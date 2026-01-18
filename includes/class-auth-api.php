<?php
/**
 * Auth API Class
 *
 * Handles REST API endpoints for authentication
 */

if (!defined('ABSPATH')) {
    exit;
}

class Casa_Alba_Auth_API {

    private $jwt_manager;
    private $rate_limiter;
    private $session_manager;
    private $analytics;
    private $turnstile_validator;

    /**
     * Constructor
     */
    public function __construct($jwt_manager, $rate_limiter, $session_manager, $analytics) {
        $this->jwt_manager = $jwt_manager;
        $this->rate_limiter = $rate_limiter;
        $this->session_manager = $session_manager;
        $this->analytics = $analytics;
        $this->turnstile_validator = new Casa_Alba_Turnstile_Validator();
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Login endpoint
        register_rest_route('casa-alba/v1', '/auth/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login'),
            'permission_callback' => '__return_true'
        ));

        // Logout endpoint
        register_rest_route('casa-alba/v1', '/auth/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'logout'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Refresh token endpoint
        register_rest_route('casa-alba/v1', '/auth/refresh', array(
            'methods' => 'POST',
            'callback' => array($this, 'refresh'),
            'permission_callback' => '__return_true'
        ));

        // Validate token endpoint
        register_rest_route('casa-alba/v1', '/auth/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate'),
            'permission_callback' => '__return_true'
        ));

        // Get current user endpoint
        register_rest_route('casa-alba/v1', '/auth/me', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_current_user'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Get user sessions endpoint
        register_rest_route('casa-alba/v1', '/auth/sessions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sessions'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Revoke session endpoint
        register_rest_route('casa-alba/v1', '/auth/sessions/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'revoke_session'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Revoke all sessions endpoint
        register_rest_route('casa-alba/v1', '/auth/sessions/all', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'revoke_all_sessions'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Get Turnstile site key endpoint
        register_rest_route('casa-alba/v1', '/auth/turnstile-key', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_turnstile_key'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Login endpoint
     */
    public function login($request) {
        $email = $request->get_param('email');
        $password = $request->get_param('password');
        $turnstile_token = $request->get_param('turnstile_token');

        // Validate required fields
        if (empty($email) || empty($password)) {
            return new WP_Error(
                'missing_credentials',
                __('Email y contraseña son requeridos', 'casa-alba-headless-auth'),
                array('status' => 400)
            );
        }

        // Check rate limiting
        $rate_check = $this->rate_limiter->check($email);
        if (is_wp_error($rate_check)) {
            $this->analytics->log_event('login', array(
                'email' => $email,
                'success' => 0,
                'error_message' => $rate_check->get_error_message()
            ));

            return $rate_check;
        }

        // Validate Turnstile if enabled
        if ($this->turnstile_validator->is_enabled()) {
            $turnstile_check = $this->turnstile_validator->validate($turnstile_token);
            if (is_wp_error($turnstile_check)) {
                $this->analytics->log_event('login', array(
                    'email' => $email,
                    'success' => 0,
                    'error_message' => 'Turnstile validation failed'
                ));

                return $turnstile_check;
            }
        }

        // Authenticate user by email
        $user = get_user_by('email', $email);

        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            $this->rate_limiter->record_attempt($email, false);
            $this->analytics->log_event('login', array(
                'email' => $email,
                'success' => 0,
                'error_message' => 'Invalid credentials'
            ));

            return new WP_Error(
                'invalid_credentials',
                __('Email o contraseña incorrectos', 'casa-alba-headless-auth'),
                array('status' => 401)
            );
        }

        // Generate tokens
        $token = $this->jwt_manager->generate_token($user->ID);
        $refresh_token = $this->jwt_manager->generate_refresh_token($user->ID);

        if (!$token || !$refresh_token) {
            return new WP_Error(
                'token_generation_failed',
                __('Error al generar tokens', 'casa-alba-headless-auth'),
                array('status' => 500)
            );
        }

        // Create session
        $session_id = $this->session_manager->create_session($user->ID, $token, $refresh_token);

        // Clear rate limit
        $this->rate_limiter->record_attempt($email, true);

        // Log successful login
        $this->analytics->log_event('login', array(
            'user_id' => $user->ID,
            'email' => $email,
            'success' => 1
        ));

        return new WP_REST_Response(array(
            'success' => true,
            'token' => $token,
            'refresh_token' => $refresh_token,
            'user' => array(
                'id' => $user->ID,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user->roles,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            )
        ), 200);
    }

    /**
     * Logout endpoint
     */
    public function logout($request) {
        $token = $this->get_token_from_request($request);

        if (!$token) {
            return new WP_Error(
                'no_token',
                __('Token no proporcionado', 'casa-alba-headless-auth'),
                array('status' => 400)
            );
        }

        $user_id = $this->jwt_manager->get_user_id_from_token($token);

        // Revoke session
        $this->session_manager->revoke_session($token);

        // Log logout
        if ($user_id) {
            $this->analytics->log_event('logout', array(
                'user_id' => $user_id
            ));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Sesión cerrada correctamente', 'casa-alba-headless-auth')
        ), 200);
    }

    /**
     * Refresh token endpoint
     */
    public function refresh($request) {
        $refresh_token = $request->get_param('refresh_token');

        if (empty($refresh_token)) {
            return new WP_Error(
                'missing_refresh_token',
                __('Refresh token es requerido', 'casa-alba-headless-auth'),
                array('status' => 400)
            );
        }

        // Refresh tokens
        $result = $this->jwt_manager->refresh_token($refresh_token);

        if (is_wp_error($result)) {
            // Revoke old session if token is invalid
            $this->session_manager->revoke_session_by_refresh_token($refresh_token);

            return $result;
        }

        // Get user ID from new token
        $user_id = $this->jwt_manager->get_user_id_from_token($result['token']);

        // Update session with new tokens
        $this->session_manager->revoke_session_by_refresh_token($refresh_token);
        $this->session_manager->create_session($user_id, $result['token'], $result['refresh_token']);

        // Log refresh
        $this->analytics->log_event('refresh', array(
            'user_id' => $user_id,
            'success' => 1
        ));

        return new WP_REST_Response(array(
            'success' => true,
            'token' => $result['token'],
            'refresh_token' => $result['refresh_token']
        ), 200);
    }

    /**
     * Validate token endpoint
     */
    public function validate($request) {
        $token = $request->get_param('token');

        if (empty($token)) {
            return new WP_Error(
                'missing_token',
                __('Token es requerido', 'casa-alba-headless-auth'),
                array('status' => 400)
            );
        }

        $payload = $this->jwt_manager->validate_token($token);

        if (is_wp_error($payload)) {
            return new WP_REST_Response(array(
                'valid' => false,
                'error' => $payload->get_error_message()
            ), 200);
        }

        // Verify session exists
        $session_valid = $this->session_manager->verify_session($token);

        if (!$session_valid) {
            return new WP_REST_Response(array(
                'valid' => false,
                'error' => __('Sesión no válida o expirada', 'casa-alba-headless-auth')
            ), 200);
        }

        // Update session activity
        $this->session_manager->update_activity($token);

        return new WP_REST_Response(array(
            'valid' => true,
            'payload' => $payload
        ), 200);
    }

    /**
     * Get current user endpoint
     */
    public function get_current_user($request) {
        $token = $this->get_token_from_request($request);
        $user_id = $this->jwt_manager->get_user_id_from_token($token);

        if (!$user_id) {
            return new WP_Error(
                'invalid_token',
                __('Token inválido', 'casa-alba-headless-auth'),
                array('status' => 401)
            );
        }

        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error(
                'user_not_found',
                __('Usuario no encontrado', 'casa-alba-headless-auth'),
                array('status' => 404)
            );
        }

        return new WP_REST_Response(array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'roles' => $user->roles
        ), 200);
    }

    /**
     * Get user sessions endpoint
     */
    public function get_sessions($request) {
        $token = $this->get_token_from_request($request);
        $user_id = $this->jwt_manager->get_user_id_from_token($token);

        if (!$user_id) {
            return new WP_Error(
                'invalid_token',
                __('Token inválido', 'casa-alba-headless-auth'),
                array('status' => 401)
            );
        }

        $sessions = $this->session_manager->get_user_sessions($user_id);

        return new WP_REST_Response(array(
            'sessions' => $sessions
        ), 200);
    }

    /**
     * Revoke session endpoint
     */
    public function revoke_session($request) {
        $session_id = $request->get_param('id');
        $token = $this->get_token_from_request($request);
        $user_id = $this->jwt_manager->get_user_id_from_token($token);

        if (!$user_id) {
            return new WP_Error(
                'invalid_token',
                __('Token inválido', 'casa-alba-headless-auth'),
                array('status' => 401)
            );
        }

        // Verify session belongs to user
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $session_id,
            $user_id
        ));

        if (!$session) {
            return new WP_Error(
                'session_not_found',
                __('Sesión no encontrada', 'casa-alba-headless-auth'),
                array('status' => 404)
            );
        }

        // Delete session
        $wpdb->delete($table, array('id' => $session_id));

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Sesión revocada correctamente', 'casa-alba-headless-auth')
        ), 200);
    }

    /**
     * Revoke all sessions endpoint
     */
    public function revoke_all_sessions($request) {
        $token = $this->get_token_from_request($request);
        $user_id = $this->jwt_manager->get_user_id_from_token($token);

        if (!$user_id) {
            return new WP_Error(
                'invalid_token',
                __('Token inválido', 'casa-alba-headless-auth'),
                array('status' => 401)
            );
        }

        // Keep current session or revoke all
        $keep_current = $request->get_param('keep_current');

        if ($keep_current) {
            // Revoke all except current
            global $wpdb;
            $table = $wpdb->prefix . 'casa_alba_auth_sessions';
            $jwt_manager = new Casa_Alba_JWT_Manager();
            $token_hash = $jwt_manager->get_token_hash($token);

            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE user_id = %d AND token_hash != %s",
                $user_id,
                $token_hash
            ));
        } else {
            // Revoke all sessions
            $this->session_manager->revoke_all_sessions($user_id);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Sesiones revocadas correctamente', 'casa-alba-headless-auth')
        ), 200);
    }

    /**
     * Get Turnstile site key
     */
    public function get_turnstile_key($request) {
        return new WP_REST_Response(array(
            'enabled' => $this->turnstile_validator->is_enabled(),
            'site_key' => $this->turnstile_validator->get_site_key()
        ), 200);
    }

    /**
     * Check authentication permission callback
     */
    public function check_authentication($request) {
        $token = $this->get_token_from_request($request);

        if (!$token) {
            return false;
        }

        $payload = $this->jwt_manager->validate_token($token);

        if (is_wp_error($payload)) {
            return false;
        }

        // Verify session
        $session_valid = $this->session_manager->verify_session($token);

        return $session_valid;
    }

    /**
     * Get token from request
     */
    private function get_token_from_request($request) {
        $auth_header = $request->get_header('authorization');

        if (empty($auth_header)) {
            return null;
        }

        // Extract token from "Bearer {token}"
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

