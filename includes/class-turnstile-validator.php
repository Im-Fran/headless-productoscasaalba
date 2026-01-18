<?php
/**
 * Cloudflare Turnstile Validator Class
 *
 * Handles Cloudflare Turnstile validation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Casa_Alba_Turnstile_Validator {

    /**
     * Verify URL for Turnstile
     */
    private $verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Check if Turnstile is enabled
     */
    public function is_enabled() {
        return (bool) get_option('casa_alba_auth_turnstile_enabled', false);
    }

    /**
     * Validate Turnstile token
     *
     * @param string $token Turnstile token from client
     * @return bool|WP_Error True if valid, WP_Error on failure
     */
    public function validate($token) {
        if (!$this->is_enabled()) {
            return true; // Skip validation if disabled
        }

        if (empty($token)) {
            return new WP_Error(
                'turnstile_required',
                __('Se requiere validación de Turnstile', 'casa-alba-headless-auth')
            );
        }

        $secret_key = get_option('casa_alba_auth_turnstile_secret_key');

        if (empty($secret_key)) {
            error_log('Casa Alba Auth: Turnstile secret key not configured');
            return new WP_Error(
                'turnstile_not_configured',
                __('Turnstile no está configurado correctamente', 'casa-alba-headless-auth')
            );
        }

        // Get client IP
        $ip = $this->get_client_ip();

        // Prepare request
        $body = array(
            'secret' => $secret_key,
            'response' => $token,
            'remoteip' => $ip
        );

        // Make request to Cloudflare
        $response = wp_remote_post($this->verify_url, array(
            'body' => $body,
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            error_log('Casa Alba Auth: Turnstile validation failed - ' . $response->get_error_message());
            return new WP_Error(
                'turnstile_request_failed',
                __('Error al validar Turnstile', 'casa-alba-headless-auth')
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('Casa Alba Auth: Turnstile returned non-200 status: ' . $response_code);
            return new WP_Error(
                'turnstile_invalid_response',
                __('Respuesta inválida de Turnstile', 'casa-alba-headless-auth')
            );
        }

        $result = json_decode($response_body, true);

        if (!isset($result['success']) || !$result['success']) {
            $error_codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'unknown';
            error_log('Casa Alba Auth: Turnstile validation failed - ' . $error_codes);

            return new WP_Error(
                'turnstile_validation_failed',
                __('Validación de Turnstile fallida. Por favor, intenta de nuevo.', 'casa-alba-headless-auth'),
                array('error_codes' => $error_codes)
            );
        }

        return true;
    }

    /**
     * Get Turnstile site key for client-side integration
     */
    public function get_site_key() {
        return get_option('casa_alba_auth_turnstile_site_key', '');
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}

