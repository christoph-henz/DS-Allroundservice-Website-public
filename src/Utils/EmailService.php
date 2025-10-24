<?php

namespace DSAllround\Utils;

use PDO;
use Exception;

/**
 * Email Service for sending templated emails
 */
class EmailService
{
    private PDO $db;
    private bool $isLocal;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        
        // Detect if running on localhost - check for actual localhost indicators
        $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
        
        // Only consider it local if:
        // 1. HTTP_HOST is exactly 'localhost' OR
        // 2. SERVER_ADDR is 127.0.0.1 or ::1 OR
        // 3. Running from CLI
        $this->isLocal = (
            $httpHost === 'localhost' || 
            $serverAddr === '127.0.0.1' || 
            $serverAddr === '::1' ||
            php_sapi_name() === 'cli'
        );
        
        // Override: Check for environment variable or setting
        if (getenv('FORCE_EMAIL_PRODUCTION') === 'true') {
            $this->isLocal = false;
            error_log("🌐 EmailService: FORCE_EMAIL_PRODUCTION is set - emails will be sent");
        }
        
        error_log("📧 EmailService: Environment - " . ($this->isLocal ? "LOCALHOST" : "PRODUCTION") . " (Host: $httpHost)");
    }

    /**
     * Send email using template
     */
    public function sendTemplateEmail(string $templateKey, string $toEmail, array $variables = [], array $attachments = []): array
    {
        try {
            error_log("📧 EmailService: Starting to send email with template '$templateKey' to '$toEmail'");
            
            // Load template
            $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1");
            $stmt->execute([$templateKey]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                $errorMsg = "Template not found or inactive: $templateKey";
                error_log("❌ EmailService: $errorMsg");
                throw new Exception($errorMsg);
            }
            
            error_log("✅ EmailService: Template loaded - ID: {$template['id']}, Subject: {$template['subject']}");
            
            // Get company variables
            $companyData = $this->getCompanyVariables();
            error_log("📋 EmailService: Company data loaded");
            
            // Template variables
            $templateVars = [];
            if (!empty($template['variables'])) {
                $templateVars = json_decode($template['variables'], true) ?: [];
            }
            
            // Merge all variables
            $allVariables = array_merge($templateVars, $companyData, $variables);
            error_log("🔧 EmailService: Variables merged - Total: " . count($allVariables));
            
            // Process template
            $subject = $this->processTemplateString($template['subject'], $allVariables);
            $bodyHtml = $this->processTemplateString($template['body_html'], $allVariables);
            $bodyText = $this->processTemplateString($template['body_text'], $allVariables);
            
            error_log("📝 EmailService: Template processed");
            error_log("   Subject: $subject");
            error_log("   To: $toEmail");
            
            // Setup email headers
            // IMPORTANT: Server only allows emails from @ds-allroundservice.de domain
            // Using noreply@ds-allroundservice.de as From, with company email as Reply-To
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: noreply@ds-allroundservice.de',
                'Reply-To: ' . $companyData['company_email'],
                'X-Mailer: DS-Allroundservice Email System'
            ];
            
            // Check if running on localhost
            if ($this->isLocal) {
                error_log("🏠 EmailService: LOCALHOST MODE - Email will be logged instead of sent");
                error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                error_log("📧 EMAIL PREVIEW (would be sent in production)");
                error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                error_log("To: $toEmail");
                error_log("Subject: $subject");
                error_log("From: {$companyData['company_name']} <{$companyData['company_email']}>");
                error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                error_log("HTML Body:");
                error_log($bodyHtml);
                error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                error_log("Text Body:");
                error_log($bodyText);
                error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                
                // Save to a file for easy viewing
                $emailLogFile = __DIR__ . '/../../debug/last_email.html';
                $emailPreview = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>$subject</title></head><body>";
                $emailPreview .= "<h2>Email Preview (Localhost Mode)</h2>";
                $emailPreview .= "<p><strong>To:</strong> $toEmail</p>";
                $emailPreview .= "<p><strong>Subject:</strong> $subject</p>";
                $emailPreview .= "<p><strong>From:</strong> {$companyData['company_name']} &lt;{$companyData['company_email']}&gt;</p>";
                $emailPreview .= "<hr>";
                $emailPreview .= $bodyHtml;
                $emailPreview .= "</body></html>";
                
                file_put_contents($emailLogFile, $emailPreview);
                error_log("💾 EmailService: Email preview saved to $emailLogFile");
                
                // Log to database as sent (even though it's simulated)
                $this->logEmailSent($templateKey, $toEmail, $subject, $allVariables, 'simulated_localhost');
                
                return [
                    'success' => true, 
                    'message' => 'Email simulated on localhost (check error log and debug/last_email.html)',
                    'mode' => 'localhost_simulation',
                    'preview_file' => $emailLogFile
                ];
            }
            
            // Production mode - send real email
            error_log("🌐 EmailService: PRODUCTION MODE - Sending real email via mail()");
            $success = mail($toEmail, $subject, $bodyHtml, implode("\r\n", $headers));
            
            if ($success) {
                // Log successful email
                $this->logEmailSent($templateKey, $toEmail, $subject, $allVariables, 'sent');
                error_log("✅ EmailService: Email sent successfully to $toEmail using template $templateKey");
                return ['success' => true, 'message' => 'Email sent successfully', 'mode' => 'production'];
            } else {
                throw new Exception("Failed to send email via mail() function");
            }
            
        } catch (Exception $e) {
            error_log("❌ EmailService Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get company-related variables from settings
     */
    private function getCompanyVariables(): array
    {
        $defaults = [
            'site_name' => 'DS Allroundservice',
            'company_name' => 'DS Allroundservice', // Alias for templates
            'site_description' => 'Ihr zuverlässiger Partner für Umzüge, Transport und Entrümpelung',
            'company_description' => 'Ihr zuverlässiger Partner für Umzüge, Transport und Entrümpelung', // Alias
            'contact_email' => 'christophhenz@gmail.com',
            'company_email' => 'christophhenz@gmail.com', // Alias for templates
            'contact_phone' => '+49 6021 123456',
            'company_phone' => '+49 6021 123456', // Alias for templates
            'phone' => '+49 6021 123456', // Additional alias
            'contact_address' => 'Darmstädter Straße 10, 63741 Aschaffenburg',
            'company_address' => 'Darmstädter Straße 10, 63741 Aschaffenburg', // Alias
            'company_website' => 'www.ds-allroundservices.de',
            'business_hours' => 'Montag: 8:00-18:00, Dienstag: 8:00-18:00, Mittwoch: 8:00-18:00, Donnerstag: 8:00-18:00, Freitag: 8:00-15:00, Samstag: 9:00-16:00, Sonntag: Geschlossen'
        ];

        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE is_public = 1");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($settings as $setting) {
                $key = $setting['setting_key'];
                $value = $setting['setting_value'];
                $defaults[$key] = $value;
                
                // Create aliases for common template variables
                if ($key === 'site_name') {
                    $defaults['company_name'] = $value;
                } elseif ($key === 'contact_email') {
                    $defaults['company_email'] = $value;
                } elseif ($key === 'contact_phone') {
                    $defaults['company_phone'] = $value;
                    $defaults['phone'] = $value;
                }
            }
        } catch (Exception $e) {
            error_log("⚠️ EmailService: Could not load company settings: " . $e->getMessage());
        }

        error_log("🏢 EmailService: Company variables loaded - company_name: " . $defaults['company_name'] . ", company_email: " . $defaults['company_email']);
        return $defaults;
    }

    /**
     * Process template string by replacing variables
     */
    private function processTemplateString(string $template, array $variables): string
    {
        $processed = $template;
        
        foreach ($variables as $key => $value) {
            // Handle array values (convert to string)
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            // Replace {{variable}} patterns
            $processed = str_replace('{{' . $key . '}}', $value, $processed);
            
            // Also support {variable} patterns
            $processed = str_replace('{' . $key . '}', $value, $processed);
        }
        
        return $processed;
    }

    /**
     * Log sent emails for tracking
     */
    private function logEmailSent(string $templateKey, string $toEmail, string $subject, array $variables, string $status = 'sent'): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO email_logs (template_key, recipient_email, subject, variables_used, status, sent_at, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([$templateKey, $toEmail, $subject, json_encode($variables), $status]);
            error_log("📊 EmailService: Email logged to database (ID: " . $this->db->lastInsertId() . ")");
        } catch (Exception $e) {
            error_log("⚠️ EmailService: Could not log email: " . $e->getMessage());
        }
    }

    /**
     * Send automatic receipt confirmation
     */
    public function sendReceiptConfirmation(array $submission): array
    {
        error_log("📬 EmailService: sendReceiptConfirmation called");
        error_log("   Customer: " . ($submission['customer_name'] ?? 'N/A'));
        error_log("   Email: " . ($submission['customer_email'] ?? 'N/A'));
        error_log("   Reference: " . ($submission['reference'] ?? 'N/A'));
        
        // Extract necessary data
        $customerEmail = $submission['customer_email'] ?? null;
        
        if (!$customerEmail) {
            error_log("❌ EmailService: No customer email provided");
            return ['success' => false, 'error' => 'No customer email provided'];
        }

        $variables = [
            'customer_name' => $submission['customer_name'] ?? 'Kunde',
            'reference' => $submission['reference'] ?? 'N/A',
            'service_type' => $submission['service_type'] ?? $submission['service_name'] ?? 'Service',
            'service_name' => $submission['service_name'] ?? $submission['service_type'] ?? 'Service',
            'submitted_at' => $submission['submitted_at'] ?? date('d.m.Y H:i'),
            'appointment_date' => $submission['appointment_date'] ?? 'Wird noch vereinbart'
        ];

        error_log("📤 EmailService: Calling sendTemplateEmail with 'auto_receipt_confirmation'");
        return $this->sendTemplateEmail('auto_receipt_confirmation', $customerEmail, $variables);
    }

    /**
     * Send team notification for new request
     */
    public function sendTeamNotification(array $submission): array
    {
        error_log("👥 EmailService: sendTeamNotification called");
        error_log("   Reference: " . ($submission['reference'] ?? 'N/A'));
        error_log("   Service: " . ($submission['service_name'] ?? 'N/A'));
        
        // Get team email from settings
        $teamEmail = $this->getTeamEmail();
        
        if (!$teamEmail) {
            error_log("⚠️ EmailService: No team email configured - skipping team notification");
            return ['success' => false, 'error' => 'No team email configured'];
        }

        // Prepare additional details from form data
        $additionalDetails = $this->formatAdditionalDetails($submission['form_data'] ?? []);

        $variables = [
            'reference' => $submission['reference'] ?? 'N/A',
            'service_type' => $submission['service_type'] ?? $submission['service_name'] ?? 'Service',
            'service_name' => $submission['service_name'] ?? $submission['service_type'] ?? 'Service',
            'submitted_at' => $submission['submitted_at'] ?? date('d.m.Y H:i'),
            'submission_id' => $submission['submission_id'] ?? 'N/A',
            'customer_name' => $submission['customer_name'] ?? 'N/A',
            'customer_email' => $submission['customer_email'] ?? 'N/A',
            'customer_phone' => $submission['customer_phone'] ?? 'N/A',
            'appointment_date' => $submission['appointment_date'] ?? 'Noch nicht festgelegt',
            'additional_details' => $additionalDetails,
            'admin_url' => $this->getAdminUrl()
        ];

        error_log("📤 EmailService: Calling sendTemplateEmail with 'team_new_request_notification' to $teamEmail");
        return $this->sendTemplateEmail('team_new_request_notification', $teamEmail, $variables);
    }

    /**
     * Get team email address from settings
     */
    private function getTeamEmail(): ?string
    {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'team_notification_email' AND is_public = 0");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['setting_value'])) {
                return $result['setting_value'];
            }
            
            // Fallback to company email
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'contact_email'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['setting_value'] ?? 'christophhenz@gmail.com'; // Hardcoded fallback
            
        } catch (Exception $e) {
            error_log("⚠️ EmailService: Could not load team email: " . $e->getMessage());
            return 'christophhenz@gmail.com'; // Hardcoded fallback
        }
    }

    /**
     * Get admin URL
     */
    private function getAdminUrl(): string
    {
        $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        
        return $protocol . '://' . $httpHost . '/admin';
    }

    /**
     * Format additional details from form data
     */
    private function formatAdditionalDetails(array $formData): string
    {
        if (empty($formData)) {
            return 'Keine zusätzlichen Details verfügbar.';
        }

        $formatted = [];
        foreach ($formData as $key => $value) {
            // Skip standard fields (already shown separately)
            if (in_array($key, ['question_1', 'question_2', 'question_3'])) {
                continue;
            }
            
            // Format question key to readable label
            $label = ucfirst(str_replace('_', ' ', $key));
            
            // Handle array values
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            // Skip empty values
            if (empty($value)) {
                continue;
            }
            
            $formatted[] = "$label: $value";
        }

        return empty($formatted) ? 'Keine zusätzlichen Details verfügbar.' : implode("\n", $formatted);
    }
}
