# Headless Productos Casa Alba

Plugin completo para integración con frontend headless de Productos Casa Alba.

## Descripción

Este plugin consolida todas las funcionalidades necesarias para operar un frontend headless (React, Vue, etc.) con WordPress y WooCommerce:

### Características Principales

#### 🔐 Autenticación JWT
- Sistema completo de autenticación basado en JWT (JSON Web Tokens)
- Soporte para tokens de acceso y refresh tokens
- Gestión de múltiples sesiones por usuario
- Rate limiting para prevenir ataques de fuerza bruta
- Integración con Cloudflare Turnstile para validación CAPTCHA

#### 🛒 Checkout Headless
- Modificación automática de URLs de retorno del checkout
- Redirección a frontend en lugar del CMS
- Soporte para múltiples métodos de pago (PayPal, Mercado Pago, etc.)
- Integración con WooCommerce Store API

#### 👤 APIs Personalizadas
- API de clientes (Customer API)
- API de pedidos (Orders API)
- Endpoints RESTful completamente documentados

#### 📊 Analítica y Monitoreo
- Seguimiento de eventos de autenticación
- Registro de sesiones activas
- Panel de administración con estadísticas
- Alertas de seguridad

## Instalación

1. Sube la carpeta `headless-productoscasaalba` a `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' de WordPress
3. Configura las opciones desde **Headless > Configuración**

### Requisitos

- WordPress 5.8 o superior
- PHP 7.4 o superior
- WooCommerce 6.0 o superior

## Configuración

### 1. URL del Frontend

Define la URL de tu aplicación frontend en `wp-config.php`:

```php
define('CASA_ALBA_FRONTEND_URL', 'https://productoscasaalba.cl');
```

O configúrala desde el panel de administración en **Headless > Configuración**.

### 2. Cloudflare Turnstile (Opcional)

Para habilitar la protección CAPTCHA:

1. Obtén tus credenciales de Cloudflare Turnstile
2. Ve a **Headless > Configuración**
3. Habilita Turnstile e ingresa tu Site Key y Secret Key

### 3. JWT Secret

El plugin genera automáticamente un secret key seguro durante la activación. Puedes personalizarlo desde **Headless > Configuración**.

### 4. Redirección Automática al Frontend (Opcional)

El plugin incluye una funcionalidad de redirección automática inspirada en el [Headless Mode plugin](https://wordpress.org/plugins/headless-mode/), que permite redirigir todas las solicitudes del frontend de WordPress a tu aplicación headless.

#### Características de la Redirección

- **Redirección Inteligente**: Solo afecta a visitantes no autenticados o usuarios sin permisos de edición
- **Protección del Backend**: El panel de administración y las APIs REST permanecen accesibles
- **Preservación de Rutas**: Las rutas y parámetros de consulta se preservan en la redirección
- **Filtros Personalizables**: Permite personalizar el comportamiento mediante hooks de WordPress

#### Cómo Habilitar

1. Ve a **Headless > Configuración**
2. En la sección "Configuración del Frontend Headless":
   - Ingresa la URL de tu frontend (ej: `https://productoscasaalba.cl`)
   - Marca la casilla "Habilitar Redirección Automática"
3. Guarda los cambios

#### Comportamiento

Cuando está habilitada:
- ✅ Los visitantes que accedan a `https://tu-wordpress.com/productos` serán redirigidos a `https://productoscasaalba.cl/productos`
- ✅ Las APIs REST (`/wp-json/*`) siguen funcionando normalmente
- ✅ El panel de administración (`/wp-admin`) permanece accesible
- ✅ Los usuarios con permisos de edición pueden acceder al frontend de WordPress
- ✅ Los procesos cron y OAuth no se ven afectados

#### Personalización Avanzada

Puedes personalizar el comportamiento usando filtros:

```php
// Deshabilitar redirección para usuarios específicos
add_filter('casa_alba_headless_disable_redirect', function($disable) {
    // Permitir acceso a usuarios con rol específico
    if (current_user_can('manage_woocommerce')) {
        return true;
    }
    return $disable;
});

// Modificar la URL de redirección antes de redirigir
add_filter('casa_alba_headless_will_redirect', function($should_redirect, $new_url) {
    // No redirigir ciertas rutas
    if (strpos($new_url, '/especial') !== false) {
        return false;
    }
    return $should_redirect;
}, 10, 2);
```

#### Créditos

Esta funcionalidad está inspirada en el excelente trabajo del [Headless Mode plugin](https://wordpress.org/plugins/headless-mode/). Agradecemos a sus autores por la inspiración y el concepto.

## APIs Disponibles

### Autenticación

#### POST `/wp-json/casa-alba/v1/auth/register`
Registra un nuevo usuario.

**Body:**
```json
{
  "email": "usuario@ejemplo.com",
  "password": "contraseña123",
  "first_name": "Juan",
  "last_name": "Pérez",
  "turnstile_token": "token_cloudflare"
}
```

#### POST `/wp-json/casa-alba/v1/auth/login`
Inicia sesión y obtiene tokens JWT.

**Body:**
```json
{
  "email": "usuario@ejemplo.com",
  "password": "contraseña123",
  "turnstile_token": "token_cloudflare"
}
```

**Response:**
```json
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
  "user": {
    "id": 1,
    "email": "usuario@ejemplo.com",
    "first_name": "Juan",
    "last_name": "Pérez"
  }
}
```

#### POST `/wp-json/casa-alba/v1/auth/refresh`
Refresca el token de acceso.

**Headers:**
```
Authorization: Bearer <refresh_token>
```

#### POST `/wp-json/casa-alba/v1/auth/logout`
Cierra la sesión actual.

**Headers:**
```
Authorization: Bearer <token>
```

#### GET `/wp-json/casa-alba/v1/auth/validate`
Valida el token actual.

**Headers:**
```
Authorization: Bearer <token>
```

#### GET `/wp-json/casa-alba/v1/auth/sessions`
Obtiene las sesiones activas del usuario.

**Headers:**
```
Authorization: Bearer <token>
```

#### DELETE `/wp-json/casa-alba/v1/auth/sessions/{session_id}`
Cierra una sesión específica.

**Headers:**
```
Authorization: Bearer <token>
```

### Clientes

#### GET `/wp-json/casa-alba/v1/customer`
Obtiene información del cliente actual.

**Headers:**
```
Authorization: Bearer <token>
```

#### PUT `/wp-json/casa-alba/v1/customer`
Actualiza información del cliente.

**Headers:**
```
Authorization: Bearer <token>
```

**Body:**
```json
{
  "first_name": "Juan",
  "last_name": "Pérez",
  "billing": {
    "address_1": "Calle Principal 123",
    "city": "Santiago",
    "postcode": "8320000"
  }
}
```

### Pedidos

#### GET `/wp-json/casa-alba/v1/orders`
Lista los pedidos del cliente.

**Headers:**
```
Authorization: Bearer <token>
```

**Query Params:**
- `page`: Número de página (default: 1)
- `per_page`: Elementos por página (default: 10)
- `status`: Filtrar por estado

#### GET `/wp-json/casa-alba/v1/orders/{order_id}`
Obtiene detalles de un pedido específico.

**Headers:**
```
Authorization: Bearer <token>
```

## Rutas del Frontend

Tu aplicación frontend debe implementar las siguientes rutas:

- `/pedido-recibido` - Página de confirmación de pedido
- `/pedido-cancelado` - Página de cancelación de pedido

### Parámetros de URL

#### Pedido Recibido
```
/pedido-recibido?order_id=123&key=wc_order_abc123
```

#### Pedido Cancelado
```
/pedido-cancelado?order_id=123
```

## Panel de Administración

### Configuración
Accede a **Headless > Configuración** para:
- Configurar URL del frontend
- Ajustar configuración de JWT
- Configurar Cloudflare Turnstile
- Ajustar límites de rate limiting
- Establecer límite de sesiones

### Sesiones Activas
Accede a **Headless > Sesiones Activas** para:
- Ver todas las sesiones activas
- Cerrar sesiones manualmente
- Monitorear actividad de usuarios

### Analítica
Accede a **Headless > Analítica** para:
- Ver estadísticas de autenticación
- Analizar intentos fallidos
- Revisar eventos de seguridad

## Seguridad

### Rate Limiting
El plugin incluye rate limiting automático para:
- Prevenir ataques de fuerza bruta
- Limitar intentos de inicio de sesión
- Bloquear IPs sospechosas temporalmente

### Sesiones
- Tokens JWT con expiración automática
- Refresh tokens para renovación segura
- Límite de sesiones simultáneas por usuario
- Cierre automático de sesiones expiradas

### CAPTCHA
- Integración con Cloudflare Turnstile
- Validación en registro e inicio de sesión
- Protección contra bots

## Changelog

### 1.0.0 (2026-01-18)
- Versión inicial consolidada
- Integración completa de autenticación JWT
- Modificación de URLs de checkout
- APIs de clientes y pedidos
- Panel de administración
- Analítica y monitoreo

## Soporte

Para soporte, contacta a: Francisco Solis
- Web: https://franciscosolis.cl
- Email: [tu-email]

## Licencia

GPL v3 - https://www.gnu.org/licenses/gpl-3.0.html
