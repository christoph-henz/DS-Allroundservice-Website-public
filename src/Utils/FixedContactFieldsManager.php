<?php
/**
 * Helper Class: Fixed Contact Fields Manager
 * 
 * This class provides methods to automatically create fixed contact fields
 * when a new questionnaire is created.
 */

class FixedContactFieldsManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Create fixed contact fields group and questions for a questionnaire
     * 
     * @param int $questionnaireId The ID of the questionnaire
     * @return array Array with group_id and question_ids
     */
    public function createFixedContactFields($questionnaireId) {
        try {
            $this->db->exec('BEGIN TRANSACTION');
            
            // Check if fixed group already exists
            $stmt = $this->db->prepare("
                SELECT id FROM question_groups 
                WHERE questionnaire_id = ? AND is_fixed = 1
            ");
            $stmt->execute([$questionnaireId]);
            $existingGroup = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingGroup) {
                error_log("Fixed contact group already exists for questionnaire {$questionnaireId}");
                $this->db->exec('ROLLBACK');
                return ['group_id' => $existingGroup['id'], 'question_ids' => []];
            }
            
            // Create fixed contact group
            $stmt = $this->db->prepare("
                INSERT INTO question_groups (questionnaire_id, name, description, sort_order, is_fixed, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $questionnaireId,
                'Kontaktinformationen',
                'Bitte geben Sie Ihre Kontaktdaten ein, damit wir Sie erreichen können.',
                -1,  // First position
                1,   // Fixed
                1    // Active
            ]);
            $groupId = $this->db->lastInsertId();
            
            // Define the 5 standard contact questions
            $contactQuestions = $this->getContactQuestions();
            $questionIds = [];
            
            foreach ($contactQuestions as $index => $questionData) {
                // Create question in questions_simple
                $stmt = $this->db->prepare("
                    INSERT INTO questions_simple 
                    (questionnaire_id, group_id, question_text, question_type, is_required, 
                     placeholder_text, help_text, sort_order, is_fixed, sort_order_in_group)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $questionnaireId,
                    $groupId,
                    $questionData['question_text'],
                    $questionData['question_type'],
                    $questionData['is_required'],
                    $questionData['placeholder_text'],
                    $questionData['help_text'],
                    $questionData['sort_order'],
                    1,  // is_fixed
                    $index  // sort_order_in_group
                ]);
                $questionId = $this->db->lastInsertId();
                $questionIds[] = $questionId;
                
                // Link to questionnaire via questionnaire_questions table
                $stmt = $this->db->prepare("
                    INSERT INTO questionnaire_questions (questionnaire_id, question_id, group_id, sort_order)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $questionnaireId,
                    $questionId,
                    $groupId,
                    $questionData['sort_order']
                ]);
            }
            
            $this->db->exec('COMMIT');
            
            return [
                'group_id' => $groupId,
                'question_ids' => $questionIds
            ];
            
        } catch (PDOException $e) {
            $this->db->exec('ROLLBACK');
            error_log("Error creating fixed contact fields: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get the definition of standard contact questions
     * 
     * @return array Array of contact question definitions
     */
    private function getContactQuestions() {
        return [
            [
                'question_text' => 'Vorname',
                'question_type' => 'text',
                'is_required' => 1,
                'placeholder_text' => 'Ihr Vorname',
                'help_text' => '',
                'sort_order' => 0
            ],
            [
                'question_text' => 'Nachname',
                'question_type' => 'text',
                'is_required' => 1,
                'placeholder_text' => 'Ihr Nachname',
                'help_text' => '',
                'sort_order' => 1
            ],
            [
                'question_text' => 'E-Mail Adresse',
                'question_type' => 'email',
                'is_required' => 1,
                'placeholder_text' => 'ihre.email@beispiel.de',
                'help_text' => '',
                'sort_order' => 2
            ],
            [
                'question_text' => 'Telefonnummer',
                'question_type' => 'phone',
                'is_required' => 0,
                'placeholder_text' => '+49 123 456789',
                'help_text' => 'Ihre Festnetznummer (optional)',
                'sort_order' => 3
            ],
            [
                'question_text' => 'Mobilnummer',
                'question_type' => 'phone',
                'is_required' => 0,
                'placeholder_text' => '+49 170 1234567',
                'help_text' => 'Ihre Mobilnummer (optional)',
                'sort_order' => 4
            ]
        ];
    }
    
    /**
     * Check if a questionnaire has fixed contact fields
     * 
     * @param int $questionnaireId The ID of the questionnaire
     * @return bool True if fixed contact fields exist
     */
    public function hasFixedContactFields($questionnaireId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM question_groups 
            WHERE questionnaire_id = ? AND is_fixed = 1
        ");
        $stmt->execute([$questionnaireId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
}
