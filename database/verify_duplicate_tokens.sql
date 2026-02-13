-- ============================================
-- SCRIPT DE VERIFICACIÓN DE TOKENS DUPLICADOS
-- T-Cuida - Sistema de Notificaciones
-- ============================================

-- 1. Verificar cuántos tokens están duplicados
SELECT 
    token_app,
    COUNT(*) as cantidad_usuarios,
    GROUP_CONCAT(id) as ids_usuarios,
    GROUP_CONCAT(nombre_usuario SEPARATOR ', ') as nombres
FROM usuarios_app
WHERE token_app IS NOT NULL
GROUP BY token_app
HAVING COUNT(*) > 1
ORDER BY cantidad_usuarios DESC;

-- 2. Estadísticas generales de tokens
SELECT 
    'Total usuarios con token' as metrica,
    COUNT(*) as valor
FROM usuarios_app
WHERE token_app IS NOT NULL

UNION ALL

SELECT 
    'Tokens únicos' as metrica,
    COUNT(DISTINCT token_app) as valor
FROM usuarios_app
WHERE token_app IS NOT NULL

UNION ALL

SELECT 
    'Usuarios con tokens duplicados' as metrica,
    COUNT(*) as valor
FROM usuarios_app
WHERE token_app IN (
    SELECT token_app
    FROM usuarios_app
    WHERE token_app IS NOT NULL
    GROUP BY token_app
    HAVING COUNT(*) > 1
)

UNION ALL

SELECT 
    'Tokens con múltiples usuarios' as metrica,
    COUNT(*) as valor
FROM (
    SELECT token_app
    FROM usuarios_app
    WHERE token_app IS NOT NULL
    GROUP BY token_app
    HAVING COUNT(*) > 1
) as duplicated;

-- 3. Ver detalle de usuarios con el mismo token
SELECT 
    u.token_app,
    u.id,
    u.nombre_usuario,
    u.email_usuario,
    u.tipo_usuario,
    u.empresa_id,
    u.activo,
    u.tipo_dispositivo,
    DATE_FORMAT(u.fecha_creacion, '%Y-%m-%d %H:%i') as fecha_registro
FROM usuarios_app u
WHERE u.token_app IN (
    SELECT token_app
    FROM usuarios_app
    WHERE token_app IS NOT NULL
    GROUP BY token_app
    HAVING COUNT(*) > 1
)
ORDER BY u.token_app, u.id;

-- 4. Contar notificaciones enviadas recientemente (últimas 24 horas)
SELECT 
    DATE_FORMAT(fecha_envio, '%Y-%m-%d %H:00') as hora,
    tipo,
    COUNT(*) as total_enviadas,
    COUNT(DISTINCT token_fcm) as tokens_unicos,
    COUNT(*) - COUNT(DISTINCT token_fcm) as duplicados
FROM notificaciones
WHERE fecha_envio >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY DATE_FORMAT(fecha_envio, '%Y-%m-%d %H:00'), tipo
ORDER BY fecha_envio DESC;

-- 5. Ver el token más duplicado y sus usuarios
SELECT 
    token_app,
    COUNT(*) as total_usuarios_con_mismo_token,
    GROUP_CONCAT(CONCAT(nombre_usuario, ' (', tipo_usuario, ')') SEPARATOR ' | ') as usuarios
FROM usuarios_app
WHERE token_app IS NOT NULL
GROUP BY token_app
ORDER BY total_usuarios_con_mismo_token DESC
LIMIT 5;
