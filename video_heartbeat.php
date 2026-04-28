<?php
session_start();
require_once 'db.php';

date_default_timezone_set('America/Bogota');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Sesión de participante activa
if (!isset($_SESSION['participante_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sin sesión']);
    exit;
}

$token           = trim($_POST['token']           ?? '');
$event           = trim($_POST['event']           ?? '');
$duration        = (float) ($_POST['duration']        ?? 0);
$seconds_watched = (float) ($_POST['seconds_watched'] ?? 0);

$allowed_events = ['play', 'heartbeat', 'ended'];

if (empty($token) || !in_array($event, $allowed_events, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

try {
    // Buscar token — verificar que existe y pertenece al participante en sesión
    $stmt = $pdo->prepare(
        "SELECT id, participante_id, recurso_id, expira_en
         FROM tokens_video WHERE token = ? LIMIT 1"
    );
    $stmt->execute([$token]);
    $tv = $stmt->fetch();

    if (!$tv) {
        echo json_encode(['ok' => true, 'skip' => 'token_not_found']);
        exit;
    }

    if ((int) $tv['participante_id'] !== (int) $_SESSION['participante_id']) {
        echo json_encode(['ok' => true, 'skip' => 'token_mismatch']);
        exit;
    }

    // Si el token expiró, aceptar silenciosamente sin registrar
    if (new DateTime('now') > new DateTime($tv['expira_en'])) {
        echo json_encode(['ok' => true, 'skip' => 'expired']);
        exit;
    }

    $token_id        = (int) $tv['id'];
    $participante_id = (int) $tv['participante_id'];
    $recurso_id      = (int) $tv['recurso_id'];

    // Sanitizar valores numéricos; cap seconds_watched a la duración real
    $duration_int = max(0, (int) round($duration));
    $sw_cap       = $duration > 0 ? min($seconds_watched, $duration) : $seconds_watched;
    $sw_int       = max(0, (int) round($sw_cap));
    $pct          = $duration_int > 0
                        ? min(1.0000, round($sw_int / $duration_int, 4))
                        : 0.0000;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    if ($event === 'play') {
        // Crear fila si no existe; si ya existe (play tras pausa) solo actualizar last_seen_at
        $pdo->prepare(
            "INSERT INTO video_visualizaciones
                (token_id, participante_id, recurso_id, started_at, last_seen_at,
                 video_duration, user_agent, ip)
             VALUES (?, ?, ?, NOW(), NOW(), ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                last_seen_at   = NOW(),
                video_duration = IF(VALUES(video_duration) > 0,
                                    VALUES(video_duration),
                                    video_duration)"
        )->execute([$token_id, $participante_id, $recurso_id, $duration_int, $ua, $ip]);

    } elseif ($event === 'heartbeat') {
        $pdo->prepare(
            "INSERT INTO video_visualizaciones
                (token_id, participante_id, recurso_id, started_at, last_seen_at,
                 seconds_watched, video_duration, percent_watched, user_agent, ip)
             VALUES (?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                last_seen_at    = NOW(),
                seconds_watched = VALUES(seconds_watched),
                video_duration  = IF(VALUES(video_duration) > 0,
                                     VALUES(video_duration),
                                     video_duration),
                percent_watched = VALUES(percent_watched)"
        )->execute([$token_id, $participante_id, $recurso_id,
                    $sw_int, $duration_int, $pct, $ua, $ip]);

    } elseif ($event === 'ended') {
        // El navegador garantiza que 'ended' solo dispara cuando el video llegó al final.
        // Forzar valores completos sin depender del acumulador JS (puede ser impreciso si hubo seeking).
        if ($duration_int > 0) {
            $sw_int = $duration_int;
            $pct    = 1.0000;
        }
        $pdo->prepare(
            "INSERT INTO video_visualizaciones
                (token_id, participante_id, recurso_id, started_at, last_seen_at,
                 ended_at, seconds_watched, video_duration, percent_watched, completed,
                 user_agent, ip)
             VALUES (?, ?, ?, NOW(), NOW(), NOW(), ?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                last_seen_at    = NOW(),
                ended_at        = NOW(),
                seconds_watched = VALUES(seconds_watched),
                video_duration  = IF(VALUES(video_duration) > 0,
                                     VALUES(video_duration),
                                     video_duration),
                percent_watched = VALUES(percent_watched),
                completed       = 1"
        )->execute([$token_id, $participante_id, $recurso_id,
                    $sw_int, $duration_int, $pct, $ua, $ip]);
    }

    echo json_encode(['ok' => true, 'event' => $event]);

} catch (Throwable $e) {
    // Fallo silencioso — no interrumpir reproducción
    echo json_encode(['ok' => true, 'skip' => 'db_error']);
}
