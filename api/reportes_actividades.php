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
require_once '../includes/functions.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $empresa_id = isset($_GET['empresa_id']) ? trim($_GET['empresa_id']) : '';
        $nino_id = isset($_GET['nino_id']) ? trim($_GET['nino_id']) : '';
        
        if (empty($empresa_id)) {
            echo json_encode([
                'success' => false,
                'message' => 'El empresa_id es requerido'
            ]);
            exit();
        }
        
        if (empty($nino_id)) {
            echo json_encode([
                'success' => false,
                'message' => 'El nino_id es requerido'
            ]);
            exit();
        }
        
        $sql = "SELECT 
                    ra.id,
                    ra.empresa_id,
                    ra.nino_id,
                    ra.materia_id,
                    ra.periodo,
                    ra.tipo_periodo,
                    ra.calificacion,
                    ra.participacion,
                    ra.observaciones,
                    ra.areas_mejora,
                    ra.logros,
                    ra.fecha_creacion,
                    n.nombre as nino_nombre,
                    m.nombre as materia_nombre
                FROM reportes_actividades ra
                LEFT JOIN ninos n ON ra.nino_id = n.id
                LEFT JOIN materias m ON ra.materia_id = m.id
                WHERE ra.empresa_id = :empresa_id 
                AND ra.nino_id = :nino_id
                ORDER BY ra.fecha_creacion DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        $stmt->bindParam(':nino_id', $nino_id, PDO::PARAM_STR);
        $stmt->execute();
        
        $reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $reportes_procesados = [];
        foreach ($reportes as $reporte) {
            $reportes_procesados[] = [
                'id' => $reporte['id'],
                'empresa_id' => $reporte['empresa_id'],
                'nino_id' => $reporte['nino_id'],
                'materia_id' => $reporte['materia_id'],
                'periodo' => $reporte['periodo'] ?? '',
                'tipo_periodo' => $reporte['tipo_periodo'] ?? '',
                'calificacion' => $reporte['calificacion'] ?? '',
                'participacion' => $reporte['participacion'] ?? '',
                'observaciones' => $reporte['observaciones'] ?? '',
                'areas_mejora' => $reporte['areas_mejora'] ?? '',
                'logros' => $reporte['logros'] ?? '',
                'fecha_creacion' => $reporte['fecha_creacion'] ?? '',
                'nino_nombre' => $reporte['nino_nombre'] ?? 'Nombre no encontrado',
                'materia_nombre' => $reporte['materia_nombre'] ?? 'Materia no encontrada'
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Reportes de actividades obtenidos correctamente',
            'data' => $reportes_procesados,
            'total' => count($reportes_procesados)
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
    }
    
} catch (Exception $e) {
    error_log("ERROR Reportes Actividades: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
?>