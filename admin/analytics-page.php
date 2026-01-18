<?php
/**
 * Analytics Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$analytics = new Casa_Alba_Auth_Analytics();
$session_manager = new Casa_Alba_Session_Manager();

// Get time range from query parameter
$days = isset($_GET['days']) ? intval($_GET['days']) : 7;
$days = in_array($days, array(7, 30, 90)) ? $days : 7;

// Get statistics
$stats = $analytics->get_statistics($days);
$connected_users = $analytics->get_connected_users();
$active_tokens = $analytics->get_active_tokens_count();
$login_activity = $analytics->get_login_activity($days);
$top_users = $analytics->get_top_users(10, $days);
$failed_attempts = $analytics->get_failed_attempts(20);
?>

<div class="wrap">
    <h1><?php _e('Analítica de Autenticación', 'casa-alba-headless-auth'); ?></h1>

    <div style="margin: 20px 0;">
        <a href="<?php echo add_query_arg('days', 7); ?>" class="button <?php echo $days === 7 ? 'button-primary' : ''; ?>">
            <?php _e('Últimos 7 días', 'casa-alba-headless-auth'); ?>
        </a>
        <a href="<?php echo add_query_arg('days', 30); ?>" class="button <?php echo $days === 30 ? 'button-primary' : ''; ?>">
            <?php _e('Últimos 30 días', 'casa-alba-headless-auth'); ?>
        </a>
        <a href="<?php echo add_query_arg('days', 90); ?>" class="button <?php echo $days === 90 ? 'button-primary' : ''; ?>">
            <?php _e('Últimos 90 días', 'casa-alba-headless-auth'); ?>
        </a>
    </div>

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="casa-alba-stat-card">
            <h3><?php _e('Usuarios Conectados', 'casa-alba-headless-auth'); ?></h3>
            <p class="stat-number"><?php echo number_format_i18n($connected_users); ?></p>
            <p class="stat-description"><?php _e('Usuarios con sesiones activas ahora', 'casa-alba-headless-auth'); ?></p>
        </div>

        <div class="casa-alba-stat-card">
            <h3><?php _e('Tokens Activos', 'casa-alba-headless-auth'); ?></h3>
            <p class="stat-number"><?php echo number_format_i18n($active_tokens); ?></p>
            <p class="stat-description"><?php _e('Total de sesiones activas', 'casa-alba-headless-auth'); ?></p>
        </div>

        <div class="casa-alba-stat-card">
            <h3><?php _e('Inicios de Sesión', 'casa-alba-headless-auth'); ?></h3>
            <p class="stat-number"><?php echo number_format_i18n($stats['total_logins']); ?></p>
            <p class="stat-description"><?php echo sprintf(__('En los últimos %d días', 'casa-alba-headless-auth'), $days); ?></p>
        </div>

        <div class="casa-alba-stat-card">
            <h3><?php _e('Intentos Fallidos', 'casa-alba-headless-auth'); ?></h3>
            <p class="stat-number"><?php echo number_format_i18n($stats['failed_logins']); ?></p>
            <p class="stat-description"><?php echo sprintf(__('En los últimos %d días', 'casa-alba-headless-auth'), $days); ?></p>
        </div>

        <div class="casa-alba-stat-card">
            <h3><?php _e('Usuarios Únicos', 'casa-alba-headless-auth'); ?></h3>
            <p class="stat-number"><?php echo number_format_i18n($stats['unique_users']); ?></p>
            <p class="stat-description"><?php echo sprintf(__('En los últimos %d días', 'casa-alba-headless-auth'), $days); ?></p>
        </div>

        <div class="casa-alba-stat-card">
            <h3><?php _e('Tasa de Éxito', 'casa-alba-headless-auth'); ?></h3>
            <p class="stat-number"><?php echo number_format_i18n($stats['success_rate'], 1); ?>%</p>
            <p class="stat-description"><?php _e('Logins exitosos vs fallidos', 'casa-alba-headless-auth'); ?></p>
        </div>
    </div>

    <!-- Login Activity Chart -->
    <div class="casa-alba-chart-container">
        <h2><?php _e('Actividad de Inicio de Sesión', 'casa-alba-headless-auth'); ?></h2>
        <?php if (!empty($login_activity)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Fecha', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('Exitosos', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('Fallidos', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('Total', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('Gráfico', 'casa-alba-headless-auth'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($login_activity as $activity) :
                        $total = $activity['successful'] + $activity['failed'];
                        $success_percent = $total > 0 ? ($activity['successful'] / $total) * 100 : 0;
                    ?>
                        <tr>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($activity['date'])); ?></td>
                            <td><?php echo number_format_i18n($activity['successful']); ?></td>
                            <td><?php echo number_format_i18n($activity['failed']); ?></td>
                            <td><?php echo number_format_i18n($total); ?></td>
                            <td>
                                <div style="display: flex; height: 20px; background: #ddd; border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?php echo $success_percent; ?>%; background: #46b450;"></div>
                                    <div style="width: <?php echo 100 - $success_percent; ?>%; background: #dc3232;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php _e('No hay actividad para mostrar', 'casa-alba-headless-auth'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Top Users -->
    <div class="casa-alba-chart-container" style="margin-top: 30px;">
        <h2><?php _e('Usuarios Más Activos', 'casa-alba-headless-auth'); ?></h2>
        <?php if (!empty($top_users)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Usuario', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('Email', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('Inicios de Sesión', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('Último Login', 'casa-alba-headless-auth'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_users as $user) : ?>
                        <tr>
                            <td><?php echo esc_html($user['display_name']); ?></td>
                            <td><?php echo esc_html($user['user_email']); ?></td>
                            <td><?php echo number_format_i18n($user['login_count']); ?></td>
                            <td><?php echo human_time_diff(strtotime($user['last_login']), current_time('timestamp')) . ' ' . __('atrás', 'casa-alba-headless-auth'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php _e('No hay datos de usuarios', 'casa-alba-headless-auth'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Failed Login Attempts -->
    <div class="casa-alba-chart-container" style="margin-top: 30px;">
        <h2><?php _e('Intentos de Login Fallidos Recientes', 'casa-alba-headless-auth'); ?></h2>
        <?php if (!empty($failed_attempts)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Email', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('IP', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('Error', 'casa-alba-headless-auth'); ?></th>
                        <th><?php _e('Fecha', 'casa-alba-headless-auth'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($failed_attempts as $attempt) : ?>
                        <tr>
                            <td><?php echo esc_html($attempt['email']); ?></td>
                            <td><?php echo esc_html($attempt['ip_address']); ?></td>
                            <td><?php echo esc_html($attempt['error_message']); ?></td>
                            <td><?php echo human_time_diff(strtotime($attempt['created_at']), current_time('timestamp')) . ' ' . __('atrás', 'casa-alba-headless-auth'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php _e('No hay intentos fallidos recientes', 'casa-alba-headless-auth'); ?></p>
        <?php endif; ?>
    </div>
</div>

<style>
.casa-alba-stat-card {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.casa-alba-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    font-weight: 600;
}

.casa-alba-stat-card .stat-number {
    font-size: 32px;
    font-weight: bold;
    margin: 10px 0;
    color: #2271b1;
}

.casa-alba-stat-card .stat-description {
    font-size: 12px;
    color: #999;
    margin: 0;
}

.casa-alba-chart-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.casa-alba-chart-container h2 {
    margin-top: 0;
}
</style>

