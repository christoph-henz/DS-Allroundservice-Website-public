<?php
/**
 * Authentication Middleware
 * Protects admin pages and handles redirects
 */

// Load dependencies
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/Page.php';

use DSAllround\Views\Page;

// Start output buffering FIRST (critical for session)
if (ob_get_level() === 0) {
    ob_start();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

class AuthMiddleware extends Page {
    private $publicPages = [
        '/login',
        '/api/auth.php',
        '/index.php',
        '/'
    ];
    
    public function __construct() {
        // Use PDO like the main application
        parent::__construct();
    }
    
    public function protect($requiredPermission = null) {
        $currentPage = $_SERVER['REQUEST_URI'];
        $currentPage = parse_url($currentPage, PHP_URL_PATH);
        
        // Skip protection for public pages
        if ($this->isPublicPage($currentPage)) {
            return;
        }
        
        // Check if user is authenticated
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin($currentPage);
            return;
        }
        
        // Check specific permission if required
        if ($requiredPermission && !$this->hasPermission($requiredPermission)) {
            $this->accessDenied();
            return;
        }
        
        // Refresh session on admin page access
        $this->refreshSession();
    }
    
    public function isAuthenticated() {
        // Check if user is authenticated via auth.php API (primary method)
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true &&
            isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
            return true;
        }
        
        // Fallback: check legacy last_activity method
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['username']) && 
               isset($_SESSION['last_activity']) &&
               (time() - $_SESSION['last_activity'] < 1800); // 30 minutes
    }
    
    /**
     * Check session and return user info if authenticated
     * @return array|null User info array or null if not authenticated
     */
    public function checkSession() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $this->getCurrentUser();
    }
    
    public function hasPermission($permission) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $userId = $_SESSION['user_id'];
        
        // Get user role
        $stmt = $this->_database->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();
        
        if (!$role) {
            return false;
        }
        
        // Check permission from user_permissions table
        try {
            $stmt = $this->_database->prepare("
                SELECT permission_value 
                FROM user_permissions 
                WHERE role = ? AND permission_key = ?
            ");
            $stmt->execute([$role, $permission]);
            $permissionValue = $stmt->fetchColumn();
            
            // Return true if permission exists and is set to 1, false otherwise
            return $permissionValue === 1 || $permissionValue === '1';
            
        } catch (PDOException $e) {
            error_log("Error checking permission: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all permissions for a specific role
     * @param string $role The role to get permissions for
     * @return array Array of permission keys where value is true
     */
    public function getRolePermissions($role = null) {
        if (!$role) {
            if (!$this->isAuthenticated()) {
                return [];
            }
            
            $userId = $_SESSION['user_id'];
            
            // Get user role
            $stmt = $this->_database->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $role = $stmt->fetchColumn();
            
            if (!$role) {
                return [];
            }
        }
        
        try {
            $stmt = $this->_database->prepare("
                SELECT permission_key, permission_value 
                FROM user_permissions 
                WHERE role = ? AND permission_value = 1
            ");
            $stmt->execute([$role]);
            $permissions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Return only the permission keys where value is true
            return array_keys($permissions);
            
        } catch (PDOException $e) {
            error_log("Error getting role permissions: " . $e->getMessage());
            return [];
        }
    }
    
    private function isPublicPage($page) {
        foreach ($this->publicPages as $publicPage) {
            if ($page === $publicPage || strpos($page, $publicPage) === 0) {
                return true;
            }
        }
        return false;
    }
    
    private function redirectToLogin($returnUrl = null) {
        $loginUrl = '/login';
        if ($returnUrl && $returnUrl !== '/') {
            $loginUrl .= '?redirect=' . urlencode($returnUrl);
        }
        
        header('Location: ' . $loginUrl);
        exit();
    }
    
    private function accessDenied() {
        http_response_code(403);
        echo '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Zugriff verweigert - DS Allroundservice</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                .error-icon { font-size: 4rem; color: #e74c3c; margin-bottom: 20px; }
                h1 { color: #2c3e50; margin-bottom: 20px; }
                p { color: #7f8c8d; margin-bottom: 30px; }
                .btn { background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
                .btn:hover { background: #2980b9; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-icon">🔒</div>
                <h1>Zugriff verweigert</h1>
                <p>Sie haben nicht die erforderlichen Berechtigungen für diese Seite.</p>
                <a href="/admin" class="btn">Zurück zum Dashboard</a>
                <a href="/api/auth.php?action=logout" class="btn" style="background: #e74c3c; margin-left: 10px;">Abmelden</a>
            </div>
        </body>
        </html>';
        exit();
    }
    
    private function refreshSession() {
        // Extend session if user is active
        if (isset($_SESSION['last_activity'])) {
            $inactiveTime = time() - $_SESSION['last_activity'];
            if ($inactiveTime > 1800) { // 30 minutes
                session_destroy();
                $this->redirectToLogin();
                return;
            }
        }
        $_SESSION['last_activity'] = time();
    }
    
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
}