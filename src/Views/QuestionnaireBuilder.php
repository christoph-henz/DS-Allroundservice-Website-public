<?php

namespace DSAllround\Views;
use Exception;

require_once 'Page.php';

class QuestionnaireBuilder extends Page
{
    private $questionnaire;
    private $groups;
    private $questions;

    /**
     * Questionnaire Builder for Drag & Drop Group Management
     */
    protected function __construct($questionnaireId)
    {
        parent::__construct();
        $this->loadQuestionnaireData($questionnaireId);
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    private function loadQuestionnaireData($questionnaireId): void
    {
        try {
            // Load questionnaire basic data
            $stmt = $this->_database->prepare("SELECT * FROM questionnaires WHERE id = ?");
            $stmt->execute([$questionnaireId]);
            $this->questionnaire = $stmt->fetch();

            if (!$this->questionnaire) {
                throw new Exception("Questionnaire not found");
            }

            // Load groups for this questionnaire
            $stmt = $this->_database->prepare("
                SELECT * FROM question_groups 
                WHERE questionnaire_id = ? AND is_active = 1 
                ORDER BY sort_order ASC
            ");
            $stmt->execute([$questionnaireId]);
            $this->groups = $stmt->fetchAll();

            // Load all questions grouped by group_id
            $stmt = $this->_database->prepare("
                SELECT qs.*, qg.name as group_name, qg.sort_order as group_sort_order
                FROM questions_simple qs
                LEFT JOIN question_groups qg ON qs.group_id = qg.id
                JOIN questionnaire_questions qq ON qs.id = qq.question_id
                WHERE qq.questionnaire_id = ?
                ORDER BY qg.sort_order ASC, qs.sort_order_in_group ASC
            ");
            $stmt->execute([$questionnaireId]);
            $allQuestions = $stmt->fetchAll();

            // Group questions by group_id
            $this->questions = [];
            foreach ($allQuestions as $question) {
                $groupId = $question['group_id'] ?? 'ungrouped';
                if (!isset($this->questions[$groupId])) {
                    $this->questions[$groupId] = [];
                }
                $this->questions[$groupId][] = $question;
            }

        } catch (\PDOException $e) {
            error_log("Database error in loadQuestionnaireData: " . $e->getMessage());
            throw new Exception("Questionnaire data could not be loaded");
        }
    }

    /**
     * Static factory method to create questionnaire builder page
     */
    public static function create($questionnaireId): void
    {
        // Initialize session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            $page = new QuestionnaireBuilder($questionnaireId);
            $page->generateView();
        } catch (Exception $e) {
            header("HTTP/1.0 404 Not Found");
            header("Content-type: text/html; charset=UTF-8");
            echo "<h1>404 - Questionnaire not found</h1>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    protected function generateView(): void
    {
        $pageTitle = "Fragebogen Builder - " . $this->questionnaire['title'];
        $this->generatePageHeader($pageTitle);
        $this->generateBuilderContent();
    }

    protected function additionalMetaData(): void
    {
        echo <<<HTML
            <meta name="description" content="Drag & Drop Fragebogen Builder">
            <meta name="robots" content="noindex, nofollow">
            
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" type="text/css" href="public/assets/css/questionnaire-builder.css"/>
            
            <!-- Sortable.js for Drag & Drop -->
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
            <script src="public/assets/js/questionnaire-builder.js"></script>
        HTML;
    }

    private function generateBuilderContent(): void
    {
        $questionnaireTitle = htmlspecialchars($this->questionnaire['title']);
        
        echo <<<HTML
            <div class="questionnaire-builder">
                <div class="container">
                    <div class="builder-header">
                        <h1>Fragebogen Builder</h1>
                        <p class="builder-subtitle">{$questionnaireTitle}</p>
                        
                        <div class="builder-actions">
                            <button class="btn btn-secondary" id="previewBtn">
                                <i class="icon-eye"></i> Vorschau
                            </button>
                            <button class="btn btn-primary" id="saveBtn">
                                <i class="icon-save"></i> Änderungen speichern
                            </button>
                        </div>
                    </div>

                    <div class="builder-content">
                        <!-- Sidebar mit verfügbaren Fragen -->
                        <div class="builder-sidebar">
                            <h3>Verfügbare Fragen</h3>
                            <div class="question-types">
                                <div class="question-type-group">
                                    <h4>Neue Frage erstellen</h4>
                                    <div class="new-question-items" id="newQuestionItems">
                                        <div class="question-item new-question" data-type="text" draggable="true">
                                            <i class="icon-text"></i>
                                            <span>Text-Eingabe</span>
                                        </div>
                                        <div class="question-item new-question" data-type="email" draggable="true">
                                            <i class="icon-email"></i>
                                            <span>E-Mail</span>
                                        </div>
                                        <div class="question-item new-question" data-type="phone" draggable="true">
                                            <i class="icon-phone"></i>
                                            <span>Telefon</span>
                                        </div>
                                        <div class="question-item new-question" data-type="select" draggable="true">
                                            <i class="icon-list"></i>
                                            <span>Auswahl-Liste</span>
                                        </div>
                                        <div class="question-item new-question" data-type="radio" draggable="true">
                                            <i class="icon-radio"></i>
                                            <span>Radio-Buttons</span>
                                        </div>
                                        <div class="question-item new-question" data-type="checkbox" draggable="true">
                                            <i class="icon-checkbox"></i>
                                            <span>Checkboxen</span>
                                        </div>
                                        <div class="question-item new-question" data-type="textarea" draggable="true">
                                            <i class="icon-textarea"></i>
                                            <span>Text-Bereich</span>
                                        </div>
                                        <div class="question-item new-question" data-type="date" draggable="true">
                                            <i class="icon-date"></i>
                                            <span>Datum</span>
                                        </div>
                                        <div class="question-item new-question" data-type="number" draggable="true">
                                            <i class="icon-number"></i>
                                            <span>Zahl</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hauptbereich mit Gruppen -->
                        <div class="builder-main">
                            <div class="groups-container" id="groupsContainer">
        HTML;

        $this->generateGroupsAndQuestions();

        echo <<<HTML
                            </div>
                            
                            <div class="drop-zone-new-group" id="newGroupDropZone">
                                <div class="drop-zone-content">
                                    <i class="icon-plus-circle"></i>
                                    <h4>Neue Gruppe erstellen</h4>
                                    <p>Ziehen Sie zwei Fragen hierher, um eine neue Gruppe zu erstellen</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Question Edit Modal -->
            <div class="modal" id="questionEditModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Frage bearbeiten</h3>
                        <button class="modal-close" data-close="questionEditModal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="questionEditForm">
                            <input type="hidden" id="editQuestionId" name="question_id">
                            
                            <div class="form-group">
                                <label for="editQuestionText">Fragetext *</label>
                                <textarea id="editQuestionText" name="question_text" required rows="2"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="editQuestionType">Frage-Typ</label>
                                <select id="editQuestionType" name="question_type">
                                    <option value="text">Text-Eingabe</option>
                                    <option value="email">E-Mail</option>
                                    <option value="phone">Telefon</option>
                                    <option value="textarea">Text-Bereich</option>
                                    <option value="select">Auswahl-Liste</option>
                                    <option value="radio">Radio-Buttons</option>
                                    <option value="checkbox">Checkboxen</option>
                                    <option value="date">Datum</option>
                                    <option value="number">Zahl</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="editPlaceholder">Platzhalter-Text</label>
                                <input type="text" id="editPlaceholder" name="placeholder_text">
                            </div>
                            
                            <div class="form-group">
                                <label for="editHelpText">Hilfe-Text</label>
                                <textarea id="editHelpText" name="help_text" rows="2"></textarea>
                            </div>
                            
                            <div class="form-group options-group" style="display: none;">
                                <label for="editOptions">Optionen (eine pro Zeile)</label>
                                <textarea id="editOptions" name="options" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="checkbox" id="editIsRequired" name="is_required">
                                    <span class="checkmark"></span>
                                    Pflichtfeld
                                </label>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-close="questionEditModal">Abbrechen</button>
                        <button class="btn btn-danger" id="deleteQuestionBtn">Löschen</button>
                        <button class="btn btn-primary" id="saveQuestionBtn">Speichern</button>
                    </div>
                </div>
            </div>

            <!-- Group Edit Modal -->
            <div class="modal" id="groupEditModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Gruppe bearbeiten</h3>
                        <button class="modal-close" data-close="groupEditModal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="groupEditForm">
                            <input type="hidden" id="editGroupId" name="group_id">
                            
                            <div class="form-group">
                                <label for="editGroupName">Gruppen-Name *</label>
                                <input type="text" id="editGroupName" name="group_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="editGroupDescription">Beschreibung</label>
                                <textarea id="editGroupDescription" name="group_description" rows="2"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-close="groupEditModal">Abbrechen</button>
                        <button class="btn btn-danger" id="deleteGroupBtn">Gruppe löschen</button>
                        <button class="btn btn-primary" id="saveGroupBtn">Speichern</button>
                    </div>
                </div>
            </div>

            <!-- Sortable.js Library -->
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
            
            <!-- Questionnaire Builder JavaScript -->
            <script src="/public/assets/js/questionnaire-builder.js"></script>
            
            <script>
                // Pass data to JavaScript
                window.questionnaireData = {
                    id: {$this->questionnaire['id']},
                    title: '{$questionnaireTitle}'
                };
            </script>
        HTML;
    }

    private function generateGroupsAndQuestions(): void
    {
        foreach ($this->groups as $group) {
            $groupId = $group['id'];
            $groupName = htmlspecialchars($group['name']);
            $groupDescription = htmlspecialchars($group['description'] ?? '');
            $isFixed = $group['is_fixed'] ?? 0;
            $fixedClass = $isFixed ? 'fixed-group' : '';
            $fixedBadge = $isFixed ? '<span class="badge badge-fixed">Feste Kontaktfelder</span>' : '';
            $questionsInGroup = $this->questions[$groupId] ?? [];

            echo <<<HTML
                <div class="question-group {$fixedClass}" data-group-id="{$groupId}" data-is-fixed="{$isFixed}">
                    <div class="group-header">
                        <h3 class="group-title" data-editable="group-name">{$groupName}</h3>
                        {$fixedBadge}
                        {$this->renderGroupDescription($groupDescription)}
                        <div class="group-actions">
            HTML;
            
            if (!$isFixed) {
                echo <<<HTML
                            <button class="btn-icon" data-action="edit-group" data-group-id="{$groupId}">
                                <i class="icon-edit"></i>
                            </button>
                            <button class="btn-icon" data-action="delete-group" data-group-id="{$groupId}">
                                <i class="icon-trash"></i>
                            </button>
                HTML;
            } else {
                echo <<<HTML
                            <button class="btn-icon" disabled title="Feste Gruppe kann nicht bearbeitet werden">
                                <i class="icon-lock"></i>
                            </button>
                HTML;
            }
            
            echo <<<HTML
                        </div>
                    </div>
                    
                    <div class="questions-list" data-group-id="{$groupId}">
            HTML;

            foreach ($questionsInGroup as $question) {
                $this->renderQuestionItem($question);
            }

            if (!$isFixed) {
                echo <<<HTML
                            <div class="drop-zone-add-question">
                                <i class="icon-plus"></i>
                                <span>Frage hierher ziehen</span>
                            </div>
                HTML;
            }

            echo <<<HTML
                    </div>
                </div>
            HTML;
        }

        // Ungrouped questions (if any)
        if (isset($this->questions['ungrouped']) && !empty($this->questions['ungrouped'])) {
            echo <<<HTML
                <div class="question-group ungrouped" data-group-id="ungrouped">
                    <div class="group-header">
                        <h3 class="group-title">Nicht gruppierte Fragen</h3>
                        <p class="group-description">Diese Fragen gehören zu keiner Gruppe</p>
                    </div>
                    
                    <div class="questions-list" data-group-id="ungrouped">
            HTML;

            foreach ($this->questions['ungrouped'] as $question) {
                $this->renderQuestionItem($question);
            }

            echo <<<HTML
                    </div>
                </div>
            HTML;
        }
    }

    private function renderGroupDescription($description): string
    {
        if (!empty($description)) {
            return "<p class=\"group-description\" data-editable=\"group-description\">{$description}</p>";
        }
        return "<p class=\"group-description placeholder\" data-editable=\"group-description\">Beschreibung hinzufügen...</p>";
    }

    private function renderQuestionItem($question): void
    {
        $questionId = $question['id'];
        $questionText = htmlspecialchars($question['question_text']);
        $questionType = htmlspecialchars($question['question_type']);
        $isRequired = $question['is_required'] ? 'required' : '';
        $isFixed = $question['is_fixed'] ?? 0;
        $fixedClass = $isFixed ? 'fixed-question' : '';
        $typeIcon = $this->getQuestionTypeIcon($questionType);

        echo <<<HTML
            <div class="question-item {$fixedClass}" data-question-id="{$questionId}" data-type="{$questionType}" data-is-fixed="{$isFixed}" draggable="true">
                <div class="question-drag-handle">
                    <i class="icon-drag"></i>
                </div>
                
                <div class="question-content">
                    <div class="question-type-icon">
                        <i class="{$typeIcon}"></i>
                    </div>
                    <div class="question-details">
                        <h4 class="question-text" data-editable="question-text">{$questionText}</h4>
                        <div class="question-meta">
                            <span class="question-type">{$this->getQuestionTypeLabel($questionType)}</span>
                            {$this->renderRequiredBadge($isRequired)}
        HTML;
        
        if ($isFixed) {
            echo '<span class="badge badge-fixed-small">Fest</span>';
        }
        
        echo <<<HTML
                        </div>
                    </div>
                </div>
                
                <div class="question-actions">
        HTML;
        
        if (!$isFixed) {
            echo <<<HTML
                    <button class="btn-icon" data-action="edit-question" data-question-id="{$questionId}">
                        <i class="icon-edit"></i>
                    </button>
                    <button class="btn-icon" data-action="delete-question" data-question-id="{$questionId}">
                        <i class="icon-trash"></i>
                    </button>
            HTML;
        } else {
            echo <<<HTML
                    <button class="btn-icon" disabled title="Feste Frage kann nicht bearbeitet werden">
                        <i class="icon-lock"></i>
                    </button>
            HTML;
        }
        
        echo <<<HTML
                </div>
            </div>
        HTML;
    }

    private function getQuestionTypeIcon($type): string
    {
        $icons = [
            'text' => 'icon-text',
            'email' => 'icon-email',
            'phone' => 'icon-phone',
            'textarea' => 'icon-textarea',
            'select' => 'icon-list',
            'radio' => 'icon-radio',
            'checkbox' => 'icon-checkbox',
            'date' => 'icon-date',
            'number' => 'icon-number'
        ];
        
        return $icons[$type] ?? 'icon-question';
    }

    private function getQuestionTypeLabel($type): string
    {
        $labels = [
            'text' => 'Text',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'textarea' => 'Text-Bereich',
            'select' => 'Auswahl',
            'radio' => 'Radio',
            'checkbox' => 'Checkbox',
            'date' => 'Datum',
            'number' => 'Zahl'
        ];
        
        return $labels[$type] ?? ucfirst($type);
    }

    private function renderRequiredBadge($isRequired): string
    {
        if ($isRequired) {
            return '<span class="badge badge-required">Pflicht</span>';
        }
        return '';
    }
}
?>
