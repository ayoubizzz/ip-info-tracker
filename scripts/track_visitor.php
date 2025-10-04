<?php
/**
 * Visitor Tracker Script
 * Logs visitor information including IP, geolocation data, and request details
 * Works with both local (XAMPP) and production (EC2) environments
 */

// Load configuration
$config = include dirname(__DIR__) . '/config.php';
$dbConf = $config['db'];

// Database connection with error handling
try {
    $pdo = new PDO(
        "mysql:host={$dbConf['host']};dbname={$dbConf['name']};charset={$dbConf['charset']}",
        $dbConf['user'],
        $dbConf['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    error_log("Visitor tracker DB connect error: " . $e->getMessage());
    return; // Exit gracefully without breaking the page
}

/**
 * Get the real client IP address
 * Checks multiple headers to handle proxies and load balancers
 */
function get_client_ip(): string {
    $keys = [
        'HTTP_CF_CONNECTING_IP',  // Cloudflare
        'HTTP_X_FORWARDED_FOR',   // Standard proxy header
        'HTTP_CLIENT_IP',         // Some proxies
        'REMOTE_ADDR'             // Direct connection
    ];
    
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = $_SERVER[$k];
            // Handle comma-separated list from X-Forwarded-For
            if ($k === 'HTTP_X_FORWARDED_FOR') {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// Get client IP
$ip = get_client_ip();

// Detect if IP is local (localhost)
$isLocal = ($ip === '127.0.0.1' || $ip === '::1' || $ip === '0.0.0.0');

// For local testing, use a public IP (Google DNS) for geolocation lookup
// but still store the real IP in the database
$ip_for_geo = $isLocal ? '8.8.8.8' : $ip;

// Collect request information
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 1000);
$url       = substr($_SERVER['REQUEST_URI'] ?? '', 0, 2000);
$referer   = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 2000);

// Initialize geolocation variables
$country = $city = null;
$lat = $lon = null;

// Perform GeoIP lookup using MaxMind database
$mmdbPath = $config['paths']['geoip_dir'] . '/' . $config['paths']['mmdb_filename'];
$autoload = $config['paths']['vendor_autoload'];

if (is_file($mmdbPath) && is_file($autoload)) {
    require_once $autoload;
    
    if (class_exists('MaxMind\\Db\\Reader')) {
        try {
            $reader = new MaxMind\Db\Reader($mmdbPath);
            $rec = $reader->get($ip_for_geo);
            
            if (is_array($rec)) {
                // Extract geolocation data
                $country = $rec['country']['names']['en'] ?? null;
                $city    = $rec['city']['names']['en'] ?? null;
                $lat     = $rec['location']['latitude'] ?? null;
                $lon     = $rec['location']['longitude'] ?? null;
            }
            
            $reader->close();
        } catch (Throwable $e) {
            // Silently ignore GeoIP errors, still log the visit
            error_log("GeoIP lookup error: " . $e->getMessage());
        }
    }
}

// Insert visitor log into database
try {
    $sql = "INSERT INTO visitor_logs
            (ip, geo_ip, country, city, latitude, longitude, user_agent, referer, url)
            VALUES (:ip, :geo_ip, :country, :city, :lat, :lon, :ua, :ref, :url)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ip'      => $ip,
        ':geo_ip'  => $ip_for_geo,
        ':country' => $country,
        ':city'    => $city,
        ':lat'     => $lat,
        ':lon'     => $lon,
        ':ua'      => $userAgent,
        ':ref'     => $referer,
        ':url'     => $url,
    ]);
} catch (Throwable $e) {
    error_log("Visitor tracker INSERT error: " . $e->getMessage());
}
