<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
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
        'rol' => $decoded['tipo_usuario'] ?? $decoded['userType'] ?? $decoded['rol'] ?? null, // Compatibilidad con nombres antiguos
        'email' => $decoded['email'] ?? null
    ];
    
    if (!$usuario['id'] || !$usuario['empresa_id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos de usuario incompletos en el token']);
        exit();
    }
    
    switch ($method) {
        case 'GET':
            obtenerTareas($db, $usuario);
            break;
        case 'POST':
            crearTarea($db, $usuario);
            break;
        case 'PUT':
            actualizarTarea($db, $usuario);
            break;
        case 'DELETE':
            eliminarTarea($db, $usuario);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error en tareas.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

/**
 * Obtener tareas según el rol del usuario
 */
function obtenerTareas($db, $usuario) {
    try {
        $empresa_id = $usuario['empresa_id'];
        $rol = $usuario['rol'];
        $usuario_id = $usuario['id'];
        
        $whereConditions = ["t.empresa_id = ?"];
        $params = [$empresa_id];
        
        // Filtros según el rol
        if ($rol === 'familia') {
            // Los padres ven tareas de sus hijos
            $whereConditions[] = "EXISTS (
                SELECT 1 FROM ninos n 
                WHERE n.salon_id = t.salon_id 
                AND n.empresa_id = ? 
                AND (
                    n.email_emergencia = ? OR 
                    n.email_emergencia_2 = ? OR 
                    n.email_emergencia_3 = ? OR 
                    n.email_emergencia_4 = ?
                )
            )";
            $email = $usuario['email'];
            array_push($params, $empresa_id, $email, $email, $email, $email);
        } else if ($rol === 'academico') {
            // Los educadores ven solo tareas de su salón asignado
            // Obtener personal_id del usuario académico
            $stmt = $db->prepare("SELECT personal_id FROM usuarios_app WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$usuario_id, $empresa_id]);
            $usuario_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario_data || !$usuario_data['personal_id']) {
                throw new Exception('Usuario académico sin personal_id asignado');
            }
            
            // Filtrar tareas por salón asignado al personal
            $whereConditions[] = "EXISTS (
                SELECT 1 FROM personal p 
                WHERE p.salon_id = t.salon_id 
                AND p.id = ?
                AND p.empresa_id = ?
                AND p.activo = 1
            )";
            
            array_push($params, $usuario_data['personal_id'], $empresa_id);
        }
        
        // Filtros adicionales por parámetros GET
        if (isset($_GET['salon_id'])) {
            $whereConditions[] = "t.salon_id = ?";
            $params[] = $_GET['salon_id'];
        }
        
        if (isset($_GET['nino_id'])) {
            if ($_GET['nino_id'] === 'null' || $_GET['nino_id'] === '') {
                $whereConditions[] = "t.nino_id IS NULL";
            } else {
                $whereConditions[] = "t.nino_id = ?";
                $params[] = $_GET['nino_id'];
            }
        }
        
        if (isset($_GET['estado'])) {
            $whereConditions[] = "t.estado = ?";
            $params[] = $_GET['estado'];
        }
        
        if (isset($_GET['fecha_desde'])) {
            $whereConditions[] = "t.fecha_asignacion >= ?";
            $params[] = $_GET['fecha_desde'];
        }
        
        if (isset($_GET['fecha_hasta'])) {
            $whereConditions[] = "t.fecha_asignacion <= ?";
            $params[] = $_GET['fecha_hasta'];
        }
        
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
                n.nombre as nino_nombre,
                n.apellido_paterno as nino_apellido_paterno,
                n.apellido_materno as nino_apellido_materno,
                u.nombre_usuario as creador_nombre
            FROM tareas t
            LEFT JOIN salones s ON t.salon_id = s.id
            LEFT JOIN ninos n ON t.nino_id = n.id
            LEFT JOIN usuarios_app u ON t.usuario_creador_id = u.id
            WHERE t.activo = 1 AND " . implode(' AND ', $whereConditions) . "
            ORDER BY 
                t.fecha_creacion DESC,
                CASE t.prioridad 
                    WHEN 'alta' THEN 1 
                    WHEN 'media' THEN 2 
                    WHEN 'baja' THEN 3 
                END,
                t.fecha_entrega ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear las tareas
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
                    'nombre' => $tarea['salon_nombre'],
                    'descripcion' => $tarea['salon_descripcion']
                ],
                'nino' => $tarea['nino_id'] ? [
                    'id' => (int)$tarea['nino_id'],
                    'nombre' => $tarea['nino_nombre'],
                    'apellido_paterno' => $tarea['nino_apellido_paterno'],
                    'apellido_materno' => $tarea['nino_apellido_materno'],
                    'nombre_completo' => trim($tarea['nino_nombre'] . ' ' . $tarea['nino_apellido_paterno'] . ' ' . $tarea['nino_apellido_materno'])
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
            'usuario_rol' => $rol
        ]);
        
    } catch (Exception $e) {
        error_log("Error obteniendo tareas: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error obteniendo tareas: ' . $e->getMessage()]);
    }
}

/**
 * Crear nueva tarea
 */
function crearTarea($db, $usuario) {
    try {
        // Solo educadores y admins pueden crear tareas
        if (!in_array($usuario['rol'], ['academico', 'administrador'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para crear tareas']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validar campos requeridos
        $camposRequeridos = ['titulo', 'fecha_asignacion', 'salon_id'];
        foreach ($camposRequeridos as $campo) {
            if (!isset($input[$campo]) || empty($input[$campo])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Campo requerido: $campo"]);
                return;
            }
        }
        
        // Validar que el salón pertenezca a la empresa del usuario
        $stmt = $db->prepare("SELECT id FROM salones WHERE id = ? AND empresa_id = ? AND activo = 1");
        $stmt->execute([$input['salon_id'], $usuario['empresa_id']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Salón no válido']);
            return;
        }
        
        // Si se especifica nino_id, validar que pertenezca al salón
        if (isset($input['nino_id']) && !empty($input['nino_id'])) {
            $stmt = $db->prepare("SELECT id FROM ninos WHERE id = ? AND salon_id = ? AND empresa_id = ? AND activo = 1");
            $stmt->execute([$input['nino_id'], $input['salon_id'], $usuario['empresa_id']]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Niño no válido para este salón']);
                return;
            }
        }
        
        $sql = "INSERT INTO tareas (
            titulo, descripcion, fecha_asignacion, fecha_entrega,
            empresa_id, salon_id, nino_id, usuario_creador_id,
            tipo, prioridad, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $input['titulo'],
            $input['descripcion'] ?? null,
            $input['fecha_asignacion'],
            $input['fecha_entrega'] ?? null,
            $usuario['empresa_id'],
            $input['salon_id'],
            $input['nino_id'] ?? null,
            $usuario['id'],
            $input['tipo'] ?? 'general',
            $input['prioridad'] ?? 'media',
            $input['estado'] ?? 'pendiente'
        ]);
        
        $tarea_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Tarea creada exitosamente',
            'tarea_id' => $tarea_id
        ]);
        
    } catch (Exception $e) {
        error_log("Error creando tarea: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error creando tarea: ' . $e->getMessage()]);
    }
}

/**
 * Actualizar tarea existente
 */
function actualizarTarea($db, $usuario) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de tarea requerido']);
            return;
        }
        
        // Verificar que la tarea existe y pertenece a la empresa del usuario
        $stmt = $db->prepare("SELECT * FROM tareas WHERE id = ? AND empresa_id = ? AND activo = 1");
        $stmt->execute([$input['id'], $usuario['empresa_id']]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tarea) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
            return;
        }
        
        // Solo el creador, admins o educadores pueden actualizar
        if (!in_array($usuario['rol'], ['administrador']) && 
            $usuario['rol'] !== 'academico' && 
            $tarea['usuario_creador_id'] != $usuario['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para actualizar esta tarea']);
            return;
        }
        
        // Construir query de actualización dinámicamente
        $campos = [];
        $valores = [];
        
        $camposPermitidos = ['titulo', 'descripcion', 'fecha_asignacion', 'fecha_entrega', 'estado', 'prioridad'];
        
        foreach ($camposPermitidos as $campo) {
            if (isset($input[$campo])) {
                $campos[] = "$campo = ?";
                $valores[] = $input[$campo];
            }
        }
        
        // Si se marca como completada y no tiene fecha_completado, agregarla
        if (isset($input['estado']) && $input['estado'] === 'completada' && !$tarea['fecha_completado']) {
            $campos[] = "fecha_completado = ?";
            $valores[] = date('Y-m-d H:i:s');
        }
        
        if (empty($campos)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No hay campos para actualizar']);
            return;
        }
        
        $valores[] = $input['id'];
        
        $sql = "UPDATE tareas SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($valores);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tarea actualizada exitosamente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error actualizando tarea: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error actualizando tarea: ' . $e->getMessage()]);
    }
}

/**
 * Eliminar tarea (soft delete)
 */
function eliminarTarea($db, $usuario) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de tarea requerido']);
            return;
        }
        
        // Verificar que la tarea existe y pertenece a la empresa del usuario
        $stmt = $db->prepare("SELECT * FROM tareas WHERE id = ? AND empresa_id = ? AND activo = 1");
        $stmt->execute([$input['id'], $usuario['empresa_id']]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tarea) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
            return;
        }
        
        // Solo el creador o admins pueden eliminar
        if ($usuario['rol'] !== 'administrador' && $tarea['usuario_creador_id'] != $usuario['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar esta tarea']);
            return;
        }
        
        // Eliminación física (DELETE permanente)
        $stmt = $db->prepare("DELETE FROM tareas WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tarea eliminada permanentemente'
        ]);
        
    } catch (Exception $e) {
        error_log("Error eliminando tarea: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error eliminando tarea: ' . $e->getMessage()]);
    }
}
?>