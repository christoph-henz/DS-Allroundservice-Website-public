<?php
/**
 * API endpoint for questionnaire submission
 * Handles form submissions, database storage, and PDF generation
 */

// CRITICAL: Absolute error suppression for Apache CGI
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Custom error handler that logs but doesn't display
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true; // Suppress error display
});

// Custom exception handler
set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage());
    error_log("File: " . $exception->getFile() . " Line: " . $exception->getLine());
    error_log("Stack trace: " . $exception->getTraceAsString());
    
    // Clear any output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server-Fehler: ' . $exception->getMessage(),
        'debug' => [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'type' => get_class($exception)
        ]
    ]);
    exit;
});

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("PHP Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        // Clear any output
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server-Fehler: ' . $error['message'],
            'debug' => [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => 'Fatal Error'
            ]
        ]);
        exit;
    }
});

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Start output buffering to catch any unexpected output
ob_start();

// Set content type and CORS headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the autoloader if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Include the Page class for database inheritance
require_once __DIR__ . '/../src/Views/Page.php';

use DSAllround\Views\Page;
use DSAllround\Utils\QuestionnaireSubmissionHandler;

class QuestionnaireAPI extends Page {
    
    public function __construct() {
        parent::__construct();
        
        // CRITICAL: Re-apply error suppression after Page::__construct()
        // Page.php may override error_reporting, causing 500 errors in Apache CGI
        error_reporting(0);
        ini_set('display_errors', '0');
        set_error_handler(function() { return true; });
    }
    
    public function handleRequest() {
        try {
            // Only allow POST requests
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                return $this->error('Method not allowed. Use POST.');
            }
            
            // Get POST data - handle both JSON and form data
            $postData = $this->getPostData();
            
            // Basic validation
            if (empty($postData)) {
                throw new \Exception('No form data received');
            }
            
            // DEBUG: Log received data structure
            error_log("API Debug - Received " . count($postData) . " fields: " . implode(', ', array_keys($postData)));
            
            // Extract service slug - support both new (service_slug) and old (service_type) formats
            $serviceSlug = $postData['service_slug'] ?? $postData['service_type'] ?? null;
            
            if (!$serviceSlug) {
                throw new \Exception('Service slug is required');
            }
            
            // Process the submission using inherited database connection
            return $this->processSubmission($postData, $serviceSlug);
            
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }
    
    private function getPostData() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            // Handle JSON data
            $jsonInput = file_get_contents('php://input');
            $postData = json_decode($jsonInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON data: ' . json_last_error_msg());
            }
            
            return $postData;
        } else {
            // Handle form data
            return $_POST;
        }
    }
    
    private function processSubmission($postData, $serviceSlug) {
        // Create submission handler with inherited database connection
        $handler = new QuestionnaireSubmissionHandler($this->_database);
        
        // Process the submission
        $result = $handler->processSubmission($postData, $serviceSlug);
        
        if ($result['success']) {
            // Store information in session for success page
            $_SESSION['last_reference'] = $result['reference'];
            $_SESSION['last_service'] = $result['service']['slug'] ?? $serviceSlug;
            $_SESSION['last_service_name'] = $result['service']['name'] ?? '';
            $_SESSION['last_submission_id'] = $result['submission_id'];
            
            // Check if this is an AJAX request
            $isAjax = $this->isAjaxRequest();
            
            // Additional debug logging
            error_log("API Debug - AJAX Detection: " . ($isAjax ? 'true' : 'false') . 
                     ", Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set') . 
                     ", X-Requested-With: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set'));
            
            if ($isAjax) {
                return $this->handleAjaxSuccess($result, $serviceSlug);
            } else {
                return $this->handleFormSuccess($result, $serviceSlug);
            }
            
        } else {
            throw new \Exception($result['error']);
        }
    }
    
    private function isAjaxRequest() {
        // Modern fetch() API doesn't always set X-Requested-With, so check Content-Type too
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               (isset($_SERVER['CONTENT_TYPE']) && 
                strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
    }
    
    private function handleAjaxSuccess($result, $serviceSlug) {
        // Clear any output buffer to ensure clean JSON response
        if (ob_get_length()) {
            ob_clean();
        }
        
        // For AJAX requests, return JSON with redirect URL (no GET parameters)
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'reference' => $result['reference'],
            'redirect' => '/questionnaire-success',
            'message' => 'Ihre Anfrage wurde erfolgreich übermittelt.',
            'submission_id' => $result['submission_id'],
            'service_name' => $result['service']['name'],
            'pdf_generated' => $result['pdf_result']['success'] ?? false,
            'is_production' => $result['is_production']
        ]);
        
        // Ensure output buffer is flushed and exit
        if (ob_get_length()) {
            ob_end_flush();
        }
        
        // Log successful AJAX submission
        error_log("API Success (AJAX) - Reference: {$result['reference']}");
        exit;
    }
    
    private function handleFormSuccess($result, $serviceSlug) {
        // For regular form submissions, redirect directly (no GET parameters)
        header('Location: /questionnaire-success');
        
        // Log successful form submission
        error_log("API Success (Form) - Reference: {$result['reference']}");
        exit;
    }
    
    private function handleError(\Exception $e) {
        // Clear any output buffer to prevent HTML output
        if (ob_get_length()) {
            ob_clean();
        }
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ]);
        
        // Log error for debugging
        error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        
        // Ensure output buffer is flushed
        if (ob_get_length()) {
            ob_end_flush();
        }
        
        exit;
    }
    
    private function error($message) {
        return json_encode(['success' => false, 'error' => $message]);
    }
}

// Handle the request
$api = new QuestionnaireAPI();
$api->handleRequest();
