<?php
/**
 * descargar_entrega.php
 *
 * Servidor seguro de descarga para entregas del trabajo integrador final.
 * Nunca sirve archivos directamente desde private_entregas/ — siempre
 * pasa por validación de sesión y autorización por rol/evento.
 *
 * Acceso permitido:
 *   admin (rol='admin')     → cualquier entrega, cualquier evento
 *   admin (rol='evaluador') → solo entregas de eventos asignados en trabajo_evaluador_eventos
 *   participante            → solo su propia entrega (participante_id = $_SESSION['participante_id'])
 */

session_start();
require_once 'db.php';

// ── 1. Identificar tipo de sesión activa ───────────────────────────────────
$is_admin        = isset($_SESSION['admin_id']);
$is_participante = isset($_SESSION['participante_id']) && isset($_SESSION['terminos_aceptados']);

if (!$is_admin && !$is_participante) {
    http_response_code(403);
    exit('Acceso denegado');
}

// ── 2. Validar y sanitizar el ID recibido por GET ─────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID de entrega inválido');
}

// ── 3. Consultar entrega con datos relacionados ───────────────────────────
//      Se incluyen personas y eventos para auditoría y autorización.
//      trabajo_integrador_config se omite: no se necesita para servir el archivo.
$stmt = $pdo->prepare(
    "SELECT
         te.id,
         te.archivo,
         te.nombre_original,
         te.participante_id,
         te.persona_id,
         te.evento_id,
         te.estado,
         p.documento,
         p.nombre  AS participante_nombre,
         ev.nombre AS evento_nombre
     FROM  trabajo_integrador_entregas te
     JOIN  personas p  ON p.id  = te.persona_id
     JOIN  eventos  ev ON ev.id = te.evento_id
     WHERE te.id = ?
     LIMIT 1"
);
$stmt->execute([$id]);
$entrega = $stmt->fetch();

if (!$entrega) {
    http_response_code(404);
    exit('Entrega no encontrada');
}

// ── 4. Verificar autorización ──────────────────────────────────────────────
$autorizado = false;

if ($is_admin) {
    $admin_rol = $_SESSION['admin_rol'] ?? 'admin';   // 'admin' por defecto hasta que Task 4 esté implementado

    if ($admin_rol === 'admin') {
        // Acceso total
        $autorizado = true;
    } elseif ($admin_rol === 'evaluador') {
        // Evaluador: solo si está asignado al evento de esta entrega
        $stmt_asig = $pdo->prepare(
            "SELECT id
             FROM  trabajo_evaluador_eventos
             WHERE admin_id = ? AND evento_id = ?
             LIMIT 1"
        );
        $stmt_asig->execute([$_SESSION['admin_id'], $entrega['evento_id']]);
        $autorizado = (bool) $stmt_asig->fetch();
    }
} elseif ($is_participante) {
    // Participante: solo su propia entrega
    $autorizado = (int) $entrega['participante_id'] === (int) $_SESSION['participante_id'];
}

if (!$autorizado) {
    http_response_code(403);
    exit('Acceso denegado');
}

// ── 5. Verificar que la entrega tiene archivo ─────────────────────────────
if (empty($entrega['archivo'])) {
    http_response_code(404);
    exit('Esta entrega no tiene archivo adjunto');
}

// ── 6. Construir ruta segura — prevención de path traversal ──────────────
//
//  Capa 1: basename() elimina cualquier componente de directorio que
//          pudiera venir de la BD (../../etc/passwd, ../admin.php, etc.).
//
//  Capa 2: realpath() resuelve symlinks y referencias relativas.
//          Si la ruta no existe devuelve false (→ 404).
//
//  Capa 3: strpos() confirma que la ruta resuelta sigue dentro de
//          private_entregas/ — defensa en profundidad.

$nombre_archivo  = basename($entrega['archivo']);
$directorio_base = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'private_entregas');

if ($directorio_base === false) {
    // La carpeta private_entregas/ no existe o no es accesible
    http_response_code(500);
    exit('Error de configuración del servidor');
}

$directorio_base = rtrim($directorio_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$filepath        = $directorio_base . $nombre_archivo;

if (!file_exists($filepath)) {
    http_response_code(404);
    exit('Archivo no encontrado en el servidor');
}

$filepath_real = realpath($filepath);

// Confirmar que la ruta resuelta sigue dentro de private_entregas/
if ($filepath_real === false || strpos($filepath_real . DIRECTORY_SEPARATOR, $directorio_base) !== 0) {
    http_response_code(403);
    exit('Ruta de archivo inválida');
}

// ── 7. Registrar descarga en tabla accesos ────────────────────────────────
$pdo->prepare(
    "INSERT INTO accesos (participante_id, evento_id, accion, ip)
     VALUES (?, ?, 'descarga_entrega', ?)"
)->execute([
    $entrega['participante_id'],
    $entrega['evento_id'],
    $_SERVER['REMOTE_ADDR'] ?? '',
]);

// ── 8. Preparar nombre para Content-Disposition ───────────────────────────
//
//  Se usan los dos parámetros del header para máxima compatibilidad:
//    filename=       → fallback ASCII para clientes antiguos
//    filename*=      → RFC 5987 con UTF-8 para nombres con tildes/ñ
//
$nombre_descarga = !empty($entrega['nombre_original'])
    ? $entrega['nombre_original']
    : ('entrega_' . $entrega['documento'] . '_' . $id);

// Fallback ASCII: reemplaza caracteres no imprimibles y comillas
$nombre_ascii = preg_replace('/[^\x20-\x7E]/', '_', str_replace(['"', '\\', '/'], '_', $nombre_descarga));
$nombre_utf8  = rawurlencode($nombre_descarga);

// ── 9. Limpiar buffers y servir el archivo ────────────────────────────────
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename=\"{$nombre_ascii}\"; filename*=UTF-8''{$nombre_utf8}");
header('Content-Length: ' . filesize($filepath_real));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($filepath_real);
exit;
