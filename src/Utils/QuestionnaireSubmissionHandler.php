<?php

namespace DSAllround\Utils;

use PDO;
use PDOException;
use Exception;

require_once __DIR__ . '/PDFGenerator.php';
require_once __DIR__ . '/EmailService.php';

class QuestionnaireSubmissionHandler
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Save questionnaire submission to database
     */
    public function saveSubmission(array $submissionData, array $answers, string $reference): array
    {
        try {
            $this->db->beginTransaction();

            // Merge answers into submission data for JSON storage
            $submissionData['answers'] = $answers;

            // Insert main submission record
            $submissionId = $this->insertSubmission($submissionData, $reference);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'submission_id' => $submissionId,
                'reference' => $reference
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            error_log("❌ QuestionnaireSubmissionHandler::saveSubmission failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'submission_id' => null,
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }

    /**
     * Insert main submission record
     */
    private function insertSubmission(array $data, string $reference): int
    {
        try {
            error_log("📝 insertSubmission called with reference: {$reference}");
            
            $sql = "INSERT INTO questionnaire_submissions (
                reference, 
                service_id, 
                template_id, 
                customer_name, 
                customer_email, 
                customer_phone, 
                form_data, 
                ip_address, 
                user_agent, 
                status,
                submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())";
            
            $stmt = $this->db->prepare($sql);
            
            // Extract contact information from answers array (new fixed contact fields)
            error_log("🔍 Extracting contact data...");
            $contactData = $this->extractContactData($data);
            error_log("✅ Contact data extracted: " . json_encode($contactData));
            
            $customerName = $contactData['name'];
            $customerEmail = $contactData['email'];
            $customerPhone = $contactData['phone'];
            
            // Prepare form data as JSON
            $formDataJson = json_encode($data);
            error_log("📦 Form data JSON length: " . strlen($formDataJson));
            
            // Get client information
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            error_log("🚀 Executing INSERT query...");
            $stmt->execute([
                $reference,
                $data['service_id'] ?? null,
                $data['template_id'] ?? null,
                $customerName,
                $customerEmail,
                $customerPhone,
                $formDataJson,
                $ipAddress,
                $userAgent
            ]);
            
            $insertId = $this->db->lastInsertId();
            error_log("✅ Submission inserted successfully with ID: {$insertId}");
            
            return $insertId;
        } catch (Exception $e) {
            error_log("❌ insertSubmission failed: " . $e->getMessage());
            error_log("SQL Error Code: " . ($this->db->errorCode() ?? 'unknown'));
            error_log("SQL Error Info: " . json_encode($this->db->errorInfo()));
            throw $e;
        }
    }

    /**
     * Generate unique reference number
     */
    public static function generateReference(string $serviceSlug): string
    {
        $prefix = strtoupper(substr($serviceSlug, 0, 3));
        $timestamp = date('ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Get service information by slug
     */
    public function getServiceBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM services WHERE slug = ? AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$slug]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get questionnaire template for service
     */
    public function getQuestionnaireTemplate(int $serviceId): ?array
    {
        $sql = "SELECT * FROM questionnaires 
                WHERE service_id = ? AND status = 'active' 
                ORDER BY created_at DESC 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$serviceId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get questions for template
     */
    public function getQuestionsForTemplate(int $templateId): array
    {
        $sql = "SELECT qs.*, qq.sort_order as question_order
                FROM questions_simple qs
                JOIN questionnaire_questions qq ON qs.id = qq.question_id
                WHERE qq.questionnaire_id = ? 
                ORDER BY qq.sort_order ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$templateId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update PDF generation status
     */
    public function updatePDFStatus(int $submissionId, string $pdfPath, bool $generated = true): bool
    {
        $sql = "UPDATE questionnaire_submissions 
                SET pdf_generated = ?, pdf_path = ?, processed_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$generated ? 1 : 0, $pdfPath, $submissionId]);
    }

    /**
     * Update email status
     */
    public function updateEmailStatus(int $submissionId, bool $emailSent = true, bool $customerNotified = true): bool
    {
        $sql = "UPDATE questionnaire_submissions 
                SET email_sent = ?, customer_notified = ?, 
                    completed_at = CASE WHEN ? = 1 THEN NOW() ELSE completed_at END
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$emailSent ? 1 : 0, $customerNotified ? 1 : 0, $customerNotified, $submissionId]);
    }

    /**
     * Send automatic receipt confirmation email
     */
    private function sendReceiptConfirmation(array $submission): array
    {
        try {
            error_log("📧 QuestionnaireSubmissionHandler::sendReceiptConfirmation - Start");
            
            // Check if customer email is provided
            if (empty($submission['customer_email'])) {
                error_log("⚠️ No customer email provided - skipping receipt confirmation");
                return ['success' => false, 'error' => 'No customer email provided'];
            }

            // Create email service instance
            $emailService = new EmailService($this->db);
            
            // Send the receipt confirmation
            $result = $emailService->sendReceiptConfirmation($submission);
            
            if ($result['success']) {
                error_log("✅ Receipt confirmation sent to {$submission['customer_email']}");
            } else {
                error_log("❌ Failed to send receipt confirmation: " . ($result['error'] ?? 'Unknown error'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("❌ Error in sendReceiptConfirmation: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send team notification for new request
     */
    private function sendTeamNotification(array $submission): array
    {
        try {
            error_log("👥 QuestionnaireSubmissionHandler::sendTeamNotification - Start");
            
            // Create email service instance
            $emailService = new EmailService($this->db);
            
            // Send the team notification
            $result = $emailService->sendTeamNotification($submission);
            
            if ($result['success']) {
                error_log("✅ Team notification sent");
            } else {
                error_log("⚠️ Team notification not sent: " . ($result['error'] ?? 'Unknown error'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("❌ Error in sendTeamNotification: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get submission by reference
     */
    public function getSubmissionByReference(string $reference): ?array
    {
        $sql = "SELECT s.*, srv.name as service_name, srv.slug as service_slug, srv.color as service_color
                FROM questionnaire_submissions s
                JOIN services srv ON s.service_id = srv.id
                WHERE s.reference = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$reference]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get answers for submission from form_data JSON
     */
    public function getSubmissionAnswers(int $submissionId): array
    {
        $sql = "SELECT form_data FROM questionnaire_submissions WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$submissionId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            return [];
        }
        
        $formData = json_decode($result['form_data'], true);
        return $formData['answers'] ?? [];
    }

    /**
     * Check if environment is production
     */
    public static function isProduction(): bool
    {
        // Check various indicators for production environment
        $indicators = [
            $_SERVER['HTTP_HOST'] ?? '',
            $_SERVER['SERVER_NAME'] ?? '',
            getenv('APP_ENV'),
            php_uname('n')
        ];
        
        foreach ($indicators as $indicator) {
            if (strpos($indicator, 'localhost') !== false || 
                strpos($indicator, '127.0.0.1') !== false ||
                strpos($indicator, 'development') !== false ||
                strpos($indicator, 'local') !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Process complete questionnaire submission
     */
    public function processSubmission(array $formData, string $serviceSlug): array
    {
        try {
            // Get service information
            $service = $this->getServiceBySlug($serviceSlug);
            if (!$service) {
                throw new Exception("Service '{$serviceSlug}' not found");
            }

            // Get template for this service (optional)
            $template = $this->getQuestionnaireTemplate($service['id']);

            // Add service_id and template_id to form data
            $formData['service_id'] = $service['id'];
            $formData['template_id'] = $template ? $template['id'] : null;

            // Generate reference
            $reference = self::generateReference($serviceSlug);

            // Extract answers from form data
            $answers = $this->extractAnswersFromFormData($formData);

            // Save to database
            $submissionResult = $this->saveSubmission($formData, $answers, $reference);
            if (!$submissionResult['success']) {
                throw new Exception("Failed to save submission: " . $submissionResult['error']);
            }

            // Generate PDF with structured data (optional - fällt zurück wenn TCPDF fehlt)
            $isProduction = self::isProduction();
            
            // Prepare data for PDF with proper structure
            $pdfData = array_merge($formData, ['answers' => $answers]);
            $pdfResult = null;
            
            try {
                error_log("📄 Attempting PDF generation for reference: {$reference}");
                $pdfGenerator = new PDFGenerator($pdfData, $service, $reference, $isProduction);
                
                $pdfResult = $pdfGenerator->savePDF();
                if ($pdfResult['success']) {
                    $this->updatePDFStatus($submissionResult['submission_id'], $pdfResult['filepath'], true);
                    error_log("✅ PDF generated successfully: {$pdfResult['filepath']}");
                }
            } catch (\Throwable $pdfError) {
                // PDF-Generierung fehlgeschlagen (z.B. TCPDF nicht vorhanden)
                // Submission und E-Mail werden trotzdem verarbeitet
                error_log("❌ PDF generation failed: " . $pdfError->getMessage());
                error_log("⚠️ Continuing without PDF - submission and email will still be processed");
            }

            // ✅ Send automatic receipt confirmation email
            error_log("📧 QuestionnaireSubmissionHandler: Preparing to send receipt confirmation");
            $emailResult = null;
            try {
                // Extract contact data using the new method
                $contactData = $this->extractContactData($pdfData);
                
                // Find appointment date from answers or form data
                $appointmentDate = null;
                if (isset($pdfData['answers']) && is_array($pdfData['answers'])) {
                    foreach ($pdfData['answers'] as $answer) {
                        $questionText = $answer['question_text'] ?? '';
                        if (stripos($questionText, 'termin') !== false || stripos($questionText, 'besichtigung') !== false) {
                            $appointmentDate = $answer['answer_text'] ?? null;
                            break;
                        }
                    }
                }
                
                // Fallback: search in form data
                if (!$appointmentDate) {
                    foreach ($formData as $key => $value) {
                        if ((stripos($key, 'termin') !== false || stripos($key, 'date') !== false) && !empty($value)) {
                            $appointmentDate = $value;
                            break;
                        }
                    }
                }
                
                $emailResult = $this->sendReceiptConfirmation([
                    'customer_name' => $contactData['name'] ?? 'Kunde',
                    'customer_email' => $contactData['email'] ?? null,
                    'reference' => $reference,
                    'service_name' => $service['name'],
                    'service_type' => $service['title'] ?? $service['name'],
                    'submitted_at' => date('d.m.Y H:i'),
                    'appointment_date' => $appointmentDate ?? 'Wird noch vereinbart'
                ]);
                
                error_log("📧 QuestionnaireSubmissionHandler: Email send result: " . json_encode($emailResult));
                
                // Send team notification
                error_log("👥 QuestionnaireSubmissionHandler: Preparing team notification");
                $teamEmailResult = $this->sendTeamNotification([
                    'customer_name' => $contactData['name'] ?? 'Kunde',
                    'customer_email' => $contactData['email'] ?? null,
                    'customer_phone' => $contactData['phone'] ?? 'Nicht angegeben',
                    'reference' => $reference,
                    'service_name' => $service['name'],
                    'service_type' => $service['title'] ?? $service['name'],
                    'submitted_at' => date('d.m.Y H:i'),
                    'appointment_date' => $appointmentDate ?? 'Wird noch vereinbart',
                    'submission_id' => $submissionResult['submission_id'],
                    'form_data' => $formData
                ]);
                
                error_log("👥 QuestionnaireSubmissionHandler: Team notification result: " . json_encode($teamEmailResult));
                
            } catch (Exception $emailError) {
                error_log("⚠️ QuestionnaireSubmissionHandler: Email sending failed but submission continues: " . $emailError->getMessage());
                $emailResult = ['success' => false, 'error' => $emailError->getMessage()];
                $teamEmailResult = ['success' => false, 'error' => 'Skipped due to customer email error'];
            }

            return [
                'success' => true,
                'reference' => $reference,
                'submission_id' => $submissionResult['submission_id'],
                'service' => $service,
                'template' => $template,
                'pdf_result' => $pdfResult,
                'email_result' => $emailResult,
                'team_email_result' => $teamEmailResult ?? ['success' => false, 'error' => 'Not attempted'],
                'is_production' => $isProduction
            ];

        } catch (Exception $e) {
            error_log("❌ QuestionnaireSubmissionHandler::processSubmission failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            error_log("Form data: " . json_encode($formData));
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'service_slug' => $serviceSlug ?? 'unknown',
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }

    /**
     * Extract question answers from form data with proper question texts from database
     */
    private function extractAnswersFromFormData(array $formData): array
    {
        $answers = [];
        $questionIndex = 1;
        
        // Get the service to load questionnaire data
        $serviceSlug = $formData['service_slug'] ?? null;
        if (!$serviceSlug) {
            return $this->extractAnswersFromFormDataFallback($formData);
        }
        
        // Load questions from database to get proper question texts
        $questionsMap = $this->loadQuestionsMap($serviceSlug);
        
        foreach ($formData as $key => $value) {
            // Skip system fields
            if (in_array($key, ['service_id', 'template_id', 'csrf_token', 'service_slug'])) {
                continue;
            }
            
            // Skip contact fields (they're handled separately)
            if (in_array($key, ['name', 'email', 'phone', 'customer_name', 'customer_email', 'customer_phone'])) {
                continue;
            }
            
            // Skip empty values
            if (empty($value) && $value !== '0') {
                continue;
            }
            
            // Skip question_text fields (they are meta fields)
            if (strpos($key, '_text') !== false) {
                continue;
            }
            
            // Get question text from database mapping or fallback to field label
            $questionText = $questionsMap[$key] ?? $this->formatFieldLabel($key);
            
            $answers[] = [
                'question_id' => $questionIndex,
                'question_text' => $questionText,
                'answer_text' => $value,
                'answer_data' => null
            ];
            $questionIndex++;
        }
        
        return $answers;
    }

    /**
     * Load questions mapping from database for a service
     */
    private function loadQuestionsMap(string $serviceSlug): array
    {
        try {
            // First get the service
            $service = $this->getServiceBySlug($serviceSlug);
            if (!$service) {
                return [];
            }
            
            // Then get the questionnaire for this service
            $template = $this->getQuestionnaireTemplate($service['id']);
            if (!$template) {
                return [];
            }
            
            // Get questions for this questionnaire
            $sql = "
                SELECT qs.id, qs.question_text, qq.sort_order
                FROM questions_simple qs
                JOIN questionnaire_questions qq ON qs.id = qq.question_id
                WHERE qq.questionnaire_id = ?
                ORDER BY qq.sort_order ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$template['id']]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $questionsMap = [];
            $index = 0;
            foreach ($questions as $question) {
                $questionId = $question['id'];
                $questionText = $question['question_text'];
                
                // Map different possible field name patterns to the actual question text
                $questionsMap['question_' . $questionId] = $questionText;
                $questionsMap['q' . $questionId] = $questionText;
                $questionsMap[$questionId] = $questionText;
                
                // Also map by order for generic forms
                $questionsMap['question_' . ($index + 1)] = $questionText;
                $questionsMap['q' . ($index + 1)] = $questionText;
                
                $index++;
            }
            
            return $questionsMap;
            
        } catch (Exception $e) {
            error_log("Error loading questions map for service '{$serviceSlug}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fallback method when database lookup fails
     */
    private function extractAnswersFromFormDataFallback(array $formData): array
    {
        $answers = [];
        $questionIndex = 1;
        
        foreach ($formData as $key => $value) {
            // Skip system fields
            if (in_array($key, ['service_id', 'template_id', 'csrf_token', 'service_slug'])) {
                continue;
            }
            
            // Skip contact fields (they're handled separately)
            if (in_array($key, ['name', 'email', 'phone', 'customer_name', 'customer_email', 'customer_phone'])) {
                continue;
            }
            
            // Skip empty values
            if (empty($value) && $value !== '0') {
                continue;
            }
            
            // Handle question fields with text
            if (strpos($key, 'question_') === 0 && !strpos($key, '_text')) {
                $questionTextField = $key . '_text';
                $questionText = $formData[$questionTextField] ?? $this->formatFieldLabel($key);
                
                $answers[] = [
                    'question_id' => $questionIndex,
                    'question_text' => $questionText,
                    'answer_text' => $value,
                    'answer_data' => null
                ];
                $questionIndex++;
            }
            // Handle other form fields that are not question_text fields
            elseif (!strpos($key, 'question_text_')) {
                $answers[] = [
                    'question_id' => $questionIndex,
                    'question_text' => $this->formatFieldLabel($key),
                    'answer_text' => $value,
                    'answer_data' => null
                ];
                $questionIndex++;
            }
        }
        
        return $answers;
    }

    /**
     * Format field key into readable German question label
     */
    private function formatFieldLabel(string $fieldKey): string
    {
        // Common field translations to German
        $translations = [
            'moving_date' => 'Umzugsdatum',
            'from_address' => 'Von Adresse',
            'to_address' => 'Zu Adresse', 
            'pickup_address' => 'Abholadresse',
            'delivery_address' => 'Lieferadresse',
            'rooms' => 'Anzahl Zimmer',
            'elevator_from' => 'Aufzug vorhanden (von)',
            'elevator_to' => 'Aufzug vorhanden (zu)',
            'piano' => 'Klavier vorhanden',
            'packing_service' => 'Verpackungsservice',
            'storage' => 'Lagerung erforderlich',
            'transport_date' => 'Transportdatum',
            'items' => 'Gegenstände',
            'weight' => 'Geschätztes Gewicht',
            'special_handling' => 'Besondere Behandlung',
            'loading_help' => 'Ladehilfe benötigt',
            'service_date' => 'Wunschdatum',
            'property_address' => 'Objektadresse',
            'property_type' => 'Objekttyp',
            'disposal_method' => 'Entsorgungsart',
            'certificate_needed' => 'Bescheinigung erforderlich',
            'cleaning_included' => 'Reinigung inklusive',
            'storage_needed' => 'Lagerung benötigt',
            'contact_person' => 'Ansprechpartner',
            'phone' => 'Telefonnummer',
            'email' => 'E-Mail-Adresse',
            'message' => 'Nachricht',
            'comments' => 'Anmerkungen',
            'notes' => 'Hinweise'
        ];
        
        // Check if we have a specific translation
        if (isset($translations[$fieldKey])) {
            return $translations[$fieldKey];
        }
        
        // Remove common prefixes
        $cleaned = preg_replace('/^(question_|q)/', '', $fieldKey);
        
        // Convert to readable format
        $readable = str_replace(['_', '-'], ' ', $cleaned);
        $readable = ucfirst(strtolower($readable));
        
        return $readable;
    }

    /**
     * Extract contact data from form data or answers array
     * Supports both new fixed contact fields and legacy field names
     * 
     * @param array $data Form data with answers
     * @return array ['name' => string|null, 'email' => string|null, 'phone' => string|null]
     */
    private function extractContactData(array $data): array
    {
        try {
            error_log("🔍 extractContactData called");
            error_log("Data keys: " . implode(', ', array_keys($data)));
            
            $name = null;
            $email = null;
            $phone = null;
            
            // Try to extract from answers array (new fixed contact fields)
            if (isset($data['answers']) && is_array($data['answers'])) {
                error_log("📋 Found answers array with " . count($data['answers']) . " items");
                $firstName = null;
                $lastName = null;
                
                foreach ($data['answers'] as $answer) {
                    if (!is_array($answer)) {
                        error_log("⚠️ Answer is not an array: " . gettype($answer));
                        continue;
                    }
                    
                    $questionText = $answer['question_text'] ?? '';
                    $answerText = $answer['answer_text'] ?? '';
                    
                    switch ($questionText) {
                        case 'Vorname':
                            $firstName = $answerText;
                            error_log("✅ Found Vorname: {$firstName}");
                            break;
                        case 'Nachname':
                            $lastName = $answerText;
                            error_log("✅ Found Nachname: {$lastName}");
                            break;
                        case 'E-Mail Adresse':
                            $email = $answerText;
                            error_log("✅ Found E-Mail: {$email}");
                            break;
                        case 'Telefonnummer':
                        case 'Mobilnummer':
                            if (empty($phone)) { // Use first phone number found
                                $phone = $answerText;
                                error_log("✅ Found Phone: {$phone}");
                            }
                            break;
                    }
                }
                
                // Combine first and last name
                if ($firstName || $lastName) {
                    $name = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
                    error_log("✅ Combined name: {$name}");
                }
            } else {
                error_log("⚠️ No answers array found in data");
            }
            
            // Fallback: Try legacy field names
            if (empty($name)) {
                $name = $data['name'] ?? $data['customer_name'] ?? null;
                if ($name) error_log("✅ Found name from legacy field: {$name}");
            }
            if (empty($email)) {
                $email = $data['email'] ?? $data['customer_email'] ?? null;
                if ($email) error_log("✅ Found email from legacy field: {$email}");
            }
            if (empty($phone)) {
                $phone = $data['phone'] ?? $data['customer_phone'] ?? null;
                if ($phone) error_log("✅ Found phone from legacy field: {$phone}");
            }
            
            $result = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone
            ];
            
            error_log("✅ extractContactData result: " . json_encode($result));
            return $result;
            
        } catch (Exception $e) {
            error_log("❌ extractContactData failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Return empty data instead of crashing
            return [
                'name' => null,
                'email' => null,
                'phone' => null
            ];
        }
    }
}