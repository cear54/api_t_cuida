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

    // Obtener salon_id del parámetro GET
    $salonId = $_GET['salon_id'] ?? null;
    if (!$salonId) {
        throw new Exception('salon_id es requerido');
    }

    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el salón pertenezca a la empresa del usuario
    $stmtVerify = $db->prepare("
        SELECT id FROM salones 
        WHERE id = :salon_id AND empresa_id = :empresa_id AND activo = 1
    ");
    $stmtVerify->bindParam(':salon_id', $salonId, PDO::PARAM_INT);
    $stmtVerify->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $stmtVerify->execute();
    
    if ($stmtVerify->rowCount() === 0) {
        throw new Exception('Salón no encontrado o no pertenece a esta empresa');
    }

    // Consultar personal asignado al salón con información del puesto
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.nombre,
            p.apellido_paterno,
            p.apellido_materno,
            CONCAT(p.apellido_paterno, ' ', IFNULL(p.apellido_materno, '')) as apellido,
            p.puesto_id,
            pu.nombre as cargo,
            p.telefono,
            p.email,
            p.salon_id,
            p.estado,
            p.fecha_creacion as created_at,
            p.fecha_actualizacion as updated_at
        FROM personal p 
        LEFT JOIN puestos pu ON p.puesto_id = pu.id
        WHERE p.salon_id = :salon_id 
          AND p.activo = 1 
          AND p.empresa_id = :empresa_id
        ORDER BY p.nombre ASC, p.apellido_paterno ASC
    ");
    
    $stmt->bindParam(':salon_id', $salonId, PDO::PARAM_INT);
    $stmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $stmt->execute();
    $personal = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener información del salón para el contexto
    $stmtSalon = $db->prepare("
        SELECT nombre, descripcion, capacidad 
        FROM salones 
        WHERE id = :salon_id AND empresa_id = :empresa_id
    ");
    $stmtSalon->bindParam(':salon_id', $salonId, PDO::PARAM_INT);
    $stmtSalon->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $stmtSalon->execute();
    $salon = $stmtSalon->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Personal obtenido exitosamente',
        'salon' => $salon,
        'personal' => $personal,
        'total_personal' => count($personal)
    ]);

} catch (Exception $e) {
    error_log("Error en personal_salon.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>