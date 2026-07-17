<?php
// Standalone key maker for CuredHosting Plugin Suite.
// Usage: php generate-key.php --email="user@example.com" --tier=pro [--secret="shared-secret"]

$options = getopt('', ['email:', 'tier:', 'secret::']);

$email = isset($options['email']) ? trim($options['email']) : '';
$tier = isset($options['tier']) ? trim($options['tier']) : 'free';
$secret = isset($options['secret']) ? trim($options['secret']) : 'curedhosting-standalone-key-maker';

if ($email === '') {
    fwrite(STDERR, "Error: --email is required\n");
    exit(1);
}

$tier = strtolower($tier);
if (!in_array($tier, ['free', 'pro', 'corporate'], true)) {
    fwrite(STDERR, "Error: --tier must be one of free, pro, corporate\n");
    exit(1);
}

$prefix = $tier === 'corporate' ? 'CORP' : ($tier === 'pro' ? 'PRO' : 'FREE');
$hashSource = sprintf('%s:%s:%s', strtolower($email), $tier, $secret);
$hash = strtoupper(substr(hash('sha256', $hashSource), 0, 16));
$key = sprintf('%s-%s', $prefix, $hash);

fwrite(STDOUT, "Generated license key for {$email} ({$tier}): {$key}\n");
