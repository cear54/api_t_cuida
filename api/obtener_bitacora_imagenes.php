<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, nino_id, empresa_id');

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
        $empresaIdFromToken = $payload['empresa_id']; // Obtener empresa_id del token
        
        if (!$empresaIdFromToken) {
            http_response_code(401);
            echo json_encode(['error' => 'Token no contiene información de empresa']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido']);
        exit;
    }

    // Obtener parámetros de headers o GET
    $ninoId = $headers['nino_id'] ?? $_GET['nino_id'] ?? null;
    $empresaId = $headers['empresa_id'] ?? $_GET['empresa_id'] ?? null;

    // Validar parámetros requeridos
    if (empty($ninoId) || empty($empresaId)) {
        throw new Exception('Parámetros requeridos: nino_id, empresa_id');
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

    // Obtener bitácoras con imágenes para el menor específico
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.nino_id,
            b.empresa_id,
            b.fecha,
            b.imagen1,
            b.imagen2,
            b.imagen3,
            b.created_at
        FROM bitacoras b
        WHERE b.nino_id = ? 
            AND b.empresa_id = ? 
            AND b.activo = 1
            AND (
                (b.imagen1 IS NOT NULL AND b.imagen1 != '') OR
                (b.imagen2 IS NOT NULL AND b.imagen2 != '') OR
                (b.imagen3 IS NOT NULL AND b.imagen3 != '')
            )
        ORDER BY b.fecha DESC, b.created_at DESC
    ");
    
    $stmt->execute([$ninoId, $empresaId]);
    $bitacoras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar resultados para limpiar valores nulos
    $bitacorasLimpias = [];
    foreach ($bitacoras as $bitacora) {
        $bitacoraLimpia = [
            'id' => $bitacora['id'],
            'nino_id' => $bitacora['nino_id'],
            'empresa_id' => $bitacora['empresa_id'],
            'fecha' => $bitacora['fecha'],
            'created_at' => $bitacora['created_at']
        ];

        // Solo incluir imágenes que no estén vacías
        if (!empty($bitacora['imagen1'])) {
            $bitacoraLimpia['imagen1'] = $bitacora['imagen1'];
        }
        if (!empty($bitacora['imagen2'])) {
            $bitacoraLimpia['imagen2'] = $bitacora['imagen2'];
        }
        if (!empty($bitacora['imagen3'])) {
            $bitacoraLimpia['imagen3'] = $bitacora['imagen3'];
        }

        $bitacorasLimpias[] = $bitacoraLimpia;
    }

    echo json_encode([
        'success' => true,
        'data' => $bitacorasLimpias,
        'total' => count($bitacorasLimpias),
        'message' => 'Imágenes de bitácora obtenidas exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error en obtener_bitacora_imagenes.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Error de base de datos en obtener_bitacora_imagenes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>