<?php
require_once '../config/database.php';
require_once '../utils/JWTHandler.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar token JWT
$headers = getallheaders();
if (!$headers) {
    $headers = [];
}

// Buscar el header Authorization en diferentes formas
$authHeader = '';
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
    $jwtHandler = new JWTHandler();
    $payload = $jwtHandler->verifyToken($token);
    
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }
    
    $userId = $payload['user_id'];
    $personalId = $payload['personal_id']; // ID del personal/educadora
    $empresaId = $payload['empresa_id']; // Obtener empresa_id del token
    
    if (!$empresaId) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no contiene información de empresa']);
        exit;
    }
    
    if (!$personalId) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no contiene información de personal']);
        exit;
    }
    
    // Validar suscripción de la empresa
    require_once '../config/database.php';
    require_once '../middleware/subscription_validator.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $subscriptionStatus = SubscriptionValidator::validateSubscription($db, $empresaId);
    
    if (!$subscriptionStatus['valid']) {
        http_response_code($subscriptionStatus['code']);
        echo json_encode([
            'success' => false,
            'message' => $subscriptionStatus['message']
        ]);
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

// Obtener datos del cuerpo de la petición
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['nino_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID del niño requerido']);
    exit;
}

$ninoId = $input['nino_id'];
$fecha = $input['fecha'] ?? date('Y-m-d'); // Usar fecha del cliente o fecha del servidor como fallback
$horaEntrada = $input['hora_entrada'] ?? null;
$temperatura = $input['temperatura'] ?? null;
$sePresentoEnfermo = isset($input['se_presento_enfermo']) ? (bool)$input['se_presento_enfermo'] : false;
$descripcionEnfermedad = $input['descripcion_enfermedad'] ?? '';
$sePresentoLimpio = isset($input['se_presento_limpio']) ? (bool)$input['se_presento_limpio'] : true;
$trajoMochilaCompleta = isset($input['trajo_mochila_completa']) ? (bool)$input['trajo_mochila_completa'] : true;
$sePresentoBuenEstadoFisico = isset($input['se_presento_buen_estado_fisico']) ? (bool)$input['se_presento_buen_estado_fisico'] : true;
$personaQueEntrega = $input['persona_que_entrega'] ?? null;

// Validar formato de fecha si viene del cliente
if (isset($input['fecha'])) {
    $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
        exit;
    }
}

// Validaciones
if (!$horaEntrada) {
    http_response_code(400);
    echo json_encode(['error' => 'Hora de entrada requerida']);
    exit;
}

if (!$temperatura) {
    http_response_code(400);
    echo json_encode(['error' => 'Temperatura requerida']);
    exit;
}

// Validar temperatura en rango válido
$tempFloat = floatval($temperatura);
if ($tempFloat < 30 || $tempFloat > 45) {
    http_response_code(400);
    echo json_encode(['error' => 'La temperatura debe estar entre 30°C y 45°C']);
    exit;
}

// Si está enfermo, la descripción es obligatoria
if ($sePresentoEnfermo && empty(trim($descripcionEnfermedad))) {
    http_response_code(400);
    echo json_encode(['error' => 'Descripción de enfermedad requerida cuando el niño se presenta enfermo']);
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

    // Verificar si ya existe una entrada para la fecha especificada
    $existingQuery = "SELECT id FROM asistencias 
                      WHERE nino_id = :nino_id 
                      AND empresa_id = :empresa_id 
                      AND DATE(fecha) = :fecha 
                      AND hora_entrada IS NOT NULL";
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
    $existingStmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_INT);
    $existingStmt->bindParam(':fecha', $fecha);
    $existingStmt->execute();

    if ($existingStmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Ya se registró una entrada para este niño en la fecha especificada']);
        exit;
    }

    // Insertar registro de asistencia completo
    $insertQuery = "INSERT INTO asistencias (
                        nino_id, 
                        empresa_id, 
                        fecha, 
                        hora_entrada, 
                        temperatura,
                        se_presento_enfermo,
                        descripcion_enfermedad,
                        se_presento_limpio,
                        trajo_mochila_completa,
                        se_presento_buen_estado_fisico,
                        persona_que_entrega,
                        educadora_entrada_id, 
                        created_at
                    ) VALUES (
                        :nino_id, 
                        :empresa_id, 
                        :fecha, 
                        :hora_entrada, 
                        :temperatura,
                        :se_presento_enfermo,
                        :descripcion_enfermedad,
                        :se_presento_limpio,
                        :trajo_mochila_completa,
                        :se_presento_buen_estado_fisico,
                        :persona_que_entrega,
                        :educadora_entrada_id, 
                        NOW()
                    )";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
    $insertStmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $insertStmt->bindParam(':fecha', $fecha);
    $insertStmt->bindParam(':hora_entrada', $horaEntrada);
    $insertStmt->bindParam(':temperatura', $temperatura);
    $insertStmt->bindParam(':se_presento_enfermo', $sePresentoEnfermo, PDO::PARAM_BOOL);
    $insertStmt->bindParam(':descripcion_enfermedad', $descripcionEnfermedad);
    $insertStmt->bindParam(':se_presento_limpio', $sePresentoLimpio, PDO::PARAM_BOOL);
    $insertStmt->bindParam(':trajo_mochila_completa', $trajoMochilaCompleta, PDO::PARAM_BOOL);
    $insertStmt->bindParam(':se_presento_buen_estado_fisico', $sePresentoBuenEstadoFisico, PDO::PARAM_BOOL);
    $insertStmt->bindParam(':persona_que_entrega', $personaQueEntrega);
    $insertStmt->bindParam(':educadora_entrada_id', $personalId, PDO::PARAM_INT);

    if ($insertStmt->execute()) {
        $asistenciaId = $db->lastInsertId();
        
        // Obtener datos del niño para la respuesta
        $ninoQuery = "SELECT n.nombre, n.apellido_paterno, n.apellido_materno, s.nombre as salon_nombre
                      FROM ninos n 
                      INNER JOIN salones s ON n.salon_id = s.id 
                      WHERE n.id = :nino_id";
        $ninoStmt = $db->prepare($ninoQuery);
        $ninoStmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
        $ninoStmt->execute();
        $nino = $ninoStmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => 'Asistencia registrada correctamente',
            'data' => [
                'asistencia_id' => $asistenciaId,
                'nino_id' => $ninoId,
                'nino_nombre' => $nino['nombre'] . ' ' . $nino['apellido_paterno'] . ' ' . $nino['apellido_materno'],
                'salon' => $nino['salon_nombre'],
                'fecha' => $fecha,
                'hora_entrada' => $horaEntrada,
                'temperatura' => $temperatura,
                'se_presento_enfermo' => $sePresentoEnfermo,
                'descripcion_enfermedad' => $descripcionEnfermedad,
                'se_presento_limpio' => $sePresentoLimpio,
                'trajo_mochila_completa' => $trajoMochilaCompleta,
                'se_presento_buen_estado_fisico' => $sePresentoBuenEstadoFisico
            ]
        ]);
    } else {
        throw new Exception('Error al insertar el registro de asistencia');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
