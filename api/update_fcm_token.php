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
    
    $userId = $payload['user_id'];
    
    // Obtener datos del POST
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->fcm_token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token FCM requerido']);
        exit;
    }
    
    // Obtener información del dispositivo (opcional)
    $tipoDispositivo = $data->tipo_dispositivo ?? null;
    $versionSistema = $data->version_sistema ?? null;
    $modeloDispositivo = $data->modelo_dispositivo ?? null;
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Verificar si el token es diferente al actual
    $checkQuery = "SELECT token_app, tipo_dispositivo FROM usuarios_app WHERE id = :user_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(":user_id", $userId);
    $checkStmt->execute();
    $currentData = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $currentToken = $currentData['token_app'] ?? null;
    $currentTipoDispositivo = $currentData['tipo_dispositivo'] ?? null;
    
    $tokenNeedsUpdate = ($currentToken !== $data->fcm_token);
    $deviceNeedsUpdate = ($tipoDispositivo && $currentTipoDispositivo !== $tipoDispositivo);
    
    if (!$tokenNeedsUpdate && !$deviceNeedsUpdate) {
        echo json_encode([
            'success' => true,
            'message' => 'Token FCM y dispositivo ya están actualizados',
            'updated' => false
        ]);
        exit;
    }
    
    // Construir consulta de actualización dinámicamente
    $updateFields = ["token_app = :token_app"];
    $params = [':token_app' => $data->fcm_token, ':user_id' => $userId];
    
    if ($tipoDispositivo) {
        $updateFields[] = "tipo_dispositivo = :tipo_dispositivo";
        $params[':tipo_dispositivo'] = $tipoDispositivo;
    }
    
    if ($versionSistema) {
        $updateFields[] = "version_sistema = :version_sistema";
        $params[':version_sistema'] = $versionSistema;
    }
    
    if ($modeloDispositivo) {
        $updateFields[] = "modelo_dispositivo = :modelo_dispositivo";
        $params[':modelo_dispositivo'] = $modeloDispositivo;
    }
    
    // Actualizar token FCM y datos del dispositivo
    $updateQuery = "UPDATE usuarios_app SET " . implode(', ', $updateFields) . " WHERE id = :user_id";
    $updateStmt = $db->prepare($updateQuery);
    
    foreach ($params as $param => $value) {
        $updateStmt->bindValue($param, $value);
    }
    
    if ($updateStmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Token FCM y dispositivo actualizados correctamente',
            'updated' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'device_info' => [
                'tipo_dispositivo' => $tipoDispositivo,
                'version_sistema' => $versionSistema,
                'modelo_dispositivo' => $modeloDispositivo
            ]
        ]);
    } else {
        throw new Exception("Error al actualizar el token FCM");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
