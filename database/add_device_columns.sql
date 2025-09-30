-- Script SQL para agregar columnas de dispositivo a usuarios_app
USE estancias;

-- Agregar columna tipo_dispositivo si no existe
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'estancias' 
AND table_name = 'usuarios_app' 
AND column_name = 'tipo_dispositivo';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE usuarios_app ADD COLUMN tipo_dispositivo VARCHAR(20) DEFAULT NULL COMMENT "Tipo de dispositivo: android, ios, web"',
    'SELECT "Columna tipo_dispositivo ya existe" as mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar columna version_sistema si no existe
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'estancias' 
AND table_name = 'usuarios_app' 
AND column_name = 'version_sistema';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE usuarios_app ADD COLUMN version_sistema VARCHAR(50) DEFAULT NULL COMMENT "Versión del sistema operativo"',
    'SELECT "Columna version_sistema ya existe" as mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar columna modelo_dispositivo si no existe
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'estancias' 
AND table_name = 'usuarios_app' 
AND column_name = 'modelo_dispositivo';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE usuarios_app ADD COLUMN modelo_dispositivo VARCHAR(100) DEFAULT NULL COMMENT "Modelo del dispositivo"',
    'SELECT "Columna modelo_dispositivo ya existe" as mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mostrar estructura final
DESCRIBE usuarios_app;