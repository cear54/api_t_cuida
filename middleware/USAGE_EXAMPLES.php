<?php
/**
 * EJEMPLO DE USO DEL MIDDLEWARE DE VALIDACIÓN DE SUSCRIPCIONES
 * 
 * Este archivo documenta cómo implementar la validación de suscripciones
 * en los diferentes tipos de endpoints del API.
 */

// ============================================================================
// OPCIÓN 1: Usar JWTHandler::requireAuth() (RECOMENDADO)
// ============================================================================
// Esta es la forma más simple. El método requireAuth() ahora incluye
// automáticamente la validación de suscripción.

// Ejemplo:
/*
<?php
require_once '../config/database.php';
require_once '../utils/JWTHandler.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Esta línea valida JWT + usuario activo + suscripción de empresa
$userData = JWTHandler::requireAuth();

// Si llega aquí, todo está validado
$database = new Database();
$db = $database->getConnection();

// ... resto del código del endpoint ...
?>
*/

// ============================================================================
// OPCIÓN 2: Usar SubscriptionValidator directamente
// ============================================================================
// Para casos donde ya tienes tu propia validación de JWT

// Ejemplo:
/*
<?php
require_once '../config/database.php';
require_once '../utils/JWTHandler.php';
require_once '../middleware/subscription_validator.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Tu propia validación de JWT
$token = JWTHandler::getTokenFromHeader();
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Token requerido']);
    exit;
}

$userData = JWTHandler::verifyToken($token);
if (!$userData) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

// Validar suscripción
$database = new Database();
$db = $database->getConnection();

$empresa_id = $userData['empresa_id'] ?? null;
if ($empresa_id) {
    $subscriptionStatus = SubscriptionValidator::validateSubscription($db, $empresa_id);
    
    if (!$subscriptionStatus['valid']) {
        http_response_code($subscriptionStatus['code']);
        echo json_encode([
            'success' => false,
            'message' => $subscriptionStatus['message']
        ]);
        exit;
    }
}

// ... resto del código del endpoint ...
?>
*/

// ============================================================================
// OPCIÓN 3: Usar validateAuthAndSubscription (TODO EN UNO)
// ============================================================================
// Esta opción valida JWT y suscripción en una sola llamada

// Ejemplo:
/*
<?php
require_once '../config/database.php';
require_once '../middleware/subscription_validator.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$database = new Database();
$db = $database->getConnection();

// Esta línea valida JWT + suscripción en una sola llamada
$userData = SubscriptionValidator::validateAuthAndSubscription($db);

// Si llega aquí, todo está validado
// ... resto del código del endpoint ...
?>
*/

// ============================================================================
// ENDPOINTS QUE YA USAN requireAuth() (No requieren cambios)
// ============================================================================
// Los siguientes endpoints ya están protegidos automáticamente:
// - nino.php
// - eventos.php
// - tareas.php
// - colegiaturas.php
// - personal_salon.php
// - Y todos los que usen JWTHandler::requireAuth()

// ============================================================================
// ENDPOINTS QUE NECESITAN ACTUALIZACIÓN
// ============================================================================
// Los siguientes endpoints tienen validación JWT manual y deben actualizarse:
// - get_children.php
// - asistencia_entrada.php
// - asistencia_salida.php
// - asistencia_estado.php
// - asistencia_historial.php
// - bitacora_comportamiento.php
// - obtener_bitacora.php
// - salida_registro.php
// - subir_imagen_bitacora.php
// - Y otros que usen validación JWT manual

?>
