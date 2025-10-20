<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Obtener la ruta solicitada
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/api_t_cuida/api', '', $path);

// Incluir archivos necesarios
include_once '../config/database.php';
include_once '../includes/functions.php';

// Enrutamiento básico
switch ($path) {
    case '/login':
        include_once 'login.php';
        break;
    
    case '/verify-token':
        include_once 'verify-token.php';
        break;
    
    case '/nino':
        include_once 'nino.php';
        break;
    
    case '/salones':
        include_once 'salones.php';
        break;
    
    case '/personal_salon':
        include_once 'personal_salon.php';
        break;
    
    case '/':
    case '':
        http_response_code(200);
        echo json_encode(array(
            "message" => "API T-Cuida v2.0 - JWT Enabled",
            "endpoints" => array(
                "POST /login" => "Autenticación de usuarios (retorna JWT)",
                "GET /verify-token" => "Verificar validez del token JWT",
                "GET /nino" => "Obtener información de un niño (requiere autenticación)",
                "GET /salones" => "Obtener lista de salones/grupos (requiere autenticación)",
                "GET /personal_salon?salon_id={id}" => "Obtener personal asignado a un salón (requiere autenticación)"
            ),
            "authentication" => "JWT Bearer Token required for protected endpoints"
        ));
        break;
    
    default:
        http_response_code(404);
        echo json_encode(array(
            "success" => false,
            "message" => "Endpoint no encontrado"
        ));
        break;
}
?>
