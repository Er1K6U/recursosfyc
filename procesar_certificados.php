<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit;
}

ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

// Cargar pdfparser
spl_autoload_register(function ($class) {
    $base = __DIR__ . '/libs/pdfparser-master/src/';
    $file = $base . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/Config.php';
require_once __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/Parser.php';
require_once __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/Document.php';
require_once __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/Element.php';
require_once __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/Header.php';
require_once __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/PDFObject.php';
require_once __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/Font.php';
require_once __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/Page.php';
require_once __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/Pages.php';

$resultados = [];
$errores    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip_certificados'])) {

    $zip_tmp  = $_FILES['zip_certificados']['tmp_name'];
    $zip_size = $_FILES['zip_certificados']['size'];

    if ($zip_size === 0) {
        $errores[] = ['archivo' => $_FILES['zip_certificados']['name'], 'motivo' => 'El archivo ZIP llegó vacío. Aumenta el límite de subida en .php-ini (upload_max_filesize y post_max_size).'];
    } else {

        // Carpeta temporal para extraer
        $temp_dir = __DIR__ . '/uploads/temp_cert_' . time();
        mkdir($temp_dir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zip_tmp) === true) {
            $zip->extractTo($temp_dir);
            $zip->close();

            // Obtener todos los participantes
            $stmt = $pdo->query("SELECT id, documento, nombre FROM participantes WHERE activo = 1");
            $participantes = $stmt->fetchAll();

            $indice = [];
            foreach ($participantes as $p) {
                $indice[$p['documento']] = $p;
            }

            // Buscar todos los PDFs extraídos (incluyendo subcarpetas)
            $pdfs = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_dir)
            );

            $parser = new \Smalot\PdfParser\Parser();

            foreach ($pdfs as $pdf_file) {
                if ($pdf_file->isDir()) continue;
                if (strtolower($pdf_file->getExtension()) !== 'pdf') continue;

                $nombre_original = $pdf_file->getFilename();
                $ruta_pdf        = $pdf_file->getPathname();

                try {
                    $pdf   = $parser->parseFile($ruta_pdf);
                    $texto = $pdf->getText();

                    $encontrado = false;
                    foreach ($indice as $documento => $participante) {
                        $doc_con_puntos = number_format((float)$documento, 0, '', '.');
                        if (strpos($texto, $documento) !== false || strpos($texto, $doc_con_puntos) !== false) {

                            $nombre_archivo = 'cert_' . time() . '_' . mt_rand(1000, 9999) . '_' . $nombre_original;
                            $destino        = __DIR__ . '/uploads/' . $nombre_archivo;
                            copy($ruta_pdf, $destino);

                            $stmtCheck = $pdo->prepare("SELECT id, archivo FROM certificados WHERE participante_id = ?");
                            $stmtCheck->execute([$participante['id']]);
                            $existe = $stmtCheck->fetch();

                            if ($existe) {
                                if (file_exists(__DIR__ . '/uploads/' . $existe['archivo'])) {
                                    unlink(__DIR__ . '/uploads/' . $existe['archivo']);
                                }
                                $stmtUp = $pdo->prepare("UPDATE certificados SET nombre=?, archivo=? WHERE id=?");
                                $stmtUp->execute(['Certificado de permanencia', $nombre_archivo, $existe['id']]);
                            } else {
                                $stmtIns = $pdo->prepare("INSERT INTO certificados (participante_id, nombre, archivo) VALUES (?, ?, ?)");
                                $stmtIns->execute([$participante['id'], 'Certificado de permanencia', $nombre_archivo]);
                            }

                            $resultados[] = [
                                'archivo'      => $nombre_original,
                                'participante' => $participante['nombre'],
                                'documento'    => $documento,
                                'estado'       => $existe ? 'Actualizado' : 'Nuevo'
                            ];
                            $encontrado = true;
                            break;
                        }
                    }

                    if (!$encontrado) {
                        $errores[] = [
                            'archivo' => $nombre_original,
                            'motivo'  => 'No se encontró ningún documento de participante en el PDF'
                        ];
                    }

                } catch (Exception $e) {
                    $errores[] = [
                        'archivo' => $nombre_original,
                        'motivo'  => 'Error leyendo PDF: ' . $e->getMessage()
                    ];
                }
            }

            // Limpiar carpeta temporal
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($temp_dir);

        } else {
            $errores[] = ['archivo' => $_FILES['zip_certificados']['name'], 'motivo' => 'No se pudo abrir el archivo ZIP.'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga masiva de certificados</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f3ff; min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 860px; margin: 0 auto; }
        .topbar {
            background: linear-gradient(135deg, #1a1433, #3b1fa8);
            color: white; padding: 14px 28px; border-radius: 12px;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 28px;
        }
        .topbar .brand { font-size: 17px; font-weight: 700; }
        .topbar a { color: #c4b5fd; text-decoration: none; font-size: 13px; }
        .topbar a:hover { color: white; }
        .card {
            background: white; border-radius: 12px; padding: 28px;
            margin-bottom: 24px; box-shadow: 0 2px 16px rgba(124,58,237,0.08);
        }
        .card h3 { color: #4c1d95; font-size: 17px; margin-bottom: 18px;
            padding-bottom: 10px; border-bottom: 2px solid #e2d9f3; }
        label { display: block; font-size: 13px; font-weight: 700; color: #1a1433;
            margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        input[type=file] { width: 100%; padding: 10px; border: 2px dashed #c4b5fd;
            border-radius: 10px; background: #faf8ff; font-size: 14px; }
        .btn { padding: 12px 28px; background: linear-gradient(135deg, #1a1433, #7c3aed);
            color: white; border: none; border-radius: 10px; font-size: 15px;
            font-weight: 700; cursor: pointer; margin-top: 16px; }
        .btn:hover { opacity: 0.9; }
        .info { background: #f5f3ff; border: 1px solid #c4b5fd; border-radius: 8px;
            padding: 14px 18px; font-size: 13px; color: #4c1d95; margin-bottom: 18px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f5f3ff; padding: 10px 12px; text-align: left;
            color: #4c1d95; font-weight: 700; border-bottom: 2px solid #e2d9f3; }
        td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; }
        .ok { color: #059669; font-weight: 700; }
        .err { color: #dc2626; font-weight: 700; }
        .badge-new { background: #d1fae5; color: #065f46; padding: 3px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-upd { background: #dbeafe; color: #1e40af; padding: 3px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 700; }
        .loading { display:none; text-align:center; padding: 20px; color: #6d28d9; font-weight: 700; }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div class="brand">🎓 Carga masiva de certificados</div>
        <a href="admin.php?tab=certificados">← Volver al admin</a>
    </div>

    <div class="card">
        <h3>📤 Subir certificados en ZIP</h3>
        <div class="info">
            📌 Comprime todos los PDFs en un solo archivo ZIP y súbelo aquí.<br>
            🔍 El sistema leerá automáticamente el número de cédula dentro de cada PDF y lo asociará al participante correcto.<br>
            ⚠️ Si un participante ya tenía certificado, será reemplazado por el nuevo.
        </div>
        <form method="POST" enctype="multipart/form-data" onsubmit="mostrarCargando()">
            <label>Archivo ZIP con todos los certificados PDF</label>
            <input type="file" name="zip_certificados" accept=".zip" required>
            <button type="submit" class="btn">🚀 Procesar ZIP</button>
        </form>
        <div class="loading" id="loading">
            ⏳ Procesando certificados, por favor espera... esto puede tomar unos minutos.
        </div>
    </div>

    <?php if (!empty($resultados)): ?>
    <div class="card">
        <h3 class="ok">✅ Certificados procesados correctamente (<?= count($resultados) ?>)</h3>
        <table>
            <thead>
                <tr><th>Archivo</th><th>Participante</th><th>Documento</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($resultados as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['archivo']) ?></td>
                    <td><?= htmlspecialchars($r['participante']) ?></td>
                    <td><?= htmlspecialchars($r['documento']) ?></td>
                    <td>
                        <?php if ($r['estado'] === 'Nuevo'): ?>
                            <span class="badge-new">✅ Nuevo</span>
                        <?php else: ?>
                            <span class="badge-upd">🔄 Actualizado</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
    <div class="card">
        <h3 class="err">⚠️ Archivos no procesados (<?= count($errores) ?>)</h3>
        <table>
            <thead>
                <tr><th>Archivo</th><th>Motivo</th></tr>
            </thead>
            <tbody>
            <?php foreach ($errores as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['archivo']) ?></td>
                    <td class="err"><?= htmlspecialchars($e['motivo']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
<script>
function mostrarCargando() {
    document.getElementById('loading').style.display = 'block';
}
</script>
</body>
</html>