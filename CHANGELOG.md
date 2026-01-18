# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Versionado Semántico](https://semver.org/lang/es/).

## [1.0.0] - 2026-01-18

### 🎉 Lanzamiento Inicial - Plugin Consolidado

Primera versión consolidada que une los plugins `headless-authentication` y `headless-checkout` en un solo plugin completo.

### ✨ Agregado

#### Autenticación JWT
- Sistema completo de autenticación basado en JWT (JSON Web Tokens)
- Endpoints de registro, login, logout y validación
- Tokens de acceso con expiración configurable (default: 1 hora)
- Refresh tokens para renovación automática (default: 7 días)
- Gestión de múltiples sesiones simultáneas por usuario
- Límite configurable de sesiones por usuario (default: 5)
- API para listar y cerrar sesiones individuales

#### Seguridad
- Rate limiting integrado para prevenir ataques de fuerza bruta
- Configuración flexible de límites (intentos, ventana de tiempo, duración de bloqueo)
- Integración con Cloudflare Turnstile para validación CAPTCHA
- Bloqueo temporal de IPs con intentos fallidos excesivos
- Validación de contraseñas seguras en registro
- Hashing seguro de tokens en base de datos

#### Checkout Headless
- Modificación automática de URLs de retorno del checkout
- Redirección a frontend en pedidos completados (`/pedido-recibido`)
- Redirección a frontend en pedidos cancelados (`/pedido-cancelado`)
- Soporte para múltiples métodos de pago (Transbank, PayPal, Mercado Pago, etc.)
- Integración con WooCommerce Store API
- Manejo inteligente de redirecciones según método de pago

#### APIs REST
- **Customer API**: Obtener y actualizar información del cliente
- **Orders API**: Listar y obtener detalles de pedidos
- Endpoints RESTful siguiendo mejores prácticas
- Respuestas JSON estructuradas y consistentes
- Paginación en listados de pedidos
- Filtros por estado, fecha y otros criterios

#### Panel de Administración
- Página de configuración central con todas las opciones
- Gestión visual de sesiones activas
- Panel de analítica con estadísticas de autenticación
- Registro de eventos de seguridad
- Interfaz intuitiva y moderna

#### Base de Datos
- Tabla de sesiones (`casa_alba_auth_sessions`)
- Tabla de rate limiting (`casa_alba_auth_rate_limits`)
- Tabla de analítica (`casa_alba_auth_analytics`)
- Índices optimizados para rendimiento
- Limpieza automática de sesiones expiradas

#### Configuración
- Configuración centralizada en `wp-config.php` o panel de admin
- JWT algorithm configurable (default: HS256)
- Generación automática de secret key seguro
- Configuración de tiempos de expiración
- Habilitación/deshabilitación de features individuales

#### Documentación
- README.md completo con guía de uso
- INSTALLATION.md con guía paso a paso
- API.md con documentación detallada de endpoints
- Ejemplos de código en JavaScript y PHP
- Solución de problemas comunes

### 🔧 Configuración Inicial

#### Opciones por Defecto
- JWT Algorithm: `HS256`
- Token Expiration: `3600` segundos (1 hora)
- Refresh Token Expiration: `604800` segundos (7 días)
- Rate Limit Max Attempts: `5`
- Rate Limit Window: `900` segundos (15 minutos)
- Rate Limit Lockout Duration: `1800` segundos (30 minutos)
- Session Limit: `5` sesiones por usuario
- Frontend URL: `https://productoscasaalba.cl`

### 📦 Archivos Incluidos

#### Core
- `headless-productoscasaalba.php` - Archivo principal del plugin

#### Includes
- `class-jwt-manager.php` - Gestión de tokens JWT
- `class-rate-limiter.php` - Control de rate limiting
- `class-session-manager.php` - Gestión de sesiones
- `class-analytics.php` - Analítica de autenticación
- `class-turnstile-validator.php` - Validación de Cloudflare Turnstile
- `class-auth-api.php` - API REST de autenticación
- `class-auth-middleware.php` - Middleware de autenticación
- `class-customer-api.php` - API REST de clientes
- `class-orders-api.php` - API REST de pedidos

#### Admin
- `settings-page.php` - Página de configuración
- `sessions-page.php` - Página de sesiones activas
- `analytics-page.php` - Página de analítica

#### Assets
- `assets/css/admin.css` - Estilos del panel de admin
- `assets/js/admin.js` - Scripts del panel de admin

#### Documentación
- `README.md` - Documentación general
- `INSTALLATION.md` - Guía de instalación
- `API.md` - Documentación de APIs
- `CHANGELOG.md` - Este archivo

### 🔄 Migración desde Plugins Separados

Si vienes de los plugins `headless-authentication` y `headless-checkout`:

1. **Compatibilidad**: Este plugin mantiene todas las tablas y opciones existentes
2. **No requiere reconfiguración**: Tu configuración actual se mantiene
3. **Sesiones activas**: Las sesiones existentes seguirán funcionando
4. **Endpoints**: Todos los endpoints mantienen la misma URL

#### Pasos de Migración Recomendados:
1. Haz backup de tu base de datos
2. Desactiva los plugins antiguos
3. Activa este nuevo plugin
4. Verifica que todo funcione correctamente
5. Elimina los plugins antiguos (opcional)

### 🛠️ Requisitos

- WordPress 5.8 o superior
- PHP 7.4 o superior
- WooCommerce 6.0 o superior
- PHP Extensions: json, openssl, hash

### 🔐 Seguridad

- Todos los tokens se guardan hasheados en la base de datos
- Rate limiting habilitado por defecto
- Validación de entrada en todos los endpoints
- Sanitización de salida
- Protección contra ataques CSRF
- Headers de seguridad configurados

### 📊 Analítica Incluida

El plugin registra automáticamente:
- Intentos de login (exitosos y fallidos)
- Registros de usuarios
- Validaciones de token
- Cierres de sesión
- Eventos de seguridad
- Rate limit violations

### 🌐 CORS

Configurable desde `wp-config.php` para permitir solicitudes desde el frontend:

```php
define('CASA_ALBA_ALLOW_CORS', true);
define('CASA_ALBA_CORS_ORIGIN', 'https://productoscasaalba.cl');
```

### 🐛 Bugs Conocidos

Ninguno en esta versión inicial.

### 🚀 Próximas Características (Planificadas)

#### v1.1.0
- [ ] Soporte para OAuth (Google, Facebook)
- [ ] Two-Factor Authentication (2FA)
- [ ] Webhooks para eventos de pedidos
- [ ] API de productos
- [ ] Cache de respuestas

#### v1.2.0
- [ ] Notificaciones push
- [ ] Integración con Stripe
- [ ] Soporte para suscripciones
- [ ] Dashboard de métricas avanzadas

### 📝 Notas de Desarrollo

- Código sigue WordPress Coding Standards
- Documentación inline en PHPDoc
- Prefijo `casa_alba_` en todas las funciones y clases
- Uso de hooks de WordPress para extensibilidad
- Arquitectura orientada a objetos
- Patrón Singleton en clase principal

### 🙏 Agradecimientos

Desarrollado por Francisco Solis para Productos Casa Alba.

### 📄 Licencia

GPL v3 - https://www.gnu.org/licenses/gpl-3.0.html

---

## Formato de Versiones

El proyecto sigue [Versionado Semántico](https://semver.org/):
- **MAJOR**: Cambios incompatibles con versiones anteriores
- **MINOR**: Nuevas funcionalidades compatibles
- **PATCH**: Correcciones de bugs compatibles

Ejemplo: `MAJOR.MINOR.PATCH` → `1.0.0`

---

## Tipos de Cambios

- **Agregado** (Added): Para nuevas características
- **Cambiado** (Changed): Para cambios en funcionalidad existente
- **Obsoleto** (Deprecated): Para características que se eliminarán
- **Eliminado** (Removed): Para características eliminadas
- **Corregido** (Fixed): Para corrección de bugs
- **Seguridad** (Security): Para vulnerabilidades

---

**Nota**: Este CHANGELOG se actualizará con cada nueva versión del plugin.
