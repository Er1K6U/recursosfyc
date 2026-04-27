<?php
$base = __DIR__ . '/libs/pdfparser-master/src/';
echo '<pre>';
echo "Carpeta existe: " . (is_dir($base) ? 'SÍ' : 'NO') . "\n";
echo "Archivos en /libs:\n";
$archivos = scandir(__DIR__ . '/libs/');
foreach ($archivos as $a) echo "  - $a\n";
echo "\nArchivos en /libs/pdfparser-master/src/Smalot/PdfParser/:\n";
$ruta = __DIR__ . '/libs/pdfparser-master/src/Smalot/PdfParser/';
if (is_dir($ruta)) {
    $archivos2 = scandir($ruta);
    foreach ($archivos2 as $a) echo "  - $a\n";
} else {
    echo "  ❌ Carpeta no existe\n";
}
echo '</pre>';
?>