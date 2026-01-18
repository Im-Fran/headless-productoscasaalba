<?php
/**
 * Plugin Name: Headless Productos Casa Alba
 * Plugin URI: https://productoscasaalba.cl
 * Description: Plugin completo para frontend headless: autenticación JWT con Cloudflare Turnstile, gestión de sesiones, checkout URLs modificadas, redirección automática al frontend, y APIs personalizadas para clientes y pedidos.
 * Version: 1.2.2
 * Author: Francisco Solis
 * Author URI: https://franciscosolis.cl
 * License: GPL v3
 * Text Domain: headless-productoscasaalba
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants
define('CASA_ALBA_HEADLESS_VERSION', '1.2.2');
define('CASA_ALBA_HEADLESS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CASA_ALBA_HEADLESS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Class Casa_Alba_Headless
 *
 * Main plugin class that consolidates authentication and checkout functionality
 */
class Casa_Alba_Headless {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * JWT Manager instance
     */
    private $jwt_manager;

    /**
     * Rate Limiter instance
     */
    private $rate_limiter;

    /**
     * Session Manager instance
     */
    private $session_manager;

    /**
     * Analytics instance
     */
    private $analytics;

    /**
     * Auth Middleware instance
     */
    private $auth_middleware;

    /**
     * Customer API instance
     */
    private $customer_api;

    /**
     * Orders API instance
     */
    private $orders_api;

    /**
     * GitHub Updater instance
     */
    private $github_updater;

    /**
     * Frontend URL from environment or settings
     */
    private $frontend_url;

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
        // Get frontend URL from wp-config.php constant or use default
        $this->frontend_url = defined('CASA_ALBA_FRONTEND_URL')
            ? CASA_ALBA_FRONTEND_URL
            : get_option('casa_alba_frontend_url', 'https://productoscasaalba.cl');

        // Load dependencies
        $this->load_dependencies();

        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));

        // Initialize hooks
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Database tables installation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Authentication classes
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-jwt-manager.php';
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-rate-limiter.php';
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-session-manager.php';
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-analytics.php';
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-turnstile-validator.php';
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-auth-api.php';
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-auth-middleware.php';

        // Checkout and WooCommerce API classes
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-customer-api.php';
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-orders-api.php';

        // GitHub Updater
        require_once CASA_ALBA_HEADLESS_PLUGIN_DIR . 'includes/class-github-updater.php';
    }

    /**
     * Initialize components
     */
    public function init_components() {
        // Initialize authentication components
        $this->jwt_manager = new Casa_Alba_JWT_Manager();
        $this->rate_limiter = new Casa_Alba_Rate_Limiter();
        $this->session_manager = new Casa_Alba_Session_Manager();
        $this->analytics = new Casa_Alba_Auth_Analytics();

        // Initialize middleware
        $this->auth_middleware = new Casa_Alba_Auth_Middleware($this->jwt_manager, $this->session_manager);
        $this->auth_middleware->init();

        // Initialize WooCommerce API classes
        $this->customer_api = new Casa_Alba_Customer_API();
        $this->orders_api = new Casa_Alba_Orders_API();

        // Initialize GitHub Updater
        $this->github_updater = Casa_Alba_GitHub_Updater::get_instance();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('headless-productoscasaalba', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            error_log('Casa Alba Headless: WooCommerce not found');
            return;
        }

        // Initialize checkout URL modifications
        $this->init_checkout_hooks();

        // Initialize frontend redirect (inspired by Headless Mode plugin)
        $this->init_frontend_redirect();

        // Log initialization
        error_log('Casa Alba Headless: Plugin initialized with frontend URL: ' . $this->frontend_url);
    }

    /**
     * Initialize frontend redirect functionality
     * Inspired by Headless Mode plugin (https://wordpress.org/plugins/headless-mode/)
     * Credits to the original authors for the redirect concept
     */
    private function init_frontend_redirect() {
        // Hook to intercept frontend requests
        add_action('parse_request', array($this, 'redirect_to_frontend'), 99);
    }

    /**
     * Get ASN from Cloudflare headers
     *
     * @return string|null ASN number or null if not available
     */
    private function get_cloudflare_asn() {
        // Cloudflare provides ASN in the CF-Connecting-ASN header
        if (isset($_SERVER['HTTP_CF_CONNECTING_ASN'])) {
            return sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_ASN']);
        }

        return null;
    }

    /**
     * Check if the current request ASN should be ignored for redirect
     *
     * @return bool True if ASN should be ignored, false otherwise
     */
    private function should_ignore_asn() {
        $asn = $this->get_cloudflare_asn();

        if (empty($asn)) {
            return false;
        }

        // Get ignored ASNs from settings
        $ignored_asns = get_option('casa_alba_ignored_asns', '');

        if (empty($ignored_asns)) {
            return false;
        }

        // Convert comma-separated string to array and trim whitespace
        $asn_list = array_map('trim', explode(',', $ignored_asns));

        // Check if current ASN is in the ignored list
        if (in_array($asn, $asn_list)) {
            error_log('Casa Alba Headless: Ignoring redirect for ASN: ' . $asn);
            return true;
        }

        return false;
    }

    /**
     * Redirect frontend requests to the headless frontend
     * This prevents users from accessing the WordPress frontend directly
     *
     * Inspired by Headless Mode plugin functionality
     * Credits: Headless Mode plugin (https://wordpress.org/plugins/headless-mode/)
     */
    public function redirect_to_frontend() {
        // Check if redirect is enabled in settings
        $redirect_enabled = get_option('casa_alba_enable_frontend_redirect', false);

        if (!$redirect_enabled) {
            return;
        }

        // Check if the request ASN should be ignored
        if ($this->should_ignore_asn()) {
            return;
        }

        // Allow filter to disable redirect (e.g., for logged-in editors)
        $disable_redirect = apply_filters('casa_alba_headless_disable_redirect', !current_user_can('edit_posts'));

        if (false === $disable_redirect) {
            return;
        }

        global $wp;

        // Don't redirect if:
        // - In admin area
        // - Processing REST API request
        // - Processing GraphQL request
        // - Running cron
        // - OAuth request
        $should_skip = (
            is_admin() ||
            defined('REST_REQUEST') ||
            defined('GRAPHQL_HTTP_REQUEST') ||
            defined('DOING_CRON') ||
            !empty($wp->query_vars['rest_oauth1'])
        );

        if ($should_skip) {
            return;
        }

        // Check if frontend URL is configured
        if (empty($this->frontend_url) || $this->frontend_url === 'https://productoscasaalba.cl') {
            // Only redirect if explicitly configured
            $configured_url = get_option('casa_alba_frontend_url');
            if (empty($configured_url)) {
                return;
            }
        }

        // Build the redirect URL
        $new_url = trailingslashit($this->frontend_url);

        // Append the request path
        if (!empty($wp->request)) {
            $new_url .= $wp->request;
        }

        // Append query string if present
        if (!empty($_SERVER['QUERY_STRING'])) {
            $new_url .= '?' . $_SERVER['QUERY_STRING'];
        }

        // Allow filtering the redirect behavior
        $should_redirect = apply_filters('casa_alba_headless_will_redirect', true, $new_url);

        if ($should_redirect) {
            $asn = $this->get_cloudflare_asn();
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : 'Unknown';
            error_log('Casa Alba Headless: Redirecting to frontend - ' . $new_url . ' | ASN: ' . ($asn ?: 'N/A') . ' | User-Agent: ' . $user_agent);
        	header('Location: ' . $new_url, true, 301);
            exit;
        }
    }

    /**
     * Initialize checkout URL modification hooks
     */
    private function init_checkout_hooks() {
        // Hook to modify checkout return URL (order received page)
        add_filter('woocommerce_get_checkout_order_received_url', array($this, 'modify_order_received_url'), 10, 2);

        // Hook to modify checkout cancel URL
        add_filter('woocommerce_get_cancel_order_url', array($this, 'modify_cancel_order_url'), 10, 2);
        add_filter('woocommerce_get_cancel_order_url_raw', array($this, 'modify_cancel_order_url'), 10, 2);

        // Hook for payment gateway return URLs (for gateways like PayPal, Mercado Pago, etc.)
        add_filter('woocommerce_get_return_url', array($this, 'modify_return_url'), 10, 2);

        // Hook to modify checkout payment URL
        add_filter('woocommerce_get_checkout_payment_url', array($this, 'modify_payment_url'), 10, 2);

        // Hook for Store API checkout response (used by the headless frontend)
        add_filter('woocommerce_store_api_checkout_order_response', array($this, 'modify_store_api_response'), 10, 2);
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Authentication API
        $auth_api = new Casa_Alba_Auth_API(
            $this->jwt_manager,
            $this->rate_limiter,
            $this->session_manager,
            $this->analytics
        );
        $auth_api->register_routes();

        // Customer and Orders APIs
        $this->customer_api->register_routes();
        $this->orders_api->register_routes();
    }

    /**
     * Modify order received URL (thank you page)
     */
    public function modify_order_received_url($url, $order) {
        if (!$order) {
            return $url;
        }

        $order_id = is_numeric($order) ? $order : $order->get_id();
        $order_key = is_object($order) ? $order->get_order_key() : '';

        // Build frontend URL for order confirmation
        $frontend_url = trailingslashit($this->frontend_url) . 'pedido-recibido';

        // Add order parameters
        $frontend_url = add_query_arg(array(
            'order_id' => $order_id,
            'key' => $order_key
        ), $frontend_url);

        error_log('Casa Alba Headless: Modified order received URL from ' . $url . ' to ' . $frontend_url);

        return $frontend_url;
    }

    /**
     * Modify cancel order URL
     */
    public function modify_cancel_order_url($url, $order = null) {
        // Build frontend URL for cancellation
        $frontend_url = trailingslashit($this->frontend_url) . 'pedido-cancelado';

        // If we have order info, add it to the URL
        if ($order && is_object($order)) {
            $order_id = $order->get_id();
            $frontend_url = add_query_arg(array(
                'order_id' => $order_id
            ), $frontend_url);
        }

        error_log('Casa Alba Headless: Modified cancel URL from ' . $url . ' to ' . $frontend_url);

        return $frontend_url;
    }

    /**
     * Modify return URL for payment gateways
     */
    public function modify_return_url($url, $order) {
        if (!$order) {
            return $url;
        }

        // Check order status to determine which page to redirect to
        $order_status = $order->get_status();

        if (in_array($order_status, array('pending', 'on-hold', 'processing', 'completed'))) {
            // Success - redirect to order received page
            return $this->modify_order_received_url($url, $order);
        } else if (in_array($order_status, array('failed', 'cancelled'))) {
            // Failed or cancelled - redirect to cancel/failed page
            return $this->modify_cancel_order_url($url, $order);
        }

        // Default to order received URL
        return $this->modify_order_received_url($url, $order);
    }

    /**
     * Modify payment URL
     */
    public function modify_payment_url($url, $order) {
        // For now, keep payment URL as is since it needs to process through WooCommerce
        // But log it for debugging
        error_log('Casa Alba Headless: Payment URL: ' . $url);
        return $url;
    }

    /**
     * Modify Store API checkout response
     * This is crucial for headless frontends using the Store API
     */
    public function modify_store_api_response($response, $order) {
        if (!isset($response['redirect_url'])) {
            return $response;
        }

        // Get the payment method to determine if we need special handling
        $payment_method = $order->get_payment_method();

        error_log('Casa Alba Headless: Store API response for payment method: ' . $payment_method);
        error_log('Casa Alba Headless: Original redirect URL: ' . $response['redirect_url']);

        // Check if this is a direct payment method (no external redirect needed)
        $direct_payment_methods = array('bacs', 'cheque', 'cod'); // Bank transfer, check, cash on delivery

        if (in_array($payment_method, $direct_payment_methods)) {
            // For direct payment methods, redirect to order confirmation
            $response['redirect_url'] = $this->modify_order_received_url('', $order);
        } else {
            // For external payment gateways (PayPal, Mercado Pago, etc.)
            // The redirect_url should point to the payment gateway
            // The gateway will then redirect back using our modified return URLs
            error_log('Casa Alba Headless: Keeping gateway redirect URL: ' . $response['redirect_url']);
        }

        error_log('Casa Alba Headless: Modified redirect URL: ' . $response['redirect_url']);

        return $response;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Headless Casa Alba', 'headless-productoscasaalba'),
            __('Headless', 'headless-productoscasaalba'),
            'manage_options',
            'casa-alba-headless',
            array($this, 'render_settings_page'),
            'dashicons-lock',
            30
        );

        // Submenu - Settings
        add_submenu_page(
            'casa-alba-headless',
            __('Configuración', 'headless-productoscasaalba'),
            __('Configuración', 'headless-productoscasaalba'),
            'manage_options',
            'casa-alba-headless',
            array($this, 'render_settings_page')
        );

        // Submenu - Active Sessions
        add_submenu_page(
            'casa-alba-headless',
            __('Sesiones Activas', 'headless-productoscasaalba'),
            __('Sesiones Activas', 'headless-productoscasaalba'),
            'manage_options',
            'casa-alba-headless-sessions',
            array($this, 'render_sessions_page')
        );

        // Submenu - Analytics
        add_submenu_page(
            'casa-alba-headless',
            __('Analítica', 'headless-productoscasaalba'),
            __('Analítica', 'headless-productoscasaalba'),
            'manage_options',
            'casa-alba-headless-analytics',
            array($this, 'render_analytics_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Authentication settings
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_jwt_algorithm');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_jwt_secret');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_jwt_expiration');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_jwt_refresh_expiration');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_turnstile_enabled');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_turnstile_site_key');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_turnstile_secret_key');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_rate_limit_enabled');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_rate_limit_max_attempts');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_rate_limit_window');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_rate_limit_lockout_duration');
        register_setting('casa_alba_headless_settings', 'casa_alba_auth_session_limit');

        // Checkout settings
        register_setting('casa_alba_headless_settings', 'casa_alba_frontend_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://productoscasaalba.cl'
        ));

        // Redirect settings
        register_setting('casa_alba_headless_settings', 'casa_alba_enable_frontend_redirect', array(
            'type' => 'boolean',
            'default' => false
        ));

        // Ignored ASNs for redirect bypass (comma-separated)
        register_setting('casa_alba_headless_settings', 'casa_alba_ignored_asns', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        // GitHub token for API requests
        register_setting('casa_alba_headless_settings', 'casa_alba_github_token', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'casa-alba-headless') === false) {
            return;
        }

        wp_enqueue_style(
            'casa-alba-headless-admin',
            CASA_ALBA_HEADLESS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CASA_ALBA_HEADLESS_VERSION
        );

        wp_enqueue_script(
            'casa-alba-headless-admin',
            CASA_ALBA_HEADLESS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CASA_ALBA_HEADLESS_VERSION,
            true
        );

        wp_localize_script('casa-alba-headless-admin', 'casaAlbaHeadless', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('casa_alba_headless_admin')
        ));
    }

    /**
     * Render admin settings page
     */
    public function render_settings_page() {
        include CASA_ALBA_HEADLESS_PLUGIN_DIR . 'admin/settings-page.php';
    }

    /**
     * Render sessions management page
     */
    public function render_sessions_page() {
        include CASA_ALBA_HEADLESS_PLUGIN_DIR . 'admin/sessions-page.php';
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        include CASA_ALBA_HEADLESS_PLUGIN_DIR . 'admin/analytics-page.php';
    }

    /**
     * Admin notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('Headless Productos Casa Alba requiere que WooCommerce esté instalado y activado.', 'headless-productoscasaalba'); ?></p>
        </div>
        <?php
    }

    /**
     * Plugin activation
     */
    public function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create sessions table
        $sessions_table = $wpdb->prefix . 'casa_alba_auth_sessions';
        $sessions_sql = "CREATE TABLE IF NOT EXISTS $sessions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            token_hash varchar(255) NOT NULL,
            refresh_token_hash varchar(255) NOT NULL,
            device_type varchar(50) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            os varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY token_hash (token_hash),
            KEY refresh_token_hash (refresh_token_hash),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Create rate limiting table
        $rate_limit_table = $wpdb->prefix . 'casa_alba_auth_rate_limits';
        $rate_limit_sql = "CREATE TABLE IF NOT EXISTS $rate_limit_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            attempts int(11) DEFAULT 0,
            locked_until datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY identifier (identifier),
            KEY locked_until (locked_until)
        ) $charset_collate;";

        // Create analytics table
        $analytics_table = $wpdb->prefix . 'casa_alba_auth_analytics';
        $analytics_sql = "CREATE TABLE IF NOT EXISTS $analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            metadata text DEFAULT NULL,
            success tinyint(1) DEFAULT 1,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY success (success)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sessions_sql);
        dbDelta($rate_limit_sql);
        dbDelta($analytics_sql);

        // Set default options
        if (!get_option('casa_alba_auth_jwt_algorithm')) {
            add_option('casa_alba_auth_jwt_algorithm', 'HS256');
        }
        if (!get_option('casa_alba_auth_jwt_secret')) {
            add_option('casa_alba_auth_jwt_secret', wp_generate_password(64, true, true));
        }
        if (!get_option('casa_alba_auth_jwt_expiration')) {
            add_option('casa_alba_auth_jwt_expiration', 3600); // 1 hour
        }
        if (!get_option('casa_alba_auth_jwt_refresh_expiration')) {
            add_option('casa_alba_auth_jwt_refresh_expiration', 604800); // 7 days
        }
        if (!get_option('casa_alba_auth_rate_limit_enabled')) {
            add_option('casa_alba_auth_rate_limit_enabled', 1);
        }
        if (!get_option('casa_alba_auth_rate_limit_max_attempts')) {
            add_option('casa_alba_auth_rate_limit_max_attempts', 5);
        }
        if (!get_option('casa_alba_auth_rate_limit_window')) {
            add_option('casa_alba_auth_rate_limit_window', 900); // 15 minutes
        }
        if (!get_option('casa_alba_auth_rate_limit_lockout_duration')) {
            add_option('casa_alba_auth_rate_limit_lockout_duration', 1800); // 30 minutes
        }
        if (!get_option('casa_alba_auth_session_limit')) {
            add_option('casa_alba_auth_session_limit', 5); // Max 5 sessions per user
        }
        if (!get_option('casa_alba_frontend_url')) {
            add_option('casa_alba_frontend_url', 'https://productoscasaalba.cl');
        }
        if (!get_option('casa_alba_ignored_asns')) {
            add_option('casa_alba_ignored_asns', '');
        }

        error_log('Casa Alba Headless: Plugin activated');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        error_log('Casa Alba Headless: Plugin deactivated');
    }
}

// Initialize plugin
function casa_alba_headless() {
    return Casa_Alba_Headless::get_instance();
}

casa_alba_headless();
