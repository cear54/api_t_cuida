<?php
/**
 * Script de verificación de configuración de variables de entorno
 * Ejecutar para verificar que todo está correctamente configurado
 */

require_once 'config/env.php';

echo "=================================\n";
echo "  VERIFICACIÓN DE CONFIGURACIÓN  \n";
echo "=================================\n\n";

// Verificar carga de variables
echo "📋 VARIABLES DE ENTORNO:\n";
echo "------------------------\n";

$variables = [
    'DB_HOST' => 'Base de datos - Host',
    'DB_NAME' => 'Base de datos - Nombre',
    'DB_USERNAME' => 'Base de datos - Usuario',
    'DB_PASSWORD' => 'Base de datos - Contraseña',
    'JWT_SECRET_KEY' => 'JWT - Clave secreta',
    'JWT_EXPIRE_HOURS' => 'JWT - Horas de expiración',
    'APP_ENV' => 'Aplicación - Entorno',
    'APP_DEBUG' => 'Aplicación - Debug',
    'CORS_ORIGIN' => 'CORS - Origen permitido'
];

foreach ($variables as $var => $description) {
    $value = EnvLoader::get($var);
    $status = $value !== null ? '✅' : '❌';
    
    // Ocultar contraseñas y claves secretas
    if (in_array($var, ['DB_PASSWORD', 'JWT_SECRET_KEY'])) {
        $displayValue = $value ? '[CONFIGURADO]' : '[NO CONFIGURADO]';
    } else {
        $displayValue = $value ?: '[NO CONFIGURADO]';
    }
    
    echo sprintf("%-20s %s %s: %s\n", $var, $status, $description, $displayValue);
}

echo "\n🔗 PRUEBA DE CONEXIÓN:\n";
echo "----------------------\n";

// Probar conexión a base de datos
try {
    require_once 'config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "✅ Conexión a base de datos: EXITOSA\n";
        
        // Verificar tabla usuarios_app
        $stmt = $conn->prepare("SHOW TABLES LIKE 'usuarios_app'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "✅ Tabla usuarios_app: EXISTE\n";
        } else {
            echo "❌ Tabla usuarios_app: NO EXISTE\n";
        }
    } else {
        echo "❌ Conexión a base de datos: FALLIDA\n";
    }
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}

// Probar JWT
echo "\n🔐 PRUEBA DE JWT:\n";
echo "----------------\n";

try {
    require_once 'utils/JWTHandler.php';
    
    // Datos de prueba
    $testData = [
        'id' => 1,
        'usuario' => 'test_user',
        'tipo_usuario' => 'familia',
        'nino_id' => 1
    ];
    
    // Generar token
    $token = JWTHandler::generateToken($testData);
    echo "✅ Generación de token: EXITOSA\n";
    
    // Verificar token
    $decoded = JWTHandler::verifyToken($token);
    if ($decoded && $decoded['user_id'] == 1) {
        echo "✅ Verificación de token: EXITOSA\n";
    } else {
        echo "❌ Verificación de token: FALLIDA\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error en JWT: " . $e->getMessage() . "\n";
}

echo "\n📊 RESUMEN:\n";
echo "----------\n";
$env = EnvLoader::get('APP_ENV', 'unknown');
$debug = EnvLoader::getBool('APP_DEBUG', false);

echo "Entorno: " . strtoupper($env) . "\n";
echo "Debug: " . ($debug ? 'ACTIVADO' : 'DESACTIVADO') . "\n";
echo "Configuración: " . (EnvLoader::has('DB_HOST') ? 'COMPLETA' : 'INCOMPLETA') . "\n";

echo "\n=================================\n";
echo "  VERIFICACIÓN COMPLETADA  \n";
echo "=================================\n";
?>
