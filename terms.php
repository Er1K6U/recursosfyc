<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['participante_id'])) {
    header('Location: index.php');
    exit;
}

$evento_id = (int) ($_SESSION['evento_id'] ?? 1);

// Verificar si ya firmó antes para este evento
$stmt = $pdo->prepare("SELECT id FROM accesos WHERE participante_id = ? AND accion = 'acepto_terminos' AND evento_id = ? LIMIT 1");
$stmt->execute([$_SESSION['participante_id'], $evento_id]);
$ya_firmo = $stmt->fetch();

if ($ya_firmo) {
    $_SESSION['terminos_aceptados'] = true;
    header('Location: portal.php');
    exit;
}

if (isset($_SESSION['terminos_aceptados'])) {
    header('Location: portal.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firma = $_POST['firma'] ?? '';
    $acepta = $_POST['acepta'] ?? '';

    if (empty($acepta)) {
        $error = 'Debes aceptar los términos para continuar.';
    } elseif (empty($firma) || $firma === 'data:,') {
        $error = 'Debes firmar para continuar.';
    } else {
        $_SESSION['terminos_aceptados'] = true;

        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("INSERT INTO accesos (participante_id, accion, firma_imagen, ip, evento_id) VALUES (?, 'acepto_terminos', ?, ?, ?)");
        $stmt->execute([$_SESSION['participante_id'], $firma, $ip, $evento_id]);

        header('Location: portal.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos de Uso - F&C Consultores</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #1a1433 0%, #2d1b69 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            max-width: 780px;
            margin: 0 auto;
            box-shadow: 0 8px 40px rgba(124,58,237,0.25);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1a1433, #3b1fa8);
            color: white;
            padding: 28px 36px;
            text-align: center;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -1px; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #7c3aed, #a855f7, #7c3aed);
        }

        .header h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .header p {
            font-size: 13px;
            opacity: 0.8;
            margin-top: 6px;
            color: #c4b5fd;
        }

        .body {
            padding: 32px 36px;
        }

        .section-title {
            background: linear-gradient(135deg, #1a1433, #3b1fa8);
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
        }

        .terms-box {
            border: 1px solid #e2d9f3;
            border-radius: 10px;
            padding: 24px;
            font-size: 14px;
            line-height: 1.8;
            color: #333;
            max-height: 320px;
            overflow-y: auto;
            background: #faf8ff;
            margin-bottom: 24px;
        }

        .terms-box::-webkit-scrollbar { width: 6px; }
        .terms-box::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        .terms-box::-webkit-scrollbar-thumb { background: #7c3aed; border-radius: 3px; }

        .terms-box p { margin-bottom: 14px; }

        .terms-box .highlight {
            color: #6d28d9;
            font-weight: 700;
        }

        .terms-box .alert {
            color: #5b21b6;
            font-weight: 700;
            text-align: center;
            border-top: 2px solid #e2d9f3;
            padding-top: 14px;
            margin-top: 14px;
        }

        .firma-section {
            margin-bottom: 24px;
        }

        .firma-section label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: #1a1433;
            margin-bottom: 10px;
        }

        #firma-canvas {
            border: 2px dashed #7c3aed;
            border-radius: 10px;
            display: block;
            width: 100%;
            height: 160px;
            background: #faf8ff;
            cursor: crosshair;
            touch-action: none;
        }

        .firma-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-limpiar {
            padding: 8px 20px;
            background: #f3f0ff;
            border: 1px solid #c4b5fd;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            color: #6d28d9;
        }

        .btn-limpiar:hover { background: #ede9fe; }

        .acepta-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 24px;
            background: #f5f3ff;
            border: 1px solid #c4b5fd;
            border-radius: 10px;
            padding: 16px;
        }

        .acepta-row input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-top: 2px;
            cursor: pointer;
            accent-color: #7c3aed;
            flex-shrink: 0;
        }

        .acepta-row label {
            font-size: 14px;
            color: #1a1433;
            line-height: 1.5;
            cursor: pointer;
        }

        .btn-continuar {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #1a1433, #7c3aed);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.3s;
            letter-spacing: 0.5px;
        }

        .btn-continuar:hover { opacity: 0.9; }

        .error-msg {
            background: #fdf4ff;
            color: #7c3aed;
            border: 1px solid #c4b5fd;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .participante-info {
            background: #f5f3ff;
            border: 1px solid #c4b5fd;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 14px;
            color: #4c1d95;
            margin-bottom: 24px;
        }

        @media (max-width: 480px) {
            .body { padding: 20px 16px; }
            .header { padding: 20px 16px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>USO DE LA HERRAMIENTA</h1>
        <p>F&amp;C Consultores · Diplomado en Gestión Integral de Riesgos</p>
    </div>

    <div class="body">

        <div class="participante-info">
            👤 <strong><?= htmlspecialchars($_SESSION['participante_nombre']) ?></strong>
            &nbsp;|&nbsp; Documento: <?= htmlspecialchars($_SESSION['participante_documento']) ?>
        </div>

        <div class="section-title">¿Qué debo tener en cuenta sobre la herramienta?</div>

        <div class="terms-box">
            <p>Esta herramienta ha sido diseñada por <strong class="highlight">F&amp;C Consultores</strong> con propósitos académicos, como parte del material exclusivo del <strong>Diplomado en Gestión Integral de Riesgos en el Sector Público Colombiano.</strong></p>

            <p>Su diseño toma como referente las normas vigentes a la fecha de elaboración, entre otros: el Marco COSO-ERM 2017, la norma ISO 31000:2018, la Guía para la Gestión Integral de Riesgos en Entidades Públicas Versión 7 del DAFP (septiembre 2025), el Manual Operativo del MIPG Versión 6, la Guía de la Secretaría de Transparencia para la Gestión del Riesgo de Corrupción, la Ley 2195 de 2022 y el Decreto 1122 de 2024 en relación con el Programa de Transparencia y Ética Pública —PTEP— y el Sistema de Gestión de Riesgos para la Integridad Pública —SIGRIP—, así como normas sectoriales en materia de prevención y gestión de riesgos en Colombia y referentes internacionales.</p>

            <p class="highlight">La herramienta está lista para usar y ha sido diseñada para ser personalizada: el participante puede ajustarla, adaptarla y mejorarla según las particularidades de su entidad.</p>

            <p class="alert">
                ⚠️ Importante: USO EXCLUSIVO DE PARTICIPANTES DEL DIPLOMADO:<br><br>
                Esta herramienta es un beneficio exclusivo para quienes hacen parte de este programa académico.<br><br>
                <strong>Queda expresamente prohibida su distribución a terceros, su publicación o carga en plataformas digitales o de internet, su presentación como herramienta propia o de autoría diferente a F&amp;C Consultores, y su traslado o uso en cualquier otro escenario académico, formativo o comercial distinto al presente Diplomado.</strong><br><br>
                Su uso indebido constituye una violación a los derechos de propiedad intelectual de F&amp;C Consultores.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="form-terminos">
            <input type="hidden" name="firma" id="firma-data">

            <div class="firma-section">
                <label>✍️ Firma aquí para confirmar que leíste y aceptas los términos:</label>
                <canvas id="firma-canvas"></canvas>
                <div class="firma-actions">
                    <button type="button" class="btn-limpiar" onclick="limpiarFirma()">🗑️ Limpiar firma</button>
                </div>
            </div>

            <div class="acepta-row">
                <input type="checkbox" id="acepta" name="acepta" value="1">
                <label for="acepta">
                    He leído y acepto los términos de uso de esta herramienta. Entiendo que es de uso exclusivo para participantes del Diplomado y me comprometo a no distribuirla ni compartirla con terceros.
                </label>
            </div>

            <button type="submit" class="btn-continuar" onclick="return prepararFirma()">
                Acepto y quiero acceder a los recursos →
            </button>
        </form>
    </div>
</div>

<script>
    const canvas = document.getElementById('firma-canvas');
    const ctx = canvas.getContext('2d');
    let dibujando = false;
    let hayFirma = false;

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function getPosicion(e) {
        const rect = canvas.getBoundingClientRect();
        if (e.touches) {
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top
            };
        }
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }

    canvas.addEventListener('mousedown', (e) => {
        dibujando = true;
        const pos = getPosicion(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        ctx.strokeStyle = '#1a1433';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    });

    canvas.addEventListener('mousemove', (e) => {
        if (!dibujando) return;
        const pos = getPosicion(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        hayFirma = true;
    });

    canvas.addEventListener('mouseup', () => { dibujando = false; });
    canvas.addEventListener('mouseleave', () => { dibujando = false; });

    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        dibujando = true;
        const pos = getPosicion(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        ctx.strokeStyle = '#1a1433';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }, { passive: false });

    canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        if (!dibujando) return;
        const pos = getPosicion(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        hayFirma = true;
    }, { passive: false });

    canvas.addEventListener('touchend', () => { dibujando = false; });

    function limpiarFirma() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hayFirma = false;
    }

    function prepararFirma() {
        if (!hayFirma) {
            alert('Por favor firma antes de continuar.');
            return false;
        }
        document.getElementById('firma-data').value = canvas.toDataURL('image/png');
        return true;
    }
</script>
</body>
</html>