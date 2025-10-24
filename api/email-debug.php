<?php
/**
 * Email API Test - Debug API für E-Mail-System
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Check if IMAP is available
    $imap_available = extension_loaded('imap');
    
    echo json_encode([
        'success' => true,
        'debug_info' => [
            'imap_extension_loaded' => $imap_available,
            'php_version' => PHP_VERSION,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $imap_available ? 'IMAP verfügbar' : 'IMAP nicht verfügbar - Fallback wird verwendet'
        ],
        'test_emails' => $imap_available ? 'Echte IMAP-Verbindung' : 'Mock-Daten werden verwendet'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'imap_extension_loaded' => extension_loaded('imap'),
            'php_version' => PHP_VERSION,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);
}
?>