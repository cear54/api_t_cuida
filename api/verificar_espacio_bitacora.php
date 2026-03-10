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
    // Verificar token JWT
    $headers = getallheaders();
    if (!$headers) {
        $headers = apache_request_headers();
    }
    if (!$headers) {
        $headers = [];
    }
    
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        throw new Exception('Token de autorización requerido');
    }
    
    $token = $matches[1];
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->verifyToken($token);
    
    if (!$decoded) {
        throw new Exception('Token inválido');
    }

    // Obtener datos del body
    $input = json_decode(file_get_contents('php://input'), true);
    
    $nino_id = $input['nino_id'] ?? null;
    $empresa_id = $input['empresa_id'] ?? null;
    $fecha = $input['fecha'] ?? null;

    if (!$nino_id || !$empresa_id || !$fecha) {
        throw new Exception('Faltan parámetros requeridos: nino_id, empresa_id, fecha');
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

    // Si no existe registro de bitácora, no se puede subir imagen
    if (!$bitacora) {
        echo json_encode([
            'success' => false,
            'error' => 'Debe ingresar por lo menos el desayuno en bitácora para poder agregar las imágenes de hoy.',
            'espacios_disponibles' => 0,
            'tiene_bitacora' => false
        ]);
        exit();
    }

    // Contar espacios disponibles
    $espacios_disponibles = 0;
    if (empty($bitacora['imagen1'])) {
        $espacios_disponibles++;
    }
    if (empty($bitacora['imagen2'])) {
        $espacios_disponibles++;
    }
    if (empty($bitacora['imagen3'])) {
        $espacios_disponibles++;
    }

    echo json_encode([
        'success' => true,
        'espacios_disponibles' => $espacios_disponibles,
        'tiene_bitacora' => true,
        'bitacora_id' => $bitacora['id'],
        'imagenes' => [
            'imagen1' => !empty($bitacora['imagen1']),
            'imagen2' => !empty($bitacora['imagen2']),
            'imagen3' => !empty($bitacora['imagen3'])
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
