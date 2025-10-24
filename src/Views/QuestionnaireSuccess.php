<?php

namespace DSAllround\Views;

use Exception;
use DateTime;
use PDO;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class QuestionnaireSuccess extends Page
{
    public function __construct()
    {
        parent::__construct();
    }

    public function show(): void
    {
        $this->generatePageHeader('Anfrage erfolgreich gesendet - DS-Allroundservice');
        $this->generateContent();
        $this->generatePageFooter();
    }

    protected function additionalMetaData(): void
    {
        echo <<< HTML
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" type="text/css" href="public/assets/css/questionnaire.css"/>
            <style>
                .success-container {
                    max-width: 800px;
                    margin: 2rem auto;
                    padding: 2rem;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                    text-align: center;
                }
                
                .success-icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 2rem;
                    background: #27ae60;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 2.5rem;
                }
                
                .success-title {
                    color: #2c3e50;
                    margin-bottom: 1rem;
                    font-size: 2.5rem;
                    font-weight: 700;
                }
                
                .success-message {
                    color: #7f8c8d;
                    font-size: 1.1rem;
                    line-height: 1.6;
                    margin-bottom: 2rem;
                }
                
                .reference-number {
                    background: #f8f9fa;
                    border: 2px solid #27ae60;
                    border-radius: 8px;
                    padding: 1.5rem;
                    margin: 2rem 0;
                }
                
                .reference-label {
                    font-weight: 600;
                    color: #2c3e50;
                    margin-bottom: 0.5rem;
                }
                
                .reference-value {
                    font-family: 'Courier New', monospace;
                    font-size: 1.4rem;
                    font-weight: bold;
                    color: #27ae60;
                    letter-spacing: 1px;
                }
                
                .next-steps {
                    text-align: left;
                    margin: 2rem 0;
                    padding: 1.5rem;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border-left: 4px solid #3498db;
                }
                
                .next-steps h3 {
                    color: #2c3e50;
                    margin-bottom: 1rem;
                    font-size: 1.3rem;
                }
                
                .next-steps ul {
                    list-style: none;
                    padding: 0;
                }
                
                .next-steps li {
                    margin-bottom: 0.8rem;
                    padding-left: 2rem;
                    position: relative;
                    color: #7f8c8d;
                }
                
                .next-steps li::before {
                    content: "✓";
                    position: absolute;
                    left: 0;
                    color: #27ae60;
                    font-weight: bold;
                    font-size: 1.2rem;
                }
                
                .submission-details {
                    background: #f8f9fa;
                    border-radius: 8px;
                    padding: 1.5rem;
                    margin: 2rem 0;
                    border-left: 4px solid #27ae60;
                }
                
                .submission-details h3 {
                    color: #2c3e50;
                    margin-bottom: 1rem;
                    font-size: 1.3rem;
                }
                
                .details-grid {
                    display: grid;
                    gap: 0.8rem;
                }
                
                .detail-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.5rem 0;
                    border-bottom: 1px solid #e9ecef;
                }
                
                .detail-item:last-child {
                    border-bottom: none;
                }
                
                .detail-item .label {
                    font-weight: 600;
                    color: #2c3e50;
                }
                
                .detail-item .value {
                    color: #7f8c8d;
                }
                
                .status-new {
                    background: #3498db;
                    color: white;
                    padding: 0.2rem 0.5rem;
                    border-radius: 4px;
                    font-size: 0.9rem;
                }
                
                .status-processed {
                    background: #f39c12;
                    color: white;
                    padding: 0.2rem 0.5rem;
                    border-radius: 4px;
                    font-size: 0.9rem;
                }
                
                .status-completed {
                    background: #27ae60;
                    color: white;
                    padding: 0.2rem 0.5rem;
                    border-radius: 4px;
                    font-size: 0.9rem;
                }
                
                .contact-info {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 2rem;
                    border-radius: 8px;
                    margin: 2rem 0;
                }
                
                .contact-info h3 {
                    margin-bottom: 1rem;
                    font-size: 1.3rem;
                }
                
                .contact-details {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1rem;
                    margin-top: 1rem;
                }
                
                .contact-item {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .action-buttons {
                    display: flex;
                    gap: 1rem;
                    justify-content: center;
                    flex-wrap: wrap;
                    margin: 2rem 0;
                }
                
                .btn-home {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-decoration: none;
                    padding: 1rem 2rem;
                    border-radius: 8px;
                    font-weight: 600;
                    transition: transform 0.2s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .btn-home:hover {
                    transform: translateY(-2px);
                    text-decoration: none;
                    color: white;
                }
                
                .btn-services {
                    background: white;
                    color: #667eea;
                    border: 2px solid #667eea;
                    text-decoration: none;
                    padding: 1rem 2rem;
                    border-radius: 8px;
                    font-weight: 600;
                    transition: all 0.2s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .btn-services:hover {
                    background: #667eea;
                    color: white;
                    transform: translateY(-2px);
                    text-decoration: none;
                }
                
                @media (max-width: 768px) {
                    .success-container {
                        margin: 1rem;
                        padding: 1.5rem;
                    }
                    
                    .success-title {
                        font-size: 2rem;
                    }
                    
                    .action-buttons {
                        flex-direction: column;
                    }
                    
                    .contact-details {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
            <script>
                // Prevent accidental page reload/refresh
                let isFirstLoad = true;
                
                window.addEventListener('beforeunload', function(e) {
                    // Only show warning if this is not the first load
                    if (!isFirstLoad) {
                        e.preventDefault();
                        e.returnValue = 'Möchten Sie diese Seite wirklich neu laden? Die Erfolgsmeldung und gespeicherte Formulardaten werden gelöscht.';
                        return e.returnValue;
                    }
                });
                
                window.addEventListener('load', function() {
                    // Mark that first load is complete
                    isFirstLoad = false;
                    
                    // Check if this is a page reload (not initial visit)
                    if (performance.navigation.type === 1) {
                        // Page was reloaded - clear session and cache
                        clearSessionAndRedirect();
                    }
                });
                
                function clearSessionAndRedirect() {
                    // Clear localStorage cache (questionnaire data)
                    Object.keys(localStorage).forEach(key => {
                        if (key.startsWith('questionnaire_') || key.includes('form_data')) {
                            localStorage.removeItem(key);
                        }
                    });
                    
                    // Clear sessionStorage
                    sessionStorage.clear();
                    
                    // Call API to clear server-side session
                    fetch('/api/clear-success-session.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    }).finally(() => {
                        // Redirect to homepage
                        window.location.replace('/');
                    });
                }
            </script>
        HTML;
    }

    protected function generateContent(): void
    {
        // Get reference number and service info from session only (no GET fallback)
        $referenceNumber = $_SESSION['last_reference'] ?? null;
        $serviceType = $_SESSION['last_service'] ?? '';
        $serviceName = $_SESSION['last_service_name'] ?? '';
        $submissionId = $_SESSION['last_submission_id'] ?? null;
        
        // If no reference number is available, show generic message
        if (!$referenceNumber) {
            $referenceNumber = 'Wird per E-Mail zugesendet';
        }
        
        // Determine service name if not in session
        if (!$serviceName) {
            $serviceName = $this->getServiceName($serviceType);
        }
        
        // Get additional submission details if available
        $submissionDetails = null;
        if ($submissionId && $referenceNumber !== 'Wird per E-Mail zugesendet') {
            $submissionDetails = $this->getSubmissionDetails($referenceNumber);
        }
        
        echo <<< HTML
            <div class="success-container">
                <div class="success-icon">
                    ✓
                </div>
                
                <h1 class="success-title">Vielen Dank für Ihre Anfrage!</h1>
                
                <p class="success-message">
                    Ihre {$serviceName}-Anfrage wurde erfolgreich übermittelt. 
                    Unser Team hat Ihre Nachricht erhalten und wird sich zeitnah mit Ihnen in Verbindung setzen.
                </p>
        HTML;

        if ($referenceNumber !== 'Wird per E-Mail zugesendet') {
            echo <<< HTML
                <div class="reference-number">
                    <div class="reference-label">Ihre Referenznummer:</div>
                    <div class="reference-value">{$referenceNumber}</div>
                </div>
            HTML;
        }

        // Show submission details if available
        if ($submissionDetails) {
            echo <<< HTML
                <div class="submission-details">
                    <h3>Details Ihrer Anfrage:</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="label">Service:</span>
                            <span class="value">{$submissionDetails['service_name']}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Eingegangen am:</span>
                            <span class="value">{$this->formatDateTime($submissionDetails['submitted_at'])}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Status:</span>
                            <span class="value status-{$submissionDetails['status']}">{$this->getStatusLabel($submissionDetails['status'])}</span>
                        </div>
                    </div>
                </div>
            HTML;
        }

        echo <<< HTML
                <div class="next-steps">
                    <h3>Wie geht es weiter?</h3>
                    <ul>
                        <li>Sie erhalten eine Bestätigungs-E-Mail mit allen Details</li>
                        <li>Unser Team prüft Ihre Anfrage und erstellt ein individuelles Angebot</li>
                        <li>Wir melden uns innerhalb von 24 Stunden bei Ihnen</li>
                        <li>Gemeinsam besprechen wir alle Details und Ihren Wunschtermin</li>
                    </ul>
                </div>
                
                <div class="contact-info">
                    <h3>Dringende Fragen? Kontaktieren Sie uns direkt!</h3>
                    <div class="contact-details">
                        <div class="contact-item">
                            <span>📞</span>
                            <span>+49 (0) 123 456 789</span>
                        </div>
                        <div class="contact-item">
                            <span>✉️</span>
                            <span>info@ds-allroundservice.de</span>
                        </div>
                        <div class="contact-item">
                            <span>⏰</span>
                            <span>Mo-Fr: 8:00-18:00 Uhr</span>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="/" class="btn-home">
                        🏠 Zur Startseite
                    </a>
                    <a href="/#services" class="btn-services">
                        📋 Weitere Services
                    </a>
                </div>
            </div>
        HTML;
        
        // Clear session data after displaying
        unset($_SESSION['last_reference'], $_SESSION['last_service'], $_SESSION['last_service_name'], $_SESSION['last_submission_id']);
    }

    private function getServiceName(string $serviceType): string
    {
        switch (strtolower($serviceType)) {
            case 'umzug':
            case 'umzuege':
                return 'Umzug';
            case 'transport':
                return 'Transport';
            case 'entruempelung':
                return 'Entrümpelung';
            case 'aufloesung':
                return 'Auflösung';
            default:
                return 'Service';
        }
    }
    
    /**
     * Get submission details from database using inherited connection
     */
    private function getSubmissionDetails(string $reference): ?array
    {
        try {
            // Use inherited database connection from Page class
            $sql = "SELECT qs.*, s.name as service_name 
                    FROM questionnaire_submissions qs 
                    JOIN services s ON qs.service_id = s.id 
                    WHERE qs.reference = ?";
            $stmt = $this->_database->prepare($sql);
            $stmt->execute([$reference]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log("Error getting submission details: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Format datetime for display
     */
    private function formatDateTime(string $datetime): string
    {
        try {
            $date = new \DateTime($datetime);
            return $date->format('d.m.Y H:i') . ' Uhr';
        } catch (Exception $e) {
            return $datetime;
        }
    }
    
    /**
     * Get status label in German
     */
    private function getStatusLabel(string $status): string
    {
        switch ($status) {
            case 'new':
                return 'Neu eingegangen';
            case 'processed':
                return 'In Bearbeitung';
            case 'completed':
                return 'Abgeschlossen';
            case 'cancelled':
                return 'Abgebrochen';
            default:
                return ucfirst($status);
        }
    }
}

$page = new QuestionnaireSuccess();
$page->show();
?>
