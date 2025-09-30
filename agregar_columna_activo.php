<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "🔧 Agregando columna 'activo' a las tablas...\n\n";
    
    // Agregar columna activo a la tabla asistencias
    try {
        $sql = "ALTER TABLE asistencias ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER updated_at";
        $pdo->exec($sql);
        echo "✅ Columna 'activo' agregada a la tabla 'asistencias'\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠️  La columna 'activo' ya existe en la tabla 'asistencias'\n";
        } else {
            echo "❌ Error al agregar columna a 'asistencias': " . $e->getMessage() . "\n";
        }
    }
    
    // Agregar columna activo a la tabla bitacoras
    try {
        $sql = "ALTER TABLE bitacoras ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER updated_at";
        $pdo->exec($sql);
        echo "✅ Columna 'activo' agregada a la tabla 'bitacoras'\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠️  La columna 'activo' ya existe en la tabla 'bitacoras'\n";
        } else {
            echo "❌ Error al agregar columna a 'bitacoras': " . $e->getMessage() . "\n";
        }
    }
    
    // Agregar columna activo a la tabla salidas
    try {
        $sql = "ALTER TABLE salidas ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER updated_at";
        $pdo->exec($sql);
        echo "✅ Columna 'activo' agregada a la tabla 'salidas'\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠️  La columna 'activo' ya existe en la tabla 'salidas'\n";
        } else {
            echo "❌ Error al agregar columna a 'salidas': " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📋 Verificando estructura actualizada...\n\n";
    
    // Verificar las columnas agregadas
    $tablas = ['asistencias', 'bitacoras', 'salidas'];
    
    foreach ($tablas as $tabla) {
        echo "🔍 Tabla '$tabla':\n";
        try {
            $stmt = $pdo->query("DESCRIBE $tabla");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $activoEncontrado = false;
            foreach ($columns as $column) {
                if ($column['Field'] === 'activo') {
                    echo "  ✅ activo: {$column['Type']} {$column['Null']} Default: {$column['Default']}\n";
                    $activoEncontrado = true;
                    break;
                }
            }
            
            if (!$activoEncontrado) {
                echo "  ❌ Columna 'activo' no encontrada\n";
            }
            
        } catch (PDOException $e) {
            echo "  ❌ Error al verificar tabla '$tabla': " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    echo "🎉 Proceso completado.\n";
    echo "💡 La columna 'activo' permite:\n";
    echo "   - Soft delete (marcar como inactivo en lugar de eliminar)\n";
    echo "   - Activar/desactivar registros temporalmente\n";
    echo "   - Mantener historial completo de datos\n";
    echo "   - Valor por defecto: 1 (activo)\n";
    
} catch (PDOException $e) {
    echo "❌ Error de conexión a la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}
?>
