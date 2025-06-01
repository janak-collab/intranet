<?php
namespace App\Services;

use App\Database\Connection;
use PDO;

class AuthService {
    private $db;
    
    public function __construct() {
        $this->db = Connection::getInstance()->getConnection();
    }
    
    public function authenticate($username, $password) {
        $stmt = $this->db->prepare("
            SELECT id, username, password_hash, role 
            FROM users 
            WHERE username = :username AND active = 1
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        // Create session
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        
        return true;
    }
    
    public function logout() {
        $_SESSION = [];
        session_destroy();
    }
}
