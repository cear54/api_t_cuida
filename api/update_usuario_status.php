<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../utils/JWTHandler.php';

// Función para enviar respuesta JSON
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

try {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        sendJsonResponse(false, 'Método no permitido', null, 405);
    }

    // Validar el token JWT
    $decodedToken = JWTHandler::requireAuth();
    
    $usuarioId = $decodedToken['user_id'] ?? $decodedToken['id'] ?? null;
    $empresaId = $decodedToken['empresa_id'] ?? null;
    $tipoUsuario = $decodedToken['tipo_usuario'] ?? null;
    
    if (!$usuarioId || !$empresaId || !$tipoUsuario) {
        sendJsonResponse(false, 'Token incompleto - faltan datos de usuario', null, 401);
    }
    
    // Solo administradores pueden deshabilitar usuarios
    if ($tipoUsuario !== 'administrador') {
        sendJsonResponse(false, 'Sin permisos para realizar esta acción', null, 403);
    }

    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    $usuarioIdADeshabilitar = $input['usuario_id'] ?? null;
    $nuevoEstado = $input['activo'] ?? null;

    if ($usuarioIdADeshabilitar === null || $nuevoEstado === null) {
        sendJsonResponse(false, 'Faltan parámetros requeridos: usuario_id y activo', null, 400);
    }

    // Validar que el estado sea 0 o 1
    if (!in_array($nuevoEstado, [0, 1])) {
        sendJsonResponse(false, 'El estado activo debe ser 0 o 1', null, 400);
    }

    // Verificar que no se esté deshabilitando a sí mismo
    if ($usuarioId == $usuarioIdADeshabilitar) {
        sendJsonResponse(false, 'No puedes modificar tu propio estado', null, 400);
    }

    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el usuario a deshabilitar existe y pertenece a la misma empresa
    $stmt = $db->prepare("
        SELECT id, nombre_usuario, activo 
        FROM usuarios_app 
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->execute([$usuarioIdADeshabilitar, $empresaId]);
    $usuarioObjectivo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuarioObjectivo) {
        sendJsonResponse(false, 'Usuario no encontrado o no pertenece a tu empresa', null, 404);
    }

    // Verificar si ya está en el estado solicitado
    if ($usuarioObjectivo['activo'] == $nuevoEstado) {
        $estadoTexto = $nuevoEstado == 1 ? 'habilitado' : 'deshabilitado';
        sendJsonResponse(false, "El usuario ya está $estadoTexto", null, 400);
    }

    // Actualizar el estado del usuario
    $stmt = $db->prepare("
        UPDATE usuarios_app 
        SET activo = ?, fecha_actualizacion = NOW() 
        WHERE id = ? AND empresa_id = ?
    ");
    
    $success = $stmt->execute([$nuevoEstado, $usuarioIdADeshabilitar, $empresaId]);

    if ($success && $stmt->rowCount() > 0) {
        $accionTexto = $nuevoEstado == 1 ? 'habilitado' : 'deshabilitado';
        sendJsonResponse(true, "Usuario {$usuarioObjectivo['nombre_usuario']} $accionTexto exitosamente");
    } else {
        sendJsonResponse(false, 'No se pudo actualizar el estado del usuario', null, 500);
    }

} catch (Exception $e) {
    error_log("Error en update_usuario_status.php: " . $e->getMessage());
    sendJsonResponse(false, 'Error interno del servidor', null, 500);
}
?>