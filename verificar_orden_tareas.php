<?php
require_once 'config/database.php';

echo "=== VERIFICACIÓN ORDEN DE TAREAS ===\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "🔍 Probando nuevo orden de tareas (más recientes primero)...\n\n";
    
    // Consulta con el nuevo orden
    $sql = "
        SELECT 
            t.id,
            t.titulo,
            t.fecha_creacion,
            t.fecha_asignacion,
            t.prioridad,
            t.estado,
            s.nombre as salon_nombre
        FROM tareas t
        LEFT JOIN salones s ON t.salon_id = s.id
        WHERE t.activo = 1
        ORDER BY 
            t.fecha_creacion DESC,
            CASE t.prioridad 
                WHEN 'alta' THEN 1 
                WHEN 'media' THEN 2 
                WHEN 'baja' THEN 3 
            END,
            t.fecha_entrega ASC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Tareas encontradas (ordenadas por fecha_creacion DESC):\n\n";
    
    foreach ($tareas as $index => $tarea) {
        echo "📌 " . ($index + 1) . ". ID: {$tarea['id']}\n";
        echo "   📝 Título: {$tarea['titulo']}\n";
        echo "   🏫 Salón: {$tarea['salon_nombre']}\n";
        echo "   📅 Creada: {$tarea['fecha_creacion']}\n";
        echo "   📆 Asignada: {$tarea['fecha_asignacion']}\n";
        echo "   ⚡ Prioridad: {$tarea['prioridad']}\n";
        echo "   📊 Estado: {$tarea['estado']}\n";
        echo "   ---\n";
    }
    
    echo "\n✅ VERIFICACIÓN COMPLETADA\n";
    echo "🎯 Las tareas ahora se muestran:\n";
    echo "   1. Por fecha de creación (más recientes primero)\n";
    echo "   2. Por prioridad (alta, media, baja)\n";
    echo "   3. Por fecha de entrega (más próximas primero)\n\n";
    
    echo "📱 En Flutter, la lista mostrará las tareas en este orden.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>