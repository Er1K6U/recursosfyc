-- =============================================================================
--  Migración 001 — Acceso restringido a videos
--  Proyecto:  recursosfyc
--  Aplicar a: base de datos recursosfyc (producción y staging)
--
--  INSTRUCCIONES:
--    1. Haz backup de la BD antes de ejecutar.
--    2. Ejecuta en MySQL/MariaDB con un usuario que tenga privilegios ALTER/CREATE.
--    3. Todas las sentencias son idempotentes:
--       - ADD COLUMN solo se ejecuta si la columna no existe (verificar con SHOW COLUMNS).
--       - CREATE TABLE usa IF NOT EXISTS.
--    4. En MySQL < 8.0 no existe "ADD COLUMN IF NOT EXISTS".
--       Usa los SHOW COLUMNS de la sección de verificación para revisar antes.
-- =============================================================================

-- -----------------------------------------------------------------------------
--  VERIFICACIÓN PREVIA — ejecutar para saber qué columnas ya existen
--  antes de lanzar los ALTER TABLE.
-- -----------------------------------------------------------------------------
SHOW COLUMNS FROM recursos LIKE 'es_video';
SHOW COLUMNS FROM recursos LIKE 'ruta_video';
SHOW COLUMNS FROM recursos LIKE 'video_expira_horas';
SHOW COLUMNS FROM recursos LIKE 'url_externa';


-- =============================================================================
--  BLOQUE 1 — ALTER TABLE recursos
--  Agregar columnas de soporte para videos restringidos.
--
--  IMPORTANTE: Si SHOW COLUMNS devuelve una fila para alguna columna,
--  omite el ALTER TABLE correspondiente (ya existe).
-- =============================================================================

-- 1a. Marca el recurso como video privado (1) o recurso normal (0/NULL).
ALTER TABLE recursos
    ADD COLUMN es_video TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Flag: 1 = video privado con token, 0 = recurso descargable normal';

-- 1b. Ruta relativa del archivo de video (o URL externa para videos embed).
--     Puede coincidir con `archivo` en recursos nuevos; en recursos migrados
--     se usa `archivo` como fallback si esta columna está vacía.
ALTER TABLE recursos
    ADD COLUMN ruta_video VARCHAR(500) DEFAULT NULL
    COMMENT 'Ruta relativa del video en private_videos/ o URL externa de video';

-- 1c. Horas de validez del token desde que el participante abre el video.
--     El portal usa VIDEO_EXPIRACION_HORAS como fallback si es NULL.
ALTER TABLE recursos
    ADD COLUMN video_expira_horas SMALLINT UNSIGNED NOT NULL DEFAULT 4
    COMMENT 'Horas de validez del token de acceso al video';

-- 1d. URL externa para recursos descargables (Google Drive, etc.).
--     Null indica que el archivo está en uploads/ o private_videos/.
ALTER TABLE recursos
    ADD COLUMN url_externa VARCHAR(2000) DEFAULT NULL
    COMMENT 'URL de descarga directa externa (ej. Google Drive). NULL = archivo local';


-- =============================================================================
--  BLOQUE 2 — CREATE TABLE tokens_video
--  Tokens de un solo uso con expiración para acceder a video.php.
--  Generados en portal.php al hacer clic en "Ver video".
-- =============================================================================

CREATE TABLE IF NOT EXISTS tokens_video (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token         CHAR(36)        NOT NULL COMMENT 'UUID v4 generado con random_bytes()',
    participante_id INT UNSIGNED  NOT NULL,
    recurso_id    INT UNSIGNED    NOT NULL,
    ip_generado   VARCHAR(45)     DEFAULT NULL COMMENT 'IP en el momento de generación (IPv4/IPv6)',
    expira_en     DATETIME        NOT NULL     COMMENT 'Calculado: NOW() + video_expira_horas',
    creado_en     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usado_en      DATETIME        DEFAULT NULL COMMENT 'NULL = no reproducido aún; se rellena al primer byte servido',

    PRIMARY KEY (id),
    UNIQUE  KEY uq_token          (token),
    KEY     idx_tv_participante   (participante_id),
    KEY     idx_tv_recurso        (recurso_id),
    KEY     idx_tv_expira         (expira_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tokens de acceso temporal a videos privados';


-- =============================================================================
--  BLOQUE 3 — CREATE TABLE recurso_permisos_video
--  Lista de control de acceso (ACL): qué participante puede ver qué video.
--  Administrada desde admin.php → modal de permisos.
-- =============================================================================

CREATE TABLE IF NOT EXISTS recurso_permisos_video (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recurso_id      INT UNSIGNED    NOT NULL,
    participante_id INT UNSIGNED    NOT NULL,
    activo          TINYINT(1)      NOT NULL DEFAULT 1   COMMENT '0 = revocado sin borrar el registro',
    fecha_inicio    DATETIME        DEFAULT NULL         COMMENT 'NULL = sin restricción de fecha de inicio',
    fecha_fin       DATETIME        DEFAULT NULL         COMMENT 'NULL = sin restricción de fecha de fin; usado en portal.php',
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_recurso_participante (recurso_id, participante_id),
    KEY     idx_rpv_participante        (participante_id),
    KEY     idx_rpv_recurso_activo      (recurso_id, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ACL de acceso a videos privados por participante';


-- =============================================================================
--  FIN DE MIGRACIÓN 001
-- =============================================================================
