<?php
// Última actualización: 2025-10-25 - Test diagnóstico
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Debug logging
error_log("🔍 DEBUG: colegiaturas.php iniciado - Versión TEST");

// Si es OPTIONS, responder inmediatamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Test simple para verificar que funciona
echo json_encode([
    'success' => true,
    'message' => 'Archivo colegiaturas.php funciona correctamente',
    'version' => 'test-2025-10-25',
    'timestamp' => date('Y-m-d H:i:s')
]);
exit();
?>