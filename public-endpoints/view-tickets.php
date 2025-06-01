<?php
// Adjust path since we're now in /app/public-endpoints/
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}

require_once APP_PATH . '/vendor/autoload.php';
require_once APP_PATH . '/src/bootstrap.php';

// Authentication check
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    header('Location: /login');
    exit;
}

// Include ticket viewing logic here
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Tickets - GMPM</title>
    <link rel="stylesheet" href="/assets/css/form-styles.css">
    <link rel="stylesheet" href="/assets/css/panel-styles.css">
</head>
<body>
    <div class="container">
        <h1>Support Tickets</h1>
        <p>Tickets will be displayed here</p>
        <p><a href="/">Back to Portal</a></p>
    </div>
</body>
</html>
