<?php
include_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar si existe la tabla suscripciones
    $query = "SHOW TABLES LIKE 'suscripciones'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabla 'suscripciones' existe\n\n";
        
        // Mostrar estructura
        $query = "DESCRIBE suscripciones";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        echo "Estructura de la tabla:\n";
        echo "-------------------------\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
        
        // Mostrar algunos registros
        echo "\n\nRegistros actuales:\n";
        echo "-------------------------\n";
        $query = "SELECT * FROM suscripciones LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
            echo "\n";
        }
    } else {
        echo "✗ La tabla 'suscripciones' NO existe\n";
        echo "\nSe debe crear la tabla con la siguiente estructura:\n\n";
        echo "CREATE TABLE suscripciones (\n";
        echo "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
        echo "    empresa_id INT NOT NULL,\n";
        echo "    fecha_inicio DATE NOT NULL,\n";
        echo "    fecha_fin DATE,\n";
        echo "    fecha_fin_prueba DATE,\n";
        echo "    activo TINYINT(1) DEFAULT 1,\n";
        echo "    tipo_suscripcion VARCHAR(50),\n";
        echo "    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        echo "    FOREIGN KEY (empresa_id) REFERENCES empresas(id)\n";
        echo ");\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
