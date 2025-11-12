<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';
include_once '../includes/functions.php';
include_once '../utils/JWTHandler.php';

// Verificar que el método sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $userEmail = $decoded['usuario'] ?? null; // El email podría estar en 'usuario'
    
    // Obtener datos del POST
    $data = json_decode(file_get_contents("php://input"));
    
    // Validar que se recibieron los datos necesarios
    if (empty($data->current_password) || empty($data->new_password) || empty($data->confirm_password)) {
        sendResponse(false, "Todos los campos son requeridos", null, 400);
    }
    
    // Validar que las nuevas contraseñas coincidan
    if ($data->new_password !== $data->confirm_password) {
        sendResponse(false, "Las contraseñas nuevas no coinciden", null, 400);
    }
    
    // Validar longitud mínima de la nueva contraseña
    if (strlen($data->new_password) < 6) {
        sendResponse(false, "La nueva contraseña debe tener al menos 6 caracteres", null, 400);
    }
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Obtener usuario por ID
    $query = "SELECT id, email_usuario, password 
              FROM usuarios_app 
              WHERE id = :user_id AND activo = 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendResponse(false, "Usuario no encontrado", null, 404);
    }
    
    // Verificar contraseña actual
    if (!password_verify($data->current_password, $user['password'])) {
        sendResponse(false, "Contraseña actual incorrecta", null, 401);
    }
    
    // Verificar que la nueva contraseña sea diferente a la actual (opcional pero recomendado)
    if (password_verify($data->new_password, $user['password'])) {
        sendResponse(false, "La nueva contraseña debe ser diferente a la actual", null, 400);
    }
    
    // Generar hash de la nueva contraseña
    $newPasswordHash = password_hash($data->new_password, PASSWORD_DEFAULT);
    
    // Actualizar contraseña en la base de datos
    $updateQuery = "UPDATE usuarios_app 
                    SET password = :new_password_hash,
                        fecha_actualizacion = NOW()
                    WHERE id = :user_id AND activo = 1";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':new_password_hash', $newPasswordHash);
    $updateStmt->bindParam(':user_id', $userId);
    
    if ($updateStmt->execute()) {
        // Log de cambio de contraseña (opcional)
        $logMessage = "Contraseña actualizada para usuario ID: " . $userId . " - Email: " . $user['email_usuario'];
        error_log($logMessage);
        
        sendResponse(true, "Contraseña actualizada exitosamente", array(
            'user_id' => $userId,
            'email' => $user['email_usuario']
        ), 200);
    } else {
        sendResponse(false, "Error al actualizar la contraseña", null, 500);
    }
    
} catch (Exception $e) {
    sendResponse(false, "Error interno del servidor: " . $e->getMessage(), null, 500);
}
?>
