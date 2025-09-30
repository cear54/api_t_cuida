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

// Incluir archivos necesarios
include_once '../config/database.php';
include_once '../includes/functions.php';
include_once '../utils/JWTHandler.php';

// Verificar que el método sea GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, "Método no permitido", null, 405);
}

// Verificar token de autorización
$headers = getallheaders();
// Buscar header tanto en minúscula como mayúscula
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
    sendResponse(false, "Token de autorización requerido", null, 401);
}

$token = substr($authHeader, 7); // Remover "Bearer "

try {
    // Verificar token JWT
    $decoded = JWTHandler::verifyToken($token);
    if (!$decoded) {
        sendResponse(false, "Token inválido", null, 401);
    }

    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }

    $empresa_id = $decoded['empresa_id'] ?? null;
    
    if (empty($empresa_id)) {
        sendResponse(false, "ID de empresa no válido", null, 400);
    }

    // Consultar la tabla empresa en la base de datos
    try {
        $query = "SELECT id, nombre_empresa, direccion, telefono, email FROM empresa WHERE id = :empresa_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $empresa = [
                "id" => $row['id'],
                "nombre" => $row['nombre_empresa'], // Usar nombre_empresa de la tabla
                "direccion" => $row['direccion'],
                "telefono" => $row['telefono'],
                "email" => $row['email']
            ];
            sendResponse(true, "Información de empresa obtenida exitosamente", $empresa, 200);
        } else {
            // Si no se encuentra en la base de datos, devolver datos por defecto
            $empresa = [
                "id" => $empresa_id,
                "nombre" => "T Cuida - Centro Educativo",
                "direccion" => "No especificada",
                "telefono" => "No especificado",
                "email" => "info@tcuida.edu.co"
            ];
            sendResponse(true, "Información de empresa obtenida exitosamente", $empresa, 200);
        }
    } catch (PDOException $e) {
        sendResponse(false, "Error consultando información de empresa", null, 500);
    }

} catch (Exception $e) {
    sendResponse(false, "Error interno del servidor: " . $e->getMessage(), null, 500);
}
?>
