-- ============================================
-- SCRIPT DE LIMPIEZA DE TOKENS DUPLICADOS (OPCIONAL)
-- T-Cuida - Sistema de Notificaciones
-- ============================================
-- ADVERTENCIA: Este script modifica datos en la base de datos
-- Ejecutar solo si deseas limpiar tokens duplicados
-- ============================================

-- OPCIÓN A: Ver qué se eliminaría (SIN MODIFICAR)
-- Muestra los usuarios cuyo token_app sería puesto en NULL
SELECT 
    u.id,
    u.nombre_usuario,
    u.email_usuario,
    u.tipo_usuario,
    u.token_app,
    u.fecha_creacion
FROM usuarios_app u
WHERE u.token_app IN (
    SELECT token_app
    FROM usuarios_app
    WHERE token_app IS NOT NULL
    GROUP BY token_app
    HAVING COUNT(*) > 1
)
AND u.id NOT IN (
    -- Mantener solo el usuario MÁS RECIENTE por cada token
    SELECT MIN(id)
    FROM usuarios_app
    WHERE token_app IS NOT NULL
    GROUP BY token_app
)
ORDER BY u.token_app, u.id;

-- OPCIÓN B: Limpiar tokens duplicados (MODIFICA DATOS)
-- Descomenta las siguientes líneas para ejecutar
/*
UPDATE usuarios_app u
SET token_app = NULL
WHERE u.id IN (
    SELECT * FROM (
        SELECT u2.id
        FROM usuarios_app u2
        WHERE u2.token_app IN (
            SELECT token_app
            FROM usuarios_app
            WHERE token_app IS NOT NULL
            GROUP BY token_app
            HAVING COUNT(*) > 1
        )
        AND u2.id NOT IN (
            -- Mantener solo el usuario MÁS RECIENTE por cada token duplicado
            SELECT MAX(id)
            FROM usuarios_app
            WHERE token_app IS NOT NULL
            GROUP BY token_app
            HAVING COUNT(*) > 1
        )
    ) AS ids_to_clean
);

SELECT 'Tokens duplicados eliminados correctamente' as resultado;
*/

-- OPCIÓN C: Ver usuarios sin token (para actualizar manualmente)
SELECT 
    id,
    nombre_usuario,
    email_usuario,
    tipo_usuario,
    activo,
    CASE 
        WHEN token_app IS NULL THEN 'Sin token'
        ELSE 'Con token'
    END as estado_token
FROM usuarios_app
WHERE activo = 1
ORDER BY estado_token, tipo_usuario, nombre_usuario;
