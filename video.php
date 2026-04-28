<?php
session_start();
require_once 'db.php';

date_default_timezone_set('America/Bogota');

define('VIDEO_EXPIRACION_HORAS', 4);

// ── Helpers ──────────────────────────────────────────────────────────────────

function logAcceso(int $participante_id, ?int $recurso_id, string $accion): void
{
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare("INSERT INTO accesos (participante_id, accion, recurso_id, ip) VALUES (?, ?, ?, ?)")
        ->execute([$participante_id, $accion, $recurso_id, $ip]);
}

function renderError(string $titulo, string $mensaje, string $btn_texto, string $btn_url): void
{
    http_response_code(http_response_code() ?: 403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($titulo) ?> — F&amp;C Consultores</title>
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
                max-width: 440px;
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
            p { font-size: 14px; color: #6b7280; line-height: 1.7; margin-bottom: 28px; }
            a {
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
            <h1><?= htmlspecialchars($titulo) ?></h1>
            <p><?= htmlspecialchars($mensaje) ?></p>
            <a href="<?= htmlspecialchars($btn_url) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                <?= htmlspecialchars($btn_texto) ?>
            </a>
        </div>
    </body>
    </html>
    <?php
}

function renderAccesoAgotado(array $tv): void
{
    $fecha_acceso = date('d/m/Y \a \l\a\s H:i', strtotime($tv['usado_en']));
    $fecha_expira = date('d/m/Y \a \l\a\s H:i', strtotime($tv['expira_en']));
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
            p { font-size: 14px; color: #6b7280; line-height: 1.7; margin-bottom: 28px; }
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
            a {
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
            <p>Este contenido ya fue visualizado. Tu acceso ha expirado y no está disponible nuevamente.</p>
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
            <a href="portal.php">
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
}

// ── Auth: sesión activa ──────────────────────────────────────────────────────

if (!isset($_SESSION['participante_id'])) {
    http_response_code(403);
    renderError(
        'Sesión no válida',
        'Debes iniciar sesión para acceder a este contenido.',
        'Ir al login',
        'index.php'
    );
    exit;
}

// ── Token presente ───────────────────────────────────────────────────────────

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    http_response_code(400);
    renderError(
        'Enlace inválido',
        'El enlace de acceso no contiene un token válido.',
        'Volver al portal',
        'portal.php'
    );
    exit;
}

// ── Token existe en BD ───────────────────────────────────────────────────────

$stmt = $pdo->prepare("SELECT * FROM tokens_video WHERE token = ?");
$stmt->execute([$token]);
$tv = $stmt->fetch();

if (!$tv) {
    logAcceso((int) $_SESSION['participante_id'], null, 'video_expirado');
    http_response_code(403);
    renderError(
        'Acceso no autorizado',
        'El enlace de acceso no es válido o ya fue eliminado.',
        'Volver al portal',
        'portal.php'
    );
    exit;
}

// ── Token pertenece al participante en sesión ────────────────────────────────

if ((int) $tv['participante_id'] !== (int) $_SESSION['participante_id']) {
    http_response_code(403);
    renderError(
        'Acceso no autorizado',
        'Este enlace de video no pertenece a tu cuenta.',
        'Volver al portal',
        'portal.php'
    );
    exit;
}

// ── Token no expirado ────────────────────────────────────────────────────────

if (new DateTime('now') > new DateTime($tv['expira_en'])) {
    logAcceso((int) $tv['participante_id'], (int) $tv['recurso_id'], 'video_expirado');
    http_response_code(403);
    if ($tv['usado_en'] !== null) {
        renderAccesoAgotado($tv);
    } else {
        renderError(
            'Enlace expirado',
            'Tu enlace de acceso al video expiró. Regresa al portal y haz clic en "Ver video" para obtener uno nuevo.',
            'Volver al portal',
            'portal.php'
        );
    }
    exit;
}

// ── Recurso activo ───────────────────────────────────────────────────────────

$stmt = $pdo->prepare("SELECT * FROM recursos WHERE id = ? AND activo = 1");
$stmt->execute([(int) $tv['recurso_id']]);
$recurso = $stmt->fetch();

if (!$recurso) {
    http_response_code(404);
    renderError(
        'Video no disponible',
        'El video solicitado ya no está disponible.',
        'Volver al portal',
        'portal.php'
    );
    exit;
}

// ── Permiso activo del participante ──────────────────────────────────────────

$stmt_perm = $pdo->prepare(
    "SELECT id FROM recurso_permisos_video
     WHERE recurso_id = ? AND participante_id = ? AND activo = 1
       AND (fecha_inicio IS NULL OR fecha_inicio <= NOW())
       AND (fecha_fin   IS NULL OR fecha_fin   >= NOW())"
);
$stmt_perm->execute([(int) $tv['recurso_id'], (int) $tv['participante_id']]);
if (!$stmt_perm->fetch()) {
    logAcceso((int) $tv['participante_id'], (int) $tv['recurso_id'], 'video_no_autorizado');
    http_response_code(403);
    renderError(
        'No autorizado',
        'No tienes autorización para ver este video. Contacta al administrador si crees que esto es un error.',
        'Volver al portal',
        'portal.php'
    );
    exit;
}

$ruta = !empty($recurso['ruta_video']) ? $recurso['ruta_video'] : $recurso['archivo'];
$fn   = basename($ruta);
// Buscar primero en carpeta privada; fallback a uploads/ para videos anteriores
$archivo = file_exists('private_videos/' . $fn) ? 'private_videos/' . $fn : 'uploads/' . $fn;
if (!file_exists($archivo)) {
    http_response_code(404);
    renderError(
        'Archivo no encontrado',
        'El archivo de video no se encontró en el servidor. Contacta al administrador.',
        'Volver al portal',
        'portal.php'
    );
    exit;
}

// ── Datos del participante para marca de agua ─────────────────────────────────

$stmt_part = $pdo->prepare("SELECT nombre, documento FROM participantes WHERE id = ?");
$stmt_part->execute([(int) $tv['participante_id']]);
$part_data = $stmt_part->fetch();
$wm_nombre = htmlspecialchars($part_data['nombre']   ?? '', ENT_QUOTES);
$wm_doc    = htmlspecialchars($part_data['documento'] ?? '', ENT_QUOTES);

// ── Modo stream (raw=1): bytes directos para el <video src> ──────────────────

if (isset($_GET['raw'])) {

    if (empty($tv['usado_en'])) {
        $pdo->prepare("UPDATE tokens_video SET usado_en = NOW() WHERE token = ?")
            ->execute([$token]);
        logAcceso((int) $tv['participante_id'], (int) $tv['recurso_id'], 'ver_video');
    }

    set_time_limit(0);
    while (ob_get_level()) ob_end_clean();

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $archivo);
    finfo_close($finfo);

    $size  = filesize($archivo);
    $start = 0;
    $end   = $size - 1;

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($recurso['archivo']) . '"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    if (isset($_SERVER['HTTP_RANGE'])) {
        if (!preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $start = (int) $m[1];
        $end   = ($m[2] !== '') ? (int) $m[2] : $size - 1;
        if ($start > $end || $start >= $size || $end >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    header('Content-Length: ' . ($end - $start + 1));

    $fp        = fopen($archivo, 'rb');
    fseek($fp, $start);
    $remaining = $end - $start + 1;

    while ($remaining > 0 && !feof($fp)) {
        $chunk = min(65536, $remaining);
        echo fread($fp, $chunk);
        $remaining -= $chunk;
        flush();
    }

    fclose($fp);
    exit;
}

// ── Modo reproductor: página HTML con controles personalizados ────────────────

$token_enc    = htmlspecialchars(urlencode($token));
$nombre_video = htmlspecialchars($recurso['nombre']);
$expira_ts    = strtotime($tv['expira_en']);   // timestamp UNIX hora Colombia
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $nombre_video ?> — F&amp;C Consultores</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(145deg, #0f0a1e 0%, #1e0f4a 45%, #12082e 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
        }

        .topbar {
            width: 100%;
            background: rgba(15, 10, 30, 0.85);
            border-bottom: 1px solid rgba(167, 139, 250, 0.15);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .brand {
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand span { color: #a78bfa; }

        .btn-volver {
            display: flex;
            align-items: center;
            gap: 6px;
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            padding: 7px 14px;
            border-radius: 8px;
            transition: background 0.2s, color 0.2s;
        }

        .btn-volver:hover { color: white; background: rgba(255,255,255,0.14); }

        .content {
            width: 100%;
            max-width: 960px;
            padding: 24px 20px 48px;
        }

        .video-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── Player ─────────────────────────────────────────────────── */
        #player-wrap {
            position: relative;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            user-select: none;
            -webkit-user-select: none;
            box-shadow: 0 8px 48px rgba(0,0,0,0.7);
            aspect-ratio: 16 / 9;
        }

        #vid {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
            cursor: pointer;
        }

        /* Controls overlay */
        #controls {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.88));
            padding: 40px 14px 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            opacity: 0;
            z-index: 10;
            transition: opacity 0.25s;
        }

        #player-wrap:hover #controls,
        #player-wrap.show-ctrl #controls { opacity: 1; }

        /* Progress bar */
        #prog-wrap {
            position: relative;
            height: 6px;
            background: rgba(255,255,255,0.22);
            border-radius: 99px;
            cursor: pointer;
            transition: height 0.15s;
        }

        /* Expand touch hit area without changing visual height */
        #prog-wrap::before {
            content: '';
            position: absolute;
            top: -8px; bottom: -8px; left: 0; right: 0;
        }

        #prog-wrap:hover { height: 8px; }

        #prog-fill {
            height: 100%;
            background: linear-gradient(90deg, #7c3aed, #a855f7);
            border-radius: 99px;
            width: 0%;
            pointer-events: none;
        }

        #prog-thumb {
            position: absolute;
            top: 50%; left: 0%;
            width: 16px; height: 16px;
            background: white;
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            transition: transform 0.15s;
            pointer-events: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.25);
        }

        #prog-wrap:hover #prog-thumb { transform: translate(-50%, -50%) scale(1); }

        /* Buttons row */
        .ctrl-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ctrl-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.88;
            flex-shrink: 0;
            transition: opacity 0.15s, transform 0.1s;
            min-width: 36px;
            min-height: 36px;
        }

        .ctrl-btn:hover { opacity: 1; transform: scale(1.1); }

        #time-disp {
            color: rgba(255,255,255,0.8);
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        #vol-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        #vol-slider {
            width: 58px;
            height: 4px;
            -webkit-appearance: none;
            appearance: none;
            background: rgba(255,255,255,0.3);
            border-radius: 99px;
            cursor: pointer;
            outline: none;
        }

        #vol-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 12px; height: 12px;
            background: white; border-radius: 50%;
        }

        #vol-slider::-moz-range-thumb {
            width: 12px; height: 12px;
            background: white; border-radius: 50%; border: none;
        }

        .ml-auto { margin-left: auto; }

        /* Big play overlay */
        #big-play {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(109, 40, 217, 0.82);
            border: none;
            border-radius: 50%;
            width: 68px; height: 68px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            pointer-events: none;
            z-index: 10;
            transition: background 0.2s, transform 0.15s, opacity 0.2s;
        }

        #player-wrap.show-ctrl #big-play {
            opacity: 1;
            pointer-events: auto;
        }

        #big-play:hover {
            background: rgba(124, 58, 237, 1);
            transform: translate(-50%, -50%) scale(1.08);
        }

        /* Fullscreen */
        #player-wrap:-webkit-full-screen,
        #player-wrap:fullscreen { aspect-ratio: unset; }

        #player-wrap:-webkit-full-screen #vid,
        #player-wrap:fullscreen #vid { height: 100%; max-height: 100vh; }

        /* Tablet */
        @media (max-width: 768px) {
            .topbar { padding: 10px 18px; }
            .content { padding: 18px 16px 36px; }
            .video-title { font-size: 16px; margin-bottom: 12px; }
            #big-play { width: 56px; height: 56px; }
        }

        /* Mobile: controls always visible, hide volume */
        @media (max-width: 600px) {
            .topbar { padding: 9px 14px; }
            .brand { font-size: 13px; }
            .btn-volver { padding: 6px 12px; font-size: 12px; }
            .content { padding: 14px 14px 24px; }
            .video-title { font-size: 15px; white-space: normal; overflow: visible; }
            #controls { opacity: 1 !important; padding: 20px 12px 10px; }
            #vol-group { display: none; }
            #big-play { opacity: 0 !important; pointer-events: none !important; }
        }

        /* ── Badge de estado de carga ──────────────────────────────── */
        #vid-status {
            position: absolute;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.58);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            color: rgba(255, 255, 255, 0.92);
            font-size: 12px;
            font-weight: 500;
            padding: 5px 16px;
            border-radius: 99px;
            white-space: nowrap;
            pointer-events: none;
            z-index: 8;
            opacity: 0;
            transition: opacity 0.3s ease;
            letter-spacing: 0.02em;
            max-width: calc(100% - 28px);
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #vid-status.visible { opacity: 1; }

        @media (max-width: 600px) {
            #vid-status { font-size: 11px; padding: 4px 12px; top: 10px; }
        }

        /* ── Badge de expiración de acceso ─────────────────────────── */
        #expira-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 20;
            background: rgba(0, 0, 0, 0.65);
            color: #fff;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            white-space: nowrap;
            display: block;
        }
        #expira-badge.visible  { opacity: 1; }
        #expira-badge.urgente  { background: rgba(185, 28, 28, 0.80); }

        @media (max-width: 600px) {
            #expira-badge { font-size: 11px; padding: 4px 8px; top: 10px; right: 10px; }
        }

        /* ── Marca de agua ─────────────────────────────────────────── */
        #wm-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 6;
            overflow: hidden;
        }

        #wm-text {
            position: absolute;
            color: rgba(255, 255, 255, 0.16);
            font-size: 12px;
            font-weight: 600;
            line-height: 1.6;
            text-align: center;
            white-space: nowrap;
            user-select: none;
            -webkit-user-select: none;
            transform: translate(-50%, -50%);
            transition: top 2s ease, left 2s ease;
            top: 25%;
            left: 30%;
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
            <path d="M6 12v5c3 3 9 3 12 0v-5"/>
        </svg>
        F&amp;C <span>Consultores</span>
    </div>
    <a href="portal.php" class="btn-volver">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
        </svg>
        Volver al portal
    </a>
</div>

<div class="content">
    <div class="video-title"><?= $nombre_video ?></div>

    <div id="player-wrap" class="show-ctrl">

        <video id="vid"
               src="video.php?token=<?= $token_enc ?>&amp;raw=1"
               controlsList="nodownload noplaybackrate noremoteplayback"
               disablePictureInPicture
               oncontextmenu="return false"
               preload="metadata">
        </video>

        <div id="wm-overlay">
            <div id="wm-text">
                <?= $wm_nombre ?><br>
                <?= $wm_doc ?><br>
                <span id="wm-clock"></span><br>
                <span style="font-size:10px;font-weight:400;opacity:0.85;">Propiedad de F&amp;C CONSULTORES<br>Prohibida su venta o distribución</span>
            </div>
        </div>

        <div id="vid-status" role="status" aria-live="polite" aria-atomic="true">
            <span id="vid-status-text"></span>
        </div>

        <div id="expira-badge" role="timer" aria-live="polite">
            Tu acceso expira en <span id="expira-tiempo">--:--</span>
        </div>

        <button id="big-play" aria-label="Reproducir">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
                 fill="white" stroke="none">
                <polygon points="5 3 19 12 5 21 5 3"/>
            </svg>
        </button>

        <div id="controls">

            <div id="prog-wrap">
                <div id="prog-fill"></div>
                <div id="prog-thumb"></div>
            </div>

            <div class="ctrl-row">

                <button class="ctrl-btn" id="btn-play" aria-label="Reproducir / pausar">
                    <svg id="ico-play" xmlns="http://www.w3.org/2000/svg" width="22" height="22"
                         viewBox="0 0 24 24" fill="white" stroke="none">
                        <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                    <svg id="ico-pause" xmlns="http://www.w3.org/2000/svg" width="22" height="22"
                         viewBox="0 0 24 24" fill="white" stroke="none" style="display:none">
                        <rect x="6" y="4" width="4" height="16" rx="1"/>
                        <rect x="14" y="4" width="4" height="16" rx="1"/>
                    </svg>
                </button>

                <div id="vol-group">
                    <button class="ctrl-btn" id="btn-mute" aria-label="Silenciar / activar sonido">
                        <svg id="ico-vol" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                             viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                            <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                            <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                        </svg>
                        <svg id="ico-muted" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                             viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" style="display:none">
                            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                            <line x1="23" y1="9" x2="17" y2="15"/>
                            <line x1="17" y1="9" x2="23" y2="15"/>
                        </svg>
                    </button>
                    <input type="range" id="vol-slider" min="0" max="1" step="0.05" value="1"
                           aria-label="Volumen">
                </div>

                <span id="time-disp">0:00 / 0:00</span>

                <button class="ctrl-btn ml-auto" id="btn-fs" aria-label="Pantalla completa">
                    <svg id="ico-expand" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                         viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 3 21 3 21 9"/>
                        <polyline points="9 21 3 21 3 15"/>
                        <line x1="21" y1="3" x2="14" y2="10"/>
                        <line x1="3" y1="21" x2="10" y2="14"/>
                    </svg>
                    <svg id="ico-compress" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                         viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" style="display:none">
                        <polyline points="4 14 10 14 10 20"/>
                        <polyline points="20 10 14 10 14 4"/>
                        <line x1="10" y1="14" x2="3" y2="21"/>
                        <line x1="21" y1="3" x2="14" y2="10"/>
                    </svg>
                </button>

            </div>
        </div>

    </div><!-- /player-wrap -->
</div><!-- /content -->

<script>
(function () {
    'use strict';

    const vid        = document.getElementById('vid');
    const wrap       = document.getElementById('player-wrap');
    const btnPlay    = document.getElementById('btn-play');
    const bigPlay    = document.getElementById('big-play');
    const icoPlay    = document.getElementById('ico-play');
    const icoPause   = document.getElementById('ico-pause');
    const progWrap   = document.getElementById('prog-wrap');
    const progFill   = document.getElementById('prog-fill');
    const progThumb  = document.getElementById('prog-thumb');
    const timeDisp   = document.getElementById('time-disp');
    const btnMute    = document.getElementById('btn-mute');
    const icoVol     = document.getElementById('ico-vol');
    const icoMuted   = document.getElementById('ico-muted');
    const volSlider  = document.getElementById('vol-slider');
    const btnFs      = document.getElementById('btn-fs');
    const icoExpand  = document.getElementById('ico-expand');
    const icoCompress = document.getElementById('ico-compress');

    function fmt(s) {
        if (!s || isNaN(s)) return '0:00';
        const h   = Math.floor(s / 3600);
        const m   = Math.floor((s % 3600) / 60);
        const sec = Math.floor(s % 60);
        const ss  = sec < 10 ? '0' + sec : sec;
        if (h > 0) {
            const mm = m < 10 ? '0' + m : m;
            return h + ':' + mm + ':' + ss;
        }
        return m + ':' + ss;
    }

    function syncPlay() {
        const paused = vid.paused || vid.ended;
        icoPlay.style.display  = paused ? '' : 'none';
        icoPause.style.display = paused ? 'none' : '';
        paused ? wrap.classList.add('show-ctrl') : wrap.classList.remove('show-ctrl');
    }

    function togglePlay() {
        vid.paused ? vid.play() : vid.pause();
    }

    vid.addEventListener('click',  togglePlay);
    btnPlay.addEventListener('click', togglePlay);
    bigPlay.addEventListener('click', togglePlay);
    vid.addEventListener('play',  syncPlay);
    vid.addEventListener('pause', syncPlay);
    vid.addEventListener('ended', syncPlay);

    vid.addEventListener('timeupdate', function () {
        if (!vid.duration) return;
        const pct = (vid.currentTime / vid.duration) * 100;
        progFill.style.width = pct + '%';
        progThumb.style.left = pct + '%';
        timeDisp.textContent = fmt(vid.currentTime) + ' / ' + fmt(vid.duration);
    });

    vid.addEventListener('loadedmetadata', function () {
        timeDisp.textContent = '0:00 / ' + fmt(vid.duration);
    });

    // Progress seeking
    var dragging = false;
    function seekAt(e) {
        if (!vid.duration) return;
        var rect = progWrap.getBoundingClientRect();
        var cx   = (e.touches && e.touches[0]) ? e.touches[0].clientX : e.clientX;
        var pct  = Math.max(0, Math.min(1, (cx - rect.left) / rect.width));
        vid.currentTime = pct * vid.duration;
    }
    progWrap.addEventListener('mousedown',  function (e) { dragging = true; seekAt(e); });
    progWrap.addEventListener('touchstart', function (e) { dragging = true; seekAt(e); }, { passive: true });
    document.addEventListener('mousemove',  function (e) { if (dragging) seekAt(e); });
    document.addEventListener('touchmove',  function (e) { if (dragging) seekAt(e); }, { passive: true });
    document.addEventListener('mouseup',  function () { dragging = false; });
    document.addEventListener('touchend', function () { dragging = false; });

    // Volume
    volSlider.addEventListener('input', function () {
        vid.volume = parseFloat(this.value);
        vid.muted  = (parseFloat(this.value) === 0);
        syncMute();
    });
    btnMute.addEventListener('click', function () {
        vid.muted     = !vid.muted;
        volSlider.value = vid.muted ? 0 : (vid.volume || 1);
        syncMute();
    });
    function syncMute() {
        var m = vid.muted || vid.volume === 0;
        icoVol.style.display   = m ? 'none' : '';
        icoMuted.style.display = m ? '' : 'none';
    }

    // Fullscreen
    btnFs.addEventListener('click', function () {
        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
            var req = wrap.requestFullscreen || wrap.webkitRequestFullscreen || wrap.mozRequestFullScreen;
            if (req) req.call(wrap);
        } else {
            var ex = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen;
            if (ex) ex.call(document);
        }
    });
    document.addEventListener('fullscreenchange', syncFs);
    document.addEventListener('webkitfullscreenchange', syncFs);
    function syncFs() {
        var fs = !!(document.fullscreenElement || document.webkitFullscreenElement);
        icoExpand.style.display   = fs ? 'none' : '';
        icoCompress.style.display = fs ? '' : 'none';
    }

    // Auto-hide controls while playing
    var hideTimer;
    function resetHide() {
        clearTimeout(hideTimer);
        wrap.classList.add('show-ctrl');
        if (!vid.paused) {
            hideTimer = setTimeout(function () {
                if (!vid.paused) wrap.classList.remove('show-ctrl');
            }, 3000);
        }
    }
    wrap.addEventListener('mousemove',  resetHide);
    wrap.addEventListener('touchstart', resetHide, { passive: true });

    // Block right-click on video element
    vid.addEventListener('contextmenu', function (e) { e.preventDefault(); });

    // ── Estado de carga ────────────────────────────────────────────────────
    var statusEl    = document.getElementById('vid-status');
    var statusText  = document.getElementById('vid-status-text');
    var statusTimer;

    function showStatus(msg) {
        clearTimeout(statusTimer);
        statusText.textContent = msg;
        statusEl.classList.add('visible');
    }

    function hideStatus(delay) {
        clearTimeout(statusTimer);
        statusTimer = setTimeout(function () {
            statusEl.classList.remove('visible');
        }, delay !== undefined ? delay : 0);
    }

    vid.addEventListener('loadstart',      function () { showStatus('Preparando video…'); });
    vid.addEventListener('loadedmetadata', function () { showStatus('Cargando información…'); });
    vid.addEventListener('canplay',        function () { showStatus('Video listo para reproducir'); hideStatus(2500); });
    vid.addEventListener('waiting',        function () { showStatus('Cargando más contenido…'); });
    vid.addEventListener('playing',        function () { hideStatus(900); });
    vid.addEventListener('error',          function () { showStatus('No se pudo cargar el video.'); });

    showStatus('Preparando video…');

    // ── Marca de agua ─────────────────────────────────────────────────────────
    var wmEl = document.getElementById('wm-text');

    function updateWmClock() {
        var n = new Date();
        var p = function (v) { return (v < 10 ? '0' : '') + v; };
        document.getElementById('wm-clock').textContent =
            p(n.getDate()) + '/' + p(n.getMonth() + 1) + '/' + n.getFullYear() +
            ' ' + p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
    }

    function moveWm() {
        if (!wmEl) return;
        wmEl.style.top  = (12 + Math.random() * 62) + '%';
        wmEl.style.left = (12 + Math.random() * 68) + '%';
    }

    updateWmClock();
    setInterval(updateWmClock, 1000);
    moveWm();
    setInterval(moveWm, 9000);

    syncPlay();

    // ── Badge de expiración de acceso ──────────────────────────────────────
    var expiraTs    = <?= (int) $expira_ts ?> * 1000;
    var expiraBadge = document.getElementById('expira-badge');
    var expiraSpan  = document.getElementById('expira-tiempo');

    console.log('[expira] expiraTs:', expiraTs, '| Date.now:', Date.now(), '| null?', !expiraBadge, !expiraSpan);

    function formatearExpira(seg) {
        var m = Math.floor(seg / 60);
        var s = seg % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function actualizarExpira() {
        var restante = Math.floor((expiraTs - Date.now()) / 1000);

        console.log('[expira] restante:', restante, 'seg | visible:', restante <= 600);

        if (restante <= 0) {
            expiraBadge.innerHTML = 'Acceso expirado';
            expiraBadge.classList.add('visible', 'urgente');
            return;
        }

        if (restante <= 600) {
            expiraBadge.classList.add('visible');
            expiraBadge.classList.toggle('urgente', restante <= 120);
            expiraSpan.textContent = formatearExpira(restante);
        } else {
            expiraBadge.classList.remove('visible');
        }
    }

    actualizarExpira();
    setInterval(actualizarExpira, 1000);
}());
</script>

</body>
</html>
