<?php
require_once 'utils.php';
session_start();

function is_logged_in() { return isset($_SESSION['admin']) && $_SESSION['admin'] === true; }

if (isset($_POST['clave'])) {
    $cfg = cargar_configuracion();
    if ($cfg && trim($_POST['clave']) === trim($cfg['GENERAL']['contraseña'])) {
        $_SESSION['admin'] = true;
    } else {
        $error = "Clave incorrecta.";
    }
}

if (is_logged_in() && isset($_POST['nconfig'])) {
    if (guardar_configuracion($_POST['nconfig'])) {
        header("Location: config.php?saved=1");
        exit;
    } else { $error = "Error al guardar la configuración."; }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    session_destroy();
    header("Location: config.php");
    exit;
}

$backups = glob(BACKUP_DIR . "config_*.txt");
$backups = $backups ? array_reverse($backups) : [];
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Configuración Intercom</title><link rel="stylesheet" href="style.css"></head>
<body>
<div style="max-width:800px;margin:2em auto;padding:2em;background:#fff;border-radius:12px;">
<img src="logo.svg" style="width:120px;">
<h2>Administración</h2>
<?php if (!is_logged_in()): ?>
<form method="post"><label>Contraseña:</label><input type="password" name="clave"><button type="submit">Acceder</button></form>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php else: ?>
<form method="post">
<label>Editar config.txt:</label><br>
<textarea name="nconfig" style="width:100%;height:300px;"><?php echo htmlspecialchars(file_get_contents(CONFIG_FILE)); ?></textarea><br>
<button type="submit">Guardar</button>
<a href="config.php?logout=1">Salir</a>
</form>
<hr>
<h3>Backups</h3>
<form method="post">
<select name="restore"><?php foreach($backups as $b) echo "<option value='".htmlspecialchars($b)."'>".basename($b)."</option>"; ?></select>
<button type="submit" name="restore" value="1">Restaurar</button>
</form>
<?php endif; ?>
</div>
</body>
</html>