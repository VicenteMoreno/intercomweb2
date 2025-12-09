<?php
// signaling_server.php - Ratchet WebSocket signaling server integrado con config.txt (salas/usuarios).
// Requisitos: composer require cboden/ratchet
// Ejecutar: php signaling_server.php
// El servidor valida token HMAC enviado por index.php y enruta mensajes sólo dentro de la misma sala
// y por IP destino (mantiene el modelo original: usuarios identificados por IP).

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/utils.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class SignalingServer implements MessageComponentInterface {
    protected $clients; // SplObjectStorage
    protected $meta;    // resourceId => ['conn'=>..., 'ip'=>..., 'sala'=>..., 'user'=>...]
    protected $config;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->meta = [];
        $this->config = cargar_configuracion() ?: [];
        echo "[".date('c')."] SignalingServer iniciado\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "[".date('c')."] Conexión abierta resourceId={$conn->resourceId}\n";
        // No registramos metadata hasta recibir 'join' desde el cliente.
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $fromId = $from->resourceId;
        $data = @json_decode($msg, true);
        if (!$data) {
            echo "[".date('c')."] Mensaje no JSON desde $fromId: $msg\n";
            return;
        }

        $type = $data['type'] ?? '';
        if ($type === 'join') {
            $this->handleJoin($from, $data);
            return;
        }

        if (!isset($this->meta[$fromId])) {
            echo "[".date('c')."] Mensaje desde no-registrado $fromId ignorado\n";
            return;
        }

        $to = $data['to'] ?? null;
        switch ($type) {
            case 'offer':
            case 'answer':
            case 'candidate':
            case 'leave':
                $this->routeMessage($fromId, $to, $data);
                break;
            default:
                echo "[".date('c')."] Tipo desconocido $type desde $fromId\n";
        }
    }

    protected function handleJoin(ConnectionInterface $conn, array $data) {
        $rid = $conn->resourceId;
        $ip = $data['ip'] ?? '';
        $sala = $data['sala'] ?? '';
        $user = $data['user'] ?? '';
        $token = $data['token'] ?? '';

        echo "[".date('c')."] Join request from resourceId={$rid} ip={$ip} sala={$sala}\n";

        if (!$this->config) {
            $conn->send(json_encode(['type'=>'error','msg'=>'config_not_loaded']));
            $conn->close();
            return;
        }

        $secret = $this->config['GENERAL']['contraseña'] ?? '';
        if ($secret === '') {
            $conn->send(json_encode(['type'=>'error','msg'=>'no_secret_config']));
            $conn->close();
            return;
        }

        $expected = hash_hmac('sha256', $ip . '|' . $sala, $secret);
        if (!hash_equals($expected, $token)) {
            echo "[".date('c')."] Token inválido para resourceId={$rid} (ip={$ip}, sala={$sala})\n";
            $conn->send(json_encode(['type'=>'error','msg'=>'invalid_token']));
            $conn->close();
            return;
        }

        if (!usuario_ip_permitida($this->config, $ip)) {
            echo "[".date('c')."] IP no permitida ($ip) para resourceId={$rid}\n";
            $conn->send(json_encode(['type'=>'error','msg'=>'ip_not_allowed']));
            $conn->close();
            return;
        }

        $this->meta[$rid] = [
            'conn' => $conn,
            'ip' => $ip,
            'sala' => $sala,
            'user' => $user,
            'joined_at' => time()
        ];

        $conn->send(json_encode(['type'=>'id','id'=>$rid]));

        $peers = [];
        foreach ($this->meta as $mid => $m) {
            if ($mid == $rid) continue;
            if (isset($m['sala']) && $m['sala'] === $sala) {
                $peers[] = ['id'=>$mid,'ip'=>$m['ip'],'user'=>$m['user']];
            }
        }
        $conn->send(json_encode(['type'=>'peers','peers'=>$peers]));

        foreach ($this->meta as $mid => $m) {
            if ($mid == $rid) continue;
            if (isset($m['sala']) && $m['sala'] === $sala) {
                $m['conn']->send(json_encode(['type'=>'join','id'=>$rid,'ip'=>$ip,'user'=>$user]));
            }
        }

        echo "[".date('c')."] resourceId={$rid} unido a sala {$sala} (ip={$ip})\n";
    }

    protected function routeMessage($fromId, $to, $data) {
        $fromMeta = $this->meta[$fromId];
        $sala = $fromMeta['sala'];
        $type = $data['type'];

        $found = false;
        foreach ($this->meta as $mid => $m) {
            if (!isset($m['sala']) || $m['sala'] !== $sala) continue;
            if ($to === null) {
                if ($mid == $fromId) continue;
                $m['conn']->send(json_encode($data));
                $found = true;
            } else {
                if ((string)$mid === (string)$to || (isset($m['ip']) && $m['ip'] === $to)) {
                    $m['conn']->send(json_encode($data));
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            echo "[".date('c')."] Destino no encontrado en sala {$sala} para mensaje type={$type} from={$fromId} to={$to}\n";
        } else {
            echo "[".date('c')."] Reenviado {$type} from={$fromId} to={$to} (sala={$sala})\n";
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $rid = $conn->resourceId;
        echo "[".date('c')."] Cierre de conexión resourceId={$rid}\n";
        if (isset($this->meta[$rid])) {
            $meta = $this->meta[$rid];
            $sala = $meta['sala'];
            unset($this->meta[$rid]);

            foreach ($this->meta as $mid => $m) {
                if (isset($m['sala']) && $m['sala'] === $sala) {
                    $m['conn']->send(json_encode(['type'=>'leave','id'=>$rid,'ip'=>$meta['ip']]));
                }
            }
        }
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[".date('c')."] Error en connection {$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }
}

$host = '127.0.0.1';
$port = 8443;

$loop = React\EventLoop\Factory::create();
$webSock = new React\Socket\Server("$host:$port", $loop);

$server = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer(
            new SignalingServer()
        )
    ),
    $webSock,
    $loop
);

echo "[".date('c')."] Ratchet escuchando en ws://{$host}:{$port}/\n";
$server->run();