<?php
define('PRESENCE_DIR', __DIR__ . '/presence/');
if (!is_dir(PRESENCE_DIR)) mkdir(PRESENCE_DIR, 0755, true);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === "POST") {
    // Puede venir con application/x-www-form-urlencoded o JSON
    if (!empty($_POST['ip'])) $ip = $_POST['ip'];
    else {
        $d = file_get_contents('php://input');
        if ($d) {
            // si viene URLSearchParams será parseable en $_POST; si JSON:
            $obj = json_decode($d, true);
            $ip = $obj['ip'] ?? $_SERVER['REMOTE_ADDR'];
        } else $ip = $_SERVER['REMOTE_ADDR'];
    }
    $time = time();
    file_put_contents(PRESENCE_DIR . $ip . '.txt', $time);
    echo "OK";
    exit;
}

if ($method === "GET") {
    $ip = isset($_GET['ip']) ? $_GET['ip'] : $_SERVER['REMOTE_ADDR'];
    $f = PRESENCE_DIR . $ip . '.txt';
    if (file_exists($f)) {
        $last = intval(file_get_contents($f));
        echo (time() - $last <= 60) ? "online" : "offline";
    } else {
        echo "offline";
    }
    exit;
}
?>