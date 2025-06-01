<?php
namespace App\Controllers;

use App\Models\ITTicket;
use App\Services\EmailService;
use App\Services\ValidationService;
use App\Services\RateLimiter;

class ITSupportController {
    private $ticketModel;
    private $validator;
    private $rateLimiter;
    
    public function __construct() {
        $this->ticketModel = new ITTicket();
        $this->validator = new ValidationService();
        $this->rateLimiter = new RateLimiter();
    }
    
    /**
     * Display the IT Support form
     */
    public function showForm() {
        // Generate CSRF token
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        // Initialize form data
        $formData = [
            'name' => '',
            'location' => '',
            'category' => '',
            'priority' => 'normal',
            'description' => ''
        ];
        
        $errors = [];
        $message = '';
        $messageType = '';
        
        // Get locations and categories for the form
        $locations = $this->getLocations();
        $categories = $this->getCategories();
        $priorities = $this->getPriorities();
        
        // Load the view
        require_once __DIR__ . '/../../templates/views/it-support-form.php';
    }
    
    /**
     * Handle form submission
     */
    public function handleSubmission() {
        header('Content-Type: application/json');
        
        // Check rate limiting
        $identifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
        if (!$this->rateLimiter->check($identifier)) {
            $timeLeft = $this->rateLimiter->getTimeUntilReset($identifier);
            $this->jsonResponse([
                'success' => false,
                'error' => 'Too many requests. Please try again in ' . ceil($timeLeft / 60) . ' minutes.'
            ], 429);
            return;
        }
        
        // Verify CSRF
        if (!$this->verifyCsrf()) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token. Please refresh the page and try again.'
            ], 403);
            return;
        }
        
        // Validate and sanitize input
        $data = $this->sanitizeInput($_POST);
        if (!$this->validator->validateTicket($data)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $this->validator->getErrors()
            ], 400);
            return;
        }
        
        // Create ticket
        try {
            $ticketId = $this->ticketModel->create($data);
            $ticket = $this->ticketModel->getById($ticketId);
            
            // Send email notification
            $emailSent = false;
            try {
                $emailService = new EmailService();
                $emailService->sendTicketNotification($ticket);
                $emailSent = true;
            } catch (\Exception $e) {
                error_log("Email notification failed: " . $e->getMessage());
            }
            
            // Generate new CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Build response message
            $message = "Ticket #{$ticketId} created successfully!";
            if ($emailSent) {
                $message .= " IT team has been notified.";
            }
            
            // Add priority-specific messaging
            $estimatedResponse = $this->getEstimatedResponse($data['priority']);
            
            $this->jsonResponse([
                'success' => true,
                'ticket_id' => $ticketId,
                'message' => $message,
                'estimated_response' => $estimatedResponse,
                'new_csrf_token' => $_SESSION['csrf_token']
            ]);
            
        } catch (\Exception $e) {
            error_log("IT ticket creation failed: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'An error occurred. Please try again or contact IT directly.'
            ], 500);
        }
    }
    
    /**
     * Display admin panel
     */
    public function showAdminPanel() {
        // Check if admin is logged in
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            header('Location: login.php');
            exit;
        }
        
        // Handle status updates
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'], $_POST['status'])) {
            $this->ticketModel->updateStatus($_POST['ticket_id'], $_POST['status']);
            header('Location: ' . $_SERVER['REQUEST_URI'] . '?updated=1');
            exit;
        }
        
        // Get tickets with optional filtering
        $status = $_GET['status'] ?? 'all';
        $tickets = $this->ticketModel->getAllWithStatus($status);
        $stats = $this->ticketModel->getStats();
        
        // Load admin view
        require_once __DIR__ . '/../../templates/views/admin/tickets.php';
    }
    
    /**
     * Handle admin login
     */
    public function handleAdminLogin() {
        error_log("=== LOGIN DEBUG START ===");
        error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            error_log("Username provided: " . $username);
            error_log("Password length: " . strlen($password));
            
            try {
                $authService = new \App\Services\AuthService();
                $result = $authService->authenticate($username, $password);
                error_log("Auth result: " . ($result ? "SUCCESS" : "FAILED"));
                
                if ($result) {
                    error_log("Session after auth: " . print_r($_SESSION, true));
                    header('Location: tickets.php');
                } else {
                    $loginError = 'Invalid credentials';
                }
            } catch (\Exception $e) {
                error_log("Auth exception: " . $e->getMessage());
                $loginError = 'Authentication error: ' . $e->getMessage();
            }
        }
        
        require_once __DIR__ . '/../../templates/views/admin/login.php';
    }
    
    /**
     * Handle admin logout
     */
    public function handleAdminLogout() {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    /**
     * API: Get ticket details
     */
    public function getTicket($id) {
        header('Content-Type: application/json');
        
        try {
            $ticket = $this->ticketModel->getById($id);
            if ($ticket) {
                $ticket['comments'] = $this->ticketModel->getComments($id);
                $this->jsonResponse(['success' => true, 'ticket' => $ticket]);
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'Ticket not found'], 404);
            }
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => 'An error occurred'], 500);
        }
    }
    
    /**
     * API: Add comment to ticket
     */
    public function addComment() {
        header('Content-Type: application/json');
        
        if (!$this->verifyCsrf()) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid security token'], 403);
            return;
        }
        
        $ticketId = $_POST['ticket_id'] ?? 0;
        $comment = trim($_POST['comment'] ?? '');
        $userName = $_SESSION['admin_username'] ?? 'System';
        $isInternal = isset($_POST['is_internal']) && $_POST['is_internal'] === '1';
        
        if (empty($comment)) {
            $this->jsonResponse(['success' => false, 'error' => 'Comment cannot be empty'], 400);
            return;
        }
        
        try {
            $this->ticketModel->addComment($ticketId, $userName, $comment, $isInternal);
            $this->jsonResponse(['success' => true, 'message' => 'Comment added successfully']);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => 'Failed to add comment'], 500);
        }
    }
    
    // Helper methods
    
    private function verifyCsrf() {
        return isset($_POST['csrf_token']) && 
               isset($_SESSION['csrf_token']) &&
               hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    
    private function sanitizeInput($data) {
        return [
            'name' => $this->validator->sanitize($data['name'] ?? ''),
            'location' => $this->validator->sanitize($data['location'] ?? ''),
            'category' => $this->validator->sanitize($data['category'] ?? ''),
            'priority' => $this->validator->sanitize($data['priority'] ?? 'normal'),
            'description' => $this->validator->sanitize($data['description'] ?? '')
        ];
    }
    
    private function jsonResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
    
    private function getLocations() {
        return ['Leonardtown', 'Odenton', 'Prince Frederick', 'Catonsville', 
                'Edgewater', 'Elkridge', 'Glen Burnie'];
    }
    
    private function getCategories() {
        return [
            'hardware' => '🖥️ Hardware',
            'software' => '💾 Software',
            'network' => '🌐 Network/Internet',
            'phone' => '📞 Phone System',
            'printer' => '🖨️ Printer',
            'email' => '📧 Email',
            'other' => '❓ Other'
        ];
    }
    
    private function getPriorities() {
        return [
            'low' => ['label' => 'Low', 'desc' => 'Can wait a few days', 'class' => 'priority-low'],
            'normal' => ['label' => 'Normal', 'desc' => 'Within 24 hours', 'class' => 'priority-normal'],
            'high' => ['label' => 'High', 'desc' => 'Urgent, affecting work', 'class' => 'priority-high'],
            'critical' => ['label' => 'Critical', 'desc' => 'System down, can\'t work', 'class' => 'priority-critical']
        ];
    }
    
    private function getEstimatedResponse($priority) {
        $responses = [
            'critical' => '2 hours - Please also call IT at 410-555-1234',
            'high' => '2-4 hours',
            'normal' => '24 hours',
            'low' => '2-3 business days'
        ];
        
        return $responses[$priority] ?? '24 hours';
    }
}
