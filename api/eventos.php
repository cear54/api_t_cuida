<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder a solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/timezone_helper.php';

try {
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Obtener empresa_id del parámetro GET
        $empresa_id = isset($_GET['empresa_id']) ? trim($_GET['empresa_id']) : '';
        
        if (empty($empresa_id)) {
            echo json_encode([
                'success' => false,
                'message' => 'El empresa_id es requerido'
            ]);
            exit();
        }
        
        // Preparar consulta SQL con filtro de fecha (desde ayer)
        $sql = "SELECT 
                    id,
                    nombre,
                    fecha,
                    hora_inicio,
                    hora_final,
                    lugar,
                    observaciones,
                    color,
                    activo,
                    empresa_id,
                    DATE_FORMAT(fecha, '%Y-%m-%d') as fecha_formatted,
                    DATE_FORMAT(hora_inicio, '%H:%i') as hora_inicio_formatted,
                    DATE_FORMAT(hora_final, '%H:%i') as hora_final_formatted
                FROM eventos 
                WHERE empresa_id = :empresa_id 
                AND activo = 1
                AND DATE(fecha) >= :fecha_minima
                ORDER BY fecha ASC, hora_inicio ASC";
        // Calcular fecha mínima usando PHP para consistencia
        $fechaMinima = date('Y-m-d', strtotime('-1 day'));
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_minima', $fechaMinima, PDO::PARAM_STR);
        $stmt->execute();
        
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Procesar eventos para asegurar formato correcto
        $eventos_procesados = [];
        foreach ($eventos as $evento) {
            $eventos_procesados[] = [
                'id' => $evento['id'],
                'nombre' => $evento['nombre'],
                'fecha' => $evento['fecha_formatted'],
                'hora_inicio' => $evento['hora_inicio_formatted'],
                'hora_final' => $evento['hora_final_formatted'],
                'lugar' => $evento['lugar'] ?? '',
                'observaciones' => $evento['observaciones'] ?? '',
                'color' => $evento['color'] ?? '#2196F3',
                'activo' => $evento['activo'],
                'empresa_id' => $evento['empresa_id']
            ];
        }
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Eventos obtenidos correctamente',
            'data' => $eventos_procesados,
            'total' => count($eventos_procesados)
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Crear nuevo evento
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos requeridos
        $required_fields = ['nombre', 'fecha', 'hora_inicio', 'hora_final', 'empresa_id'];
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                echo json_encode([
                    'success' => false,
                    'message' => "El campo '$field' es requerido"
                ]);
                exit();
            }
        }
        
        // Preparar datos del evento
        $nombre = trim($input['nombre']);
        $fecha = trim($input['fecha']);
        $hora_inicio = trim($input['hora_inicio']);
        $hora_final = trim($input['hora_final']);
        $lugar = isset($input['lugar']) ? trim($input['lugar']) : '';
        $observaciones = isset($input['observaciones']) ? trim($input['observaciones']) : '';
        $color = isset($input['color']) ? trim($input['color']) : '#E91E63';
        $empresa_id = trim($input['empresa_id']);
        
        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            echo json_encode([
                'success' => false,
                'message' => 'Formato de fecha inválido. Use YYYY-MM-DD'
            ]);
            exit();
        }
        
        // Validar formato de hora
        if (!preg_match('/^\d{2}:\d{2}$/', $hora_inicio) || !preg_match('/^\d{2}:\d{2}$/', $hora_final)) {
            echo json_encode([
                'success' => false,
                'message' => 'Formato de hora inválido. Use HH:MM'
            ]);
            exit();
        }
        
        // Insertar evento en la base de datos
        $sql = "INSERT INTO eventos (nombre, fecha, hora_inicio, hora_final, lugar, observaciones, color, activo, empresa_id) 
                VALUES (:nombre, :fecha, :hora_inicio, :hora_final, :lugar, :observaciones, :color, 1, :empresa_id)";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->bindParam(':hora_inicio', $hora_inicio, PDO::PARAM_STR);
        $stmt->bindParam(':hora_final', $hora_final, PDO::PARAM_STR);
        $stmt->bindParam(':lugar', $lugar, PDO::PARAM_STR);
        $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
        $stmt->bindParam(':color', $color, PDO::PARAM_STR);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $evento_id = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Evento creado exitosamente',
                'data' => [
                    'id' => $evento_id,
                    'nombre' => $nombre,
                    'fecha' => $fecha,
                    'hora_inicio' => $hora_inicio,
                    'hora_final' => $hora_final,
                    'lugar' => $lugar,
                    'observaciones' => $observaciones,
                    'color' => $color,
                    'activo' => '1',
                    'empresa_id' => $empresa_id
                ]
            ]);
        } else {
            throw new Exception('Error al insertar evento en la base de datos');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Modificar evento existente
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos requeridos
        $required_fields = ['id', 'nombre', 'fecha', 'hora_inicio', 'hora_final', 'empresa_id'];
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                echo json_encode([
                    'success' => false,
                    'message' => "El campo '$field' es requerido"
                ]);
                exit();
            }
        }
        
        // Preparar datos del evento
        $id = trim($input['id']);
        $nombre = trim($input['nombre']);
        $fecha = trim($input['fecha']);
        $hora_inicio = trim($input['hora_inicio']);
        $hora_final = trim($input['hora_final']);
        $lugar = isset($input['lugar']) ? trim($input['lugar']) : '';
        $observaciones = isset($input['observaciones']) ? trim($input['observaciones']) : '';
        $color = isset($input['color']) ? trim($input['color']) : '#E91E63';
        $empresa_id = trim($input['empresa_id']);
        
        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            echo json_encode([
                'success' => false,
                'message' => 'Formato de fecha inválido. Use YYYY-MM-DD'
            ]);
            exit();
        }
        
        // Validar formato de hora
        if (!preg_match('/^\d{2}:\d{2}$/', $hora_inicio) || !preg_match('/^\d{2}:\d{2}$/', $hora_final)) {
            echo json_encode([
                'success' => false,
                'message' => 'Formato de hora inválido. Use HH:MM'
            ]);
            exit();
        }
        
        // Verificar que el evento pertenece a la empresa
        $check_sql = "SELECT id FROM eventos WHERE id = :id AND empresa_id = :empresa_id";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $check_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Evento no encontrado o no pertenece a esta empresa'
            ]);
            exit();
        }
        
        // Actualizar evento en la base de datos
        $sql = "UPDATE eventos SET 
                    nombre = :nombre, 
                    fecha = :fecha, 
                    hora_inicio = :hora_inicio, 
                    hora_final = :hora_final, 
                    lugar = :lugar, 
                    observaciones = :observaciones, 
                    color = :color 
                WHERE id = :id AND empresa_id = :empresa_id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->bindParam(':hora_inicio', $hora_inicio, PDO::PARAM_STR);
        $stmt->bindParam(':hora_final', $hora_final, PDO::PARAM_STR);
        $stmt->bindParam(':lugar', $lugar, PDO::PARAM_STR);
        $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
        $stmt->bindParam(':color', $color, PDO::PARAM_STR);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Evento actualizado exitosamente',
                'data' => [
                    'id' => $id,
                    'nombre' => $nombre,
                    'fecha' => $fecha,
                    'hora_inicio' => $hora_inicio,
                    'hora_final' => $hora_final,
                    'lugar' => $lugar,
                    'observaciones' => $observaciones,
                    'color' => $color,
                    'empresa_id' => $empresa_id
                ]
            ]);
        } else {
            throw new Exception('Error al actualizar evento en la base de datos');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Eliminar evento
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validar datos requeridos
        if (empty($input['id']) || empty($input['empresa_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Los campos id y empresa_id son requeridos'
            ]);
            exit();
        }
        
        $id = trim($input['id']);
        $empresa_id = trim($input['empresa_id']);
        
        // Verificar que el evento pertenece a la empresa
        $check_sql = "SELECT id, nombre FROM eventos WHERE id = :id AND empresa_id = :empresa_id";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $check_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        $check_stmt->execute();
        
        $evento = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$evento) {
            echo json_encode([
                'success' => false,
                'message' => 'Evento no encontrado o no pertenece a esta empresa'
            ]);
            exit();
        }
        
        // Eliminar evento de la base de datos
        $sql = "DELETE FROM eventos WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Evento eliminado exitosamente',
                'data' => [
                    'id' => $id,
                    'nombre' => $evento['nombre']
                ]
            ]);
        } else {
            throw new Exception('Error al eliminar evento de la base de datos');
        }
        
    } else {
        // Método no permitido
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("❌ ERROR Eventos: " . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
?>