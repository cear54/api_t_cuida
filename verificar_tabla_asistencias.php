<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "=== ESTRUCTURA DE LA TABLA ASISTENCIAS ===" . PHP_EOL;
    $stmt = $conn->query('DESCRIBE asistencias');
    while ($row = $stmt->fetch()) {
        printf("%-30s %-20s %-10s %-10s %s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'],
            $row['Default'] ?? ''
        );
    }
    
    echo PHP_EOL . "=== DATOS DE EJEMPLO ===" . PHP_EOL;
    $stmt = $conn->query('SELECT * FROM asistencias LIMIT 3');
    while ($row = $stmt->fetch()) {
        echo "ID: " . $row['id'] . " | Fecha: " . $row['fecha'] . " | Entrada: " . ($row['hora_entrada'] ?? 'NULL') . " | Salida: " . ($row['hora_salida'] ?? 'NULL') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>