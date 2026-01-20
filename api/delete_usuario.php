<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';
include_once '../includes/functions.php';
include_once '../utils/JWTHandler.php';

// Verificar que el método sea DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    $currentUserId = $decoded['user_id'];
    $tipoUsuario = $decoded['tipo_usuario'] ?? 'familia';
    $empresaId = $decoded['empresa_id'] ?? null;
    
    // Solo administradores pueden eliminar usuarios
    if ($tipoUsuario !== 'administrador') {
        sendResponse(false, "Acceso no autorizado. Solo administradores pueden eliminar usuarios", null, 403);
    }
    
    // Obtener datos del POST
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if (!isset($data['usuario_id']) || empty($data['usuario_id'])) {
        sendResponse(false, "ID de usuario requerido", null, 400);
    }
    
    $usuarioIdAEliminar = (int)$data['usuario_id'];
    
    // Verificar que no se esté auto-eliminando
    if ($currentUserId == $usuarioIdAEliminar) {
        sendResponse(false, "No puedes eliminarte a ti mismo", null, 400);
    }
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Verificar que el usuario a eliminar existe y pertenece a la misma empresa
    $checkQuery = "SELECT id, nombre_usuario, tipo_usuario, empresa_id FROM usuarios_app 
                   WHERE id = :usuario_id AND empresa_id = :empresa_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':usuario_id', $usuarioIdAEliminar);
    $checkStmt->bindParam(':empresa_id', $empresaId);
    $checkStmt->execute();
    
    $usuarioAEliminar = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuarioAEliminar) {
        sendResponse(false, "Usuario no encontrado o no pertenece a tu empresa", null, 404);
    }
    
    // No permitir eliminar otros administradores (opcional, por seguridad)
    if ($usuarioAEliminar['tipo_usuario'] === 'administrador') {
        sendResponse(false, "No se puede eliminar otro administrador", null, 400);
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Eliminar usuario de la tabla usuarios_app
        $deleteQuery = "DELETE FROM usuarios_app WHERE id = :usuario_id AND empresa_id = :empresa_id";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':usuario_id', $usuarioIdAEliminar);
        $deleteStmt->bindParam(':empresa_id', $empresaId);
        $deleteStmt->execute();
        
        $filasAfectadas = $deleteStmt->rowCount();
        
        if ($filasAfectadas === 0) {
            throw new Exception("No se pudo eliminar el usuario");
        }
        
        // Confirmar transacción
        $db->commit();
        
        sendResponse(true, "Usuario eliminado exitosamente", [
            "usuario_eliminado" => [
                "id" => $usuarioAEliminar['id'],
                "nombre" => $usuarioAEliminar['nombre_usuario'],
                "tipo" => $usuarioAEliminar['tipo_usuario']
            ],
            "eliminado_por" => $currentUserId,
            "fecha_eliminacion" => TimezoneHelper::getCurrentTimestamp()
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    sendResponse(false, "Error eliminando usuario: " . $e->getMessage(), null, 500);
}
?>