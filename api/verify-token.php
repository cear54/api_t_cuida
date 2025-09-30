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

include_once '../utils/JWTHandler.php';

// Verificar que el método sea GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit();
}

try {
    // Verificar autenticación JWT
    $user_data = JWTHandler::requireAuth();
    
    // Si llegamos aquí, el token es válido
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Token válido',
        'data' => [
            'user_id' => $user_data['user_id'],
            'usuario' => $user_data['usuario'],
            'tipo_usuario' => $user_data['tipo_usuario'],
            'nino_id' => $user_data['nino_id'],
            'expires_at' => date('Y-m-d H:i:s', $user_data['exp'])
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>
