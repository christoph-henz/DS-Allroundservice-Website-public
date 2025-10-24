<?php

namespace DSAllround\Views;
use Exception;

require_once 'Page.php';

class DynamicQuestionnaire extends Page
{
    private $service;
    private $questionnaire;
    private $questions;

    /**
     * Instantiates members and loads questionnaire data from database
     */
    protected function __construct($serviceSlug)
    {
        parent::__construct();
        $this->loadQuestionnaireData($serviceSlug);
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    /**
     * Load service and questionnaire data from database
     */
    private function loadQuestionnaireData($serviceSlug): void
    {
        try {
            // Debug: Log the service slug
            error_log("🔍 Loading questionnaire for service slug: " . $serviceSlug);
            
            // Load service basic data
            $stmt = $this->_database->prepare("SELECT * FROM services WHERE slug = ? AND is_active = 1");
            $stmt->execute([$serviceSlug]);
            $this->service = $stmt->fetch();

            if (!$this->service) {
                error_log("❌ No active service found for slug: " . $serviceSlug);
                throw new Exception("Service not found or inactive");
            }
            
            error_log("✅ Service loaded: " . json_encode($this->service));

            // Load questionnaire for this service
            $stmt = $this->_database->prepare("
                SELECT * FROM questionnaires 
                WHERE service_id = ? AND status IN ('active', 'published') 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$this->service['id']]);
            $this->questionnaire = $stmt->fetch();

            if (!$this->questionnaire) {
                error_log("❌ No active questionnaire found for service ID: " . $this->service['id']);
                throw new Exception("No questionnaire found for this service");
            }
            
            error_log("✅ Questionnaire loaded: " . json_encode($this->questionnaire));

            // Load questions for this questionnaire with group information
            $stmt = $this->_database->prepare("
                SELECT qs.*, qq.sort_order, qq.id as relationship_id, qq.group_id,
                       COALESCE(qg.name, 'Ungrouped') as group_name, 
                       COALESCE(qg.description, '') as group_description, 
                       COALESCE(qg.sort_order, 999) as group_sort_order
                FROM questionnaire_questions qq
                JOIN questions_simple qs ON qq.question_id = qs.id
                LEFT JOIN question_groups qg ON qq.group_id = qg.id
                WHERE qq.questionnaire_id = ?
                ORDER BY COALESCE(qg.sort_order, 999) ASC, qq.sort_order ASC, qs.id ASC
            ");
            $stmt->execute([$this->questionnaire['id']]);
            $this->questions = $stmt->fetchAll();
            
            error_log("📊 Raw questions query result: " . json_encode($this->questions));
            
            if (empty($this->questions)) {
                error_log("❌ No questions found for questionnaire ID: " . $this->questionnaire['id']);
            } else {
                // Debug: Log individual questions with group info
                foreach ($this->questions as $index => $question) {
                    error_log("🔸 Question {$index}: ID={$question['id']}, Text='{$question['question_text']}', GroupID={$question['group_id']}, GroupName='{$question['group_name']}'");
                }
            }

        } catch (\PDOException $e) {
            error_log("Database error in loadQuestionnaireData: " . $e->getMessage());
            throw new Exception("Questionnaire data could not be loaded");
        }
    }

    /**
     * Organize questions by groups
     */
    private function organizeQuestionsByGroups(): array
    {
        error_log("🔄 Starting to organize " . count($this->questions) . " questions by groups");
        
        $organizedData = [
            'groups' => [],
            'ungrouped' => [],
            'flat_questions' => []
        ];

        foreach ($this->questions as $question) {
            // Add to flat questions for overall navigation
            $organizedData['flat_questions'][] = $question;

            if (!empty($question['group_id'])) {
                $groupId = $question['group_id'];
                
                // Initialize group if not exists
                if (!isset($organizedData['groups'][$groupId])) {
                    $organizedData['groups'][$groupId] = [
                        'id' => $groupId,
                        'name' => $question['group_name'] ?? 'Gruppe ' . $groupId,
                        'description' => $question['group_description'] ?? '',
                        'sort_order' => $question['group_sort_order'] ?? 0,
                        'questions' => []
                    ];
                    error_log("🆕 Created new group: ID={$groupId}, Name='{$question['group_name']}'");
                }
                
                // Add question to group
                $organizedData['groups'][$groupId]['questions'][] = $question;
                error_log("➕ Added question '{$question['question_text']}' to group '{$question['group_name']}'");
            } else {
                // Add to ungrouped
                $organizedData['ungrouped'][] = $question;
                error_log("📝 Added question '{$question['question_text']}' to ungrouped");
            }
        }

        // Sort groups by sort_order
        uasort($organizedData['groups'], function($a, $b) {
            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        });

        error_log("📊 Final organization summary:");
        error_log("   - Groups: " . count($organizedData['groups']));
        foreach ($organizedData['groups'] as $group) {
            error_log("     * {$group['name']}: " . count($group['questions']) . " questions");
        }
        error_log("   - Ungrouped: " . count($organizedData['ungrouped']) . " questions");
        error_log("📋 Organized questions structure: " . json_encode($organizedData));

        return $organizedData;
    }

    /**
     * Generate grouped questions HTML
     */
    private function generateGroupedQuestions($organizedQuestions): void
    {
        $stepIndex = 0;
        $totalSteps = count($organizedQuestions['groups']) + count($organizedQuestions['ungrouped']);
        $totalQuestions = count($organizedQuestions['flat_questions']);

        // If we have groups, show them as group cards
        if (!empty($organizedQuestions['groups'])) {
            foreach ($organizedQuestions['groups'] as $group) {
                $this->generateGroupStepHTML($group, $stepIndex, $totalSteps);
                $stepIndex++;
            }
        }

        // Generate ungrouped questions individually (backward compatibility)
        if (!empty($organizedQuestions['ungrouped'])) {
            foreach ($organizedQuestions['ungrouped'] as $questionIndex => $question) {
                $this->generateQuestionHTML($question, $stepIndex, $totalSteps);
                $stepIndex++;
            }
        }
    }

    /**
     * Generate a group step with all its questions on one card
     */
    private function generateGroupStepHTML($group, $stepIndex, $totalSteps): void
    {
        $groupName = htmlspecialchars($group['name']);
        $groupDescription = htmlspecialchars($group['description']);
        $isFirstStep = ($stepIndex === 0);
        $isLastStep = ($stepIndex === $totalSteps - 1);
        
        $displayStyle = $isFirstStep ? 'display: block;' : 'display: none;';
        $stepNumber = $stepIndex + 1;

        echo <<<HTML
            <div class="question-step group-step" data-step="{$stepIndex}" style="{$displayStyle}">
                <div class="question-group-card">
                    <div class="group-card-header">
                        <div class="question-number">Schritt {$stepNumber} von {$totalSteps}</div>
                        <h2 class="group-title">{$groupName}</h2>
        HTML;
        
        if ($groupDescription) {
            echo "<p class=\"group-description\">{$groupDescription}</p>";
        }
        
        echo <<<HTML
                    </div>
                    <div class="group-questions">
        HTML;

        // Generate all questions in this group
        foreach ($group['questions'] as $question) {
            $this->generateGroupQuestionHTML($question);
        }

        echo <<<HTML
                    </div>
                </div>
                
                <div class="question-navigation">
        HTML;
        
        if (!$isFirstStep) {
            $prevStep = $stepIndex - 1;
            echo "<button type=\"button\" class=\"btn btn-secondary prev-btn\" data-target=\"{$prevStep}\">Zurück</button>";
        }
        
        if (!$isLastStep) {
            $nextStep = $stepIndex + 1;
            echo "<button type=\"button\" class=\"btn btn-primary next-btn\" data-target=\"{$nextStep}\">Weiter</button>";
        } else {
            echo '<button type="button" class="btn btn-primary final-btn">Zusammenfassung</button>';
        }
        
        echo <<<HTML
                </div>
            </div>
        HTML;
    }

    /**
     * Generate a question within a group (without navigation)
     */
    private function generateGroupQuestionHTML($question): void
    {
        $questionId = $question['id'];
        $questionText = htmlspecialchars($question['question_text']);
        $questionType = $question['question_type'];
        $placeholder = htmlspecialchars($question['placeholder_text'] ?? '');
        $helpText = htmlspecialchars($question['help_text'] ?? '');
        $isRequired = (bool)$question['is_required'];
        
        // Better options handling
        $options = [];
        if (isset($question['options'])) {
            if (is_array($question['options'])) {
                $options = $question['options'];
            } else if (is_string($question['options']) && !empty($question['options'])) {
                $optionsString = trim($question['options']);
                if (strpos($optionsString, '[') === 0 || strpos($optionsString, '{') === 0) {
                    $options = json_decode($optionsString, true) ?? [];
                } else if (strpos($optionsString, "\n") !== false) {
                    $options = array_filter(array_map('trim', explode("\n", $optionsString)));
                } else if (strpos($optionsString, ",") !== false) {
                    $options = array_filter(array_map('trim', explode(",", $optionsString)));
                } else {
                    $options = [$optionsString];
                }
            }
        }
        
        $requiredAttr = $isRequired ? 'required' : '';
        $requiredLabel = $isRequired ? ' <span class="required">*</span>' : '';

        echo <<<HTML
            <div class="group-question">
                <label class="question-label">
                    {$questionText}{$requiredLabel}
                </label>
        HTML;
        
        if ($helpText) {
            echo "<p class=\"question-help\">{$helpText}</p>";
        }

        echo '<div class="question-input-container">';
        $this->renderQuestionInput($questionId, $questionType, $placeholder, $options, $requiredAttr);
        echo '</div>';
        echo '</div>';
    }

    /**
     * Static factory method to create questionnaire page
     */
    public static function create($serviceSlug): void
    {
        // Initialize session and CSRF token if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }
        
        try {
            $page = new DynamicQuestionnaire($serviceSlug);
            $page->generateView();
        } catch (Exception $e) {
            // Show error page or redirect to service page
            header("Location: /{$serviceSlug}");
            exit;
        }
    }

    protected function generateView(): void
    {
        $pageTitle = $this->questionnaire['title'] ?? "{$this->service['name']} Anfrage";
        $this->generatePageHeader($pageTitle);
        
        // Debug: Output API data in console and browser
        $this->outputDebugInfo();
        
        $this->generateQuestionnaire();
    }

    /**
     * Output debug information about groups and questions
     */
    private function outputDebugInfo(): void
    {
        $organizedQuestions = $this->organizeQuestionsByGroups();
        
        // Also output visible debug panel (only if debug parameter is set)
        if (isset($_GET['debug'])) {
            echo "<div style='position: fixed; top: 10px; right: 10px; background: rgba(0,0,0,0.9); color: white; padding: 20px; max-width: 400px; max-height: 80vh; overflow-y: auto; z-index: 9999; border-radius: 8px; font-family: monospace; font-size: 12px;'>";
            echo "<h3 style='color: #4CAF50; margin-top: 0;'>🔍 Debug Info</h3>";
            echo "<h4>Service:</h4><pre>" . json_encode($this->service, JSON_PRETTY_PRINT) . "</pre>";
            echo "<h4>Questionnaire:</h4><pre>" . json_encode($this->questionnaire, JSON_PRETTY_PRINT) . "</pre>";
            echo "<h4>Organized Questions:</h4><pre>" . json_encode($organizedQuestions, JSON_PRETTY_PRINT) . "</pre>";
            echo "</div>";
        }
    }

    private function generateQuestionnaire(): void
    {
        $this->generateQuestionnaireHeader();
        $this->generateQuestionnaireForm();
        $this->generateQuestionnaireFooter();
    }

    protected function additionalMetaData(): void
    {
        $serviceName = htmlspecialchars($this->service['name']);
        
        echo <<<HTML
            <meta name="description" content="Fordern Sie jetzt Ihr individuelles Angebot für {$serviceName} an. Unverbindlich und kostenlos.">
            <meta name="robots" content="noindex, nofollow">
            
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" type="text/css" href="public/assets/css/dynamic-questionnaire.css"/>
            <script src="public/assets/js/dynamic-questionnaire.js"></script>
        HTML;
    }

    private function generateQuestionnaireHeader(): void
    {
        $serviceName = htmlspecialchars($this->service['name']);
        $questionnaireTitle = htmlspecialchars($this->questionnaire['title']);
        $questionnaireDescription = htmlspecialchars($this->questionnaire['description'] ?? '');
        $serviceColor = $this->service['color'] ?? '#007cba';

        echo <<<HTML
            <div class="questionnaire-header" style="--service-color: {$serviceColor}">
                <div class="container">
                    <div class="breadcrumb">
                        <a href="/">Home</a>
                        <span>/</span>
                        <a href="/{$this->service['slug']}">{$serviceName}</a>
                        <span>/</span>
                        <span>Anfrage</span>
                    </div>
                    
                    <div class="questionnaire-intro">
                        <h1>{$questionnaireTitle}</h1>
                        <p class="questionnaire-description">{$questionnaireDescription}</p>
                        
                        <div class="questionnaire-benefits">
                            <div class="benefit-item">
                                <span class="benefit-icon">✓</span>
                                <span>Kostenlose & unverbindliche Anfrage</span>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon">✓</span>
                                <span>Individuelle Beratung</span>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon">✓</span>
                                <span>Maßgeschneidertes Angebot</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }

    private function generateQuestionnaireForm(): void
    {
        $organizedQuestions = $this->organizeQuestionsByGroups();
        $totalQuestions = count($organizedQuestions['flat_questions']);
        
        echo <<<HTML
            <div class="questionnaire-main">
                <div class="container">
                    <div class="questionnaire-container">
                        <div class="questionnaire-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressBar"></div>
                            </div>
                            <div class="progress-text">
                                <span id="currentStep">1</span> von <span id="totalSteps">{$totalQuestions}</span> Fragen
                            </div>
                        </div>
                        
                        <form id="dynamicQuestionnaireForm" class="questionnaire-form" novalidate>
                            <input type="hidden" name="service_id" value="{$this->service['id']}">
                            <input type="hidden" name="service_slug" value="{$this->service['slug']}">
                            <input type="hidden" name="questionnaire_id" value="{$this->questionnaire['id']}">
                            <input type="hidden" name="csrf_token" value="{$_SESSION['token']}">
        HTML;

        // Generate grouped questions
        $this->generateGroupedQuestions($organizedQuestions);

        echo <<<HTML
                            <div class="question-step final-step" style="display: none;">
                                <div class="question-content">
                                    <h2>Vielen Dank!</h2>
                                    <p>Ihre Angaben sind vollständig. Klicken Sie auf "Anfrage senden", um Ihr individuelles Angebot zu erhalten.</p>
                                    
                                    <div class="form-summary" id="formSummary">
                                        <!-- Wird dynamisch gefüllt -->
                                    </div>
                                </div>
                                
                                <div class="question-navigation">
                                    <button type="button" class="btn btn-secondary" id="prevBtnFinal">Zurück</button>
                                    <button type="submit" class="btn btn-primary btn-submit">
                                        <span class="btn-text">Anfrage senden</span>
                                        <span class="btn-loading" style="display: none;">
                                            <span class="loading-spinner"></span>
                                            Wird gesendet...
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        HTML;
    }

    private function generateQuestionHTML($question, $index, $totalQuestions): void
    {
        $questionId = $question['id'];
        $questionText = htmlspecialchars($question['question_text']);
        $questionType = $question['question_type'];
        $placeholder = htmlspecialchars($question['placeholder_text'] ?? '');
        $helpText = htmlspecialchars($question['help_text'] ?? '');
        $isRequired = (bool)$question['is_required'];
        
        // Better options handling - check if it's already an array or JSON string
        $options = [];
        if (isset($question['options'])) {
            if (is_array($question['options'])) {
                $options = $question['options'];
            } else if (is_string($question['options']) && !empty($question['options'])) {
                // Handle different formats: JSON array, newline-separated, or comma-separated
                $optionsString = trim($question['options']);
                if (strpos($optionsString, '[') === 0 || strpos($optionsString, '{') === 0) {
                    // It's JSON
                    $options = json_decode($optionsString, true) ?? [];
                } else if (strpos($optionsString, "\n") !== false) {
                    // It's newline-separated
                    $options = array_filter(array_map('trim', explode("\n", $optionsString)));
                } else if (strpos($optionsString, ",") !== false) {
                    // It's comma-separated
                    $options = array_filter(array_map('trim', explode(",", $optionsString)));
                } else {
                    // Single option
                    $options = [$optionsString];
                }
            }
        }
        
        $isFirstQuestion = ($index === 0);
        $isLastQuestion = ($index === $totalQuestions - 1);
        
        $displayStyle = $isFirstQuestion ? 'display: block;' : 'display: none;';
        $requiredAttr = $isRequired ? 'required' : '';
        $requiredLabel = $isRequired ? ' <span class="required">*</span>' : '';
        $questionNumber = $index + 1;

        echo <<<HTML
            <div class="question-step" data-step="{$index}" style="{$displayStyle}">
                <div class="question-content">
                    <div class="question-number">Frage {$questionNumber} von {$totalQuestions}</div>
                    <h2 class="question-title">{$questionText}{$requiredLabel}</h2>
        HTML;
        
        if ($helpText) {
            echo "<p class=\"question-help\">{$helpText}</p>";
        }

        echo '<div class="question-input-container">';
        
        $this->renderQuestionInput($questionId, $questionType, $placeholder, $options, $requiredAttr);
        
        echo '</div>';
        echo '</div>';

        // Navigation buttons
        echo '<div class="question-navigation">';
        
        if (!$isFirstQuestion) {
            $prevStep = $index - 1;
            echo "<button type=\"button\" class=\"btn btn-secondary prev-btn\" data-target=\"{$prevStep}\">Zurück</button>";
        }
        
        if (!$isLastQuestion) {
            $nextStep = $index + 1;
            echo "<button type=\"button\" class=\"btn btn-primary next-btn\" data-target=\"{$nextStep}\">Weiter</button>";
        } else {
            echo '<button type="button" class="btn btn-primary final-btn">Zusammenfassung</button>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    private function renderQuestionInput($questionId, $questionType, $placeholder, $options, $requiredAttr): void
    {
        switch ($questionType) {
            case 'text':
                echo "<input type=\"text\" id=\"question_{$questionId}\" name=\"question_{$questionId}\" class=\"form-input\" placeholder=\"{$placeholder}\" {$requiredAttr}>";
                break;
                
            case 'email':
                echo "<input type=\"email\" id=\"question_{$questionId}\" name=\"question_{$questionId}\" class=\"form-input\" placeholder=\"{$placeholder}\" {$requiredAttr}>";
                break;
                
            case 'phone':
            case 'tel':
                echo "<input type=\"tel\" id=\"question_{$questionId}\" name=\"question_{$questionId}\" class=\"form-input\" placeholder=\"{$placeholder}\" {$requiredAttr}>";
                break;
                
            case 'textarea':
                echo "<textarea id=\"question_{$questionId}\" name=\"question_{$questionId}\" class=\"form-textarea\" placeholder=\"{$placeholder}\" rows=\"4\" {$requiredAttr}></textarea>";
                break;
                
            case 'select':
                echo "<select id=\"question_{$questionId}\" name=\"question_{$questionId}\" class=\"form-select\" {$requiredAttr}>";
                echo "<option value=\"\">Bitte wählen...</option>";
                
                // Debug: Check if options array is valid
                if (is_array($options) && !empty($options)) {
                    foreach ($options as $option) {
                        if (!empty($option)) {
                            $optionValue = htmlspecialchars(trim($option));
                            echo "<option value=\"{$optionValue}\">{$optionValue}</option>";
                        }
                    }
                } else {
                    // Fallback: Add a debug option
                    echo "<option value=\"debug\">Keine Optionen verfügbar (Debug: " . (is_array($options) ? count($options) : 'not array') . ")</option>";
                }
                echo "</select>";
                break;
                
            case 'radio':
                echo '<div class="radio-group">';
                if (is_array($options) && !empty($options)) {
                    foreach ($options as $optionIndex => $option) {
                        if (!empty($option)) {
                            $optionValue = htmlspecialchars(trim($option));
                            $optionId = "question_{$questionId}_option_{$optionIndex}";
                            // For radio buttons, only the first one needs the required attribute for HTML5 validation
                            $radioRequired = ($requiredAttr && $optionIndex === 0) ? $requiredAttr : '';
                            echo <<<HTML
                                <label class="radio-option" for="{$optionId}">
                                    <input type="radio" id="{$optionId}" name="question_{$questionId}" value="{$optionValue}" {$radioRequired}>
                                    <span class="radio-checkmark"></span>
                                    <span class="radio-label">{$optionValue}</span>
                                </label>
                            HTML;
                        }
                    }
                } else {
                    echo '<p class="error">Keine Optionen verfügbar</p>';
                }
                echo '</div>';
                break;
                
            case 'checkbox':
                echo '<div class="checkbox-group">';
                if (is_array($options) && !empty($options)) {
                    foreach ($options as $optionIndex => $option) {
                        if (!empty($option)) {
                            $optionValue = htmlspecialchars(trim($option));
                            $optionId = "question_{$questionId}_option_{$optionIndex}";
                            echo <<<HTML
                                <label class="checkbox-option" for="{$optionId}">
                                    <input type="checkbox" id="{$optionId}" name="question_{$questionId}[]" value="{$optionValue}">
                                    <span class="checkbox-checkmark"></span>
                                    <span class="checkbox-label">{$optionValue}</span>
                                </label>
                            HTML;
                        }
                    }
                } else {
                    echo '<p class="error">Keine Optionen verfügbar</p>';
                }
                echo '</div>';
                break;
                
            case 'number':
                echo "<input type=\"number\" id=\"question_{$questionId}\" name=\"question_{$questionId}\" class=\"form-input\" placeholder=\"{$placeholder}\" {$requiredAttr}>";
                break;
                
            case 'date':
                echo "<input type=\"date\" id=\"question_{$questionId}\" name=\"question_{$questionId}\" class=\"form-input\" {$requiredAttr}>";
                break;
        }
    }

    private function generateQuestionnaireFooter(): void
    {
        $serviceName = htmlspecialchars($this->service['name']);
        
        echo <<<HTML
            <div class="questionnaire-footer">
                <div class="container">
                    <div class="footer-content">
                        <div class="footer-info">
                            <h3>Ihre Vorteile bei DS-Allroundservice</h3>
                            <ul>
                                <li>✓ Kostenlose & unverbindliche Beratung</li>
                                <li>✓ Transparente Preisgestaltung</li>
                                <li>✓ Erfahrenes & zuverlässiges Team</li>
                                <li>✓ Umfassende Versicherung</li>
                            </ul>
                        </div>
                        
                        <div class="footer-contact">
                            <h3>Haben Sie Fragen?</h3>
                            <p>Unser Team steht Ihnen gerne zur Verfügung:</p>
                            <div class="contact-info">
                                <div class="contact-item">
                                    <span class="contact-icon">📞</span>
                                    <a href="tel:+49123456789">+49 123 456 789</a>
                                </div>
                                <div class="contact-item">
                                    <span class="contact-icon">✉️</span>
                                    <a href="mailto:info@ds-allroundservice.de">info@ds-allroundservice.de</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }
}
