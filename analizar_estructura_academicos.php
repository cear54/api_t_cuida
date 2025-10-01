<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "🔍 Analizando estructura para usuarios académicos...\n\n";
    
    // Verificar estructura de usuarios_app
    echo "📊 Estructura de tabla usuarios_app:\n";
    $stmt = $conn->prepare("DESCRIBE usuarios_app");
    $stmt->execute();
    $usuarios_app_estructura = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($usuarios_app_estructura as $campo) {
        echo "- {$campo['Field']}: {$campo['Type']}\n";
    }
    
    echo "\n📊 Muestra de datos en usuarios_app:\n";
    $stmt = $conn->prepare("SELECT * FROM usuarios_app LIMIT 3");
    $stmt->execute();
    $muestra = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($muestra as $index => $usuario) {
        echo "Usuario " . ($index + 1) . ":\n";
        foreach ($usuario as $campo => $valor) {
            echo "  - $campo: " . ($valor ?? 'NULL') . "\n";
        }
        echo "\n";
    }
    
    echo "🏫 Estructura de tabla salones:\n";
    $stmt = $conn->prepare("DESCRIBE salones");
    $stmt->execute();
    $salones_estructura = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($salones_estructura as $campo) {
        echo "- {$campo['Field']}: {$campo['Type']}\n";
    }
    
    echo "\n📊 Muestra de salones:\n";
    $stmt = $conn->prepare("SELECT * FROM salones LIMIT 3");
    $stmt->execute();
    $salones_muestra = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($salones_muestra as $index => $salon) {
        echo "Salón " . ($index + 1) . ":\n";
        foreach ($salon as $campo => $valor) {
            echo "  - $campo: " . ($valor ?? 'NULL') . "\n";
        }
        echo "\n";
    }
    
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>