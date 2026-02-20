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

include_once '../config/database.php';
include_once '../includes/functions.php';
include_once '../utils/JWTHandler.php';

// Verificar que el método sea GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, "Método no permitido", null, 405);
}

// Verificar JWT
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : 
              (isset($headers['authorization']) ? $headers['authorization'] : '');

if (empty($authHeader)) {
    sendResponse(false, "Token de autorización requerido", null, 401);
}

$token = str_replace('Bearer ', '', $authHeader);

try {
    $decoded = JWTHandler::verifyToken($token);
    $userId = $decoded['user_id'];
    $empresaId = $decoded['empresa_id'] ?? null;
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Contar mensajes únicos NO LEÍDOS para este usuario
    // Usando COUNT(DISTINCT mensaje_id) para ser consistente con el historial agrupado
    $query = "SELECT COUNT(DISTINCT n.mensaje_id) as unread_count
              FROM notificaciones n
              WHERE n.usuario_id = :user_id
              AND n.estado = 'enviado'";
    
    // Si hay empresa_id, filtrar también por empresa
    if ($empresaId) {
        $query .= " AND n.empresa_id = :empresa_id";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    if ($empresaId) {
        $stmt->bindParam(':empresa_id', $empresaId);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $unreadCount = (int)($result['unread_count'] ?? 0);
    
    // Obtener también la última notificación no leída
    $lastUnreadQuery = "SELECT MAX(fecha_envio) as last_unread_date
                        FROM notificaciones
                        WHERE usuario_id = :user_id
                        AND estado = 'enviado'";
    
    if ($empresaId) {
        $lastUnreadQuery .= " AND empresa_id = :empresa_id";
    }
    
    $lastStmt = $db->prepare($lastUnreadQuery);
    $lastStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    if ($empresaId) {
        $lastStmt->bindParam(':empresa_id', $empresaId);
    }
    
    $lastStmt->execute();
    $lastResult = $lastStmt->fetch(PDO::FETCH_ASSOC);
    
    sendResponse(true, "Contador de notificaciones obtenido", [
        'unread_count' => $unreadCount,
        'last_unread_date' => $lastResult['last_unread_date'],
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    sendResponse(false, "Error obteniendo contador: " . $e->getMessage(), null, 500);
}
?>
