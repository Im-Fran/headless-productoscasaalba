# API Documentation - Headless Productos Casa Alba

Documentación completa de las APIs REST disponibles en el plugin.

## Base URL

```
https://tu-sitio.com/wp-json/casa-alba/v1
```

## Autenticación

La mayoría de los endpoints requieren un token JWT válido en el header `Authorization`:

```
Authorization: Bearer <tu_token_jwt>
```

## Endpoints

---

## 🔐 Autenticación

### 1. Registro de Usuario

Crea una nueva cuenta de usuario.

**Endpoint**: `POST /auth/register`

**Headers**:
```
Content-Type: application/json
```

**Body**:
```json
{
  "email": "usuario@ejemplo.com",
  "password": "Password123!",
  "first_name": "Juan",
  "last_name": "Pérez",
  "turnstile_token": "token_opcional_cloudflare"
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Usuario registrado exitosamente",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 123,
    "email": "usuario@ejemplo.com",
    "first_name": "Juan",
    "last_name": "Pérez",
    "display_name": "Juan Pérez",
    "roles": ["customer"]
  }
}
```

**Response Error (400/422)**:
```json
{
  "success": false,
  "message": "El email ya está registrado",
  "code": "email_exists"
}
```

**Errores Posibles**:
- `email_required`: Email es requerido
- `password_required`: Contraseña es requerida
- `invalid_email`: Email inválido
- `weak_password`: Contraseña débil
- `email_exists`: El email ya está registrado
- `turnstile_failed`: Validación de Turnstile fallida
- `rate_limit_exceeded`: Demasiados intentos

---

### 2. Inicio de Sesión

Autentica un usuario y obtiene tokens JWT.

**Endpoint**: `POST /auth/login`

**Headers**:
```
Content-Type: application/json
```

**Body**:
```json
{
  "email": "usuario@ejemplo.com",
  "password": "Password123!",
  "turnstile_token": "token_opcional_cloudflare"
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 123,
    "email": "usuario@ejemplo.com",
    "first_name": "Juan",
    "last_name": "Pérez",
    "display_name": "Juan Pérez",
    "roles": ["customer"]
  },
  "expires_in": 3600
}
```

**Response Error (401)**:
```json
{
  "success": false,
  "message": "Credenciales inválidas",
  "code": "invalid_credentials"
}
```

**Errores Posibles**:
- `email_required`: Email es requerido
- `password_required`: Contraseña es requerida
- `invalid_credentials`: Credenciales incorrectas
- `account_locked`: Cuenta bloqueada por demasiados intentos fallidos
- `turnstile_failed`: Validación de Turnstile fallida
- `rate_limit_exceeded`: Demasiados intentos

---

### 3. Refrescar Token

Obtiene un nuevo token de acceso usando un refresh token válido.

**Endpoint**: `POST /auth/refresh`

**Headers**:
```
Authorization: Bearer <refresh_token>
```

**Response Success (200)**:
```json
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 3600
}
```

**Response Error (401)**:
```json
{
  "success": false,
  "message": "Token de refresco inválido o expirado",
  "code": "invalid_refresh_token"
}
```

---

### 4. Cerrar Sesión

Invalida el token actual y cierra la sesión.

**Endpoint**: `POST /auth/logout`

**Headers**:
```
Authorization: Bearer <token>
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Sesión cerrada exitosamente"
}
```

---

### 5. Validar Token

Verifica si el token actual es válido.

**Endpoint**: `GET /auth/validate`

**Headers**:
```
Authorization: Bearer <token>
```

**Response Success (200)**:
```json
{
  "success": true,
  "valid": true,
  "user_id": 123,
  "expires_at": "2026-01-18T18:30:00Z"
}
```

**Response Error (401)**:
```json
{
  "success": false,
  "valid": false,
  "message": "Token inválido o expirado"
}
```

---

### 6. Obtener Sesiones Activas

Lista todas las sesiones activas del usuario actual.

**Endpoint**: `GET /auth/sessions`

**Headers**:
```
Authorization: Bearer <token>
```

**Response Success (200)**:
```json
{
  "success": true,
  "sessions": [
    {
      "id": 1,
      "device_type": "desktop",
      "browser": "Chrome",
      "os": "Windows",
      "ip_address": "192.168.1.1",
      "last_activity": "2026-01-18T18:30:00Z",
      "created_at": "2026-01-18T10:00:00Z",
      "is_current": true
    },
    {
      "id": 2,
      "device_type": "mobile",
      "browser": "Safari",
      "os": "iOS",
      "ip_address": "192.168.1.2",
      "last_activity": "2026-01-17T15:00:00Z",
      "created_at": "2026-01-17T15:00:00Z",
      "is_current": false
    }
  ],
  "total": 2
}
```

---

### 7. Cerrar Sesión Específica

Cierra una sesión específica del usuario.

**Endpoint**: `DELETE /auth/sessions/{session_id}`

**Headers**:
```
Authorization: Bearer <token>
```

**URL Params**:
- `session_id` (int): ID de la sesión a cerrar

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Sesión cerrada exitosamente"
}
```

**Response Error (404)**:
```json
{
  "success": false,
  "message": "Sesión no encontrada"
}
```

---

### 8. Solicitar Recuperación de Contraseña

Envía un email con enlace para restablecer contraseña.

**Endpoint**: `POST /auth/forgot-password`

**Headers**:
```
Content-Type: application/json
```

**Body**:
```json
{
  "email": "usuario@ejemplo.com"
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Email de recuperación enviado"
}
```

---

### 9. Restablecer Contraseña

Cambia la contraseña usando el código de recuperación.

**Endpoint**: `POST /auth/reset-password`

**Headers**:
```
Content-Type: application/json
```

**Body**:
```json
{
  "email": "usuario@ejemplo.com",
  "code": "codigo_recuperacion",
  "password": "NuevaPassword123!"
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Contraseña actualizada exitosamente"
}
```

---

## 👤 Cliente (Customer)

### 1. Obtener Información del Cliente

Obtiene la información completa del cliente actual.

**Endpoint**: `GET /customer`

**Headers**:
```
Authorization: Bearer <token>
```

**Response Success (200)**:
```json
{
  "id": 123,
  "email": "usuario@ejemplo.com",
  "first_name": "Juan",
  "last_name": "Pérez",
  "username": "usuario",
  "billing": {
    "first_name": "Juan",
    "last_name": "Pérez",
    "company": "",
    "address_1": "Calle Principal 123",
    "address_2": "Depto 4B",
    "city": "Santiago",
    "state": "RM",
    "postcode": "8320000",
    "country": "CL",
    "email": "usuario@ejemplo.com",
    "phone": "+56912345678"
  },
  "shipping": {
    "first_name": "Juan",
    "last_name": "Pérez",
    "company": "",
    "address_1": "Calle Principal 123",
    "address_2": "Depto 4B",
    "city": "Santiago",
    "state": "RM",
    "postcode": "8320000",
    "country": "CL"
  },
  "avatar_url": "https://gravatar.com/...",
  "date_created": "2026-01-01T00:00:00Z",
  "date_modified": "2026-01-18T00:00:00Z"
}
```

---

### 2. Actualizar Información del Cliente

Actualiza la información del cliente actual.

**Endpoint**: `PUT /customer`

**Headers**:
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body** (todos los campos son opcionales):
```json
{
  "first_name": "Juan",
  "last_name": "Pérez",
  "billing": {
    "first_name": "Juan",
    "last_name": "Pérez",
    "address_1": "Nueva Calle 456",
    "city": "Santiago",
    "state": "RM",
    "postcode": "8320000",
    "country": "CL",
    "email": "usuario@ejemplo.com",
    "phone": "+56912345678"
  },
  "shipping": {
    "first_name": "Juan",
    "last_name": "Pérez",
    "address_1": "Nueva Calle 456",
    "city": "Santiago",
    "state": "RM",
    "postcode": "8320000",
    "country": "CL"
  }
}
```

**Response Success (200)**:
```json
{
  "success": true,
  "message": "Información actualizada exitosamente",
  "customer": {
    "id": 123,
    "email": "usuario@ejemplo.com",
    "first_name": "Juan",
    "last_name": "Pérez",
    "billing": { ... },
    "shipping": { ... }
  }
}
```

---

## 📦 Pedidos (Orders)

### 1. Listar Pedidos

Obtiene la lista de pedidos del cliente actual.

**Endpoint**: `GET /orders`

**Headers**:
```
Authorization: Bearer <token>
```

**Query Parameters**:
- `page` (int): Número de página (default: 1)
- `per_page` (int): Elementos por página (default: 10, max: 100)
- `status` (string): Filtrar por estado (pending, processing, completed, cancelled, etc.)
- `orderby` (string): Ordenar por (date, id, total)
- `order` (string): Dirección (asc, desc)

**Example**:
```
GET /orders?page=1&per_page=20&status=completed&orderby=date&order=desc
```

**Response Success (200)**:
```json
{
  "success": true,
  "orders": [
    {
      "id": 456,
      "order_key": "wc_order_abc123",
      "status": "completed",
      "currency": "CLP",
      "total": "25990",
      "subtotal": "25990",
      "total_tax": "0",
      "shipping_total": "0",
      "date_created": "2026-01-18T10:00:00Z",
      "date_completed": "2026-01-18T12:00:00Z",
      "payment_method": "transbank",
      "payment_method_title": "Transbank Webpay Plus",
      "billing": {
        "first_name": "Juan",
        "last_name": "Pérez",
        "address_1": "Calle Principal 123",
        "city": "Santiago",
        "postcode": "8320000",
        "country": "CL",
        "email": "usuario@ejemplo.com",
        "phone": "+56912345678"
      },
      "shipping": {
        "first_name": "Juan",
        "last_name": "Pérez",
        "address_1": "Calle Principal 123",
        "city": "Santiago",
        "postcode": "8320000",
        "country": "CL"
      },
      "line_items": [
        {
          "id": 789,
          "name": "Producto Ejemplo",
          "product_id": 100,
          "variation_id": 0,
          "quantity": 2,
          "subtotal": "25990",
          "total": "25990",
          "price": "12995"
        }
      ]
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 10,
  "total_pages": 2
}
```

---

### 2. Obtener Pedido Específico

Obtiene los detalles completos de un pedido.

**Endpoint**: `GET /orders/{order_id}`

**Headers**:
```
Authorization: Bearer <token>
```

**URL Params**:
- `order_id` (int): ID del pedido

**Response Success (200)**:
```json
{
  "success": true,
  "order": {
    "id": 456,
    "order_key": "wc_order_abc123",
    "order_number": "456",
    "status": "completed",
    "currency": "CLP",
    "total": "25990",
    "subtotal": "25990",
    "total_tax": "0",
    "shipping_total": "0",
    "discount_total": "0",
    "date_created": "2026-01-18T10:00:00Z",
    "date_modified": "2026-01-18T12:00:00Z",
    "date_completed": "2026-01-18T12:00:00Z",
    "date_paid": "2026-01-18T10:30:00Z",
    "customer_note": "",
    "payment_method": "transbank",
    "payment_method_title": "Transbank Webpay Plus",
    "transaction_id": "TRX123456789",
    "billing": { ... },
    "shipping": { ... },
    "line_items": [
      {
        "id": 789,
        "name": "Producto Ejemplo",
        "product_id": 100,
        "variation_id": 0,
        "quantity": 2,
        "subtotal": "25990",
        "total": "25990",
        "price": "12995",
        "image": {
          "id": 50,
          "src": "https://example.com/image.jpg"
        }
      }
    ],
    "shipping_lines": [
      {
        "id": 101,
        "method_title": "Envío Express",
        "method_id": "flat_rate",
        "total": "0"
      }
    ],
    "meta_data": []
  }
}
```

**Response Error (404)**:
```json
{
  "success": false,
  "message": "Pedido no encontrado",
  "code": "order_not_found"
}
```

**Response Error (403)**:
```json
{
  "success": false,
  "message": "No tienes permiso para ver este pedido",
  "code": "permission_denied"
}
```

---

## Códigos de Estado HTTP

- `200 OK`: Solicitud exitosa
- `201 Created`: Recurso creado exitosamente
- `400 Bad Request`: Solicitud inválida o datos faltantes
- `401 Unauthorized`: Token inválido o expirado
- `403 Forbidden`: Sin permisos para acceder al recurso
- `404 Not Found`: Recurso no encontrado
- `422 Unprocessable Entity`: Validación fallida
- `429 Too Many Requests`: Rate limit excedido
- `500 Internal Server Error`: Error del servidor

---

## Rate Limiting

Todos los endpoints están protegidos con rate limiting:

- **Endpoints de autenticación**: 5 intentos por IP cada 15 minutos
- **Otros endpoints**: 100 solicitudes por usuario cada hora

Cuando se excede el límite:

```json
{
  "success": false,
  "message": "Demasiadas solicitudes. Intenta nuevamente en 10 minutos.",
  "code": "rate_limit_exceeded",
  "retry_after": 600
}
```

---

## Ejemplos de Uso

### JavaScript (Fetch)

```javascript
// Login
const login = async (email, password) => {
  const response = await fetch('https://tu-sitio.com/wp-json/casa-alba/v1/auth/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email, password })
  });
  
  const data = await response.json();
  
  if (data.success) {
    localStorage.setItem('token', data.token);
    localStorage.setItem('refresh_token', data.refresh_token);
    return data;
  }
  
  throw new Error(data.message);
};

// Obtener pedidos
const getOrders = async () => {
  const token = localStorage.getItem('token');
  
  const response = await fetch('https://tu-sitio.com/wp-json/casa-alba/v1/orders', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  return await response.json();
};
```

### Axios

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://tu-sitio.com/wp-json/casa-alba/v1',
  headers: {
    'Content-Type': 'application/json'
  }
});

// Interceptor para agregar token
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Uso
const orders = await api.get('/orders');
const customer = await api.get('/customer');
```

---

## Soporte

Para más información o soporte:
- Documentación completa: [README.md](README.md)
- Guía de instalación: [INSTALLATION.md](INSTALLATION.md)
- Contacto: Francisco Solis - https://franciscosolis.cl
