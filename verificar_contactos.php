<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query('DESCRIBE ninos');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Campos de contacto en la tabla 'ninos':\n";
    echo "=====================================\n";
    
    foreach ($columns as $column) {
        if (stripos($column['Field'], 'contacto') !== false || 
            stripos($column['Field'], 'telefono') !== false ||
            stripos($column['Field'], 'emergencia') !== false) {
            echo $column['Field'] . ' - ' . $column['Type'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>