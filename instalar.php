<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
header('Content-Type: text/html; charset=utf-8');
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

try {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    exit('No se pudo conectar a MySQL. Revisa los datos de config.php.');
}
try { $instalado = (int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0; } catch (Throwable $e) { $instalado = false; }
if ($instalado) exit('SIRBE ya está instalado. Borra instalar.php del servidor.');

$msg = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $pass = $_POST['password'] ?? '';
    if (!hash_equals(INSTALL_KEY, (string)($_POST['clave'] ?? ''))) $msg = 'La clave de instalación no coincide con config.php.';
    elseif ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $msg = 'Escribe un nombre y un correo válido.';
    elseif (strlen($pass) < 10) $msg = 'La contraseña debe tener al menos 10 caracteres.';
    else {
        try {
            foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__ . '/schema.sql')))) as $sql) $db->exec($sql);
            $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'admin')")
               ->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $ok = true;
        } catch (Throwable $e) { error_log('SIRBE instalar: ' . $e->getMessage()); $msg = 'Error al crear las tablas. Revisa el registro de errores del hosting.'; }
    }
}
?><!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalar SIRBE</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:420px;margin:8vh auto;padding:0 1rem;color:#1d2a33}
label{display:block;font-weight:700;margin:.8rem 0 .25rem}input{width:100%;padding:.5rem;border:1px solid #bbb;border-radius:6px;font:inherit}
button{margin-top:1rem;background:#1e5b4f;color:#fff;border:0;border-radius:6px;padding:.6rem 1rem;font:inherit;font-weight:700;cursor:pointer}.e{color:#a8322d;font-weight:700}</style></head><body>
<?php if ($ok): ?>
<h1>Instalación lista</h1><p>Las tablas y el administrador se crearon.</p>
<p class="e">Ahora borra <b>instalar.php</b> del servidor y entra al portal.</p><p><a href="index.html">Ir al portal</a></p>
<?php else: ?>
<h1>Instalar SIRBE</h1><p>Crea las tablas y el usuario administrador.</p>
<?php if ($msg): ?><p class="e" role="alert"><?= $h($msg) ?></p><?php endif; ?>
<form method="post">
<label for="c">Clave de instalación (la de config.php)</label><input id="c" name="clave" type="password" required>
<label for="n">Nombre del administrador</label><input id="n" name="nombre" required value="<?= $h($_POST['nombre'] ?? '') ?>">
<label for="e">Correo</label><input id="e" name="email" type="email" required value="<?= $h($_POST['email'] ?? '') ?>">
<label for="p">Contraseña (mínimo 10 caracteres)</label><input id="p" name="password" type="password" minlength="10" required>
<button>Instalar</button></form>
<?php endif; ?></body></html>
