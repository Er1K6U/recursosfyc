<?php
session_start();
require_once 'db.php';

date_default_timezone_set('America/Bogota');

define('VIDEO_EXPIRACION_HORAS', 4);

function generarUUID(): string
{
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function renderEventoFinalizado(array $evento): void
{
    $ev_nombre   = htmlspecialchars($evento['nombre'] ?? '');
    $fin_mensaje = !empty($evento['mensaje_finalizado'])
        ? htmlspecialchars($evento['mensaje_finalizado'])
        : 'Gracias por participar. El evento ha finalizado, pero puedes seguir consultando tus recursos, videos y certificados disponibles.';

    $fin_fecha = '';
    if (!empty($evento['finalizado_en'])) {
        $meses = ['enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $ts        = strtotime($evento['finalizado_en']);
        $fin_fecha = (int) date('j', $ts) . ' de '
                   . $meses[(int) date('n', $ts) - 1] . ' de '
                   . date('Y', $ts);
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evento finalizado · F&amp;C Consultores</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1433 0%, #3b1fa8 55%, #685f2f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .fin-wrap {
            animation: cardIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
            width: 100%;
            max-width: 600px;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(28px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1);    }
        }

        .fin-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 48px 40px 40px;
            text-align: center;
            box-shadow: 0 32px 80px rgba(0,0,0,0.30), 0 4px 20px rgba(148,41,52,0.20);
            position: relative;
            overflow: hidden;
        }

        .fin-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, #1a1433, #3b1fa8, #685f2f);
        }

        .fin-ovi {
            width: 140px;
            height: 140px;
            margin: 0 auto 24px;
            display: block;
            animation: oviFloat 3.2s ease-in-out infinite;
            filter: drop-shadow(0 8px 18px rgba(59,31,168,0.22));
        }

        @keyframes oviFloat {
            0%, 100% { transform: translateY(0);    }
            50%       { transform: translateY(-9px); }
        }

        .fin-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
            border-radius: 99px;
            padding: 5px 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 22px;
        }

        .fin-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1433;
            line-height: 1.25;
            margin-bottom: 10px;
        }

        .fin-evento {
            font-size: 17px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 22px;
        }

        .fin-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
            margin: 0 0 22px;
        }

        .fin-mensaje {
            font-size: 15px;
            color: #4b5563;
            line-height: 1.75;
            margin-bottom: 14px;
        }

        .fin-fecha {
            font-size: 13px;
            color: #9ca3af;
            margin-bottom: 0;
        }

        .fin-fecha strong { color: #6b7280; }

        .fin-actions {
            display: flex;
            gap: 12px;
            margin-top: 34px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .fin-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 26px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.15s;
        }

        .fin-btn:hover  { opacity: 0.88; transform: translateY(-2px); }
        .fin-btn:active { transform: translateY(0); }

        .fin-btn-entrar {
            background: #3b1fa8;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(59,31,168,0.35);
        }

        .fin-btn-cambiar {
            background: #f3f4f6;
            color: #374151;
        }

        .fin-link-salir {
            display: block;
            margin-top: 16px;
            font-size: 13px;
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.2s;
        }

        .fin-link-salir:hover { color: #6b7280; }

        @media (max-width: 480px) {
            .fin-card  { padding: 36px 20px 32px; }
            .fin-title { font-size: 23px; }
            .fin-ovi   { width: 115px; height: 115px; }
            .fin-btn   { padding: 12px 20px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <div class="fin-wrap">
        <div class="fin-card">

            <img src="assets/ovi/ovi-alerta.svg" class="fin-ovi" alt="Ovi">

            <div class="fin-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="3"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Evento finalizado
            </div>

            <h1 class="fin-title">¡Felicitaciones por completar este evento!</h1>
            <p class="fin-evento"><?= $ev_nombre ?></p>

            <div class="fin-divider"></div>

            <p class="fin-mensaje"><?= $fin_mensaje ?></p>

            <?php if ($fin_fecha): ?>
            <p class="fin-fecha">Finalizado el: <strong><?= $fin_fecha ?></strong></p>
            <?php endif; ?>

            <div class="fin-actions">
                <a href="portal.php?continuar_evento_finalizado=1" class="fin-btn fin-btn-entrar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                    Entrar al portal
                </a>
                <a href="portal.php?cambiar_evento=1" class="fin-btn fin-btn-cambiar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Cambiar evento
                </a>
            </div>
            <a href="logout.php" class="fin-link-salir">Cerrar sesión</a>

        </div>
    </div>
</body>
</html>
    <?php
}

if (!isset($_SESSION['participante_id']) || !isset($_SESSION['terminos_aceptados'])) {
    header('Location: index.php');
    exit;
}

// Cambiar de evento sin cerrar sesión
if (isset($_GET['cambiar_evento'])) {
    unset($_SESSION['evento_id']);
    unset($_SESSION['eventos_disponibles']);
    // Re-consultar eventos disponibles para repoblar el selector
    $stmt_cev = $pdo->prepare("
        SELECT e.id, e.nombre
        FROM evento_participantes ep
        JOIN eventos e ON e.id = ep.evento_id AND e.activo = 1
        WHERE ep.persona_id = ? AND ep.activo = 1
        ORDER BY e.id ASC
    ");
    $stmt_cev->execute([$_SESSION['participante_id']]);
    $ev_list = $stmt_cev->fetchAll();
    if (count($ev_list) > 1) {
        $_SESSION['eventos_disponibles'] = array_map(fn($ev) => [
            'id'     => (int) $ev['id'],
            'nombre' => $ev['nombre'],
        ], $ev_list);
        header('Location: evento_select.php');
    } else {
        // Solo un evento disponible: restaurar y quedarse en portal
        if (!empty($ev_list)) {
            $_SESSION['evento_id'] = (int) $ev_list[0]['id'];
        }
        header('Location: portal.php');
    }
    exit;
}

// Resolver evento activo; fallback al evento default para sesiones antiguas
$evento_id = (int) ($_SESSION['evento_id'] ?? 0);
if (!$evento_id) {
    $ev_def = $pdo->query("SELECT id FROM eventos WHERE es_default = 1 AND activo = 1 LIMIT 1")->fetch();
    $evento_id = $ev_def ? (int) $ev_def['id'] : 1;
    $_SESSION['evento_id'] = $evento_id;
}

$stmt = $pdo->prepare("SELECT * FROM recursos WHERE activo = 1 AND evento_id = ? ORDER BY created_at ASC");
$stmt->execute([$evento_id]);
$recursos = $stmt->fetchAll();

$stmt_perm = $pdo->prepare(
    "SELECT recurso_id FROM recurso_permisos_video
     WHERE participante_id = ? AND activo = 1
       AND (fecha_inicio IS NULL OR fecha_inicio <= NOW())
       AND (fecha_fin   IS NULL OR fecha_fin   >= NOW())"
);
$stmt_perm->execute([$_SESSION['participante_id']]);
$permisos_video = array_map('intval', array_column($stmt_perm->fetchAll(), 'recurso_id'));

$stmt_ev = $pdo->prepare("SELECT titulo_login, banner, nombre, finalizado, finalizado_en, mensaje_finalizado FROM eventos WHERE id = ? LIMIT 1");
$stmt_ev->execute([$evento_id]);
$ev_data = $stmt_ev->fetch();
$banner = $ev_data['banner'] ?? '';
$titulo = $ev_data['titulo_login'] ?: ($ev_data['nombre'] ?? 'Diplomado en Gestión Integral de Riesgos');

// Marcar evento finalizado como visto y continuar al portal
if (isset($_GET['continuar_evento_finalizado'])) {
    if (!isset($_SESSION['evento_finalizado_visto'])) {
        $_SESSION['evento_finalizado_visto'] = [];
    }
    $_SESSION['evento_finalizado_visto'][$evento_id] = true;
    header('Location: portal.php');
    exit;
}

// Guard: mostrar pantalla de felicitación una sola vez por sesión/evento
if (!empty($ev_data) && (int) $ev_data['finalizado'] === 1) {
    $ya_visto = isset($_SESSION['evento_finalizado_visto'][$evento_id]);
    if (!$ya_visto) {
        renderEventoFinalizado($ev_data);
        exit;
    }
}

// Obtener certificado del participante para este evento
$stmt_cert = $pdo->prepare("SELECT * FROM certificados WHERE participante_id = ? AND evento_id = ?");
$stmt_cert->execute([$_SESSION['participante_id'], $evento_id]);
$certificados_participante = $stmt_cert->fetchAll();

// Trabajo integrador: config, recursos base, entrega propia
$trabajo_config        = null;
$trabajo_recursos_base = [];
$mi_entrega            = null;

$stmt_tc = $pdo->prepare(
    "SELECT * FROM trabajo_integrador_config WHERE evento_id = ? AND activo = 1 LIMIT 1"
);
$stmt_tc->execute([$evento_id]);
$trabajo_config = $stmt_tc->fetch() ?: null;

if ($trabajo_config) {
    $stmt_trb = $pdo->prepare(
        "SELECT * FROM trabajo_integrador_recursos
         WHERE  config_id = ? AND activo = 1
         ORDER  BY orden ASC, creado_en ASC"
    );
    $stmt_trb->execute([$trabajo_config['id']]);
    $trabajo_recursos_base = $stmt_trb->fetchAll();

    $stmt_me = $pdo->prepare(
        "SELECT * FROM trabajo_integrador_entregas
         WHERE  config_id = ? AND participante_id = ? LIMIT 1"
    );
    $stmt_me->execute([$trabajo_config['id'], $_SESSION['participante_id']]);
    $mi_entrega = $stmt_me->fetch() ?: null;
}

// Flash messages para el portal (subida de entrega)
$flash_portal_ok  = $_SESSION['flash_portal']['ok']  ?? '';
$flash_portal_err = $_SESSION['flash_portal']['err'] ?? '';
unset($_SESSION['flash_portal']);

// Manejar descarga certificado
if (isset($_GET['descargar_cert'])) {
    $cert_id = (int) $_GET['descargar_cert'];
    $stmt_dc = $pdo->prepare("SELECT * FROM certificados WHERE id = ? AND participante_id = ? AND evento_id = ?");
    $stmt_dc->execute([$cert_id, $_SESSION['participante_id'], $evento_id]);
    $cert = $stmt_dc->fetch();

    if ($cert) {
        $archivo = 'uploads/' . basename($cert['archivo']);
        if (file_exists($archivo)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($cert['archivo']) . '"');
            header('Content-Length: ' . filesize($archivo));
            header('X-Content-Type-Options: nosniff');
            readfile($archivo);
            exit;
        }
    }
}

// Manejar descarga
if (isset($_GET['descargar'])) {
    $recurso_id = (int) $_GET['descargar'];
    $stmt3 = $pdo->prepare("SELECT * FROM recursos WHERE id = ? AND activo = 1 AND evento_id = ?");
    $stmt3->execute([$recurso_id, $evento_id]);
    $recurso = $stmt3->fetch();

    if ($recurso) {
        // Registrar descarga
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt4 = $pdo->prepare("INSERT INTO accesos (participante_id, accion, recurso_id, ip, evento_id) VALUES (?, 'descarga', ?, ?, ?)");
        $stmt4->execute([$_SESSION['participante_id'], $recurso_id, $ip, $evento_id]);

        // Recurso externo — redirigir a URL
        if (!empty($recurso['url_externa'])) {
            header('Location: ' . $recurso['url_externa']);
            exit;
        }

        // Recurso local — descargar archivo
        $archivo = 'uploads/' . basename($recurso['archivo']);
        if (file_exists($archivo)) {
            set_time_limit(0);
            while (ob_get_level())
                ob_end_clean();

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $archivo);
            finfo_close($finfo);
            $size = filesize($archivo);

            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . basename($recurso['archivo']) . '"');
            header('Content-Length: ' . $size);
            header('Content-Transfer-Encoding: binary');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-cache');
            header('Pragma: no-cache');

            readfile($archivo);
            exit;
        }
    }
}

// Generar token de acceso temporal para video restringido
if (isset($_GET['ver_video'])) {
    $recurso_id = (int) $_GET['ver_video'];
    $stmt_v = $pdo->prepare("SELECT * FROM recursos WHERE id = ? AND activo = 1 AND evento_id = ?");
    $stmt_v->execute([$recurso_id, $evento_id]);
    $recurso_v = $stmt_v->fetch();

    $tipos_video = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v'];
    if ($recurso_v && (!empty($recurso_v['es_video']) || in_array(strtolower($recurso_v['tipo']), $tipos_video))) {
        if (!in_array((int) $recurso_v['id'], $permisos_video)) {
            $pdo->prepare("INSERT INTO accesos (participante_id, accion, recurso_id, ip, evento_id) VALUES (?, 'video_no_autorizado', ?, ?, ?)")
                ->execute([$_SESSION['participante_id'], $recurso_id, $_SERVER['REMOTE_ADDR'] ?? '', $evento_id]);
            header('Location: portal.php');
            exit;
        }
        $stmt_visto = $pdo->prepare(
            "SELECT usado_en, expira_en, created_at FROM tokens_video
             WHERE participante_id = ? AND recurso_id = ?
               AND usado_en IS NOT NULL AND expira_en < NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt_visto->execute([$_SESSION['participante_id'], $recurso_id]);
        $token_visto = $stmt_visto->fetch();

        if ($token_visto) {
            $fecha_acceso = date('d/m/Y \a \l\a\s H:i', strtotime($token_visto['usado_en']));
            $fecha_expira = date('d/m/Y \a \l\a\s H:i', strtotime($token_visto['expira_en']));
            http_response_code(403);
            ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso no disponible — F&amp;C Consultores</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(145deg, #0f0a1e 0%, #1e0f4a 45%, #12082e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .box {
            background: white;
            border-radius: 20px;
            padding: 48px 40px;
            max-width: 460px;
            width: 100%;
            text-align: center;
            box-shadow: 0 24px 80px rgba(124, 58, 237, 0.25);
            position: relative;
            overflow: hidden;
        }
        .box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6d28d9, #a855f7, #6d28d9);
        }
        .icon { font-size: 52px; margin-bottom: 16px; }
        h1 { font-size: 21px; font-weight: 700; color: #1a1433; margin-bottom: 12px; }
        .desc { font-size: 14px; color: #6b7280; line-height: 1.7; margin-bottom: 28px; }
        .detalle {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 28px;
            text-align: left;
        }
        .detalle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            padding: 7px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .detalle-row:last-child { border-bottom: none; }
        .detalle-label { color: #6b7280; font-weight: 500; }
        .detalle-val   { color: #111827; font-weight: 600; }
        .badge-expirado {
            display: inline-block;
            background: #fef2f2;
            color: #dc2626;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 99px;
            border: 1px solid #fecaca;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .btn-volver {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #1a1433, #7c3aed);
            color: white;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(124, 58, 237, 0.35);
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">🔒</div>
        <h1>Acceso no disponible</h1>
        <p class="desc">Este contenido ya fue visualizado. Tu acceso ha expirado y no está disponible nuevamente.</p>
        <div class="detalle">
            <div class="detalle-row">
                <span class="detalle-label">Fecha de acceso</span>
                <span class="detalle-val"><?= htmlspecialchars($fecha_acceso) ?></span>
            </div>
            <div class="detalle-row">
                <span class="detalle-label">Fecha de expiración</span>
                <span class="detalle-val"><?= htmlspecialchars($fecha_expira) ?></span>
            </div>
            <div class="detalle-row">
                <span class="detalle-label">Estado</span>
                <span class="detalle-val"><span class="badge-expirado">Expirado</span></span>
            </div>
        </div>
        <a href="portal.php" class="btn-volver">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Volver al portal
        </a>
    </div>
</body>
</html>
<?php
            exit;
        }

        $token        = generarUUID();
        $horas_expira = max(1/60, (float) ($recurso_v['video_expira_horas'] ?? VIDEO_EXPIRACION_HORAS));
        $segundos     = (int) round($horas_expira * 3600);
        $expira       = (new DateTime('now'))->add(new DateInterval("PT{$segundos}S"))->format('Y-m-d H:i:s');
        $ip     = $_SERVER['REMOTE_ADDR'];
        $pdo->prepare("INSERT INTO tokens_video (token, participante_id, recurso_id, expira_en, ip_generado) VALUES (?, ?, ?, ?, ?)")
            ->execute([$token, $_SESSION['participante_id'], $recurso_id, $expira, $ip]);
        header('Location: video.php?token=' . urlencode($token));
        exit;
    }
}

// ── Descarga segura de recurso base del trabajo integrador ──────────────────
if (isset($_GET['descargar_trabajo_recurso'])) {
    $tr_id = (int) ($_GET['descargar_trabajo_recurso']);

    if ($tr_id < 1 || !$trabajo_config) {
        http_response_code(403); exit('Acceso denegado');
    }

    $stmt_dtr = $pdo->prepare(
        "SELECT archivo, nombre FROM trabajo_integrador_recursos
         WHERE  id = ? AND config_id = ? AND activo = 1 LIMIT 1"
    );
    $stmt_dtr->execute([$tr_id, $trabajo_config['id']]);
    $rec_dl = $stmt_dtr->fetch();

    if (!$rec_dl) { http_response_code(404); exit('Recurso no encontrado'); }

    $nombre_archivo  = basename($rec_dl['archivo']);
    $directorio_base = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'trabajo');

    if ($directorio_base === false) { http_response_code(500); exit('Error de configuración'); }

    $directorio_base = rtrim($directorio_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $filepath        = $directorio_base . $nombre_archivo;

    if (!file_exists($filepath)) { http_response_code(404); exit('Archivo no encontrado'); }

    $filepath_real = realpath($filepath);
    if ($filepath_real === false || strpos($filepath_real . DIRECTORY_SEPARATOR, $directorio_base) !== 0) {
        http_response_code(403); exit('Ruta inválida');
    }

    $pdo->prepare(
        "INSERT INTO accesos (participante_id, evento_id, accion, ip) VALUES (?, ?, 'descarga_trabajo_recurso', ?)"
    )->execute([$_SESSION['participante_id'], $evento_id, $_SERVER['REMOTE_ADDR'] ?? '']);

    $ext_dl   = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
    $nom_dl   = $rec_dl['nombre'] . '.' . $ext_dl;
    $ascii_dl = preg_replace('/[^\x20-\x7E]/', '_', str_replace(['"', '\\', '/'], '_', $nom_dl));
    $utf8_dl  = rawurlencode($nom_dl);

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"{$ascii_dl}\"; filename*=UTF-8''{$utf8_dl}");
    header('Content-Length: ' . filesize($filepath_real));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    readfile($filepath_real);
    exit;
}

// ── Subida de entrega del participante ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_entrega'])) {

    if (!$trabajo_config) {
        $_SESSION['flash_portal']['err'] = 'El trabajo integrador no está disponible para este evento.';
        header('Location: portal.php'); exit;
    }

    if ($mi_entrega) {
        if ($mi_entrega['estado'] === 'aprobado') {
            $_SESSION['flash_portal']['err'] = 'Tu entrega ya fue aprobada y no puede ser reemplazada.';
            header('Location: portal.php'); exit;
        }
        if (!(int) $trabajo_config['permite_reentrega']) {
            $_SESSION['flash_portal']['err'] = 'Ya realizaste tu entrega. Las reentregas no están habilitadas.';
            header('Location: portal.php'); exit;
        }
    }

    $allowed_ext  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'ppt', 'pptx'];
    $allowed_mime = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-zip-compressed',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    $file = $_FILES['entrega_archivo'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_portal']['err'] = 'Error al recibir el archivo (código: ' . ($file['error'] ?? '?') . ').';
        header('Location: portal.php'); exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        $_SESSION['flash_portal']['err'] = 'Extensión no permitida. Formatos aceptados: ' . implode(', ', $allowed_ext) . '.';
        header('Location: portal.php'); exit;
    }
    if ($file['size'] > 25 * 1024 * 1024) {
        $_SESSION['flash_portal']['err'] = 'El archivo supera el límite de 25 MB.';
        header('Location: portal.php'); exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mime)) {
        $_SESSION['flash_portal']['err'] = 'El tipo de archivo no es válido.';
        header('Location: portal.php'); exit;
    }

    $doc      = preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['participante_documento'] ?? 'doc');
    $filename = 'entrega_' . $evento_id . '_' . (int) $_SESSION['participante_id'] . '_' . $doc . '_' . time() . '.' . $ext;

    if (!is_dir('private_entregas')) { mkdir('private_entregas', 0755, true); }

    if (!move_uploaded_file($file['tmp_name'], 'private_entregas/' . $filename)) {
        $_SESSION['flash_portal']['err'] = 'No se pudo guardar el archivo en el servidor.';
        header('Location: portal.php'); exit;
    }

    // Eliminar archivo anterior al reemplazar
    if ($mi_entrega && !empty($mi_entrega['archivo'])) {
        $old = 'private_entregas/' . basename($mi_entrega['archivo']);
        if (file_exists($old)) { @unlink($old); }
    }

    $es_tardia       = ($trabajo_config['fecha_limite'] && strtotime($trabajo_config['fecha_limite']) < time()) ? 1 : 0;
    $nombre_original = basename($file['name']);
    $pid             = (int) $_SESSION['participante_id'];

    if ($mi_entrega) {
        $pdo->prepare(
            "UPDATE trabajo_integrador_entregas
             SET    archivo               = ?,
                    nombre_original       = ?,
                    estado                = 'entregado',
                    entrega_tardia        = ?,
                    fecha_entrega         = NOW(),
                    calificacion          = NULL,
                    comentarios_evaluador = NULL,
                    evaluador_id          = NULL,
                    fecha_evaluacion      = NULL
             WHERE  id = ?"
        )->execute([$filename, $nombre_original, $es_tardia, $mi_entrega['id']]);
    } else {
        $pdo->prepare(
            "INSERT INTO trabajo_integrador_entregas
                 (config_id, evento_id, participante_id, persona_id,
                  archivo, nombre_original, estado, entrega_tardia, fecha_entrega)
             VALUES (?, ?, ?, ?, ?, ?, 'entregado', ?, NOW())"
        )->execute([
            $trabajo_config['id'], $evento_id, $pid, $pid,
            $filename, $nombre_original, $es_tardia,
        ]);
    }

    $_SESSION['flash_portal']['ok'] = $es_tardia
        ? 'Entrega subida correctamente (fuera de plazo).'
        : '¡Entrega subida correctamente!';
    header('Location: portal.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recursos - F&C Consultores</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f4f2fb;
            min-height: 100vh;
            color: #111827;
        }

        .topbar {
            background: linear-gradient(135deg, #0f0a1e, #2d1b69);
            color: white;
            padding: 0 32px;
            height: 64px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 24px rgba(15, 10, 30, 0.5);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar .brand {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .topbar .brand svg {
            opacity: 0.9;
        }

        .topbar .brand span {
            color: #a78bfa;
        }

        .topbar .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(167, 139, 250, 0.25);
            border-radius: 99px;
            padding: 6px 14px 6px 8px;
        }

        .topbar .user-avatar {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #5b21b6, #a855f7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .topbar .user-name {
            font-size: 13px;
            font-weight: 500;
            color: #e9d5ff;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .topbar .btn-salir {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 7px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
            white-space: nowrap;
        }

        .topbar .btn-salir:hover {
            background: rgba(255, 255, 255, 0.16);
            color: white;
        }

        .topbar .btn-cambiar-evento {
            display: flex;
            align-items: center;
            gap: 6px;
            background: transparent;
            color: rgba(255, 255, 255, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 7px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            white-space: nowrap;
        }

        .topbar .btn-cambiar-evento:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-color: rgba(255, 255, 255, 0.35);
        }

        .banner-wrap {
            width: 100%;
            max-width: 960px;
            margin: 28px auto 0 auto;
            padding: 0 24px;
        }

        .banner-wrap img {
            width: 100%;
            display: block;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(124, 58, 237, 0.15);
        }

        .main {
            max-width: 960px;
            margin: 32px auto;
            padding: 0 24px;
        }

        .welcome-box {
            background: white;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 32px;
            border-left: 4px solid #7c3aed;
            box-shadow: 0 1px 12px rgba(124, 58, 237, 0.08), 0 0 0 1px rgba(124, 58, 237, 0.06);
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .welcome-avatar {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #1a1433, #7c3aed);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .welcome-box h2 {
            font-size: 17px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }

        .welcome-box p {
            font-size: 13px;
            color: #6d28d9;
            font-weight: 500;
        }

        .section-header {
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-header svg {
            color: #7c3aed;
        }

        .section-header::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, #ddd6fe, transparent);
        }

        .recursos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(272px, 1fr));
            gap: 18px;
        }

        .recurso-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.06), 0 0 0 1px rgba(124, 58, 237, 0.06);
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            cursor: default;
        }

        .recurso-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 32px rgba(124, 58, 237, 0.14), 0 0 0 1px rgba(124, 58, 237, 0.1);
        }

        .recurso-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #f5f3ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7c3aed;
        }

        .recurso-nombre {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            line-height: 1.4;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .recurso-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .recurso-tipo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f5f3ff;
            color: #5b21b6;
            font-size: 10.5px;
            padding: 4px 10px;
            border-radius: 99px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            border: 1px solid #ede9fe;
            width: fit-content;
        }

        .btn-descargar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
            background: linear-gradient(135deg, #1a1433 0%, #6d28d9 100%);
            color: white;
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 3px 12px rgba(109, 40, 217, 0.35);
            cursor: pointer;
        }

        .btn-descargar:hover {
            opacity: 0.93;
            transform: translateY(-1px);
            box-shadow: 0 5px 18px rgba(109, 40, 217, 0.45);
        }

        .btn-descargar:active {
            transform: translateY(0);
        }

        .empty-state {
            text-align: center;
            padding: 64px 24px;
            color: #9ca3af;
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.05);
        }

        .empty-state svg {
            color: #d1d5db;
            margin-bottom: 16px;
        }

        .empty-state p {
            font-size: 14px;
        }

        .cert-card {
            border-left: 3px solid #a855f7;
            background: linear-gradient(150deg, #fdf4ff 0%, #ffffff 55%);
        }

        #overlay-descarga {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(26, 20, 51, 0.88);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }

        #overlay-descarga.visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .overlay-box {
            background: white;
            border-radius: 18px;
            padding: 36px 40px;
            text-align: center;
            max-width: 380px;
            width: 92%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }

        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            padding: 28px 20px;
            border-top: 1px solid #ede9fe;
            margin-top: 16px;
        }

        @media (max-width: 768px) {
            .main {
                margin: 22px auto;
                padding: 0 18px;
            }
            .banner-wrap {
                margin-top: 18px;
                padding: 0 18px;
            }
            .welcome-box {
                padding: 18px 22px;
            }
            .recursos-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 14px;
            }
            .recurso-card {
                padding: 18px;
            }
        }

        @media (max-width: 600px) {
            .topbar {
                padding: 0 14px;
                height: 56px;
                gap: 8px;
            }

            .topbar .brand {
                font-size: 14px;
            }

            .topbar .user-chip {
                padding: 5px 10px 5px 6px;
                gap: 7px;
            }

            .topbar .user-name {
                max-width: 100px;
            }

            .topbar .btn-salir {
                padding: 6px 12px;
                font-size: 12px;
            }

            .main {
                margin: 16px auto;
                padding: 0 14px;
            }

            .banner-wrap {
                padding: 0 14px;
                margin-top: 12px;
            }

            .welcome-box {
                padding: 16px;
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .recursos-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .recurso-card {
                padding: 16px;
                gap: 10px;
            }

            .section-header::after {
                display: none;
            }

            .overlay-box {
                padding: 24px 20px;
            }
        }
    </style>
</head>

<body>

    <div class="topbar">
        <div class="brand">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                <path d="M6 12v5c3 3 9 3 12 0v-5" />
            </svg>
            F&amp;C <span>Consultores</span>
        </div>
        <div class="user-chip">
            <div class="user-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                    stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
            </div>
            <span class="user-name"><?= htmlspecialchars($_SESSION['participante_nombre']) ?></span>
        </div>
        <a href="portal.php?cambiar_evento=1" class="btn-cambiar-evento">
            🔄 Cambiar evento
        </a>
        <a href="logout.php" class="btn-salir">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            Salir
        </a>
    </div>

    <?php if (!empty($banner)): ?>
        <div class="banner-wrap">
            <img src="uploads/<?= htmlspecialchars($banner) ?>" alt="Banner del evento">
        </div>
    <?php endif; ?>

    <div class="main">

        <div class="welcome-box">
            <div class="welcome-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
            </div>
            <div>
                <h2>Bienvenido, <?= htmlspecialchars($_SESSION['participante_nombre']) ?></h2>
                <p><?= htmlspecialchars($titulo) ?></p>
            </div>
        </div>

        <?php if (!empty($certificados_participante)): ?>
            <div class="section-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="6" />
                    <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" />
                </svg>
                Mi certificado
            </div>
            <div class="recursos-grid" style="margin-bottom: 32px;">
                <?php foreach ($certificados_participante as $cert): ?>
                    <div class="recurso-card cert-card">
                        <div class="recurso-icon-wrap" style="background:#fdf4ff; color:#a855f7;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="6" />
                                <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" />
                            </svg>
                        </div>
                        <div class="recurso-nombre"><?= htmlspecialchars($cert['nombre']) ?></div>
                        <div class="recurso-desc">Tu certificado personal del Diplomado en Gestión Integral de Riesgos.</div>
                        <span class="recurso-tipo">PDF</span>
                        <a href="portal.php?descargar_cert=<?= $cert['id'] ?>" class="btn-descargar"
                            style="background: linear-gradient(135deg, #4c1d95, #a855f7); box-shadow: 0 3px 12px rgba(168,85,247,0.4);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="7 10 12 15 17 10" />
                                <line x1="12" y1="15" x2="12" y2="3" />
                            </svg>
                            Descargar certificado
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="section-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
            </svg>
            Recursos disponibles
        </div>

        <?php if (empty($recursos)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                </svg>
                <p>Aún no hay recursos disponibles. Pronto estarán aquí.</p>
            </div>
        <?php else: ?>
            <div class="recursos-grid">
                <?php foreach ($recursos as $r):
                    $ext      = strtolower(pathinfo($r['archivo'], PATHINFO_EXTENSION));
                    $es_video = !empty($r['es_video']) || in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v']);
                    if ($es_video && !in_array((int) $r['id'], $permisos_video)) { continue; }
                    [$icono_svg, $icon_bg] = match ($ext) {
                        'pdf' => ['<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="10" y1="13" x2="14" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><line x1="8" y1="9" x2="10" y2="9"/>', '#fff1f0|#ef4444'],
                        'xlsx', 'xls' => ['<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h2l2 4 2-4h2"/>', '#f0fdf4|#16a34a'],
                        'docx', 'doc' => ['<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><line x1="8" y1="9" x2="12" y2="9"/>', '#eff6ff|#2563eb'],
                        'pptx', 'ppt' => ['<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><rect x="8" y="12" width="4" height="5"/><path d="M8 12a2 2 0 0 1 4 0"/>', '#fff7ed|#ea580c'],
                        'zip', 'rar' => ['<path d="M21 10H3"/><path d="M21 6H3"/><path d="M21 14H3"/><path d="M21 18H3"/><rect x="9" y="2" width="6" height="20" rx="1"/>', '#faf5ff|#7c3aed'],
                        'mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'm4v' => ['<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>', '#f0f9ff|#0284c7'],
                        default => ['<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>', '#f9fafb|#6b7280'],
                    };
                    [$icon_bg_color, $icon_color] = explode('|', $icon_bg);
                    ?>
                    <div class="recurso-card">
                        <div class="recurso-icon-wrap" style="background:<?= $icon_bg_color ?>; color:<?= $icon_color ?>;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                                stroke-linejoin="round"><?= $icono_svg ?></svg>
                        </div>
                        <div class="recurso-nombre"><?= htmlspecialchars($r['nombre']) ?></div>
                        <?php if (!empty($r['descripcion'])): ?>
                            <div class="recurso-desc"><?= htmlspecialchars($r['descripcion']) ?></div>
                        <?php endif; ?>
                        <span class="recurso-tipo"><?= htmlspecialchars(strtoupper($ext)) ?></span>
                        <?php if ($es_video): ?>
                        <a href="portal.php?ver_video=<?= $r['id'] ?>" class="btn-descargar"
                            style="background:linear-gradient(135deg,#0f172a,#0284c7);box-shadow:0 3px 12px rgba(2,132,199,0.35);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                            Ver video
                        </a>
                        <?php else: ?>
                        <a href="portal.php?descargar=<?= $r['id'] ?>" class="btn-descargar" <?php if (empty($r['url_externa'])): ?>
                                onclick="iniciarDescarga(event, this, '<?= htmlspecialchars(strtoupper($ext)) ?>', '<?= htmlspecialchars(addslashes(basename($r['archivo']))) ?>')"
                            <?php else: ?> target="_blank" <?php endif; ?> <svg xmlns="http://www.w3.org/2000/svg" width="15"
                            height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                            </svg>
                            Descargar
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($flash_portal_ok): ?>
            <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:14px 18px;color:#166534;font-size:14px;font-weight:600;margin-bottom:24px">
                ✅ <?= htmlspecialchars($flash_portal_ok) ?>
            </div>
        <?php endif; ?>
        <?php if ($flash_portal_err): ?>
            <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:14px 18px;color:#991b1b;font-size:14px;font-weight:600;margin-bottom:24px">
                ⚠️ <?= htmlspecialchars($flash_portal_err) ?>
            </div>
        <?php endif; ?>

        <?php if ($trabajo_config): ?>
        <?php
        // Calcular estado de entrega y permisos de subida
        $puede_subir   = true;
        $bloqueo_razon = '';
        if ($mi_entrega) {
            if ($mi_entrega['estado'] === 'aprobado') {
                $puede_subir   = false;
                $bloqueo_razon = 'aprobado';
            } elseif (!(int) $trabajo_config['permite_reentrega']) {
                $puede_subir   = false;
                $bloqueo_razon = 'sin_reentrega';
            }
        }
        $es_fuera_plazo = $trabajo_config['fecha_limite'] && strtotime($trabajo_config['fecha_limite']) < time();
        ?>

        <div class="section-header" style="margin-top:8px">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            Trabajo integrador final
        </div>

        <div style="background:white;border-radius:16px;padding:28px;box-shadow:0 1px 8px rgba(0,0,0,.06),0 0 0 1px rgba(124,58,237,.06);margin-bottom:28px">

            <!-- Título y descripción -->
            <div style="margin-bottom:20px">
                <h3 style="font-size:17px;font-weight:700;color:#111827;margin-bottom:8px"><?= htmlspecialchars($trabajo_config['titulo']) ?></h3>
                <?php if ($trabajo_config['descripcion']): ?>
                <p style="font-size:14px;color:#374151;line-height:1.7;white-space:pre-wrap"><?= htmlspecialchars($trabajo_config['descripcion']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Fecha límite + calificación máxima -->
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px">
                <?php if ($trabajo_config['fecha_limite']): ?>
                <span style="display:inline-flex;align-items:center;gap:6px;background:<?= $es_fuera_plazo ? '#fef2f2' : '#f5f3ff' ?>;color:<?= $es_fuera_plazo ? '#991b1b' : '#5b21b6' ?>;padding:6px 14px;border-radius:99px;font-size:12px;font-weight:600;border:1px solid <?= $es_fuera_plazo ? '#fca5a5' : '#ede9fe' ?>">
                    <?= $es_fuera_plazo ? '⏰' : '📅' ?>
                    Fecha límite: <?= date('d/m/Y H:i', strtotime($trabajo_config['fecha_limite'])) ?>
                    <?= $es_fuera_plazo ? '— Plazo vencido' : '' ?>
                </span>
                <?php endif; ?>
                <span style="display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;color:#166534;padding:6px 14px;border-radius:99px;font-size:12px;font-weight:600;border:1px solid #86efac">
                    🏆 Calificación máxima: <?= htmlspecialchars($trabajo_config['calificacion_maxima']) ?> puntos
                </span>
            </div>

            <!-- Recursos base -->
            <?php if (!empty($trabajo_recursos_base)): ?>
            <div style="margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid #f3f4f6">
                <p style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">📎 Materiales de referencia</p>
                <div style="display:flex;flex-direction:column;gap:10px">
                <?php foreach ($trabajo_recursos_base as $trb):
                    $trb_ext = strtoupper($trb['tipo'] ?? pathinfo($trb['archivo'], PATHINFO_EXTENSION));
                    $trb_sz  = (int) ($trb['tamanio'] ?? 0);
                    $trb_sz_str = $trb_sz > 1048576 ? round($trb_sz/1048576,1).' MB' : round($trb_sz/1024,0).' KB';
                ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px">
                        <div style="min-width:0">
                            <span style="font-size:14px;font-weight:600;color:#111827;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($trb['nombre']) ?></span>
                            <?php if ($trb['descripcion']): ?>
                            <span style="font-size:12px;color:#6b7280"><?= htmlspecialchars($trb['descripcion']) ?></span>
                            <?php endif; ?>
                            <span style="font-size:11px;color:#9ca3af"><?= $trb_ext ?> · <?= $trb_sz ? $trb_sz_str : '' ?></span>
                        </div>
                        <a href="portal.php?descargar_trabajo_recurso=<?= (int) $trb['id'] ?>"
                           style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#1a1433,#6d28d9);color:#fff;text-decoration:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;white-space:nowrap;box-shadow:0 2px 8px rgba(109,40,217,.3)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Descargar
                        </a>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Estado de mi entrega -->
            <?php if ($mi_entrega): ?>
            <?php
            switch ($mi_entrega['estado']) {
                case 'aprobado':
                    $ent_bg = '#dcfce7'; $ent_cl = '#166534'; $ent_label = '✅ Aprobado'; break;
                case 'requiere_ajustes':
                    $ent_bg = '#ffedd5'; $ent_cl = '#9a3412'; $ent_label = '🔶 Requiere ajustes'; break;
                case 'en_revision':
                    $ent_bg = '#e0f2fe'; $ent_cl = '#0369a1'; $ent_label = '🔍 En revisión'; break;
                case 'no_aprobado':
                    $ent_bg = '#fef2f2'; $ent_cl = '#991b1b'; $ent_label = '❌ No aprobado'; break;
                default:
                    $ent_bg = '#f3f4f6'; $ent_cl = '#374151'; $ent_label = '📤 Entregado';
            }
            $nom_ent = $mi_entrega['nombre_original'] ?: $mi_entrega['archivo'];
            ?>
            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;margin-bottom:20px">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px">
                    <span style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px">Mi entrega</span>
                    <span style="background:<?= $ent_bg ?>;color:<?= $ent_cl ?>;padding:4px 12px;border-radius:99px;font-size:12px;font-weight:700"><?= $ent_label ?></span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                    <div>
                        <span style="font-size:13px;color:#374151;font-weight:500"><?= htmlspecialchars($nom_ent) ?></span>
                        <?php if ($mi_entrega['entrega_tardia']): ?>
                        <span style="margin-left:8px;font-size:11px;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:99px;font-weight:600">Fuera de plazo</span>
                        <?php endif; ?>
                        <br>
                        <span style="font-size:12px;color:#9ca3af">
                            Enviado: <?= date('d/m/Y H:i', strtotime($mi_entrega['fecha_entrega'])) ?>
                            <?php if ($mi_entrega['fecha_evaluacion']): ?>
                            &nbsp;·&nbsp; Evaluado: <?= date('d/m/Y H:i', strtotime($mi_entrega['fecha_evaluacion'])) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($mi_entrega['archivo']): ?>
                    <a href="descargar_entrega.php?id=<?= (int) $mi_entrega['id'] ?>"
                       style="display:inline-flex;align-items:center;gap:6px;background:#f0f4ff;color:#3730a3;border:1px solid #c7d2fe;text-decoration:none;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Ver mi entrega
                    </a>
                    <?php endif; ?>
                </div>
                <?php if ($mi_entrega['calificacion'] !== null): ?>
                <div style="margin-top:14px;padding-top:12px;border-top:1px solid #e5e7eb">
                    <span style="font-size:13px;color:#374151;font-weight:600">Calificación: </span>
                    <span style="font-size:20px;font-weight:800;color:#1a1433"><?= htmlspecialchars($mi_entrega['calificacion']) ?></span>
                    <span style="font-size:13px;color:#6b7280"> / <?= htmlspecialchars($trabajo_config['calificacion_maxima']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($mi_entrega['comentarios_evaluador']): ?>
                <div style="margin-top:12px;padding:12px 16px;background:white;border-radius:8px;border-left:3px solid #6d28d9">
                    <p style="font-size:12px;font-weight:700;color:#6b7280;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px">Comentarios del evaluador</p>
                    <p style="font-size:14px;color:#374151;line-height:1.6;white-space:pre-wrap"><?= htmlspecialchars($mi_entrega['comentarios_evaluador']) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Formulario de subida -->
            <?php if ($puede_subir): ?>
            <?php if ($es_fuera_plazo && !$mi_entrega): ?>
            <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:10px 14px;font-size:13px;color:#92400e;margin-bottom:16px">
                ⏰ El plazo de entrega venció, pero aún puedes subir tu trabajo. Quedará marcado como <strong>entrega fuera de plazo</strong>.
            </div>
            <?php elseif ($es_fuera_plazo && $mi_entrega): ?>
            <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:10px 14px;font-size:13px;color:#92400e;margin-bottom:16px">
                ⏰ El plazo venció. Si reemplazas tu entrega, quedará marcada como <strong>fuera de plazo</strong>.
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div style="border:2px dashed #c4b5fd;border-radius:12px;padding:24px;text-align:center;background:#faf5ff;margin-bottom:4px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
                        stroke="#7c3aed" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                        style="display:block;margin:0 auto 10px">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p style="font-size:14px;font-weight:600;color:#1a1433;margin-bottom:6px"><?= $mi_entrega ? 'Reemplazar entrega' : 'Subir mi entrega' ?></p>
                    <p style="font-size:12px;color:#6b7280;margin-bottom:14px">PDF, Word, Excel, PowerPoint, ZIP · Máx. 25 MB</p>
                    <input type="file" name="entrega_archivo" id="entrega_archivo" required
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.ppt,.pptx"
                        style="display:none"
                        onchange="document.getElementById('nombre-archivo-sel').textContent = this.files[0]?.name || ''">
                    <label for="entrega_archivo"
                        style="display:inline-flex;align-items:center;gap:8px;background:#f5f3ff;color:#6d28d9;border:1.5px solid #c4b5fd;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                        📂 Seleccionar archivo
                    </label>
                    <p id="nombre-archivo-sel" style="font-size:12px;color:#7c3aed;margin-top:10px;min-height:16px;font-weight:500"></p>
                </div>
                <button type="submit" name="subir_entrega"
                    style="width:100%;margin-top:14px;background:linear-gradient(135deg,#1a1433,#6d28d9);color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(109,40,217,.35);font-family:inherit">
                    <?= $mi_entrega ? '🔄 Reemplazar entrega' : '📤 Enviar entrega' ?>
                </button>
            </form>
            <?php elseif ($bloqueo_razon === 'aprobado'): ?>
            <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:14px 18px;color:#166534;font-size:14px;font-weight:600;text-align:center">
                ✅ Tu trabajo fue aprobado. No se permiten nuevas entregas.
            </div>
            <?php else: ?>
            <div style="background:#f3f4f6;border-radius:10px;padding:14px 18px;color:#6b7280;font-size:14px;text-align:center">
                🔒 Las reentregas no están habilitadas para este trabajo.
            </div>
            <?php endif; ?>

        </div>
        <?php endif; // $trabajo_config ?>

    </div>

    <div class="footer">© F&C Consultores · Todos los derechos reservados · Uso exclusivo participantes del Diplomado
    </div>

    <!-- Overlay de descarga -->
    <div id="overlay-descarga">
        <div class="overlay-box">
            <div style="font-size:48px; margin-bottom:12px;">⬇️</div>
            <div style="font-size:18px; font-weight:700; color:#1a1433; margin-bottom:4px;">Descargando archivo</div>
            <div id="overlay-tipo" style="font-size:13px; color:#6d28d9; font-weight:600; margin-bottom:20px;"></div>

            <!-- Barra de progreso real -->
            <div style="background:#f0ecff; border-radius:99px; height:10px; overflow:hidden; margin-bottom:10px;">
                <div id="barra-progreso"
                    style="height:100%; width:0%; background:linear-gradient(90deg,#7c3aed,#a855f7); border-radius:99px; transition:width 0.3s ease;">
                </div>
            </div>
            <div
                style="display:flex; justify-content:space-between; font-size:12px; color:#9ca3af; margin-bottom:20px;">
                <span id="overlay-mb">0 MB</span>
                <span id="overlay-pct">0%</span>
            </div>

            <div id="overlay-mensaje" style="font-size:13px; color:#6b7280; line-height:1.6; margin-bottom:20px;">
                Iniciando descarga, por favor espera...
            </div>

            <button id="btn-cerrar-overlay" onclick="document.getElementById('overlay-descarga').classList.remove('visible')"
                style="display:none; padding:10px 24px; background:#7c3aed; color:white; border:none; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer;">
                Cerrar
            </button>
        </div>
    </div>

    <script>
        function iniciarDescarga(e, el, tipo, nombre) {
            e.preventDefault();
            _ejecutarDescarga(el.href, tipo, nombre);
        }

        async function _ejecutarDescarga(url, tipo, nombre) {
            const overlay = document.getElementById('overlay-descarga');
            const barra = document.getElementById('barra-progreso');
            const pctEl = document.getElementById('overlay-pct');
            const mbEl = document.getElementById('overlay-mb');
            const tipoEl = document.getElementById('overlay-tipo');
            const msgEl = document.getElementById('overlay-mensaje');
            const btnCerrar = document.getElementById('btn-cerrar-overlay');

            tipoEl.textContent = 'Archivo ' + tipo;
            barra.style.width = '0%';
            pctEl.textContent = '0%';
            mbEl.textContent = '0 MB';
            msgEl.textContent = 'Conectando con el servidor...';
            btnCerrar.style.display = 'none';
            overlay.classList.add('visible');

            // Chrome/Edge: stream directo a disco sin cargar en memoria
            if ('showSaveFilePicker' in window) {
                let fileHandle;
                try {
                    fileHandle = await showSaveFilePicker({ suggestedName: nombre });
                } catch (err) {
                    overlay.classList.remove('visible');
                    return; // usuario canceló el diálogo
                }

                try {
                    const writable = await fileHandle.createWritable();
                    const response = await fetch(url, { credentials: 'same-origin' });
                    const total = parseInt(response.headers.get('Content-Length') || '0');
                    let received = 0;

                    msgEl.textContent = 'Descargando, por favor no cierres esta página...';

                    const reader = response.body.getReader();
                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;
                        await writable.write(value);
                        received += value.length;

                        const mb = (received / 1048576).toFixed(1);
                        const pct = total > 0 ? Math.min(Math.round((received / total) * 100), 99) : 0;
                        barra.style.width = pct + '%';
                        pctEl.textContent = pct + '%';
                        mbEl.textContent = mb + ' MB';
                    }

                    await writable.close();
                    barra.style.width = '100%';
                    pctEl.textContent = '100%';
                    mbEl.textContent = (received / 1048576).toFixed(1) + ' MB';
                    msgEl.textContent = '¡Descarga completada!';
                    msgEl.style.color = '#059669';
                    msgEl.style.fontWeight = '700';
                    btnCerrar.style.display = 'inline-block';

                } catch (err) {
                    msgEl.textContent = 'Ocurrió un error durante la descarga. Intenta de nuevo.';
                    msgEl.style.color = '#dc2626';
                    btnCerrar.style.display = 'inline-block';
                }

            } else {
                // Firefox / Safari: descarga tradicional con overlay manual
                msgEl.innerHTML = 'La descarga ha iniciado en tu navegador.<br><strong style="color:#1a1433;">Puedes cerrar este aviso cuando veas el progreso en la barra de descargas.</strong>';

                // Barra indeterminada animada
                let pct = 0;
                const anim = setInterval(function () {
                    pct = pct < 90 ? pct + Math.random() * 3 : pct + 0.1;
                    if (pct > 95) pct = 95;
                    barra.style.width = pct + '%';
                    pctEl.textContent = Math.round(pct) + '%';
                }, 500);

                btnCerrar.style.display = 'inline-block';
                window.location.href = url;

                setTimeout(function () { clearInterval(anim); }, 60000);
            }
        }
    </script>

</body>

</html>