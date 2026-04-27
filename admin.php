<?php
session_start();
require_once 'db.php';

// Login admin
if (!isset($_SESSION['admin_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_admin'])) {
        $usuario = trim($_POST['usuario'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_usuario'] = $admin['usuario'];
            header('Location: admin.php');
            exit;
        } else {
            $login_error = 'Usuario o contraseña incorrectos.';
        }
    }

    if (!isset($_SESSION['admin_id'])) {
        ?>
        <!DOCTYPE html>
        <html lang="es">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin - F&C Consultores</title>
            <style>
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }

                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    background: linear-gradient(135deg, #7b1a2e, #4a0e1a);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .box {
                    background: white;
                    border-radius: 12px;
                    padding: 40px;
                    width: 100%;
                    max-width: 400px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                }

                h2 {
                    color: #7b1a2e;
                    text-align: center;
                    margin-bottom: 8px;
                    font-size: 22px;
                }

                .sub {
                    text-align: center;
                    color: #888;
                    font-size: 13px;
                    margin-bottom: 28px;
                }

                .divider {
                    height: 3px;
                    background: linear-gradient(90deg, #7b1a2e, #c0392b);
                    border-radius: 2px;
                    margin-bottom: 28px;
                }

                label {
                    display: block;
                    font-size: 13px;
                    font-weight: 600;
                    color: #333;
                    margin-bottom: 6px;
                }

                input[type=text],
                input[type=password] {
                    width: 100%;
                    padding: 12px 14px;
                    border: 2px solid #ddd;
                    border-radius: 8px;
                    font-size: 15px;
                    margin-bottom: 16px;
                    outline: none;
                }

                input:focus {
                    border-color: #7b1a2e;
                }

                .btn {
                    width: 100%;
                    padding: 13px;
                    background: linear-gradient(135deg, #7b1a2e, #c0392b);
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 15px;
                    font-weight: 700;
                    cursor: pointer;
                }

                .btn:hover {
                    opacity: 0.9;
                }

                .error {
                    background: #fdecea;
                    color: #c0392b;
                    border: 1px solid #f5c6cb;
                    border-radius: 6px;
                    padding: 10px 14px;
                    font-size: 13px;
                    margin-bottom: 16px;
                }
            </style>
        </head>

        <body>
            <div class="box">
                <h2>F&amp;C Consultores</h2>
                <p class="sub">Panel de Administración</p>
                <div class="divider"></div>
                <?php if (isset($login_error)): ?>
                    <div class="error">⚠️
                        <?= htmlspecialchars($login_error) ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="login_admin" value="1">
                    <label>Usuario</label>
                    <input type="text" name="usuario" autofocus>
                    <label>Contraseña</label>
                    <input type="password" name="password">
                    <button type="submit" class="btn">Ingresar al panel →</button>
                </form>
            </div>
        </body>

        </html>
        <?php
        exit;
    }
}

// Cerrar sesión admin
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Crear carpetas si no existen
if (!file_exists('uploads'))        { mkdir('uploads',        0755, true); }
if (!file_exists('private_videos')) { mkdir('private_videos', 0755, true); }

$mensaje   = '';
$error_msg = '';
$tab       = $_GET['tab'] ?? 'participantes';

if (!empty($_SESSION['flash']['ok']))  { $mensaje   = $_SESSION['flash']['ok'];  unset($_SESSION['flash']['ok']);  }
if (!empty($_SESSION['flash']['err'])) { $error_msg = $_SESSION['flash']['err']; unset($_SESSION['flash']['err']); }

// ===================== CERTIFICADOS =====================
if ($tab === 'certificados') {

    // Agregar certificado
    if (isset($_POST['agregar_certificado'])) {
        $participante_id = (int) $_POST['participante_id'];
        $nombre = trim($_POST['nombre_certificado']);
        if ($participante_id && $nombre && isset($_FILES['archivo_certificado']) && $_FILES['archivo_certificado']['error'] === 0) {
            $nombre_archivo = 'cert_' . time() . '_' . basename($_FILES['archivo_certificado']['name']);
            $destino = 'uploads/' . $nombre_archivo;
            if (move_uploaded_file($_FILES['archivo_certificado']['tmp_name'], $destino)) {
                $stmt = $pdo->prepare("INSERT INTO certificados (participante_id, nombre, archivo) VALUES (?, ?, ?)");
                $stmt->execute([$participante_id, $nombre, $nombre_archivo]);
                $_SESSION['flash']['ok'] = 'Certificado agregado correctamente.';
                header('Location: admin.php?tab=certificados');
                exit;
            }
        }
    }

    // Eliminar certificado
    if (isset($_GET['eliminar_c'])) {
        $id = (int) $_GET['eliminar_c'];
        $r = $pdo->prepare("SELECT archivo FROM certificados WHERE id=?");
        $r->execute([$id]);
        $cert = $r->fetch();
        if ($cert && file_exists('uploads/' . basename($cert['archivo']))) {
            unlink('uploads/' . basename($cert['archivo']));
        }
        $pdo->prepare("DELETE FROM certificados WHERE id=?")->execute([$id]);
        $_SESSION['flash']['ok'] = 'Certificado eliminado.';
        header('Location: admin.php?tab=certificados');
        exit;
    }

    $certificados = $pdo->query("
        SELECT c.*, p.nombre AS nombre_participante, p.documento
        FROM certificados c
        JOIN participantes p ON c.participante_id = p.id
        ORDER BY p.nombre ASC
    ")->fetchAll();

    $participantes_lista = $pdo->query("SELECT id, documento, nombre FROM participantes WHERE activo=1 ORDER BY nombre ASC")->fetchAll();
}

// ===================== PARTICIPANTES =====================
if ($tab === 'participantes') {

    // Agregar participante
    if (isset($_POST['agregar_participante'])) {
        $doc = trim($_POST['documento']);
        $nom = trim($_POST['nombre']);
        if ($doc && $nom) {
            try {
                $stmt = $pdo->prepare("INSERT INTO participantes (documento, nombre) VALUES (?, ?)");
                $stmt->execute([$doc, $nom]);
                $_SESSION['flash']['ok'] = 'Participante agregado correctamente.';
            } catch (PDOException $e) {
                $_SESSION['flash']['err'] = 'Error: El documento ya existe.';
            }
        }
        header('Location: admin.php?tab=participantes');
        exit;
    }

    // Editar participante
    if (isset($_POST['editar_participante'])) {
        $id = (int) $_POST['id'];
        $doc = trim($_POST['documento']);
        $nom = trim($_POST['nombre']);
        $act = isset($_POST['activo']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE participantes SET documento=?, nombre=?, activo=? WHERE id=?");
        $stmt->execute([$doc, $nom, $act, $id]);
        $_SESSION['flash']['ok'] = 'Participante actualizado.';
        header('Location: admin.php?tab=participantes');
        exit;
    }

    // Eliminar participante
    if (isset($_GET['eliminar_p'])) {
        $id = (int) $_GET['eliminar_p'];
        $pdo->prepare("DELETE FROM participantes WHERE id=?")->execute([$id]);
        $_SESSION['flash']['ok'] = 'Participante eliminado.';
        header('Location: admin.php?tab=participantes');
        exit;
    }

    // Importar CSV
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === 0) {
        $handle = fopen($_FILES['archivo_csv']['tmp_name'], 'r');
        $importados = 0;
        $errores_csv = 0;
        $primera = true;
        while (($fila = fgetcsv($handle, 1000, ';')) !== false) {
            if ($primera) {
                $primera = false;
                continue;
            } // saltar encabezado
            if (count($fila) >= 2) {
                $doc = trim($fila[0]);
                $nom = trim($fila[1]);
                if ($doc && $nom) {
                    try {
                        $stmt = $pdo->prepare("INSERT IGNORE INTO participantes (documento, nombre) VALUES (?, ?)");
                        $stmt->execute([$doc, $nom]);
                        $importados++;
                    } catch (PDOException $e) {
                        $errores_csv++;
                    }
                }
            }
        }
        fclose($handle);
        $_SESSION['flash']['ok'] = "CSV importado: $importados participantes agregados.";
        header('Location: admin.php?tab=participantes');
        exit;
    }

    $participantes = $pdo->query("SELECT * FROM participantes ORDER BY nombre ASC")->fetchAll();
}

// ===================== RECURSOS =====================
if ($tab === 'recursos') {

    // Agregar recurso
    if (isset($_POST['agregar_recurso'])) {
        $nom = trim($_POST['nombre_recurso']);
        $desc = trim($_POST['descripcion']);
        $url_externa = trim($_POST['url_externa'] ?? '');

        if ($nom) {
            // Recurso con URL externa
            if (!empty($url_externa)) {
                $stmt = $pdo->prepare("INSERT INTO recursos (nombre, descripcion, archivo, tipo, url_externa) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $desc, 'externo', 'url', $url_externa]);
                $_SESSION['flash']['ok'] = 'Recurso externo agregado correctamente.';
                header('Location: admin.php?tab=recursos');
                exit;

                // Recurso con archivo
            } elseif (isset($_FILES['archivo_recurso']) && $_FILES['archivo_recurso']['error'] === 0) {
                $nombre_archivo = time() . '_' . basename($_FILES['archivo_recurso']['name']);
                $destino = 'uploads/' . $nombre_archivo;
                if (move_uploaded_file($_FILES['archivo_recurso']['tmp_name'], $destino)) {
                    $ext = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
                    $stmt = $pdo->prepare("INSERT INTO recursos (nombre, descripcion, archivo, tipo, url_externa) VALUES (?, ?, ?, ?, NULL)");
                    $stmt->execute([$nom, $desc, $nombre_archivo, $ext]);
                    $_SESSION['flash']['ok'] = 'Recurso agregado correctamente.';
                    header('Location: admin.php?tab=recursos');
                    exit;
                }
            } else {
                $_SESSION['flash']['err'] = 'Debes subir un archivo o ingresar una URL externa.';
                header('Location: admin.php?tab=recursos');
                exit;
            }
        }
    }

    // Agregar video restringido con caducidad
    if (isset($_POST['agregar_video'])) {
        $nom            = trim($_POST['nombre_video'] ?? '');
        $desc           = trim($_POST['descripcion_video'] ?? '');
        $video_url      = trim($_POST['url_video'] ?? '');
        $video_existente = trim($_POST['video_existente'] ?? '');
        $horas          = max(1, (int) ($_POST['video_expira_horas'] ?? 4));

        error_log("VIDEO EXISTENTE: " . $video_existente);

        $seleccionados = array_map('intval', $_POST['participantes_video'] ?? []);

        if (!$nom) {
            $_SESSION['flash']['err'] = 'El nombre del video es obligatorio.';
        } elseif (!empty($video_url)) {
            // ── Opción 1: URL externa ─────────────────────────────────────────
            $stmt = $pdo->prepare("INSERT INTO recursos (nombre, descripcion, archivo, tipo, url_externa, es_video, ruta_video, video_expira_horas) VALUES (?, ?, 'externo_video', 'video', ?, 1, ?, ?)");
            $stmt->execute([$nom, $desc, $video_url, $video_url, $horas]);
            $nuevo_id = (int) $pdo->lastInsertId();
            if ($nuevo_id && $seleccionados) {
                $ins_perm = $pdo->prepare("INSERT IGNORE INTO recurso_permisos_video (recurso_id, participante_id) VALUES (?, ?)");
                foreach ($seleccionados as $pid) {
                    if ($pid > 0) $ins_perm->execute([$nuevo_id, $pid]);
                }
            }
            $_SESSION['flash']['ok'] = 'Video externo agregado correctamente.';
        } elseif (isset($_FILES['video_archivo']) && $_FILES['video_archivo']['error'] === 0) {
            // ── Opción 2: Subida desde navegador ─────────────────────────────
            $ext_permitidas = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'];
            $nombre_archivo = time() . '_' . basename($_FILES['video_archivo']['name']);
            $ext            = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
            if (!in_array($ext, $ext_permitidas)) {
                $_SESSION['flash']['err'] = 'Tipo de archivo no permitido. Use: ' . implode(', ', $ext_permitidas);
            } else {
                $destino = 'private_videos/' . $nombre_archivo;
                if (move_uploaded_file($_FILES['video_archivo']['tmp_name'], $destino)) {
                    $stmt = $pdo->prepare("INSERT INTO recursos (nombre, descripcion, archivo, tipo, es_video, ruta_video, video_expira_horas) VALUES (?, ?, ?, ?, 1, ?, ?)");
                    $stmt->execute([$nom, $desc, $nombre_archivo, $ext, $nombre_archivo, $horas]);
                    $nuevo_id = (int) $pdo->lastInsertId();
                    if ($nuevo_id && $seleccionados) {
                        $ins_perm = $pdo->prepare("INSERT IGNORE INTO recurso_permisos_video (recurso_id, participante_id) VALUES (?, ?)");
                        foreach ($seleccionados as $pid) {
                            if ($pid > 0) $ins_perm->execute([$nuevo_id, $pid]);
                        }
                    }
                    $_SESSION['flash']['ok'] = 'Video agregado correctamente.';
                } else {
                    $_SESSION['flash']['err'] = 'Error al mover el archivo subido.';
                }
            }
        } elseif ($video_existente !== '') {
            // ── Opción 3: Archivo ya en private_videos/ (subido por FTP) ─────
            $nombre_archivo = basename($video_existente);
            if ($nombre_archivo === '' || $nombre_archivo !== $video_existente) {
                $_SESSION['flash']['err'] = 'Nombre de archivo inválido. Escribe solo el nombre sin ruta (ej: clase1.mp4).';
            } else {
                $ext            = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
                $ext_permitidas = ['mp4', 'webm', 'mov', 'm4v'];
                if (!in_array($ext, $ext_permitidas)) {
                    $_SESSION['flash']['err'] = 'Extensión no permitida. Use: ' . implode(', ', $ext_permitidas);
                } elseif (!file_exists('private_videos/' . $nombre_archivo)) {
                    $_SESSION['flash']['err'] = "El archivo '{$nombre_archivo}' no existe en private_videos/.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO recursos (nombre, descripcion, archivo, tipo, es_video, ruta_video, video_expira_horas) VALUES (?, ?, ?, ?, 1, ?, ?)");
                    $stmt->execute([$nom, $desc, $nombre_archivo, $ext, $nombre_archivo, $horas]);
                    $nuevo_id = (int) $pdo->lastInsertId();
                    if ($nuevo_id && $seleccionados) {
                        $ins_perm = $pdo->prepare("INSERT IGNORE INTO recurso_permisos_video (recurso_id, participante_id) VALUES (?, ?)");
                        foreach ($seleccionados as $pid) {
                            if ($pid > 0) $ins_perm->execute([$nuevo_id, $pid]);
                        }
                    }
                    $_SESSION['flash']['ok'] = 'Video registrado correctamente (archivo existente en private_videos/).';
                }
            }
        } else {
            $_SESSION['flash']['err'] = 'Debes subir un archivo, ingresar una URL o escribir el nombre de un archivo existente en private_videos/.';
        }
        header('Location: admin.php?tab=recursos');
        exit;
    }

    // Registrar archivo existente (subido por FTP)
    if (isset($_POST['registrar_existente'])) {
        $nom = trim($_POST['nombre_recurso_existente']);
        $desc = trim($_POST['descripcion_existente']);
        $archivo = basename(trim($_POST['nombre_archivo_existente']));
        $ruta = 'uploads/' . $archivo;
        if ($nom && $archivo && file_exists($ruta)) {
            $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
            $stmt = $pdo->prepare("INSERT INTO recursos (nombre, descripcion, archivo, tipo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nom, $desc, $archivo, $ext]);
            $_SESSION['flash']['ok'] = 'Recurso registrado correctamente.';
            header('Location: admin.php?tab=recursos');
            exit;
        } else {
            $_SESSION['flash']['err'] = 'El archivo no fue encontrado en uploads/. Verifica el nombre exacto.';
            header('Location: admin.php?tab=recursos');
            exit;
        }
    }

    // Editar recurso (solo nombre, descripcion y estado)
    if (isset($_POST['editar_recurso'])) {
        $id = (int) $_POST['id'];
        $nom = trim($_POST['nombre_recurso']);
        $desc = trim($_POST['descripcion']);
        $act = isset($_POST['activo']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE recursos SET nombre=?, descripcion=?, activo=? WHERE id=?");
        $stmt->execute([$nom, $desc, $act, $id]);
        $_SESSION['flash']['ok'] = 'Recurso actualizado.';
        header('Location: admin.php?tab=recursos');
        exit;
    }

    // Eliminar recurso
    if (isset($_GET['eliminar_r'])) {
        $id = (int) $_GET['eliminar_r'];
        $r = $pdo->prepare("SELECT archivo FROM recursos WHERE id=?");
        $r->execute([$id]);
        $rec = $r->fetch();
        if ($rec) {
            $fn = basename($rec['archivo']);
            foreach (['private_videos/', 'uploads/'] as $dir) {
                if (file_exists($dir . $fn)) { unlink($dir . $fn); break; }
            }
        }
        $pdo->prepare("DELETE FROM recursos WHERE id=?")->execute([$id]);
        $_SESSION['flash']['ok'] = 'Recurso eliminado.';
        header('Location: admin.php?tab=recursos');
        exit;
    }

    // Guardar permisos de video
    if (isset($_POST['guardar_permisos_video'])) {
        $recurso_id    = (int) $_POST['recurso_id_perm'];
        $seleccionados = array_map('intval', $_POST['participantes_video_perm'] ?? []);
        $chk = $pdo->prepare("SELECT id FROM recursos WHERE id = ? AND es_video = 1");
        $chk->execute([$recurso_id]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE recurso_permisos_video SET activo = 0 WHERE recurso_id = ?")
                ->execute([$recurso_id]);
            if ($seleccionados) {
                $upsert = $pdo->prepare(
                    "INSERT INTO recurso_permisos_video (recurso_id, participante_id, activo)
                     VALUES (?, ?, 1)
                     ON DUPLICATE KEY UPDATE activo = 1"
                );
                foreach ($seleccionados as $pid) {
                    if ($pid > 0) $upsert->execute([$recurso_id, $pid]);
                }
            }
            $_SESSION['flash']['ok'] = 'Permisos del video actualizados.';
        }
        header('Location: admin.php?tab=recursos');
        exit;
    }

    $recursos = $pdo->query("SELECT * FROM recursos ORDER BY created_at DESC")->fetchAll();
    $participantes_video = $pdo->query("SELECT id, documento, nombre FROM participantes WHERE activo=1 ORDER BY nombre ASC")->fetchAll();

    $perms_raw = $pdo->query("SELECT recurso_id, participante_id FROM recurso_permisos_video WHERE activo = 1")->fetchAll();
    $video_perms = [];
    foreach ($perms_raw as $pr) {
        $video_perms[(int) $pr['recurso_id']][] = (int) $pr['participante_id'];
    }
}

// ===================== CONFIGURACION =====================
if ($tab === 'configuracion') {
    if (isset($_POST['guardar_config'])) {
        $titulo = trim($_POST['titulo_evento']);
        $pdo->prepare("UPDATE configuracion SET valor=? WHERE clave='titulo_evento'")->execute([$titulo]);

        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === 0) {
            $nombre_banner = 'banner_' . time() . '_' . basename($_FILES['banner']['name']);
            $destino = 'uploads/' . $nombre_banner;
            if (move_uploaded_file($_FILES['banner']['tmp_name'], $destino)) {
                $pdo->prepare("UPDATE configuracion SET valor=? WHERE clave='banner'")->execute([$nombre_banner]);
            }
        }
        $_SESSION['flash']['ok'] = 'Configuración guardada.';
        header('Location: admin.php?tab=configuracion');
        exit;
    }

    $stmt = $pdo->query("SELECT clave, valor FROM configuracion");
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['clave']] = $row['valor'];
    }
}

// ===================== LOGS =====================
if ($tab === 'logs') {
    $logs = $pdo->query("
        SELECT a.*, p.nombre, p.documento, r.nombre AS recurso_nombre
        FROM accesos a
        JOIN participantes p ON a.participante_id = p.id
        LEFT JOIN recursos r ON a.recurso_id = r.id
        ORDER BY a.fecha DESC
        LIMIT 300
    ")->fetchAll();
}

// ===================== CAMBIAR PASSWORD =====================
if ($tab === 'password') {
    if (isset($_POST['cambiar_password'])) {
        $actual = $_POST['password_actual'];
        $nueva = $_POST['password_nueva'];
        $confirma = $_POST['password_confirma'];
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id=?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        if (!password_verify($actual, $admin['password'])) {
            $error_msg = 'La contraseña actual es incorrecta.';
        } elseif ($nueva !== $confirma) {
            $error_msg = 'Las contraseñas nuevas no coinciden.';
        } elseif (strlen($nueva) < 6) {
            $error_msg = 'La contraseña debe tener al menos 6 caracteres.';
        } else {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admins SET password=? WHERE id=?")->execute([$hash, $_SESSION['admin_id']]);
            $_SESSION['flash']['ok'] = 'Contraseña actualizada correctamente.';
            header('Location: admin.php?tab=password');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - F&C Consultores</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        .topbar {
            background: linear-gradient(135deg, #7b1a2e, #c0392b);
            color: white;
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar .brand {
            font-size: 18px;
            font-weight: 700;
        }

        .topbar .right {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-size: 13px;
        }

        .topbar a:hover {
            color: white;
        }

        .tabs {
            background: white;
            border-bottom: 2px solid #eee;
            display: flex;
            overflow-x: auto;
            padding: 0 16px;
            gap: 2px;
            scrollbar-width: thin;
            scrollbar-color: #ddd transparent;
            -webkit-overflow-scrolling: touch;
        }

        .tabs a {
            padding: 13px 16px;
            text-decoration: none;
            color: #666;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            border-radius: 6px 6px 0 0;
            transition: background 0.15s, color 0.15s;
        }

        .tabs a.active {
            color: #7b1a2e;
            border-bottom-color: #7b1a2e;
            background: #fdf2f4;
        }

        .tabs a:hover:not(.active) {
            color: #7b1a2e;
            background: #fdf8f9;
        }

        .main {
            max-width: 1320px;
            margin: 28px auto;
            padding: 0 24px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 22px 24px;
            margin-bottom: 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.07);
        }

        .card h3 {
            color: #7b1a2e;
            font-size: 17px;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-row {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .form-row .field {
            flex: 1;
            min-width: 140px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #444;
            margin-bottom: 5px;
        }

        input[type=text],
        input[type=password],
        input[type=file],
        textarea,
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
        }

        input:focus,
        textarea:focus {
            border-color: #7b1a2e;
        }

        textarea {
            resize: vertical;
            min-height: 70px;
        }

        .btn {
            padding: 10px 22px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-primary {
            background: #7b1a2e;
            color: white;
        }

        .btn-primary:hover {
            background: #9b2235;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #d68910;
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
        }

        .alert-ok {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 18px;
        }

        .alert-err {
            background: #fdecea;
            color: #c0392b;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #f8f8f8;
            padding: 10px 12px;
            text-align: left;
            color: #555;
            font-weight: 700;
            border-bottom: 2px solid #eee;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        tr:hover td {
            background: #fafafa;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge-ok {
            background: #d4edda;
            color: #155724;
        }

        .badge-off {
            background: #f8d7da;
            color: #721c24;
        }

        .accion-login {
            color: #2980b9;
            font-weight: 600;
        }

        .accion-descarga {
            color: #27ae60;
            font-weight: 600;
        }

        .accion-terminos {
            color: #8e44ad;
            font-weight: 600;
        }

        .firma-thumb {
            max-width: 120px;
            max-height: 50px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .search-box {
            margin-bottom: 16px;
        }

        .search-box input {
            max-width: 320px;
        }

        .form-section {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 14px;
            background: #fafbfc;
        }

        .form-section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .td-actions {
            white-space: nowrap;
        }

        .td-actions .btn + .btn {
            margin-left: 4px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 100%;
            max-width: 480px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal h4 {
            color: #7b1a2e;
            font-size: 16px;
            margin-bottom: 18px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 18px;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .main {
                margin: 20px auto;
                padding: 0 14px;
            }
            .card {
                padding: 18px;
            }
            .tabs a {
                padding: 11px 13px;
                font-size: 12px;
            }
            /* Tabla recursos: ocultar # y Archivo en tablet */
            #tabla-r th:nth-child(1), #tabla-r td:nth-child(1),
            #tabla-r th:nth-child(4), #tabla-r td:nth-child(4) { display: none; }
            /* Tabla participantes: ocultar # en tablet */
            #tabla-p th:nth-child(1), #tabla-p td:nth-child(1) { display: none; }
            /* Tabla certificados: ocultar # en tablet */
            #tabla-cert th:nth-child(1), #tabla-cert td:nth-child(1) { display: none; }
            /* Tabla logs: ocultar Documento en tablet */
            #tabla-log th:nth-child(3), #tabla-log td:nth-child(3) { display: none; }
        }

        @media (max-width: 600px) {
            .topbar {
                flex-direction: column;
                gap: 8px;
                text-align: center;
                padding: 10px 16px;
            }

            .topbar .brand { font-size: 15px; }

            .form-row {
                flex-direction: column;
            }

            .modal {
                padding: 16px;
                margin: 8px;
                border-radius: 8px;
            }

            /* Tabla participantes: ocultar # y Registrado */
            #tabla-p th:nth-child(1), #tabla-p td:nth-child(1),
            #tabla-p th:nth-child(5), #tabla-p td:nth-child(5) { display: none; }

            /* Tabla recursos: ocultar #, Descripción, Archivo, Subido */
            #tabla-r th:nth-child(1), #tabla-r td:nth-child(1),
            #tabla-r th:nth-child(3), #tabla-r td:nth-child(3),
            #tabla-r th:nth-child(4), #tabla-r td:nth-child(4),
            #tabla-r th:nth-child(7), #tabla-r td:nth-child(7) { display: none; }

            /* Tabla certificados: ocultar #, Archivo, Subido */
            #tabla-cert th:nth-child(1), #tabla-cert td:nth-child(1),
            #tabla-cert th:nth-child(5), #tabla-cert td:nth-child(5),
            #tabla-cert th:nth-child(6), #tabla-cert td:nth-child(6) { display: none; }

            /* Tabla logs: ocultar IP y Firma */
            #tabla-log th:nth-child(6), #tabla-log td:nth-child(6),
            #tabla-log th:nth-child(7), #tabla-log td:nth-child(7) { display: none; }
        }
    </style>
</head>

<body>

    <div class="topbar">
        <div class="brand">⚙️ F&amp;C Consultores · Admin</div>
        <div class="right">
            <span>👤
                <?= htmlspecialchars($_SESSION['admin_usuario']) ?>
            </span>
            <a href="admin.php?logout=1">Cerrar sesión</a>
        </div>
    </div>

    <div class="tabs">
        <a href="admin.php?tab=participantes" class="<?= $tab === 'participantes' ? 'active' : '' ?>">👥
            Participantes</a>
        <a href="admin.php?tab=recursos" class="<?= $tab === 'recursos' ? 'active' : '' ?>">📦 Recursos</a>
        <a href="admin.php?tab=certificados" class="<?= $tab === 'certificados' ? 'active' : '' ?>">🎓 Certificados</a>
        <a href="admin.php?tab=logs" class="<?= $tab === 'logs' ? 'active' : '' ?>">📋 Accesos y Descargas</a>
        <a href="admin.php?tab=configuracion" class="<?= $tab === 'configuracion' ? 'active' : '' ?>">🎨
            Configuración</a>
        <a href="admin.php?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>">🔑 Contraseña</a>
    </div>

    <div class="main">

        <?php if ($mensaje): ?>
            <div class="alert-ok">✅ <?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert-err">⚠️
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- ============ TAB PARTICIPANTES ============ -->
        <?php if ($tab === 'participantes'): ?>

            <div class="card">
                <h3>➕ Agregar participante</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="field">
                            <label>Documento</label>
                            <input type="text" name="documento" placeholder="Número de documento" required>
                        </div>
                        <div class="field">
                            <label>Nombre completo</label>
                            <input type="text" name="nombre" placeholder="Nombre completo" required>
                        </div>
                    </div>
                    <button type="submit" name="agregar_participante" class="btn btn-primary">Agregar participante</button>
                </form>
            </div>

            <div class="card">
                <h3>📤 Importar participantes desde CSV</h3>
                <p style="font-size:13px;color:#666;margin-bottom:14px;">El archivo CSV debe tener dos columnas:
                    <strong>documento</strong> y <strong>nombre</strong>. La primera fila es el encabezado y se omite.
                </p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="field">
                            <label>Archivo CSV</label>
                            <input type="file" name="archivo_csv" accept=".csv" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Importar CSV</button>
                </form>
            </div>

            <div class="card">
                <h3>👥 Lista de participantes (
                    <?= count($participantes) ?>)
                </h3>
                <div class="search-box">
                    <input type="text" id="buscar-p" placeholder="Buscar por nombre o documento..."
                        onkeyup="filtrarTabla('tabla-p', this.value)">
                </div>
                <div style="overflow-x:auto;">
                    <table id="tabla-p">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Documento</th>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th>Registrado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participantes as $p): ?>
                                <tr>
                                    <td>
                                        <?= $p['id'] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($p['documento']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($p['nombre']) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $p['activo'] ? 'badge-ok' : 'badge-off' ?>">
                                            <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm"
                                            onclick="abrirEditarP(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['documento'])) ?>', '<?= htmlspecialchars(addslashes($p['nombre'])) ?>', <?= $p['activo'] ?>)">
                                            Editar
                                        </button>
                                        <a href="admin.php?tab=participantes&eliminar_p=<?= $p['id'] ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('¿Eliminar este participante?')">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal editar participante -->
            <div class="modal-overlay" id="modal-p">
                <div class="modal">
                    <h4>✏️ Editar participante</h4>
                    <form method="POST">
                        <input type="hidden" name="id" id="edit-p-id">
                        <div class="form-row">
                            <div class="field">
                                <label>Documento</label>
                                <input type="text" name="documento" id="edit-p-doc" required>
                            </div>
                            <div class="field">
                                <label>Nombre</label>
                                <input type="text" name="nombre" id="edit-p-nom" required>
                            </div>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;">
                            <input type="checkbox" name="activo" id="edit-p-activo" style="width:auto;">
                            Activo
                        </label>
                        <div class="modal-actions">
                            <button type="button" class="btn" style="background:#eee;color:#333;"
                                onclick="cerrarModal('modal-p')">Cancelar</button>
                            <button type="submit" name="editar_participante" class="btn btn-primary">Guardar
                                cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ============ TAB RECURSOS ============ -->
        <?php elseif ($tab === 'recursos'): ?>

            <div class="card">
                <h3>➕ Agregar recurso</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="field">
                            <label>Nombre del recurso</label>
                            <input type="text" name="nombre_recurso" placeholder="Ej: Guía de Gestión de Riesgos" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Descripción (opcional)</label>
                            <textarea name="descripcion" placeholder="Breve descripción del recurso..."></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Archivo (PDF, Excel, Word, PPT, ZIP...) — déjalo vacío si usas URL externa</label>
                            <input type="file" name="archivo_recurso"
                                accept=".pdf,.xlsx,.xls,.docx,.doc,.pptx,.ppt,.zip,.rar">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>O URL externa (Google Drive, etc.) — déjala vacía si subes archivo</label>
                            <input type="text" name="url_externa"
                                placeholder="https://drive.google.com/uc?export=download&id=...">
                        </div>
                    </div>
                    <button type="submit" name="agregar_recurso" class="btn btn-primary">Subir recurso</button>
                </form>
            </div>

            <div class="card" style="border-left:4px solid #1d4ed8;">
                <h3 style="color:#1d4ed8;">🎬 Agregar video restringido</h3>
                <p style="font-size:13px;color:#666;margin-bottom:16px;">
                    Los videos se sirven a través de un enlace temporal con caducidad. Los participantes no pueden descargarlos directamente.
                </p>
                <form method="POST" enctype="multipart/form-data">

                    <div class="form-section">
                        <div class="form-section-title">📝 Datos básicos</div>
                        <div class="form-row">
                            <div class="field">
                                <label>Nombre del video</label>
                                <input type="text" name="nombre_video" placeholder="Ej: Clase 1 — Introducción a la gestión de riesgos" required>
                            </div>
                        </div>
                        <div class="form-row" style="margin-bottom:0;">
                            <div class="field">
                                <label>Descripción (opcional)</label>
                                <textarea name="descripcion_video" placeholder="Breve descripción del video..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">📁 Fuente del video <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#9ca3af;">(elige una de las tres opciones)</span></div>
                        <div class="form-row" style="margin-bottom:12px;">
                            <div class="field">
                                <label>① Subir archivo desde el navegador</label>
                                <input type="file" name="video_archivo" accept=".mp4,.webm,.ogg,.mov,.avi,.mkv,.m4v">
                                <p style="font-size:12px;color:#999;margin-top:4px;">Para videos pequeños (&lt;50 MB aprox.).</p>
                            </div>
                            <div class="field">
                                <label>② URL externa del video</label>
                                <input type="text" name="url_video" placeholder="https://...">
                                <p style="font-size:12px;color:#999;margin-top:4px;">Déjala vacía si usas archivo o FTP.</p>
                            </div>
                        </div>
                        <div style="border-top:1px dashed #e5e7eb;padding-top:12px;">
                            <div class="field" style="margin-bottom:0;">
                                <label>③ Nombre de archivo ya subido por FTP a <code style="font-size:12px;background:#f3f4f6;padding:1px 5px;border-radius:4px;">private_videos/</code></label>
                                <input type="text" name="video_existente" placeholder="Ej: clase1_gestion_riesgos.mp4" style="font-family:monospace;">
                                <p style="font-size:12px;color:#999;margin-top:4px;">Solo el nombre del archivo con su extensión. El archivo debe existir en <code>private_videos/</code> antes de guardar.</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">⏱ Duración del acceso</div>
                        <div class="form-row" style="margin-bottom:0;">
                            <div class="field" style="max-width:240px;">
                                <label>Horas desde que el participante abre el video</label>
                                <input type="number" name="video_expira_horas" value="4" min="1" max="720">
                                <p style="font-size:12px;color:#999;margin-top:4px;">El enlace expira pasado este tiempo.</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">👥 Participantes autorizados <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#9ca3af;">(selecciona al menos uno)</span></div>
                        <div style="border:1px solid #ddd;border-radius:7px;max-height:200px;overflow-y:auto;padding:10px 12px;background:#fff;">
                            <?php if (!empty($participantes_video)): ?>
                                <?php foreach ($participantes_video as $pv): ?>
                                    <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-weight:400;font-size:13px;cursor:pointer;">
                                        <input type="checkbox" name="participantes_video[]" value="<?= $pv['id'] ?>" style="width:auto;">
                                        <?= htmlspecialchars($pv['documento']) ?> — <?= htmlspecialchars($pv['nombre']) ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color:#9ca3af;font-size:12px;margin:0;">No hay participantes activos registrados.</p>
                            <?php endif; ?>
                        </div>
                        <p style="font-size:12px;color:#999;margin-top:6px;">Si no seleccionas ninguno, el video no será visible para nadie.</p>
                    </div>

                    <button type="submit" name="agregar_video" class="btn btn-primary" style="background:#1d4ed8;">🎬 Agregar video</button>
                </form>
            </div>

            <div class="card">
                <h3>📁 Registrar archivo subido por FTP</h3>
                <p style="font-size:13px;color:#666;margin-bottom:14px;">
                    ¿Tienes un archivo grande (ZIP, video, etc.) que subiste directamente por FTP a la carpeta
                    <code>uploads/</code>?
                    Ingrésalo aquí para que quede disponible en el portal sin necesidad de subirlo por el navegador.
                </p>
                <form method="POST">
                    <div class="form-row">
                        <div class="field">
                            <label>Nombre del recurso (visible para los participantes)</label>
                            <input type="text" name="nombre_recurso_existente"
                                placeholder="Ej: Módulo V - Gestión de Riesgos" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Descripción (opcional)</label>
                            <textarea name="descripcion_existente"
                                placeholder="Breve descripción del recurso..."></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Nombre exacto del archivo en uploads/ (con extensión)</label>
                            <input type="text" name="nombre_archivo_existente" placeholder="Ej: modulo5_riesgos.zip"
                                required>
                            <p style="font-size:12px;color:#999;margin-top:4px;">Debe coincidir exactamente con el nombre
                                del archivo en la carpeta uploads/.</p>
                        </div>
                    </div>
                    <button type="submit" name="registrar_existente" class="btn btn-primary">Registrar recurso</button>
                </form>
            </div>

            <div class="card">
                <h3>📦 Lista de recursos (
                    <?= count($recursos) ?>)
                </h3>
                <div style="overflow-x:auto;">
                    <table id="tabla-r">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Archivo</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Subido</th>
                                <th>Duración</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recursos as $r): ?>
                                <tr>
                                    <td>
                                        <?= $r['id'] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['nombre']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['descripcion']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['archivo']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['es_video'])): ?>
                                            <span class="badge" style="background:#dbeafe;color:#1d4ed8;">🎬 VIDEO</span>
                                        <?php else: ?>
                                            <span class="badge badge-ok"><?= strtoupper(htmlspecialchars($r['tipo'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $r['activo'] ? 'badge-ok' : 'badge-off' ?>">
                                            <?= $r['activo'] ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($r['created_at'])) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['es_video'])): ?>
                                            <span class="badge" style="background:#dbeafe;color:#1d4ed8;"><?= (int) ($r['video_expira_horas'] ?? 4) ?>h</span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="td-actions">
                                        <button class="btn btn-warning btn-sm"
                                            onclick="abrirEditarR(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['nombre'])) ?>', '<?= htmlspecialchars(addslashes($r['descripcion'])) ?>', <?= $r['activo'] ?>)">
                                            Editar
                                        </button>
                                        <?php if (!empty($r['es_video'])): ?>
                                        <button class="btn btn-sm"
                                            style="background:#1d4ed8;color:white;"
                                            onclick="abrirPermisos(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['nombre'])) ?>')">
                                            Permisos
                                        </button>
                                        <?php endif; ?>
                                        <a href="admin.php?tab=recursos&eliminar_r=<?= $r['id'] ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('¿Eliminar este recurso?')">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal editar recurso -->
            <div class="modal-overlay" id="modal-r">
                <div class="modal">
                    <h4>✏️ Editar recurso</h4>
                    <form method="POST">
                        <input type="hidden" name="id" id="edit-r-id">
                        <div class="form-row">
                            <div class="field">
                                <label>Nombre</label>
                                <input type="text" name="nombre_recurso" id="edit-r-nom" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label>Descripción</label>
                                <textarea name="descripcion" id="edit-r-desc"></textarea>
                            </div>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;">
                            <input type="checkbox" name="activo" id="edit-r-activo" style="width:auto;">
                            Activo
                        </label>
                        <div class="modal-actions">
                            <button type="button" class="btn" style="background:#eee;color:#333;"
                                onclick="cerrarModal('modal-r')">Cancelar</button>
                            <button type="submit" name="editar_recurso" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal gestionar permisos de video -->
            <div class="modal-overlay" id="modal-permisos">
                <div class="modal" style="max-width:560px;">
                    <h4>🔐 Permisos — <span id="perm-video-nombre" style="color:#1d4ed8;"></span></h4>
                    <p style="font-size:13px;color:#666;margin-bottom:12px;">
                        Solo los participantes marcados podrán ver este video.
                        Al guardar, los desmarcados quedan desactivados (historial conservado).
                    </p>
                    <form method="POST">
                        <input type="hidden" name="recurso_id_perm" id="perm-recurso-id">
                        <input type="text" id="perm-buscar" placeholder="Buscar participante…"
                               style="margin-bottom:8px;"
                               oninput="filtrarPermisos(this.value)">
                        <div id="perm-lista"
                             style="border:1px solid #ddd;border-radius:7px;max-height:240px;overflow-y:auto;
                                    padding:8px 12px;background:#fafafa;margin-bottom:14px;"></div>
                        <div class="modal-actions">
                            <button type="button" class="btn" style="background:#eee;color:#333;"
                                onclick="cerrarModal('modal-permisos')">Cancelar</button>
                            <button type="submit" name="guardar_permisos_video" class="btn btn-primary">
                                💾 Guardar permisos
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            (function () {
                var videoPerms        = <?= json_encode($video_perms) ?>;
                var participantesData = <?= json_encode(array_values(array_map(function ($p) {
                    return ['id' => (int) $p['id'], 'doc' => $p['documento'], 'nom' => $p['nombre']];
                }, $participantes_video))) ?>;

                function esc(s) {
                    return String(s)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                }

                function renderLista(filtro) {
                    var lista = document.getElementById('perm-lista');
                    var q     = (filtro || '').toLowerCase();
                    var datos = q
                        ? participantesData.filter(function (p) {
                            return (p.doc + ' ' + p.nom).toLowerCase().indexOf(q) !== -1;
                          })
                        : participantesData;

                    if (datos.length === 0) {
                        lista.innerHTML = '<p style="color:#9ca3af;font-size:12px;margin:0;">Sin resultados.</p>';
                        return;
                    }

                    var activos = window._permActivos || [];
                    lista.innerHTML = datos.map(function (p) {
                        var chk = activos.indexOf(p.id) !== -1 ? ' checked' : '';
                        return '<label style="display:flex;align-items:center;gap:8px;padding:5px 0;font-size:13px;cursor:pointer;border-bottom:1px solid #f0f0f0;">'
                            + '<input type="checkbox" name="participantes_video_perm[]" value="' + p.id + '"' + chk + ' style="width:auto;flex-shrink:0;">'
                            + '<span><strong style="color:#444;">' + esc(p.doc) + '</strong> — ' + esc(p.nom) + '</span>'
                            + '</label>';
                    }).join('');
                }

                window.filtrarPermisos = function (valor) {
                    renderLista(valor);
                };

                window.abrirPermisos = function (recursoId, nombre) {
                    document.getElementById('perm-recurso-id').value = recursoId;
                    document.getElementById('perm-video-nombre').textContent = nombre;
                    var buscar = document.getElementById('perm-buscar');
                    buscar.value = '';
                    window._permActivos = videoPerms[recursoId] || [];

                    if (participantesData.length === 0) {
                        document.getElementById('perm-lista').innerHTML =
                            '<p style="color:#9ca3af;font-size:12px;margin:0;">No hay participantes activos registrados.</p>';
                    } else {
                        renderLista('');
                    }

                    document.getElementById('modal-permisos').classList.add('open');
                    setTimeout(function () { buscar.focus(); }, 80);
                };
            }());
            </script>

            <!-- ============ TAB CERTIFICADOS ============ -->
        <?php elseif ($tab === 'certificados'): ?>

            <div class="card">
                    <h3>🎓 Agregar certificado</h3>
                    <p style="margin-bottom:16px;font-size:14px;color:#6d28d9;">
                        ¿Tienes muchos certificados?
                        <a href="procesar_certificados.php" style="color:#7c3aed;font-weight:700;">
                            🚀 Usar carga masiva automática
                        </a> — el sistema detecta la cédula y los asocia solo.
                    </p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="field">
                                <label>Participante</label>
                                <select name="participante_id" required>
                                    <option value="">-- Selecciona un participante --</option>
                                    <?php foreach ($participantes_lista as $p): ?>
                                        <option value="<?= $p['id'] ?>">
                                            <?= htmlspecialchars($p['nombre']) ?> — <?= htmlspecialchars($p['documento']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Nombre del certificado</label>
                                <input type="text" name="nombre_certificado" placeholder="Ej: Certificado de participación"
                                    required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label>Archivo PDF</label>
                                <input type="file" name="archivo_certificado" accept=".pdf" required>
                            </div>
                        </div>
                        <button type="submit" name="agregar_certificado" class="btn btn-primary">Subir certificado</button>
                    </form>
                </div>

                <div class="card">
                    <h3>🎓 Lista de certificados (<?= count($certificados) ?>)</h3>
                    <div class="search-box">
                        <input type="text" id="buscar-cert" placeholder="Buscar por nombre o documento..."
                            onkeyup="filtrarTabla('tabla-cert', this.value)">
                    </div>
                    <div style="overflow-x:auto;">
                        <table id="tabla-cert">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Participante</th>
                                    <th>Documento</th>
                                    <th>Certificado</th>
                                    <th>Archivo</th>
                                    <th>Subido</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($certificados as $c): ?>
                                    <tr>
                                        <td><?= $c['id'] ?></td>
                                        <td><?= htmlspecialchars($c['nombre_participante']) ?></td>
                                        <td><?= htmlspecialchars($c['documento']) ?></td>
                                        <td><?= htmlspecialchars($c['nombre']) ?></td>
                                        <td><?= htmlspecialchars($c['archivo']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                                        <td>
                                            <a href="uploads/<?= htmlspecialchars($c['archivo']) ?>" target="_blank"
                                                class="btn btn-warning btn-sm">Ver</a>
                                            <a href="admin.php?tab=certificados&eliminar_c=<?= $c['id'] ?>"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('¿Eliminar este certificado?')">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============ TAB LOGS ============ -->
            <?php elseif ($tab === 'logs'): ?>

                <div class="card">
                    <h3>📋 Registro de accesos y descargas</h3>
                    <div class="search-box">
                        <input type="text" id="buscar-log" placeholder="Buscar por nombre, documento o recurso..."
                            onkeyup="filtrarTabla('tabla-log', this.value)">
                    </div>
                    <div style="overflow-x:auto;">
                        <table id="tabla-log">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Participante</th>
                                    <th>Documento</th>
                                    <th>Acción</th>
                                    <th>Recurso</th>
                                    <th>IP</th>
                                    <th>Firma</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($log['fecha'])) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($log['nombre']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($log['documento']) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $clase = match ($log['accion']) {
                                                'login' => 'accion-login',
                                                'descarga' => 'accion-descarga',
                                                'acepto_terminos' => 'accion-terminos',
                                                default => ''
                                            };
                                            $label = match ($log['accion']) {
                                                'login' => '🔑 Ingreso',
                                                'descarga' => '⬇️ Descarga',
                                                'acepto_terminos' => '✅ Aceptó términos',
                                                default => $log['accion']
                                            };
                                            ?>
                                            <span class="<?= $clase ?>">
                                                <?= $label ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $log['recurso_nombre'] ? htmlspecialchars($log['recurso_nombre']) : '—' ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($log['ip']) ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['firma_imagen']) && strpos($log['firma_imagen'], 'data:image') === 0): ?>
                                                <img src="<?= htmlspecialchars($log['firma_imagen']) ?>" class="firma-thumb"
                                                    alt="Firma"
                                                    style="max-width:120px;max-height:50px;border:1px solid #ddd;border-radius:4px;">
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============ TAB CONFIGURACION ============ -->
            <?php elseif ($tab === 'configuracion'): ?>

                <div class="card">
                    <h3>🎨 Configuración general</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="field">
                                <label>Título del evento</label>
                                <input type="text" name="titulo_evento"
                                    value="<?= htmlspecialchars($config['titulo_evento'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label>Banner del evento (imagen)</label>
                                <input type="file" name="banner" accept="image/*">
                                <?php if (!empty($config['banner'])): ?>
                                    <div style="margin-top:12px;">
                                        <p style="font-size:12px;color:#888;margin-bottom:6px;">Banner actual:</p>
                                        <img src="uploads/<?= htmlspecialchars($config['banner']) ?>"
                                            style="max-width:100%;max-height:180px;border-radius:6px;border:1px solid #ddd;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="submit" name="guardar_config" class="btn btn-primary">Guardar configuración</button>
                    </form>
                </div>

                <!-- ============ TAB PASSWORD ============ -->
            <?php elseif ($tab === 'password'): ?>

                <div class="card" style="max-width:480px;">
                    <h3>🔑 Cambiar contraseña del administrador</h3>
                    <form method="POST">
                        <label>Contraseña actual</label>
                        <input type="password" name="password_actual" style="margin-bottom:14px;" required>
                        <label>Nueva contraseña</label>
                        <input type="password" name="password_nueva" style="margin-bottom:14px;" required>
                        <label>Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirma" style="margin-bottom:18px;" required>
                        <button type="submit" name="cambiar_password" class="btn btn-primary">Cambiar contraseña</button>
                    </form>
                </div>

            <?php endif; ?>

        </div><!-- /main -->

        <script>
            function filtrarTabla(tablaId, query) {
                const filas = document.querySelectorAll('#' + tablaId + ' tbody tr');
                const q = query.toLowerCase();
                filas.forEach(fila => {
                    fila.style.display = fila.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            }

            function abrirEditarP(id, doc, nom, activo) {
                document.getElementById('edit-p-id').value = id;
                document.getElementById('edit-p-doc').value = doc;
                document.getElementById('edit-p-nom').value = nom;
                document.getElementById('edit-p-activo').checked = activo == 1;
                document.getElementById('modal-p').classList.add('open');
            }

            function abrirEditarR(id, nom, desc, activo) {
                document.getElementById('edit-r-id').value = id;
                document.getElementById('edit-r-nom').value = nom;
                document.getElementById('edit-r-desc').value = desc;
                document.getElementById('edit-r-activo').checked = activo == 1;
                document.getElementById('modal-r').classList.add('open');
            }

            function cerrarModal(id) {
                document.getElementById(id).classList.remove('open');
            }

            // Cerrar modal al hacer clic fuera
            document.querySelectorAll('.modal-overlay').forEach(overlay => {
                overlay.addEventListener('click', function (e) {
                    if (e.target === this) this.classList.remove('open');
                });
            });
        </script>

</body>

</html>