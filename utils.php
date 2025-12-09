<?php
define('CONFIG_FILE', __DIR__.'/config.txt');
define('BACKUP_DIR', __DIR__.'/backups/');

function cargar_configuracion() {
    if (!file_exists(CONFIG_FILE)) return false;
    $seccion = '';
    $data = [];
    foreach (file(CONFIG_FILE) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (preg_match('/^\[(.+)\]$/', $line, $m)) {
            $seccion = $m[1];
            $data[$seccion] = [];
        } else {
            if ($seccion) {
                $parts = explode('=', $line, 2);
                if (count($parts) == 2) {
                    $k = trim($parts[0]);
                    $v = trim($parts[1]);
                    $data[$seccion][$k] = $v;
                }
            }
        }
    }
    return $data;
}

function guardar_configuracion($new_config_txt) {
    if (!file_exists(CONFIG_FILE)) return false;
    if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0755, true);
    $backup_name = BACKUP_DIR . 'config_' . date('Y-m-d_His') . '.txt';
    copy(CONFIG_FILE, $backup_name);
    return file_put_contents(CONFIG_FILE, $new_config_txt);
}

function obtener_salas($config) {
    $salas = [];
    foreach ($config as $seccion => $datos) {
        if (preg_match('/^SALA_\d+$/', $seccion)) {
            $salas[] = [
                'clave' => $seccion,
                'nombre' => isset($datos['nombre']) ? $datos['nombre'] : '',
                'titulo' => isset($datos['titulo']) ? $datos['titulo'] : '',
                'botones' => array_filter($datos, function($k){return strpos($k, 'boton_')===0;}, ARRAY_FILTER_USE_KEY),
            ];
        }
    }
    return $salas;
}

function usuario_ip_permitida($config, $ip) {
    foreach (obtener_salas($config) as $sala) {
        foreach ($sala['botones'] as $btn) {
            $data = explode(',', $btn);
            if (count($data)==2 && trim($data[1])==$ip) return true;
        }
    }
    return false;
}

function obtener_usuario_por_ip($config, $ip, $sala_clave) {
    if (isset($config[$sala_clave])) {
        foreach ($config[$sala_clave] as $k => $v) {
            if (strpos($k, 'boton_')===0 && $v) {
                $data = explode(',', $v);
                if (count($data)==2 && trim($data[1])==$ip) return trim($data[0]);
            }
        }
    }
    return null;
}
?>