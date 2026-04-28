# Migraciones de base de datos

## Estructura

```
database/
└── migrations/
    ├── 001_video_restricted_access.sql
    └── 002_video_duration_decimal.sql
```

## Convención de nombres

`NNN_descripcion_corta.sql` — NNN es un número secuencial de 3 dígitos.  
Las migraciones se aplican **en orden numérico ascendente**.

---

## Migraciones disponibles

| # | Archivo | Descripción |
|---|---------|-------------|
| 001 | `001_video_restricted_access.sql` | Agrega columnas de video a `recursos`; crea `tokens_video` y `recurso_permisos_video` |
| 002 | `002_video_duration_decimal.sql` | Cambia `video_expira_horas` de `SMALLINT` a `DECIMAL(6,4)` para soportar duraciones sub-hora (ej: 5 minutos = 0.0833) |

---

## Cómo aplicar una migración en producción

### Pasos obligatorios (en este orden)

1. **Backup antes de cualquier cambio**
   ```bash
   mysqldump -u USUARIO -p NOMBRE_BD > ~/backups/pre_migration_$(date +%Y%m%d_%H%M).sql
   ```

2. **Verificar qué columnas ya existen** (para ALTER TABLE seguros)  
   Ejecutar primero los `SHOW COLUMNS` que están al inicio del archivo SQL.

3. **Aplicar la migración**
   ```bash
   mysql -u USUARIO -p NOMBRE_BD < database/migrations/001_video_restricted_access.sql
   ```
   O pegar el contenido directamente en phpMyAdmin → pestaña SQL.

4. **Verificar** que las tablas y columnas quedaron correctas:
   ```sql
   SHOW COLUMNS FROM recursos;
   DESCRIBE tokens_video;
   DESCRIBE recurso_permisos_video;
   ```

---

## Advertencias importantes

- **Git no ejecuta migraciones automáticamente.**  
  Hacer `git pull` en producción actualiza los archivos PHP pero **no** modifica la base de datos. Las migraciones siempre son un paso manual y explícito.

- **Nunca omitas el backup.**  
  Un `ALTER TABLE` mal ejecutado en producción puede ser irreversible si no tienes snapshot.

- **Idempotencia parcial.**  
  Los `CREATE TABLE IF NOT EXISTS` son seguros de re-ejecutar.  
  Los `ALTER TABLE ADD COLUMN` **no** son idempotentes en MySQL < 8.0 — si la columna ya existe, darán error. Usa `SHOW COLUMNS` primero.

- **No hay rollback automático.**  
  Si una migración falla a mitad, restaura desde el backup. No hay `DOWN` migrations en este proyecto.

---

## Historial de cambios de schema

| Migración | Fecha aplicada | Quién | Notas |
|-----------|---------------|-------|-------|
| 001 | _pendiente_ | — | Primera migración — proyecto nuevo en producción |
| 002 | _pendiente_ | — | Cambio de tipo de columna; aplicar junto con código PHP actualizado |
