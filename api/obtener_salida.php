<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../utils/JWTHandler.php';

try {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }

    // Obtener y validar el token JWT
    $headers = getallheaders();
    if (!$headers) {
        $headers = [];
    }

    // Buscar el header Authorization en diferentes formas
    $authHeader = '';
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    }

    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token requerido']);
        exit;
    }

    $token = $matches[1];

    try {
        $jwtHandler = new JWTHandler();
        $payload = $jwtHandler->verifyToken($token);
        
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido o expirado']);
            exit;
        }
        
        $userId = $payload['user_id'];
        $empresaId = $payload['empresa_id']; // Obtener empresa_id del token
        
        if (!$empresaId) {
            http_response_code(401);
            echo json_encode(['error' => 'Token no contiene información de empresa']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido']);
        exit;
    }

    // Obtener parámetros
    $ninoId = $_GET['nino_id'] ?? null;
    $fecha = $_GET['fecha'] ?? null;
    // empresa_id viene del token JWT, no del parámetro

    // Validar parámetros requeridos
    if (empty($ninoId) || empty($fecha)) {
        throw new Exception('Parámetros requeridos: nino_id, fecha');
    }

    // Validar formato de fecha
    $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        throw new Exception('Formato de fecha inválido. Use YYYY-MM-DD');
    }

    // Conectar a la base de datos
    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar que el menor pertenece a la empresa del usuario
    $stmt = $pdo->prepare("
        SELECT n.id, n.nombre, n.apellido_paterno, n.apellido_materno
        FROM ninos n 
        WHERE n.id = ? AND n.empresa_id = ?
    ");
    $stmt->execute([$ninoId, $empresaId]);
    $nino = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nino) {
        throw new Exception('Menor no encontrado o no pertenece a su empresa');
    }

    // Obtener información de salida para la fecha específica
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.nino_id,
            s.empresa_id,
            s.fecha,
            s.hora_salida,
            s.quien_recoge,
            s.entregado_limpio,
            s.entregado_con_pertenencias,
            s.created_at
        FROM salidas s
        WHERE s.nino_id = ? 
            AND s.empresa_id = ? 
            AND s.fecha = ?
            AND s.activo = 1
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    
    $stmt->execute([$ninoId, $empresaId, $fecha]);
    $salidaInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($salidaInfo) {
        // Convertir valores booleanos
        $salidaInfo['entregado_limpio'] = (bool)$salidaInfo['entregado_limpio'];
        $salidaInfo['entregado_con_pertenencias'] = (bool)$salidaInfo['entregado_con_pertenencias'];
        
        echo json_encode([
            'success' => true,
            'data' => $salidaInfo,
            'message' => 'Información de salida obtenida exitosamente'
        ]);
    } else {
        // No hay información de salida para esta fecha
        echo json_encode([
            'success' => true,
            'data' => null,
            'message' => 'No hay información de salida registrada para esta fecha'
        ]);
    }

} catch (Exception $e) {
    error_log("Error en obtener_salida.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Error de base de datos en obtener_salida.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>