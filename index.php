<?php
require_once 'utils.php';
$config = cargar_configuracion();
$user_ip = $_SERVER['REMOTE_ADDR'];
$salas = obtener_salas($config);
$sala_actual = isset($_GET['sala']) ? $_GET['sala'] : (count($salas) ? $salas[0]['clave'] : 'SALA_1');

if (!$config || !usuario_ip_permitida($config, $user_ip)) {
    echo "<html><body style='text-align:center;padding:5em;background:#cccccc;font-family:Open Sans,Arial'><h2>ACCESO DENEGADO</h2><p>Tu equipo ($user_ip) no está registrado.</p></body></html>";
    exit;
}

$nombre_usuario = obtener_usuario_por_ip($config, $user_ip, $sala_actual);

// Generar token HMAC
$secret = $config['GENERAL']['contraseña'] ?? '';
$token = '';
if ($secret !== '') {
    $token = hash_hmac('sha256', $user_ip . '|' . $sala_actual, $secret);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?php echo "INTERCOM " . htmlspecialchars($sala_actual); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <script>
    window.__salaActivo = "<?php echo htmlspecialchars($sala_actual); ?>";
    window.__miIP = "<?php echo htmlspecialchars($user_ip); ?>";
    window.__miUser = "<?php echo htmlspecialchars($nombre_usuario); ?>";
    window.INTERCOM_TOKEN = "<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>";
    window.INTERCOM_CONFIG = {
      signalingUrl: "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'wss' : 'ws'; ?>://<?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/intercom/ws",
      iceServers: [] 
    };
  </script>
</head>
<body style="background:#cccccc;">
<div id="header">
  <img src="logo.svg" alt="Logo" style="width:200px;">
  <h1><?php echo htmlspecialchars($config['GENERAL']['titulo'] ?? 'Intercom'); ?></h1>
  <div style="margin-left:auto;">
    <img src="altavoz.png" id="audio_config" title="Configurar audio" style="width:40px;cursor:pointer;margin-right:10px;">
    <a href="config.php"><img src="conf.png" title="Configuración" style="width:40px;"></a>
  </div>
</div>

<div id="salas">
<?php foreach ($salas as $s) {
  echo "<a class='sala-tab' href='?sala={$s['clave']}'" . ($s['clave']==$sala_actual?" style='font-weight:bold;'":"") . ">".htmlspecialchars($s['nombre'])."</a> ";
} ?>
</div>

<div class="botonera">
<?php
echo "<button class='icombtn green' data-conf='1' data-ip='ALL' data-user='CONFERENCIA'>CONFER</button>";
for ($i=1; $i<=40; $i++) {
    $b_key = "boton_$i";
    if (isset($config[$sala_actual][$b_key]) && $config[$sala_actual][$b_key]) {
        list($nombre,$ip) = explode(',',$config[$sala_actual][$b_key]);
        $nombre = trim($nombre);
        $nombre_btn = substr($nombre,0,6);
        $estado = "gray";
        // presence color will be set by JS class or server-side presence check
        echo "<button class='icombtn $estado' data-ip='".htmlspecialchars($ip)."' data-user='".htmlspecialchars($nombre)."'>$nombre_btn</button>";
    } else {
        echo "<button class='icombtn gray' data-index='$i'>BTN$i</button>";
    }
    if ($i%8==0) echo "<br>";
}
?>
</div>

<div id="audios"><h4>Remote Audio Streams</h4></div>

<div>
  <button id="btnStart">Start (allow mic)</button>
  <textarea id="log" readonly style="width:100%;height:200px"></textarea>
</div>

<script src="audio.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const altavoz = document.getElementById('audio_config');
  if (altavoz) altavoz.addEventListener('click', async () => {
    const devices = await navigator.mediaDevices.enumerateDevices();
    // Simple modal: store ids
    let entrada = prompt('DeviceId entrada (vacío = predeterminado). Disp:\n' + devices.filter(d=>d.kind==='audioinput').map(d=>d.deviceId+' - '+(d.label||'sin label')).join('\n'));
    if (entrada !== null) localStorage.setItem('audioIn', entrada);
  });
});
</script>
</body>
</html>