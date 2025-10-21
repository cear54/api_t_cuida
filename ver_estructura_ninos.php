<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query('DESCRIBE ninos');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== ESTRUCTURA DE LA TABLA NINOS ===\n\n";
    
    foreach ($columns as $column) {
        echo sprintf("%-25s | %-20s | %s\n", 
            $column['Field'], 
            $column['Type'], 
            ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL')
        );
    }
    
    echo "\n=== CAMPOS DE CONTACTO DE EMERGENCIA ===\n\n";
    
    foreach ($columns as $column) {
        if (stripos($column['Field'], 'contacto') !== false || 
            stripos($column['Field'], 'telefono') !== false) {
            echo sprintf("%-25s | %-20s | %s\n", 
                $column['Field'], 
                $column['Type'], 
                ($column['Null'] === 'YES' ? 'NULL' : 'NOT NULL')
            );
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>