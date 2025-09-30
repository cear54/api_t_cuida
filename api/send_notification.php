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

// Verificar que el método sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    // Obtener datos del POST
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->title) || empty($data->body)) {
        http_response_code(400);
        echo json_encode(['error' => 'Título y mensaje son requeridos']);
        exit;
    }
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Obtener destinatarios
    $recipients = [];
    
    if (isset($data->user_id)) {
        // Enviar a usuario específico
        $query = "SELECT token_app FROM usuarios_app WHERE id = :user_id AND token_app IS NOT NULL";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $data->user_id);
        $stmt->execute();
        $token = $stmt->fetchColumn();
        if ($token) {
            $recipients[] = $token;
        }
    } elseif (isset($data->tipo_usuario)) {
        // Enviar a todos los usuarios de un tipo
        $query = "SELECT token_app FROM usuarios_app WHERE tipo_usuario = :tipo_usuario AND token_app IS NOT NULL AND activo = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tipo_usuario", $data->tipo_usuario);
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Enviar a todos los usuarios activos
        $query = "SELECT token_app FROM usuarios_app WHERE token_app IS NOT NULL AND activo = 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    if (empty($recipients)) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontraron destinatarios con tokens válidos'
        ]);
        exit;
    }
    
    // Preparar mensaje FCM
    $notification = [
        'title' => $data->title,
        'body' => $data->body,
        'icon' => $data->icon ?? '/assets/icon/icon.png',
        'click_action' => $data->click_action ?? 'FLUTTER_NOTIFICATION_CLICK'
    ];
    
    $messageData = [
        'data' => $data->data ?? [],
        'notification' => $notification
    ];
    
    // Aquí necesitarías la Server Key de Firebase
    // Por seguridad, debería estar en variables de entorno
    $serverKey = 'AAAAL0oUg5di1pOWmWrp3cGF37VIc8kvaqJSwfXMq9tKDGQ'; // Clave Firebase FCM
    
    $successCount = 0;
    $failureCount = 0;
    $results = [];
    
    foreach ($recipients as $fcmToken) {
        $message = $messageData;
        $message['to'] = $fcmToken;
        
        $result = sendFCMNotification($message, $serverKey);
        
        if ($result['success']) {
            $successCount++;
        } else {
            $failureCount++;
        }
        
        $results[] = [
            'token' => substr($fcmToken, 0, 20) . '...', // Token parcial por seguridad
            'success' => $result['success'],
            'error' => $result['error'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notificaciones procesadas',
        'summary' => [
            'total_sent' => count($recipients),
            'successful' => $successCount,
            'failed' => $failureCount
        ],
        'details' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}

function sendFCMNotification($message, $serverKey) {
    $url = 'https://fcm.googleapis.com/fcm/send';
    
    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $responseData = json_decode($response, true);
        return [
            'success' => isset($responseData['success']) ? $responseData['success'] : true,
            'response' => $responseData
        ];
    } else {
        return [
            'success' => false,
            'error' => 'HTTP Error: ' . $httpCode,
            'response' => $response
        ];
    }
}
?>
