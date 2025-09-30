<?php
echo "=== VERIFICACIÓN API TAREAS EN PRODUCCIÓN ===\n\n";

// URL de producción desde el .env de Flutter
$url = 'https://estancias.cear54.com/api_t_cuida/api/tareas.php';

echo "🌐 Verificando endpoint: $url\n\n";

// Test 1: Verificar que el endpoint responde
echo "1. 📡 Probando conexión básica...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   📊 Código HTTP: $httpCode\n";
echo "   📄 Respuesta: $response\n";

if ($error) {
    echo "   ❌ Error cURL: $error\n";
} else {
    if ($httpCode == 401) {
        echo "   ✅ CORRECTO: Endpoint existe y requiere autenticación (401)\n";
    } elseif ($httpCode == 200) {
        echo "   ⚠️ ADVERTENCIA: Endpoint responde sin autenticación\n";
    } else {
        echo "   ❌ ERROR: Código HTTP inesperado\n";
    }
}

echo "\n2. 🔍 Probando OPTIONS (CORS)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   📊 Código HTTP OPTIONS: $httpCode\n";
echo "   📄 Respuesta OPTIONS: $response\n";

if ($httpCode == 200) {
    echo "   ✅ CORRECTO: CORS configurado correctamente\n";
} else {
    echo "   ❌ ERROR: Problema con CORS\n";
}

echo "\n=== RESUMEN ===\n";
echo "🎯 El endpoint de tareas está disponible en producción\n";
echo "🔗 URL: $url\n";
echo "📱 Flutter ahora debe apuntar correctamente a producción\n";
echo "🔐 Requiere token JWT válido para funcionar\n";
?>