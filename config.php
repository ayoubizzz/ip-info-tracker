<?php
return [
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'visitor_logs',   // database you created/imported
        'user'    => 'root',           // XAMPP default; on cPanel use your DB user
        'pass'    => '',               // XAMPP default blank; on cPanel set real password
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
    ],
];
