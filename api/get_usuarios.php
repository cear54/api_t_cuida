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
    $tipoUsuario = $decoded['tipo_usuario'] ?? 'familia';
    $empresaId = $decoded['empresa_id'] ?? null;
    
    // Solo administradores pueden ver la lista de usuarios
    if ($tipoUsuario !== 'administrador') {
        sendResponse(false, "Acceso no autorizado", null, 403);
    }
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Consulta principal para obtener usuarios con información de niños si es familia
    $query = "SELECT 
                u.id,
                u.nombre_usuario,
                u.email_usuario,
                u.tipo_usuario,
                u.activo,
                u.fecha_creacion,
                u.empresa_id,
                u.nino_id,
                CASE 
                    WHEN u.tipo_usuario = 'familia' AND u.nino_id IS NOT NULL THEN (
                        SELECT CONCAT(n.nombre, ' ', n.apellido_paterno, ' ', COALESCE(n.apellido_materno, ''))
                        FROM ninos n 
                        WHERE n.id = u.nino_id AND n.activo = 1
                    )
                    ELSE NULL
                END as nino_asociado
              FROM usuarios_app u
              WHERE u.empresa_id = :empresa_id
              ORDER BY 
                CASE u.tipo_usuario
                    WHEN 'administrador' THEN 1
                    WHEN 'educador' THEN 2
                    WHEN 'academico' THEN 3
                    WHEN 'familia' THEN 4
                    ELSE 5
                END,
                u.nombre_usuario ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':empresa_id', $empresaId);
    $stmt->execute();
    
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas por tipo de usuario
    $statsQuery = "SELECT 
                    tipo_usuario,
                    COUNT(*) as cantidad,
                    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos
                   FROM usuarios_app 
                   WHERE empresa_id = :empresa_id
                   GROUP BY tipo_usuario";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(':empresa_id', $empresaId);
    $statsStmt->execute();
    $estadisticas = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de usuarios
    $totalQuery = "SELECT COUNT(*) as total FROM usuarios_app WHERE empresa_id = :empresa_id";
    $totalStmt = $db->prepare($totalQuery);
    $totalStmt->bindParam(':empresa_id', $empresaId);
    $totalStmt->execute();
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    sendResponse(true, "Lista de usuarios obtenida correctamente", [
        "usuarios" => $usuarios,
        "total" => (int)$total,
        "estadisticas" => $estadisticas
    ]);
    
} catch (Exception $e) {
    sendResponse(false, "Error obteniendo usuarios: " . $e->getMessage(), null, 500);
}
?>