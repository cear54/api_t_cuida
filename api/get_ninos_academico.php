<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../utils/JWTHandler.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Verificar token JWT
        $headers = getallheaders() ?: [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token requerido']);
            exit;
        }
        
        $token = substr($authHeader, 7);
        $jwtHandler = new JWTHandler();
        $decoded = $jwtHandler->verifyToken($token);
        
        if ($decoded === false) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit;
        }
        
        $personal_id = $decoded['personal_id'] ?? null;
        $empresa_id = $decoded['empresa_id'] ?? null;
        $tipo_usuario = $decoded['tipo_usuario'] ?? null;
        
        if ($tipo_usuario !== 'academico') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo para usuarios académicos']);
            exit;
        }
        
        if (!$personal_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Personal ID no encontrado']);
            exit;
        }
        
        // Obtener niños del salón asignado al personal académico
        $sql = "SELECT 
                    n.id,
                    n.nombre,
                    n.apellido_paterno,
                    n.apellido_materno,
                    s.nombre as salon_nombre
                FROM ninos n 
                JOIN salones s ON n.salon_id = s.id 
                JOIN personal p ON p.salon_id = s.id 
                WHERE p.id = :personal_id 
                AND p.empresa_id = :empresa_id 
                AND n.activo = 1
                ORDER BY n.nombre, n.apellido_paterno";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':personal_id', $personal_id, PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        $stmt->execute();
        
        $ninos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ninos_procesados = [];
        foreach ($ninos as $nino) {
            $ninos_procesados[] = [
                'id' => $nino['id'],
                'nombre' => $nino['nombre'],
                'apellido_paterno' => $nino['apellido_paterno'] ?? '',
                'apellido_materno' => $nino['apellido_materno'] ?? '',
                'nombre_completo' => trim($nino['nombre'] . ' ' . ($nino['apellido_paterno'] ?? '') . ' ' . ($nino['apellido_materno'] ?? '')),
                'salon_nombre' => $nino['salon_nombre'] ?? ''
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Niños obtenidos correctamente',
            'data' => $ninos_procesados,
            'total' => count($ninos_procesados)
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
    }
    
} catch (Exception $e) {
    error_log("ERROR Niños Académico: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
?>
