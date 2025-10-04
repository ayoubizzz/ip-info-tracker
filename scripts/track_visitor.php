<?php
// scripts/track_visitor.php
$config = include dirname(__DIR__) . '/config.php';
$dbConf = $config['db'];

try {
    $pdo = new PDO(
        "mysql:host={$dbConf['host']};dbname={$dbConf['name']};charset={$dbConf['charset']}",
        $dbConf['user'], $dbConf['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    error_log("Visitor tracker DB connect error: " . $e->getMessage());
    return; // do not break the page
}

function get_client_ip(): string {
    $keys = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = $_SERVER[$k];
            if ($k === 'HTTP_X_FORWARDED_FOR') {
                $ip = trim(explode(',', $ip)[0]); // first IP in chain
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

$ip = get_client_ip();
$ip_bin = @inet_pton($ip);
$ip_version = (strpos($ip, ':') !== false) ? 6 : 4;
if ($ip_bin === false || $ip_bin === null) {
    $ip = '0.0.0.0';
    $ip_bin = inet_pton('0.0.0.0');
    $ip_version = 4;
}

// Après avoir défini $ip, $ip_bin, $ip_version...
$isLocal = ($ip === '127.0.0.1' || $ip === '::1');

// Pour les tests en local, on fait la géoloc sur une IP publique connue,
// mais on continue à stocker l'IP réelle (ici ::1) en base.
$ip_for_geo = $isLocal ? '8.8.8.8' : $ip;

$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 1000);
$url       = substr($_SERVER['REQUEST_URI'] ?? '', 0, 2000);
$referer   = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 2000);

// Defaults
$country_iso = $country_name = $region = $city = $postal = $time_zone = null;
$lat = $lon = null;

// Local MMDB lookup (maxmind-db/reader)
$mmdbPath = $config['paths']['geoip_dir'] . '/' . $config['paths']['mmdb_filename'];
$autoload = $config['paths']['vendor_autoload'];
if (is_file($mmdbPath) && is_file($autoload)) {
    require_once $autoload;
    if (class_exists('MaxMind\\Db\\Reader')) {
        try {
            $reader = new MaxMind\Db\Reader($mmdbPath);
            $rec    = $reader->get($ip_for_geo); // array or null
            if (is_array($rec)) {
                $country_iso  = $rec['country']['iso_code']        ?? null;
                $country_name = $rec['country']['names']['en']     ?? null;
                $city         = $rec['city']['names']['en']        ?? null;
                $region       = $rec['subdivisions'][0]['names']['en'] ?? null;
                $postal       = $rec['postal']['code']             ?? null;
                $lat          = $rec['location']['latitude']       ?? null;
                $lon          = $rec['location']['longitude']      ?? null;
                $time_zone    = $rec['location']['time_zone']      ?? null;
            }
            $reader->close();
        } catch (Throwable $e) {
            // silently ignore, still insert basic row
        }
    }
}

// Latest geoip file id (if any)
$geoip_id = null;
try {
    $geoip_id = $pdo->query("SELECT id FROM geoip_files ORDER BY updated_at DESC LIMIT 1")->fetchColumn();
} catch (Throwable $e) {}

// Insert
$sql = "INSERT INTO visitor_logs
(ip, ip_text, ip_version, country_iso, country_name, region, city, postal_code,
 latitude, longitude, time_zone, user_agent, url, referer, geoip_file_id)
VALUES (:ip_bin,:ip_text,:ip_version,:country_iso,:country_name,:region,:city,:postal,
        :lat,:lon,:tz,:ua,:url,:ref,:geo)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':ip_bin'      => $ip_bin,
    ':ip_text'     => $ip,
    ':ip_version'  => $ip_version,
    ':country_iso' => $country_iso,
    ':country_name'=> $country_name,
    ':region'      => $region,
    ':city'        => $city,
    ':postal'      => $postal,
    ':lat'         => $lat,
    ':lon'         => $lon,
    ':tz'          => $time_zone,
    ':ua'          => $userAgent,
    ':url'         => $url,
    ':ref'         => $referer,
    ':geo'         => $geoip_id ?: null,
]);
