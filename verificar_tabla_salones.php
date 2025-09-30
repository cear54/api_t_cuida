<?php
include_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== ESTRUCTURA DE LA TABLA SALONES ===\n";
    $stmt = $db->query("DESCRIBE salones");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("%-30s %-20s %-10s %-10s %-10s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default']
        );
    }
    
    echo "\n=== DATOS DE EJEMPLO DE SALONES ===\n";
    $stmt = $db->query("SELECT * FROM salones LIMIT 5");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . " | Nombre: " . $row['nombre'] . "\n";
        echo "Campos disponibles: " . implode(", ", array_keys($row)) . "\n\n";
    }
    
    echo "\n=== RELACION NINOS CON SALONES ===\n";
    $stmt = $db->query("
        SELECT n.id as nino_id, n.nombre as nino_nombre, n.grupo_id, n.salon_id,
               s.nombre as salon_nombre, s.descripcion as salon_descripcion
        FROM ninos n 
        LEFT JOIN salones s ON n.grupo_id = s.id OR n.salon_id = s.id
        WHERE n.id = 2
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Niño ID: " . $row['nino_id'] . "\n";
        echo "Niño Nombre: " . $row['nino_nombre'] . "\n";
        echo "Grupo ID: " . $row['grupo_id'] . "\n";
        echo "Salon ID: " . $row['salon_id'] . "\n";
        echo "Salon Nombre: " . $row['salon_nombre'] . "\n";
        echo "Salon Descripción: " . $row['salon_descripcion'] . "\n\n";
    }
    
} catch(PDOException $exception) {
    echo "Error de conexión: " . $exception->getMessage();
}
?>