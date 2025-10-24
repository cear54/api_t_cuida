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
                AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                ORDER BY fecha ASC, hora_inicio ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
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