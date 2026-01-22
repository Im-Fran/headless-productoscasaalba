<?php
/**
 * Auth Middleware Class
 *
 * Handles JWT authentication middleware for WordPress REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Casa_Alba_Auth_Middleware {

    /**
     * Static instances to share across all method calls
     */
    private static $jwt_manager_instance = null;
    private static $session_manager_instance = null;

    /**
     * Instance properties (for backward compatibility)
     */
    private $jwt_manager;
    private $session_manager;
    private $current_token;

    /**
     * Constructor
     */
    public function __construct($jwt_manager, $session_manager) {
        $this->jwt_manager = $jwt_manager;
        $this->session_manager = $session_manager;
        $this->current_token = null;

        // Store in static for use across instances
        self::$jwt_manager_instance = $jwt_manager;
        self::$session_manager_instance = $session_manager;
    }

    /**
     * Get JWT manager (static or instance)
     */
    private function get_jwt_manager() {
        return self::$jwt_manager_instance ?: $this->jwt_manager;
    }

    /**
     * Get session manager (static or instance)
     */
    private function get_session_manager() {
        return self::$session_manager_instance ?: $this->session_manager;
    }

    /**
     * Initialize middleware hooks
     */
    public function init() {
        // Hook into WordPress authentication with LOW priority (9) to run BEFORE
        // wp_validate_auth_cookie and wp_validate_logged_in_cookie (both priority 10/20)
        // This way we can authenticate via JWT before WordPress tries cookie auth
        add_filter('determine_current_user', array($this, 'authenticate_jwt'), 9);

        // Also add a late hook to catch any cases where we missed the early hook
        add_filter('determine_current_user', array($this, 'authenticate_jwt_late'), 99);

        // Update session activity on authenticated requests
        add_action('rest_api_init', array($this, 'setup_activity_tracking'));

        // Register debug endpoint
        add_action('rest_api_init', array($this, 'register_debug_endpoint'));

        // Hook into REST authentication errors with priority 999 to run AFTER rest_cookie_check_errors (priority 100)
        // This allows us to clear cookie-related errors when JWT is valid
        add_filter('rest_authentication_errors', array($this, 'handle_rest_authentication_errors'), 999);

        // CRITICAL: Force JWT re-authentication at the very beginning of REST API processing
        // This runs with priority 1 to execute before any other rest_api_init callbacks
        // It forces re-evaluation of the current user when a JWT token is present
        add_action('rest_api_init', array($this, 'force_jwt_auth_for_rest'), 1);
    }

    /**
     * Late authentication hook - runs after all other determine_current_user filters
     * This is a fallback in case the early hook didn't work
     */
    public function authenticate_jwt_late($user_id) {
        // If user is already authenticated (not 0), don't override
        if ($user_id && $user_id > 0) {
            return $user_id;
        }

        // Check for JWT token
        $token = $this->get_bearer_token();
        if (!$token) {
            return $user_id;
        }

        // We have a token but user_id is 0, try to authenticate
        return $this->do_jwt_authentication($token, $user_id);
    }

    /**
     * Force JWT authentication at the beginning of REST API requests
     *
     * This is necessary because WordPress may have already cached the current user
     * (as user 0) due to an invalid/expired WordPress cookie before our plugin loaded.
     * This function checks for a JWT token and forces authentication if present.
     */
    public function force_jwt_auth_for_rest() {
        // Check if we have a JWT token
        $token = $this->get_bearer_token();
        if ($token) {
            // If we have a valid JWT token, remove the rest_cookie_check_errors filter
            // This filter causes issues when both a JWT token AND a WordPress cookie are present
            // The cookie might be invalid/expired but the JWT is valid, so we don't want
            // WordPress to reject the request based on the invalid cookie
            remove_filter('rest_authentication_errors', 'rest_cookie_check_errors', 100);
        }

        // Check if we have a JWT token
        $token = $this->get_bearer_token();
        if (!$token) {
            return;
        }

        // If there's a JWT token but the user is not authenticated (user_id = 0),
        // we need to force re-authentication
        if (get_current_user_id() === 0) {
            // Validate token
            $jwt_manager = $this->get_jwt_manager();
            $session_manager = $this->get_session_manager();

            if (!$jwt_manager || !$session_manager) {
                return;
            }

            $payload = $jwt_manager->validate_token($token);
            if (is_wp_error($payload)) {
                return;
            }

            // Verify session
            if (!$session_manager->verify_session($token)) {
                return;
            }

            // Get user ID from token
            $user_id = $jwt_manager->get_user_id_from_token($token);
            if (!$user_id) {
                return;
            }

            // Verify user exists
            if (!get_userdata($user_id)) {
                return;
            }

            // Force set the current user
            wp_set_current_user($user_id);
        }
    }

    /**
     * Handle REST authentication errors
     *
     * This filter is called when WordPress determines if the REST request is authenticated.
     * If there's a JWT token and the user was authenticated via JWT, we clear any errors.
     * This helps handle cases where WordPress cookie authentication fails but JWT is valid.
     *
     * @param WP_Error|null|true $errors Authentication errors or null/true if authenticated
     * @return WP_Error|null|true
     */
    public function handle_rest_authentication_errors($errors) {
        // If no errors, nothing to do
        if (!is_wp_error($errors)) {
            return $errors;
        }

        // Check if we have a valid JWT token
        $token = $this->get_bearer_token();
        if (!$token) {
            return $errors;
        }

        // Validate token
        $payload = $this->get_jwt_manager()->validate_token($token);
        if (is_wp_error($payload)) {
            return $errors;
        }

        // Verify session exists and is valid
        $session_valid = $this->get_session_manager()->verify_session($token);
        if (!$session_valid) {
            return $errors;
        }

        // Get user ID from token
        $authenticated_user_id = $this->get_jwt_manager()->get_user_id_from_token($token);
        if (!$authenticated_user_id) {
            return $errors;
        }

        // Verify user exists
        $user = get_userdata($authenticated_user_id);
        if (!$user) {
            return $errors;
        }
        // Force set the current user since we have a valid JWT
        wp_set_current_user($authenticated_user_id);

        return null;
    }

    /**
     * Authenticate user via JWT token
     *
     * @param int|bool $user_id Current user ID or false
     * @return int|bool User ID if authenticated, original value otherwise
     */
    public function authenticate_jwt($user_id) {
        // Get token from Authorization header
        $token = $this->get_bearer_token();

        // If no JWT token present, return the original user_id (from cookie auth or other methods)
        if (!$token) {
            return $user_id;
        }

        // If JWT token is present, it takes precedence over cookie authentication
        return $this->do_jwt_authentication($token, $user_id);
    }

    /**
     * Perform JWT authentication
     *
     * @param string $token JWT token
     * @param int|bool $fallback_user_id User ID to return if authentication fails
     * @return int|bool Authenticated user ID or fallback
     */
    private function do_jwt_authentication($token, $fallback_user_id) {
        // Validate token
        $payload = $this->get_jwt_manager()->validate_token($token);

        if (is_wp_error($payload)) {
            return $fallback_user_id;
        }

        // Verify session exists and is valid
        $session_valid = $this->get_session_manager()->verify_session($token);
        if (!$session_valid) {
            return $fallback_user_id;
        }

        // Get user ID from token
        $authenticated_user_id = $this->get_jwt_manager()->get_user_id_from_token($token);
        if (!$authenticated_user_id) {
            return $fallback_user_id;
        }

        // Verify user exists
        $user = get_userdata($authenticated_user_id);
        if (!$user) {
            return $fallback_user_id;
        }

        // Store token for activity tracking
        $this->current_token = $token;

        // Force WordPress to use our authenticated user by clearing the current user cache
        // This is necessary because wp_validate_logged_in_cookie may have already set the user to 0
        global $current_user;
        $current_user = null;

        // Also set the user to ensure WordPress uses our authenticated user
        wp_set_current_user($authenticated_user_id);

        return $authenticated_user_id;
    }

    /**
     * Setup activity tracking for authenticated requests
     */
    public function setup_activity_tracking() {
        add_action('rest_pre_dispatch', array($this, 'update_session_activity'), 10, 3);
    }

    /**
     * Update session activity for authenticated requests
     */
    public function update_session_activity($result, $server, $request) {
        // Only update if user is authenticated via JWT
        if ($this->current_token) {
            $this->get_session_manager()->update_activity($this->current_token);
        }

        return $result;
    }

    /**
     * Get bearer token from Authorization header
     *
     * @return string|null Token or null if not found
     */
    private function get_bearer_token() {
        // Try to get from Authorization header
        $auth_header = null;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $auth_header = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $auth_header = $headers['authorization'];
            }
        }

        if (empty($auth_header)) {
            return null;
        }

        // Extract token from "Bearer {token}"
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
