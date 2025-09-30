-- Crear tabla de asistencias para el sistema T-Cuida
-- Ejecutar este script para crear la tabla si no existe

CREATE TABLE IF NOT EXISTS `asistencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nino_id` int(11) NOT NULL,
  `empresa_id` char(36) NOT NULL,
  `fecha` date NOT NULL,
  `hora_entrada` time DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  `observaciones_entrada` text DEFAULT NULL,
  `observaciones_salida` text DEFAULT NULL,
  `educadora_entrada_id` int(11) DEFAULT NULL,
  `educadora_salida_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nino_fecha` (`nino_id`, `fecha`),
  KEY `idx_empresa_fecha` (`empresa_id`, `fecha`),
  KEY `idx_fecha` (`fecha`),
  KEY `fk_asistencias_nino` (`nino_id`),
  KEY `fk_asistencias_empresa` (`empresa_id`),
  KEY `fk_asistencias_educadora_entrada` (`educadora_entrada_id`),
  KEY `fk_asistencias_educadora_salida` (`educadora_salida_id`),
  CONSTRAINT `fk_asistencias_nino` FOREIGN KEY (`nino_id`) REFERENCES `ninos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_educadora_entrada` FOREIGN KEY (`educadora_entrada_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencias_educadora_salida` FOREIGN KEY (`educadora_salida_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear índices adicionales para optimizar consultas
CREATE INDEX IF NOT EXISTS `idx_asistencias_estado` ON `asistencias` (`nino_id`, `fecha`, `hora_entrada`, `hora_salida`);
CREATE INDEX IF NOT EXISTS `idx_asistencias_educadora_fecha` ON `asistencias` (`educadora_entrada_id`, `fecha`);
CREATE INDEX IF NOT EXISTS `idx_asistencias_empresa_educadora` ON `asistencias` (`empresa_id`, `educadora_entrada_id`, `fecha`);

-- Comentarios para documentar la estructura
ALTER TABLE `asistencias` 
COMMENT = 'Tabla para registrar la asistencia diaria de los niños en la estancia infantil';

-- Ejemplos de uso:
-- 1. Registrar entrada: INSERT INTO asistencias (nino_id, empresa_id, fecha, hora_entrada, educadora_entrada_id) VALUES (1, 1, '2025-09-07', '08:30:00', 2);
-- 2. Registrar salida: UPDATE asistencias SET hora_salida = '17:00:00', educadora_salida_id = 2 WHERE nino_id = 1 AND fecha = '2025-09-07' AND empresa_id = 1;
-- 3. Consultar asistencia del día: SELECT * FROM asistencias WHERE fecha = CURDATE() AND empresa_id = 1;
