<?php
/**
 * Sessions Management Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$session_manager = new Casa_Alba_Session_Manager();

// Handle session revocation
if (isset($_GET['action']) && $_GET['action'] === 'revoke' && isset($_GET['session_id']) && check_admin_referer('revoke_session_' . $_GET['session_id'])) {
    global $wpdb;
    $table = $wpdb->prefix . 'casa_alba_auth_sessions';
    $wpdb->delete($table, array('id' => intval($_GET['session_id'])));
    echo '<div class="notice notice-success"><p>' . __('Sesión revocada correctamente', 'casa-alba-headless-auth') . '</p></div>';
}

// Get pagination parameters
$per_page = 20;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

// Get sessions
$sessions = $session_manager->get_all_active_sessions($per_page, $offset);

// Get total count for pagination
global $wpdb;
$sessions_table = $wpdb->prefix . 'casa_alba_auth_sessions';
$total_sessions = $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table WHERE expires_at > NOW()");
$total_pages = ceil($total_sessions / $per_page);
?>

<div class="wrap">
    <h1><?php _e('Sesiones Activas', 'casa-alba-headless-auth'); ?></h1>

    <div class="notice notice-info">
        <p>
            <strong><?php echo number_format_i18n($total_sessions); ?></strong>
            <?php _e('sesiones activas', 'casa-alba-headless-auth'); ?>
        </p>
    </div>

    <?php if (empty($sessions)) : ?>
        <p><?php _e('No hay sesiones activas en este momento', 'casa-alba-headless-auth'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Usuario', 'casa-alba-headless-auth'); ?></th>
                    <th><?php _e('Email', 'casa-alba-headless-auth'); ?></th>
                    <th><?php _e('Dispositivo', 'casa-alba-headless-auth'); ?></th>
                    <th><?php _e('Navegador', 'casa-alba-headless-auth'); ?></th>
                    <th><?php _e('Sistema Operativo', 'casa-alba-headless-auth'); ?></th>
                    <th><?php _e('IP', 'casa-alba-headless-auth'); ?></th>
                    <th><?php _e('Última Actividad', 'casa-alba-headless-auth'); ?></th>
                    <th><?php _e('Creada', 'casa-alba-headless-auth'); ?></th>
                    <th><?php _e('Expira', 'casa-alba-headless-auth'); ?></th>
                    <th><?php _e('Acciones', 'casa-alba-headless-auth'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session) : ?>
                    <tr>
                        <td><?php echo esc_html($session->display_name); ?></td>
                        <td><?php echo esc_html($session->user_email); ?></td>
                        <td>
                            <?php
                            $device_icon = $session->device_type === 'mobile' ? '📱' : ($session->device_type === 'tablet' ? '📱' : '🖥️');
                            echo $device_icon . ' ' . esc_html(ucfirst($session->device_type));
                            ?>
                        </td>
                        <td><?php echo esc_html($session->browser); ?></td>
                        <td><?php echo esc_html($session->os); ?></td>
                        <td><?php echo esc_html($session->ip_address); ?></td>
                        <td><?php echo human_time_diff(strtotime($session->last_activity), current_time('timestamp')) . ' ' . __('atrás', 'casa-alba-headless-auth'); ?></td>
                        <td><?php echo human_time_diff(strtotime($session->created_at), current_time('timestamp')) . ' ' . __('atrás', 'casa-alba-headless-auth'); ?></td>
                        <td><?php echo human_time_diff(current_time('timestamp'), strtotime($session->expires_at)); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(
                                add_query_arg(array(
                                    'action' => 'revoke',
                                    'session_id' => $session->id
                                )),
                                'revoke_session_' . $session->id
                            ); ?>" class="button button-small" onclick="return confirm('<?php _e('¿Estás seguro de que quieres revocar esta sesión?', 'casa-alba-headless-auth'); ?>');">
                                <?php _e('Revocar', 'casa-alba-headless-auth'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    .wp-list-table th,
    .wp-list-table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>
