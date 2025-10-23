<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include_once '../config/database.php';
include_once '../includes/functions.php';
include_once '../utils/JWTHandler.php';

// Verificar que el método sea GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, "Método no permitido", null, 405);
}

// Verificar JWT
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : 
              (isset($headers['authorization']) ? $headers['authorization'] : '');

if (empty($authHeader)) {
    sendResponse(false, "Token de autorización requerido", null, 401);
}

$token = str_replace('Bearer ', '', $authHeader);

try {
    $decoded = JWTHandler::verifyToken($token);
    $userId = $decoded['user_id'];
    $tipoUsuario = $decoded['tipo_usuario'] ?? 'familia';
    $empresaId = $decoded['empresa_id'] ?? null;
    
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Parámetros de consulta
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
    $estado = isset($_GET['estado']) ? $_GET['estado'] : null;
    $ninoId = isset($_GET['nino_id']) ? (int)$_GET['nino_id'] : null;
    
    $offset = ($pagina - 1) * $limite;
    
    // Construir consulta base - simplificada para solo tabla notificaciones
    $baseQuery = "FROM notificaciones n 
                  WHERE n.empresa_id = :empresa_id";
    
    $params = [
        ':empresa_id' => $empresaId
    ];
    
    // Agregar filtros opcionales
    if ($tipo) {
        $baseQuery .= " AND n.tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    
    if ($estado) {
        $baseQuery .= " AND n.estado = :estado";
        $params[':estado'] = $estado;
    }
    
    if ($ninoId) {
        $baseQuery .= " AND n.nino_id = :nino_id";
        $params[':nino_id'] = $ninoId;
    }
    
    // Consulta para contar total
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $countStmt = $db->prepare($countQuery);
    
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Consulta principal con datos - Solo las columnas requeridas
    $dataQuery = "SELECT 
                    n.titulo,
                    n.mensaje,
                    n.prioridad,
                    n.fecha_envio
                  " . $baseQuery . "
                  ORDER BY n.fecha_envio DESC";
    
    $dataStmt = $db->prepare($dataQuery);
    
    foreach ($params as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }
    
    $dataStmt->execute();
    $notificaciones = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Los datos ya vienen listos de la consulta SQL simplificada
    // Solo las 4 columnas: titulo, mensaje, prioridad, fecha_envio
    
    // Estadísticas simplificadas
    $statsQuery = "SELECT COUNT(*) as total_general FROM notificaciones WHERE empresa_id = :empresa_id";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(':empresa_id', $empresaId);
    $statsStmt->execute();
    $estadisticas = [
        'total_general' => $statsStmt->fetch(PDO::FETCH_ASSOC)['total_general'],
        'leidas' => 0,
        'no_leidas' => 0,
        'pendientes_accion' => 0
    ];
    
    // Respuesta exitosa
    sendResponse(true, "Historial de notificaciones obtenido", [
        "notificaciones" => $notificaciones,
        "estadisticas" => $estadisticas,
        "filtros_aplicados" => [
            "tipo" => $tipo,
            "estado" => $estado,
            "nino_id" => $ninoId
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse(false, "Error obteniendo historial: " . $e->getMessage(), null, 500);
}

// Función auxiliar para formatear fechas
function formatearFecha($fecha) {
    if (!$fecha) return null;
    
    $timestamp = strtotime($fecha);
    $ahora = time();
    $diferencia = $ahora - $timestamp;
    
    if ($diferencia < 60) {
        return "Hace unos segundos";
    } elseif ($diferencia < 3600) {
        $minutos = floor($diferencia / 60);
        return "Hace $minutos minuto" . ($minutos != 1 ? "s" : "");
    } elseif ($diferencia < 86400) {
        $horas = floor($diferencia / 3600);
        return "Hace $horas hora" . ($horas != 1 ? "s" : "");
    } elseif ($diferencia < 604800) {
        $dias = floor($diferencia / 86400);
        return "Hace $dias día" . ($dias != 1 ? "s" : "");
    } else {
        return date('d/m/Y H:i', $timestamp);
    }
}
?>