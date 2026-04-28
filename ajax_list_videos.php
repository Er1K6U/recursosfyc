<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store');

$dir      = realpath(__DIR__ . '/private_videos');
$archivos = [];

if ($dir && is_dir($dir)) {
    foreach (new DirectoryIterator($dir) as $file) {
        if (!$file->isFile()) continue;
        $nombre = $file->getFilename();
        if (strtolower($file->getExtension()) !== 'mp4') continue;
        if (basename($nombre) !== $nombre) continue;
        $archivos[] = [
            'nombre'  => $nombre,
            'size'    => $file->getSize(),
            'size_mb' => number_format($file->getSize() / 1048576, 1) . ' MB',
            'mtime'   => $file->getMTime(),
        ];
    }
}

usort($archivos, fn($a, $b) => $b['mtime'] - $a['mtime']);

echo json_encode(
    array_map(fn($a) => [
        'nombre'  => $a['nombre'],
        'size'    => $a['size'],
        'size_mb' => $a['size_mb'],
    ], $archivos),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
