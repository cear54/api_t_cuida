<?php
include_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== ESTRUCTURA DE LA TABLA NINOS ===\n";
    $stmt = $db->query("DESCRIBE ninos");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("%-30s %-20s %-10s %-10s %-10s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default']
        );
    }
    
    echo "\n=== DATOS DE EJEMPLO DE NINOS ===\n";
    $stmt = $db->query("SELECT * FROM ninos LIMIT 2");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . " | Nombre: " . $row['nombre'] . "\n";
        echo "Campos disponibles: " . implode(", ", array_keys($row)) . "\n\n";
    }
    
} catch(PDOException $exception) {
    echo "Error de conexión: " . $exception->getMessage();
}
?>