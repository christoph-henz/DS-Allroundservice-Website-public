<?php
/**
 * API endpoint to clear success page session data
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json; charset=UTF-8');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Clear success-related session data
    unset($_SESSION['last_reference']);
    unset($_SESSION['last_service']);
    unset($_SESSION['last_service_name']);
    unset($_SESSION['last_submission_id']);
    
    // Log the action
    error_log("Success session cleared - User refreshed success page");
    
    // Return success
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Session data cleared successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
