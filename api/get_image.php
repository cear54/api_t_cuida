<?php
// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit();
}

// Validar que sea método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener el nombre del archivo de la URL
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    http_response_code(400);
    echo json_encode(['error' => 'Nombre de archivo requerido']);
    exit();
}

// Sanitizar el nombre del archivo para seguridad
$filename = basename($filename);

// Ruta base donde están las imágenes - ajustando la ruta
$imagePath = $_SERVER['DOCUMENT_ROOT'] . '/kid_care/public/uploads/ninos/' . $filename;

// Debug: log de la ruta que se está intentando
error_log("Intentando acceder a imagen: " . $imagePath);

// Verificar que el archivo existe
if (!file_exists($imagePath)) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    http_response_code(404);
    echo json_encode([
        'error' => 'Imagen no encontrada',
        'path' => $imagePath,
        'filename' => $filename
    ]);
    exit();
}

// Verificar que es un archivo de imagen válido
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de archivo no válido']);
    exit();
}

// Determinar el tipo MIME
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];
$mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

// Establecer headers para la imagen
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($imagePath));
header('Cache-Control: public, max-age=3600');
header('Access-Control-Allow-Origin: *');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Servir la imagen
readfile($imagePath);
exit();
?>
