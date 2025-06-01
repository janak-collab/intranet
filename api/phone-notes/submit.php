<?php
// Start with error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set JSON header
header('Content-Type: application/json');

// Check if files exist
$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
$bootstrapPath = __DIR__ . '/../../../src/bootstrap.php';

if (!file_exists($autoloadPath)) {
    echo json_encode(['error' => 'Autoload not found at: ' . $autoloadPath]);
    exit;
}

if (!file_exists($bootstrapPath)) {
    echo json_encode(['error' => 'Bootstrap not found at: ' . $bootstrapPath]);
    exit;
}

// Load files
require_once $autoloadPath;
require_once $bootstrapPath;

// Try to use the controller
use App\Controllers\PhoneNoteController;

try {
    $controller = new PhoneNoteController();
    $controller->submit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
