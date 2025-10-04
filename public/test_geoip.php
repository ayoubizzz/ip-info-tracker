<?php
$config = include dirname(__DIR__) . '/config.php';
$mmdb   = $config['paths']['geoip_dir'] . '/' . $config['paths']['mmdb_filename'];
$autoload = $config['paths']['vendor_autoload'];

if (!is_file($mmdb)) {
    header('Content-Type: text/plain');
    echo "MMDB not found at: $mmdb\nRun geoip_update.php or download GeoLite2-City.mmdb.";
    exit;
}

if (!is_file($autoload)) {
    header('Content-Type: text/plain');
    echo "Composer autoload not found at: $autoload\nRun: composer require maxmind-db/reader";
    exit;
}

require_once $autoload;
use MaxMind\Db\Reader;

$ip = $_GET['ip'] ?? $_SERVER['REMOTE_ADDR'];

header('Content-Type: application/json; charset=utf-8');
try {
    $reader = new Reader($mmdb);
    $rec    = $reader->get($ip);
    $reader->close();
    echo json_encode(['ip'=>$ip, 'record'=>$rec], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
