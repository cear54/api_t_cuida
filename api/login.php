<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

// Verificar que el método sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Método no permitido", null, 405);
}

// Obtener datos del POST
$data = json_decode(file_get_contents("php://input"));

// Verificar que se recibieron los datos necesarios
if (empty($data->email) || empty($data->password)) {
    sendResponse(false, "Email y contraseña son requeridos", null, 400);
}

// Validar formato de email
if (!validateEmail($data->email)) {
    sendResponse(false, "Formato de email inválido", null, 400);
}

try {
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    if ($db == null) {
        throw new Exception("Error de conexión a la base de datos");
    }

    // Preparar consulta
    $query = "SELECT id, email_usuario, password, nombre_usuario, personal_id, nino_id, tipo_usuario, empresa_id, activo, fecha_creacion 
              FROM usuarios_app 
              WHERE email_usuario = :email AND activo = 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $data->email);
    $stmt->execute();

    $num = $stmt->rowCount();

    if ($num > 0) {
        // Usuario encontrado
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar contraseña
        if (password_verify($data->password, $row['password'])) {
            // Verificar suscripción de la empresa
            $empresa_id = $row['empresa_id'];
            $fecha_actual = date('Y-m-d');
            
            $query_suscripcion = "SELECT id, estado, fecha_fin, fecha_fin_prueba, en_periodo_prueba 
                                  FROM suscripciones 
                                  WHERE empresa_id = :empresa_id 
                                  ORDER BY id DESC 
                                  LIMIT 1";
            
            $stmt_suscripcion = $db->prepare($query_suscripcion);
            $stmt_suscripcion->bindParam(":empresa_id", $empresa_id);
            $stmt_suscripcion->execute();
            
            if ($stmt_suscripcion->rowCount() > 0) {
                $suscripcion = $stmt_suscripcion->fetch(PDO::FETCH_ASSOC);
                
                // Verificar si la suscripción está en un estado válido
                $estado = $suscripcion['estado'];
                if ($estado === 'vencida' || $estado === 'cancelada' || $estado === 'suspendida') {
                    $mensaje = "Su suscripción está " . $estado . ". Contacte al administrador para reactivarla.";
                    sendResponse(false, $mensaje, null, 403);
                }
                
                // Verificar fechas de vencimiento
                $fecha_fin = $suscripcion['fecha_fin'];
                $fecha_fin_prueba = $suscripcion['fecha_fin_prueba'];
                
                // Si está en período de prueba, verificar fecha_fin_prueba
                if ($suscripcion['en_periodo_prueba'] == 1) {
                    if (!empty($fecha_fin_prueba) && $fecha_actual > $fecha_fin_prueba) {
                        sendResponse(false, "El período de prueba ha finalizado. Contacte al administrador para renovar su suscripción.", null, 403);
                    }
                } else {
                    // Si no está en prueba, verificar fecha_fin
                    if (!empty($fecha_fin) && $fecha_actual > $fecha_fin) {
                        sendResponse(false, "Su suscripción ha expirado. Contacte al administrador para renovar.", null, 403);
                    }
                }
            } else {
                // No existe registro de suscripción para esta empresa
                sendResponse(false, "No se encontró una suscripción válida para su empresa. Contacte al administrador.", null, 403);
            }
            
            // Login exitoso - Generar JWT
            $tokenData = array(
                "id" => $row['id'],
                "usuario" => $row['nombre_usuario'],
                "tipo_usuario" => $row['tipo_usuario'],
                "nino_id" => $row['nino_id'],
                "personal_id" => $row['personal_id'],
                "empresa_id" => $row['empresa_id']
            );
            
            $jwt_token = JWTHandler::generateToken($tokenData);
            
            $userData = array(
                "id" => $row['id'],
                "email" => $row['email_usuario'],
                "nombre_usuario" => $row['nombre_usuario'],
                "personal_id" => $row['personal_id'],
                "nino_id" => $row['nino_id'],
                "tipo_usuario" => $row['tipo_usuario'],
                "empresa_id" => $row['empresa_id'],
                "activo" => $row['activo'],
                "fecha_creacion" => $row['fecha_creacion'],
                "token" => $jwt_token
            );
            sendResponse(true, "Login exitoso", $userData, 200);
        } else {
            // Contraseña incorrecta
            sendResponse(false, "Credenciales incorrectas", null, 401);
        }
    } else {
        // Usuario no encontrado
        sendResponse(false, "Credenciales incorrectas", null, 401);
    }

} catch (Exception $e) {
    sendResponse(false, "Error interno del servidor: " . $e->getMessage(), null, 500);
}
?>
