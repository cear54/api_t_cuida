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
include_once 'models/Nino.php';
include_once '../utils/JWTHandler.php';

// Verificar autenticación JWT
$user_data = JWTHandler::requireAuth();

$database = new Database();
$db = $database->getConnection();

$nino = new Nino($db);

// Obtener nino_id de los parámetros GET
$nino_id = isset($_GET['nino_id']) ? $_GET['nino_id'] : '';

// Validar que se envió el nino_id
if(empty($nino_id)) {
    http_response_code(400);
    echo json_encode(array(
        "success" => false,
        "message" => "nino_id es requerido"
    ));
    exit;
}

// Validar que nino_id no sea 0
if($nino_id == '0') {
    http_response_code(404);
    echo json_encode(array(
        "success" => false,
        "message" => "No hay niño asociado a esta cuenta"
    ));
    exit;
}

// Validar que el usuario tenga acceso a este niño
if($user_data['nino_id'] != $nino_id && $user_data['tipo_usuario'] != 'admin') {
    http_response_code(403);
    echo json_encode(array(
        "success" => false,
        "message" => "No tienes permisos para acceder a esta información"
    ));
    exit;
}

// Asignar nino_id
$nino->id = $nino_id;

// Intentar obtener datos del niño
if($nino->getNinoById()) {
    http_response_code(200);
    echo json_encode(array(
        "success" => true,
        "message" => "Datos del niño obtenidos exitosamente",
        "data" => array(
            "id" => $nino->id,
            "nombre" => $nino->nombre,
            "apellidos" => $nino->apellido_paterno . " " . $nino->apellido_materno,
            "apellido_paterno" => $nino->apellido_paterno,
            "apellido_materno" => $nino->apellido_materno,
            "fecha_nacimiento" => $nino->fecha_nacimiento,
            "genero" => $nino->genero,
            "grupo_id" => $nino->grupo_id,
            "curp" => $nino->curp,
            "imagen" => $nino->imagen,
            "tiene_alergias" => $nino->tiene_alergias,
            "alergias" => $nino->alergias,
            "toma_medicamentos" => $nino->toma_medicamentos,
            "medicamentos" => $nino->medicamentos,
            "tiene_condiciones_medicas" => $nino->tiene_condiciones_medicas,
            "condiciones_medicas" => $nino->condiciones_medicas,
            "contacto_emergencia" => $nino->contacto_emergencia,
            "parentesco_emergencia" => $nino->parentesco_emergencia,
            "telefono_emergencia" => $nino->telefono_emergencia,
            "imagen_contacto_1" => $nino->imagen_contacto_1,
            "email_emergencia" => $nino->email_emergencia,
            "contacto_emergencia_2" => $nino->contacto_emergencia_2,
            "parentesco_emergencia_2" => $nino->parentesco_emergencia_2,
            "telefono_emergencia_2" => $nino->telefono_emergencia_2,
            "imagen_contacto_2" => $nino->imagen_contacto_2,
            "email_emergencia_2" => $nino->email_emergencia_2,
            "contacto_emergencia_3" => $nino->contacto_emergencia_3,
            "parentesco_emergencia_3" => $nino->parentesco_emergencia_3,
            "telefono_emergencia_3" => $nino->telefono_emergencia_3,
            "imagen_contacto_3" => $nino->imagen_contacto_3,
            "email_emergencia_3" => $nino->email_emergencia_3,
            "activo" => $nino->activo,
            "fecha_inscripcion" => $nino->fecha_inscripcion,
            "fecha_creacion" => $nino->fecha_creacion,
            "fecha_actualizacion" => $nino->fecha_actualizacion,
            "empresa_id" => $nino->empresa_id,
            "salon_id" => $nino->salon_id,
            "salon_nombre" => $nino->salon_nombre,
            "contacto_emergencia_4" => $nino->contacto_emergencia_4,
            "parentesco_emergencia_4" => $nino->parentesco_emergencia_4,
            "telefono_emergencia_4" => $nino->telefono_emergencia_4,
            "imagen_contacto_4" => $nino->imagen_contacto_4,
            "email_emergencia_4" => $nino->email_emergencia_4
        ),
        "timestamp" => date('Y-m-d H:i:s')
    ));
} else {
    http_response_code(404);
    echo json_encode(array(
        "success" => false,
        "message" => "No se encontró el niño con ID: " . $nino_id
    ));
}
?>
