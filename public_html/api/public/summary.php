<?php
header('Content-Type: text/plain');

echo "GMPM System Summary\n";
echo "==================\n\n";

echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "Your IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
echo "PHP Version: " . phpversion() . "\n\n";

echo "Directory Status:\n";
echo "- App: " . (is_dir('../../../app') ? '✓' : '✗') . "\n";
echo "- Vendor: " . (is_dir('../../../app/vendor') ? '✓' : '✗') . "\n";
echo "- Storage: " . (is_dir('../../../storage') ? '✓' : '✗') . "\n";
echo "- Assets: " . (is_dir('../../assets') ? '✓' : '✗') . "\n\n";

echo "Key Files:\n";
echo "- .env: " . (file_exists('../../../app/.env') ? '✓' : '✗') . "\n";
echo "- index.php: " . (file_exists('../../index.php') ? '✓' : '✗') . "\n";
echo "- .htaccess: " . (file_exists('../../.htaccess') ? '✓' : '✗') . "\n\n";

echo "Recent Access Log (last 5):\n";
$log = file_get_contents('../../error_log');
$lines = explode("\n", trim($log));
$recent = array_slice($lines, -5);
foreach ($recent as $line) {
    if (strpos($line, 'Access:') !== false) {
        echo "  " . $line . "\n";
    }
}

echo "\nSystem is operational!\n";
