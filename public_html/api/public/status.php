<?php
header('Content-Type: application/json');

$checks = [
    'status' => 'ok',
    'php_version' => phpversion(),
    'timestamp' => date('Y-m-d H:i:s'),
    'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
    'your_ip' => $_SERVER['REMOTE_ADDR'],
    'app_directory' => is_dir('../../../app') ? 'found' : 'missing',
    'vendor_directory' => is_dir('../../../app/vendor') ? 'found' : 'missing',
    'storage_directory' => is_dir('../../../storage') ? 'found' : 'missing',
    'environment_file' => file_exists('../../../app/.env') ? 'found' : 'missing'
];

echo json_encode($checks, JSON_PRETTY_PRINT);
