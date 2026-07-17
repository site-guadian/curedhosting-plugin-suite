<?php
$path = __DIR__ . '/../curedhosting-plugin-suite.php';
if (!file_exists($path)) {
    fwrite(STDERR, "Plugin file not found: $path\n");
    exit(2);
}
$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Failed to read plugin file\n");
    exit(2);
}
if (preg_match("/define\(\'CHPS_VERSION\',\s*\'([0-9]+\.[0-9]+\.[0-9]+)\'\)/", $contents, $m)) {
    $v = $m[1];
    echo "CHPS_VERSION = $v\n";
    exit(0);
}
fwrite(STDERR, "CHPS_VERSION not found or malformed\n");
exit(1);
