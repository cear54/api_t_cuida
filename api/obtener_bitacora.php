<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Verificar que el método sea GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Incluir archivos necesarios
include_once '../config/database.php';
include_once '../utils/JWTHandler.php';

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
    $jwtHandler = new JWTHandler();
    $payload = $jwtHandler->verifyToken($token);
    
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }
    
    $userId = $payload['user_id'];
    $personalId = $payload['personal_id'];
    $empresaId = $payload['empresa_id'];
    
    if (!$empresaId) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no contiene información de empresa']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

// Obtener parámetros de consulta
$ninoId = $_GET['nino_id'] ?? null;
$fecha = $_GET['fecha'] ?? date('Y-m-d'); // Por defecto hoy

if (!$ninoId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID del niño requerido']);
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

    // Obtener bitácora del día especificado
    $query = "SELECT b.*,
                     n.nombre as nino_nombre,
                     n.apellido_paterno as nino_apellido_paterno,
                     n.apellido_materno as nino_apellido_materno,
                     p.nombre as educadora_nombre,
                     p.apellido_paterno as educadora_apellido_paterno
              FROM bitacoras b
              INNER JOIN ninos n ON b.nino_id = n.id
              LEFT JOIN personal p ON b.educadora_id = p.id
              WHERE b.nino_id = :nino_id 
              AND b.empresa_id = :empresa_id 
              AND DATE(b.fecha) = :fecha";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
    $stmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $stmt->bindParam(':fecha', $fecha);
    $stmt->execute();

    $bitacora = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bitacora) {
        // Formatear la respuesta
        $response = [
            'success' => true,
            'data' => [
                'id' => $bitacora['id'],
                'nino_id' => $bitacora['nino_id'],
                'nino_nombre' => $bitacora['nino_nombre'] . ' ' . $bitacora['nino_apellido_paterno'] . ' ' . $bitacora['nino_apellido_materno'],
                'fecha' => $bitacora['fecha'],
                'alimentacion' => [
                    'desayuno' => $bitacora['desayuno'],
                    'colacion' => $bitacora['colacion'],
                    'comida' => $bitacora['comida']
                ],
                'sueno' => [
                    'sueno_descanso' => $bitacora['sueno_descanso'],
                    'tiempo_siesta' => $bitacora['tiempo_siesta']
                ],
                'bano' => [
                    'pipi' => $bitacora['pipi'],
                    'numero_veces_pipi' => $bitacora['numero_veces_pipi'],
                    'popo' => $bitacora['popo'],
                    'numero_veces_popo' => $bitacora['numero_veces_popo']
                ],
                'aviso' => [
                    'aviso_pipi_popo' => $bitacora['aviso_pipi_popo'],
                    'cuando_aviso' => $bitacora['cuando_aviso'],
                    'cuantas_veces_aviso' => $bitacora['cuantas_veces_aviso']
                ],
                'estado_animo' => $bitacora['estado_animo'],
                'incidentes' => [
                    'tuvo_accidente' => (bool)$bitacora['tuvo_accidente'],
                    'descripcion_accidente' => $bitacora['descripcion_accidente'],
                    'problema_salud' => (bool)$bitacora['problema_salud'],
                    'descripcion_salud' => $bitacora['descripcion_salud']
                ],
                'imagenes' => [
                    'imagen1' => $bitacora['imagen1'],
                    'imagen2' => $bitacora['imagen2'],
                    'imagen3' => $bitacora['imagen3']
                ],
                'educadora' => $bitacora['educadora_nombre'] ? 
                    $bitacora['educadora_nombre'] . ' ' . $bitacora['educadora_apellido_paterno'] : null,
                'created_at' => $bitacora['created_at'],
                'updated_at' => $bitacora['updated_at']
            ]
        ];
    } else {
        $response = [
            'success' => true,
            'data' => null,
            'message' => 'No hay bitácora registrada para esta fecha'
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
