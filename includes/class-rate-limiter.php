<?php
/**
 * Rate Limiter Class
 *
 * Handles rate limiting for authentication attempts
 */

if (!defined('ABSPATH')) {
    exit;
}

class Casa_Alba_Rate_Limiter {

    /**
     * Check if rate limiting is enabled
     */
    private function is_enabled() {
        return (bool) get_option('casa_alba_auth_rate_limit_enabled', true);
    }

    /**
     * Get identifier for rate limiting (IP + email)
     */
    private function get_identifier($email) {
        $ip = $this->get_client_ip();
        return hash('sha256', $ip . '|' . $email);
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle multiple IPs (take first one)
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

    /**
     * Check if request is rate limited
     *
     * @param string $email User email
     * @return bool|WP_Error True if allowed, WP_Error if rate limited
     */
    public function check($email) {
        if (!$this->is_enabled()) {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_rate_limits';
        $identifier = $this->get_identifier($email);

        // Check if currently locked
        $locked = $wpdb->get_var($wpdb->prepare(
            "SELECT locked_until FROM $table WHERE identifier = %s AND locked_until > NOW()",
            $identifier
        ));

        if ($locked) {
            $remaining = strtotime($locked) - time();
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Demasiados intentos fallidos. Intenta de nuevo en %d minutos.', 'casa-alba-headless-auth'),
                    ceil($remaining / 60)
                ),
                array('retry_after' => $remaining)
            );
        }

        return true;
    }

    /**
     * Record failed attempt
     *
     * @param string $email User email
     */
    public function record_attempt($email, $success = false) {
        if (!$this->is_enabled()) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_rate_limits';
        $identifier = $this->get_identifier($email);

        // If success, clear attempts
        if ($success) {
            $wpdb->delete($table, array('identifier' => $identifier));
            return;
        }

        $max_attempts = (int) get_option('casa_alba_auth_rate_limit_max_attempts', 5);
        $window = (int) get_option('casa_alba_auth_rate_limit_window', 900); // 15 minutes
        $lockout_duration = (int) get_option('casa_alba_auth_rate_limit_lockout_duration', 1800); // 30 minutes

        // Get current record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE identifier = %s",
            $identifier
        ));

        if ($record) {
            // Check if window has expired
            $created = strtotime($record->created_at);
            if (time() - $created > $window) {
                // Reset counter
                $wpdb->update(
                    $table,
                    array(
                        'attempts' => 1,
                        'created_at' => current_time('mysql'),
                        'locked_until' => null
                    ),
                    array('identifier' => $identifier)
                );
            } else {
                // Increment attempts
                $new_attempts = $record->attempts + 1;
                $update_data = array('attempts' => $new_attempts);

                // Lock if max attempts reached
                if ($new_attempts >= $max_attempts) {
                    $locked_until = date('Y-m-d H:i:s', time() + $lockout_duration);
                    $update_data['locked_until'] = $locked_until;
                }

                $wpdb->update(
                    $table,
                    $update_data,
                    array('identifier' => $identifier)
                );
            }
        } else {
            // Create new record
            $wpdb->insert($table, array(
                'identifier' => $identifier,
                'attempts' => 1,
                'created_at' => current_time('mysql')
            ));
        }
    }

    /**
     * Clear rate limit for identifier
     */
    public function clear($email) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_rate_limits';
        $identifier = $this->get_identifier($email);

        $wpdb->delete($table, array('identifier' => $identifier));
    }

    /**
     * Clean expired records
     */
    public function cleanup() {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_rate_limits';

        // Delete records older than 24 hours
        $wpdb->query(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
}

