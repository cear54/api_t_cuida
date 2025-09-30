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
    // Verificar token JWT - múltiples métodos para obtener headers
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
    error_log("DEBUG verificar_espacio_bitacora: Token extraído = " . substr($token, 0, 20) . "...");
    
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->verifyToken($token);
    
    error_log("DEBUG verificar_espacio_bitacora: Token verificado = " . ($decoded ? 'true' : 'false'));
    
    if (!$decoded) {
        throw new Exception('Token inválido');
    }

    // Obtener datos del body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }

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
        SELECT imagen1, imagen2, imagen3 
        FROM bitacoras 
        WHERE nino_id = ? AND empresa_id = ? AND fecha = ?
    ");
    
    $stmt->execute([$nino_id, $empresa_id, $fecha]);
    $bitacora = $stmt->fetch(PDO::FETCH_ASSOC);

    // Solo permitir agregar imágenes si existe una fila de bitácora para esta fecha
    if (!$bitacora) {
        echo json_encode([
            'success' => false,
            'error' => 'No hay registro de bitácora para esta fecha. Debe existir una bitácora del día para agregar imágenes.'
        ]);
        exit();
    }

    // Contar cuántos espacios están ocupados en las columnas imagen1, imagen2, imagen3
    $espacios_ocupados = 0;
    
    if (!empty($bitacora['imagen1'])) $espacios_ocupados++;
    if (!empty($bitacora['imagen2'])) $espacios_ocupados++;
    if (!empty($bitacora['imagen3'])) $espacios_ocupados++;
    
    $espacios_disponibles = 3 - $espacios_ocupados;

    echo json_encode([
        'success' => true,
        'espacios_disponibles' => $espacios_disponibles,
        'registro_existe' => true
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>