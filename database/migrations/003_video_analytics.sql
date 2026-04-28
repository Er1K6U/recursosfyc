-- =============================================================================
--  Migración 003 — Sesiones de visualización de video
--  Proyecto:  recursosfyc
--
--  Crea tabla video_visualizaciones para registrar por token/sesión:
--    started_at      — cuándo inició la reproducción
--    last_seen_at    — último heartbeat recibido (≤ 15 s de precisión)
--    ended_at        — cuándo terminó (NULL si cerró el tab)
--    seconds_watched — segundos reales reproducidos (acumulado desde JS)
--    video_duration  — duración total del archivo (desde JS vid.duration)
--    percent_watched — porcentaje calculado servidor
--    completed       — 1 solo si evento ended disparó
--
--  INSTRUCCIONES:
--    1. Hacer backup de la BD antes de ejecutar.
--    2. CREATE TABLE IF NOT EXISTS es seguro de re-ejecutar.
--    3. Requiere que tokens_video (id INT) ya exista (migración 001).
-- =============================================================================

CREATE TABLE IF NOT EXISTS video_visualizaciones (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_id        INT             NOT NULL                  COMMENT 'FK → tokens_video.id',
    participante_id INT             NOT NULL                  COMMENT 'Desnormalizado para queries del dashboard',
    recurso_id      INT             NOT NULL                  COMMENT 'Desnormalizado para queries del dashboard',
    started_at      DATETIME        NOT NULL                  COMMENT 'Primera vez que play disparó',
    last_seen_at    DATETIME        DEFAULT NULL              COMMENT 'Último heartbeat recibido',
    ended_at        DATETIME        DEFAULT NULL              COMMENT 'NULL si cerró el tab sin terminar',
    seconds_watched INT UNSIGNED    NOT NULL DEFAULT 0        COMMENT 'Segundos reales reproducidos (JS, acumulado)',
    video_duration  INT UNSIGNED    NOT NULL DEFAULT 0        COMMENT 'Duración total del video en segundos (JS)',
    percent_watched DECIMAL(5,2)    NOT NULL DEFAULT 0.00     COMMENT 'seconds_watched / video_duration * 100',
    completed       TINYINT(1)      NOT NULL DEFAULT 0        COMMENT '1 solo si evento ended del video HTML5 disparó',
    user_agent      VARCHAR(500)    DEFAULT NULL,
    ip              VARCHAR(45)     DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_vv_token         (token_id)                COMMENT 'Un token = una sesión de visualización',
    KEY         idx_vv_participante (participante_id),
    KEY         idx_vv_recurso      (recurso_id),
    KEY         idx_vv_started      (started_at),

    CONSTRAINT fk_vv_token
        FOREIGN KEY (token_id)
        REFERENCES  tokens_video (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Sesiones de visualización de video por participante/token';

-- =============================================================================
--  POST-MIGRACIÓN: verificar resultado
--  DESCRIBE video_visualizaciones;
-- =============================================================================
