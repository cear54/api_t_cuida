<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar token JWT
require_once __DIR__ . '/../utils/JWTHandler.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/timezone_helper.php';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token de autorización requerido']);
    exit;
}

$token = substr($authHeader, 7);

try {
    $jwtHandler = new JWTHandler();
    $payload = $jwtHandler->verifyToken($token);
    
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido o expirado']);
        exit;
    }
    
    $userId = $payload['user_id'];
    $personalId = $payload['personal_id']; // ID del personal/educadora
    $empresaId = $payload['empresa_id']; // Obtener empresa_id del token
    
    if (!$empresaId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token no contiene información de empresa']);
        exit;
    }
    
    if (!$personalId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token no contiene información de personal']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

// Leer datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
    exit;
}

// Validar campos requeridos
$requiredFields = ['nino_id', 'hora_salida', 'quien_recoge', 'entregado_limpio', 'entregado_con_pertenencias'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Campo requerido faltante: $field"]);
        exit;
    }
}

$ninoId = (int)$data['nino_id'];
$fecha = $data['fecha'] ?? TimezoneHelper::getCurrentDate(); // Usar fecha del cliente o fecha del servidor como fallback
$horaSalida = trim($data['hora_salida']);
$quienRecoge = trim($data['quien_recoge']);
$entregadoLimpio = (bool)$data['entregado_limpio'];
$entregadoConPertenencias = (bool)$data['entregado_con_pertenencias'];

// Validar formato de fecha si viene del cliente
if (isset($data['fecha'])) {
    if (!TimezoneHelper::validateDateFormat($fecha)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
        exit;
    }
}

// Validaciones básicas
if (empty($horaSalida)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Hora de salida es requerida']);
    exit;
}

if (empty($quienRecoge)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Persona que recoge es requerida']);
    exit;
}

// Validar formato de hora (HH:MM)
if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $horaSalida)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de hora inválido. Use HH:MM']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar que el niño existe y pertenece a la empresa
    $stmt = $pdo->prepare("
        SELECT id, CONCAT(nombre, ' ', apellido_paterno, ' ', COALESCE(apellido_materno, '')) as nombre_completo 
        FROM ninos 
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->execute([$ninoId, $empresaId]);
    $nino = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nino) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Niño no encontrado']);
        exit;
    }
    
    // Verificar que existe asistencia para la fecha especificada y no hay salida registrada
    $stmt = $pdo->prepare("
        SELECT id, hora_entrada, hora_salida 
        FROM asistencias 
        WHERE nino_id = ? AND empresa_id = ? AND fecha = ?
    ");
    $stmt->execute([$ninoId, $empresaId, $fecha]);
    $asistencia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$asistencia) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'No hay registro de asistencia para la fecha especificada. El niño debe tener asistencia registrada antes de poder registrar la salida.'
        ]);
        exit;
    }
    
    if (!empty($asistencia['hora_salida'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Ya existe un registro de salida para este niño en la fecha especificada.'
        ]);
        exit;
    }
    
    // Verificar que existe bitácora para la fecha especificada
    $stmt = $pdo->prepare("
        SELECT id 
        FROM bitacoras 
        WHERE nino_id = ? AND empresa_id = ? AND fecha = ?
    ");
    $stmt->execute([$ninoId, $empresaId, $fecha]);
    $bitacora = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bitacora) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'No se puede registrar la salida. El niño debe tener bitácora del día registrada antes de poder salir.'
        ]);
        exit;
    }
    
    // Verificar que no existe ya una salida registrada en la tabla salidas
    $stmt = $pdo->prepare("
        SELECT id FROM salidas 
        WHERE nino_id = ? AND fecha = ?
    ");
    $stmt->execute([$ninoId, $fecha]);
    $salidaExistente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($salidaExistente) {
        http_response_code(409);
        echo json_encode([
            'success' => false, 
            'message' => 'Ya existe un registro de salida para este niño hoy.'
        ]);
        exit;
    }
    
    // Insertar nuevo registro en la tabla salidas
    $stmt = $pdo->prepare("
        INSERT INTO salidas (
            nino_id, 
            empresa_id, 
            fecha, 
            hora_salida, 
            quien_recoge, 
            entregado_limpio, 
            entregado_con_pertenencias
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insertData = [
        $ninoId,
        $empresaId,
        $fecha,
        $horaSalida,
        $quienRecoge,
        (int)$entregadoLimpio,
        (int)$entregadoConPertenencias
    ];
    
    $result = $stmt->execute($insertData);
    
    if ($result) {
        $salidaId = $pdo->lastInsertId();
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Salida registrada exitosamente',
            'data' => [
                'id' => $salidaId,
                'nino_id' => $ninoId,
                'nino_nombre' => $nino['nombre_completo'],
                'hora_salida' => $horaSalida,
                'quien_recoge' => $quienRecoge,
                'entregado_limpio' => $entregadoLimpio,
                'entregado_con_pertenencias' => $entregadoConPertenencias,
                'fecha' => $fecha
            ]
        ]);
    } else {
        throw new Exception('Error al actualizar el registro de asistencia');
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>
