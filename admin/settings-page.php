<?php
/**
 * Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Save settings
if (isset($_POST['casa_alba_auth_save_settings']) && check_admin_referer('casa_alba_auth_settings')) {
    update_option('casa_alba_auth_jwt_algorithm', sanitize_text_field($_POST['jwt_algorithm']));
    update_option('casa_alba_auth_jwt_secret', sanitize_text_field($_POST['jwt_secret']));
    update_option('casa_alba_auth_jwt_expiration', intval($_POST['jwt_expiration']));
    update_option('casa_alba_auth_jwt_refresh_expiration', intval($_POST['jwt_refresh_expiration']));
    update_option('casa_alba_auth_turnstile_enabled', isset($_POST['turnstile_enabled']) ? 1 : 0);
    update_option('casa_alba_auth_turnstile_site_key', sanitize_text_field($_POST['turnstile_site_key']));
    update_option('casa_alba_auth_turnstile_secret_key', sanitize_text_field($_POST['turnstile_secret_key']));
    update_option('casa_alba_auth_rate_limit_enabled', isset($_POST['rate_limit_enabled']) ? 1 : 0);
    update_option('casa_alba_auth_rate_limit_max_attempts', intval($_POST['rate_limit_max_attempts']));
    update_option('casa_alba_auth_rate_limit_window', intval($_POST['rate_limit_window']));
    update_option('casa_alba_auth_rate_limit_lockout_duration', intval($_POST['rate_limit_lockout_duration']));
    update_option('casa_alba_auth_session_limit', intval($_POST['session_limit']));

    echo '<div class="notice notice-success"><p>' . __('Configuración guardada correctamente', 'casa-alba-headless-auth') . '</p></div>';
}

// Get current settings
$jwt_algorithm = get_option('casa_alba_auth_jwt_algorithm', 'HS256');
$jwt_secret = get_option('casa_alba_auth_jwt_secret', '');
$jwt_expiration = get_option('casa_alba_auth_jwt_expiration', 3600);
$jwt_refresh_expiration = get_option('casa_alba_auth_jwt_refresh_expiration', 604800);
$turnstile_enabled = get_option('casa_alba_auth_turnstile_enabled', 0);
$turnstile_site_key = get_option('casa_alba_auth_turnstile_site_key', '');
$turnstile_secret_key = get_option('casa_alba_auth_turnstile_secret_key', '');
$rate_limit_enabled = get_option('casa_alba_auth_rate_limit_enabled', 1);
$rate_limit_max_attempts = get_option('casa_alba_auth_rate_limit_max_attempts', 5);
$rate_limit_window = get_option('casa_alba_auth_rate_limit_window', 900);
$rate_limit_lockout_duration = get_option('casa_alba_auth_rate_limit_lockout_duration', 1800);
$session_limit = get_option('casa_alba_auth_session_limit', 5);
?>

<div class="wrap">
    <h1><?php _e('Configuración de Headless Authentication', 'casa-alba-headless-auth'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('casa_alba_auth_settings'); ?>

        <h2><?php _e('Configuración de JWT', 'casa-alba-headless-auth'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="jwt_algorithm"><?php _e('Algoritmo JWT', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <select name="jwt_algorithm" id="jwt_algorithm">
                        <option value="HS256" <?php selected($jwt_algorithm, 'HS256'); ?>>HS256 (SHA-256)</option>
                        <option value="HS384" <?php selected($jwt_algorithm, 'HS384'); ?>>HS384 (SHA-384)</option>
                        <option value="HS512" <?php selected($jwt_algorithm, 'HS512'); ?>>HS512 (SHA-512)</option>
                    </select>
                    <p class="description"><?php _e('Algoritmo usado para firmar los tokens JWT', 'casa-alba-headless-auth'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="jwt_secret"><?php _e('Clave Secreta JWT', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="text" name="jwt_secret" id="jwt_secret" value="<?php echo esc_attr($jwt_secret); ?>" class="regular-text" />
                    <button type="button" class="button" onclick="document.getElementById('jwt_secret').value = generateSecret();">
                        <?php _e('Generar Nueva', 'casa-alba-headless-auth'); ?>
                    </button>
                    <p class="description"><?php _e('Clave secreta para firmar tokens. ¡IMPORTANTE: Cambiar esta clave invalidará todos los tokens existentes!', 'casa-alba-headless-auth'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="jwt_expiration"><?php _e('Expiración del Token (segundos)', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="number" name="jwt_expiration" id="jwt_expiration" value="<?php echo esc_attr($jwt_expiration); ?>" class="small-text" />
                    <p class="description">
                        <?php _e('Duración del token de acceso. Recomendado: 3600 (1 hora)', 'casa-alba-headless-auth'); ?>
                        <br><?php echo sprintf(__('Valor actual: %s', 'casa-alba-headless-auth'), human_time_diff(0, $jwt_expiration)); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="jwt_refresh_expiration"><?php _e('Expiración del Refresh Token (segundos)', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="number" name="jwt_refresh_expiration" id="jwt_refresh_expiration" value="<?php echo esc_attr($jwt_refresh_expiration); ?>" class="small-text" />
                    <p class="description">
                        <?php _e('Duración del refresh token. Recomendado: 604800 (7 días)', 'casa-alba-headless-auth'); ?>
                        <br><?php echo sprintf(__('Valor actual: %s', 'casa-alba-headless-auth'), human_time_diff(0, $jwt_refresh_expiration)); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Configuración de Cloudflare Turnstile', 'casa-alba-headless-auth'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="turnstile_enabled"><?php _e('Habilitar Turnstile', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="turnstile_enabled" id="turnstile_enabled" value="1" <?php checked($turnstile_enabled, 1); ?> />
                        <?php _e('Activar validación Cloudflare Turnstile en login', 'casa-alba-headless-auth'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="turnstile_site_key"><?php _e('Site Key', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="text" name="turnstile_site_key" id="turnstile_site_key" value="<?php echo esc_attr($turnstile_site_key); ?>" class="regular-text" />
                    <p class="description"><?php _e('Clave pública de Turnstile (se usa en el frontend)', 'casa-alba-headless-auth'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="turnstile_secret_key"><?php _e('Secret Key', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="text" name="turnstile_secret_key" id="turnstile_secret_key" value="<?php echo esc_attr($turnstile_secret_key); ?>" class="regular-text" />
                    <p class="description"><?php _e('Clave secreta de Turnstile (se usa en el backend)', 'casa-alba-headless-auth'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Configuración de Rate Limiting', 'casa-alba-headless-auth'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="rate_limit_enabled"><?php _e('Habilitar Rate Limiting', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="rate_limit_enabled" id="rate_limit_enabled" value="1" <?php checked($rate_limit_enabled, 1); ?> />
                        <?php _e('Activar limitación de intentos de login', 'casa-alba-headless-auth'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rate_limit_max_attempts"><?php _e('Máximo de Intentos', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="number" name="rate_limit_max_attempts" id="rate_limit_max_attempts" value="<?php echo esc_attr($rate_limit_max_attempts); ?>" class="small-text" />
                    <p class="description"><?php _e('Número máximo de intentos fallidos antes de bloquear', 'casa-alba-headless-auth'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rate_limit_window"><?php _e('Ventana de Tiempo (segundos)', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="number" name="rate_limit_window" id="rate_limit_window" value="<?php echo esc_attr($rate_limit_window); ?>" class="small-text" />
                    <p class="description">
                        <?php _e('Tiempo en el que se cuentan los intentos fallidos', 'casa-alba-headless-auth'); ?>
                        <br><?php echo sprintf(__('Valor actual: %s', 'casa-alba-headless-auth'), human_time_diff(0, $rate_limit_window)); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rate_limit_lockout_duration"><?php _e('Duración del Bloqueo (segundos)', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="number" name="rate_limit_lockout_duration" id="rate_limit_lockout_duration" value="<?php echo esc_attr($rate_limit_lockout_duration); ?>" class="small-text" />
                    <p class="description">
                        <?php _e('Tiempo que el usuario estará bloqueado tras exceder los intentos', 'casa-alba-headless-auth'); ?>
                        <br><?php echo sprintf(__('Valor actual: %s', 'casa-alba-headless-auth'), human_time_diff(0, $rate_limit_lockout_duration)); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Configuración de Sesiones', 'casa-alba-headless-auth'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="session_limit"><?php _e('Límite de Sesiones por Usuario', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="number" name="session_limit" id="session_limit" value="<?php echo esc_attr($session_limit); ?>" class="small-text" />
                    <p class="description"><?php _e('Número máximo de sesiones activas por usuario (0 = ilimitado)', 'casa-alba-headless-auth'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Guardar Configuración', 'casa-alba-headless-auth'), 'primary', 'casa_alba_auth_save_settings'); ?>
    </form>

    <hr>

    <h2><?php _e('Información de la API', 'casa-alba-headless-auth'); ?></h2>
    <table class="widefat">
        <thead>
            <tr>
                <th><?php _e('Endpoint', 'casa-alba-headless-auth'); ?></th>
                <th><?php _e('Método', 'casa-alba-headless-auth'); ?></th>
                <th><?php _e('Descripción', 'casa-alba-headless-auth'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>/wp-json/casa-alba/v1/auth/login</code></td>
                <td><code>POST</code></td>
                <td><?php _e('Iniciar sesión', 'casa-alba-headless-auth'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/casa-alba/v1/auth/logout</code></td>
                <td><code>POST</code></td>
                <td><?php _e('Cerrar sesión', 'casa-alba-headless-auth'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/casa-alba/v1/auth/refresh</code></td>
                <td><code>POST</code></td>
                <td><?php _e('Refrescar token', 'casa-alba-headless-auth'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/casa-alba/v1/auth/validate</code></td>
                <td><code>POST</code></td>
                <td><?php _e('Validar token', 'casa-alba-headless-auth'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/casa-alba/v1/auth/me</code></td>
                <td><code>GET</code></td>
                <td><?php _e('Obtener usuario actual', 'casa-alba-headless-auth'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/casa-alba/v1/auth/sessions</code></td>
                <td><code>GET</code></td>
                <td><?php _e('Obtener sesiones del usuario', 'casa-alba-headless-auth'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/casa-alba/v1/auth/turnstile-key</code></td>
                <td><code>GET</code></td>
                <td><?php _e('Obtener configuración de Turnstile', 'casa-alba-headless-auth'); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<script>
function generateSecret() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    let secret = '';
    for (let i = 0; i < 64; i++) {
        secret += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return secret;
}
</script>

