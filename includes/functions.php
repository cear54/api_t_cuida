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
    // Limpiar cualquier salida previa que pueda corromper el JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Forzar Content-Type JSON
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code($code);
    
    $response = [
        'success' => (bool)$success,
        'message' => (string)$message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Verificar que la respuesta se pueda codificar como JSON
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($json === false) {
        // Si falla la codificación, enviar un error genérico
        error_log("[API] Error al codificar JSON: " . json_last_error_msg());
        $json = json_encode([
            'success' => false,
            'message' => 'Error interno al generar respuesta',
            'error_code' => json_last_error(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    echo $json;
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

/**
 * Valida que la petición tenga Content-Type JSON
 * 
 * @return bool true si es JSON, false si no
 */
function validateJsonContentType() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    
    // Si no hay Content-Type, aceptar (puede ser una petición GET simple)
    if (empty($contentType)) {
        return true;
    }
    
    // Verificar que contenga 'application/json'
    return stripos($contentType, 'application/json') !== false;
}

/**
 * Valida y decodifica el body JSON de una petición
 * 
 * @return object|null El objeto decodificado o null si hay error
 */
function getJsonBody() {
    $rawInput = file_get_contents("php://input");
    
    if (empty($rawInput)) {
        sendResponse(false, "Body de la petición vacío", null, 400);
    }
    
    $data = json_decode($rawInput);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[API] JSON inválido recibido: " . json_last_error_msg());
        error_log("[API] Raw input: " . substr($rawInput, 0, 200)); // Log primeros 200 chars
        sendResponse(false, "JSON inválido: " . json_last_error_msg(), null, 400);
    }
    
    return $data;
}

/**
 * Registra intentos sospechosos (posibles ataques o errores de red)
 */
function logSuspiciousActivity($activity, $details = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'activity' => $activity,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'details' => $details
    ];
    
    error_log("[SUSPICIOUS] " . json_encode($logEntry));
}
?>

