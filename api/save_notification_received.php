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
    $decoded = JWTHandler::validateToken($token);
    $userId = $decoded['id'];
    $empresaId = $decoded['empresa_id'] ?? null;
    
    // Obtener datos del POST
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->firebase_message_id)) {
        sendResponse(false, "ID del mensaje Firebase es requerido", null, 400);
    }
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Verificar si el mensaje ya existe
    $checkQuery = "SELECT id FROM notificaciones WHERE firebase_message_id = :firebase_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(":firebase_id", $data->firebase_message_id);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        // El mensaje ya existe, actualizar estado si es necesario
        $updateQuery = "UPDATE notificaciones 
                       SET estado = :estado,
                           fecha_entrega = COALESCE(fecha_entrega, NOW()),
                           fecha_lectura = CASE WHEN :estado = 'leida' THEN NOW() ELSE fecha_lectura END
                       WHERE firebase_message_id = :firebase_id 
                       AND para_user_id = :user_id";
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(":estado", $data->estado);
        $updateStmt->bindParam(":firebase_id", $data->firebase_message_id);
        $updateStmt->bindParam(":user_id", $userId);
        
        if ($updateStmt->execute()) {
            sendResponse(true, "Estado de notificación actualizado", [
                "firebase_message_id" => $data->firebase_message_id,
                "nuevo_estado" => $data->estado,
                "action" => "updated"
            ]);
        } else {
            throw new Exception("Error actualizando estado de notificación");
        }
    } else {
        // El mensaje no existe, crear nuevo registro
        $insertQuery = "INSERT INTO notificaciones 
                       (titulo, mensaje, tipo, enviado_por, para_user_id, empresa_id, 
                        nino_id, datos_extra, estado, firebase_message_id, fecha_entrega,
                        dispositivo_tipo, version_app)
                       VALUES 
                       (:titulo, :mensaje, :tipo, :enviado_por, :para_user_id, :empresa_id,
                        :nino_id, :datos_extra, :estado, :firebase_id, NOW(),
                        :dispositivo_tipo, :version_app)";
        
        $insertStmt = $db->prepare($insertQuery);
        
        // Preparar datos
        $titulo = $data->titulo ?? 'Notificación';
        $mensaje = $data->mensaje ?? '';
        $tipo = $data->tipo ?? 'general';
        $enviadoPor = $data->enviado_por ?? $userId; // Por defecto, el mismo usuario
        $ninoId = $data->nino_id ?? null;
        $datosExtra = isset($data->datos_extra) ? json_encode($data->datos_extra) : null;
        $estado = $data->estado ?? 'entregada';
        $dispositivoTipo = $data->dispositivo_tipo ?? 'android';
        $versionApp = $data->version_app ?? '1.0.0';
        
        // Bind parameters
        $insertStmt->bindParam(":titulo", $titulo);
        $insertStmt->bindParam(":mensaje", $mensaje);
        $insertStmt->bindParam(":tipo", $tipo);
        $insertStmt->bindParam(":enviado_por", $enviadoPor);
        $insertStmt->bindParam(":para_user_id", $userId);
        $insertStmt->bindParam(":empresa_id", $empresaId);
        $insertStmt->bindParam(":nino_id", $ninoId);
        $insertStmt->bindParam(":datos_extra", $datosExtra);
        $insertStmt->bindParam(":estado", $estado);
        $insertStmt->bindParam(":firebase_id", $data->firebase_message_id);
        $insertStmt->bindParam(":dispositivo_tipo", $dispositivoTipo);
        $insertStmt->bindParam(":version_app", $versionApp);
        
        if ($insertStmt->execute()) {
            $notificationId = $db->lastInsertId();
            
            sendResponse(true, "Notificación guardada en historial", [
                "id" => $notificationId,
                "firebase_message_id" => $data->firebase_message_id,
                "estado" => $estado,
                "action" => "created"
            ]);
        } else {
            throw new Exception("Error guardando notificación en historial");
        }
    }
    
} catch (Exception $e) {
    sendResponse(false, "Error procesando notificación: " . $e->getMessage(), null, 500);
}
?>