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
    
    // Estadísticas generales
    $generalStats = $db->query("
        SELECT 
            COUNT(*) as total_notificaciones,
            COUNT(DISTINCT mensaje_id) as total_mensajes,
            COUNT(DISTINCT usuario_id) as usuarios_notificados,
            SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviadas,
            SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregadas,
            SUM(CASE WHEN estado = 'leido' THEN 1 ELSE 0 END) as leidas,
            SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as errores
        FROM notificaciones
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Estadísticas por tipo
    $statsByType = $db->query("
        SELECT 
            tipo,
            COUNT(*) as cantidad,
            SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviadas,
            SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregadas,
            SUM(CASE WHEN estado = 'leido' THEN 1 ELSE 0 END) as leidas,
            SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as errores
        FROM notificaciones 
        GROUP BY tipo
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas por prioridad
    $statsByPriority = $db->query("
        SELECT 
            prioridad,
            COUNT(*) as cantidad,
            SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviadas,
            SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregadas,
            SUM(CASE WHEN estado = 'leido' THEN 1 ELSE 0 END) as leidas,
            SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as errores
        FROM notificaciones 
        GROUP BY prioridad
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Últimos 7 días de actividad
    $last7Days = $db->query("
        SELECT 
            DATE(fecha_envio) as fecha,
            COUNT(*) as total_notificaciones,
            COUNT(DISTINCT mensaje_id) as total_mensajes,
            SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviadas,
            SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregadas,
            SUM(CASE WHEN estado = 'leido' THEN 1 ELSE 0 END) as leidas,
            SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as errores
        FROM notificaciones 
        WHERE fecha_envio >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(fecha_envio)
        ORDER BY fecha DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Usuarios más notificados
    $topUsers = $db->query("
        SELECT 
            u.nombre_usuario as nombre,
            u.email_usuario as email,
            u.tipo_usuario,
            COUNT(*) as total_notificaciones,
            SUM(CASE WHEN n.estado = 'leido' THEN 1 ELSE 0 END) as leidas,
            MAX(n.fecha_envio) as ultima_notificacion
        FROM notificaciones n
        JOIN usuarios_app u ON n.usuario_id = u.id
        GROUP BY n.usuario_id, u.nombre_usuario, u.email_usuario, u.tipo_usuario
        ORDER BY total_notificaciones DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Mensajes recientes (últimos 10 mensajes únicos)
    $recentMessages = $db->query("
        SELECT 
            mensaje_id,
            titulo,
            mensaje,
            tipo,
            prioridad,
            fecha_envio,
            COUNT(*) as destinatarios,
            SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviadas,
            SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregadas,
            SUM(CASE WHEN estado = 'leido' THEN 1 ELSE 0 END) as leidas,
            SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) as errores
        FROM notificaciones
        GROUP BY mensaje_id, titulo, mensaje, tipo, prioridad, fecha_envio
        ORDER BY fecha_envio DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular tasas de éxito
    $totalNotifications = (int)$generalStats['total_notificaciones'];
    $successRate = $totalNotifications > 0 ? 
        round((((int)$generalStats['enviadas'] + (int)$generalStats['entregadas'] + (int)$generalStats['leidas']) / $totalNotifications) * 100, 2) : 0;
    
    $deliveryRate = $totalNotifications > 0 ? 
        round((((int)$generalStats['entregadas'] + (int)$generalStats['leidas']) / $totalNotifications) * 100, 2) : 0;
    
    $readRate = $totalNotifications > 0 ? 
        round(((int)$generalStats['leidas'] / $totalNotifications) * 100, 2) : 0;
    
    echo json_encode([
        'success' => true,
        'general_stats' => $generalStats,
        'rates' => [
            'success_rate' => $successRate,
            'delivery_rate' => $deliveryRate,
            'read_rate' => $readRate
        ],
        'stats_by_type' => $statsByType,
        'stats_by_priority' => $statsByPriority,
        'last_7_days' => $last7Days,
        'top_users' => $topUsers,
        'recent_messages' => $recentMessages,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>