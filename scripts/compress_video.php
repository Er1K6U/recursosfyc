<?php
/**
 * compress_video.php — Comprime videos de private_videos/ con FFmpeg
 *
 * Uso (desde cualquier directorio):
 *   php scripts/compress_video.php --input=nombre.mp4 --profile=web|hq|mobile
 *   php scripts/compress_video.php --list
 *   php scripts/compress_video.php --help
 */

// ── Solo CLI ────────────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos.' . PHP_EOL);
}

// ── Rutas — siempre relativas al script, funciona desde cualquier directorio ─
$baseDir     = realpath(__DIR__ . '/../');
if ($baseDir === false) {
    die("Error: no se pudo resolver la ruta raíz del proyecto desde " . __DIR__ . "\n");
}
$private_dir = $baseDir . DIRECTORY_SEPARATOR . 'private_videos' . DIRECTORY_SEPARATOR;

// ── Perfiles disponibles ────────────────────────────────────────────────────
$profiles = [
    'hq' => [
        'label'   => 'Alta calidad',
        'crf'     => 18,
        'preset'  => 'slow',
        'scale'   => null,
        'audio'   => '192k',
    ],
    'web' => [
        'label'   => 'Web 720p',
        'crf'     => 23,
        'preset'  => 'medium',
        'scale'   => 720,
        'audio'   => '128k',
    ],
    'mobile' => [
        'label'   => 'Móvil 480p',
        'crf'     => 28,
        'preset'  => 'fast',
        'scale'   => 480,
        'audio'   => '96k',
    ],
];

// ── Argumentos CLI ──────────────────────────────────────────────────────────
$opts = getopt('', ['input:', 'profile:', 'list', 'help']);

if (isset($opts['help']) || $argc === 1) {
    echo <<<HELP

compress_video.php — Compresor de videos para recursosfyc
==========================================================

Uso:
  php scripts/compress_video.php --input=nombre.mp4 --profile=<perfil>
  php scripts/compress_video.php --list

Perfiles disponibles:
  hq      Alta calidad   — CRF 18, preset slow,   resolución original, audio 192k
  web     Web 720p       — CRF 23, preset medium,  escala a 720p,       audio 128k
  mobile  Móvil 480p     — CRF 28, preset fast,    escala a 480p,       audio  96k

Ejemplo:
  php scripts/compress_video.php --input=clase1.mp4 --profile=web

Salida generada:
  private_videos/clase1_web.mp4

NOTA: El archivo original NO se elimina. Actualiza ruta_video en la BD
      para que el portal sirva el archivo comprimido.

HELP;
    exit(0);
}

// ── Opción --list: mostrar archivos disponibles ─────────────────────────────
if (isset($opts['list'])) {
    echo "\nArchivos .mp4 en private_videos/:\n";
    echo str_repeat('-', 42) . "\n";
    $files = glob($private_dir . '*.mp4');
    if (empty($files)) {
        echo "  (ninguno encontrado)\n";
    } else {
        foreach ($files as $f) {
            $mb = round(filesize($f) / 1048576, 1);
            printf("  %-40s  %6s MB\n", basename($f), $mb);
        }
    }
    echo "\n";
    exit(0);
}

// ── Validar parámetros obligatorios ────────────────────────────────────────
$input_raw = trim($opts['input']  ?? '');
$profile   = trim($opts['profile'] ?? '');

if ($input_raw === '') {
    die("Error: falta --input=nombre.mp4\nEjecuta con --help para ver el uso.\n");
}
if ($profile === '') {
    die("Error: falta --profile=web|hq|mobile\nEjecuta con --help para ver el uso.\n");
}
if (!array_key_exists($profile, $profiles)) {
    die("Error: perfil '$profile' no existe. Opciones: " . implode(', ', array_keys($profiles)) . "\n");
}

// ── Seguridad: evitar path traversal ───────────────────────────────────────
$nombre = basename($input_raw);
if ($nombre === '' || $nombre !== $input_raw) {
    die("Error: nombre de archivo inválido. Escribe solo el nombre sin ruta (ej: clase1.mp4).\n");
}

// ── Solo .mp4 ───────────────────────────────────────────────────────────────
$ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
if ($ext !== 'mp4') {
    die("Error: solo se admiten archivos .mp4 (recibido: .$ext).\n");
}

// ── Verificar existencia del archivo ───────────────────────────────────────
$input_path = $baseDir . '/private_videos/' . $nombre;
if (!file_exists($input_path)) {
    die(
        "Error: el archivo no existe en private_videos/\n" .
        "  Buscado : {$input_path}\n" .
        "  Archivo : {$nombre}\n" .
        "  Tip     : ejecuta --list para ver los archivos disponibles.\n"
    );
}

// ── Verificar FFmpeg ────────────────────────────────────────────────────────
exec('ffmpeg -version 2>&1', $ffmpeg_out, $ffmpeg_code);
if ($ffmpeg_code !== 0) {
    die("Error: FFmpeg no encontrado o no ejecutable.\n" .
        "Verifica que esté instalado y disponible en el PATH.\n");
}
$ffmpeg_ver = explode(' ', $ffmpeg_out[0] ?? '')[2] ?? 'desconocida';

// ── Construir nombre de salida ──────────────────────────────────────────────
$base        = pathinfo($nombre, PATHINFO_FILENAME);
$output_name = "{$base}_{$profile}.mp4";
$output_path = $private_dir . $output_name;

// ── Construir comando FFmpeg ────────────────────────────────────────────────
$p = $profiles[$profile];

$vf_arg = $p['scale'] !== null
    ? ' -vf ' . escapeshellarg('scale=-2:' . $p['scale'])
    : '';

$cmd = sprintf(
    'ffmpeg -y -i %s -c:v libx264 -crf %d -preset %s%s -c:a aac -b:a %s -movflags +faststart %s',
    escapeshellarg($input_path),
    $p['crf'],
    $p['preset'],
    $vf_arg,
    $p['audio'],
    escapeshellarg($output_path)
);

// ── Mostrar cabecera ────────────────────────────────────────────────────────
$size_in_mb = round(filesize($input_path) / 1048576, 1);

echo "\n";
echo "=== COMPRESOR DE VIDEO — recursosfyc ===\n";
echo str_repeat('-', 42) . "\n";
echo "Proyecto   : {$baseDir}\n";
echo "FFmpeg     : v{$ffmpeg_ver}\n";
echo "Archivo    : {$nombre}\n";
echo "Ruta real  : {$input_path}\n";
echo "Entrada    : {$size_in_mb} MB\n";
echo "Salida     : private_videos/{$output_name}\n";
echo "Perfil     : {$p['label']} (CRF {$p['crf']}, preset {$p['preset']})\n";
if ($p['scale']) {
    echo "Resolución : máx. {$p['scale']}p (ancho proporcional)\n";
}
echo "Audio      : AAC {$p['audio']}\n";
echo str_repeat('-', 42) . "\n\n";
echo "Iniciando FFmpeg...\n\n";

// ── Ejecutar (passthru pasa stderr/stdout directamente al terminal) ─────────
passthru($cmd, $exit_code);

echo "\n" . str_repeat('-', 42) . "\n";

// ── Resultado ───────────────────────────────────────────────────────────────
if ($exit_code === 0 && file_exists($output_path)) {
    $size_out_mb = round(filesize($output_path) / 1048576, 1);
    $reduction   = $size_in_mb > 0
        ? round((1 - $size_out_mb / $size_in_mb) * 100)
        : 0;

    echo "COMPLETADO\n";
    echo "  Original   : {$size_in_mb} MB\n";
    echo "  Comprimido : {$size_out_mb} MB  (reducción {$reduction}%)\n";
    echo "  Archivo    : private_videos/{$output_name}\n\n";
    echo "Siguiente paso:\n";
    echo "  Actualiza el campo ruta_video en la BD para servir este archivo,\n";
    echo "  o súbelo por FTP y regístralo desde admin.php.\n\n";
    exit(0);
} else {
    echo "ERROR: FFmpeg terminó con código {$exit_code}.\n";
    echo "  Revisa el output de arriba para ver el detalle del fallo.\n\n";
    exit(1);
}
