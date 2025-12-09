<?php
require_once 'utils.php';
$config = cargar_configuracion();
$user_ip = $_SERVER['REMOTE_ADDR'];
if (!$config || !usuario_ip_permitida($config, $user_ip)) {
    header("Location: index.php");
    exit;
}
$salas = obtener_salas($config);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Salas</title><link rel="stylesheet" href="style.css"></head>
<body>
<div style="max-width:650px;margin:4em auto;text-align:center;">
<img src="logo.svg" style="width:120px;">
<h2>Salas disponibles</h2>
<?php foreach ($salas as $s) echo "<div style='margin-bottom:1em;'><a href='index.php?sala={$s['clave']}'>{$s['nombre']}</a><br><small>".htmlspecialchars($s['titulo'])."</small></div>"; ?>
</div>
</body></html>