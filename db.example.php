<?php
// =============================================================================
//  PLANTILLA DE CONEXIÓN — recursosfyc
//
//  1. Copia este archivo: cp db.example.php db.php
//  2. Completa las credenciales reales en db.php
//  3. db.php está en .gitignore — NUNCA lo subas al repositorio
// =============================================================================

define('DB_HOST', 'localhost');       // Host de MySQL (usualmente localhost)
define('DB_NAME', 'recursosfyc');     // Nombre de la base de datos
define('DB_USER', '');                // Usuario de MySQL
define('DB_PASS', '');                // Contraseña de MySQL

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}
?>
