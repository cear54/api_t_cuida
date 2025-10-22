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
include_once '../config/FirebaseAPIv1.php';

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
        // Enviar según tipo de usuario
        if ($data->tipo_usuario === 'educador') {
            // EDUCADOR = todos los usuarios MENOS familia
            $query = "SELECT token_app FROM usuarios_app WHERE tipo_usuario != 'familia' AND token_app IS NOT NULL AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // Otros tipos mantienen lógica original
            $query = "SELECT token_app FROM usuarios_app WHERE tipo_usuario = :tipo_usuario AND token_app IS NOT NULL AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tipo_usuario", $data->tipo_usuario);
            $stmt->execute();
            $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
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
    
    // Usar Firebase API v1
    // FirebaseAPIv1 ahora toma credenciales desde variables de entorno o desde
    // FIREBASE_SERVICE_ACCOUNT_BASE64 (recomendado). Asegúrate de configurar .env o
    // variables de entorno en producción en lugar de subir el JSON del service account.
    $firebase = new FirebaseAPIv1();
    
    // Preparar datos adicionales (todos los valores deben ser strings)
    $messageData = [];
    if (isset($data->data) && is_array($data->data)) {
        foreach ($data->data as $key => $value) {
            $messageData[$key] = (string)$value;
        }
    }
    $messageData['timestamp'] = (string)time();
    $messageData['sender'] = 'admin';
    $defaultPublicIcon = 'https://www.cear54.com/acceso_publico/icono_b.png';

    // Función para comprobar accesibilidad de una URL (HEAD con timeout corto)
    function is_url_accessible($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch);
        curl_close($ch);
        return ($err === 0 && $httpCode >= 200 && $httpCode < 400);
    }

    // Determinar qué icon usar: primero $data->icon si existe y es accesible,
    // si no, intentar el icono público por defecto; si tampoco responde, dejar NULL
    $requestedIcon = isset($data->icon) ? trim($data->icon) : null;
    $iconToUse = null;

    if (!empty($requestedIcon) && is_url_accessible($requestedIcon)) {
        $iconToUse = $requestedIcon;
    } elseif (is_url_accessible($defaultPublicIcon)) {
        $iconToUse = $defaultPublicIcon;
    } else {
        // Ninguna URL accesible: no incluimos icon para que la app use su icono por defecto
        $iconToUse = null;
    }

    if ($iconToUse) {
        $messageData['icon'] = $iconToUse;
    }

    // Enviar notificación usando API v1
    $icon = $iconToUse;
    
    $result = $firebase->sendMulticast(
        $recipients,
        $data->title,
        $data->body,
        $messageData,
        $icon
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Notificaciones enviadas correctamente',
        'summary' => [
            'total_recipients' => count($recipients),
            'successful' => $result['success_count'],
            'failed' => $result['failure_count']
        ],
        'details' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>
