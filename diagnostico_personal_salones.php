<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== DIAGNÓSTICO DE TABLA PERSONAL Y RELACIÓN CON SALONES ===\n\n";
    
    // 1. Verificar si la tabla personal existe
    $stmt = $db->query("SHOW TABLES LIKE 'personal'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "❌ ERROR: La tabla 'personal' no existe\n";
        exit;
    } else {
        echo "✅ La tabla 'personal' existe\n\n";
    }
    
    // 2. Verificar estructura de la tabla personal
    echo "=== ESTRUCTURA DE LA TABLA PERSONAL ===\n";
    $stmt = $db->query("DESCRIBE personal");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_salon_id = false;
    foreach ($structure as $column) {
        echo "- {$column['Field']}: {$column['Type']}\n";
        if ($column['Field'] === 'salon_id') {
            $has_salon_id = true;
        }
    }
    
    if (!$has_salon_id) {
        echo "\n❌ ERROR: No se encontró la columna 'salon_id' en la tabla personal\n";
    } else {
        echo "\n✅ La columna 'salon_id' existe en la tabla personal\n";
    }
    
    // 3. Verificar datos totales en personal
    echo "\n=== DATOS EN TABLA PERSONAL ===\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM personal");
    $totalPersonal = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total de registros en personal: $totalPersonal\n";
    
    if ($totalPersonal > 0) {
        // Mostrar algunos registros de ejemplo
        $stmt = $db->query("SELECT id, nombre, apellido_paterno, apellido_materno, puesto_id, salon_id, activo, empresa_id FROM personal LIMIT 10");
        $ejemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nEjemplos de registros:\n";
        foreach ($ejemplos as $persona) {
            echo "- ID: {$persona['id']} | {$persona['nombre']} {$persona['apellido_paterno']} {$persona['apellido_materno']} | Puesto_ID: {$persona['puesto_id']} | Salon_ID: {$persona['salon_id']} | Activo: {$persona['activo']} | Empresa: {$persona['empresa_id']}\n";
        }
    }
    
    // 4. Verificar datos en salones
    echo "\n=== DATOS EN TABLA SALONES ===\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM salones WHERE activo = 1");
    $totalSalones = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total de salones activos: $totalSalones\n";
    
    if ($totalSalones > 0) {
        $stmt = $db->query("SELECT id, nombre, empresa_id FROM salones WHERE activo = 1 LIMIT 10");
        $salones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nSalones disponibles:\n";
        foreach ($salones as $salon) {
            echo "- ID: {$salon['id']} | {$salon['nombre']} | Empresa: {$salon['empresa_id']}\n";
        }
    }
    
    // 5. Verificar relación entre personal y salones
    echo "\n=== VERIFICACIÓN DE RELACIÓN PERSONAL-SALONES ===\n";
    $stmt = $db->query("
        SELECT 
            s.id as salon_id,
            s.nombre as salon_nombre,
            COUNT(p.id) as personal_count
        FROM salones s
        LEFT JOIN personal p ON s.id = p.salon_id AND p.activo = 1
        WHERE s.activo = 1
        GROUP BY s.id, s.nombre
        ORDER BY s.nombre
    ");
    $relacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($relacion)) {
        echo "❌ No se encontraron salones o problemas en la relación\n";
    } else {
        echo "Relación Personal por Salón:\n";
        foreach ($relacion as $rel) {
            echo "- Salón '{$rel['salon_nombre']}' (ID: {$rel['salon_id']}) -> {$rel['personal_count']} personal asignado\n";
        }
    }
    
    // 6. Probar consulta específica del endpoint
    echo "\n=== PRUEBA DE CONSULTA DEL ENDPOINT ===\n";
    
    // Obtener un salon_id de ejemplo
    $stmt = $db->query("SELECT id FROM salones WHERE activo = 1 LIMIT 1");
    $salonEjemplo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($salonEjemplo) {
        $salonId = $salonEjemplo['id'];
        echo "Probando con salon_id: $salonId\n";
        
        $stmt = $db->prepare("
            SELECT 
                p.id,
                p.nombre,
                p.apellido_paterno,
                p.apellido_materno,
                p.puesto_id,
                p.salon_id,
                p.activo,
                p.empresa_id
            FROM personal p 
            WHERE p.salon_id = :salon_id 
              AND p.activo = 1
        ");
        
        $stmt->bindParam(':salon_id', $salonId, PDO::PARAM_INT);
        $stmt->execute();
        $personalEncontrado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Personal encontrado para salon_id $salonId: " . count($personalEncontrado) . "\n";
        
        if (!empty($personalEncontrado)) {
            foreach ($personalEncontrado as $persona) {
                echo "  - {$persona['nombre']} {$persona['apellido_paterno']} {$persona['apellido_materno']} (Puesto ID: {$persona['puesto_id']})\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>