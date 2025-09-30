-- Script de migración para agregar empresa_id a la tabla asistencias
-- Ejecutar SOLO si ya existe la tabla asistencias sin el campo empresa_id

-- Verificar si la columna empresa_id ya existe
SET @col_exists = 0;
SELECT 1 INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'asistencias' 
AND COLUMN_NAME = 'empresa_id';

-- Solo agregar la columna si no existe
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE asistencias ADD COLUMN empresa_id int(11) NOT NULL AFTER nino_id',
    'SELECT "La columna empresa_id ya existe" AS mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar la foreign key constraint si la columna fue creada
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE asistencias ADD CONSTRAINT fk_asistencias_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT "Foreign key ya existe o no se necesita agregar" AS mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Crear índice para optimizar consultas
SET @sql = IF(@col_exists = 0, 
    'CREATE INDEX idx_empresa_fecha ON asistencias (empresa_id, fecha)',
    'SELECT "Índice ya existe o no se necesita agregar" AS mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Si hay datos existentes, necesitarás poblar empresa_id basándote en los niños
-- IMPORTANTE: Ejecutar esto solo si tienes datos existentes y conoces la empresa_id
-- UPDATE asistencias a 
-- INNER JOIN ninos n ON a.nino_id = n.id 
-- SET a.empresa_id = n.empresa_id 
-- WHERE a.empresa_id IS NULL OR a.empresa_id = 0;

SELECT 'Migración completada. Revisa que todos los registros tengan empresa_id poblado.' AS resultado;
