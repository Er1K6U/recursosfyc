-- =============================================================================
--  Migración 002 — Duración de video en horas decimales
--  Proyecto:  recursosfyc
--
--  Problema que resuelve:
--    video_expira_horas era SMALLINT UNSIGNED, por lo que valores sub-hora
--    como 0.0833 (5 minutos) se truncaban a 0, haciendo imposible definir
--    duraciones menores a 1 hora.
--
--  Solución:
--    Cambiar a DECIMAL(6,4) permite almacenar fracciones de hora con
--    4 decimales de precisión (ej: 0.0833 = 5 min, 0.5 = 30 min).
--
--  Seguridad:
--    MODIFY COLUMN en MySQL/MariaDB convierte los valores existentes
--    automáticamente. Un registro con valor 4 (SMALLINT) se convierte
--    en 4.0000 (DECIMAL) sin pérdida de datos.
--
--  INSTRUCCIONES:
--    1. Haz backup de la BD antes de ejecutar.
--    2. Verificar tipo actual con SHOW COLUMNS (línea de abajo).
--    3. Ejecutar el ALTER TABLE.
-- =============================================================================

-- Verificación previa — muestra tipo actual
SHOW COLUMNS FROM recursos LIKE 'video_expira_horas';

-- Cambio de tipo: SMALLINT → DECIMAL(6,4)
ALTER TABLE recursos
    MODIFY COLUMN video_expira_horas DECIMAL(6,4) NOT NULL DEFAULT 4.0000
    COMMENT 'Horas de validez del token (acepta fracciones: 0.0833 = 5 min, 0.5 = 30 min)';

-- Verificación post-cambio
SHOW COLUMNS FROM recursos LIKE 'video_expira_horas';

-- =============================================================================
--  FIN DE MIGRACIÓN 002
-- =============================================================================
