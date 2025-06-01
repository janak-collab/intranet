<?php
/**
 * Greater Maryland Pain Management - IP Address Manager
 * LiteSpeed Server Compatible Version
 * Styled to match GMPM design system
 * CSP-compliant version with external JavaScript
 */

session_start();

// Configuration
define('HTACCESS_PATH', '/home/gmpmus/public_html/.htaccess');
define('BACKUP_DIR', '/home/gmpmus/htaccess_backups/');
define('LOG_FILE', '/home/gmpmus/logs/ip_changes.log');
define('DEBUG_MODE', false);

// Security check
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    die('Unauthorized access');
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Functions (keeping all the existing PHP functions)
function validateIP($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    $parts = explode('.', $ip);
    if (count($parts) !== 4) {
        return false;
    }
    foreach ($parts as $part) {
        if (!is_numeric($part) || $part < 0 || $part > 255) {
            return false;
        }
    }
    return true;
}

function escapeIPForRegex($ip) {
    return str_replace('.', '\.', $ip);
}

function getCurrentIPs() {
    $ips = [];
    if (file_exists(HTACCESS_PATH)) {
        $content = file_get_contents(HTACCESS_PATH);
        if (preg_match('/<IfModule LiteSpeed>(.*?)<\/IfModule>/s', $content, $moduleMatch)) {
            $moduleContent = $moduleMatch[1];
            $lines = explode("\n", $moduleContent);
            $currentLocation = '';
            foreach ($lines as $line) {
                if (preg_match('/^\s*#\s*(.+?)\s*(?:Office)?$/', $line, $commentMatch)) {
                    $location = trim($commentMatch[1]);
                    if (!in_array($location, ['Block all IPs except allowed ones', 'Force rewrite rules to process first'])) {
                        $currentLocation = $location;
                    }
                }
                if (preg_match('/RewriteCond\s+%\{REMOTE_ADDR\}\s+!\^([0-9\.\\\\]+)\$/', $line, $ipMatch)) {
                    if ($currentLocation) {
                        $ip = str_replace('\\', '', $ipMatch[1]);
                        $ips[] = ['ip' => $ip, 'location' => $currentLocation];
                        $currentLocation = '';
                    }
                }
            }
        }
    }
    
    // Sort alphabetically by location (case-insensitive)
    usort($ips, function($a, $b) {
        return strcasecmp($a['location'], $b['location']);
    });
    
    return $ips;
}

function backupHtaccess() {
    if (!file_exists(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0750, true);
    }
    $backupFile = BACKUP_DIR . 'htaccess_' . date('Y-m-d_H-i-s') . '.bak';
    copy(HTACCESS_PATH, $backupFile);
    $backups = glob(BACKUP_DIR . '*.bak');
    if (count($backups) > 30) {
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        for ($i = 0; $i < count($backups) - 30; $i++) {
            unlink($backups[$i]);
        }
    }
}

function updateHtaccess($ips) {
    // Sort IPs alphabetically by location before saving
    usort($ips, function($a, $b) {
        return strcasecmp($a['location'], $b['location']);
    });
    
    backupHtaccess();
    $content = file_get_contents(HTACCESS_PATH);
    $pattern = '/(<IfModule LiteSpeed>.*?RewriteEngine On\s*\n)(.*?)(RewriteRule[^\n]+\[F,L\]\s*\n<\/IfModule>)/s';
    
    if (preg_match($pattern, $content, $matches)) {
        $newConditions = $matches[1] . "\n    # Block all IPs except allowed ones\n";
        foreach ($ips as $ip) {
            $escapedIP = str_replace('.', '\.', $ip['ip']);
            $newConditions .= "    # " . $ip['location'] . "\n";
            $newConditions .= "    RewriteCond %{REMOTE_ADDR} !^" . $escapedIP . "$\n";
        }
        $newConditions .= "    " . $matches[3];
        $content = preg_replace($pattern, $newConditions, $content);
        file_put_contents(HTACCESS_PATH, $content);
        logChange('Updated IP addresses for LiteSpeed server');
    } else {
        throw new Exception("Could not find LiteSpeed IP blocking section in .htaccess");
    }
}

function logChange($action) {
    $logDir = dirname(LOG_FILE);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0750, true);
    }
    $entry = sprintf(
        "[%s] %s - User: %s, IP: %s\n",
        date('Y-m-d H:i:s'),
        $action,
        $_SERVER['PHP_AUTH_USER'],
        $_SERVER['REMOTE_ADDR']
    );
    file_put_contents(LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

// Handle form submissions
$message = '';
$error = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ips = [];
    
    if (isset($_POST['ips']) && is_array($_POST['ips'])) {
        foreach ($_POST['ips'] as $index => $ip) {
            $ip = trim($ip);
            $location = trim($_POST['locations'][$index] ?? '');
            
            if (!empty($ip)) {
                if (!validateIP($ip)) {
                    $error = "Invalid IP address: $ip";
                    $messageType = 'error';
                    break;
                }
                
                if (empty($location)) {
                    $error = "Location required for IP: $ip";
                    $messageType = 'error';
                    break;
                }
                
                if (stripos($location, 'office') === false && stripos($location, 'home') === false) {
                    $location .= ' Office';
                }
                
                $ips[] = ['ip' => $ip, 'location' => $location];
            }
        }
    }
    
    if (empty($error) && !empty($ips)) {
        try {
            updateHtaccess($ips);
            $message = 'IP addresses updated successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $error = 'Error updating .htaccess: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$currentIPs = getCurrentIPs();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Address Manager - Greater Maryland Pain Management</title>
    <link rel="stylesheet" href="/assets/css/form-styles.css">
    <style>
        /* Additional styles specific to IP Manager */
        .ip-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .ip-row {
            display: grid;
            grid-template-columns: 40px 200px 1fr auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            background: var(--background-color);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            transition: var(--transition);
        }
        
        .ip-row:hover {
            border-color: var(--primary-color);
            background: white;
        }
        
        .row-number {
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .remove-btn {
            padding: 0.5rem 1rem;
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: calc(var(--radius) * 0.75);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .remove-btn:hover {
            background: #c53030;
            transform: translateY(-1px);
        }
        
        .server-notice {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #92400e;
        }
        
        .debug-box {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #742a2a;
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1.5rem;
            font-size: 0.875rem;
        }
        
        .debug-box h3 {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .debug-box pre {
            background: rgba(0,0,0,0.05);
            padding: 0.5rem;
            border-radius: calc(var(--radius) * 0.5);
            overflow-x: auto;
            font-size: 0.75rem;
            margin-top: 0.5rem;
        }
        
        /* IP validation visual feedback */
        .form-input.error {
            border-color: var(--error-color) !important;
            background-color: #fee;
        }
        
        .form-input:valid:not(:placeholder-shown) {
            border-color: var(--success-color);
        }
        
        @media (max-width: 640px) {
            .ip-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .row-number {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h1>🔒 IP Address Manager</h1>
                <p>Manage secure access for office locations</p>
            </div>
            
            <div class="form-content">
                <div id="alertContainer">
                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-error">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="info-box">
                    💡 <strong>Tip:</strong> Always test access after making changes. If locked out, use cPanel to create an .emergency-override file.
                </div>
                
                <div class="server-notice">
                    ⚡ This server runs LiteSpeed. IP blocking uses RewriteRule directives for compatibility.
                </div>
                
                <form id="ipForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" id="csrfToken" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">
                            Allowed IP Addresses <span class="required">*</span>
                            <small style="font-weight: normal; color: var(--text-secondary);">(sorted alphabetically by location)</small>
                        </label>
                        <div class="ip-grid" id="ipList">
                            <?php foreach ($currentIPs as $index => $ipData): ?>
                                <div class="ip-row">
                                    <span class="row-number">#<?php echo $index + 1; ?></span>
                                    <input 
                                        type="text" 
                                        name="ips[]" 
                                        class="form-input ip-input"
                                        value="<?php echo htmlspecialchars($ipData['ip']); ?>" 
                                        placeholder="IP Address (e.g., 192.168.1.1)"
                                        pattern="^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                                        title="Enter a valid IP address (e.g., 192.168.1.1)"
                                        required
                                        maxlength="15"
                                    >
                                    <input 
                                        type="text" 
                                        name="locations[]" 
                                        class="form-input"
                                        value="<?php echo htmlspecialchars($ipData['location']); ?>" 
                                        placeholder="Location/Office Name"
                                        required
                                        maxlength="100"
                                    >
                                    <button type="button" class="remove-btn">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-error" id="ipError"></div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="addIPBtn">
                            + Add IP Address
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span id="btnText">Save Changes</span>
                            <span id="btnSpinner" class="spinner" style="display: none;"></span>
                        </button>
                    </div>
                </form>
                
                <?php if (DEBUG_MODE): ?>
                <div class="debug-box">
                    <h3>🔍 Debug Information</h3>
                    <p><strong>Your current IP:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
                    <p><strong>Logged in as:</strong> <?php echo $_SERVER['PHP_AUTH_USER']; ?></p>
                    <p><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                    <p><strong>.htaccess path:</strong> <?php echo HTACCESS_PATH; ?></p>
                    <p><strong>.htaccess exists:</strong> <?php echo file_exists(HTACCESS_PATH) ? 'Yes' : 'No'; ?></p>
                    <p><strong>.htaccess writable:</strong> <?php echo is_writable(HTACCESS_PATH) ? 'Yes' : 'No'; ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer">
            <p>Greater Maryland Pain Management</p>
            <p><a href="/secure-admin/">Back to Admin Portal</a></p>
        </div>
    </div>
    
    <!-- External JavaScript file for CSP compliance -->
    <script src="/assets/js/ip-manager.js"></script>
</body>
</html>