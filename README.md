# Recursos FYC

Portal de recursos para participantes de diplomados de F&C Consultores.
Permite a los participantes autenticarse, firmar términos, descargar materiales y obtener certificados.

---

## Requisitos

- PHP 8.3 con extensiones: `pdo_mysql`, `zlib`, `iconv`, `zip`
- MySQL 5.7+ o MariaDB 10.x
- Servidor web local (Laragon, XAMPP, WAMP) o Apache/Nginx

---

## Levantar en local

### 1. Clonar el repositorio

```bash
git clone <url-del-repo> recursosfyc
```

### 2. Configurar credenciales de base de datos

```bash
cp db.example.php db.php
```

Editar `db.php` y completar `DB_USER` y `DB_PASS` con las credenciales reales.

### 3. Crear la base de datos

Importar el schema desde `schema.sql` (ver carpeta del proyecto o solicitar al equipo):

```sql
CREATE DATABASE recursosfyc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'usuario'@'localhost' IDENTIFIED BY 'contraseña';
GRANT ALL ON recursosfyc.* TO 'usuario'@'localhost';
```

Luego importar:

```bash
mysql -u root -p recursosfyc < schema.sql
```

### 4. Configurar carpeta de uploads

Crear la carpeta `uploads/` y asegurarse de que tenga permisos de escritura:

```bash
mkdir uploads
chmod 755 uploads
```

Los archivos del portal (PDFs, ZIPs, imágenes) se copian manualmente a esta carpeta.

### 5. Configurar PHP (opcional)

Si el servidor no tiene los límites necesarios, copiar `.php-ini` al directorio raíz o aplicar en `php.ini`:

```ini
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 256M
max_execution_time = 300
```

### 6. Acceder al proyecto

- **Portal participantes:** `http://localhost/recursosfyc/`
- **Panel de administración:** `http://localhost/recursosfyc/admin.php`

---

## Estructura de archivos

```
recursosfyc/
├── index.php                    # Login de participantes
├── terms.php                    # Aceptación de términos y firma
├── portal.php                   # Portal de recursos y certificados
├── video.php                    # Reproductor seguro con streaming y marca de agua
├── admin.php                    # Panel de administración
├── procesar_certificados.php    # Procesamiento masivo de certificados PDF
├── logout.php                   # Cierre de sesión
├── db.php                       # Conexión a BD — NO subir a Git
├── db.example.php               # Plantilla de conexión sin credenciales
├── libs/                        # Librería pdfparser — NO subir a Git
└── uploads/                     # Archivos del portal — NO subir a Git
    └── .htaccess                # Bloquea acceso HTTP directo a videos
```

---

## Seguridad

| Archivo / Carpeta | Estado en Git | Motivo |
|---|---|---|
| `db.php` | **Excluido** | Contiene credenciales de base de datos |
| `uploads/` | **Excluida** | Binarios de usuario (PDFs, videos, imágenes) |
| `libs/` | **Excluida** | Librería de tercero, incluir manualmente |
| `*.sql` | **Excluidos** | Dumps pueden contener datos sensibles |
| `db.example.php` | Incluido | Plantilla sin credenciales, seguro versionar |

**Videos:** el acceso directo a archivos de video está bloqueado por `uploads/.htaccess` (Apache 2.4+). Los videos se sirven exclusivamente a través de `video.php` mediante tokens temporales firmados por sesión.

**Para producción:** reemplazar el usuario `root` en `db.php` por un usuario MySQL con privilegios mínimos sobre la base de datos `recursosfyc`.
