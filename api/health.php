<?php
/**
 * Health Check Endpoint
 * 
 * Este endpoint permite diagnosticar problemas de conectividad.
 * No requiere autenticación y siempre responde con JSON.
 * 
 * Uso: GET /api/health.php
 */

// Forzar Content-Type JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Manejar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido',
        'allowed_methods' => ['GET', 'OPTIONS']
    ]);
    exit();
}

// Información del servidor
$serverInfo = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'server_time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
];

// Verificar conexión a base de datos (opcional)
$dbStatus = 'not_checked';
try {
    if (file_exists('../config/database.php')) {
        include_once '../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        $dbStatus = ($db !== null) ? 'connected' : 'failed';
    }
} catch (Exception $e) {
    $dbStatus = 'error: ' . $e->getMessage();
}

// Respuesta exitosa
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'API funcionando correctamente',
    'status' => 'healthy',
    'api_version' => '1.0',
    'timestamp' => date('Y-m-d H:i:s'),
    'database_status' => $dbStatus,
    'server_info' => $serverInfo,
    'endpoints_available' => [
        'login' => '/api/login.php',
        'health' => '/api/health.php',
        'ninos' => '/api/ninos.php',
    ],
    // Información útil para debugging
    'request_info' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'unknown',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ]
]);
exit();
