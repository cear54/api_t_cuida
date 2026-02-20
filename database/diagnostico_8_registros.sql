-- ============================================
-- DIAGNÓSTICO: ¿Por qué 8 registros en BD?
-- Ejecutar este query para ver el último mensaje general
-- ============================================

-- Ver el último mensaje general enviado con todos sus detalles
SELECT 
    n.id,
    n.mensaje_id,
    n.usuario_id,
    n.token_fcm,
    n.titulo,
    n.tipo,
    n.fecha_envio,
    u.nombre_usuario,
    u.email_usuario,
    u.tipo_usuario
FROM notificaciones n
LEFT JOIN usuarios_app u ON n.usuario_id = u.id
WHERE n.mensaje_id = (
    SELECT mensaje_id 
    FROM notificaciones 
    WHERE tipo = 'general'
    ORDER BY fecha_envio DESC 
    LIMIT 1
)
ORDER BY n.id;

-- Analizar: ¿Son los 8 tokens IGUALES o DIFERENTES?
SELECT 
    n.mensaje_id,
    n.titulo,
    COUNT(*) as total_registros,
    COUNT(DISTINCT n.token_fcm) as tokens_diferentes,
    COUNT(DISTINCT n.usuario_id) as usuarios_diferentes,
    CASE 
        WHEN COUNT(*) = COUNT(DISTINCT n.token_fcm) THEN '✅ Tokens diferentes (correcto si 8 usuarios = 8 dispositivos)'
        WHEN COUNT(*) > COUNT(DISTINCT n.token_fcm) THEN '❌ PROBLEMA: Múltiples registros con mismo token'
        ELSE 'Caso raro'
    END as diagnostico
FROM notificaciones n
WHERE n.mensaje_id = (
    SELECT mensaje_id 
    FROM notificaciones 
    WHERE tipo = 'general'
    ORDER BY fecha_envio DESC 
    LIMIT 1
)
GROUP BY n.mensaje_id, n.titulo;

-- Ver cuántos usuarios ACTIVOS hay en total
SELECT 
    tipo_usuario,
    COUNT(*) as total,
    COUNT(DISTINCT token_app) as tokens_unicos,
    SUM(CASE WHEN token_app IS NULL THEN 1 ELSE 0 END) as sin_token
FROM usuarios_app
WHERE activo = 1
GROUP BY tipo_usuario;

-- Ver si hay tokens duplicados en usuarios activos
SELECT 
    token_app,
    COUNT(*) as usuarios_con_mismo_token,
    GROUP_CONCAT(id ORDER BY id) as usuario_ids,
    GROUP_CONCAT(nombre_usuario SEPARATOR ' | ') as nombres
FROM usuarios_app
WHERE activo = 1 AND token_app IS NOT NULL
GROUP BY token_app
HAVING COUNT(*) > 1;
