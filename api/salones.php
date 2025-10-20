<?php
require_once '../config/database.php';
require_once '../utils/JWTHandler.php';

// Configurar CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

    // Obtener empresa_id del token decodificado
    $empresaId = $decoded['empresa_id'] ?? null;
    if (!$empresaId) {
        throw new Exception('ID de empresa no encontrado en el token');
    }

    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    // Consultar salones solo de la empresa del usuario
    $stmt = $db->prepare("
        SELECT 
            id,
            nombre,
            descripcion,
            capacidad,
            activo,
            fecha_creacion as created_at,
            fecha_actualizacion as updated_at
        FROM salones 
        WHERE activo = 1 AND empresa_id = :empresa_id
        ORDER BY nombre ASC
    ");
    
    $stmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $stmt->execute();
    $salones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Salones obtenidos exitosamente',
        'salones' => $salones,
        'total' => count($salones)
    ]);

} catch (Exception $e) {
    error_log("Error en salones.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>