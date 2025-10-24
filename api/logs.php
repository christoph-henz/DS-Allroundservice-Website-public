<?php
declare(strict_types=1);

/**
 * Logs API Endpoint
 * Handles activity logs, user sessions, and email logs
 * Only accessible to Admin users
 */

require_once __DIR__ . '/../auth_middleware.php';

header('Content-Type: application/json');

// Initialize authentication
$auth = new AuthMiddleware();
$user = $auth->checkSession();

// Check if user is authenticated and is Admin
if (!$user || $user['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Zugriff verweigert. Nur Administratoren können auf Logs zugreifen.'
    ]);
    exit;
}

// Database connection
try {
    $dbPath = __DIR__ . '/../data/database.sqlite';
    $isLocal = true;
    
    if (file_exists($dbPath)) {
        $db = new PDO('sqlite:' . $dbPath);
    } else {
        // Production MySQL connection
        $isLocal = false;
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Datenbankverbindungsfehler'
    ]);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get-activity-logs':
            getActivityLogs($db, $isLocal);
            break;
            
        case 'get-sessions-logs':
            getSessionsLogs($db, $isLocal);
            break;
            
        case 'get-email-logs':
            getEmailLogs($db, $isLocal);
            break;
            
        case 'export-activity-logs':
            exportActivityLogs($db, $isLocal);
            break;
            
        case 'export-sessions-logs':
            exportSessionsLogs($db, $isLocal);
            break;
            
        case 'export-email-logs':
            exportEmailLogs($db, $isLocal);
            break;
            
        case 'terminate-session':
            terminateSession($db);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ungültige Aktion'
            ]);
    }
} catch (Exception $e) {
    error_log("Logs API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler: ' . $e->getMessage()
    ]);
}

/**
 * Get activity logs with filtering, sorting, and pagination
 */
function getActivityLogs($db, $isLocal) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $sortField = $_GET['sort'] ?? 'timestamp';
    $sortDirection = $_GET['direction'] ?? 'desc';
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if (!empty($_GET['search'])) {
        $where[] = "(al.action LIKE :search OR al.details LIKE :search OR u.username LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    
    if (!empty($_GET['user'])) {
        $where[] = "al.user_id = :user_id";
        $params[':user_id'] = $_GET['user'];
    }
    
    if (!empty($_GET['action'])) {
        $where[] = "al.action = :action";
        $params[':action'] = $_GET['action'];
    }
    
    if (!empty($_GET['date'])) {
        if ($isLocal) {
            $where[] = "DATE(al.timestamp) = :date";
        } else {
            $where[] = "DATE(al.timestamp) = :date";
        }
        $params[':date'] = $_GET['date'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Validate sort field
    $allowedSortFields = ['timestamp', 'user_id', 'action', 'ip_address'];
    if (!in_array($sortField, $allowedSortFields)) {
        $sortField = 'timestamp';
    }
    
    $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM activity_log al 
                 LEFT JOIN users u ON al.user_id = u.id 
                 $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get logs
    $sql = "SELECT al.*, u.username 
            FROM activity_log al 
            LEFT JOIN users u ON al.user_id = u.id 
            $whereClause 
            ORDER BY al.$sortField $sortDirection 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalRecords / $limit),
            'total_records' => $totalRecords,
            'per_page' => $limit
        ]
    ]);
}

/**
 * Get user sessions logs with filtering, sorting, and pagination
 */
function getSessionsLogs($db, $isLocal) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $sortField = $_GET['sort'] ?? 'created_at';
    $sortDirection = $_GET['direction'] ?? 'desc';
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if (!empty($_GET['search'])) {
        $where[] = "(u.username LIKE :search OR us.ip_address LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    
    if (!empty($_GET['user'])) {
        $where[] = "us.user_id = :user_id";
        $params[':user_id'] = $_GET['user'];
    }
    
    if (!empty($_GET['status'])) {
        $where[] = "us.status = :status";
        $params[':status'] = $_GET['status'];
    }
    
    if (!empty($_GET['date'])) {
        if ($isLocal) {
            $where[] = "DATE(us.created_at) = :date";
        } else {
            $where[] = "DATE(us.created_at) = :date";
        }
        $params[':date'] = $_GET['date'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Validate sort field
    $allowedSortFields = ['created_at', 'user_id', 'ip_address', 'last_activity', 'status'];
    if (!in_array($sortField, $allowedSortFields)) {
        $sortField = 'created_at';
    }
    
    $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM user_sessions us 
                 LEFT JOIN users u ON us.user_id = u.id 
                 $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get sessions
    $sql = "SELECT us.*, u.username 
            FROM user_sessions us 
            LEFT JOIN users u ON us.user_id = u.id 
            $whereClause 
            ORDER BY us.$sortField $sortDirection 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $sessions = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'logs' => $sessions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalRecords / $limit),
            'total_records' => $totalRecords,
            'per_page' => $limit
        ]
    ]);
}

/**
 * Get email logs with filtering, sorting, and pagination
 */
function getEmailLogs($db, $isLocal) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $sortField = $_GET['sort'] ?? 'timestamp';
    $sortDirection = $_GET['direction'] ?? 'desc';
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if (!empty($_GET['search'])) {
        $where[] = "(el.recipient LIKE :search OR el.subject LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    
    if (!empty($_GET['status'])) {
        $where[] = "el.status = :status";
        $params[':status'] = $_GET['status'];
    }
    
    if (!empty($_GET['type'])) {
        $where[] = "el.type = :type";
        $params[':type'] = $_GET['type'];
    }
    
    if (!empty($_GET['date'])) {
        if ($isLocal) {
            $where[] = "DATE(el.timestamp) = :date";
        } else {
            $where[] = "DATE(el.timestamp) = :date";
        }
        $params[':date'] = $_GET['date'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Validate sort field
    $allowedSortFields = ['timestamp', 'recipient', 'subject', 'type', 'status'];
    if (!in_array($sortField, $allowedSortFields)) {
        $sortField = 'timestamp';
    }
    
    $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM email_logs el $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get emails
    $sql = "SELECT el.* 
            FROM email_logs el 
            $whereClause 
            ORDER BY el.$sortField $sortDirection 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $emails = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'logs' => $emails,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalRecords / $limit),
            'total_records' => $totalRecords,
            'per_page' => $limit
        ]
    ]);
}

/**
 * Export activity logs to CSV
 */
function exportActivityLogs($db, $isLocal) {
    // Build WHERE clause (same as getActivityLogs)
    $where = [];
    $params = [];
    
    if (!empty($_GET['search'])) {
        $where[] = "(al.action LIKE :search OR al.details LIKE :search OR u.username LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }
    
    if (!empty($_GET['user'])) {
        $where[] = "al.user_id = :user_id";
        $params[':user_id'] = $_GET['user'];
    }
    
    if (!empty($_GET['action'])) {
        $where[] = "al.action = :action";
        $params[':action'] = $_GET['action'];
    }
    
    if (!empty($_GET['date'])) {
        $where[] = $isLocal ? "DATE(al.timestamp) = :date" : "DATE(al.timestamp) = :date";
        $params[':date'] = $_GET['date'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT al.timestamp, u.username, al.action, al.details, al.ip_address 
            FROM activity_log al 
            LEFT JOIN users u ON al.user_id = u.id 
            $whereClause 
            ORDER BY al.timestamp DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity-logs-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Zeitstempel', 'Benutzer', 'Aktion', 'Details', 'IP-Adresse']);
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['timestamp'],
            $log['username'] ?? 'System',
            $log['action'],
            $log['details'],
            $log['ip_address']
        ]);
    }
    
    fclose($output);
    exit;
}

/**
 * Export sessions logs to CSV
 */
function exportSessionsLogs($db, $isLocal) {
    // Similar implementation as exportActivityLogs
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sessions-logs-' . date('Y-m-d') . '.csv"');
    
    echo "Export für Sitzungs-Logs wird noch implementiert\n";
    exit;
}

/**
 * Export email logs to CSV
 */
function exportEmailLogs($db, $isLocal) {
    // Similar implementation as exportActivityLogs
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="email-logs-' . date('Y-m-d') . '.csv"');
    
    echo "Export für E-Mail-Logs wird noch implementiert\n";
    exit;
}

/**
 * Terminate a user session (Admin only)
 */
function terminateSession($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    $sessionId = $data['session_id'] ?? '';
    
    if (empty($sessionId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Sitzungs-ID fehlt'
        ]);
        return;
    }
    
    // Update session status
    $stmt = $db->prepare("UPDATE user_sessions SET status = 'logged_out' WHERE session_id = :session_id");
    $stmt->execute([':session_id' => $sessionId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Sitzung erfolgreich beendet'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Sitzung nicht gefunden'
        ]);
    }
}
