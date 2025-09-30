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
    error_log("DEBUG subir_imagen_bitacora_url: Iniciando proceso de subida");
    error_log("DEBUG subir_imagen_bitacora_url: Método = " . $_SERVER['REQUEST_METHOD']);
    error_log("DEBUG subir_imagen_bitacora_url: POST data = " . print_r($_POST, true));
    error_log("DEBUG subir_imagen_bitacora_url: FILES data = " . print_r($_FILES, true));
    
    // Verificar token JWT - múltiples métodos para obtener headers
    $headers = getallheaders();
    if (!$headers) {
        $headers = apache_request_headers();
    }
    if (!$headers) {
        $headers = [];
    }
    
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    error_log("DEBUG subir_imagen_bitacora_url: Auth header = " . ($authHeader ? "presente" : "ausente"));
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        error_log("DEBUG subir_imagen_bitacora_url: Error - Token de autorización no encontrado");
        throw new Exception('Token de autorización requerido');
    }
    
    $token = $matches[1];
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->verifyToken($token);
    
    if (!$decoded) {
        throw new Exception('Token inválido');
    }

    // Verificar que se recibió la imagen
    if (!isset($_FILES['imagen'])) {
        throw new Exception('No se recibió ninguna imagen');
    }

    $nino_id = $_POST['nino_id'] ?? null;
    $empresa_id = $_POST['empresa_id'] ?? null;
    $fecha = $_POST['fecha'] ?? null;

    if (!$nino_id || !$empresa_id || !$fecha) {
        throw new Exception('Faltan parámetros requeridos: nino_id, empresa_id, fecha');
    }

    // Validar la imagen
    $imagen = $_FILES['imagen'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/octet-stream'];
    
    // Verificar también por extensión de archivo si el tipo MIME no es confiable
    $file_extension = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    error_log("DEBUG subir_imagen_bitacora_url: Tipo MIME = " . $imagen['type']);
    error_log("DEBUG subir_imagen_bitacora_url: Extensión = " . $file_extension);
    
    if (!in_array($imagen['type'], $allowed_types) && !in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF');
    }

    if ($imagen['size'] > 5 * 1024 * 1024) { // 5MB máximo
        throw new Exception('El archivo es demasiado grande. Máximo 5MB');
    }

    if ($imagen['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir la imagen: ' . $imagen['error']);
    }

    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    // Buscar si existe un registro para esta fecha y niño en la tabla bitacoras
    $stmt = $db->prepare("
        SELECT id, imagen1, imagen2, imagen3 
        FROM bitacoras 
        WHERE nino_id = ? AND empresa_id = ? AND fecha = ?
    ");
    
    $stmt->execute([$nino_id, $empresa_id, $fecha]);
    $bitacora = $stmt->fetch(PDO::FETCH_ASSOC);

    // REQUERIR que exista una fila de bitácora para esa fecha
    if (!$bitacora) {
        throw new Exception('No hay registro de bitácora para esta fecha. Debe existir una bitácora del día para agregar imágenes.');
    }

    // Determinar en qué columna guardar la imagen (solo en fila existente)
    $imagen_campo = null;
    if (empty($bitacora['imagen1'])) {
        $imagen_campo = 'imagen1';
    } elseif (empty($bitacora['imagen2'])) {
        $imagen_campo = 'imagen2';
    } elseif (empty($bitacora['imagen3'])) {
        $imagen_campo = 'imagen3';
    } else {
        throw new Exception('Debe eliminar una imagen antes de subir una nueva pues solamente se permiten 3 por día en la bitácora.');
    }

    // Crear directorio si no existe
    $upload_dir = '../uploads/bitacoras/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generar nombre único para la imagen
    $extension = pathinfo($imagen['name'], PATHINFO_EXTENSION);
    $nombre_archivo = 'bitacora_' . $nino_id . '_' . $fecha . '_' . $imagen_campo . '_' . uniqid() . '.' . $extension;
    $ruta_archivo = $upload_dir . $nombre_archivo;

    // Mover la imagen al directorio de uploads
    if (!move_uploaded_file($imagen['tmp_name'], $ruta_archivo)) {
        throw new Exception('Error al guardar la imagen en el servidor');
    }

    // Crear la URL completa de la imagen
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $imagen_url = $protocol . '://' . $host . '/api_t_cuida/uploads/bitacoras/' . $nombre_archivo;

    // Actualizar SOLO el registro existente en la tabla bitacoras
    $stmt = $db->prepare("
        UPDATE bitacoras 
        SET $imagen_campo = ? 
        WHERE id = ?
    ");
    $stmt->execute([$imagen_url, $bitacora['id']]);
    $bitacora_id = $bitacora['id'];

    echo json_encode([
        'success' => true,
        'message' => 'Imagen subida exitosamente',
        'bitacora_id' => $bitacora_id,
        'imagen_url' => $imagen_url,
        'imagen_campo' => $imagen_campo
    ]);

} catch (Exception $e) {
    error_log("DEBUG subir_imagen_bitacora_url: Exception capturada = " . $e->getMessage());
    error_log("DEBUG subir_imagen_bitacora_url: Exception stack trace = " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>