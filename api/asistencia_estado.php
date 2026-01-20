<?php
require_once '../config/database.php';
require_once '../utils/JWTHandler.php';
require_once '../includes/timezone_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    $empresaId = $payload['empresa_id']; // Obtener empresa_id del token
    
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

$fecha = $_GET['fecha'] ?? TimezoneHelper::getCurrentDate();

try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener todos los niños del grupo con su estado de asistencia
    $query = "SELECT 
                n.id,
                n.nombre,
                n.apellido_paterno,
                n.apellido_materno,
                n.imagen,
                n.genero,
                s.nombre as salon_nombre,
                a.id as asistencia_id,
                a.hora_entrada,
                a.hora_salida,
                a.observaciones_entrada,
                a.observaciones_salida,
                CASE 
                    WHEN a.hora_entrada IS NULL THEN 'ausente'
                    WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NULL THEN 'presente'
                    WHEN a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL THEN 'completo'
                END as estado
              FROM ninos n
              LEFT JOIN salones s ON n.salon_id = s.id
              LEFT JOIN asistencias a ON n.id = a.nino_id AND DATE(a.fecha) = :fecha AND a.empresa_id = :empresa_id
              WHERE n.empresa_id = :empresa_id AND n.activo = 1
              ORDER BY n.nombre, n.apellido_paterno";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':fecha', $fecha);
    $stmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $stmt->execute();

    $ninos = [];
    $resumen = [
        'total' => 0,
        'presentes' => 0,
        'ausentes' => 0,
        'salidas_registradas' => 0
    ];

    while ($row = $stmt->fetch()) {
        $nino = [
            'id' => $row['id'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno'],
            'imagen' => $row['imagen'],
            'genero' => $row['genero'],
            'salon' => $row['salon_nombre'],
            'estado' => $row['estado'],
            'asistencia' => null
        ];

        if ($row['asistencia_id']) {
            $nino['asistencia'] = [
                'id' => $row['asistencia_id'],
                'hora_entrada' => $row['hora_entrada'],
                'hora_salida' => $row['hora_salida'],
                'observaciones_entrada' => $row['observaciones_entrada'],
                'observaciones_salida' => $row['observaciones_salida']
            ];
        }

        $ninos[] = $nino;

        // Actualizar resumen
        $resumen['total']++;
        switch ($row['estado']) {
            case 'presente':
            case 'completo':
                $resumen['presentes']++;
                break;
            case 'ausente':
                $resumen['ausentes']++;
                break;
        }
        
        if ($row['estado'] === 'completo') {
            $resumen['salidas_registradas']++;
        }
    }

    echo json_encode([
        'success' => true,
        'fecha' => $fecha,
        'ninos' => $ninos,
        'resumen' => $resumen
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
