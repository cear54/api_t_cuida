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

// Verificar token JWT
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Token de autorización requerido']);
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
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Parámetros de consulta
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
    $estado = isset($_GET['estado']) ? $_GET['estado'] : null;
    $fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : null;
    $fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : null;
    
    // Construir consulta base
    $baseQuery = "SELECT n.*, u.nombre_usuario as usuario_nombre, u.email_usuario as usuario_email 
                  FROM notificaciones n 
                  LEFT JOIN usuarios_app u ON n.usuario_id = u.id";
    
    $conditions = [];
    $params = [];
    
    // Agregar filtros
    if ($tipo) {
        $conditions[] = "n.tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    
    if ($estado) {
        $conditions[] = "n.estado = :estado";
        $params[':estado'] = $estado;
    }
    
    if ($fecha_desde) {
        $conditions[] = "n.fecha_envio >= :fecha_desde";
        $params[':fecha_desde'] = $fecha_desde;
    }
    
    if ($fecha_hasta) {
        $conditions[] = "n.fecha_envio <= :fecha_hasta";
        $params[':fecha_hasta'] = $fecha_hasta;
    }
    
    // Construir consulta completa
    $whereClause = "";
    if (!empty($conditions)) {
        $whereClause = " WHERE " . implode(" AND ", $conditions);
    }
    
    // Consulta para contar total
    $countQuery = "SELECT COUNT(*) as total FROM notificaciones n" . $whereClause;
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Consulta principal con paginación (LIMIT y OFFSET no se pueden bindear como parámetros)
    $mainQuery = $baseQuery . $whereClause . " ORDER BY n.fecha_envio DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($mainQuery);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar datos adicionales (convertir JSON a objeto si existe)
    foreach ($notifications as &$notification) {
        if ($notification['datos_adicionales']) {
            $notification['datos_adicionales'] = json_decode($notification['datos_adicionales'], true);
        }
    }
    
    // Obtener estadísticas rápidas
    $statsQuery = "SELECT 
                    COUNT(*) as total_notificaciones,
                    SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviadas,
                    SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregadas,
                    SUM(CASE WHEN estado = 'leido' THEN 1 ELSE 0 END) as leidas,
                    SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as errores
                   FROM notificaciones" . $whereClause;
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute(array_filter($params, function($key) {
        return !in_array($key, [':limit', ':offset']);
    }, ARRAY_FILTER_USE_KEY));
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ],
        'statistics' => $stats,
        'filters_applied' => [
            'tipo' => $tipo,
            'estado' => $estado,
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>