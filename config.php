<?php
// Configuration that works both locally (XAMPP) and on EC2 (reads from environment)
return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'name'    => getenv('DB_NAME') ?: 'visitor_logs',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'paths' => [
        // keep outside webroot in production if possible; fine for local test
        'geoip_dir'       => __DIR__ . '/geoip',
        'mmdb_filename'   => 'GeoLite2-City.mmdb',
        'vendor_autoload' => __DIR__ . '/vendor/autoload.php',
    ],
    'maxmind' => [
        'edition'     => 'GeoLite2-City',
        'license_key' => getenv('MAXMIND_LICENSE_KEY') ?: '',  // Set via environment or pass as -var to Terraform
    ],
];
