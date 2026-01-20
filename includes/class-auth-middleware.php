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
    }

    /**
     * Initialize middleware hooks
     */
    public function init() {
        // Hook into WordPress authentication
        add_filter('determine_current_user', array($this, 'authenticate_jwt'), 0);
        
        // Update session activity on authenticated requests
        add_action('rest_api_init', array($this, 'setup_activity_tracking'));
    }

    /**
     * Authenticate user via JWT token
     *
     * @param int|bool $user_id Current user ID or false
     * @return int|bool User ID if authenticated, original value otherwise
     */
    public function authenticate_jwt($user_id) {
        // If user is already authenticated, return
        if ($user_id) {
            return $user_id;
        }

        // Only authenticate for REST API requests
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return $user_id;
        }

        // Get token from Authorization header
        $token = $this->get_bearer_token();

        if (!$token) {
            return $user_id;
        }

        // Validate token
        $payload = $this->jwt_manager->validate_token($token);

        if (is_wp_error($payload)) {
            return $user_id;
        }

        // Verify session exists and is valid
        $session_valid = $this->session_manager->verify_session($token);

        if (!$session_valid) {
            return $user_id;
        }

        // Get user ID from token
        $authenticated_user_id = isset($payload['sub']) ? (int) $payload['sub'] : false;

        if (!$authenticated_user_id) {
            return $user_id;
        }

        // Verify user exists
        $user = get_userdata($authenticated_user_id);

        if (!$user) {
            return $user_id;
        }

        // Store token for activity tracking
        $this->current_token = $token;

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
            $this->session_manager->update_activity($this->current_token);
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
