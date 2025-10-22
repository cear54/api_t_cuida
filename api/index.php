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
    
    case '/ninos':
        include_once 'ninos.php';
        break;
    
    case '/update_fcm_token':
        include_once 'update_fcm_token.php';
        break;
    
    case '/send_notification':
        include_once 'send_notification.php';
        break;
    
    case '/test_firebase_v1':
        include_once 'test_firebase_v1.php';
        break;
    
    case '/test_firebase_config':
        include_once 'test_firebase_config.php';
        break;
    
    case '/test_icon_notification':
        include_once 'test_icon_notification.php';
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
                "GET /ninos" => "Obtener lista de todos los niños de la empresa (requiere autenticación)",
                "GET /salones" => "Obtener lista de salones/grupos (requiere autenticación)",
                "GET /personal_salon?salon_id={id}" => "Obtener personal asignado a un salón (requiere autenticación)",
                "POST /update_fcm_token" => "Actualizar token FCM del usuario (requiere autenticación)",
                "POST /send_notification" => "Enviar notificaciones push (solo administradores)"
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
