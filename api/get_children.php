<?php
require_once '../config/database.php';
require_once '../utils/JWTHandler.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar token JWT
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
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_X_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $apacheHeaders = apache_request_headers();
    if (isset($apacheHeaders['Authorization'])) {
        $authHeader = $apacheHeaders['Authorization'];
    }
}

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Token requerido']);
    exit;
}

$token = substr($authHeader, 7);
$jwtHandler = new JWTHandler();

try {
    $decoded = $jwtHandler->verifyToken($token);
    
    if ($decoded === false) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido']);
        exit;
    }
    
    $personal_id = $decoded['personal_id'] ?? null;
    $empresa_id = $decoded['empresa_id'] ?? null;
    $tipo_usuario = $decoded['tipo_usuario'] ?? null;
    
    // Verificar que sea usuario académico
    if ($tipo_usuario !== 'academico') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso denegado. Solo para usuarios académicos']);
        exit;
    }
    
    // Verificar que tengamos personal_id
    if (!$personal_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Personal ID no encontrado en el token']);
        exit;
    }

    $database = new Database();
    $conn = $database->getConnection();
    
    // Validar suscripción de la empresa
    if ($empresa_id) {
        require_once '../middleware/subscription_validator.php';
        $subscriptionStatus = SubscriptionValidator::validateSubscription($conn, $empresa_id);
        
        if (!$subscriptionStatus['valid']) {
            http_response_code($subscriptionStatus['code']);
            echo json_encode([
                'success' => false,
                'message' => $subscriptionStatus['message']
            ]);
            exit;
        }
    }
    $conn = $database->getConnection();

    // Obtener los niños asignados al salón del personal académico
    $query = "
        SELECT 
            n.id,
            n.nombre,
            n.apellido_paterno,
            n.apellido_materno,
            n.fecha_nacimiento,
            n.genero,
            n.imagen,
            n.tiene_alergias,
            n.alergias,
            n.toma_medicamentos,
            n.medicamentos,
            n.tiene_condiciones_medicas,
            n.condiciones_medicas,
            n.contacto_emergencia,
            n.telefono_emergencia,
            n.contacto_emergencia_2,
            n.telefono_emergencia_2,
            n.contacto_emergencia_3,
            n.telefono_emergencia_3,
            n.contacto_emergencia_4,
            n.telefono_emergencia_4,
            s.nombre as salon_nombre,
            s.id as salon_id,
            CASE 
                WHEN a.id IS NOT NULL THEN 1 
                ELSE 0 
            END as tiene_asistencia_hoy,
            CASE 
                WHEN sal.id IS NOT NULL THEN 1 
                ELSE 0 
            END as tiene_salida_hoy,
            CASE 
                WHEN b.id IS NOT NULL THEN 1 
                ELSE 0 
            END as tiene_bitacora_hoy
        FROM ninos n 
        JOIN salones s ON n.salon_id = s.id 
        JOIN personal p ON p.salon_id = s.id 
        LEFT JOIN asistencias a ON n.id = a.nino_id 
            AND a.empresa_id = ? 
            AND DATE(a.fecha) = CURDATE()
        LEFT JOIN salidas sal ON n.id = sal.nino_id 
            AND sal.empresa_id = ? 
            AND sal.fecha = CURDATE()
        LEFT JOIN bitacoras b ON n.id = b.nino_id 
            AND b.empresa_id = ? 
            AND b.fecha = CURDATE()
        WHERE p.id = ? AND p.empresa_id = ? AND n.activo = 1
        ORDER BY n.nombre, n.apellido_paterno
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $empresa_id, PDO::PARAM_STR);
    $stmt->bindParam(2, $empresa_id, PDO::PARAM_STR);
    $stmt->bindParam(3, $empresa_id, PDO::PARAM_STR);
    $stmt->bindParam(4, $personal_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $empresa_id, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $children = [];
    foreach ($result as $row) {
        // Calcular edad
        $fecha_nacimiento = new DateTime($row['fecha_nacimiento']);
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_nacimiento)->y;

        $children[] = [
            'id' => (int)$row['id'],
            'nombre_completo' => trim($row['nombre'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno']),
            'nombre' => $row['nombre'],
            'apellido_paterno' => $row['apellido_paterno'],
            'apellido_materno' => $row['apellido_materno'],
            'fecha_nacimiento' => $row['fecha_nacimiento'],
            'edad' => $edad,
            'genero' => $row['genero'],
            'imagen' => $row['imagen'],
            'salon' => [
                'id' => (int)$row['salon_id'],
                'nombre' => $row['salon_nombre']
            ],
            'salud' => [
                'tiene_alergias' => $row['tiene_alergias'] === 'si',
                'alergias' => $row['alergias'],
                'toma_medicamentos' => $row['toma_medicamentos'] === 'si',
                'medicamentos' => $row['medicamentos'],
                'tiene_condiciones_medicas' => $row['tiene_condiciones_medicas'] === 'si',
                'condiciones_medicas' => $row['condiciones_medicas']
            ],
            'contacto_emergencia' => [
                'contacto1' => [
                    'nombre' => $row['contacto_emergencia'],
                    'telefono' => $row['telefono_emergencia']
                ],
                'contacto2' => [
                    'nombre' => $row['contacto_emergencia_2'],
                    'telefono' => $row['telefono_emergencia_2']
                ],
                'contacto3' => [
                    'nombre' => $row['contacto_emergencia_3'],
                    'telefono' => $row['telefono_emergencia_3']
                ],
                'contacto4' => [
                    'nombre' => $row['contacto_emergencia_4'],
                    'telefono' => $row['telefono_emergencia_4']
                ]
            ],
            'tiene_asistencia_hoy' => (bool)$row['tiene_asistencia_hoy'],
            'tiene_salida_hoy' => (bool)$row['tiene_salida_hoy'],
            'tiene_bitacora_hoy' => (bool)$row['tiene_bitacora_hoy']
        ];
    }

    $stmt = null;
    $conn = null;

    echo json_encode([
        'success' => true,
        'data' => [
            'children' => $children,
            'total' => count($children),
            'salon_info' => count($children) > 0 ? $children[0]['salon'] : null
        ]
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido: ' . $e->getMessage()]);
}
?>
