<?php
// phone-note-print.php - Modern Phone Note Print Handler
session_start();

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('Security token mismatch. Please try again.');
}

// Sanitize and validate input data
$name = ucwords(trim($_POST['pname'] ?? ''));
$dob = trim($_POST['dob'] ?? '');
$phone = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
$location = trim($_POST['location'] ?? '');
$last_seen = trim($_POST['last_seen'] ?? '');
$upcoming = trim($_POST['upcoming'] ?? '');
$provider = trim($_POST['requester'] ?? '');
$description = trim($_POST['description'] ?? '');
$caller_name = trim($_POST['caller_name'] ?? '');

// Validation
if (empty($name) || empty($dob) || empty($phone) || empty($location) || 
    empty($last_seen) || empty($upcoming) || empty($provider) || empty($description)) {
    die('Missing required information. Please go back and fill in all fields.');
}

if (strlen($phone) !== 10) {
    die('Invalid phone number format. Please go back and correct.');
}

// Get current user info
$user = $_SERVER['REMOTE_USER'] ?? 'Unknown User';
$first_initial = ucfirst(substr($user, 0, 1));
$last_name = ucfirst(substr($user, 1));
$user_display = $first_initial . '. ' . $last_name;

// Format phone number
function formatPhone($phone) {
    return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phone Note - <?php echo htmlspecialchars($name); ?> - Greater Maryland Pain Management</title>
    <link rel="stylesheet" href="/assets/css/form-styles.css">
    <style>
        /* Print-specific styles */
        @media print {
            body { 
                margin: 0; 
                background: white;
            }
            .no-print { 
                display: none !important; 
            }
            .page-break { 
                page-break-before: always; 
            }
            .print-container {
                max-width: 100%;
                padding: 0;
            }
            .print-card {
                box-shadow: none;
                border: none;
            }
        }
        
        .print-container {
            max-width: 8.5in;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .print-card {
            background: var(--card-background);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .print-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .print-header h1 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .print-header .date {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .print-content {
            padding: 2rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .info-item {
            padding: 0.75rem;
            background: #f7fafc;
            border-radius: calc(var(--radius) * 0.75);
            border: 1px solid var(--border-color);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .appointment-banner {
            background: linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%);
            border: 1px solid #91d5ff;
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .message-section {
            background: #f7fafc;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .message-header {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 1rem;
            font-size: 1.125rem;
        }
        
        .message-content {
            color: var(--text-primary);
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .notes-section {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            background: #fafafa;
        }
        
        .notes-line {
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            height: 2rem;
        }
        
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr auto 1fr auto;
            gap: 1rem;
            align-items: end;
            margin-top: 2rem;
        }
        
        .signature-line {
            border-bottom: 2px solid var(--secondary-color);
            height: 2rem;
        }
        
        .signature-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .followup-section {
            background: #f0f0f0;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .checkbox {
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid var(--secondary-color);
            border-radius: 0.25rem;
            display: inline-block;
        }
        
        .meta-info {
            background: #e6f7ff;
            border: 1px solid #91d5ff;
            border-radius: var(--radius);
            padding: 1rem;
            font-size: 0.875rem;
            color: #0050b3;
            text-align: center;
        }
        
        .print-actions {
            text-align: center;
            padding: 2rem;
            background: var(--background-color);
            border-radius: var(--radius);
            margin-bottom: 2rem;
        }
        
        .hipaa-badge {
            background: var(--warning-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="print-actions no-print">
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ Print Phone Note
            </button>
            <a href="phone-note.php" class="btn btn-secondary" style="margin-left: 1rem;">
                ← Back to Form
            </a>
        </div>

        <div class="print-card">
            <div class="print-header">
                <div>
                    <h1>📞 Phone Note</h1>
                    <div>Greater Maryland Pain Management</div>
                </div>
                <div class="date">
                    <?php echo date('l, F j, Y'); ?>
                </div>
            </div>
            
            <div class="print-content">
                <!-- Patient Information -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Patient Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($name); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value">
                            <?php 
                            $date_obj = DateTime::createFromFormat('Y-m-d', $dob);
                            echo $date_obj ? $date_obj->format('m/d/Y') : htmlspecialchars($dob);
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Callback Number</div>
                        <div class="info-value"><?php echo formatPhone($phone); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Office Location</div>
                        <div class="info-value"><?php echo htmlspecialchars($location); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Message For</div>
                        <div class="info-value"><?php echo htmlspecialchars(explode('NPI', $provider)[0]); ?></div>
                    </div>
                    
                    <?php if (!empty($caller_name)): ?>
                    <div class="info-item">
                        <div class="info-label">Caller <span class="hipaa-badge">HIPAA Alert</span></div>
                        <div class="info-value"><?php echo htmlspecialchars($caller_name); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Appointment Information -->
                <div class="appointment-banner">
                    <div>
                        <div class="info-label">Last Seen</div>
                        <div class="info-value">
                            <?php 
                            $last_seen_obj = DateTime::createFromFormat('Y-m-d', $last_seen);
                            echo $last_seen_obj ? $last_seen_obj->format('m/d/Y') : htmlspecialchars($last_seen);
                            ?>
                        </div>
                    </div>
                    
                    <div style="text-align: center;">
                        <?php 
                        if ($last_seen_obj && $upcoming_obj = DateTime::createFromFormat('Y-m-d', $upcoming)) {
                            $interval = $last_seen_obj->diff($upcoming_obj);
                            echo '<div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">' . $interval->days . ' days</div>';
                            echo '<div style="font-size: 0.75rem; color: var(--text-secondary);">between appointments</div>';
                        }
                        ?>
                    </div>
                    
                    <div>
                        <div class="info-label">Next Appointment</div>
                        <div class="info-value">
                            <?php 
                            $upcoming_obj = DateTime::createFromFormat('Y-m-d', $upcoming);
                            echo $upcoming_obj ? $upcoming_obj->format('m/d/Y') : htmlspecialchars($upcoming);
                            
                            if ($upcoming_obj) {
                                $today = new DateTime();
                                $interval_today = $today->diff($upcoming_obj);
                                $days_from_today = $interval_today->days;
                                
                                if ($upcoming_obj->format('Y-m-d') == $today->format('Y-m-d')) {
                                    echo ' <span style="color: var(--success-color); font-weight: bold;">(Today)</span>';
                                } elseif ($days_from_today == 1 && $upcoming_obj > $today) {
                                    echo ' <span style="color: var(--success-color); font-weight: bold;">(Tomorrow)</span>';
                                } elseif ($upcoming_obj > $today) {
                                    echo ' <span style="color: var(--info-color);">(' . $days_from_today . ' days)</span>';
                                } else {
                                    echo ' <span style="color: var(--error-color);">(' . $days_from_today . ' days ago)</span>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Message Content -->
                <div class="message-section">
                    <div class="message-header">Message Description</div>
                    <div class="message-content"><?php echo htmlspecialchars($description); ?></div>
                </div>
                
                <!-- Provider Notes Section -->
                <div class="notes-section">
                    <div class="message-header">Provider/Staff Notes</div>
                    <div class="notes-line"></div>
                    <div class="notes-line"></div>
                    <div class="notes-line"></div>
                    
                    <div class="signature-grid">
                        <div class="signature-label">Provider/Staff Signature:</div>
                        <div class="signature-line"></div>
                        <div class="signature-label">Date & Time:</div>
                        <div class="signature-line"></div>
                    </div>
                </div>
                
                <!-- Follow-up Section -->
                <div class="followup-section">
                    <div class="message-header">Follow-up Actions</div>
                    
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>No call needed</span>
                    </div>
                    
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Left message/voicemail</span>
                    </div>
                    
                    <div class="checkbox-item">
                        <div class="checkbox"></div>
                        <span>Spoke to: _________________________________</span>
                    </div>
                    
                    <div style="margin-top: 1rem;">
                        <div class="message-header" style="font-size: 1rem;">Additional Notes:</div>
                        <div class="notes-line"></div>
                        <div class="notes-line"></div>
                    </div>
                    
                    <div class="signature-grid" style="margin-top: 2rem;">
                        <div class="signature-label">Phone Rep Signature:</div>
                        <div class="signature-line"></div>
                        <div class="signature-label">Date & Time:</div>
                        <div class="signature-line"></div>
                    </div>
                </div>
                
                <!-- Meta Information -->
                <div class="meta-info">
                    Generated by <?php echo htmlspecialchars($user_display); ?> at <?php echo date('g:i:s A'); ?> EST on <?php echo date('l, F j, Y'); ?>
                </div>
            </div>
        </div>
        
        <div class="footer no-print">
            <p>Greater Maryland Pain Management</p>
            <p><a href="/">Back to Portal</a></p>
        </div>
    </div>

    <script>
        // Auto-print when page loads (matching original functionality)
        window.addEventListener('load', function() {
            // Small delay to ensure page is fully loaded
            setTimeout(function() {
                window.print();
            }, 500);
        });
        
        // Handle print dialog
        window.addEventListener('afterprint', function() {
            // Show success message after printing
            const successDiv = document.createElement('div');
            successDiv.className = 'alert alert-success';
            successDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1000;';
            successDiv.innerHTML = '✓ Phone note sent to printer';
            document.body.appendChild(successDiv);
            
            setTimeout(() => {
                successDiv.remove();
            }, 3000);
        });
    </script>
</body>
</html>