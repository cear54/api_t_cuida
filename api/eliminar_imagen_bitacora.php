<?php
require_once '../config/database.php';
require_once '../utils/JWTHandler.php';

// Configurar CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

try {
    // DEBUG: Log de inicio
    error_log("DEBUG eliminar_imagen_bitacora: Iniciando proceso de eliminación");
    error_log("DEBUG eliminar_imagen_bitacora: Método = " . $_SERVER['REQUEST_METHOD']);
    
    // Verificar token JWT - múltiples métodos para obtener headers
    $headers = getallheaders();
    if (!$headers) {
        $headers = apache_request_headers();
    }
    if (!$headers) {
        $headers = [];
    }
    
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    error_log("DEBUG eliminar_imagen_bitacora: Auth header = " . ($authHeader ? "presente" : "ausente"));
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        error_log("DEBUG eliminar_imagen_bitacora: Error - Token de autorización no encontrado");
        throw new Exception('Token de autorización requerido');
    }
    
    $token = $matches[1];
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->verifyToken($token);
    
    if (!$decoded) {
        throw new Exception('Token inválido');
    }

    // Obtener datos del body JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    error_log("DEBUG eliminar_imagen_bitacora: Input recibido = " . print_r($input, true));
    
    if (!$input) {
        error_log("DEBUG eliminar_imagen_bitacora: Error - Datos inválidos o vacíos");
        throw new Exception('Datos inválidos');
    }

    $nino_id = $input['nino_id'] ?? null;
    $empresa_id = $input['empresa_id'] ?? null;
    $fecha = $input['fecha'] ?? null;
    $imagen_field = $input['imagen_field'] ?? null;

    if (!$nino_id || !$empresa_id || !$fecha || !$imagen_field) {
        throw new Exception('Faltan parámetros requeridos: nino_id, empresa_id, fecha, imagen_field');
    }

    // Validar que el campo de imagen sea válido
    if (!in_array($imagen_field, ['imagen1', 'imagen2', 'imagen3'])) {
        throw new Exception('Campo de imagen inválido');
    }

    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    // Obtener la URL de la imagen a eliminar
    $stmt = $db->prepare("
        SELECT $imagen_field 
        FROM bitacoras 
        WHERE nino_id = ? AND empresa_id = ? AND fecha = ?
    ");
    
    $stmt->execute([$nino_id, $empresa_id, $fecha]);
    $bitacora = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bitacora) {
        throw new Exception('No se encontró el registro de bitácora');
    }

    $imagen_url = $bitacora[$imagen_field];
    
    if (empty($imagen_url)) {
        throw new Exception('No hay imagen en este campo para eliminar');
    }

    // Extraer el nombre del archivo de la URL
    $parsed_url = parse_url($imagen_url);
    $archivo_path = $parsed_url['path'] ?? '';
    
    // Obtener solo el nombre del archivo
    $nombre_archivo = basename($archivo_path);
    
    if ($nombre_archivo) {
        // Construir la ruta completa al archivo en el servidor
        $ruta_archivo = '../uploads/bitacoras/' . $nombre_archivo;
        
        // Eliminar el archivo físico si existe
        if (file_exists($ruta_archivo)) {
            unlink($ruta_archivo);
            error_log("DEBUG eliminar_imagen_bitacora: Archivo físico eliminado = " . $ruta_archivo);
        }
    }

    // Actualizar la base de datos (poner NULL en el campo correspondiente)
    $stmt = $db->prepare("
        UPDATE bitacoras 
        SET $imagen_field = NULL 
        WHERE nino_id = ? AND empresa_id = ? AND fecha = ?
    ");
    
    $stmt->execute([$nino_id, $empresa_id, $fecha]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No se pudo actualizar el registro');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Imagen eliminada exitosamente'
    ]);

} catch (Exception $e) {
    error_log("DEBUG eliminar_imagen_bitacora: Exception capturada = " . $e->getMessage());
    error_log("DEBUG eliminar_imagen_bitacora: Exception stack trace = " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>