<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['participante_id'])) {
    header('Location: index.php');
    exit;
}

// Already selected (back-button) or no pending multi-select → skip
if (isset($_SESSION['evento_id']) && !isset($_SESSION['eventos_disponibles'])) {
    header('Location: terms.php');
    exit;
}

$eventos = $_SESSION['eventos_disponibles'] ?? [];
if (empty($eventos)) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evento_id_sel = (int) ($_POST['evento_id'] ?? 0);
    $ids_validos   = array_column($eventos, 'id');
    if (!in_array($evento_id_sel, $ids_validos, true)) {
        $error = 'Selección no válida. Por favor elige un evento de la lista.';
    } else {
        $_SESSION['evento_id'] = $evento_id_sel;
        unset($_SESSION['eventos_disponibles']);
        $ip = $_SERVER['REMOTE_ADDR'];
        $pdo->prepare("INSERT INTO accesos (participante_id, accion, ip, evento_id) VALUES (?, 'login', ?, ?)")
            ->execute([$_SESSION['participante_id'], $ip, $evento_id_sel]);
        header('Location: terms.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar evento - F&C Consultores</title>
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
            margin-bottom: 28px;
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

        .logo-area h1 {
            font-size: 16px;
            font-weight: 600;
            color: #4c1d95;
            line-height: 1.5;
        }

        .nombre-usuario {
            font-size: 13px;
            color: #6b7280;
            margin-top: 6px;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #ddd6fe, transparent);
            margin-bottom: 24px;
        }

        .eventos-lista {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .evento-btn {
            width: 100%;
            padding: 14px 18px;
            background: #f5f3ff;
            border: 1.5px solid #ddd6fe;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #1a1433;
            cursor: pointer;
            text-align: left;
            font-family: 'Inter', sans-serif;
            transition: background 0.15s, border-color 0.15s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .evento-btn:hover {
            background: #ede9fe;
            border-color: #7c3aed;
            transform: translateY(-1px);
        }

        .evento-btn:active { transform: translateY(0); }

        .evento-btn .arrow { color: #7c3aed; flex-shrink: 0; }

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
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .footer {
            text-align: center;
            color: rgba(255,255,255,0.35);
            font-size: 12px;
            margin-top: 24px;
            letter-spacing: 0.3px;
        }

        @media (max-width: 480px) { .container { padding: 32px 24px; } }
    </style>
</head>
<body>

    <div class="container">
        <div class="logo-area">
            <div class="marca">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                </svg>
                F&amp;C Consultores
            </div>
            <h1>Selecciona el evento al que deseas ingresar</h1>
            <p class="nombre-usuario">👤 <?= htmlspecialchars($_SESSION['participante_nombre']) ?></p>
        </div>
        <div class="divider"></div>

        <?php if (!empty($error)): ?>
            <div class="error-msg">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="eventos-lista">
            <?php foreach ($eventos as $ev): ?>
            <button type="submit" name="evento_id" value="<?= (int) $ev['id'] ?>" class="evento-btn">
                <span><?= htmlspecialchars($ev['nombre']) ?></span>
                <svg class="arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>
            <?php endforeach; ?>
        </form>
    </div>

    <div class="footer">© F&C Consultores · Todos los derechos reservados</div>

</body>
</html>
