<?php
/**
 * Analytics Class
 *
 * Handles authentication analytics and monitoring
 */

if (!defined('ABSPATH')) {
    exit;
}

class Casa_Alba_Auth_Analytics {

    /**
     * Log authentication event
     *
     * @param string $event_type Type of event (login, logout, refresh, failed_login, etc.)
     * @param array $data Event data
     */
    public function log_event($event_type, $data = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_analytics';

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        $wpdb->insert($table, array(
            'event_type' => $event_type,
            'user_id' => isset($data['user_id']) ? $data['user_id'] : null,
            'email' => isset($data['email']) ? $data['email'] : null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $user_agent,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'success' => isset($data['success']) ? (int) $data['success'] : 1,
            'error_message' => isset($data['error_message']) ? $data['error_message'] : null,
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Get authentication statistics
     */
    public function get_statistics($days = 7) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_analytics';

        $stats = array();

        // Total logins
        $stats['total_logins'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE event_type = 'login' AND success = 1
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Failed logins
        $stats['failed_logins'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE event_type = 'login' AND success = 0
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Unique users
        $stats['unique_users'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table
             WHERE event_type = 'login' AND success = 1
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Token refreshes
        $stats['token_refreshes'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE event_type = 'refresh' AND success = 1
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Logouts
        $stats['logouts'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE event_type = 'logout'
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Success rate
        $total_attempts = $stats['total_logins'] + $stats['failed_logins'];
        $stats['success_rate'] = $total_attempts > 0
            ? round(($stats['total_logins'] / $total_attempts) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Get login activity by day
     */
    public function get_login_activity($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_analytics';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                COUNT(CASE WHEN success = 1 THEN 1 END) as successful,
                COUNT(CASE WHEN success = 0 THEN 1 END) as failed
             FROM $table
             WHERE event_type = 'login'
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            $days
        ), ARRAY_A);
    }

    /**
     * Get top users by login count
     */
    public function get_top_users($limit = 10, $days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_analytics';
        $users_table = $wpdb->prefix . 'users';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                a.user_id,
                u.user_email,
                u.display_name,
                COUNT(*) as login_count,
                MAX(a.created_at) as last_login
             FROM $table a
             LEFT JOIN $users_table u ON a.user_id = u.ID
             WHERE a.event_type = 'login' AND a.success = 1
             AND a.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY a.user_id
             ORDER BY login_count DESC
             LIMIT %d",
            $days,
            $limit
        ), ARRAY_A);
    }

    /**
     * Get failed login attempts
     */
    public function get_failed_attempts($limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_analytics';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT email, ip_address, error_message, created_at
             FROM $table
             WHERE event_type = 'login' AND success = 0
             ORDER BY created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Get currently connected users
     */
    public function get_connected_users() {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'casa_alba_auth_sessions';

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id)
             FROM $sessions_table
             WHERE expires_at > NOW()"
        );
    }

    /**
     * Get active tokens count
     */
    public function get_active_tokens_count() {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'casa_alba_auth_sessions';

        return (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM $sessions_table
             WHERE expires_at > NOW()"
        );
    }

    /**
     * Clean old analytics data
     */
    public function cleanup($days = 90) {
        global $wpdb;
        $table = $wpdb->prefix . 'casa_alba_auth_analytics';

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
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

