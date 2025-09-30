<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "=== ESTRUCTURA DE LA TABLA USUARIOS ===" . PHP_EOL;
    $stmt = $conn->query('DESCRIBE usuarios');
    while ($row = $stmt->fetch()) {
        printf("%-30s %-20s %-10s %-10s %s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'],
            $row['Default'] ?? ''
        );
    }
    
    echo PHP_EOL . "=== DATOS DE EJEMPLO DE USUARIOS ===" . PHP_EOL;
    $stmt = $conn->query('SELECT * FROM usuarios LIMIT 3');
    while ($row = $stmt->fetch()) {
        echo "ID: " . $row['id'] . " | Nombre: " . ($row['nombre'] ?? 'NULL') . " | Email: " . ($row['email'] ?? 'NULL') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>