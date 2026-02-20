<?php
// Log inicial para debug
error_log("=== DEBUG SUBIR IMAGEN - INICIO ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Headers: " . print_r(getallheaders(), true));
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar que el método sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Incluir archivos necesarios
include_once '../config/database.php';
include_once '../utils/JWTHandler.php';
include_once '../includes/timezone_helper.php';

// Verificar token JWT - Múltiples métodos para obtener el header Authorization
$authHeader = null;
$headers = getallheaders();

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
} elseif (isset($headers['authorization'])) {
    $authHeader = $headers['authorization'];
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_X_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $apacheHeaders = apache_request_headers();
    if (isset($apacheHeaders['Authorization'])) {
        $authHeader = $apacheHeaders['Authorization'];
    }
}

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Token requerido']);
    exit;
}

$token = substr($authHeader, 7);

try {
    error_log("DEBUG: Attempting to verify token: " . substr($token, 0, 20) . "...");
    $payload = JWTHandler::verifyToken($token);
    
    if (!$payload) {
        error_log("DEBUG: Token verification failed");
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }
    
    error_log("DEBUG: Token verified successfully. Payload: " . print_r($payload, true));
    
    $userId = $payload['user_id'] ?? $payload['id'] ?? null; // Flexibilidad en el nombre del campo
    $empresaId = $payload['empresa_id'] ?? null;
    
    if (!$empresaId) {
        error_log("DEBUG: No empresa_id in token. Available fields: " . implode(', ', array_keys($payload)));
        http_response_code(401);
        echo json_encode(['error' => 'Token no contiene información de empresa']);
        exit;
    }
    
    // Validar suscripción de la empresa
    require_once '../middleware/subscription_validator.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $subscriptionStatus = SubscriptionValidator::validateSubscription($db, $empresaId);
    
    if (!$subscriptionStatus['valid']) {
        error_log("DEBUG: Subscription validation failed: " . $subscriptionStatus['message']);
        http_response_code($subscriptionStatus['code']);
        echo json_encode([
            'success' => false,
            'message' => $subscriptionStatus['message']
        ]);
        exit;
    }
    
    error_log("DEBUG: User ID: $userId, Empresa ID: $empresaId");
} catch (Exception $e) {
    error_log("DEBUG: JWT Exception: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido: ' . $e->getMessage()]);
    exit;
}

// Verificar que se haya enviado una imagen
if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    error_log("DEBUG: Image upload error. FILES: " . print_r($_FILES, true));
    error_log("DEBUG: POST data: " . print_r($_POST, true));
    
    $errorMsg = 'No se recibió ninguna imagen válida';
    if (isset($_FILES['imagen']['error'])) {
        $errorMsg .= ' (Error code: ' . $_FILES['imagen']['error'] . ')';
    }
    
    http_response_code(400);
    echo json_encode(['error' => $errorMsg]);
    exit;
}

// Obtener información adicional
$ninoId = $_POST['nino_id'] ?? null;
$empresaIdPost = $_POST['empresa_id'] ?? null;
$fechaBitacora = $_POST['fecha'] ?? TimezoneHelper::getCurrentDate(); // Fecha de la bitácora

error_log("DEBUG: Received data - nino_id: $ninoId, empresa_id: $empresaIdPost, fecha: $fechaBitacora");

if (!$ninoId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID del niño requerido']);
    exit;
}

// Validar que el empresa_id del POST coincida con el del token
if ($empresaIdPost && $empresaIdPost != $empresaId) {
    error_log("DEBUG: Empresa ID mismatch. Token: $empresaId, POST: $empresaIdPost");
    http_response_code(400);
    echo json_encode(['error' => 'empresa_id no coincide con el token']);
    exit;
}

// Validar formato de fecha
if (!TimezoneHelper::validateDateFormat($fechaBitacora)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el niño pertenezca a la misma empresa
    $checkQuery = "SELECT n.id FROM ninos n 
                   WHERE n.id = :nino_id 
                   AND n.empresa_id = :empresa_id 
                   AND n.activo = 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
    $checkStmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Niño no encontrado o no pertenece a tu empresa']);
        exit;
    }

    // Configurar directorio de subida
    $uploadDir = '../uploads/bitacoras/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Validar tipo de archivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $fileType = $_FILES['imagen']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de archivo no permitido. Solo JPG, PNG y WebP']);
        exit;
    }

    // Validar tamaño (máximo 5MB)
    if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'El archivo es demasiado grande. Máximo 5MB']);
        exit;
    }

    // Generar nombre único para el archivo
    $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    $fileName = 'bitacora_' . $empresaId . '_' . $ninoId . '_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    // Mover archivo subido
    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar la imagen']);
        exit;
    }

    // Buscar o crear registro de bitácora para la fecha
    $stmt = $db->prepare("
        SELECT id, imagen1, imagen2, imagen3 
        FROM bitacoras 
        WHERE nino_id = ? AND empresa_id = ? AND fecha = ? AND activo = 1
        LIMIT 1
    ");
    $stmt->execute([$ninoId, $empresaId, $fechaBitacora]);
    $bitacora = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bitacora) {
        // Actualizar registro existente
        $bitacoraId = $bitacora['id'];
        
        // Determinar qué campo de imagen usar
        $campoImagen = null;
        if (empty($bitacora['imagen1'])) {
            $campoImagen = 'imagen1';
        } elseif (empty($bitacora['imagen2'])) {
            $campoImagen = 'imagen2';
        } elseif (empty($bitacora['imagen3'])) {
            $campoImagen = 'imagen3';
        } else {
            // Eliminar archivo recién subido si ya hay 3 imágenes
            unlink($filePath);
            http_response_code(400);
            echo json_encode(['error' => 'Ya se han subido 3 imágenes para esta fecha. Máximo permitido alcanzado.']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE bitacoras 
            SET $campoImagen = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$fileName, $bitacoraId]);
        
    } else {
        // Crear nuevo registro de bitácora
        $stmt = $db->prepare("
            INSERT INTO bitacoras (
                nino_id, empresa_id, fecha, imagen1, 
                user_id, created_at, updated_at, activo
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1)
        ");
        $stmt->execute([$ninoId, $empresaId, $fechaBitacora, $fileName, $userId]);
        $bitacoraId = $db->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Imagen subida exitosamente',
        'data' => [
            'bitacora_id' => $bitacoraId,
            'imagen' => $fileName,
            'fecha' => $fechaBitacora,
            'campo_usado' => $campoImagen ?? 'imagen1'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
