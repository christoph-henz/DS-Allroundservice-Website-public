<?php
/**
 * Authentication API
 * Handles login, logout, session management and user permissions
 */

// Start output buffering FIRST (critical for session_start)
if (ob_get_level() === 0) {
    ob_start();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include the autoloader if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Include the Page class for database inheritance
require_once __DIR__ . '/../src/Views/Page.php';

use DSAllround\Views\Page;

class AuthAPI extends Page {
    private $sessionTimeout = 3600; // 1 hour default
    private $rememberMeTimeout = 2592000; // 30 days
    
    public function __construct() {
        parent::__construct();
    }
    
    public function handleRequest() {
        try {
            $action = $_REQUEST['action'] ?? '';
            
            switch ($action) {
                case 'login':
                    return $this->login();
                    
                case 'logout':
                    return $this->logout();
                    
                case 'check-session':
                    return $this->checkSession();
                    
                case 'get-user':
                    return $this->getCurrentUser();
                    
                case 'refresh-session':
                    return $this->refreshSession();
                    
                default:
                    return $this->error('Invalid action');
            }
        } catch (Exception $e) {
            return $this->error('Server error: ' . $e->getMessage());
        }
    }
    
    private function login() {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
        
        if (empty($username) || empty($password)) {
            return $this->error('Benutzername und Passwort sind erforderlich.');
        }
        
        // Find user by username or email
        $stmt = $this->_database->prepare("
            SELECT id, username, email, password_hash, first_name, last_name, role, 
                   is_active, login_attempts, locked_until 
            FROM users 
            WHERE (username = ? OR email = ?) AND is_active = 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $this->logFailedLogin($username, 'User not found');
            return $this->error('Ungültige Anmeldedaten.');
        }
        
        // Check if user is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $lockTime = date('H:i', strtotime($user['locked_until']));
            return $this->error("Konto gesperrt bis $lockTime. Zu viele Fehlversuche.");
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->incrementFailedLogins($user['id']);
            return $this->error('Ungültige Anmeldedaten.');
        }
        
        // Reset failed login attempts
        $this->resetFailedLogins($user['id']);
        
        // Create session
        $sessionToken = $this->createSession($user['id'], $remember);
        
        // Update last login
        $stmt = $this->_database->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Protokolliere erfolgreichen Login
        $this->logActivity($user['id'], 'login_success', json_encode([
            'username' => $user['username'],
            'role' => $user['role'],
            'remember_me' => $remember
        ]));
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['authenticated'] = true;
        
        // ✅ SECURITY: Generiere CSRF-Token für Admin-API
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // ✅ SECURITY: Session-Regeneration gegen Session-Fixation
        session_regenerate_id(true);
        
        return $this->success([
            'message' => 'Anmeldung erfolgreich',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role']
            ],
            'permissions' => $this->getUserPermissions($user['role']),
            'csrf_token' => $_SESSION['csrf_token'] // ✅ Token an Client senden
        ]);
    }
    
    private function logout() {
        $sessionToken = $_SESSION['session_token'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($sessionToken) {
            // Deactivate session in database
            $stmt = $this->_database->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = ?");
            $stmt->execute([$sessionToken]);
        }
        
        // Protokolliere Logout
        if ($userId) {
            $this->logActivity($userId, 'logout', json_encode([
                'session_token_prefix' => $sessionToken ? substr($sessionToken, 0, 8) . '...' : null
            ]));
        }
        
        // Clear session
        session_destroy();
        
        return $this->success(['message' => 'Erfolgreich abgemeldet']);
    }
    
    private function checkSession() {
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            return $this->success(['authenticated' => false]);
        }
        
        $sessionToken = $_SESSION['session_token'] ?? null;
        if (!$sessionToken) {
            return $this->success(['authenticated' => false]);
        }
        
        // Check if session is still valid in database
        $stmt = $this->_database->prepare("
            SELECT s.*, u.username, u.role, u.is_active 
            FROM user_sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.session_token = ? AND s.is_active = 1 AND s.expires_at > CURRENT_TIMESTAMP
        ");
        $stmt->execute([$sessionToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session || !$session['is_active']) {
            session_destroy();
            return $this->success(['authenticated' => false]);
        }
        
        // ✅ CSRF-Token generieren falls nicht vorhanden
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $this->success([
            'authenticated' => true,
            'user' => [
                'id' => $session['user_id'],
                'username' => $session['username'],
                'role' => $session['role']
            ],
            'permissions' => $this->getUserPermissions($session['role']),
            'csrf_token' => $_SESSION['csrf_token'] // ✅ Token zurückgeben
        ]);
    }
    
    private function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return $this->error('Nicht authentifiziert');
        }
        
        $stmt = $this->_database->prepare("
            SELECT id, username, email, first_name, last_name, role, last_login 
            FROM users WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return $this->error('Benutzer nicht gefunden');
        }
        
        return $this->success([
            'user' => $user,
            'permissions' => $this->getUserPermissions($user['role'])
        ]);
    }
    
    private function refreshSession() {
        if (!$this->isAuthenticated()) {
            return $this->error('Nicht authentifiziert');
        }
        
        $sessionToken = $_SESSION['session_token'] ?? null;
        if ($sessionToken) {
            // Extend session expiry
            $newExpiry = date('Y-m-d H:i:s', time() + $this->sessionTimeout);
            $stmt = $this->_database->prepare("UPDATE user_sessions SET expires_at = ? WHERE session_token = ?");
            $stmt->execute([$newExpiry, $sessionToken]);
        }
        
        return $this->success(['message' => 'Session erneuert']);
    }
    
    /**
     * Ermittelt die echte Client-IP-Adresse auch hinter Proxies/Load Balancern
     */
    private function getClientIP() {
        // Verschiedene Header für echte Client-IP prüfen
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // IPv6 Loopback zu IPv4 konvertieren für bessere Lesbarkeit
                if ($ip === '::1') {
                    $ip = '127.0.0.1';
                }
                
                // Validiere IP-Adresse
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // Fallback
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Protokolliert Benutzeraktivitäten für Sicherheits- und Audit-Zwecke
     */
    private function logActivity($userId, $action, $details = null, $success = true) {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Optional: Activity Log Tabelle erstellen falls nicht vorhanden
        $this->createActivityLogTableIfNotExists();
        
        try {
            $stmt = $this->_database->prepare("
                INSERT INTO user_activity_log (
                    user_id, action, details, ip_address, user_agent, 
                    success
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $action,
                $details,
                $ip,
                $userAgent,
                $success ? 1 : 0
            ]);
        } catch (Exception $e) {
            // Logging sollte die Hauptfunktion nicht unterbrechen
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Erstellt die Activity Log Tabelle falls sie nicht existiert
     */
    private function createActivityLogTableIfNotExists() {
        try {
            // Bestimme die richtigen Datentypen basierend auf der Datenbank
            $autoIncrement = $this->isMySQL() ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
            $intType = $this->isMySQL() ? 'INT' : 'INTEGER';
            $boolType = $this->isMySQL() ? 'TINYINT(1)' : 'INTEGER';
            
            $this->_database->exec("
                CREATE TABLE IF NOT EXISTS user_activity_log (
                    id $autoIncrement,
                    user_id $intType,
                    action VARCHAR(100) NOT NULL,
                    details TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    success $boolType DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");
        } catch (Exception $e) {
            error_log("Failed to create activity log table: " . $e->getMessage());
        }
    }

    private function createSession($userId, $remember = false) {
        $sessionToken = bin2hex(random_bytes(32));
        $expiryTime = $remember ? $this->rememberMeTimeout : $this->sessionTimeout;
        $expiresAt = date('Y-m-d H:i:s', time() + $expiryTime);
        
        $stmt = $this->_database->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $sessionToken,
            $this->getClientIP(), // Verwende verbesserte IP-Erkennung
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiresAt
        ]);
        
        // Protokolliere Session-Erstellung
        $this->logActivity($userId, 'session_created', json_encode([
            'remember' => $remember,
            'expires_at' => $expiresAt,
            'session_token_prefix' => substr($sessionToken, 0, 8) . '...'
        ]));
        
        return $sessionToken;
    }
    
    private function getUserPermissions($role) {
        $stmt = $this->_database->prepare("
            SELECT permission_key, permission_value 
            FROM user_permissions 
            WHERE role = ? AND permission_value = 1
        ");
        $stmt->execute([$role]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $permissionList = [];
        foreach ($permissions as $perm) {
            $permissionList[] = $perm['permission_key'];
        }
        
        return $permissionList;
    }
    
    private function incrementFailedLogins($userId) {
        // Get current attempts first
        $stmt = $this->_database->prepare("SELECT login_attempts FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentAttempts = $stmt->fetchColumn();
        
        $newAttempts = $currentAttempts + 1;
        
        if ($newAttempts >= 4) {
            // Lock account for 30 minutes using PHP timestamp
            $lockUntil = date('Y-m-d H:i:s', time() + 1800); // 30 minutes = 1800 seconds
            $stmt = $this->_database->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
            $stmt->execute([$newAttempts, $lockUntil, $userId]);
        } else {
            $stmt = $this->_database->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
            $stmt->execute([$newAttempts, $userId]);
        }
    }
    
    private function resetFailedLogins($userId) {
        $stmt = $this->_database->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    private function logFailedLogin($username, $reason) {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Log zu error_log
        error_log("Failed login attempt: $username - $reason - IP: $ip - User-Agent: $userAgent");
        
        // Log zu Datenbank (ohne user_id da Login fehlgeschlagen)
        $this->createActivityLogTableIfNotExists();
        
        try {
            $stmt = $this->_database->prepare("
                INSERT INTO user_activity_log (
                    user_id, action, details, ip_address, user_agent, 
                    success
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                null, // Keine user_id bei fehlgeschlagenem Login
                'login_failed',
                json_encode([
                    'username' => $username,
                    'reason' => $reason
                ]),
                $ip,
                $userAgent,
                0 // success = false
            ]);
        } catch (Exception $e) {
            error_log("Failed to log login attempt to database: " . $e->getMessage());
        }
    }
    
    public function isAuthenticated() {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
    
    public function hasPermission($permission) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $role = $_SESSION['role'] ?? null;
        if (!$role) {
            return false;
        }
        
        $stmt = $this->_database->prepare("
            SELECT permission_value 
            FROM user_permissions 
            WHERE role = ? AND permission_key = ?
        ");
        $stmt->execute([$role, $permission]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['permission_value'] == 1;
    }
    
    private function success($data = []) {
        return json_encode(array_merge(['success' => true], $data));
    }
    
    private function error($message) {
        return json_encode(['success' => false, 'message' => $message]);
    }
}

// Handle the request
$auth = new AuthAPI();
echo $auth->handleRequest();