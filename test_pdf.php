<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

echo '<pre>';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {
    $tmp = $_FILES['pdf']['tmp_name'];
    echo "Archivo recibido: " . $_FILES['pdf']['name'] . "\n";
    echo "Tamaño: " . $_FILES['pdf']['size'] . " bytes\n";

    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($tmp);
        $texto = $pdf->getText();
        echo "✅ PDF leído correctamente\n\n";
        echo "Texto extraído:\n";
        echo htmlspecialchars(substr($texto, 0, 1000));
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString();
    }
} else {
    echo "Sube un PDF de prueba:\n";
    echo '</pre>';
    ?>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="pdf" accept=".pdf">
        <button type="submit">Probar</button>
    </form>
    <?php
}
echo '</pre>';
?>