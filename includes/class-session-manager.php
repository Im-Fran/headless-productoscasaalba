<?php
/**
 * Session Manager Class
 *
 * Handles user session management and device tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Casa_Alba_Session_Manager {

    /**
     * Create new session
     *
     * @param int $user_id User ID
     * @param string $token JWT token
     * @param string $refresh_token Refresh token
     * @return int|false Session ID or false on failure
     */
    public function create_session($user_id, $token, $refresh_token) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';

        $jwt_manager = new Casa_Alba_JWT_Manager();
        $device_info = $this->get_device_info();

        $expiration = time() + (int) get_option('casa_alba_auth_jwt_refresh_expiration', 604800);

        // Check session limit
        $session_limit = (int) get_option('casa_alba_auth_session_limit', 5);
        $current_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND expires_at > NOW()",
            $user_id
        ));

        // If limit reached, delete oldest session
        if ($current_sessions >= $session_limit) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE user_id = %d ORDER BY created_at ASC LIMIT 1",
                $user_id
            ));
        }

        $result = $wpdb->insert($table, array(
            'user_id' => $user_id,
            'token_hash' => $jwt_manager->get_token_hash($token),
            'refresh_token_hash' => $jwt_manager->get_token_hash($refresh_token),
            'device_type' => $device_info['device_type'],
            'browser' => $device_info['browser'],
            'os' => $device_info['os'],
            'ip_address' => $device_info['ip_address'],
            'user_agent' => $device_info['user_agent'],
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', $expiration)
        ));

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update session activity
     */
    public function update_activity($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';
        $jwt_manager = new Casa_Alba_JWT_Manager();

        $token_hash = $jwt_manager->get_token_hash($token);

        $wpdb->update(
            $table,
            array('last_activity' => current_time('mysql')),
            array('token_hash' => $token_hash)
        );
    }

    /**
     * Verify session exists and is valid
     */
    public function verify_session($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';
        $jwt_manager = new Casa_Alba_JWT_Manager();

        $token_hash = $jwt_manager->get_token_hash($token);

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token_hash = %s AND expires_at > NOW()",
            $token_hash
        ));

        return $session !== null;
    }

    /**
     * Revoke session (logout)
     */
    public function revoke_session($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';
        $jwt_manager = new Casa_Alba_JWT_Manager();

        $token_hash = $jwt_manager->get_token_hash($token);

        return $wpdb->delete($table, array('token_hash' => $token_hash));
    }

    /**
     * Revoke session by refresh token
     */
    public function revoke_session_by_refresh_token($refresh_token) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';
        $jwt_manager = new Casa_Alba_JWT_Manager();

        $refresh_token_hash = $jwt_manager->get_token_hash($refresh_token);

        return $wpdb->delete($table, array('refresh_token_hash' => $refresh_token_hash));
    }

    /**
     * Revoke all user sessions
     */
    public function revoke_all_sessions($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';

        return $wpdb->delete($table, array('user_id' => $user_id));
    }

    /**
     * Get user sessions
     */
    public function get_user_sessions($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, device_type, browser, os, ip_address, last_activity, created_at, expires_at
             FROM $table
             WHERE user_id = %d AND expires_at > NOW()
             ORDER BY last_activity DESC",
            $user_id
        ));
    }

    /**
     * Get all active sessions (for admin)
     */
    public function get_all_active_sessions($limit = 100, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';
        $users_table = $wpdb->prefix . 'users';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.user_email, u.display_name
             FROM $table s
             LEFT JOIN $users_table u ON s.user_id = u.ID
             WHERE s.expires_at > NOW()
             ORDER BY s.last_activity DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    /**
     * Get active sessions count
     */
    public function get_active_sessions_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM $table WHERE expires_at > NOW()"
        );
    }

    /**
     * Clean expired sessions
     */
    public function cleanup_expired_sessions() {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_sessions';

        return $wpdb->query(
            "DELETE FROM $table WHERE expires_at <= NOW()"
        );
    }

    /**
     * Get device information from user agent
     */
    private function get_device_info() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        return array(
            'device_type' => $this->detect_device_type($user_agent),
            'browser' => $this->detect_browser($user_agent),
            'os' => $this->detect_os($user_agent),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $user_agent
        );
    }

    /**
     * Detect device type
     */
    private function detect_device_type($user_agent) {
        if (preg_match('/mobile|android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i', $user_agent)) {
            if (preg_match('/ipad|tablet|kindle/i', $user_agent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * Detect browser
     */
    private function detect_browser($user_agent) {
        $browsers = array(
            'Edg' => 'Edge',
            'Chrome' => 'Chrome',
            'Safari' => 'Safari',
            'Firefox' => 'Firefox',
            'Opera' => 'Opera',
            'MSIE' => 'Internet Explorer',
            'Trident' => 'Internet Explorer'
        );

        foreach ($browsers as $key => $browser) {
            if (stripos($user_agent, $key) !== false) {
                return $browser;
            }
        }

        return 'Unknown';
    }

    /**
     * Detect operating system
     */
    private function detect_os($user_agent) {
        $os_array = array(
            'Windows 10' => 'Windows 10',
            'Windows NT 6.3' => 'Windows 8.1',
            'Windows NT 6.2' => 'Windows 8',
            'Windows NT 6.1' => 'Windows 7',
            'Windows NT 6.0' => 'Windows Vista',
            'Windows NT 5.1' => 'Windows XP',
            'Mac OS X' => 'macOS',
            'Android' => 'Android',
            'iPhone' => 'iOS',
            'iPad' => 'iOS',
            'Linux' => 'Linux'
        );

        foreach ($os_array as $regex => $value) {
            if (stripos($user_agent, $regex) !== false) {
                return $value;
            }
        }

        return 'Unknown';
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

