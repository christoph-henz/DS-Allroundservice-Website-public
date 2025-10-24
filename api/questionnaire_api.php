<?php
session_start();

// Enable error reporting for debugging, but don't display errors (they mess up JSON)
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Changed from 1 to 0
ini_set('log_errors', 1);

// Clean any previous output
if (ob_get_level()) {
    ob_clean();
}

// Include PDF generator
require_once __DIR__ . '/../src/Utils/PDFGenerator.php';
use DSAllround\Utils\PDFGenerator;

// Log all received data for debugging
error_log("API called with POST data: " . print_r($_POST, true));
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set'));

// Determine if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$acceptsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

error_log("isAjax: " . ($isAjax ? 'true' : 'false'));
error_log("acceptsJson: " . ($acceptsJson ? 'true' : 'false'));

// Set appropriate headers
if ($isAjax || $acceptsJson) {
    header('Content-Type: application/json');
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax || $acceptsJson) {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    } else {
        header('Location: /');
    }
    exit;
}

// Handle both FormData and JSON input
$data = [];

// Check if it's JSON input
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    // Parse JSON input
    $input = file_get_contents('php://input');
    $jsonData = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        if ($isAjax || $acceptsJson) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
        } else {
            header('Location: /?error=invalid_data');
        }
        exit;
    }
    $data = $jsonData;
} else {
    // Handle FormData (multipart/form-data or application/x-www-form-urlencoded)
    $data = $_POST;
}

// CSRF Token validation
if (isset($data['token']) && isset($_SESSION['token'])) {
    if (!hash_equals($_SESSION['token'], $data['token'])) {
        if ($isAjax || $acceptsJson) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
        } else {
            header('Location: /?error=csrf_token');
        }
        exit;
    }
} else {
    // For development, allow requests without CSRF token
    // In production, uncomment the following lines:
    // if ($isAjax || $acceptsJson) {
    //     http_response_code(403);
    //     echo json_encode(['error' => 'CSRF token required']);
    // } else {
    //     header('Location: /?error=csrf_token');
    // }
    // exit;
}

// Validate required fields
$requiredFields = ['name', 'email', 'phone'];
$missingFields = [];

// Map service_type to service for backward compatibility
if (!empty($data['service_type'])) {
    $data['service'] = $data['service_type'];
}

// Check if service field exists
if (empty($data['service'])) {
    $missingFields[] = 'service';
}

// Map question fields to expected API fields based on common patterns
// This mapping assumes the first few questions are always: Name, Email, Phone
$fieldMappings = [
    'question_0' => 'name',
    'question_1' => 'email', 
    'question_2' => 'phone'
];

// Apply field mappings
foreach ($fieldMappings as $questionField => $apiField) {
    if (!empty($data[$questionField]) && empty($data[$apiField])) {
        $data[$apiField] = $data[$questionField];
        error_log("Mapped {$questionField} to {$apiField}: " . $data[$questionField]);
    }
}

// Validate required fields
$requiredFields = ['name', 'email', 'phone'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $missingFields[] = $field;
        error_log("Missing required field: {$field}");
    }
}

if (!empty($missingFields)) {
    if ($isAjax || $acceptsJson) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Missing required fields',
            'fields' => $missingFields
        ]);
    } else {
        header('Location: /?error=missing_fields');
    }
    exit;
}

// Validate email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    if ($isAjax || $acceptsJson) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
    } else {
        header('Location: /?error=invalid_email');
    }
    exit;
}

// Process the questionnaire data
try {
    $questionnaire = new QuestionnaireProcessor($data);
    $result = $questionnaire->process();
    
    // Store reference and service in session for success page
    $_SESSION['last_reference'] = $result['reference'];
    $_SESSION['last_service'] = $data['service_type'] ?? $data['service'] ?? '';
    
    // Send appropriate response
    if ($isAjax || $acceptsJson) {
        echo json_encode([
            'success' => true,
            'message' => 'Questionnaire submitted successfully',
            'reference' => $result['reference'],
            'redirect' => '/anfrage-erfolgreich?ref=' . urlencode($result['reference'])
        ]);
    } else {
        // Direct form submission - redirect to success page
        header('Location: /anfrage-erfolgreich?ref=' . urlencode($result['reference']) . '&service=' . urlencode($data['service_type'] ?? $data['service'] ?? ''));
    }
    
} catch (Exception $e) {
    error_log('Questionnaire processing error: ' . $e->getMessage());
    
    if ($isAjax || $acceptsJson) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Internal server error',
            'message' => 'Could not process questionnaire'
        ]);
    } else {
        header('Location: /?error=processing_failed');
    }
}

class QuestionnaireProcessor {
    private $data;
    private $serviceTypes = ['umzug', 'transport', 'entruempelung', 'aufloesung'];
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    public function process() {
        // Validate service type
        if (!in_array($this->data['service'], $this->serviceTypes)) {
            throw new Exception('Invalid service type');
        }
        
        // Generate reference number
        $reference = $this->generateReference();
        
        // Save to database or file
        $this->save($reference);
        
        // Send email notification
        $this->sendNotification($reference);
        
        return ['reference' => $reference];
    }
    
    private function generateReference() {
        $serviceCode = strtoupper(substr($this->data['service'], 0, 3));
        $timestamp = date('Ymd');
        $random = rand(1000, 9999);
        
        return $serviceCode . '-' . $timestamp . '-' . $random;
    }
    
    private function save($reference) {
        // Create data directory if it doesn't exist
        $dataDir = __DIR__ . '/../data/questionnaires';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // Prepare data for storage
        $storage = [
            'reference' => $reference,
            'service' => $this->data['service'],
            'timestamp' => date('c'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'data' => $this->data
        ];
        
        // Save to JSON file
        $filename = $dataDir . '/' . $reference . '.json';
        file_put_contents($filename, json_encode($storage, JSON_PRETTY_PRINT));
        
        // Also append to daily log for easy overview
        $logFile = $dataDir . '/submissions_' . date('Y-m-d') . '.log';
        $logEntry = date('H:i:s') . " | {$reference} | {$this->data['service']} | {$this->data['name']} | {$this->data['email']}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendNotification($reference) {
        $service = ucfirst($this->data['service']);
        $subject = "Neue {$service}-Anfrage: {$reference}";
        
        // Generate PDF
        $pdfGenerator = new PDFGenerator($this->data, $reference);
        $pdfContent = $pdfGenerator->generatePDF();
        $pdfFilename = "Anfrage_{$reference}.pdf";
        
        // Prepare email content
        $message = $this->buildEmailMessage($reference);
        
        $businessEmail = 'info@ds-allroundservice.de';
        
        // Check if we're in local development environment
        if ($this->isLocalEnvironment()) {
            // Save PDF locally in /data/
            $dataDir = __DIR__ . '/../../data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            $pdfPath = $dataDir . '/' . $pdfFilename;
            file_put_contents($pdfPath, $pdfContent);
            
            // Log email instead of sending in local environment
            error_log("=== EMAIL NOTIFICATION (LOCAL) ===");
            error_log("To: {$businessEmail}");
            error_log("Subject: {$subject}");
            error_log("Message: {$message}");
            error_log("PDF Attachment: {$pdfPath} (saved locally)");
            error_log("PDF Size: " . strlen($pdfContent) . " bytes");
            error_log("=== END EMAIL ===");
        } else {
            // Send actual email with PDF attachment in production
            $this->sendEmailWithPDFAttachment($businessEmail, $subject, $message, $pdfContent, $pdfFilename);
        }
        
        // Send confirmation to customer
        $this->sendCustomerConfirmation($reference);
    }
    
    private function buildEmailMessage($reference) {
        $service = ucfirst($this->data['service']);
        
        $html = "
        <h2>Neue {$service}-Anfrage: {$reference}</h2>
        <h3>Kontaktdaten:</h3>
        <p>
            <strong>Name:</strong> {$this->data['name']}<br>
            <strong>E-Mail:</strong> {$this->data['email']}<br>
            <strong>Telefon:</strong> {$this->data['phone']}<br>
        </p>
        
        <h3>Service-spezifische Angaben:</h3>
        ";
        
        // Add service-specific details
        switch ($this->data['service']) {
            case 'umzug':
                $html .= $this->buildUmzugDetails();
                break;
            case 'transport':
                $html .= $this->buildTransportDetails();
                break;
            case 'entruempelung':
                $html .= $this->buildEntruempelungDetails();
                break;
            case 'aufloesung':
                $html .= $this->buildAufloesungDetails();
                break;
        }
        
        if (!empty($this->data['notes'])) {
            $html .= "<h3>Zusätzliche Anmerkungen:</h3><p>" . nl2br(htmlspecialchars($this->data['notes'])) . "</p>";
        }
        
        $html .= "<p><small>Eingereicht am: " . date('d.m.Y H:i:s') . "</small></p>";
        
        return $html;
    }
    
    private function buildUmzugDetails() {
        $html = "<p>";
        if (!empty($this->data['current_address'])) {
            $html .= "<strong>Aktuelle Adresse:</strong> " . htmlspecialchars($this->data['current_address']) . "<br>";
        }
        if (!empty($this->data['new_address'])) {
            $html .= "<strong>Neue Adresse:</strong> " . htmlspecialchars($this->data['new_address']) . "<br>";
        }
        if (!empty($this->data['rooms'])) {
            $html .= "<strong>Zimmerzahl:</strong> " . htmlspecialchars($this->data['rooms']) . "<br>";
        }
        if (!empty($this->data['moving_date'])) {
            $html .= "<strong>Umzugsdatum:</strong> " . htmlspecialchars($this->data['moving_date']) . "<br>";
        }
        $html .= "</p>";
        return $html;
    }
    
    private function buildTransportDetails() {
        $html = "<p>";
        if (!empty($this->data['pickup_address'])) {
            $html .= "<strong>Abholadresse:</strong> " . htmlspecialchars($this->data['pickup_address']) . "<br>";
        }
        if (!empty($this->data['delivery_address'])) {
            $html .= "<strong>Lieferadresse:</strong> " . htmlspecialchars($this->data['delivery_address']) . "<br>";
        }
        if (!empty($this->data['transport_date'])) {
            $html .= "<strong>Transportdatum:</strong> " . htmlspecialchars($this->data['transport_date']) . "<br>";
        }
        if (!empty($this->data['transport_type'])) {
            $html .= "<strong>Transportart:</strong> " . htmlspecialchars($this->data['transport_type']) . "<br>";
        }
        $html .= "</p>";
        return $html;
    }
    
    private function buildEntruempelungDetails() {
        $html = "<p>";
        if (!empty($this->data['property_address'])) {
            $html .= "<strong>Objekt-Adresse:</strong> " . htmlspecialchars($this->data['property_address']) . "<br>";
        }
        if (!empty($this->data['object_type'])) {
            $html .= "<strong>Objektart:</strong> " . htmlspecialchars($this->data['object_type']) . "<br>";
        }
        if (!empty($this->data['rooms'])) {
            $html .= "<strong>Raumanzahl:</strong> " . htmlspecialchars($this->data['rooms']) . "<br>";
        }
        if (!empty($this->data['preferred_date'])) {
            $html .= "<strong>Wunschdatum:</strong> " . htmlspecialchars($this->data['preferred_date']) . "<br>";
        }
        $html .= "</p>";
        return $html;
    }
    
    private function buildAufloesungDetails() {
        $html = "<p>";
        if (!empty($this->data['property_address'])) {
            $html .= "<strong>Objekt-Adresse:</strong> " . htmlspecialchars($this->data['property_address']) . "<br>";
        }
        if (!empty($this->data['household_size'])) {
            $html .= "<strong>Haushaltsgröße:</strong> " . htmlspecialchars($this->data['household_size']) . "<br>";
        }
        if (!empty($this->data['timeline'])) {
            $html .= "<strong>Zeitrahmen:</strong> " . htmlspecialchars($this->data['timeline']) . "<br>";
        }
        if (!empty($this->data['valuable_items'])) {
            $html .= "<strong>Wertgegenstände vorhanden:</strong> " . (($this->data['valuable_items'] === 'yes') ? 'Ja' : 'Nein') . "<br>";
        }
        $html .= "</p>";
        return $html;
    }
    
    private function sendCustomerConfirmation($reference) {
        $service = ucfirst($this->data['service']);
        $subject = "Bestätigung Ihrer {$service}-Anfrage: {$reference}";
        
        $message = "
        <h2>Vielen Dank für Ihre Anfrage!</h2>
        <p>Liebe/r {$this->data['name']},</p>
        <p>wir haben Ihre {$service}-Anfrage mit der Referenznummer <strong>{$reference}</strong> erhalten.</p>
        <p>Unser Team wird sich innerhalb von 24 Stunden bei Ihnen melden, um alle Details zu besprechen und Ihnen ein individuelles Angebot zu unterbreiten.</p>
        <p>Bei dringenden Fragen erreichen Sie uns telefonisch unter: <strong>+49 123 456 789</strong></p>
        <p>Mit freundlichen Grüßen<br>Ihr DS Allroundservice Team</p>
        <hr>
        <p><small>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese E-Mail.</small></p>
        ";
        
        $headers = [
            'From: DS Allroundservice <noreply@ds-allroundservice.de>',
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        // Check if we're in local development environment
        if ($this->isLocalEnvironment()) {
            // Log email instead of sending in local environment
            error_log("=== CUSTOMER CONFIRMATION EMAIL (LOCAL) ===");
            error_log("To: {$this->data['email']}");
            error_log("Subject: {$subject}");
            error_log("Message: {$message}");
            error_log("Headers: " . implode(", ", $headers));
            error_log("=== END EMAIL ===");
        } else {
            // Send actual email in production
            @mail($this->data['email'], $subject, $message, implode("\r\n", $headers));
        }
    }
    
    /**
     * Check if we're running in a local development environment
     */
    private function isLocalEnvironment() {
        $localHosts = ['localhost', '127.0.0.1', '::1'];
        $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        
        // Check for common local development indicators
        return in_array($currentHost, $localHosts) || 
               strpos($currentHost, '.local') !== false ||
               strpos($currentHost, '.dev') !== false ||
               strpos($currentHost, '.test') !== false ||
               $_SERVER['SERVER_ADDR'] === '127.0.0.1';
    }
    
    /**
     * Send email with PDF attachment
     */
    private function sendEmailWithPDFAttachment($to, $subject, $message, $pdfContent, $pdfFilename) {
        // Create boundary for multipart email
        $boundary = md5(uniqid(time()));
        
        // Headers for multipart email
        $headers = [
            'From: DS-Allroundservice <noreply@ds-allroundservice.de>',
            'Reply-To: ' . $this->data['email'],
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"'
        ];
        
        // Email body with HTML content and PDF attachment
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $message . "\r\n";
        
        // PDF attachment
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
        $body .= chunk_split(base64_encode($pdfContent)) . "\r\n";
        $body .= "--{$boundary}--";
        
        // Send email
        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
?>
