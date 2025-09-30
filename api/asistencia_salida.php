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
$observaciones = $input['observaciones'] ?? '';
$hora_salida = $input['hora_salida'] ?? date('H:i:s');

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

    // Buscar registro de entrada para hoy
    $today = date('Y-m-d');
    $findQuery = "SELECT id FROM asistencias 
                  WHERE nino_id = :nino_id 
                  AND empresa_id = :empresa_id 
                  AND DATE(fecha) = :fecha 
                  AND hora_entrada IS NOT NULL 
                  AND hora_salida IS NULL";
    $findStmt = $db->prepare($findQuery);
    $findStmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
    $findStmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_INT);
    $findStmt->bindParam(':fecha', $today);
    $findStmt->execute();

    if ($findStmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'No se encontró un registro de entrada para hoy o ya se registró la salida']);
        exit;
    }

    $asistencia = $findStmt->fetch();
    $asistenciaId = $asistencia['id'];

    // Actualizar registro con hora de salida
    $updateQuery = "UPDATE asistencias 
                    SET hora_salida = :hora_salida, 
                        observaciones_salida = :observaciones, 
                        educadora_salida_id = :educadora_salida_id,
                        updated_at = NOW()
                    WHERE id = :asistencia_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':hora_salida', $hora_salida);
    $updateStmt->bindParam(':observaciones', $observaciones);
    $updateStmt->bindParam(':educadora_salida_id', $personalId, PDO::PARAM_INT);
    $updateStmt->bindParam(':asistencia_id', $asistenciaId, PDO::PARAM_INT);

    if ($updateStmt->execute()) {
        // Obtener datos del niño para la respuesta
        $ninoQuery = "SELECT n.nombre, n.apellido_paterno, n.apellido_materno, s.nombre as salon_nombre,
                             a.hora_entrada, a.hora_salida
                      FROM ninos n 
                      INNER JOIN salones s ON n.salon_id = s.id 
                      INNER JOIN asistencias a ON n.id = a.nino_id
                      WHERE n.id = :nino_id AND a.id = :asistencia_id";
        $ninoStmt = $db->prepare($ninoQuery);
        $ninoStmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
        $ninoStmt->bindParam(':asistencia_id', $asistenciaId, PDO::PARAM_INT);
        $ninoStmt->execute();
        $nino = $ninoStmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => 'Salida registrada correctamente',
            'data' => [
                'asistencia_id' => $asistenciaId,
                'nino_id' => $ninoId,
                'nino_nombre' => $nino['nombre'] . ' ' . $nino['apellido_paterno'] . ' ' . $nino['apellido_materno'],
                'salon' => $nino['salon_nombre'],
                'fecha' => $today,
                'hora_entrada' => $nino['hora_entrada'],
                'hora_salida' => $nino['hora_salida'],
                'observaciones' => $observaciones
            ]
        ]);
    } else {
        throw new Exception('Error al actualizar el registro de asistencia');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
