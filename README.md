# API T-Cuida

API REST en PHP para la aplicación T-Cuida con conexión a MySQL.

## Configuración

### Base de Datos
- **Base de datos:** estancias
- **Tabla:** usuarios_app
- **Usuario:** root
- **Contraseña:** (vacía para XAMPP local)

### Estructura de la tabla usuarios_app
```sql
CREATE TABLE usuarios_app (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    activo TINYINT(1) DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Endpoints

### GET /api/health.php
Verifica que el API esté funcionando correctamente. Útil para diagnóstico de problemas de conectividad.

**URL:** `/api/health.php`

**Método:** GET

**Headers:** Ninguno (no requiere autenticación)

**Respuesta exitosa (200):**
```json
{
    "success": true,
    "message": "API funcionando correctamente",
    "status": "healthy",
    "api_version": "1.0",
    "timestamp": "2026-03-11 10:30:00",
    "database_status": "connected",
    "server_info": {
        "php_version": "8.1.0",
        "server_software": "Apache/2.4",
        "server_time": "2026-03-11 10:30:00",
        "timezone": "America/Mexico_City"
    },
    "endpoints_available": {
        "login": "/api/login.php",
        "health": "/api/health.php"
    },
    "request_info": {
        "method": "GET",
        "protocol": "HTTP/1.1",
        "remote_addr": "192.168.1.100",
        "user_agent": "Dart/2.18"
    }
}
```

**Uso:**
- Verificar si el API es accesible desde una red
- Diagnosticar problemas de firewall o captive portal
- Comprobar estado de la base de datos
- Obtener información del servidor para debugging

---

### POST /api/login
Autentica un usuario en el sistema.

**URL:** `/api/login.php`

**Método:** POST

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
    "email": "usuario@email.com",
    "password": "contraseña"
}
```

**Respuesta exitosa (200):**
```json
{
    "success": true,
    "message": "Login exitoso",
    "data": {
        "id": 1,
        "email": "usuario@email.com",
        "nombre": "Nombre",
        "apellidos": "Apellidos",
        "telefono": "1234567890",
        "fecha_registro": "2025-03-09 10:30:00"
    },
    "timestamp": "2025-03-09 10:30:00"
}
```

**Respuesta error (401):**
```json
{
    "success": false,
    "message": "Credenciales incorrectas",
    "data": null,
    "timestamp": "2025-03-09 10:30:00"
}
```

**Respuesta error (400):**
```json
{
    "success": false,
    "message": "Email y contraseña son requeridos",
    "data": null,
    "timestamp": "2025-03-09 10:30:00"
}
```

## Instalación

1. Copia los archivos en tu servidor web (XAMPP, WAMP, etc.)
2. Asegúrate de que la base de datos "estancias" existe
3. Crea la tabla "usuarios_app" con la estructura indicada
4. Configura los datos de conexión en `config/database.php` si es necesario
5. Accede a `test.html` para probar la API

## Estructura de archivos

```
api_t_cuida/
├── config/
│   └── database.php          # Configuración de base de datos
├── includes/
│   └── functions.php         # Funciones auxiliares
├── api/
│   ├── index.php            # Punto de entrada principal
│   ├── login.php            # Endpoint de login
│   ├── test.html            # Archivo de prueba
│   └── .htaccess            # Configuración Apache
└── README.md                # Este archivo
```

## Uso con Flutter

Para usar esta API en Flutter, puedes hacer peticiones HTTP así:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

Future<Map<String, dynamic>> login(String email, String password) async {
  final response = await http.post(
    Uri.parse('http://localhost/api_t_cuida/api/login.php'),
    headers: {'Content-Type': 'application/json'},
    body: json.encode({
      'email': email,
      'password': password,
    }),
  );
  
  return json.decode(response.body);
}
```

## Seguridad

- Las contraseñas deben estar hasheadas con `password_hash()` en PHP
- Se utiliza `password_verify()` para verificar las contraseñas
- Se incluyen headers CORS para permitir peticiones desde Flutter
- Se valida el formato del email
- Se utilizan prepared statements para prevenir inyección SQL

## Configuración para servidor remoto

Las variables de entorno se configuran en el archivo `.env`. 

Para producción, el script de deploy ajusta automáticamente:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `CORS_ORIGIN=https://estancias.cear54.com`

Ver [DEPLOY.md](DEPLOY.md) para instrucciones completas de deployment.

## 🚀 Deploy al Servidor

### Deploy Completo
```powershell
.\deploy.ps1
```

### Deploy Solo Variables (.env)
```powershell
.\deploy.ps1 -OnlyEnv
```

### Quick Deploy (Archivo Específico)
```powershell
.\quick-deploy.ps1 -Files "api/login.php"
```

**Servidor de Producción**:
- URL: `https://estancias.cear54.com/api_t_cuida/api`
- Health: `https://estancias.cear54.com/api_t_cuida/api/health.php`

Ver [DEPLOY.md](DEPLOY.md) para más detalles y troubleshooting.
