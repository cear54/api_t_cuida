<?php
// Cargar variables de entorno
require_once __DIR__ . '/../config/env.php';

// Configuración de headers para CORS (Cross-Origin Resource Sharing)
$cors_origin = EnvLoader::get('CORS_ORIGIN', '*');
header("Access-Control-Allow-Origin: $cors_origin");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar requests OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir archivo de configuración de base de datos
include_once '../config/database.php';

// Función para enviar respuesta JSON
function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Función para validar email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Función para validar datos requeridos
function validateRequiredFields($data, $required_fields) {
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            return false;
        }
    }
    return true;
}
?>
