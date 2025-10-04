<?php
// scripts/geoip_update.php
// Run via CLI: php /path/to/scripts/geoip_update.php

$config   = include dirname(__DIR__) . '/config.php';
$edition  = $config['maxmind']['edition'];
$license  = $config['maxmind']['license_key'];
$geoipDir = rtrim($config['paths']['geoip_dir'], '/');
$mmdbDest = $geoipDir . '/' . $config['paths']['mmdb_filename'];

if (!is_dir($geoipDir) && !mkdir($geoipDir, 0750, true)) {
    fwrite(STDERR, "Failed to create dir $geoipDir\n");
    exit(1);
}

$tmpArchive = sys_get_temp_dir() . '/geoip_' . uniqid('', true) . '.tar.gz';
$tmpDir     = sys_get_temp_dir() . '/geoip_unpacked_' . uniqid('', true);
@mkdir($tmpDir, 0700, true);

$downloadUrl = "https://download.maxmind.com/app/geoip_download?edition_id=" .
    urlencode($edition) . "&license_key=" . urlencode($license) . "&suffix=tar.gz";

// Download
$ok = false;
if (function_exists('exec')) {
    $cmd = 'curl -fsSL ' . escapeshellarg($downloadUrl) . ' -o ' . escapeshellarg($tmpArchive);
    exec($cmd, $o, $ret);
    $ok = ($ret === 0 && is_file($tmpArchive) && filesize($tmpArchive) > 100);
}
if (!$ok) {
    $ctx = stream_context_create(['http' => ['timeout' => 60]]);
    $data = @file_get_contents($downloadUrl, false, $ctx);
    if (!$data) {
        fwrite(STDERR, "Download failed. Check license key/connectivity.\n");
        exit(1);
    }
    file_put_contents($tmpArchive, $data);
}

// Extract
$extracted = false;
if (function_exists('exec')) {
    $cmd = 'tar -xzf ' . escapeshellarg($tmpArchive) . ' -C ' . escapeshellarg($tmpDir);
    exec($cmd, $o2, $ret2);
    $extracted = ($ret2 === 0);
}
if (!$extracted) {
    try {
        $phar = new PharData($tmpArchive);
        $phar->decompress(); // makes .tar
        $tar = str_replace('.gz', '', $tmpArchive);
        $phar2 = new PharData($tar);
        $phar2->extractTo($tmpDir);
        $extracted = true;
    } catch (Throwable $e) {
        fwrite(STDERR, "Extraction failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// Locate mmdb
$mmdbFound = null;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir));
foreach ($it as $f) {
    if ($f->isFile() && preg_match('/\.mmdb$/i', $f->getFilename())) {
        $mmdbFound = $f->getPathname();
        break;
    }
}
if (!$mmdbFound) {
    fwrite(STDERR, "No .mmdb in archive.\n");
    exit(1);
}

// Atomic replace
if (!copy($mmdbFound, $mmdbDest . '.new')) {
    fwrite(STDERR, "Copy to temp failed.\n");
    exit(1);
}
if (!rename($mmdbDest . '.new', $mmdbDest)) {
    fwrite(STDERR, "Atomic rename failed.\n");
    exit(1);
}
@chmod($mmdbDest, 0640);

// Record metadata (optional)
try {
    $db  = $config['db'];
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
        $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare("INSERT INTO geoip_files (filename, filepath, edition_id, file_size, checksum)
                           VALUES (:fn, :fp, :ed, :sz, :chk)");
    $stmt->execute([
        ':fn'  => basename($mmdbDest),
        ':fp'  => $mmdbDest,
        ':ed'  => $edition,
        ':sz'  => filesize($mmdbDest),
        ':chk' => hash_file('sha256', $mmdbDest),
    ]);
} catch (Throwable $e) {
    // not fatal
    fwrite(STDERR, "Warn: could not save metadata: " . $e->getMessage() . "\n");
}

// Cleanup
@unlink($tmpArchive);
$rr = function ($dir) use (&$rr) {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.','..']) as $f) {
        $p = $dir . '/' . $f;
        is_dir($p) ? $rr($p) : @unlink($p);
    }
    @rmdir($dir);
};
$rr($tmpDir);

echo "GeoIP updated: $mmdbDest\n";
