-- =============================================================================
--  Migración 004 — Fase 0: Base multi-evento
--  Proyecto:  recursosfyc
--  Fecha:     2026-04-28
--
--  QUÉ HACE:
--    1. Crea tabla eventos (el evento por defecto toma datos de configuracion)
--    2. Crea tabla personas (identidades únicas, sin vínculo a evento)
--    3. Migra participantes → personas, PRESERVANDO IDs
--    4. Crea tabla evento_participantes (inscripciones persona × evento)
--    5. Migra participantes → evento_participantes, PRESERVANDO IDs
--    6. Agrega evento_id (DEFAULT 1) a recursos, certificados, accesos
--
--  QUÉ NO HACE:
--    ✗ No toca la tabla participantes (sigue existiendo sin cambios)
--    ✗ No agrega FOREIGN KEY constraints (se agregan en Fase 1)
--    ✗ No modifica ningún archivo PHP
--    ✗ No elimina ni renombra ninguna tabla o columna existente
--
--  SEGURIDAD:
--    - Solo agrega tablas nuevas y columnas con DEFAULT: producción sigue funcionando
--    - CREATE TABLE IF NOT EXISTS: seguro de re-ejecutar
--    - ALTER TABLE ADD COLUMN NO es idempotente en MySQL < 8.0.
--      Ejecutar los SHOW COLUMNS de verificación previa antes de cada ALTER.
--
--  ORDEN OBLIGATORIO DE EJECUCIÓN:
--    Paso 1 → Paso 2 → Paso 3 → Paso 4 → Paso 5 → Paso 6 → Paso 7 → Paso 8 → Paso 9
--
--  PREREQUISITO:
--    Backup de la base de datos antes de ejecutar:
--    mysqldump -u USUARIO -p recursosfyc > backup_pre_004_$(date +%Y%m%d_%H%M).sql
-- =============================================================================


-- =============================================================================
--  VERIFICACIONES PREVIAS — ejecutar antes de aplicar la migración
-- =============================================================================

-- ¿Ya existen las tablas nuevas?
SHOW TABLES LIKE 'eventos';
SHOW TABLES LIKE 'personas';
SHOW TABLES LIKE 'evento_participantes';

-- ¿Ya tiene evento_id alguna de las tablas afectadas?
SHOW COLUMNS FROM recursos     LIKE 'evento_id';
SHOW COLUMNS FROM certificados LIKE 'evento_id';
SHOW COLUMNS FROM accesos      LIKE 'evento_id';

-- Datos actuales en configuracion (para el evento por defecto)
SELECT clave, valor FROM configuracion WHERE clave IN ('titulo_evento', 'banner');

-- Conteo base para validar después
SELECT COUNT(*) AS participantes_actuales FROM participantes;
SELECT COUNT(*) AS recursos_actuales      FROM recursos;
SELECT COUNT(*) AS certificados_actuales  FROM certificados;
SELECT COUNT(*) AS accesos_actuales       FROM accesos;


-- =============================================================================
--  PASO 1 — Crear tabla eventos
-- =============================================================================

CREATE TABLE IF NOT EXISTS eventos (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    slug           VARCHAR(100)  NOT NULL
                       COMMENT 'URL-friendly único. Ej: diplomado-gestion-riesgos-2025',
    nombre         VARCHAR(300)  NOT NULL
                       COMMENT 'Nombre completo del evento',
    titulo_login   VARCHAR(300)  DEFAULT NULL
                       COMMENT 'Título visible en index.php al ingresar',
    banner         VARCHAR(300)  DEFAULT NULL
                       COMMENT 'Nombre de archivo en uploads/ (mismo formato que configuracion.banner)',
    terminos_html  TEXT          DEFAULT NULL
                       COMMENT 'Contenido HTML de los términos por evento. NULL = texto hardcodeado en terms.php',
    activo         TINYINT(1)    NOT NULL DEFAULT 1,
    es_default     TINYINT(1)    NOT NULL DEFAULT 0
                       COMMENT '1 = evento que carga cuando la URL no trae parámetro ?e=',
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY   (id),
    UNIQUE KEY    uq_slug  (slug),
    KEY           idx_activo (activo)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Eventos y diplomados de la plataforma';


-- =============================================================================
--  PASO 2 — Insertar evento por defecto
--  Toma banner y título directamente de la tabla configuracion existente.
--  es_default = 1 garantiza que las URLs actuales sin ?e= sigan funcionando.
--  RESULTADO ESPERADO: eventos.id = 1
-- =============================================================================

INSERT INTO eventos (slug, nombre, titulo_login, banner, es_default, activo)
VALUES (
    'diplomado-gestion-riesgos-2025',
    (SELECT valor FROM configuracion WHERE clave = 'titulo_evento' LIMIT 1),
    (SELECT valor FROM configuracion WHERE clave = 'titulo_evento' LIMIT 1),
    (SELECT valor FROM configuracion WHERE clave = 'banner'        LIMIT 1),
    1,
    1
);

-- Verificar resultado inmediato
SELECT id, slug, nombre, es_default FROM eventos;


-- =============================================================================
--  PASO 3 — Crear tabla personas (identidades únicas)
--  Sin FK constraints todavía. Sin vínculo a evento.
-- =============================================================================

CREATE TABLE IF NOT EXISTS personas (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    documento   VARCHAR(20)   NOT NULL,
    nombre      VARCHAR(150)  NOT NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY  (id),
    UNIQUE KEY   uq_documento (documento)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Identidades únicas de personas, independientes del evento';


-- =============================================================================
--  PASO 4 — Migrar personas desde participantes (PRESERVA IDs)
--
--  personas.id = participantes.id para los 49 registros actuales.
--  Esto garantiza que cuando el código PHP migre en Fase 2, las FKs en
--  accesos, certificados, tokens_video y video_visualizaciones sigan
--  apuntando a los mismos registros sin necesitar UPDATE.
-- =============================================================================

INSERT INTO personas (id, documento, nombre, created_at)
SELECT id, documento, nombre, created_at
FROM   participantes;

-- Ajustar AUTO_INCREMENT para que futuros INSERTs no colisionen
-- (necesario porque insertamos con IDs explícitos en lugar de AUTO)
SET @max_personas = (SELECT COALESCE(MAX(id), 0) FROM personas);
SET @sql_ai_p = CONCAT('ALTER TABLE personas AUTO_INCREMENT = ', @max_personas + 1);
PREPARE _stmt FROM @sql_ai_p;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- Verificar resultado
SELECT COUNT(*) AS personas_migradas FROM personas;
SELECT MIN(id) AS id_min, MAX(id) AS id_max FROM personas;


-- =============================================================================
--  PASO 5 — Crear tabla evento_participantes (inscripciones)
--  Una fila = una persona inscrita en un evento específico.
--  Sin FK constraints todavía.
-- =============================================================================

CREATE TABLE IF NOT EXISTS evento_participantes (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    evento_id   INT UNSIGNED  NOT NULL
                    COMMENT 'Referencia a eventos.id — FK se agrega en Fase 1',
    persona_id  INT UNSIGNED  NOT NULL
                    COMMENT 'Referencia a personas.id — FK se agrega en Fase 1',
    activo      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY  (id),
    UNIQUE KEY   uq_ep         (evento_id, persona_id),
    KEY          idx_ep_persona (persona_id),
    KEY          idx_ep_evento  (evento_id)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Inscripciones: persona × evento. id coincide con participantes.id para datos migrados';


-- =============================================================================
--  PASO 6 — Migrar inscripciones desde participantes (PRESERVA IDs)
--
--  evento_participantes.id = participantes.id para los 49 registros.
--  Crítico: $_SESSION["participante_id"] pasará a ser evento_participantes.id
--  en Fase 2. Al preservar los IDs, las sesiones activas siguen siendo válidas
--  y todas las FKs (accesos, certificados, tokens_video, video_visualizaciones,
--  recurso_permisos_video) apuntan correctamente sin necesitar UPDATE.
-- =============================================================================

INSERT INTO evento_participantes (id, evento_id, persona_id, activo, created_at)
SELECT id, 1, id, activo, created_at
FROM   participantes;

-- Ajustar AUTO_INCREMENT
SET @max_ep = (SELECT COALESCE(MAX(id), 0) FROM evento_participantes);
SET @sql_ai_ep = CONCAT('ALTER TABLE evento_participantes AUTO_INCREMENT = ', @max_ep + 1);
PREPARE _stmt FROM @sql_ai_ep;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- Verificar resultado
SELECT COUNT(*) AS inscripciones_migradas FROM evento_participantes;
SELECT MIN(id) AS id_min, MAX(id) AS id_max FROM evento_participantes;


-- =============================================================================
--  PASO 7 — Agregar evento_id a recursos
--  DEFAULT 1 asegura compatibilidad inmediata: el código PHP existente
--  no filtra por evento_id y sigue funcionando sin cambios.
--
--  Si SHOW COLUMNS FROM recursos LIKE 'evento_id' devuelve una fila, omitir.
-- =============================================================================

ALTER TABLE recursos
    ADD COLUMN evento_id INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK → eventos.id (constraint se agrega en Fase 1)'
        AFTER id;

UPDATE recursos SET evento_id = 1;

-- Verificar
SELECT COUNT(*) AS total, SUM(evento_id = 1) AS con_evento_1 FROM recursos;


-- =============================================================================
--  PASO 8 — Agregar evento_id a certificados
--
--  Si SHOW COLUMNS FROM certificados LIKE 'evento_id' devuelve una fila, omitir.
-- =============================================================================

ALTER TABLE certificados
    ADD COLUMN evento_id INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK → eventos.id (constraint se agrega en Fase 1)'
        AFTER id;

UPDATE certificados SET evento_id = 1;

-- Verificar
SELECT COUNT(*) AS total, SUM(evento_id = 1) AS con_evento_1 FROM certificados;


-- =============================================================================
--  PASO 9 — Agregar evento_id a accesos
--
--  Si SHOW COLUMNS FROM accesos LIKE 'evento_id' devuelve una fila, omitir.
-- =============================================================================

ALTER TABLE accesos
    ADD COLUMN evento_id INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'FK → eventos.id (constraint se agrega en Fase 1)'
        AFTER id;

UPDATE accesos SET evento_id = 1;

-- Verificar
SELECT COUNT(*) AS total, SUM(evento_id = 1) AS con_evento_1 FROM accesos;


-- =============================================================================
--  VALIDACIONES FINALES — ejecutar tras completar todos los pasos
-- =============================================================================

-- ── 1. Tabla eventos ─────────────────────────────────────────────────────────
SELECT 'EVENTOS' AS tabla, id, slug, nombre, es_default, activo FROM eventos;

-- ── 2. Conteos coinciden con participantes originales ────────────────────────
SELECT
    (SELECT COUNT(*) FROM participantes)        AS participantes_orig,
    (SELECT COUNT(*) FROM personas)             AS personas_nuevas,
    (SELECT COUNT(*) FROM evento_participantes) AS ep_nuevas,
    (SELECT COUNT(*) FROM participantes) = (SELECT COUNT(*) FROM personas) AS personas_ok,
    (SELECT COUNT(*) FROM participantes) = (SELECT COUNT(*) FROM evento_participantes) AS ep_ok;

-- ── 3. IDs de personas coinciden con participantes (debe devolver 0 filas) ───
SELECT par.id, par.documento, par.nombre,
       per.id AS per_id, per.documento AS per_doc
FROM   participantes par
LEFT JOIN personas per ON per.id = par.id
WHERE  per.id IS NULL
   OR  per.documento <> par.documento;
-- ESPERADO: 0 filas

-- ── 4. IDs de evento_participantes coinciden (debe devolver 0 filas) ─────────
SELECT par.id, ep.id AS ep_id, ep.evento_id, ep.persona_id
FROM   participantes par
LEFT JOIN evento_participantes ep ON ep.id = par.id
WHERE  ep.id IS NULL
   OR  ep.persona_id <> par.id
   OR  ep.evento_id  <> 1;
-- ESPERADO: 0 filas

-- ── 5. AUTO_INCREMENT correcto (deben ser > MAX actual) ──────────────────────
SELECT
    table_name,
    auto_increment,
    table_rows AS filas_aprox
FROM   information_schema.tables
WHERE  table_schema = DATABASE()
  AND  table_name IN ('personas', 'evento_participantes', 'eventos')
ORDER BY table_name;

-- ── 6. evento_id en recursos, certificados, accesos ─────────────────────────
SELECT 'recursos'     AS tabla, COUNT(*) AS total, SUM(evento_id = 1) AS evento_1_ok FROM recursos
UNION ALL
SELECT 'certificados' AS tabla, COUNT(*) AS total, SUM(evento_id = 1) AS evento_1_ok FROM certificados
UNION ALL
SELECT 'accesos'      AS tabla, COUNT(*) AS total, SUM(evento_id = 1) AS evento_1_ok FROM accesos;
-- ESPERADO: total = evento_1_ok en cada fila

-- ── 7. Estado final de todas las tablas ──────────────────────────────────────
SHOW TABLES;

-- =============================================================================
--  FIN DE MIGRACIÓN 004
--  Siguiente paso: Fase 1 — Admin: CRUD de eventos y selector de evento activo
-- =============================================================================
