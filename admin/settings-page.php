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

    // Frontend configuration
    update_option('casa_alba_frontend_url', esc_url_raw($_POST['frontend_url']));
    update_option('casa_alba_enable_frontend_redirect', isset($_POST['enable_frontend_redirect']) ? 1 : 0);
    update_option('casa_alba_ignored_asns', sanitize_text_field($_POST['ignored_asns']));

    // GitHub configuration
    update_option('casa_alba_github_token', sanitize_text_field($_POST['github_token']));

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
$frontend_url = get_option('casa_alba_frontend_url', 'https://productoscasaalba.cl');
$enable_frontend_redirect = get_option('casa_alba_enable_frontend_redirect', 0);
$ignored_asns = get_option('casa_alba_ignored_asns', '');
$github_token = get_option('casa_alba_github_token', '');
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

        <h2><?php _e('Configuración del Frontend Headless', 'casa-alba-headless-auth'); ?></h2>
        <p class="description">
            <?php _e('Configuración para integración con el frontend headless. Esta funcionalidad está inspirada en el plugin Headless Mode.', 'casa-alba-headless-auth'); ?>
            <br>
            <em><?php _e('Créditos: Headless Mode plugin (https://wordpress.org/plugins/headless-mode/)', 'casa-alba-headless-auth'); ?></em>
        </p>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="frontend_url"><?php _e('URL del Frontend', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="url" name="frontend_url" id="frontend_url" value="<?php echo esc_attr($frontend_url); ?>" class="regular-text" placeholder="https://productoscasaalba.cl" />
                    <p class="description">
                        <?php _e('URL completa de tu aplicación frontend headless. Esta URL se usará para redirecciones de checkout y páginas de confirmación de pedido.', 'casa-alba-headless-auth'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="enable_frontend_redirect"><?php _e('Habilitar Redirección Automática', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_frontend_redirect" id="enable_frontend_redirect" value="1" <?php checked($enable_frontend_redirect, 1); ?> />
                        <?php _e('Redirigir automáticamente todas las solicitudes del frontend de WordPress al frontend headless', 'casa-alba-headless-auth'); ?>
                    </label>
                    <p class="description">
                        <?php _e('⚠️ Cuando está habilitado, los visitantes no autenticados (o usuarios sin capacidad de editar posts) serán redirigidos automáticamente al frontend headless. Los usuarios con permisos de edición podrán acceder normalmente al backend de WordPress.', 'casa-alba-headless-auth'); ?>
                        <br>
                        <strong><?php _e('Nota:', 'casa-alba-headless-auth'); ?></strong> <?php _e('El panel de administración y las APIs REST siempre permanecerán accesibles.', 'casa-alba-headless-auth'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ignored_asns"><?php _e('ASNs Ignorados (Cloudflare)', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="text" name="ignored_asns" id="ignored_asns" value="<?php echo esc_attr($ignored_asns); ?>" class="regular-text" placeholder="13335, 15169, 16509" />
                    <p class="description">
                        <?php _e('Lista de ASNs (Autonomous System Numbers) de Cloudflare que no serán redirigidos, separados por comas. Útil para permitir el acceso a APIs específicas o servicios de monitoreo.', 'casa-alba-headless-auth'); ?>
                        <br>
                        <strong><?php _e('Ejemplo:', 'casa-alba-headless-auth'); ?></strong> <code>13335, 15169, 16509</code>
                        <br>
                        <strong><?php _e('Nota:', 'casa-alba-headless-auth'); ?></strong> <?php _e('Cloudflare proporciona el ASN en la cabecera CF-Connecting-ASN. Esta opción solo funciona si tu sitio está detrás de Cloudflare.', 'casa-alba-headless-auth'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Configuración de GitHub', 'casa-alba-headless-auth'); ?></h2>
        <p class="description">
            <?php _e('Configuración para las actualizaciones automáticas del plugin desde GitHub.', 'casa-alba-headless-auth'); ?>
        </p>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="github_token"><?php _e('Token de GitHub (opcional)', 'casa-alba-headless-auth'); ?></label>
                </th>
                <td>
                    <input type="password" name="github_token" id="github_token" value="<?php echo esc_attr($github_token); ?>" class="regular-text" autocomplete="off" />
                    <button type="button" class="button" onclick="toggleGitHubToken()">
                        <span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span>
                    </button>
                    <p class="description">
                        <?php _e('Token de acceso personal de GitHub para realizar solicitudes autenticadas a la API.', 'casa-alba-headless-auth'); ?>
                        <br>
                        <strong><?php _e('Beneficios:', 'casa-alba-headless-auth'); ?></strong>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li><?php _e('Aumenta el límite de solicitudes de 60 a 5,000 por hora', 'casa-alba-headless-auth'); ?></li>
                            <li><?php _e('Permite acceder a repositorios privados', 'casa-alba-headless-auth'); ?></li>
                        </ul>
                        <a href="https://github.com/settings/tokens/new?description=Casa%20Alba%20Headless%20Plugin&scopes=repo" target="_blank">
                            <?php _e('Crear un token de GitHub →', 'casa-alba-headless-auth'); ?>
                        </a>
                        <br>
                        <em><?php _e('Solo necesitas el permiso "repo" si el repositorio es privado, o ningún permiso para repositorios públicos.', 'casa-alba-headless-auth'); ?></em>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Guardar Configuración', 'casa-alba-headless-auth'), 'primary', 'casa_alba_auth_save_settings'); ?>
    </form>

    <hr>

    <h2><?php _e('Actualizaciones del Plugin', 'casa-alba-headless-auth'); ?></h2>
    <?php
    $updater = Casa_Alba_GitHub_Updater::get_instance();
    $update_status = $updater->get_update_status();
    ?>
    <div class="casa-alba-update-section">
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Versión Actual', 'casa-alba-headless-auth'); ?></th>
                <td>
                    <strong><?php echo esc_html($update_status['current_version']); ?></strong>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Última Versión Disponible', 'casa-alba-headless-auth'); ?></th>
                <td>
                    <strong id="casa-alba-latest-version"><?php echo esc_html($update_status['latest_version']); ?></strong>
                    <?php if ($update_status['update_available']): ?>
                        <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                        <span style="color: #dba617;"><?php _e('Nueva versión disponible', 'casa-alba-headless-auth'); ?></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                        <span style="color: #00a32a;"><?php _e('Estás al día', 'casa-alba-headless-auth'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($update_status['checked_at']): ?>
            <tr>
                <th scope="row"><?php _e('Última Comprobación', 'casa-alba-headless-auth'); ?></th>
                <td>
                    <span id="casa-alba-checked-at"><?php echo esc_html($update_status['checked_at']); ?></span>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($update_status['release'] && !empty($update_status['release']['published_at'])): ?>
            <tr>
                <th scope="row"><?php _e('Fecha de la Última Versión', 'casa-alba-headless-auth'); ?></th>
                <td>
                    <?php
                    $publish_date = new DateTime($update_status['release']['published_at']);
                    echo esc_html($publish_date->format('d/m/Y H:i'));
                    ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <p>
            <button type="button" class="button" id="casa-alba-check-updates">
                <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                <?php _e('Comprobar Actualizaciones', 'casa-alba-headless-auth'); ?>
            </button>

            <?php if ($update_status['update_available']): ?>
            <?php
            $update_url = wp_nonce_url(
                admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode(plugin_basename(CASA_ALBA_HEADLESS_PLUGIN_DIR . 'headless-productoscasaalba.php'))),
                'upgrade-plugin_' . plugin_basename(CASA_ALBA_HEADLESS_PLUGIN_DIR . 'headless-productoscasaalba.php')
            );
            ?>
            <a href="<?php echo esc_url($update_url); ?>" class="button button-primary" id="casa-alba-update-now">
                <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                <?php _e('Actualizar Ahora', 'casa-alba-headless-auth'); ?>
            </a>
            <?php if ($update_status['release']): ?>
            <a href="<?php echo esc_url($update_status['release']['html_url']); ?>" target="_blank" class="button">
                <span class="dashicons dashicons-external" style="vertical-align: middle;"></span>
                <?php _e('Ver Notas de la Versión', 'casa-alba-headless-auth'); ?>
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </p>

        <div id="casa-alba-update-result" style="display: none; margin-top: 10px;"></div>
    </div>

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

function toggleGitHubToken() {
    const input = document.getElementById('github_token');
    const button = event.currentTarget;
    const icon = button.querySelector('.dashicons');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('dashicons-visibility');
        icon.classList.add('dashicons-hidden');
    } else {
        input.type = 'password';
        icon.classList.remove('dashicons-hidden');
        icon.classList.add('dashicons-visibility');
    }
}
</script>

