-- ============================================================
-- Migración 006: Trabajo integrador final
-- Proyecto: recursosfyc · F&C Consultores
-- Fecha: 2026-04-30
-- Entorno: LOCAL ÚNICAMENTE
-- ============================================================
-- Ejecutar manualmente en phpMyAdmin o consola MySQL.
-- NO ejecutar en producción sin revisión previa.
-- ============================================================

-- ── 1. Extender tabla admins: roles + nombre ──────────────────
--       'admin'     = acceso total al panel
--       'evaluador' = solo puede revisar y calificar entregas
--         de los eventos que tenga asignados en trabajo_evaluador_eventos
ALTER TABLE admins
    ADD COLUMN rol    ENUM('admin','evaluador') NOT NULL DEFAULT 'admin'
        COMMENT 'admin = acceso total; evaluador = solo trabajo integrador de sus eventos',
    ADD COLUMN nombre VARCHAR(150) NULL
        COMMENT 'Nombre visible en la interfaz de evaluación';

-- ── 2. Configuración del trabajo integrador por evento ─────────
--       Un único trabajo integrador por evento (UNIQUE evento_id).
--       activo = 0 oculta el bloque en portal.php sin borrar datos.
CREATE TABLE trabajo_integrador_config (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    evento_id           INT UNSIGNED     NOT NULL,
    activo              TINYINT(1)       NOT NULL DEFAULT 0
        COMMENT '0 = bloque oculto en portal; 1 = visible para participantes',
    titulo              VARCHAR(300)     NOT NULL DEFAULT 'Trabajo integrador final',
    descripcion         TEXT             NULL,
    fecha_limite        DATETIME         NULL
        COMMENT 'NULL = sin límite. Soft: permite entrega tardía pero la marca con entrega_tardia=1',
    permite_reentrega   TINYINT(1)       NOT NULL DEFAULT 1
        COMMENT '1 = puede resubir mientras estado != aprobado',
    calificacion_maxima DECIMAL(5,2)     NOT NULL DEFAULT 100.00,
    creado_en           TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE  KEY uq_tic_evento    (evento_id),
    CONSTRAINT fk_tic_evento FOREIGN KEY (evento_id) REFERENCES eventos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Recursos base del trabajo (guías, rúbricas, formatos) ───
--       Se almacenan en uploads/trabajo/.
--       Descargables por cualquier participante del evento.
CREATE TABLE trabajo_integrador_recursos (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    config_id   INT UNSIGNED     NOT NULL,
    evento_id   INT UNSIGNED     NOT NULL,
    nombre      VARCHAR(300)     NOT NULL,
    descripcion TEXT             NULL,
    archivo     VARCHAR(300)     NOT NULL
        COMMENT 'Nombre de archivo en uploads/trabajo/',
    tipo        VARCHAR(10)      NULL
        COMMENT 'Extensión en minúscula: pdf, docx, xlsx, zip, ppt, pptx, doc, xls',
    tamanio     INT UNSIGNED     NULL
        COMMENT 'Tamaño del archivo en bytes',
    orden       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)       NOT NULL DEFAULT 1,
    creado_en   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tir_config (config_id),
    KEY idx_tir_evento (evento_id),
    CONSTRAINT fk_tir_config FOREIGN KEY (config_id) REFERENCES trabajo_integrador_config(id) ON DELETE CASCADE,
    CONSTRAINT fk_tir_evento FOREIGN KEY (evento_id) REFERENCES eventos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Entregas de participantes + evaluación ──────────────────
--       Una fila por (config_id, participante_id) — UNIQUE.
--       Resubmisión: UPDATE del archivo existente (no nueva fila).
--       participante_id = participantes.id = $_SESSION['participante_id']
--       persona_id      = personas.id (= participantes.id, IDs preservados en migración 004)
--
--       ENUM estado — valores usados por admin.php y portal.php:
--         pendiente        → fila creada sin archivo todavía (estado inicial)
--         entregado        → participante subió archivo (pendiente de revisión)
--         revisado         → evaluador revisó y calificó (ok)
--         requiere_ajustes → evaluador solicita correcciones (permite reentrega si está habilitada)
--         aprobado         → evaluación final, bloquea nuevas entregas
CREATE TABLE trabajo_integrador_entregas (
    id                      BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    config_id               INT UNSIGNED     NOT NULL,
    evento_id               INT UNSIGNED     NOT NULL,
    participante_id         INT UNSIGNED     NOT NULL
        COMMENT 'participantes.id = $_SESSION[participante_id]',
    persona_id              INT UNSIGNED     NOT NULL
        COMMENT 'personas.id; igual a participantes.id para datos migrados desde 004',

    -- Entrega
    archivo                 VARCHAR(300)     NULL
        COMMENT 'Nombre seguro generado: entrega_{evento_id}_{participante_id}_{doc}_{timestamp}.{ext} en private_entregas/',
    nombre_original         VARCHAR(300)     NULL
        COMMENT 'Nombre original del archivo para el header Content-Disposition al descargar',
    tamanio                 INT UNSIGNED     NULL
        COMMENT 'Tamaño en bytes',
    entrega_tardia          TINYINT(1)       NOT NULL DEFAULT 0
        COMMENT '1 = archivo subido después de config.fecha_limite',
    estado                  ENUM(
                                'pendiente',
                                'entregado',
                                'revisado',
                                'requiere_ajustes',
                                'aprobado'
                            )                NOT NULL DEFAULT 'pendiente',
    fecha_entrega           DATETIME         NULL,
    comentario_participante TEXT             NULL,

    -- Evaluación (llenados por admin/evaluador)
    evaluador_id            INT              NULL
        COMMENT 'admins.id — INT (no UNSIGNED) para coincidir con admins.id que es INT firmado',
    calificacion            DECIMAL(5,2)     NULL
        COMMENT 'Rango 0.00 – calificacion_maxima; NULL mientras no haya evaluación',
    comentarios_evaluador   TEXT             NULL,
    fecha_evaluacion        DATETIME         NULL,

    -- Timestamps
    creado_en               TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_tie_participante  (config_id, participante_id),
    KEY         idx_tie_evento       (evento_id),
    KEY         idx_tie_estado       (estado),
    KEY         idx_tie_participante (participante_id),
    CONSTRAINT  fk_tie_config    FOREIGN KEY (config_id)    REFERENCES trabajo_integrador_config(id),
    CONSTRAINT  fk_tie_evento    FOREIGN KEY (evento_id)    REFERENCES eventos(id),
    CONSTRAINT  fk_tie_evaluador FOREIGN KEY (evaluador_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Asignación evaluador → evento ───────────────────────────
--       Un evaluador puede estar asignado a múltiples eventos.
--       descargar_entrega.php y la query de entregas usan esta tabla
--       para autorizar qué ve cada evaluador.
CREATE TABLE trabajo_evaluador_eventos (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id  INT          NOT NULL,
    evento_id INT UNSIGNED NOT NULL,
    creado_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE  KEY uq_tee (admin_id, evento_id),
    CONSTRAINT fk_tee_admin  FOREIGN KEY (admin_id)  REFERENCES admins(id)  ON DELETE CASCADE,
    CONSTRAINT fk_tee_evento FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
