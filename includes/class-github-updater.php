<?php
/**
 * GitHub Updater Class
 *
 * Handles automatic plugin updates from GitHub releases
 *
 * @package Casa_Alba_Headless
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Casa_Alba_GitHub_Updater
 *
 * Checks for plugin updates from GitHub releases and handles the update process
 */
class Casa_Alba_GitHub_Updater {

    /**
     * GitHub repository owner
     */
    private $github_owner = 'Im-Fran';

    /**
     * GitHub repository name
     */
    private $github_repo = 'headless-productoscasaalba';

    /**
     * Plugin slug
     */
    private $plugin_slug;

    /**
     * Plugin file
     */
    private $plugin_file;

    /**
     * Current plugin version
     */
    private $current_version;

    /**
     * Cache key for storing GitHub release data
     */
    private $cache_key = 'casa_alba_github_release';

    /**
     * Cache expiration in seconds (1 hour)
     */
    private $cache_expiration = 3600;

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->plugin_slug = plugin_basename(CASA_ALBA_HEADLESS_PLUGIN_DIR . 'headless-productoscasaalba.php');
        $this->plugin_file = CASA_ALBA_HEADLESS_PLUGIN_DIR . 'headless-productoscasaalba.php';
        $this->current_version = CASA_ALBA_HEADLESS_VERSION;

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check for updates on plugin page
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));

        // Add plugin information popup
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);

        // After plugin update
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        // Admin notices for updates
        add_action('admin_notices', array($this, 'show_update_notice'));

        // AJAX handler for manual update check
        add_action('wp_ajax_casa_alba_check_updates', array($this, 'ajax_check_updates'));

        // AJAX handler for manual update
        add_action('wp_ajax_casa_alba_update_plugin', array($this, 'ajax_update_plugin'));

        // Add GitHub token to download requests
        add_filter('http_request_args', array($this, 'add_github_token_to_request'), 10, 2);
    }

    /**
     * Add GitHub token to download requests for private repos
     *
     * @param array $args HTTP request arguments
     * @param string $url Request URL
     * @return array Modified arguments
     */
    public function add_github_token_to_request($args, $url) {
        // Only add token to GitHub API requests for our repo
        if (strpos($url, 'api.github.com') === false && strpos($url, 'github.com') === false) {
            return $args;
        }

        // Check if this is a request for our repository
        if (strpos($url, $this->github_owner . '/' . $this->github_repo) === false) {
            return $args;
        }

        // Get GitHub token
        $github_token = get_option('casa_alba_github_token', '');
        if (empty($github_token)) {
            return $args;
        }

        // Add Authorization header
        if (!isset($args['headers'])) {
            $args['headers'] = array();
        }

        $args['headers']['Authorization'] = 'Bearer ' . $github_token;

        return $args;
    }

    /**
     * Get GitHub API URL for releases
     *
     * @return string API URL
     */
    private function get_api_url() {
        return sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_owner,
            $this->github_repo
        );
    }

    /**
     * Get release data from GitHub API
     *
     * @param bool $force Force refresh from API (bypass cache)
     * @return array|false Release data or false on error
     */
    public function get_github_release($force = false) {
        // Check cache first
        if (!$force) {
            $cached = get_transient($this->cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Build headers
        $headers = array(
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
        );

        // Add GitHub token if configured
        $github_token = get_option('casa_alba_github_token', '');
        if (!empty($github_token)) {
            $headers['Authorization'] = 'Bearer ' . $github_token;
        }

        // Make API request
        $response = wp_remote_get($this->get_api_url(), array(
            'headers' => $headers,
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            error_log('Casa Alba Headless: GitHub API error - ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Casa Alba Headless: GitHub API returned status ' . $response_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $release = json_decode($body, true);

        if (empty($release) || !isset($release['tag_name'])) {
            error_log('Casa Alba Headless: Invalid GitHub release data');
            return false;
        }

        // Process release data
        $release_data = array(
            'version' => ltrim($release['tag_name'], 'v'),
            'tag_name' => $release['tag_name'],
            'name' => isset($release['name']) ? $release['name'] : $release['tag_name'],
            'body' => isset($release['body']) ? $release['body'] : '',
            'html_url' => $release['html_url'],
            'published_at' => $release['published_at'],
            'zipball_url' => $release['zipball_url'],
            'tarball_url' => $release['tarball_url'],
            'download_url' => $this->get_download_url($release),
            'checked_at' => current_time('mysql')
        );

        // Cache the result
        set_transient($this->cache_key, $release_data, $this->cache_expiration);

        return $release_data;
    }

    /**
     * Get download URL from release assets or zipball
     *
     * @param array $release GitHub release data
     * @return string Download URL
     */
    private function get_download_url($release) {
        // Check for a .zip asset first
        if (!empty($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (strpos($asset['name'], '.zip') !== false) {
                    return $asset['browser_download_url'];
                }
            }
        }

        // Fall back to zipball URL
        return $release['zipball_url'];
    }

    /**
     * Check if an update is available
     *
     * @param bool $force Force refresh from API
     * @return array|false Update info or false if no update
     */
    public function check_update_available($force = false) {
        $release = $this->get_github_release($force);

        if (!$release) {
            return false;
        }

        $latest_version = $release['version'];

        if (version_compare($latest_version, $this->current_version, '>')) {
            return array(
                'current_version' => $this->current_version,
                'new_version' => $latest_version,
                'release' => $release
            );
        }

        return false;
    }

    /**
     * Hook into WordPress update check
     *
     * @param object $transient Update transient
     * @return object Modified transient
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $update = $this->check_update_available();

        if ($update) {
            $release = $update['release'];

            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $update['new_version'],
                'url' => $release['html_url'],
                'package' => $release['download_url'],
                'icons' => array(),
                'banners' => array(),
                'banners_rtl' => array(),
                'tested' => get_bloginfo('version'),
                'requires_php' => '7.4',
                'compatibility' => new stdClass()
            );
        }

        return $transient;
    }

    /**
     * Provide plugin information for the plugins API
     *
     * @param false|object|array $result Plugin API result
     * @param string $action API action
     * @param object $args API arguments
     * @return false|object Plugin info or false
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $release = $this->get_github_release();

        if (!$release) {
            return $result;
        }

        $plugin_info = new stdClass();
        $plugin_info->name = 'Headless Productos Casa Alba';
        $plugin_info->slug = dirname($this->plugin_slug);
        $plugin_info->version = $release['version'];
        $plugin_info->author = '<a href="https://franciscosolis.cl">Francisco Solis</a>';
        $plugin_info->homepage = 'https://github.com/' . $this->github_owner . '/' . $this->github_repo;
        $plugin_info->requires = '5.8';
        $plugin_info->tested = get_bloginfo('version');
        $plugin_info->requires_php = '7.4';
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = $release['published_at'];
        $plugin_info->sections = array(
            'description' => 'Plugin completo para frontend headless: autenticación JWT con Cloudflare Turnstile, gestión de sesiones, checkout URLs modificadas, redirección automática al frontend, y APIs personalizadas para clientes y pedidos.',
            'changelog' => $this->format_changelog($release['body']),
            'installation' => 'Sube el plugin a tu directorio de plugins de WordPress y actívalo.'
        );
        $plugin_info->download_link = $release['download_url'];

        return $plugin_info;
    }

    /**
     * Format changelog from markdown
     *
     * @param string $body Release body in markdown
     * @return string Formatted HTML
     */
    private function format_changelog($body) {
        if (empty($body)) {
            return '<p>No hay notas de la versión disponibles.</p>';
        }

        // Convert markdown to basic HTML
        $html = $body;

        // Convert markdown headers
        $html = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $html);

        // Convert markdown lists
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html);

        // Convert markdown bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

        // Convert markdown italic
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Convert line breaks
        $html = nl2br($html);

        return $html;
    }

    /**
     * Handle post-installation tasks
     *
     * @param bool $response Installation response
     * @param array $hook_extra Extra hook info
     * @param array $result Installation result
     * @return array Modified result
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return $result;
        }

        // GitHub downloads include a directory with the repo name and commit hash
        // We need to rename it to match our plugin directory
        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->plugin_slug);

        // Move contents from the result destination to our plugin folder
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;

        // Clear the cache
        delete_transient($this->cache_key);

        return $result;
    }

    /**
     * Show admin notice when update is available
     */
    public function show_update_notice() {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }

        // Only show to users who can update plugins
        if (!current_user_can('update_plugins')) {
            return;
        }

        // Don't show on the plugins page (WordPress handles that)
        $screen = get_current_screen();
        if ($screen && $screen->base === 'plugins') {
            return;
        }

        // Only show on our plugin's pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'casa-alba-headless') === false) {
            return;
        }

        $update = $this->check_update_available();

        if ($update) {
            $update_url = wp_nonce_url(
                admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($this->plugin_slug)),
                'upgrade-plugin_' . $this->plugin_slug
            );

            printf(
                '<div class="notice notice-warning is-dismissible casa-alba-update-notice">
                    <p><strong>%s</strong></p>
                    <p>%s</p>
                    <p>
                        <a href="%s" class="button button-primary">%s</a>
                        <a href="%s" target="_blank" class="button">%s</a>
                    </p>
                </div>',
                esc_html__('¡Nueva versión disponible!', 'headless-productoscasaalba'),
                sprintf(
                    esc_html__('Hay una nueva versión del plugin Headless Productos Casa Alba disponible. Versión actual: %1$s | Nueva versión: %2$s', 'headless-productoscasaalba'),
                    esc_html($update['current_version']),
                    esc_html($update['new_version'])
                ),
                esc_url($update_url),
                esc_html__('Actualizar ahora', 'headless-productoscasaalba'),
                esc_url($update['release']['html_url']),
                esc_html__('Ver notas de la versión', 'headless-productoscasaalba')
            );
        }
    }

    /**
     * AJAX handler for checking updates
     */
    public function ajax_check_updates() {
        check_ajax_referer('casa_alba_headless_admin', 'nonce');

        if (!current_user_can('update_plugins')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        // Force refresh from API
        $update = $this->check_update_available(true);

        if ($update) {
            wp_send_json_success(array(
                'update_available' => true,
                'current_version' => $update['current_version'],
                'new_version' => $update['new_version'],
                'release_url' => $update['release']['html_url'],
                'release_name' => $update['release']['name'],
                'published_at' => $update['release']['published_at'],
                'changelog' => $this->format_changelog($update['release']['body'])
            ));
        } else {
            $release = $this->get_github_release(true);
            wp_send_json_success(array(
                'update_available' => false,
                'current_version' => $this->current_version,
                'latest_version' => $release ? $release['version'] : $this->current_version,
                'checked_at' => current_time('mysql')
            ));
        }
    }

    /**
     * AJAX handler for triggering plugin update
     */
    public function ajax_update_plugin() {
        check_ajax_referer('casa_alba_headless_admin', 'nonce');

        if (!current_user_can('update_plugins')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        $update = $this->check_update_available();

        if (!$update) {
            wp_send_json_error(array('message' => 'No hay actualizaciones disponibles'));
        }

        // Return the update URL for the frontend to redirect
        $update_url = wp_nonce_url(
            admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($this->plugin_slug)),
            'upgrade-plugin_' . $this->plugin_slug
        );

        wp_send_json_success(array(
            'update_url' => $update_url
        ));
    }

    /**
     * Get current plugin version
     *
     * @return string Current version
     */
    public function get_current_version() {
        return $this->current_version;
    }

    /**
     * Get update status info
     *
     * @return array Status info
     */
    public function get_update_status() {
        $release = $this->get_github_release();
        $update = $this->check_update_available();

        return array(
            'current_version' => $this->current_version,
            'latest_version' => $release ? $release['version'] : $this->current_version,
            'update_available' => (bool) $update,
            'release' => $release,
            'checked_at' => $release ? $release['checked_at'] : null
        );
    }
}
