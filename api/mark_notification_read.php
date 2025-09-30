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
    
    // Obtener datos del POST
    $data = json_decode(file_get_contents("php://input"));
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Determinar qué actualizar según los parámetros recibidos
    if (isset($data->notification_id)) {
        // Marcar notificación específica por ID
        $notificationId = $data->notification_id;
        
        $query = "UPDATE notificaciones 
                  SET estado = 'leida', 
                      fecha_lectura = NOW()
                  WHERE id = :notification_id 
                  AND (para_user_id = :user_id OR para_user_id IS NULL)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":notification_id", $notificationId);
        $stmt->bindParam(":user_id", $userId);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->rowCount();
            if ($affectedRows > 0) {
                sendResponse(true, "Notificación marcada como leída", [
                    "notification_id" => $notificationId,
                    "affected_rows" => $affectedRows,
                    "action" => "mark_read_by_id"
                ]);
            } else {
                sendResponse(false, "Notificación no encontrada o ya estaba leída", null, 404);
            }
        } else {
            throw new Exception("Error actualizando notificación");
        }
        
    } elseif (isset($data->firebase_message_id)) {
        // Marcar notificación por Firebase Message ID
        $firebaseMessageId = $data->firebase_message_id;
        
        $query = "UPDATE notificaciones 
                  SET estado = 'leida', 
                      fecha_lectura = NOW()
                  WHERE firebase_message_id = :firebase_id 
                  AND (para_user_id = :user_id OR para_user_id IS NULL)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":firebase_id", $firebaseMessageId);
        $stmt->bindParam(":user_id", $userId);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->rowCount();
            if ($affectedRows > 0) {
                sendResponse(true, "Notificación marcada como leída", [
                    "firebase_message_id" => $firebaseMessageId,
                    "affected_rows" => $affectedRows,
                    "action" => "mark_read_by_firebase_id"
                ]);
            } else {
                sendResponse(false, "Notificación no encontrada o ya estaba leída", null, 404);
            }
        } else {
            throw new Exception("Error actualizando notificación");
        }
        
    } elseif (isset($data->mark_all_as_read) && $data->mark_all_as_read === true) {
        // Marcar TODAS las notificaciones como leídas
        $query = "UPDATE notificaciones 
                  SET estado = 'leida', 
                      fecha_lectura = COALESCE(fecha_lectura, NOW())
                  WHERE (para_user_id = :user_id OR para_user_id IS NULL)
                  AND estado != 'leida'";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $userId);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->rowCount();
            sendResponse(true, "Todas las notificaciones marcadas como leídas", [
                "affected_rows" => $affectedRows,
                "action" => "mark_all_read"
            ]);
        } else {
            throw new Exception("Error marcando todas las notificaciones como leídas");
        }
        
    } elseif (isset($data->tipo)) {
        // Marcar todas las notificaciones de un tipo específico como leídas
        $tipo = $data->tipo;
        
        $query = "UPDATE notificaciones 
                  SET estado = 'leida', 
                      fecha_lectura = COALESCE(fecha_lectura, NOW())
                  WHERE (para_user_id = :user_id OR para_user_id IS NULL)
                  AND tipo = :tipo
                  AND estado != 'leida'";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $userId);
        $stmt->bindParam(":tipo", $tipo);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->rowCount();
            sendResponse(true, "Notificaciones del tipo '$tipo' marcadas como leídas", [
                "tipo" => $tipo,
                "affected_rows" => $affectedRows,
                "action" => "mark_read_by_type"
            ]);
        } else {
            throw new Exception("Error marcando notificaciones por tipo");
        }
        
    } else {
        sendResponse(false, "Parámetros inválidos. Envía 'notification_id', 'firebase_message_id', 'mark_all_as_read' o 'tipo'", null, 400);
    }
    
} catch (Exception $e) {
    sendResponse(false, "Error procesando solicitud: " . $e->getMessage(), null, 500);
}
?>