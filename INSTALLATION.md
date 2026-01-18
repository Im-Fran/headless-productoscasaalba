# Guía de Instalación - Headless Productos Casa Alba

Esta guía te ayudará a instalar y configurar el plugin "Headless Productos Casa Alba" paso a paso.

## Requisitos Previos

Antes de instalar el plugin, asegúrate de tener:

✅ **WordPress 5.8 o superior**  
✅ **PHP 7.4 o superior**  
✅ **WooCommerce 6.0 o superior** instalado y activado  
✅ **Aplicación frontend** (React, Vue, etc.) lista para recibir las APIs

## Paso 1: Instalación del Plugin

### Opción A: Instalación Manual

1. Descarga el plugin o copia la carpeta `headless-productoscasaalba`
2. Sube la carpeta a `/wp-content/plugins/` en tu servidor WordPress
3. Ve a **Plugins** en el panel de administración de WordPress
4. Busca "Headless Productos Casa Alba" y haz clic en **Activar**

### Opción B: Instalación via FTP/SFTP

```bash
# Conecta a tu servidor via SFTP y navega a:
cd /path/to/wordpress/wp-content/plugins/

# Sube la carpeta del plugin
# Asegúrate de que los permisos sean correctos
chmod 755 headless-productoscasaalba
```

## Paso 2: Verificar Requisitos

Después de activar el plugin:

1. Ve a **Headless > Configuración**
2. Verifica que aparezca:
   - ✅ WooCommerce: Activado
   - ✅ PHP Version: 7.4+

Si WooCommerce no está activado, instálalo primero:
- Plugins > Añadir nuevo > Buscar "WooCommerce" > Instalar > Activar

## Paso 3: Configuración Básica

### 3.1 Configurar URL del Frontend

**Método 1: En wp-config.php (Recomendado)**

Agrega esta línea a tu archivo `wp-config.php` antes de `/* That's all, stop editing! */`:

```php
define('CASA_ALBA_FRONTEND_URL', 'https://productoscasaalba.cl');
```

**Método 2: Desde el Panel de Administración**

1. Ve a **Headless > Configuración**
2. En "URL del Frontend", ingresa: `https://productoscasaalba.cl`
3. Haz clic en **Guardar Configuración**

### 3.2 Verificar JWT Secret

El plugin genera automáticamente un JWT secret seguro al activarse. Puedes verificarlo en:
- **Headless > Configuración** > Sección "Autenticación JWT"

> ⚠️ **Importante**: No compartas el JWT secret. Si crees que ha sido comprometido, genera uno nuevo desde la configuración.

## Paso 4: Configurar Cloudflare Turnstile (Opcional pero Recomendado)

Para proteger tu sitio contra bots y ataques automatizados:

### 4.1 Obtener Credenciales de Turnstile

1. Ve a [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Selecciona tu sitio o crea uno nuevo
3. Ve a **Turnstile** en el menú lateral
4. Haz clic en **Add Site**
5. Configura:
   - **Site name**: Productos Casa Alba
   - **Domains**: productoscasaalba.cl
   - **Widget Mode**: Managed (recomendado)
6. Copia tu **Site Key** y **Secret Key**

### 4.2 Configurar en WordPress

1. Ve a **Headless > Configuración**
2. En la sección "Cloudflare Turnstile":
   - ✅ Habilitar Cloudflare Turnstile
   - **Site Key**: pega tu site key
   - **Secret Key**: pega tu secret key
3. Haz clic en **Guardar Configuración**

## Paso 5: Configurar Rate Limiting

Para proteger contra ataques de fuerza bruta:

1. Ve a **Headless > Configuración**
2. En la sección "Rate Limiting":
   - ✅ Habilitar Rate Limiting
   - **Máximo intentos**: 5 (recomendado)
   - **Ventana de tiempo**: 900 segundos (15 minutos)
   - **Duración del bloqueo**: 1800 segundos (30 minutos)
3. Haz clic en **Guardar Configuración**

## Paso 6: Configurar Sesiones

1. Ve a **Headless > Configuración**
2. En la sección "Gestión de Sesiones":
   - **Límite de sesiones por usuario**: 5 (recomendado)
   - **Tiempo de expiración del token**: 3600 segundos (1 hora)
   - **Tiempo de expiración del refresh token**: 604800 segundos (7 días)
3. Haz clic en **Guardar Configuración**

## Paso 7: Configurar CORS (Importante para Frontend)

Para que tu frontend pueda comunicarse con las APIs, necesitas configurar CORS en WordPress.

Agrega esto a tu archivo `wp-config.php`:

```php
// CORS Headers para Frontend Headless
define('CASA_ALBA_ALLOW_CORS', true);
define('CASA_ALBA_CORS_ORIGIN', 'https://productoscasaalba.cl');
```

O instala un plugin de CORS como "WP CORS" y configúralo para permitir tu dominio frontend.

## Paso 8: Verificar Rutas del Frontend

Asegúrate de que tu aplicación frontend tenga estas rutas configuradas:

### Ruta de Confirmación de Pedido
```
URL: /pedido-recibido
Params: ?order_id=123&key=wc_order_abc123
```

### Ruta de Cancelación de Pedido
```
URL: /pedido-cancelado
Params: ?order_id=123
```

Ejemplo en React Router:
```javascript
<Route path="/pedido-recibido" element={<OrderReceived />} />
<Route path="/pedido-cancelado" element={<OrderCancelled />} />
```

## Paso 9: Probar la Instalación

### 9.1 Probar Autenticación

Usa Postman o curl para probar el endpoint de registro:

```bash
curl -X POST https://tu-sitio.com/wp-json/casa-alba/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@ejemplo.com",
    "password": "Password123!",
    "first_name": "Usuario",
    "last_name": "Prueba"
  }'
```

Deberías recibir una respuesta con tokens JWT.

### 9.2 Verificar Checkout URLs

1. Ve a WooCommerce y crea un pedido de prueba
2. Completa el checkout
3. Verifica que te redirija a tu frontend en `/pedido-recibido`

## Paso 10: Monitoreo

Después de la instalación, monitorea:

### Sesiones Activas
- Ve a **Headless > Sesiones Activas**
- Verifica que las sesiones se estén creando correctamente

### Analítica
- Ve a **Headless > Analítica**
- Revisa los eventos de autenticación
- Verifica que no haya intentos de acceso sospechosos

## Configuración Avanzada

### Personalizar Tiempos de Expiración

En `wp-config.php`, puedes agregar:

```php
// Tiempo de vida del token de acceso (en segundos)
define('CASA_ALBA_JWT_EXPIRATION', 7200); // 2 horas

// Tiempo de vida del refresh token (en segundos)
define('CASA_ALBA_JWT_REFRESH_EXPIRATION', 1209600); // 14 días
```

### Personalizar Límites de Rate

```php
// Máximo de intentos de login
define('CASA_ALBA_RATE_LIMIT_MAX_ATTEMPTS', 3);

// Ventana de tiempo en segundos
define('CASA_ALBA_RATE_LIMIT_WINDOW', 600); // 10 minutos

// Tiempo de bloqueo en segundos
define('CASA_ALBA_RATE_LIMIT_LOCKOUT', 3600); // 1 hora
```

### Habilitar Logging Detallado

Para debug, agrega en `wp-config.php`:

```php
define('CASA_ALBA_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Los logs aparecerán en `/wp-content/debug.log`

## Solución de Problemas Comunes

### Error: "WooCommerce no encontrado"
**Solución**: Instala y activa WooCommerce antes de usar este plugin.

### Error: "JWT secret no configurado"
**Solución**: Desactiva y vuelve a activar el plugin para generar un nuevo secret.

### Error: "CORS blocked"
**Solución**: Configura CORS según el Paso 7 de esta guía.

### Error: "Turnstile validation failed"
**Solución**: Verifica que tus credenciales de Turnstile sean correctas y que el dominio esté autorizado.

### Las redirecciones no funcionan
**Solución**: 
1. Verifica que `CASA_ALBA_FRONTEND_URL` esté correctamente configurado
2. Verifica que las rutas existan en tu frontend
3. Revisa los logs de WordPress en `/wp-content/debug.log`

## Actualizaciones

Para actualizar el plugin:

1. Desactiva el plugin actual
2. Haz backup de tu configuración (ve a Headless > Configuración y anota tus settings)
3. Reemplaza la carpeta del plugin con la nueva versión
4. Activa el plugin nuevamente
5. Verifica que tu configuración se haya mantenido

## Soporte

Si necesitas ayuda:
- Revisa el archivo [README.md](README.md) para documentación completa
- Revisa el archivo [API.md](API.md) para documentación de APIs
- Contacta al desarrollador: Francisco Solis - https://franciscosolis.cl

## Seguridad

✅ **Recomendaciones**:
- Usa HTTPS en producción
- No compartas tus JWT secrets
- Revisa regularmente las sesiones activas
- Monitorea la analítica de seguridad
- Mantén WordPress y WooCommerce actualizados
- Usa contraseñas fuertes para usuarios administradores
- Habilita Turnstile en producción

---

**¡Instalación completada!** 🎉

Tu sitio ahora está listo para operar con un frontend headless.
