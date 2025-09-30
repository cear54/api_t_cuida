<?php
// Archivo simple para servir imágenes
$filepath = $_GET['file'] ?? '';

if (empty($filepath)) {
    http_response_code(400);
    die('Archivo requerido');
}

// El filepath ya viene con la ruta completa como "uploads/ninos/archivo.jpg"
// Solo necesitamos obtener el nombre del archivo
$filename = basename($filepath);

// Rutas posibles donde pueden estar las imágenes
$possiblePaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/kid_care/public/' . $filepath,
    $_SERVER['DOCUMENT_ROOT'] . '/' . $filepath,
    $_SERVER['DOCUMENT_ROOT'] . '/api_t_cuida/' . $filepath,
    dirname(__DIR__) . '/' . $filepath,
    $_SERVER['DOCUMENT_ROOT'] . '/kid_care/public/uploads/ninos/' . $filename,
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/ninos/' . $filename,
    $_SERVER['DOCUMENT_ROOT'] . '/api_t_cuida/uploads/ninos/' . $filename,
    dirname(__DIR__) . '/uploads/ninos/' . $filename,
];

$imagePath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $imagePath = $path;
        break;
    }
}

if (!$imagePath) {
    http_response_code(404);
    echo "Imagen no encontrada: $filepath\n";
    echo "Archivo buscado: $filename\n";
    echo "Rutas probadas:\n";
    foreach ($possiblePaths as $path) {
        echo $path . " - " . (file_exists($path) ? "EXISTE" : "NO EXISTE") . "\n";
    }
    exit();
}

// Servir la imagen
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];
$mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

header('Content-Type: ' . $mimeType);
header('Access-Control-Allow-Origin: *');
readfile($imagePath);
?>
