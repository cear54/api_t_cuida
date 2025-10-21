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

    // Consultar niños de la empresa
    $stmt = $db->prepare("
        SELECT 
            n.id,
            n.nombre,
            n.apellido_paterno,
            n.apellido_materno,
            n.fecha_nacimiento,
            n.genero,
            n.curp,
            n.imagen,
            n.salon_id,
            s.nombre as salon_nombre,
            n.contacto_emergencia,
            n.parentesco_emergencia,
            n.telefono_emergencia,
            n.email_emergencia,
            n.contacto_emergencia_2,
            n.parentesco_emergencia_2,
            n.telefono_emergencia_2,
            n.email_emergencia_2,
            n.contacto_emergencia_3,
            n.parentesco_emergencia_3,
            n.telefono_emergencia_3,
            n.email_emergencia_3,
            n.contacto_emergencia_4,
            n.parentesco_emergencia_4,
            n.telefono_emergencia_4,
            n.email_emergencia_4,
            n.tiene_alergias,
            n.toma_medicamentos,
            n.tiene_condiciones_medicas,
            n.observaciones,
            n.fecha_inscripcion,
            n.fecha_creacion as created_at,
            n.fecha_actualizacion as updated_at
        FROM ninos n 
        LEFT JOIN salones s ON n.salon_id = s.id
        WHERE n.activo = 1 
          AND n.empresa_id = :empresa_id
        ORDER BY n.nombre ASC, n.apellido_paterno ASC
    ");
    
    $stmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $stmt->execute();
    $ninos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Niños obtenidos exitosamente',
        'ninos' => $ninos,
        'total' => count($ninos)
    ]);

} catch (Exception $e) {
    error_log("Error en ninos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>