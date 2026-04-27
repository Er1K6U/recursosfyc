<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['participante_id']) && isset($_SESSION['terminos_aceptados'])) {
    header('Location: portal.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documento = trim($_POST['documento'] ?? '');

    if (empty($documento)) {
        $error = 'Por favor ingresa tu número de documento.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM participantes WHERE documento = ? AND activo = 1");
        $stmt->execute([$documento]);
        $participante = $stmt->fetch();

        if ($participante) {
            $_SESSION['participante_id'] = $participante['id'];
            $_SESSION['participante_nombre'] = $participante['nombre'];
            $_SESSION['participante_documento'] = $participante['documento'];

            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt2 = $pdo->prepare("INSERT INTO accesos (participante_id, accion, ip) VALUES (?, 'login', ?)");
            $stmt2->execute([$participante['id'], $ip]);

            header('Location: terms.php');
            exit;
        } else {
            $error = 'Documento no encontrado o no autorizado. Verifica e intenta de nuevo.';
        }
    }
}

$stmt = $pdo->query("SELECT clave, valor FROM configuracion");
$config = [];
while ($row = $stmt->fetch()) {
    $config[$row['clave']] = $row['valor'];
}
$banner = $config['banner'] ?? '';
$titulo = $config['titulo_evento'] ?? 'Diplomado en Gestión Integral de Riesgos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso a Recursos - F&C Consultores</title>
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
            justify-content: center;
            padding: 24px 16px;
        }

        .banner-wrap {
            width: 100%;
            max-width: 480px;
            margin-bottom: 24px;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(124,58,237,0.4);
        }

        .banner-wrap img { width: 100%; display: block; }

        .container {
            background: #ffffff;
            border-radius: 20px;
            padding: 44px 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 24px 80px rgba(124,58,237,0.25), 0 4px 16px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6d28d9, #a855f7, #6d28d9);
        }

        .logo-area {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-area .marca {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #1a1433, #3b1fa8);
            color: white;
            padding: 8px 20px;
            border-radius: 99px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }

        .logo-area .marca svg { opacity: 0.85; }

        .logo-area h1 {
            font-size: 16px;
            font-weight: 600;
            line-height: 1.5;
            color: #4c1d95;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #ddd6fe, transparent);
            margin-bottom: 28px;
        }

        .field-group { margin-bottom: 8px; }

        label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        label svg { color: #7c3aed; }

        input[type="text"] {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            color: #111827;
            background: #fafafa;
        }

        input[type="text"]:focus {
            border-color: #7c3aed;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(124,58,237,0.12);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1a1433 0%, #7c3aed 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            margin-top: 20px;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(124,58,237,0.35);
        }

        .btn:hover {
            opacity: 0.93;
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(124,58,237,0.45);
        }

        .btn:active { transform: translateY(0); }

        .error-msg {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #fdf4ff;
            color: #6d28d9;
            border: 1px solid #e9d5ff;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13.5px;
            margin-top: 16px;
            line-height: 1.5;
        }

        .error-msg svg { flex-shrink: 0; margin-top: 1px; }

        .footer {
            text-align: center;
            color: rgba(255,255,255,0.35);
            font-size: 12px;
            margin-top: 24px;
            letter-spacing: 0.3px;
        }

        @media (max-width: 480px) {
            .container { padding: 32px 24px; }
        }
    </style>
</head>
<body>

    <?php if (!empty($banner)): ?>
        <div class="banner-wrap">
            <img src="uploads/<?= htmlspecialchars($banner) ?>" alt="Banner del evento">
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="logo-area">
            <div class="marca">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                F&amp;C Consultores
            </div>
            <h1><?= htmlspecialchars($titulo) ?></h1>
        </div>
        <div class="divider"></div>

        <form method="POST">
            <div class="field-group">
                <label for="documento">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Número de documento de identidad
                </label>
                <input
                    type="text"
                    id="documento"
                    name="documento"
                    placeholder="Ej: 1234567890"
                    autocomplete="off"
                    autofocus
                >
            </div>
            <?php if (!empty($error)): ?>
                <div class="error-msg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn">
                Ingresar a los recursos
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>
    </div>

    <div class="footer">© F&C Consultores · Todos los derechos reservados</div>

</body>
</html>