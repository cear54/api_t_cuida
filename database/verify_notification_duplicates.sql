-- ============================================
-- VERIFICAR REGISTROS DUPLICADOS EN NOTIFICACIONES
-- T-Cuida - Diagnóstico de base de datos
-- ============================================

-- 1. Ver mensajes con múltiples registros (DUPLICADOS)
SELECT 
    mensaje_id,
    titulo,
    COUNT(*) as total_registros,
    COUNT(DISTINCT token_fcm) as tokens_unicos,
    COUNT(*) - COUNT(DISTINCT token_fcm) as registros_duplicados,
    MIN(fecha_envio) as fecha_envio,
    GROUP_CONCAT(DISTINCT usuario_id ORDER BY usuario_id) as usuarios_afectados
FROM notificaciones
GROUP BY mensaje_id, titulo
HAVING COUNT(*) > COUNT(DISTINCT token_fcm)  -- Más registros que tokens únicos = DUPLICADOS
ORDER BY fecha_envio DESC
LIMIT 20;

-- 2. Contar total de registros duplicados vs únicos
SELECT 
    'Total registros en BD' as metrica,
    COUNT(*) as valor
FROM notificaciones

UNION ALL

SELECT 
    'Mensajes únicos (mensaje_id)' as metrica,
    COUNT(DISTINCT mensaje_id) as valor
FROM notificaciones

UNION ALL

SELECT 
    'Registros con duplicados' as metrica,
    COUNT(*) as valor
FROM notificaciones
WHERE mensaje_id IN (
    SELECT mensaje_id
    FROM notificaciones
    GROUP BY mensaje_id
    HAVING COUNT(*) > COUNT(DISTINCT token_fcm)
)

UNION ALL

SELECT 
    'Mensajes con duplicados' as metrica,
    COUNT(DISTINCT mensaje_id) as valor
FROM notificaciones
WHERE mensaje_id IN (
    SELECT mensaje_id
    FROM notificaciones
    GROUP BY mensaje_id
    HAVING COUNT(*) > COUNT(DISTINCT token_fcm)
);

-- 3. Ver detalle de UN mensaje duplicado (ejemplo)
SELECT 
    n.id,
    n.mensaje_id,
    n.titulo,
    n.usuario_id,
    n.token_fcm,
    n.estado,
    n.fecha_envio,
    u.nombre_usuario,
    u.email_usuario
FROM notificaciones n
LEFT JOIN usuarios_app u ON n.usuario_id = u.id
WHERE n.mensaje_id = (
    -- Seleccionar el mensaje más reciente con duplicados
    SELECT mensaje_id
    FROM notificaciones
    GROUP BY mensaje_id
    HAVING COUNT(*) > COUNT(DISTINCT token_fcm)
    ORDER BY MAX(fecha_envio) DESC
    LIMIT 1
)
ORDER BY n.fecha_envio, n.id;

-- 4. Ver notificaciones de las últimas horas (para ver si corrección funcionó)
SELECT 
    DATE_FORMAT(fecha_envio, '%Y-%m-%d %H:%i') as hora_envio,
    mensaje_id,
    titulo,
    COUNT(*) as total_registros,
    COUNT(DISTINCT token_fcm) as tokens_unicos,
    CASE 
        WHEN COUNT(*) = COUNT(DISTINCT token_fcm) THEN '✅ Sin duplicados'
        ELSE '❌ CON DUPLICADOS'
    END as estado
FROM notificaciones
WHERE fecha_envio >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
GROUP BY mensaje_id, titulo, DATE_FORMAT(fecha_envio, '%Y-%m-%d %H:%i')
ORDER BY fecha_envio DESC;

-- 5. Comparar mensajes ANTES y DESPUÉS de la corrección
SELECT 
    '🕐 Últimas 4 horas (después corrección)' as periodo,
    COUNT(DISTINCT mensaje_id) as total_mensajes,
    COUNT(*) as total_registros,
    ROUND(COUNT(*) / COUNT(DISTINCT mensaje_id), 2) as promedio_registros_por_mensaje,
    SUM(CASE WHEN cnt > 1 THEN 1 ELSE 0 END) as mensajes_duplicados
FROM (
    SELECT mensaje_id, COUNT(*) as cnt
    FROM notificaciones
    WHERE fecha_envio >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
    GROUP BY mensaje_id
) as recent

UNION ALL

SELECT 
    '📅 Hace más de 4 horas (antes corrección)' as periodo,
    COUNT(DISTINCT mensaje_id) as total_mensajes,
    COUNT(*) as total_registros,
    ROUND(COUNT(*) / COUNT(DISTINCT mensaje_id), 2) as promedio_registros_por_mensaje,
    SUM(CASE WHEN cnt > 1 THEN 1 ELSE 0 END) as mensajes_duplicados
FROM (
    SELECT mensaje_id, COUNT(*) as cnt
    FROM notificaciones
    WHERE fecha_envio < DATE_SUB(NOW(), INTERVAL 4 HOUR)
    GROUP BY mensaje_id
) as old;

-- 6. Ver el último mensaje enviado y cuántos registros tiene
SELECT 
    n.mensaje_id,
    n.titulo,
    n.mensaje,
    n.tipo,
    COUNT(*) as total_registros,
    COUNT(DISTINCT n.token_fcm) as tokens_unicos,
    MAX(n.fecha_envio) as fecha_envio,
    GROUP_CONCAT(DISTINCT n.usuario_id ORDER BY n.usuario_id) as usuarios_ids
FROM notificaciones n
WHERE n.mensaje_id = (
    SELECT mensaje_id 
    FROM notificaciones 
    ORDER BY fecha_envio DESC 
    LIMIT 1
)
GROUP BY n.mensaje_id, n.titulo, n.mensaje, n.tipo;
