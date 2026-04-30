<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ── Seguridad ─────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

// ── Evento activo (misma lógica que admin.php) ────────────────────────────────
$todos_eventos = $pdo->query(
    "SELECT id, nombre, es_default FROM eventos WHERE activo = 1 ORDER BY id ASC"
)->fetchAll();

if (!isset($_SESSION['admin_evento_id'])) {
    foreach ($todos_eventos as $ev) {
        if ($ev['es_default']) {
            $_SESSION['admin_evento_id'] = (int) $ev['id'];
            break;
        }
    }
    if (!isset($_SESSION['admin_evento_id']) && !empty($todos_eventos)) {
        $_SESSION['admin_evento_id'] = (int) $todos_eventos[0]['id'];
    }
}
$admin_evento_id     = (int) ($_SESSION['admin_evento_id'] ?? 1);
$admin_evento_nombre = '';
foreach ($todos_eventos as $ev) {
    if ((int) $ev['id'] === $admin_evento_id) {
        $admin_evento_nombre = $ev['nombre'];
        break;
    }
}

// ── Filtros (espejo de la lógica de admin.php) ────────────────────────────────
$f_recurso      = isset($_GET['f_recurso'])      ? (int) $_GET['f_recurso']      : 0;
$f_participante = isset($_GET['f_participante']) ? (int) $_GET['f_participante'] : 0;
$f_estado       = in_array($_GET['f_estado'] ?? '', ['completado', 'en_progreso'], true)
                      ? $_GET['f_estado'] : '';

// ── Helper: segundos → m:ss ───────────────────────────────────────────────────
function xls_mmss(int $s): string {
    return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
}

// ── Query 1: métricas agregadas (solo filtro de evento, sin filtros de fila) ──
$m_stmt = $pdo->prepare("
    SELECT
        COUNT(*)                                AS total_visualizaciones,
        COUNT(DISTINCT vv.participante_id)      AS total_participantes,
        SUM(vv.completed = 1)                   AS total_completados,
        ROUND(AVG(vv.percent_watched) * 100, 1) AS promedio_porcentaje
    FROM video_visualizaciones vv
    JOIN recursos r ON r.id = vv.recurso_id
    WHERE r.evento_id = ?
");
$m_stmt->execute([$admin_evento_id]);
$metrics = $m_stmt->fetch(PDO::FETCH_ASSOC);

// ── Query 2: detalle de visualizaciones (respeta los 3 filtros) ───────────────
$d_where  = ['r.evento_id = ?'];
$d_params = [$admin_evento_id];

if ($f_recurso > 0)                  { $d_where[] = 'vv.recurso_id = ?';      $d_params[] = $f_recurso; }
if ($f_participante > 0)             { $d_where[] = 'vv.participante_id = ?'; $d_params[] = $f_participante; }
if ($f_estado === 'completado')      { $d_where[] = 'vv.completed = 1'; }
elseif ($f_estado === 'en_progreso') { $d_where[] = 'vv.completed = 0'; }

$d_stmt = $pdo->prepare("
    SELECT
        p.nombre          AS p_nombre,
        p.documento       AS p_documento,
        r.nombre          AS r_nombre,
        vv.started_at,
        vv.last_seen_at,
        vv.seconds_watched,
        vv.video_duration,
        vv.percent_watched,
        vv.completed
    FROM video_visualizaciones vv
    JOIN participantes p ON p.id = vv.participante_id
    JOIN recursos r      ON r.id = vv.recurso_id
    WHERE " . implode(' AND ', $d_where) . "
    ORDER BY vv.last_seen_at DESC, vv.started_at DESC
");
$d_stmt->execute($d_params);
$detail_rows = $d_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Query 3: resumen por video (solo filtro de evento) ────────────────────────
$v_stmt = $pdo->prepare("
    SELECT
        r.nombre                                AS video_nombre,
        COUNT(*)                                AS visualizaciones,
        COUNT(DISTINCT vv.participante_id)      AS participantes_unicos,
        SUM(vv.completed = 1)                   AS completados,
        ROUND(AVG(vv.percent_watched) * 100, 1) AS promedio_porcentaje
    FROM video_visualizaciones vv
    JOIN recursos r ON r.id = vv.recurso_id
    WHERE r.evento_id = ?
    GROUP BY r.id, r.nombre
    ORDER BY r.nombre ASC
");
$v_stmt->execute([$admin_evento_id]);
$video_rows = $v_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Paleta corporativa ────────────────────────────────────────────────────────
const C_HDR_BG   = 'FF1A1433'; // fondo encabezado — púrpura oscuro
const C_HDR_FG   = 'FFFFFFFF'; // texto encabezado — blanco
const C_TITLE_BG = 'FF3B1FA8'; // fondo título     — púrpura medio
const C_COMPLETE = 'FFD1FAE5'; // completado        — verde suave
const C_PROGRESS = 'FFFEF9C3'; // en progreso       — amarillo suave
const C_ROW_ALT  = 'FFF5F3FF'; // fila alternada    — lavanda
const C_BORDER   = 'FFDDDDDD'; // borde fino        — gris claro

// ── Spreadsheet ───────────────────────────────────────────────────────────────
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$spreadsheet->getProperties()
    ->setTitle('Informe de Videos — ' . $admin_evento_nombre)
    ->setCreator('F&C Consultores')
    ->setCompany('F&C Consultores');

// ══════════════════════════════════════════════════════════════════════════════
// HOJA 1 — Resumen ejecutivo
// ══════════════════════════════════════════════════════════════════════════════
$s1 = $spreadsheet->getActiveSheet()->setTitle('Resumen');

// Fila 1 — título principal (A1:F1 combinadas)
$s1->mergeCells('A1:F1');
$s1->setCellValue('A1', 'Informe de Visualización de Videos');
$s1->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 18,
                    'color' => ['argb' => C_HDR_FG]],
    'fill'      => ['fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => C_TITLE_BG]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER],
]);
$s1->getRowDimension(1)->setRowHeight(44);

// Fila 2 — nombre del evento (A2:F2 combinadas)
$s1->mergeCells('A2:F2');
$s1->setCellValue('A2', $admin_evento_nombre);
$s1->getStyle('A2')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 13,
                    'color' => ['argb' => C_TITLE_BG]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER],
]);
$s1->getRowDimension(2)->setRowHeight(28);

// Fila 3 — fecha de generación (A3:F3 combinadas)
$s1->mergeCells('A3:F3');
$s1->setCellValue('A3', 'Generado el ' . date('d/m/Y \a \l\a\s H:i'));
$s1->getStyle('A3')->applyFromArray([
    'font'      => ['italic' => true, 'size' => 10,
                    'color' => ['argb' => 'FF6B7280']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$s1->getRowDimension(3)->setRowHeight(18);

// Fila 4 — separador visual
$s1->getRowDimension(4)->setRowHeight(10);

// Fila 5 — encabezados tabla de métricas
$s1->setCellValue('A5', 'Métrica');
$s1->setCellValue('B5', 'Valor');
$s1->getStyle('A5:B5')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 11,
                    'color' => ['argb' => C_HDR_FG]],
    'fill'      => ['fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => C_HDR_BG]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                     'color' => ['argb' => C_BORDER]]],
]);
$s1->getRowDimension(5)->setRowHeight(22);

// Filas 6–9 — valores de métricas
$metric_data = [
    ['👥  Participantes únicos',  number_format((int) ($metrics['total_participantes']   ?? 0))],
    ['🎥  Total visualizaciones', number_format((int) ($metrics['total_visualizaciones'] ?? 0))],
    ['✅  Videos completados',     number_format((int) ($metrics['total_completados']     ?? 0))],
    ['📊  Promedio visto',         number_format((float) ($metrics['promedio_porcentaje'] ?? 0), 1) . '%'],
];
foreach ($metric_data as $idx => [$label, $value]) {
    $r  = 6 + $idx;
    $bg = ($idx % 2 === 0) ? 'FFFAFBFF' : C_ROW_ALT;
    $s1->setCellValue('A' . $r, $label);
    $s1->setCellValue('B' . $r, $value);
    $s1->getStyle('A' . $r . ':B' . $r)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $bg]],
        'font'      => ['size' => 11],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                         'color' => ['argb' => C_BORDER]]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $s1->getStyle('B' . $r)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $s1->getRowDimension($r)->setRowHeight(22);
}

// Anchos Hoja 1
$s1->getColumnDimension('A')->setWidth(32);
$s1->getColumnDimension('B')->setWidth(22);
foreach (['C', 'D', 'E', 'F'] as $c) {
    $s1->getColumnDimension($c)->setWidth(12);
}

// ══════════════════════════════════════════════════════════════════════════════
// HOJA 2 — Detalle de visualizaciones
// ══════════════════════════════════════════════════════════════════════════════
$s2 = $spreadsheet->createSheet()->setTitle('Detalle');

$d_headers = [
    'Participante', 'Documento', 'Video', 'Fecha inicio',
    'Última actividad', 'Tiempo visto', 'Duración del video',
    'Porcentaje visto', 'Estado',
];
foreach ($d_headers as $ci => $label) {
    $s2->setCellValue(Coordinate::stringFromColumnIndex($ci + 1) . '1', $label);
}
$s2->getStyle('A1:I1')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['argb' => C_HDR_FG]],
    'fill'      => ['fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => C_HDR_BG]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                     'color' => ['argb' => 'FFAAAAAA']]],
]);
$s2->getRowDimension(1)->setRowHeight(24);
$s2->freezePane('A2');
$s2->setAutoFilter('A1:I1');

foreach ($detail_rows as $i => $row) {
    $r          = $i + 2;
    $completado = (bool) $row['completed'];
    $bg_row     = ($i % 2 === 0) ? 'FFFFFFFF' : 'FFFAFBFF';
    $bg_estado  = $completado ? C_COMPLETE : C_PROGRESS;

    $s2->setCellValue('A' . $r, $row['p_nombre']);
    $s2->setCellValue('B' . $r, $row['p_documento']);
    $s2->setCellValue('C' . $r, $row['r_nombre']);
    $s2->setCellValue('D' . $r, $row['started_at']
        ? date('d/m/Y H:i', strtotime($row['started_at'])) : '');
    $s2->setCellValue('E' . $r, $row['last_seen_at']
        ? date('d/m/Y H:i', strtotime($row['last_seen_at'])) : '');
    $s2->setCellValue('F' . $r, xls_mmss((int) $row['seconds_watched']));
    $s2->setCellValue('G' . $r, $row['video_duration'] > 0
        ? xls_mmss((int) $row['video_duration']) : '');
    $s2->setCellValue('H' . $r, round((float) $row['percent_watched'] * 100, 1) . '%');
    $s2->setCellValue('I' . $r, $completado ? 'Completado' : 'En progreso');

    // Columnas A–H: fondo alternado + borde
    $s2->getStyle('A' . $r . ':H' . $r)->applyFromArray([
        'fill'    => ['fillType' => Fill::FILL_SOLID,
                      'startColor' => ['argb' => $bg_row]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                       'color' => ['argb' => C_BORDER]]],
        'font'    => ['size' => 10],
    ]);
    // Columna I — Estado con color semántico
    $s2->getStyle('I' . $r)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $bg_estado]],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                         'color' => ['argb' => C_BORDER]]],
        'font'      => ['bold' => true, 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
}

foreach (range('A', 'I') as $col) {
    $s2->getColumnDimension($col)->setAutoSize(true);
}

// ══════════════════════════════════════════════════════════════════════════════
// HOJA 3 — Resumen por video
// ══════════════════════════════════════════════════════════════════════════════
$s3 = $spreadsheet->createSheet()->setTitle('Videos');

$v_headers = ['Video', 'Visualizaciones', 'Participantes únicos', 'Completados', 'Promedio visto'];
foreach ($v_headers as $ci => $label) {
    $s3->setCellValue(Coordinate::stringFromColumnIndex($ci + 1) . '1', $label);
}
$s3->getStyle('A1:E1')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['argb' => C_HDR_FG]],
    'fill'      => ['fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => C_HDR_BG]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                     'color' => ['argb' => 'FFAAAAAA']]],
]);
$s3->getRowDimension(1)->setRowHeight(24);
$s3->freezePane('A2');
$s3->setAutoFilter('A1:E1');

foreach ($video_rows as $i => $row) {
    $r  = $i + 2;
    $bg = ($i % 2 === 0) ? 'FFFFFFFF' : C_ROW_ALT;

    $s3->setCellValue('A' . $r, $row['video_nombre']);
    $s3->setCellValue('B' . $r, (int) $row['visualizaciones']);
    $s3->setCellValue('C' . $r, (int) $row['participantes_unicos']);
    $s3->setCellValue('D' . $r, (int) $row['completados']);
    $s3->setCellValue('E' . $r, round((float) $row['promedio_porcentaje'], 1) . '%');

    $s3->getStyle('A' . $r . ':E' . $r)->applyFromArray([
        'fill'    => ['fillType' => Fill::FILL_SOLID,
                      'startColor' => ['argb' => $bg]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                       'color' => ['argb' => C_BORDER]]],
        'font'    => ['size' => 10],
    ]);
    $s3->getStyle('B' . $r . ':E' . $r)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

foreach (range('A', 'E') as $col) {
    $s3->getColumnDimension($col)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

// ── Descarga ──────────────────────────────────────────────────────────────────
$safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $admin_evento_nombre);
$filename  = 'analytics_videos_' . $safe_name . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
