-- Migración para agregar campos del formulario de asistencia
-- Ejecutar este script para agregar las nuevas columnas a la tabla asistencias

-- Agregar columnas para el formulario de asistencia
ALTER TABLE `asistencias` 
ADD COLUMN `temperatura` DECIMAL(4,2) DEFAULT NULL COMMENT 'Temperatura corporal en grados Celsius',
ADD COLUMN `se_presento_enfermo` BOOLEAN DEFAULT FALSE COMMENT 'Indica si el niño se presentó enfermo',
ADD COLUMN `descripcion_enfermedad` TEXT DEFAULT NULL COMMENT 'Descripción de la enfermedad o síntomas',
ADD COLUMN `se_presento_limpio` BOOLEAN DEFAULT TRUE COMMENT 'Indica si el niño se presentó limpio',
ADD COLUMN `trajo_mochila_completa` BOOLEAN DEFAULT TRUE COMMENT 'Indica si trajo su mochila completa',
ADD COLUMN `se_presento_buen_estado_fisico` BOOLEAN DEFAULT TRUE COMMENT 'Indica si se presentó en buen estado físico';

-- Crear índices para consultas optimizadas
CREATE INDEX IF NOT EXISTS `idx_asistencias_fecha_enfermo` ON `asistencias` (`fecha`, `se_presento_enfermo`);
CREATE INDEX IF NOT EXISTS `idx_asistencias_temperatura` ON `asistencias` (`temperatura`);

-- Comentario de la migración
INSERT INTO `migrations_log` (`script_name`, `executed_at`, `description`) 
VALUES ('add_attendance_form_fields.sql', NOW(), 'Agregar campos del formulario de asistencia')
ON DUPLICATE KEY UPDATE executed_at = NOW();

-- Verificar la estructura actualizada
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'asistencias'
ORDER BY ORDINAL_POSITION;
