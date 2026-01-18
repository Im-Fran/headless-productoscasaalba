<?php
/**
 * JWT Manager Class
 *
 * Handles JWT token generation, validation, and refresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class Casa_Alba_JWT_Manager {

    /**
     * Supported JWT algorithms
     */
    private $supported_algorithms = array(
        'HS256' => 'SHA256',
        'HS384' => 'SHA384',
        'HS512' => 'SHA512'
    );

    /**
     * Get JWT secret
     */
    private function get_secret() {
        $secret = get_option('casa_alba_auth_jwt_secret');
        if (empty($secret)) {
            // Generate and save a new secret
            $secret = wp_generate_password(64, true, true);
            update_option('casa_alba_auth_jwt_secret', $secret);
        }
        return $secret;
    }

    /**
     * Get JWT algorithm
     */
    private function get_algorithm() {
        return get_option('casa_alba_auth_jwt_algorithm', 'HS256');
    }

    /**
     * Base64 URL encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Generate JWT token
     *
     * @param int $user_id WordPress user ID
     * @param array $additional_claims Additional claims to include
     * @return string JWT token
     */
    public function generate_token($user_id, $additional_claims = array()) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $issued_at = time();
        $expiration = $issued_at + (int) get_option('casa_alba_auth_jwt_expiration', 3600);

        $payload = array(
            'iss' => get_bloginfo('url'), // Issuer
            'iat' => $issued_at, // Issued at
            'exp' => $expiration, // Expiration
            'sub' => $user_id, // Subject (user ID)
            'email' => $user->user_email,
            'data' => array(
                'user' => array(
                    'id' => $user_id,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'roles' => $user->roles
                )
            )
        );

        // Merge additional claims
        $payload = array_merge($payload, $additional_claims);

        return $this->encode_token($payload);
    }

    /**
     * Generate refresh token
     *
     * @param int $user_id WordPress user ID
     * @return string Refresh token
     */
    public function generate_refresh_token($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $issued_at = time();
        $expiration = $issued_at + (int) get_option('casa_alba_auth_jwt_refresh_expiration', 604800);

        $payload = array(
            'iss' => get_bloginfo('url'),
            'iat' => $issued_at,
            'exp' => $expiration,
            'sub' => $user_id,
            'type' => 'refresh',
            'jti' => wp_generate_uuid4() // JWT ID for tracking
        );

        return $this->encode_token($payload);
    }

    /**
     * Encode token
     */
    private function encode_token($payload) {
        $algorithm = $this->get_algorithm();
        $secret = $this->get_secret();

        if (!isset($this->supported_algorithms[$algorithm])) {
            return false;
        }

        $header = array(
            'typ' => 'JWT',
            'alg' => $algorithm
        );

        $segments = array(
            $this->base64url_encode(json_encode($header)),
            $this->base64url_encode(json_encode($payload))
        );

        $signing_input = implode('.', $segments);
        $signature = $this->sign($signing_input, $secret, $algorithm);
        $segments[] = $this->base64url_encode($signature);

        return implode('.', $segments);
    }

    /**
     * Sign data
     */
    private function sign($data, $secret, $algorithm) {
        $hash_algorithm = $this->supported_algorithms[$algorithm];
        return hash_hmac($hash_algorithm, $data, $secret, true);
    }

    /**
     * Validate JWT token
     *
     * @param string $token JWT token
     * @return array|WP_Error Token payload or error
     */
    public function validate_token($token) {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return new WP_Error('invalid_token', __('Token inválido', 'casa-alba-headless-auth'));
        }

        list($header_b64, $payload_b64, $signature_b64) = $parts;

        // Decode header and payload
        $header = json_decode($this->base64url_decode($header_b64), true);
        $payload = json_decode($this->base64url_decode($payload_b64), true);

        if (!$header || !$payload) {
            return new WP_Error('invalid_token', __('Token malformado', 'casa-alba-headless-auth'));
        }

        // Verify algorithm
        $algorithm = $this->get_algorithm();
        if (!isset($header['alg']) || $header['alg'] !== $algorithm) {
            return new WP_Error('invalid_algorithm', __('Algoritmo no soportado', 'casa-alba-headless-auth'));
        }

        // Verify signature
        $signing_input = $header_b64 . '.' . $payload_b64;
        $secret = $this->get_secret();
        $expected_signature = $this->base64url_encode($this->sign($signing_input, $secret, $algorithm));

        if (!hash_equals($expected_signature, $signature_b64)) {
            return new WP_Error('invalid_signature', __('Firma inválida', 'casa-alba-headless-auth'));
        }

        // Verify expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return new WP_Error('token_expired', __('Token expirado', 'casa-alba-headless-auth'));
        }

        // Verify issuer
        if (!isset($payload['iss']) || $payload['iss'] !== get_bloginfo('url')) {
            return new WP_Error('invalid_issuer', __('Emisor inválido', 'casa-alba-headless-auth'));
        }

        return $payload;
    }

    /**
     * Refresh token
     *
     * @param string $refresh_token Refresh token
     * @return array|WP_Error New tokens or error
     */
    public function refresh_token($refresh_token) {
        $payload = $this->validate_token($refresh_token);

        if (is_wp_error($payload)) {
            return $payload;
        }

        // Verify it's a refresh token
        if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
            return new WP_Error('invalid_token_type', __('No es un refresh token válido', 'casa-alba-headless-auth'));
        }

        $user_id = $payload['sub'];

        // Verify user still exists and is active
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('user_not_found', __('Usuario no encontrado', 'casa-alba-headless-auth'));
        }

        // Generate new tokens
        return array(
            'token' => $this->generate_token($user_id),
            'refresh_token' => $this->generate_refresh_token($user_id)
        );
    }

    /**
     * Get token hash for storage
     */
    public function get_token_hash($token) {
        return hash('sha256', $token);
    }

    /**
     * Extract user ID from token
     */
    public function get_user_id_from_token($token) {
        $payload = $this->validate_token($token);

        if (is_wp_error($payload)) {
            return false;
        }

        return isset($payload['sub']) ? $payload['sub'] : false;
    }
}

