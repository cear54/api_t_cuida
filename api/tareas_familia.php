<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../utils/JWTHandler.php';

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $headers = getallheaders();
    if (!$headers) {
        $headers = [];
    }
    
    // Solo permitir GET para este endpoint
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Solo método GET permitido']);
        exit();
    }
    
    // Validar token JWT
    $authHeader = '';
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token requerido']);
        exit();
    }
    
    $token = substr($authHeader, 7);
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->verifyToken($token);
    
    if ($decoded === false) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit();
    }
    
    // Obtener datos del usuario desde el token
    $usuario = [
        'id' => $decoded['user_id'] ?? $decoded['personal_id'] ?? $decoded['id'] ?? null,
        'empresa_id' => $decoded['empresa_id'] ?? null,
        'rol' => $decoded['tipo_usuario'] ?? $decoded['userType'] ?? $decoded['rol'] ?? null,
        'email' => $decoded['email'] ?? null,
        'nino_id' => $decoded['nino_id'] ?? $decoded['ninoId'] ?? null
    ];
    
    error_log("DEBUG TAREAS FAMILIA - Usuario extraído: " . json_encode($usuario));
    
    if (!$usuario['empresa_id'] || !$usuario['nino_id']) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Datos incompletos: se requieren empresa_id y nino_id en el token',
            'debug' => [
                'empresa_id' => $usuario['empresa_id'],
                'nino_id' => $usuario['nino_id']
            ]
        ]);
        exit();
    }
    
    obtenerTareasFamilia($db, $usuario);
    
} catch (Exception $e) {
    error_log("Error en tareas_familia.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

/**
 * Obtener tareas específicamente para usuarios familia
 * Lógica: empresa_id + nino_id -> buscar grupo_id en ninos -> buscar tareas con salon_id = grupo_id
 */
function obtenerTareasFamilia($db, $usuario) {
    try {
        $empresa_id = $usuario['empresa_id'];
        $nino_id = $usuario['nino_id'];
        
        error_log("DEBUG FAMILIA - Empresa ID: $empresa_id");
        error_log("DEBUG FAMILIA - Niño ID: $nino_id");
        
        // Paso 1: Buscar grupo_id del niño en la tabla ninos
        $stmt = $db->prepare("
            SELECT grupo_id, nombre, apellido_paterno, apellido_materno
            FROM ninos 
            WHERE empresa_id = ? AND id = ?
        ");
        $stmt->execute([$empresa_id, $nino_id]);
        $nino = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nino) {
            throw new Exception('No se encontró el niño especificado en la empresa');
        }
        
        $grupo_id = $nino['grupo_id'];
        $nino_nombre = trim($nino['nombre'] . ' ' . $nino['apellido_paterno'] . ' ' . $nino['apellido_materno']);
        
        error_log("DEBUG FAMILIA - Grupo ID encontrado: $grupo_id");
        error_log("DEBUG FAMILIA - Nombre del niño: $nino_nombre");
        
        if (!$grupo_id) {
            // Si no tiene grupo_id, retornar respuesta vacía pero exitosa
            echo json_encode([
                'success' => true,
                'tareas' => [],
                'total' => 0,
                'usuario_rol' => 'familia',
                'nino_nombre' => $nino_nombre,
                'grupo_id' => null,
                'message' => 'El niño no tiene un grupo asignado',
                'debug' => [
                    'empresa_id' => $empresa_id,
                    'nino_id' => $nino_id,
                    'grupo_id' => $grupo_id
                ]
            ]);
            return;
        }
        
        // Paso 2: Buscar tareas donde salon_id = grupo_id
        $sql = "
            SELECT 
                t.id,
                t.titulo,
                t.descripcion,
                t.fecha_asignacion,
                t.fecha_entrega,
                t.fecha_completado,
                t.estado,
                t.prioridad,
                t.tipo,
                t.salon_id,
                t.nino_id,
                t.usuario_creador_id,
                t.fecha_creacion,
                s.nombre as salon_nombre,
                s.descripcion as salon_descripcion,
                n.nombre as nino_tarea_nombre,
                n.apellido_paterno as nino_tarea_apellido_paterno,
                n.apellido_materno as nino_tarea_apellido_materno,
                u.nombre_usuario as creador_nombre
            FROM tareas t
            LEFT JOIN salones s ON t.salon_id = s.id
            LEFT JOIN ninos n ON t.nino_id = n.id
            LEFT JOIN usuarios_app u ON t.usuario_creador_id = u.id
            WHERE t.activo = 1 
            AND t.empresa_id = ? 
            AND t.salon_id = ?
            ORDER BY 
                t.fecha_creacion DESC,
                CASE t.prioridad 
                    WHEN 'alta' THEN 1 
                    WHEN 'media' THEN 2 
                    WHEN 'baja' THEN 3 
                END,
                t.fecha_entrega ASC
        ";
        
        error_log("DEBUG FAMILIA - SQL: $sql");
        error_log("DEBUG FAMILIA - Parámetros: empresa_id=$empresa_id, salon_id=$grupo_id");
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$empresa_id, $grupo_id]);
        $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("DEBUG FAMILIA - Tareas encontradas: " . count($tareas));
        
        // Formatear resultados
        $tareasFormateadas = [];
        foreach ($tareas as $tarea) {
            $tareasFormateadas[] = [
                'id' => (int)$tarea['id'],
                'titulo' => $tarea['titulo'],
                'descripcion' => $tarea['descripcion'],
                'fecha_asignacion' => $tarea['fecha_asignacion'],
                'fecha_entrega' => $tarea['fecha_entrega'],
                'fecha_completado' => $tarea['fecha_completado'],
                'estado' => $tarea['estado'],
                'prioridad' => $tarea['prioridad'],
                'tipo' => $tarea['tipo'],
                'salon' => [
                    'id' => (int)$tarea['salon_id'],
                    'nombre' => $tarea['salon_nombre'] ?? '',
                    'descripcion' => $tarea['salon_descripcion'] ?? ''
                ],
                'nino' => $tarea['nino_id'] ? [
                    'id' => (int)$tarea['nino_id'],
                    'nombre' => $tarea['nino_tarea_nombre'] ?? '',
                    'apellido_paterno' => $tarea['nino_tarea_apellido_paterno'] ?? '',
                    'apellido_materno' => $tarea['nino_tarea_apellido_materno'] ?? '',
                    'nombre_completo' => trim(($tarea['nino_tarea_nombre'] ?? '') . ' ' . ($tarea['nino_tarea_apellido_paterno'] ?? '') . ' ' . ($tarea['nino_tarea_apellido_materno'] ?? ''))
                ] : null,
                'creador' => [
                    'id' => (int)$tarea['usuario_creador_id'],
                    'nombre' => $tarea['creador_nombre'] ?? 'Usuario sin nombre'
                ],
                'fecha_creacion' => $tarea['fecha_creacion']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'tareas' => $tareasFormateadas,
            'total' => count($tareasFormateadas),
            'usuario_rol' => 'familia',
            'nino_nombre' => $nino_nombre,
            'grupo_id' => $grupo_id,
            'debug' => [
                'empresa_id' => $empresa_id,
                'nino_id' => $nino_id,
                'grupo_id' => $grupo_id,
                'sql_executed' => true
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo tareas familia: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error obteniendo tareas: ' . $e->getMessage()
        ]);
    }
}
?>