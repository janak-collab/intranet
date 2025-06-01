<?php
// GMPM Application Entry Point
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Security check - ensure critical files aren't accessible
if (file_exists(__DIR__ . '/.env')) {
    die('Security Error: .env file should not be in public directory!');
}

// Check if vendor exists in new location
if (!file_exists(APP_PATH . '/vendor/autoload.php')) {
    die('Error: Vendor directory not found. Please check installation.');
}

// Autoload
require_once APP_PATH . '/vendor/autoload.php';

// Load environment variables
if (file_exists(APP_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(APP_PATH);
    $dotenv->load();
}

// Start session for all requests
session_start();

// Basic routing
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = rtrim($requestUri, '/');

// Log access for security monitoring
error_log("Access: " . $_SERVER['REMOTE_ADDR'] . " - " . $requestUri);

// Route the request
switch (true) {
    case $requestUri === '' || $requestUri === '/index.php':
        // Show portal page instead of redirecting
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>GMPM Portal</title>
            <link rel="stylesheet" href="/assets/css/form-styles.css">
        </head>
        <body>
            <div class="container">
                <div class="form-card">
                    <h1>Greater Maryland Pain Management Portal</h1>
                    <div class="form-content">
                        <p>Welcome, <?php echo $_SERVER['PHP_AUTH_USER'] ?? 'User'; ?>!</p>
                        <div style="display: grid; gap: 1rem; margin-top: 2rem;">
                            <a href="/phone-note" class="btn btn-primary">📞 Phone Note Form</a>
                            <a href="/it-support" class="btn btn-secondary">💻 IT Support Request</a>
                            <a href="/view-tickets" class="btn btn-secondary">📋 View Tickets</a>
                            <a href="/admin" class="btn btn-secondary">🔧 Admin Area</a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        break;
    
    case $requestUri === '/phone-note':
        require APP_PATH . '/public-endpoints/phone-note.php';
        break;
        
    case $requestUri === '/it-support':
        require APP_PATH . '/public-endpoints/it-support.php';
        break;
        
    case $requestUri === '/view-tickets':
        require APP_PATH . '/public-endpoints/view-tickets.php';
        break;
    
    case strpos($requestUri, '/admin') === 0:
        // Add authentication check here
        $adminController = APP_PATH . '/src/Controllers/AdminController.php';
        if (file_exists($adminController)) {
            require $adminController;
        } else {
            http_response_code(404);
            echo "Admin area not implemented yet";
        }
        break;
    
    case strpos($requestUri, '/api') === 0:
        // API routes stay in public for now
        $apiPath = str_replace('/api', '', $requestUri);
        $apiFile = PUBLIC_PATH . '/api' . $apiPath . '.php';
        if (file_exists($apiFile)) {
            require $apiFile;
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
        }
        break;
        
    case strpos($requestUri, '/assets') === 0:
        // Let server handle static files
        return false;
        break;
    
    default:
        http_response_code(404);
        if (file_exists(__DIR__ . '/errors/404.html')) {
            include __DIR__ . '/errors/404.html';
        } else {
            echo "404 - Not Found";
        }
}
