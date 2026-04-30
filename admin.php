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
            $_SESSION['admin_id']      = $admin['id'];
            $_SESSION['admin_usuario'] = $admin['usuario'];
            $_SESSION['admin_rol']     = !empty($admin['rol'])    ? $admin['rol']    : 'admin';
            $_SESSION['admin_nombre']  = !empty($admin['nombre']) ? $admin['nombre'] : $admin['usuario'];
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

// Rol del admin autenticado — disponible en todo el resto del archivo
$admin_rol    = $_SESSION['admin_rol'] ?? 'admin';
$is_admin     = $admin_rol === 'admin';
$is_evaluador = $admin_rol === 'evaluador';

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

function av_mmss(int $s): string {
    if ($s <= 0) return '—';
    return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
}

if (!empty($_SESSION['flash']['ok']))  { $mensaje   = $_SESSION['flash']['ok'];  unset($_SESSION['flash']['ok']);  }
if (!empty($_SESSION['flash']['err'])) { $error_msg = $_SESSION['flash']['err']; unset($_SESSION['flash']['err']); }

// ===================== SELECTOR DE EVENTO ACTIVO =====================
// Cambio de evento desde topbar (GET ?set_evento=N)
if (isset($_GET['set_evento'])) {
    $sev = (int) $_GET['set_evento'];
    if ($sev > 0) { $_SESSION['admin_evento_id'] = $sev; }
    header('Location: admin.php?tab=' . urlencode($tab));
    exit;
}

$todos_eventos = $pdo->query(
    "SELECT id, nombre, es_default FROM eventos WHERE activo = 1 ORDER BY id ASC"
)->fetchAll();

if (!isset($_SESSION['admin_evento_id'])) {
    foreach ($todos_eventos as $_ev) {
        if ($_ev['es_default']) { $_SESSION['admin_evento_id'] = (int) $_ev['id']; break; }
    }
    if (!isset($_SESSION['admin_evento_id']) && !empty($todos_eventos)) {
        $_SESSION['admin_evento_id'] = (int) $todos_eventos[0]['id'];
    }
}
$admin_evento_id     = (int) ($_SESSION['admin_evento_id'] ?? 1);
$admin_evento_nombre = '';
foreach ($todos_eventos as $_ev) {
    if ((int) $_ev['id'] === $admin_evento_id) { $admin_evento_nombre = $_ev['nombre']; break; }
}

// ===================== EVENTOS =====================
if ($tab === 'eventos') {

    // Crear evento
    if (isset($_POST['agregar_evento'])) {
        $ev_nombre = trim($_POST['ev_nombre'] ?? '');
        $ev_slug   = strtolower(trim($_POST['ev_slug'] ?? ''));
        $ev_activo = isset($_POST['ev_activo']) ? 1 : 0;
        if ($ev_nombre && $ev_slug) {
            $ev_slug = preg_replace('/[^a-z0-9\-]/', '-', $ev_slug);
            try {
                $pdo->prepare("INSERT INTO eventos (nombre, slug, activo) VALUES (?, ?, ?)")
                    ->execute([$ev_nombre, $ev_slug, $ev_activo]);
                $_SESSION['flash']['ok'] = 'Evento creado correctamente.';
            } catch (PDOException $e) {
                $_SESSION['flash']['err'] = 'Error: el slug ya existe o los datos son inválidos.';
            }
        } else {
            $_SESSION['flash']['err'] = 'Nombre y slug son obligatorios.';
        }
        header('Location: admin.php?tab=eventos');
        exit;
    }

    // Editar evento
    if (isset($_POST['editar_evento'])) {
        $ev_id             = (int) ($_POST['ev_id'] ?? 0);
        $ev_nombre         = trim($_POST['ev_nombre'] ?? '');
        $ev_slug           = strtolower(trim($_POST['ev_slug'] ?? ''));
        $ev_activo         = isset($_POST['ev_activo'])     ? 1 : 0;
        $ev_finalizado     = isset($_POST['ev_finalizado']) ? 1 : 0;
        $ev_msg_finalizado = trim($_POST['ev_mensaje_finalizado'] ?? '') ?: null;

        if ($ev_id && $ev_nombre && $ev_slug) {
            $ev_slug = preg_replace('/[^a-z0-9\-]/', '-', $ev_slug);
            try {
                $curr_stmt = $pdo->prepare("SELECT finalizado FROM eventos WHERE id = ?");
                $curr_stmt->execute([$ev_id]);
                $was_finalizado = (int) ($curr_stmt->fetchColumn() ?? 0);

                if ($ev_finalizado === 1 && $was_finalizado === 0) {
                    // Transición 0→1: sellar finalizado_en = NOW()
                    $pdo->prepare("UPDATE eventos SET nombre=?, slug=?, activo=?, finalizado=1, finalizado_en=NOW(), mensaje_finalizado=? WHERE id=?")
                        ->execute([$ev_nombre, $ev_slug, $ev_activo, $ev_msg_finalizado, $ev_id]);
                } elseif ($ev_finalizado === 0) {
                    // Desmarcar: limpiar timestamp
                    $pdo->prepare("UPDATE eventos SET nombre=?, slug=?, activo=?, finalizado=0, finalizado_en=NULL, mensaje_finalizado=? WHERE id=?")
                        ->execute([$ev_nombre, $ev_slug, $ev_activo, $ev_msg_finalizado, $ev_id]);
                } else {
                    // Sigue finalizado: conservar finalizado_en existente
                    $pdo->prepare("UPDATE eventos SET nombre=?, slug=?, activo=?, finalizado=1, mensaje_finalizado=? WHERE id=?")
                        ->execute([$ev_nombre, $ev_slug, $ev_activo, $ev_msg_finalizado, $ev_id]);
                }
                $_SESSION['flash']['ok'] = 'Evento actualizado.';
            } catch (PDOException $e) {
                $_SESSION['flash']['err'] = 'Error al actualizar: el slug podría ya existir.';
            }
        }
        header('Location: admin.php?tab=eventos');
        exit;
    }

    $eventos_lista = $pdo->query("
        SELECT e.*,
               (SELECT COUNT(*) FROM evento_participantes ep WHERE ep.evento_id = e.id AND ep.activo = 1) AS total_p,
               (SELECT COUNT(*) FROM recursos r WHERE r.evento_id = e.id AND r.activo = 1) AS total_r
        FROM   eventos e
        ORDER  BY e.id ASC
    ")->fetchAll();
}

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
                $stmt = $pdo->prepare("INSERT INTO certificados (evento_id, participante_id, nombre, archivo) VALUES (?, ?, ?, ?)");
                $stmt->execute([$admin_evento_id, $participante_id, $nombre, $nombre_archivo]);
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

    $stmt_cert = $pdo->prepare("
        SELECT c.*, p.nombre AS nombre_participante, p.documento
        FROM certificados c
        JOIN participantes p ON c.participante_id = p.id
        WHERE c.evento_id = ?
        ORDER BY p.nombre ASC
    ");
    $stmt_cert->execute([$admin_evento_id]);
    $certificados = $stmt_cert->fetchAll();

    $stmt_plista = $pdo->prepare("
        SELECT p.id, p.documento, p.nombre
        FROM participantes p
        JOIN evento_participantes ep ON ep.persona_id = p.id AND ep.evento_id = ? AND ep.activo = 1
        WHERE p.activo = 1
        ORDER BY p.nombre ASC
    ");
    $stmt_plista->execute([$admin_evento_id]);
    $participantes_lista = $stmt_plista->fetchAll();
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

    $stmt_p = $pdo->prepare("
        SELECT p.*
        FROM participantes p
        JOIN evento_participantes ep ON ep.persona_id = p.id AND ep.evento_id = ? AND ep.activo = 1
        ORDER BY p.nombre ASC
    ");
    $stmt_p->execute([$admin_evento_id]);
    $participantes = $stmt_p->fetchAll();
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
                $stmt = $pdo->prepare("INSERT INTO recursos (evento_id, nombre, descripcion, archivo, tipo, url_externa) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$admin_evento_id, $nom, $desc, 'externo', 'url', $url_externa]);
                $_SESSION['flash']['ok'] = 'Recurso externo agregado correctamente.';
                header('Location: admin.php?tab=recursos');
                exit;

                // Recurso con archivo
            } elseif (isset($_FILES['archivo_recurso']) && $_FILES['archivo_recurso']['error'] === 0) {
                $nombre_archivo = time() . '_' . basename($_FILES['archivo_recurso']['name']);
                $destino = 'uploads/' . $nombre_archivo;
                if (move_uploaded_file($_FILES['archivo_recurso']['tmp_name'], $destino)) {
                    $ext = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
                    $stmt = $pdo->prepare("INSERT INTO recursos (evento_id, nombre, descripcion, archivo, tipo, url_externa) VALUES (?, ?, ?, ?, ?, NULL)");
                    $stmt->execute([$admin_evento_id, $nom, $desc, $nombre_archivo, $ext]);
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
        $valor_tiempo   = max(1, (int) ($_POST['video_expira_horas'] ?? 4));
        $unidad_tiempo  = ($_POST['unidad_tiempo'] ?? 'horas') === 'minutos' ? 'minutos' : 'horas';
        $horas          = ($unidad_tiempo === 'minutos')
                            ? max(1/60, $valor_tiempo / 60)   // mínimo 1 minuto
                            : max(1/60, (float) $valor_tiempo);

        $seleccionados = array_map('intval', $_POST['participantes_video'] ?? []);

        if (!$nom) {
            $_SESSION['flash']['err'] = 'El nombre del video es obligatorio.';
        } elseif (!empty($video_url)) {
            // ── Opción 1: URL externa ─────────────────────────────────────────
            $stmt = $pdo->prepare("INSERT INTO recursos (evento_id, nombre, descripcion, archivo, tipo, url_externa, es_video, ruta_video, video_expira_horas) VALUES (?, ?, ?, 'externo_video', 'video', ?, 1, ?, ?)");
            $stmt->execute([$admin_evento_id, $nom, $desc, $video_url, $video_url, $horas]);
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
                    $stmt = $pdo->prepare("INSERT INTO recursos (evento_id, nombre, descripcion, archivo, tipo, es_video, ruta_video, video_expira_horas) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
                    $stmt->execute([$admin_evento_id, $nom, $desc, $nombre_archivo, $ext, $nombre_archivo, $horas]);
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
                    $stmt = $pdo->prepare("INSERT INTO recursos (evento_id, nombre, descripcion, archivo, tipo, es_video, ruta_video, video_expira_horas) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
                    $stmt->execute([$admin_evento_id, $nom, $desc, $nombre_archivo, $ext, $nombre_archivo, $horas]);
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
            $stmt = $pdo->prepare("INSERT INTO recursos (evento_id, nombre, descripcion, archivo, tipo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$admin_evento_id, $nom, $desc, $archivo, $ext]);
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

    $stmt_rec = $pdo->prepare("SELECT * FROM recursos WHERE evento_id = ? ORDER BY created_at DESC");
    $stmt_rec->execute([$admin_evento_id]);
    $recursos = $stmt_rec->fetchAll();

    $stmt_pvid = $pdo->prepare("
        SELECT p.id, p.documento, p.nombre
        FROM participantes p
        JOIN evento_participantes ep ON ep.persona_id = p.id AND ep.evento_id = ? AND ep.activo = 1
        WHERE p.activo = 1
        ORDER BY p.nombre ASC
    ");
    $stmt_pvid->execute([$admin_evento_id]);
    $participantes_video = $stmt_pvid->fetchAll();

    $perms_raw = $pdo->query("SELECT recurso_id, participante_id FROM recurso_permisos_video WHERE activo = 1")->fetchAll();
    $video_perms = [];
    foreach ($perms_raw as $pr) {
        $video_perms[(int) $pr['recurso_id']][] = (int) $pr['participante_id'];
    }
}

// ===================== CONFIGURACION =====================
if ($tab === 'configuracion') {
    if (isset($_POST['guardar_config'])) {
        $titulo_login = trim($_POST['titulo_evento']);

        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === 0) {
            $nombre_banner = 'banner_' . time() . '_' . basename($_FILES['banner']['name']);
            $destino = 'uploads/' . $nombre_banner;
            if (move_uploaded_file($_FILES['banner']['tmp_name'], $destino)) {
                $pdo->prepare("UPDATE eventos SET titulo_login = ?, banner = ? WHERE id = ?")
                    ->execute([$titulo_login, $nombre_banner, $admin_evento_id]);
            } else {
                $pdo->prepare("UPDATE eventos SET titulo_login = ? WHERE id = ?")
                    ->execute([$titulo_login, $admin_evento_id]);
            }
        } else {
            $pdo->prepare("UPDATE eventos SET titulo_login = ? WHERE id = ?")
                ->execute([$titulo_login, $admin_evento_id]);
        }
        $_SESSION['flash']['ok'] = 'Configuración del evento guardada.';
        header('Location: admin.php?tab=configuracion');
        exit;
    }

    $stmt_ev_cfg = $pdo->prepare("SELECT titulo_login, banner, nombre FROM eventos WHERE id = ? LIMIT 1");
    $stmt_ev_cfg->execute([$admin_evento_id]);
    $ev_cfg = $stmt_ev_cfg->fetch();
    $config = [
        'titulo_evento' => $ev_cfg['titulo_login'] ?: ($ev_cfg['nombre'] ?? ''),
        'banner'        => $ev_cfg['banner'] ?? '',
    ];
}

// ===================== LOGS =====================
if ($tab === 'logs') {
    $stmt_logs = $pdo->prepare("
        SELECT a.*, p.nombre, p.documento, r.nombre AS recurso_nombre
        FROM accesos a
        JOIN participantes p ON a.participante_id = p.id
        LEFT JOIN recursos r ON a.recurso_id = r.id
        WHERE a.evento_id = ?
        ORDER BY a.fecha DESC
        LIMIT 300
    ");
    $stmt_logs->execute([$admin_evento_id]);
    $logs = $stmt_logs->fetchAll();
}

// ===================== ANALYTICS VIDEOS =====================
if ($tab === 'analytics_videos') {
    $stmt_avr = $pdo->prepare("SELECT id, nombre FROM recursos WHERE es_video = 1 AND evento_id = ? ORDER BY nombre ASC");
    $stmt_avr->execute([$admin_evento_id]);
    $av_recursos = $stmt_avr->fetchAll();

    $stmt_avp = $pdo->prepare("
        SELECT p.id, p.nombre, p.documento
        FROM participantes p
        JOIN evento_participantes ep ON ep.persona_id = p.id AND ep.evento_id = ? AND ep.activo = 1
        ORDER BY p.nombre ASC
    ");
    $stmt_avp->execute([$admin_evento_id]);
    $av_participantes = $stmt_avp->fetchAll();

    $av_f_recurso      = isset($_GET['f_recurso'])      ? (int) $_GET['f_recurso']      : 0;
    $av_f_participante = isset($_GET['f_participante']) ? (int) $_GET['f_participante'] : 0;
    $av_f_estado       = in_array($_GET['f_estado'] ?? '', ['completado', 'en_progreso'], true)
                             ? $_GET['f_estado'] : '';

    $av_where  = ['r.evento_id = ?'];
    $av_params = [$admin_evento_id];

    if ($av_f_recurso > 0) {
        $av_where[]  = 'vv.recurso_id = ?';
        $av_params[] = $av_f_recurso;
    }
    if ($av_f_participante > 0) {
        $av_where[]  = 'vv.participante_id = ?';
        $av_params[] = $av_f_participante;
    }
    if ($av_f_estado === 'completado') {
        $av_where[] = 'vv.completed = 1';
    } elseif ($av_f_estado === 'en_progreso') {
        $av_where[] = 'vv.completed = 0';
    }

    $av_sql = "
        SELECT vv.started_at, vv.last_seen_at,
               vv.seconds_watched, vv.video_duration, vv.percent_watched, vv.completed,
               p.nombre  AS p_nombre,  p.documento AS p_documento,
               r.nombre  AS r_nombre
        FROM   video_visualizaciones vv
        JOIN   participantes p ON p.id = vv.participante_id
        JOIN   recursos      r ON r.id = vv.recurso_id
    ";
    if ($av_where) {
        $av_sql .= ' WHERE ' . implode(' AND ', $av_where);
    }
    $av_sql .= ' ORDER BY vv.last_seen_at DESC, vv.started_at DESC LIMIT 500';

    $av_stmt = $pdo->prepare($av_sql);
    $av_stmt->execute($av_params);
    $av_rows = $av_stmt->fetchAll();

    // Aggregate metrics (always scoped to current event, ignoring row-level filters)
    $av_metrics_stmt = $pdo->prepare("
        SELECT
            COUNT(*)                              AS total_visualizaciones,
            COUNT(DISTINCT vv.participante_id)    AS total_participantes,
            SUM(vv.completed = 1)                 AS total_completados,
            ROUND(AVG(vv.percent_watched) * 100, 1) AS promedio_porcentaje
        FROM video_visualizaciones vv
        JOIN recursos r ON r.id = vv.recurso_id
        WHERE r.evento_id = ?
    ");
    $av_metrics_stmt->execute([$admin_evento_id]);
    $av_metrics = $av_metrics_stmt->fetch();
}

// ===================== EVALUADORES =====================
if ($tab === 'evaluadores') {

    if (!$is_admin) {
        $error_msg = 'Acceso denegado.';
    } else {

        // ── Crear evaluador ───────────────────────────────────────
        if (isset($_POST['crear_evaluador'])) {
            $ev_usu  = trim($_POST['ev_usuario'] ?? '');
            $ev_pass = $_POST['ev_password'] ?? '';
            $ev_nom  = trim($_POST['ev_nombre'] ?? '');

            if ($ev_usu && strlen($ev_pass) >= 6) {
                try {
                    $hash = password_hash($ev_pass, PASSWORD_DEFAULT);
                    $pdo->prepare(
                        "INSERT INTO admins (usuario, password, rol, nombre) VALUES (?, ?, 'evaluador', ?)"
                    )->execute([$ev_usu, $hash, $ev_nom ?: $ev_usu]);
                    $_SESSION['flash']['ok'] = "Evaluador '{$ev_usu}' creado correctamente.";
                } catch (PDOException $e) {
                    $_SESSION['flash']['err'] = 'El usuario ya existe o los datos son inválidos.';
                }
            } else {
                $_SESSION['flash']['err'] = 'Usuario obligatorio y contraseña mínimo 6 caracteres.';
            }
            header('Location: admin.php?tab=evaluadores');
            exit;
        }

        // ── Actualizar asignaciones de eventos de un evaluador ────
        if (isset($_POST['actualizar_asignaciones'])) {
            $asig_admin_id = (int) ($_POST['asig_admin_id'] ?? 0);
            $eventos_sel   = array_map('intval', (array) ($_POST['asig_eventos'] ?? []));

            if ($asig_admin_id) {
                // Solo actuar si el ID corresponde a un evaluador real
                $stmt_chk = $pdo->prepare(
                    "SELECT id FROM admins WHERE id = ? AND rol = 'evaluador'"
                );
                $stmt_chk->execute([$asig_admin_id]);
                if ($stmt_chk->fetch()) {
                    $pdo->prepare(
                        "DELETE FROM trabajo_evaluador_eventos WHERE admin_id = ?"
                    )->execute([$asig_admin_id]);

                    if (!empty($eventos_sel)) {
                        $stmt_ins = $pdo->prepare(
                            "INSERT IGNORE INTO trabajo_evaluador_eventos (admin_id, evento_id)
                             VALUES (?, ?)"
                        );
                        foreach ($eventos_sel as $eid) {
                            if ($eid > 0) { $stmt_ins->execute([$asig_admin_id, $eid]); }
                        }
                    }
                    $_SESSION['flash']['ok'] = 'Asignaciones de eventos actualizadas.';
                }
            }
            header('Location: admin.php?tab=evaluadores');
            exit;
        }

        // ── Eliminar evaluador ────────────────────────────────────
        if (isset($_POST['eliminar_evaluador'])) {
            $del_id = (int) ($_POST['evaluador_id'] ?? 0);
            // Nunca eliminar al admin actual ni a cuentas que no sean evaluadores
            if ($del_id && $del_id !== (int) $_SESSION['admin_id']) {
                $pdo->prepare(
                    "DELETE FROM admins WHERE id = ? AND rol = 'evaluador'"
                )->execute([$del_id]);
                // Las filas en trabajo_evaluador_eventos se eliminan en CASCADE (fk_tee_admin)
                $_SESSION['flash']['ok'] = 'Evaluador eliminado.';
            }
            header('Location: admin.php?tab=evaluadores');
            exit;
        }

    } // end $is_admin check

    // ── Data loading para la vista ────────────────────────────────
    $evaluadores_lista = $pdo->query(
        "SELECT a.id, a.usuario, a.nombre, a.created_at,
                GROUP_CONCAT(tee.evento_id ORDER BY tee.evento_id) AS eventos_asignados_ids
         FROM   admins a
         LEFT   JOIN trabajo_evaluador_eventos tee ON tee.admin_id = a.id
         WHERE  a.rol = 'evaluador'
         GROUP  BY a.id
         ORDER  BY a.created_at ASC"
    )->fetchAll();

    // Mapa rápido id→nombre para renderizar badges de eventos asignados
    $eventos_mapa = [];
    foreach ($todos_eventos as $_tev) {
        $eventos_mapa[(int) $_tev['id']] = $_tev['nombre'];
    }
}

// ===================== TRABAJO INTEGRADOR =====================
if ($tab === 'trabajo_integrador') {

    // ── Guardar / actualizar configuración ────────────────────────
    if (isset($_POST['guardar_trabajo_config']) && $is_admin) {
        $tc_titulo    = trim($_POST['tc_titulo'] ?? '') ?: 'Trabajo integrador final';
        $tc_desc      = trim($_POST['tc_descripcion'] ?? '');
        $tc_activo    = isset($_POST['tc_activo'])             ? 1 : 0;
        $tc_reentrega = isset($_POST['tc_permite_reentrega'])  ? 1 : 0;
        $tc_cal_max   = min(100, max(0, (float) ($_POST['tc_calificacion_maxima'] ?? 100)));
        $tc_fecha_raw = trim($_POST['tc_fecha_limite'] ?? '');
        $tc_fecha     = $tc_fecha_raw
            ? date('Y-m-d H:i:s', strtotime($tc_fecha_raw))
            : null;

        $pdo->prepare(
            "INSERT INTO trabajo_integrador_config
                 (evento_id, activo, titulo, descripcion, fecha_limite,
                  permite_reentrega, calificacion_maxima)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 activo              = VALUES(activo),
                 titulo              = VALUES(titulo),
                 descripcion         = VALUES(descripcion),
                 fecha_limite        = VALUES(fecha_limite),
                 permite_reentrega   = VALUES(permite_reentrega),
                 calificacion_maxima = VALUES(calificacion_maxima)"
        )->execute([
            $admin_evento_id, $tc_activo, $tc_titulo, $tc_desc ?: null,
            $tc_fecha, $tc_reentrega, $tc_cal_max,
        ]);

        $_SESSION['flash']['ok'] = 'Configuración del trabajo integrador guardada.';
        header('Location: admin.php?tab=trabajo_integrador');
        exit;
    }

    // ── Subir recurso base ────────────────────────────────────────
    if (isset($_POST['subir_trabajo_recurso']) && $is_admin) {

        // Verificar que ya existe una config para este evento
        $stmt_check_conf = $pdo->prepare(
            "SELECT id FROM trabajo_integrador_config WHERE evento_id = ? LIMIT 1"
        );
        $stmt_check_conf->execute([$admin_evento_id]);
        $conf_row = $stmt_check_conf->fetch();

        if (!$conf_row) {
            $_SESSION['flash']['err'] = 'Guarda primero la configuración del trabajo antes de subir recursos.';
            header('Location: admin.php?tab=trabajo_integrador');
            exit;
        }

        $tr_nombre      = trim($_POST['tr_nombre'] ?? '');
        $tr_descripcion = trim($_POST['tr_descripcion'] ?? '');
        $allowed_ext    = ['pdf','doc','docx','xls','xlsx','zip','ppt','pptx'];
        $allowed_mime   = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-zip-compressed',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        $file = $_FILES['tr_archivo'] ?? null;

        if (!$tr_nombre) {
            $_SESSION['flash']['err'] = 'El nombre del recurso es obligatorio.';
            header('Location: admin.php?tab=trabajo_integrador'); exit;
        }
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash']['err'] = 'Error al recibir el archivo (código: ' . ($file['error'] ?? '?') . ').';
            header('Location: admin.php?tab=trabajo_integrador'); exit;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            $_SESSION['flash']['err'] = 'Extensión no permitida. Usa: ' . implode(', ', $allowed_ext) . '.';
            header('Location: admin.php?tab=trabajo_integrador'); exit;
        }
        if ($file['size'] > 25 * 1024 * 1024) {
            $_SESSION['flash']['err'] = 'El archivo supera el límite de 25 MB.';
            header('Location: admin.php?tab=trabajo_integrador'); exit;
        }

        // Validar MIME real (no solo la extensión)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_mime)) {
            $_SESSION['flash']['err'] = 'Tipo de archivo no permitido (contenido inválido).';
            header('Location: admin.php?tab=trabajo_integrador'); exit;
        }

        // Nombre seguro: basename() elimina cualquier componente de directorio
        $safe_base = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($file['name']));
        $filename  = 'trabajo_' . $admin_evento_id . '_' . time() . '_' . $safe_base;
        $destino   = 'uploads/trabajo/' . $filename;

        if (!is_dir('uploads/trabajo')) { mkdir('uploads/trabajo', 0755, true); }

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            $_SESSION['flash']['err'] = 'No se pudo guardar el archivo en el servidor.';
            header('Location: admin.php?tab=trabajo_integrador'); exit;
        }

        $pdo->prepare(
            "INSERT INTO trabajo_integrador_recursos
                 (config_id, evento_id, nombre, descripcion, archivo, tipo, tamanio)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $conf_row['id'], $admin_evento_id, $tr_nombre,
            $tr_descripcion ?: null, $filename, $ext, $file['size'],
        ]);

        $_SESSION['flash']['ok'] = "Recurso «{$tr_nombre}» subido correctamente.";
        header('Location: admin.php?tab=trabajo_integrador');
        exit;
    }

    // ── Eliminar recurso base ─────────────────────────────────────
    if (isset($_POST['eliminar_trabajo_recurso']) && $is_admin) {
        $del_tr_id = (int) ($_POST['tr_recurso_id'] ?? 0);
        if ($del_tr_id) {
            // Verificar que el recurso pertenece al evento activo (evita borrado cruzado)
            $stmt_del = $pdo->prepare(
                "SELECT r.archivo
                 FROM   trabajo_integrador_recursos r
                 JOIN   trabajo_integrador_config   c ON c.id = r.config_id
                 WHERE  r.id = ? AND c.evento_id = ? LIMIT 1"
            );
            $stmt_del->execute([$del_tr_id, $admin_evento_id]);
            $del_row = $stmt_del->fetch();
            if ($del_row) {
                $del_path = 'uploads/trabajo/' . basename($del_row['archivo']);
                if (file_exists($del_path)) { unlink($del_path); }
                $pdo->prepare("DELETE FROM trabajo_integrador_recursos WHERE id = ?")
                    ->execute([$del_tr_id]);
                $_SESSION['flash']['ok'] = 'Recurso eliminado.';
            }
        }
        header('Location: admin.php?tab=trabajo_integrador');
        exit;
    }

    // ── Evaluar entrega ───────────────────────────────────────────
    if (isset($_POST['evaluar_entrega']) && ($is_admin || $is_evaluador)) {
        $ev_id        = (int) ($_POST['entrega_id'] ?? 0);
        $ev_estado    = $_POST['ev_estado'] ?? '';
        $ev_cal_raw   = trim($_POST['ev_calificacion'] ?? '');
        $ev_coment    = trim($_POST['ev_comentarios'] ?? '');
        $estados_ok   = ['revisado', 'requiere_ajustes'];

        if (!$ev_id || !in_array($ev_estado, $estados_ok)) {
            $_SESSION['flash']['err'] = 'Datos de evaluación inválidos.';
            header('Location: admin.php?tab=trabajo_integrador'); exit;
        }

        // Leer calificacion_maxima del config para validar rango
        $stmt_cal_max = $pdo->prepare(
            "SELECT calificacion_maxima
             FROM   trabajo_integrador_config
             WHERE  evento_id = ? LIMIT 1"
        );
        $stmt_cal_max->execute([$admin_evento_id]);
        $cal_max_row = $stmt_cal_max->fetch();
        $cal_max     = $cal_max_row ? (float) $cal_max_row['calificacion_maxima'] : 100.0;

        $ev_cal = $ev_cal_raw !== '' ? min($cal_max, max(0, (float) $ev_cal_raw)) : null;

        // Verificar que la entrega pertenece al evento activo
        $stmt_ent_chk = $pdo->prepare(
            "SELECT id FROM trabajo_integrador_entregas WHERE id = ? AND evento_id = ? LIMIT 1"
        );
        $stmt_ent_chk->execute([$ev_id, $admin_evento_id]);
        if (!$stmt_ent_chk->fetch()) {
            $_SESSION['flash']['err'] = 'Entrega no encontrada en este evento.';
            header('Location: admin.php?tab=trabajo_integrador'); exit;
        }

        $pdo->prepare(
            "UPDATE trabajo_integrador_entregas
             SET    estado                = ?,
                    calificacion          = ?,
                    comentarios_evaluador = ?,
                    evaluador_id          = ?,
                    fecha_evaluacion      = NOW()
             WHERE  id = ?"
        )->execute([$ev_estado, $ev_cal, $ev_coment ?: null, $_SESSION['admin_id'], $ev_id]);

        $_SESSION['flash']['ok'] = 'Evaluación guardada correctamente.';
        header('Location: admin.php?tab=trabajo_integrador'); exit;
    }

    // ── Data loading ──────────────────────────────────────────────
    $trabajo_config   = null;
    $trabajo_recursos = [];
    $trabajo_entregas = [];

    $stmt_tc = $pdo->prepare(
        "SELECT * FROM trabajo_integrador_config WHERE evento_id = ? LIMIT 1"
    );
    $stmt_tc->execute([$admin_evento_id]);
    $trabajo_config = $stmt_tc->fetch();

    if ($trabajo_config) {
        $stmt_tr = $pdo->prepare(
            "SELECT * FROM trabajo_integrador_recursos
             WHERE  config_id = ? AND activo = 1
             ORDER  BY orden ASC, creado_en ASC"
        );
        $stmt_tr->execute([$trabajo_config['id']]);
        $trabajo_recursos = $stmt_tr->fetchAll();
    }

    // Entregas: visible para admin (todas) y evaluador (mismo evento si asignado)
    if ($is_admin || $is_evaluador) {
        // Evaluador: solo puede ver si está asignado a este evento
        $puede_ver_entregas = $is_admin;
        if ($is_evaluador) {
            $stmt_asig_chk = $pdo->prepare(
                "SELECT id FROM trabajo_evaluador_eventos
                 WHERE admin_id = ? AND evento_id = ? LIMIT 1"
            );
            $stmt_asig_chk->execute([$_SESSION['admin_id'], $admin_evento_id]);
            $puede_ver_entregas = (bool) $stmt_asig_chk->fetch();
        }

        if ($puede_ver_entregas) {
            $stmt_ent = $pdo->prepare(
                "SELECT te.id,
                        te.participante_id,
                        te.archivo,
                        te.nombre_original,
                        te.estado,
                        te.calificacion,
                        te.comentarios_evaluador,
                        te.fecha_entrega,
                        te.fecha_evaluacion,
                        p.nombre    AS participante_nombre,
                        p.documento AS participante_documento
                 FROM   trabajo_integrador_entregas te
                 JOIN   evento_participantes ep ON ep.id  = te.participante_id
                 JOIN   personas             p  ON p.id  = te.persona_id
                 WHERE  te.evento_id = ?
                 ORDER  BY te.fecha_entrega DESC"
            );
            $stmt_ent->execute([$admin_evento_id]);
            $trabajo_entregas = $stmt_ent->fetchAll();
        }
    }
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

        .badge-warn {
            background: #fff3cd;
            color: #856404;
        }

        .badge-end {
            background: #f8d0d4;
            color: #6b0f1a;
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

        .ev-selector {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
        }

        .ev-selector select {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: white;
            border-radius: 5px;
            padding: 4px 8px;
            font-size: 12px;
            cursor: pointer;
            max-width: 200px;
        }

        .ev-selector select option {
            background: #7b1a2e;
            color: white;
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
            <?php if (count($todos_eventos) > 1): ?>
            <form method="GET" action="admin.php" class="ev-selector">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <span>🗓️</span>
                <select name="set_evento" onchange="this.form.submit()" title="Cambiar evento activo">
                    <?php foreach ($todos_eventos as $_ev): ?>
                    <option value="<?= (int) $_ev['id'] ?>" <?= (int) $_ev['id'] === $admin_evento_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($_ev['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php elseif (!empty($admin_evento_nombre)): ?>
            <span style="font-size:12px;opacity:.8;">🗓️ <?= htmlspecialchars($admin_evento_nombre) ?></span>
            <?php endif; ?>
            <span>👤 <?= htmlspecialchars($_SESSION['admin_usuario']) ?></span>
            <a href="admin.php?logout=1">Cerrar sesión</a>
        </div>
    </div>

    <div class="tabs">
        <a href="admin.php?tab=participantes" class="<?= $tab === 'participantes' ? 'active' : '' ?>">👥
            Participantes</a>
        <a href="admin.php?tab=recursos" class="<?= $tab === 'recursos' ? 'active' : '' ?>">📦 Recursos</a>
        <a href="admin.php?tab=certificados" class="<?= $tab === 'certificados' ? 'active' : '' ?>">🎓 Certificados</a>
        <a href="admin.php?tab=logs" class="<?= $tab === 'logs' ? 'active' : '' ?>">📋 Accesos y Descargas</a>
        <a href="admin.php?tab=analytics_videos" class="<?= $tab === 'analytics_videos' ? 'active' : '' ?>">📊 Analytics Videos</a>
        <a href="admin.php?tab=eventos" class="<?= $tab === 'eventos' ? 'active' : '' ?>">🗓️ Eventos</a>
        <a href="admin.php?tab=configuracion" class="<?= $tab === 'configuracion' ? 'active' : '' ?>">🎨
            Configuración</a>
        <a href="admin.php?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>">🔑 Contraseña</a>
        <?php if ($is_admin): ?>
        <a href="admin.php?tab=evaluadores" class="<?= $tab === 'evaluadores' ? 'active' : '' ?>">👤 Evaluadores</a>
        <?php endif; ?>
        <?php if ($is_admin || $is_evaluador): ?>
        <a href="admin.php?tab=trabajo_integrador" class="<?= $tab === 'trabajo_integrador' ? 'active' : '' ?>">📝 Trabajo Integrador</a>
        <?php endif; ?>
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
                                <input type="file" name="video_archivo" id="video_archivo_input" accept=".mp4,.webm,.ogg,.mov,.avi,.mkv,.m4v">
                                <p style="font-size:12px;color:#d97706;margin-top:4px;font-weight:500;">⚠ Solo para archivos pequeños. Si el video pesa más de 16&nbsp;MB, súbelo por FTP/SFTP a <code>private_videos/</code> y selecciónalo con la opción ③.</p>
                                <p id="video_archivo_error" style="display:none;font-size:12px;color:#dc2626;margin-top:4px;font-weight:600;"></p>
                            </div>
                            <div class="field">
                                <label>② URL externa del video</label>
                                <input type="text" name="url_video" placeholder="https://...">
                                <p style="font-size:12px;color:#999;margin-top:4px;">Déjala vacía si usas archivo o FTP.</p>
                            </div>
                        </div>
                        <div style="border-top:1px dashed #e5e7eb;padding-top:12px;">
                            <div class="field" style="margin-bottom:0;">
                                <label>③ Seleccionar archivo ya subido por FTP a <code style="font-size:12px;background:#f3f4f6;padding:1px 5px;border-radius:4px;">private_videos/</code></label>
                                <input type="hidden" name="video_existente" id="video_existente_input">
                                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px;">
                                    <button type="button" onclick="abrirModalVideos()"
                                            style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;
                                                   background:#1d4ed8;color:white;border:none;border-radius:8px;
                                                   font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                             fill="none" stroke="currentColor" stroke-width="2.5"
                                             stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        Seleccionar video desde servidor
                                    </button>
                                    <span id="video-seleccionado-texto"
                                          style="font-size:13px;color:#6b7280;font-style:italic;">
                                        Ningún archivo seleccionado
                                    </span>
                                </div>
                                <p style="font-size:12px;color:#999;margin-top:0;">Solo se listan archivos <code>.mp4</code> presentes en <code>private_videos/</code>.</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">⏱ Duración del acceso</div>
                        <div class="form-row" style="margin-bottom:0;">
                            <div class="field" style="max-width:320px;">
                                <label>El enlace expira pasado este tiempo desde que el participante abre el video</label>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <input type="number" name="video_expira_horas" value="4" min="1" max="720"
                                           style="width:90px;">
                                    <select name="unidad_tiempo" style="flex:1;">
                                        <option value="horas">Horas</option>
                                        <option value="minutos">Minutos</option>
                                    </select>
                                </div>
                                <p style="font-size:12px;color:#999;margin-top:4px;">
                                    Ejemplos: 4 horas · 30 minutos · 5 minutos (para pruebas).
                                </p>
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

            <!-- Modal: selector de videos desde servidor -->
            <div class="modal-overlay" id="modal-videos-servidor">
                <div class="modal" style="max-width:580px;">
                    <h4 style="color:#1d4ed8;">📁 Videos disponibles en el servidor</h4>
                    <p style="font-size:13px;color:#6b7280;margin-bottom:14px;">
                        Archivos <code>.mp4</code> en <code>private_videos/</code>, ordenados del más reciente al más antiguo.
                    </p>
                    <div id="modal-videos-lista"
                         style="border:1px solid #e5e7eb;border-radius:8px;max-height:340px;overflow-y:auto;
                                padding:0 12px;background:#f9fafb;">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn" style="background:#eee;color:#333;"
                                onclick="cerrarModal('modal-videos-servidor')">Cerrar</button>
                    </div>
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

                <!-- ============ TAB ANALYTICS VIDEOS ============ -->
            <?php elseif ($tab === 'analytics_videos'): ?>

                <div class="card">
                    <h3>📊 Analytics de visualización de videos</h3>

                    <!-- Metric cards -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:22px;">
                        <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:10px;padding:16px 18px;text-align:center;">
                            <div style="font-size:26px;font-weight:700;color:#6d28d9;">
                                <?= number_format((int)($av_metrics['total_participantes'] ?? 0)) ?>
                            </div>
                            <div style="font-size:12px;color:#6b7280;margin-top:4px;">👥 Participantes únicos</div>
                        </div>
                        <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:10px;padding:16px 18px;text-align:center;">
                            <div style="font-size:26px;font-weight:700;color:#6d28d9;">
                                <?= number_format((int)($av_metrics['total_visualizaciones'] ?? 0)) ?>
                            </div>
                            <div style="font-size:12px;color:#6b7280;margin-top:4px;">🎥 Total visualizaciones</div>
                        </div>
                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px 18px;text-align:center;">
                            <div style="font-size:26px;font-weight:700;color:#16a34a;">
                                <?= number_format((int)($av_metrics['total_completados'] ?? 0)) ?>
                            </div>
                            <div style="font-size:12px;color:#6b7280;margin-top:4px;">✅ Completados</div>
                        </div>
                        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:16px 18px;text-align:center;">
                            <div style="font-size:26px;font-weight:700;color:#ea580c;">
                                <?= number_format((float)($av_metrics['promedio_porcentaje'] ?? 0), 1) ?>%
                            </div>
                            <div style="font-size:12px;color:#6b7280;margin-top:4px;">📊 Promedio visto</div>
                        </div>
                    </div>

                    <form method="GET" action="admin.php" style="margin-bottom:18px;">
                        <input type="hidden" name="tab" value="analytics_videos">
                        <div class="form-row" style="align-items:flex-end;flex-wrap:wrap;">
                            <div class="field">
                                <label>Video</label>
                                <select name="f_recurso">
                                    <option value="0">— Todos los videos —</option>
                                    <?php foreach ($av_recursos as $rv): ?>
                                        <option value="<?= (int) $rv['id'] ?>"
                                            <?= $av_f_recurso === (int) $rv['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rv['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Participante</label>
                                <select name="f_participante">
                                    <option value="0">— Todos —</option>
                                    <?php foreach ($av_participantes as $pv): ?>
                                        <option value="<?= (int) $pv['id'] ?>"
                                            <?= $av_f_participante === (int) $pv['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pv['nombre']) ?>
                                            (<?= htmlspecialchars($pv['documento']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field" style="max-width:180px;">
                                <label>Estado</label>
                                <select name="f_estado">
                                    <option value="" <?= $av_f_estado === '' ? 'selected' : '' ?>>— Todos —</option>
                                    <option value="completado" <?= $av_f_estado === 'completado' ? 'selected' : '' ?>>Completado</option>
                                    <option value="en_progreso" <?= $av_f_estado === 'en_progreso' ? 'selected' : '' ?>>En progreso</option>
                                </select>
                            </div>
                            <div class="field" style="max-width:120px;">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary" style="width:100%;">Filtrar</button>
                            </div>
                            <div class="field" style="max-width:140px;">
                                <label>&nbsp;</label>
                                <a href="export_analytics_excel.php?f_recurso=<?= $av_f_recurso ?>&amp;f_participante=<?= $av_f_participante ?>&amp;f_estado=<?= urlencode($av_f_estado) ?>"
                                   class="btn btn-primary"
                                   style="width:100%;display:inline-block;text-align:center;text-decoration:none;box-sizing:border-box;">
                                    📥 Exportar Excel
                                </a>
                            </div>
                            <?php if ($av_f_recurso || $av_f_participante || $av_f_estado !== ''): ?>
                            <div class="field" style="max-width:120px;">
                                <label>&nbsp;</label>
                                <a href="admin.php?tab=analytics_videos"
                                   class="btn"
                                   style="background:#f3f4f6;color:#374151;width:100%;display:inline-block;text-align:center;text-decoration:none;">
                                    Limpiar
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>

                    <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">
                        <?= count($av_rows) ?> registro<?= count($av_rows) !== 1 ? 's' : '' ?>
                        <?= ($av_f_recurso || $av_f_participante || $av_f_estado !== '') ? ' (filtrado)' : '' ?>
                    </p>

                    <div style="overflow-x:auto;">
                        <table id="tabla-av">
                            <thead>
                                <tr>
                                    <th>Participante</th>
                                    <th>Documento</th>
                                    <th>Video</th>
                                    <th>Inicio</th>
                                    <th>Última actividad</th>
                                    <th>Tiempo visto</th>
                                    <th>Duración</th>
                                    <th>% Visto</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($av_rows)): ?>
                                    <tr>
                                        <td colspan="9"
                                            style="text-align:center;color:#999;padding:28px;">
                                            Sin registros de visualización
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($av_rows as $avr): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($avr['p_nombre']) ?></td>
                                            <td><?= htmlspecialchars($avr['p_documento']) ?></td>
                                            <td><?= htmlspecialchars($avr['r_nombre']) ?></td>
                                            <td>
                                                <?= $avr['started_at']
                                                    ? date('d/m/Y H:i', strtotime($avr['started_at']))
                                                    : '—' ?>
                                            </td>
                                            <td>
                                                <?= $avr['last_seen_at']
                                                    ? date('d/m/Y H:i', strtotime($avr['last_seen_at']))
                                                    : '—' ?>
                                            </td>
                                            <td><?= av_mmss((int) $avr['seconds_watched']) ?></td>
                                            <td><?= av_mmss((int) $avr['video_duration']) ?></td>
                                            <td>
                                                <?= (int) $avr['video_duration'] > 0
                                                    ? round((float) $avr['percent_watched'] * 100, 1) . '%'
                                                    : '—' ?>
                                            </td>
                                            <td>
                                                <?php if ((int) $avr['completed'] === 1): ?>
                                                    <span class="badge badge-ok">✓ Completado</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warn">⏳ En progreso</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============ TAB EVENTOS ============ -->
            <?php elseif ($tab === 'eventos'): ?>

                <div class="card">
                    <h3>➕ Crear nuevo evento</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="field">
                                <label>Nombre del evento</label>
                                <input type="text" name="ev_nombre" id="nuevo-ev-nombre" placeholder="Ej: Diplomado Gestión de Riesgos 2025" required
                                    oninput="autoSlug(this.value)">
                            </div>
                            <div class="field">
                                <label>Slug (URL)</label>
                                <input type="text" name="ev_slug" id="nuevo-ev-slug" placeholder="diplomado-gestion-riesgos-2025" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field" style="max-width:200px;">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" name="ev_activo" value="1" checked style="width:auto;"> Activo
                                </label>
                            </div>
                        </div>
                        <button type="submit" name="agregar_evento" class="btn btn-primary">Crear evento</button>
                    </form>
                </div>

                <div class="card">
                    <h3>🗓️ Eventos (<?= count($eventos_lista) ?>)</h3>
                    <div style="overflow-x:auto;">
                        <table id="tabla-eventos">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Slug</th>
                                    <th>Participantes</th>
                                    <th>Recursos</th>
                                    <th>Estado</th>
                                    <th>Default</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eventos_lista as $ev): ?>
                                <tr>
                                    <td><?= (int) $ev['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($ev['nombre']) ?></strong></td>
                                    <td><code style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($ev['slug']) ?></code></td>
                                    <td><?= (int) $ev['total_p'] ?></td>
                                    <td><?= (int) $ev['total_r'] ?></td>
                                    <td>
                                        <?php if ($ev['finalizado']): ?>
                                            <span class="badge badge-end">Finalizado</span>
                                        <?php elseif ($ev['activo']): ?>
                                            <span class="badge badge-ok">Activo</span>
                                        <?php else: ?>
                                            <span class="badge badge-off">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ev['es_default']): ?>
                                        <span class="badge badge-warn">Default</span>
                                        <?php else: ?>
                                        <span style="color:#ccc;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="td-actions">
                                        <button type="button" class="btn btn-warning btn-sm"
                                            onclick="abrirEditarEv(<?= (int)$ev['id'] ?>, <?= htmlspecialchars(json_encode($ev['nombre'])) ?>, <?= htmlspecialchars(json_encode($ev['slug'])) ?>, <?= (int)$ev['activo'] ?>, <?= (int)$ev['finalizado'] ?>, <?= htmlspecialchars(json_encode($ev['mensaje_finalizado'] ?? '')) ?>)">
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal editar evento -->
                <div class="modal-overlay" id="modal-ev">
                    <div class="modal">
                        <h4>✏️ Editar evento</h4>
                        <form method="POST">
                            <input type="hidden" name="ev_id" id="edit-ev-id">
                            <div class="form-row">
                                <div class="field">
                                    <label>Nombre</label>
                                    <input type="text" name="ev_nombre" id="edit-ev-nombre" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="field">
                                    <label>Slug (URL)</label>
                                    <input type="text" name="ev_slug" id="edit-ev-slug" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="field">
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                        <input type="checkbox" name="ev_activo" id="edit-ev-activo" value="1" style="width:auto;"> Activo
                                    </label>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="field">
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                        <input type="checkbox" name="ev_finalizado" id="edit-ev-finalizado" value="1"
                                               style="width:auto;" onchange="toggleMensajeFinalizado(this.checked)">
                                        <span style="color:#6b0f1a;font-weight:700;">🔒 Evento finalizado</span>
                                    </label>
                                    <p style="font-size:12px;color:#9ca3af;margin-top:4px;margin-left:24px;">
                                        Los participantes verán una pantalla de cierre en lugar del contenido.
                                    </p>
                                </div>
                            </div>
                            <div class="form-row" id="finalizado-msg-row" style="display:none;">
                                <div class="field">
                                    <label>Mensaje de finalización (opcional)</label>
                                    <textarea name="ev_mensaje_finalizado" id="edit-ev-mensaje" rows="3"
                                              style="width:100%;padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;"
                                              placeholder="Gracias por participar. El contenido ya no está disponible."></textarea>
                                </div>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="btn" style="background:#f3f4f6;color:#374151;" onclick="cerrarModal('modal-ev')">Cancelar</button>
                                <button type="submit" name="editar_evento" class="btn btn-primary">Guardar cambios</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ============ TAB CONFIGURACION ============ -->
            <?php elseif ($tab === 'configuracion'): ?>

                <div class="card">
                    <h3>🎨 Configuración del evento</h3>
                    <p style="font-size:13px;color:#6b7280;margin-bottom:18px;">
                        Editando: <strong><?= htmlspecialchars($admin_evento_nombre) ?></strong>
                    </p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="field">
                                <label>Título visible en login (titulo_login)</label>
                                <input type="text" name="titulo_evento"
                                    value="<?= htmlspecialchars($config['titulo_evento'] ?? '') ?>"
                                    placeholder="<?= htmlspecialchars($admin_evento_nombre) ?>">
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
                                <?php else: ?>
                                    <p style="font-size:12px;color:#aaa;margin-top:8px;">Sin banner configurado para este evento.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="submit" name="guardar_config" class="btn btn-primary">Guardar configuración</button>
                    </form>
                </div>

                <!-- ============ TAB PASSWORD ============ -->
            <!-- ============ TAB EVALUADORES ============ -->
            <?php elseif ($tab === 'evaluadores'): ?>

                <?php if (!$is_admin): ?>
                    <div class="alert-err">⚠️ Acceso denegado.</div>
                <?php else: ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;margin-bottom:24px">

                    <!-- Crear evaluador -->
                    <div class="card">
                        <h3>➕ Nuevo evaluador</h3>
                        <form method="POST" style="margin-top:14px">
                            <input type="hidden" name="crear_evaluador" value="1">
                            <label>Nombre completo</label>
                            <input type="text" name="ev_nombre" placeholder="Ej: María García" style="margin-bottom:12px">
                            <label>Usuario (para el login)</label>
                            <input type="text" name="ev_usuario" required placeholder="Ej: evaluador_maria" style="margin-bottom:12px">
                            <label>Contraseña <small style="color:#9ca3af">(mín. 6 caracteres)</small></label>
                            <input type="password" name="ev_password" required minlength="6" style="margin-bottom:16px">
                            <button type="submit" class="btn btn-primary">Crear evaluador</button>
                        </form>
                    </div>

                    <!-- Información contextual -->
                    <div class="card" style="background:#f0fdf4;border:1px solid #bbf7d0">
                        <h3 style="color:#166534">ℹ️ Sobre los evaluadores</h3>
                        <p style="font-size:14px;color:#374151;line-height:1.75;margin-top:10px">
                            Los evaluadores acceden con su usuario y contraseña al mismo panel de admin.<br><br>
                            Solo pueden ver y calificar entregas del <strong>Trabajo integrador final</strong>
                            en los eventos que tengan asignados.<br><br>
                            Asigna los eventos desde la tabla de abajo antes de compartirles el acceso.
                        </p>
                    </div>

                </div>

                <!-- Tabla de evaluadores -->
                <div class="card">
                    <h3>👤 Evaluadores registrados</h3>

                    <?php if (empty($evaluadores_lista)): ?>
                        <p style="color:#9ca3af;font-size:14px;margin-top:14px">No hay evaluadores creados aún.</p>
                    <?php else: ?>

                    <div style="overflow-x:auto;margin-top:14px">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Eventos asignados</th>
                                <th>Actualizar eventos</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($evaluadores_lista as $evl): ?>
                            <?php
                                $evl_ids = $evl['eventos_asignados_ids']
                                    ? array_map('intval', explode(',', $evl['eventos_asignados_ids']))
                                    : [];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($evl['nombre'] ?: $evl['usuario']) ?></strong></td>
                                <td style="color:#6b7280;font-size:13px">@<?= htmlspecialchars($evl['usuario']) ?></td>

                                <td>
                                    <?php if (empty($evl_ids)): ?>
                                        <em style="color:#9ca3af;font-size:13px">Sin eventos asignados</em>
                                    <?php else: ?>
                                        <?php foreach ($evl_ids as $asig_ev_id): ?>
                                            <span class="badge-ok" style="display:inline-block;margin:2px 3px 2px 0;font-size:12px">
                                                <?= htmlspecialchars($eventos_mapa[$asig_ev_id] ?? "Evento #{$asig_ev_id}") ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <form method="POST" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                                        <input type="hidden" name="actualizar_asignaciones" value="1">
                                        <input type="hidden" name="asig_admin_id" value="<?= (int) $evl['id'] ?>">
                                        <?php foreach ($todos_eventos as $tev): ?>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;white-space:nowrap">
                                                <input type="checkbox"
                                                       name="asig_eventos[]"
                                                       value="<?= (int) $tev['id'] ?>"
                                                       <?= in_array((int) $tev['id'], $evl_ids) ? 'checked' : '' ?>>
                                                <?= htmlspecialchars($tev['nombre']) ?>
                                            </label>
                                        <?php endforeach; ?>
                                        <button type="submit" class="btn btn-primary"
                                                style="padding:5px 14px;font-size:13px">Guardar</button>
                                    </form>
                                </td>

                                <td>
                                    <form method="POST"
                                          onsubmit="return confirm('¿Eliminar al evaluador <?= htmlspecialchars(addslashes($evl['usuario'])) ?>?')">
                                        <input type="hidden" name="eliminar_evaluador" value="1">
                                        <input type="hidden" name="evaluador_id" value="<?= (int) $evl['id'] ?>">
                                        <button type="submit" class="btn"
                                                style="background:#fef2f2;color:#b91c1c;border:1px solid #fca5a5;padding:5px 14px;font-size:13px">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>

                <?php endif; // $is_admin ?>

            <!-- ============ TAB TRABAJO INTEGRADOR ============ -->
            <?php elseif ($tab === 'trabajo_integrador'): ?>

                <?php if (!$is_admin && !$is_evaluador): ?>
                    <div class="alert-err">⚠️ Acceso denegado.</div>
                <?php else: ?>

                <!-- Configuración del trabajo integrador -->
                <div class="card" style="margin-bottom:24px">
                    <h3>📋 Configuración del trabajo integrador</h3>
                    <p style="font-size:13px;color:#6b7280;margin:8px 0 20px">Evento: <strong><?= htmlspecialchars($admin_evento_nombre) ?></strong></p>

                    <?php if ($is_admin): ?>
                    <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:16px 24px;align-items:start">
                        <div style="grid-column:1/-1">
                            <label>Título <span style="color:#dc2626">*</span></label>
                            <input type="text" name="tc_titulo" required
                                value="<?= htmlspecialchars($trabajo_config['titulo'] ?? 'Trabajo integrador final') ?>"
                                style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px">
                        </div>
                        <div style="grid-column:1/-1">
                            <label>Descripción / consigna</label>
                            <textarea name="tc_descripcion" rows="4"
                                style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px;resize:vertical"><?= htmlspecialchars($trabajo_config['descripcion'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label>Fecha límite de entrega</label>
                            <input type="datetime-local" name="tc_fecha_limite"
                                value="<?= $trabajo_config && $trabajo_config['fecha_limite'] ? date('Y-m-d\TH:i', strtotime($trabajo_config['fecha_limite'])) : '' ?>"
                                style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px">
                            <small style="color:#6b7280;font-size:12px">Entregas posteriores se marcan como tardías (no se bloquean)</small>
                        </div>
                        <div>
                            <label>Calificación máxima (0–100)</label>
                            <input type="number" name="tc_calificacion_maxima" min="0" max="100" step="0.5"
                                value="<?= htmlspecialchars($trabajo_config['calificacion_maxima'] ?? '100') ?>"
                                style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px">
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;padding-top:6px">
                            <input type="checkbox" name="tc_activo" id="tc_activo" value="1"
                                <?= (!empty($trabajo_config['activo'])) ? 'checked' : '' ?>
                                style="width:16px;height:16px;accent-color:#7b1a2e">
                            <label for="tc_activo" style="margin:0;font-weight:400;cursor:pointer">Módulo activo (visible para participantes)</label>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;padding-top:6px">
                            <input type="checkbox" name="tc_permite_reentrega" id="tc_permite_reentrega" value="1"
                                <?= (!empty($trabajo_config['permite_reentrega'])) ? 'checked' : '' ?>
                                style="width:16px;height:16px;accent-color:#7b1a2e">
                            <label for="tc_permite_reentrega" style="margin:0;font-weight:400;cursor:pointer">Permitir reentregas (hasta que sea aprobado)</label>
                        </div>
                        <div style="grid-column:1/-1;padding-top:8px">
                            <button type="submit" name="guardar_trabajo_config"
                                style="background:#7b1a2e;color:#fff;border:none;border-radius:7px;padding:10px 24px;font-size:14px;font-weight:600;cursor:pointer">
                                💾 Guardar configuración
                            </button>
                        </div>
                    </form>
                    <?php else: /* evaluador — solo lectura */ ?>
                    <?php if ($trabajo_config): ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 32px;font-size:14px">
                        <div><span style="color:#6b7280">Título:</span> <strong><?= htmlspecialchars($trabajo_config['titulo']) ?></strong></div>
                        <div><span style="color:#6b7280">Estado:</span>
                            <?php if ($trabajo_config['activo']): ?>
                                <span style="background:#dcfce7;color:#166534;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600">Activo</span>
                            <?php else: ?>
                                <span style="background:#f3f4f6;color:#6b7280;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600">Inactivo</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($trabajo_config['fecha_limite']): ?>
                        <div><span style="color:#6b7280">Fecha límite:</span> <?= date('d/m/Y H:i', strtotime($trabajo_config['fecha_limite'])) ?></div>
                        <?php endif; ?>
                        <div><span style="color:#6b7280">Cal. máxima:</span> <?= htmlspecialchars($trabajo_config['calificacion_maxima']) ?></div>
                        <div><span style="color:#6b7280">Reentregas:</span> <?= $trabajo_config['permite_reentrega'] ? 'Permitidas' : 'No permitidas' ?></div>
                        <?php if ($trabajo_config['descripcion']): ?>
                        <div style="grid-column:1/-1"><span style="color:#6b7280">Consigna:</span>
                            <p style="margin-top:6px;white-space:pre-wrap;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:10px 14px"><?= htmlspecialchars($trabajo_config['descripcion']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <p style="color:#9ca3af;font-size:14px;margin-top:12px">El administrador aún no configuró el trabajo integrador para este evento.</p>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Recursos base -->
                <div class="card" style="margin-bottom:24px">
                    <h3>📎 Recursos base del trabajo</h3>
                    <p style="font-size:13px;color:#6b7280;margin:8px 0 20px">Archivos de referencia disponibles para los participantes (PDF, Word, Excel, PPT, ZIP — máx. 25 MB)</p>

                    <?php if ($is_admin): ?>
                    <?php if (!$trabajo_config): ?>
                        <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:7px;padding:12px 16px;font-size:13px;color:#92400e;margin-bottom:20px">
                            ⚠️ Guarda primero la configuración del trabajo para poder subir recursos.
                        </div>
                    <?php else: ?>
                    <form method="POST" enctype="multipart/form-data"
                          style="display:grid;grid-template-columns:1fr 1fr;gap:14px 24px;align-items:end;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #e5e7eb">
                        <div>
                            <label>Nombre del recurso <span style="color:#dc2626">*</span></label>
                            <input type="text" name="tr_nombre" required placeholder="Ej: Plantilla informe final"
                                style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px">
                        </div>
                        <div>
                            <label>Archivo <span style="color:#dc2626">*</span></label>
                            <input type="file" name="tr_archivo" required accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.ppt,.pptx"
                                style="width:100%;padding:8px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px;background:#fff">
                        </div>
                        <div style="grid-column:1/-1">
                            <label>Descripción (opcional)</label>
                            <input type="text" name="tr_descripcion" placeholder="Breve descripción del archivo"
                                style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px">
                        </div>
                        <div style="grid-column:1/-1">
                            <button type="submit" name="subir_trabajo_recurso"
                                style="background:#7b1a2e;color:#fff;border:none;border-radius:7px;padding:10px 22px;font-size:14px;font-weight:600;cursor:pointer">
                                ⬆️ Subir recurso
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (empty($trabajo_recursos)): ?>
                        <p style="color:#9ca3af;font-size:14px;margin-top:<?= $is_admin ? '0' : '8' ?>px">No hay recursos cargados para este evento.</p>
                    <?php else: ?>
                    <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:#f3f4f6;color:#374151;text-align:left">
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Nombre</th>
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Tipo</th>
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Tamaño</th>
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Descripción</th>
                                <?php if ($is_admin): ?><th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Acción</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($trabajo_recursos as $tr_rec): ?>
                            <tr style="border-bottom:1px solid #f3f4f6">
                                <td style="padding:10px 14px;font-weight:500"><?= htmlspecialchars($tr_rec['nombre']) ?></td>
                                <td style="padding:10px 14px;text-transform:uppercase;color:#6b7280;font-size:12px"><?= htmlspecialchars($tr_rec['tipo']) ?></td>
                                <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">
                                    <?php
                                    $sz = (int) $tr_rec['tamanio'];
                                    echo $sz > 1048576
                                        ? round($sz / 1048576, 1) . ' MB'
                                        : round($sz / 1024, 0) . ' KB';
                                    ?>
                                </td>
                                <td style="padding:10px 14px;color:#6b7280"><?= htmlspecialchars($tr_rec['descripcion'] ?? '—') ?></td>
                                <?php if ($is_admin): ?>
                                <td style="padding:10px 14px">
                                    <form method="POST" onsubmit="return confirm('¿Eliminar este recurso?')">
                                        <input type="hidden" name="tr_recurso_id" value="<?= (int) $tr_rec['id'] ?>">
                                        <button type="submit" name="eliminar_trabajo_recurso"
                                            style="background:#fee2e2;color:#dc2626;border:none;border-radius:5px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer">
                                            🗑 Eliminar
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Entregas de participantes -->
                <?php if (($is_admin || $is_evaluador) && isset($puede_ver_entregas) && $puede_ver_entregas): ?>
                <div class="card">
                    <h3>📬 Entregas de participantes</h3>
                    <p style="font-size:13px;color:#6b7280;margin:8px 0 20px">Evento: <strong><?= htmlspecialchars($admin_evento_nombre) ?></strong> — <?= count($trabajo_entregas) ?> entrega(s)</p>

                    <?php if (empty($trabajo_entregas)): ?>
                        <p style="color:#9ca3af;font-size:14px">No hay entregas registradas para este evento aún.</p>
                    <?php else: ?>
                    <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:#f3f4f6;color:#374151;text-align:left">
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Participante</th>
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Documento</th>
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Archivo</th>
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Estado</th>
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Calificación</th>
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb;white-space:nowrap">Fecha entrega</th>
                                <th style="padding:10px 14px;border-bottom:1px solid #e5e7eb">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($trabajo_entregas as $tent): ?>
                            <?php
                            $eid = (int) $tent['id'];
                            switch ($tent['estado']) {
                                case 'revisado':
                                    $est_bg = '#dcfce7'; $est_color = '#166534'; $est_label = 'Revisado'; break;
                                case 'requiere_ajustes':
                                    $est_bg = '#ffedd5'; $est_color = '#9a3412'; $est_label = 'Requiere ajustes'; break;
                                default:
                                    $est_bg = '#f3f4f6'; $est_color = '#374151'; $est_label = 'Pendiente';
                            }
                            $nombre_desc = !empty($tent['nombre_original']) ? $tent['nombre_original'] : $tent['archivo'];
                            ?>
                            <tr style="border-bottom:1px solid #f3f4f6" id="fila-entrega-<?= $eid ?>">
                                <td style="padding:10px 14px;font-weight:500"><?= htmlspecialchars($tent['participante_nombre']) ?></td>
                                <td style="padding:10px 14px;color:#6b7280"><?= htmlspecialchars($tent['participante_documento']) ?></td>
                                <td style="padding:10px 14px">
                                    <?php if ($tent['archivo']): ?>
                                    <a href="descargar_entrega.php?id=<?= $eid ?>" target="_blank"
                                       style="color:#7b1a2e;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px">
                                        ⬇ <span style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($nombre_desc) ?></span>
                                    </a>
                                    <?php else: ?>
                                        <span style="color:#9ca3af">Sin archivo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 14px">
                                    <span style="background:<?= $est_bg ?>;color:<?= $est_color ?>;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;white-space:nowrap"><?= $est_label ?></span>
                                </td>
                                <td style="padding:10px 14px;text-align:center">
                                    <?= $tent['calificacion'] !== null ? htmlspecialchars($tent['calificacion']) : '<span style="color:#9ca3af">—</span>' ?>
                                </td>
                                <td style="padding:10px 14px;color:#6b7280;white-space:nowrap">
                                    <?= $tent['fecha_entrega'] ? date('d/m/Y H:i', strtotime($tent['fecha_entrega'])) : '—' ?>
                                </td>
                                <td style="padding:10px 14px">
                                    <button type="button"
                                        onclick="abrirEvalModal(<?= $eid ?>,<?= htmlspecialchars(json_encode($tent['estado'])) ?>,<?= htmlspecialchars(json_encode($tent['calificacion'] ?? '')) ?>,<?= htmlspecialchars(json_encode($tent['comentarios_evaluador'] ?? '')) ?>)"
                                        style="background:#7b1a2e;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap">
                                        ✏️ Evaluar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif ($is_evaluador && isset($puede_ver_entregas) && !$puede_ver_entregas): ?>
                <div class="card">
                    <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:7px;padding:14px 18px;color:#92400e;font-size:14px">
                        ⚠️ No estás asignado al evento <strong><?= htmlspecialchars($admin_evento_nombre) ?></strong>. Contactá al administrador para que te asigne.
                    </div>
                </div>
                <?php endif; ?>

                <!-- Modal de evaluación -->
                <div id="modal-eval" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;align-items:center;justify-content:center">
                    <div style="background:#fff;border-radius:12px;padding:32px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.3);position:relative">
                        <button type="button" onclick="cerrarEvalModal()"
                            style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:#6b7280">✕</button>
                        <h3 style="margin-bottom:20px;color:#1a1433">✏️ Evaluar entrega</h3>
                        <form method="POST" id="form-eval">
                            <input type="hidden" name="entrega_id" id="eval-entrega-id">
                            <div style="margin-bottom:16px">
                                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Estado <span style="color:#dc2626">*</span></label>
                                <select name="ev_estado" id="eval-estado"
                                    style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px;background:#fff">
                                    <option value="revisado">✅ Revisado</option>
                                    <option value="requiere_ajustes">🔶 Requiere ajustes</option>
                                </select>
                            </div>
                            <div style="margin-bottom:16px">
                                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Calificación (opcional)</label>
                                <input type="number" name="ev_calificacion" id="eval-calificacion"
                                    min="0" max="100" step="0.5" placeholder="Ej: 85"
                                    style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px">
                            </div>
                            <div style="margin-bottom:22px">
                                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Comentarios (opcional)</label>
                                <textarea name="ev_comentarios" id="eval-comentarios" rows="4"
                                    placeholder="Observaciones para el participante…"
                                    style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px;resize:vertical"></textarea>
                            </div>
                            <div style="display:flex;gap:12px">
                                <button type="submit" name="evaluar_entrega"
                                    style="flex:1;background:#7b1a2e;color:#fff;border:none;border-radius:7px;padding:11px;font-size:14px;font-weight:700;cursor:pointer">
                                    💾 Guardar evaluación
                                </button>
                                <button type="button" onclick="cerrarEvalModal()"
                                    style="background:#f3f4f6;color:#374151;border:none;border-radius:7px;padding:11px 20px;font-size:14px;font-weight:600;cursor:pointer">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                function abrirEvalModal(id, estado, calificacion, comentarios) {
                    document.getElementById('eval-entrega-id').value   = id;
                    document.getElementById('eval-estado').value       = estado || 'revisado';
                    document.getElementById('eval-calificacion').value = calificacion || '';
                    document.getElementById('eval-comentarios').value  = comentarios || '';
                    var m = document.getElementById('modal-eval');
                    m.style.display = 'flex';
                }
                function cerrarEvalModal() {
                    document.getElementById('modal-eval').style.display = 'none';
                }
                document.getElementById('modal-eval').addEventListener('click', function(e) {
                    if (e.target === this) cerrarEvalModal();
                });
                </script>

                <?php endif; // $is_admin || $is_evaluador ?>

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

            function abrirEditarEv(id, nombre, slug, activo, finalizado, mensaje) {
                document.getElementById('edit-ev-id').value              = id;
                document.getElementById('edit-ev-nombre').value          = nombre;
                document.getElementById('edit-ev-slug').value            = slug;
                document.getElementById('edit-ev-activo').checked        = activo == 1;
                document.getElementById('edit-ev-finalizado').checked    = finalizado == 1;
                document.getElementById('edit-ev-mensaje').value         = mensaje || '';
                toggleMensajeFinalizado(finalizado == 1);
                document.getElementById('modal-ev').classList.add('open');
            }

            function toggleMensajeFinalizado(show) {
                document.getElementById('finalizado-msg-row').style.display = show ? 'flex' : 'none';
            }

            function autoSlug(valor) {
                var slug = valor.toLowerCase()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                var el = document.getElementById('nuevo-ev-slug');
                if (el) el.value = slug;
            }

            function abrirModalVideos() {
                var lista = document.getElementById('modal-videos-lista');
                lista.innerHTML = '<p style="color:#6b7280;font-size:13px;text-align:center;padding:24px 0;">Cargando lista de videos…</p>';
                document.getElementById('modal-videos-servidor').classList.add('open');
                fetch('ajax_list_videos.php')
                    .then(function (r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function (data) {
                        if (!Array.isArray(data) || data.length === 0) {
                            lista.innerHTML = '<p style="color:#9ca3af;font-size:13px;text-align:center;padding:24px 0;">'
                                + 'No se encontraron archivos .mp4 en <code>private_videos/</code>.</p>';
                            return;
                        }
                        var esc = function (s) {
                            return String(s)
                                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        };
                        lista.innerHTML = data.map(function (v) {
                            return '<div style="display:flex;justify-content:space-between;align-items:center;'
                                 + 'padding:10px 0;border-bottom:1px solid #f0f0f0;">'
                                 + '<div style="min-width:0;flex:1;">'
                                 + '<div style="font-size:13px;font-weight:600;color:#111827;word-break:break-all;">'
                                 + esc(v.nombre) + '</div>'
                                 + '<div style="font-size:11px;color:#9ca3af;margin-top:2px;">' + esc(v.size_mb) + '</div>'
                                 + '</div>'
                                 + '<button type="button" class="btn-sel-video"'
                                 + ' data-nombre="' + esc(v.nombre) + '" data-mb="' + esc(v.size_mb) + '"'
                                 + ' style="flex-shrink:0;margin-left:14px;padding:6px 16px;background:#1d4ed8;'
                                 + 'color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">'
                                 + 'Seleccionar</button>'
                                 + '</div>';
                        }).join('');
                        lista.querySelectorAll('.btn-sel-video').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                seleccionarVideo(this.dataset.nombre, this.dataset.mb);
                            });
                        });
                    })
                    .catch(function () {
                        lista.innerHTML = '<p style="color:#dc2626;font-size:13px;text-align:center;padding:24px 0;">'
                            + 'Error al cargar la lista. Intenta de nuevo.</p>';
                    });
            }

            function seleccionarVideo(nombre, sizeMb) {
                document.getElementById('video_existente_input').value = nombre;
                var display = document.getElementById('video-seleccionado-texto');
                display.textContent = 'Seleccionado: ' + nombre + ' (' + sizeMb + ')';
                display.style.color       = '#059669';
                display.style.fontStyle   = 'normal';
                display.style.fontWeight  = '600';
                cerrarModal('modal-videos-servidor');
            }

            // Validación de tamaño al seleccionar archivo para subida directa
            (function () {
                var inp   = document.getElementById('video_archivo_input');
                var errEl = document.getElementById('video_archivo_error');
                if (!inp || !errEl) return;
                var form = inp.closest('form');
                inp.addEventListener('change', function () {
                    if (inp.files.length && inp.files[0].size > 16 * 1024 * 1024) {
                        errEl.textContent = 'Archivo demasiado grande ('
                            + (inp.files[0].size / 1048576).toFixed(1)
                            + ' MB). Súbelo por FTP y selecciónalo con la opción ③.';
                        errEl.style.display = 'block';
                    } else {
                        errEl.style.display = 'none';
                    }
                });
                if (form) {
                    form.addEventListener('submit', function (e) {
                        if (inp.files.length && inp.files[0].size > 16 * 1024 * 1024) {
                            e.preventDefault();
                            errEl.textContent = 'El archivo es demasiado grande para subir desde el navegador. Usa la opción ③.';
                            errEl.style.display = 'block';
                            inp.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                }
            }());

            // Cerrar modal al hacer clic fuera
            document.querySelectorAll('.modal-overlay').forEach(overlay => {
                overlay.addEventListener('click', function (e) {
                    if (e.target === this) this.classList.remove('open');
                });
            });
        </script>

</body>

</html>