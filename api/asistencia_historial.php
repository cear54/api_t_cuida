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

// Obtener parámetros de consulta
$ninoId = $_GET['nino_id'] ?? null;
$fecha = $_GET['fecha'] ?? null;
$fechaInicio = $_GET['fecha_inicio'] ?? null;
$fechaFin = $_GET['fecha_fin'] ?? null;
$excludeToday = $_GET['exclude_today'] ?? false;

// Log de depuración
error_log("📋 asistencia_historial.php - Parámetros recibidos:");
error_log("   nino_id: " . ($ninoId ?? 'null'));
error_log("   fecha: " . ($fecha ?? 'null'));
error_log("   fecha_inicio: " . ($fechaInicio ?? 'null'));
error_log("   fecha_fin: " . ($fechaFin ?? 'null'));
error_log("   exclude_today: " . ($excludeToday ? 'true' : 'false'));
error_log("   empresa_id: " . $empresaId);

// Si exclude_today es true, obtener todas las fechas excepto hoy
if ($excludeToday && !$fecha && !$fechaInicio && !$fechaFin) {
    $fechaFin = TimezoneHelper::getCurrentDate(); // Hasta hoy por defecto
    if ($fechaInicio) {
        // Si hay fecha de inicio, usar hasta ayer para el rango
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $fechaFin = $yesterday;
    }
    error_log("   exclude_today activado - fecha_fin establecida a: " . $fechaFin);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Query base - Simplificada solo para asistencias e información básica del niño
    $query = "SELECT a.*, 
                     n.nombre, n.apellido_paterno, n.apellido_materno, n.imagen,
                     s.nombre as salon_nombre
              FROM asistencias a
              INNER JOIN ninos n ON a.nino_id = n.id
              LEFT JOIN salones s ON n.salon_id = s.id
              WHERE a.empresa_id = :empresa_id AND n.empresa_id = :empresa_id AND a.activo = 1";

    $params = [':empresa_id' => $empresaId];

    // Filtros adicionales
    if ($ninoId) {
        $query .= " AND n.id = :nino_id";
        $params[':nino_id'] = $ninoId;
        error_log("   Filtro nino_id agregado: " . $ninoId);
    }

    if ($fechaInicio && $fechaFin) {
        $query .= " AND DATE(a.fecha) BETWEEN :fecha_inicio AND :fecha_fin";
        $params[':fecha_inicio'] = $fechaInicio;
        $params[':fecha_fin'] = $fechaFin;
        error_log("   Filtro fecha_inicio y fecha_fin agregado: " . $fechaInicio . " - " . $fechaFin);
    } elseif ($fechaFin && !$fechaInicio) {
        // Solo fecha_fin (para exclude_today)
        $query .= " AND DATE(a.fecha) <= :fecha_fin";
        $params[':fecha_fin'] = $fechaFin;
        error_log("   Filtro fecha_fin agregado: " . $fechaFin);
    } elseif ($fecha) {
        $query .= " AND DATE(a.fecha) = :fecha";
        $params[':fecha'] = $fecha;
        error_log("   Filtro fecha específica agregado: " . $fecha);
    }

    $query .= " ORDER BY a.fecha DESC, a.hora_entrada DESC";
    
    error_log("📋 Query SQL final: " . $query);
    error_log("📋 Parámetros SQL: " . json_encode($params));

    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();

    error_log("📋 Consulta ejecutada exitosamente");
    
    $asistencias = [];
    $contador = 0;
    while ($row = $stmt->fetch()) {
        $contador++;
        error_log("📋 Procesando registro $contador: " . json_encode([
            'id' => $row['id'],
            'fecha' => $row['fecha'],
            'hora_entrada' => $row['hora_entrada'],
            'hora_salida' => $row['hora_salida']
        ]));
        
        $asistencia = [
            'id' => $row['id'],
            'nino_id' => $row['nino_id'],
            'nino' => [
                'nombre_completo' => $row['nombre'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno'],
                'imagen' => $row['imagen']
            ],
            'salon' => $row['salon_nombre'],
            'fecha' => $row['fecha'],
            'hora_entrada' => $row['hora_entrada'],
            'hora_salida' => $row['hora_salida'],
            'observaciones_entrada' => $row['observaciones_entrada'],
            'observaciones_salida' => $row['observaciones_salida'],
            'temperatura' => $row['temperatura'],
            'se_presento_enfermo' => $row['se_presento_enfermo'],
            'descripcion_enfermedad' => $row['descripcion_enfermedad'],
            'se_presento_limpio' => $row['se_presento_limpio'],
            'trajo_mochila_completa' => $row['trajo_mochila_completa'],
            'se_presento_buen_estado_fisico' => $row['se_presento_buen_estado_fisico'],
            'persona_que_entrega' => $row['persona_que_entrega'],
            'persona_que_recibe' => null, // No existe en la tabla actual
            'fecha_registro' => $row['created_at'],
            'estado' => $row['hora_salida'] ? 'completo' : 'presente'
        ];

        $asistencias[] = $asistencia;
    }

    error_log("📋 Total de registros procesados: " . count($asistencias));

    // Obtener resumen si es consulta del día actual
    $resumen = null;
    if ($fecha === TimezoneHelper::getCurrentDate() && !$ninoId) {
        $resumenQuery = "SELECT 
                            COUNT(*) as total_ninos,
                            COUNT(CASE WHEN a.hora_entrada IS NOT NULL THEN 1 END) as presentes,
                            COUNT(CASE WHEN a.hora_salida IS NOT NULL THEN 1 END) as salidas_registradas
                         FROM ninos n
                         LEFT JOIN salones s ON n.salon_id = s.id
                         LEFT JOIN asistencias a ON n.id = a.nino_id AND DATE(a.fecha) = :fecha AND a.empresa_id = :empresa_id
                         WHERE n.empresa_id = :empresa_id AND n.activo = 1";
        
        $resumenStmt = $db->prepare($resumenQuery);
        $resumenStmt->bindParam(':fecha', $fecha);
        $resumenStmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
        $resumenStmt->execute();
        $resumen = $resumenStmt->fetch();
    }

    error_log("📋 Preparando respuesta final con " . count($asistencias) . " registros");
    
    $response = [
        'success' => true,
        'data' => $asistencias,
        'total' => count($asistencias)
    ];

    if ($resumen) {
        $response['resumen'] = [
            'total_ninos' => (int)$resumen['total_ninos'],
            'presentes' => (int)$resumen['presentes'],
            'ausentes' => (int)$resumen['total_ninos'] - (int)$resumen['presentes'],
            'salidas_registradas' => (int)$resumen['salidas_registradas']
        ];
    }

    error_log("📋 Respuesta final: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
