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

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

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
        
        // Log para debug
        error_log("🗓️ DEBUG Eventos - Obteniendo eventos para empresa: $empresa_id");
        error_log("🗓️ DEBUG Eventos - Filtro: eventos desde ayer hasta el futuro");
        
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
        
        // Log del resultado
        error_log("🗓️ DEBUG Eventos - Eventos encontrados: " . count($eventos));
        
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