-- Migración: Agregar campos de comentarios de familia a la tabla bitacoras
-- Fecha: 2026-02-17
-- Descripción: Permite que las familias agreguen comentarios a la bitácora después de la salida

ALTER TABLE bitacoras 
ADD COLUMN comentarios_familia TEXT NULL COMMENT 'Comentarios agregados por la familia' AFTER observaciones,
ADD COLUMN comentarios_familia_user_id INT NULL COMMENT 'ID del usuario familiar que agregó el comentario',
ADD COLUMN comentarios_familia_fecha DATETIME NULL COMMENT 'Fecha y hora en que se agregó el comentario';

-- Agregar índice para búsquedas
CREATE INDEX idx_comentarios_familia_user ON bitacoras(comentarios_familia_user_id);
CREATE INDEX idx_comentarios_familia_fecha ON bitacoras(comentarios_familia_fecha);
