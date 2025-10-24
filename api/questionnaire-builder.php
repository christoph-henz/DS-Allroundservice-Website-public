<?php

/**
 * QuestionnaireBuilderAPI - API endpoints for questionnaire builder functionality
 */

require_once __DIR__ . '/../config/config.php';

class QuestionnaireBuilderAPI {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * Handle API requests based on path and method
     */
    public function handleRequest($path, $method) {
        $pathParts = explode('/', trim($path, '/'));
        
        if (count($pathParts) < 2) {
            $this->sendResponse(['error' => 'Invalid API path'], 400);
            return;
        }

        $resource = $pathParts[1]; // api/[resource]
        $action = $pathParts[2] ?? null;
        $id = $pathParts[3] ?? null;

        try {
            switch ($resource) {
                case 'questions':
                    $this->handleQuestionsAPI($action, $id, $method);
                    break;
                case 'groups':
                    $this->handleGroupsAPI($action, $id, $method);
                    break;
                default:
                    $this->sendResponse(['error' => 'Unknown API resource'], 404);
            }
        } catch (Exception $e) {
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle questions API endpoints
     */
    private function handleQuestionsAPI($action, $id, $method) {
        switch ($action) {
            case 'create':
                if ($method !== 'POST') {
                    $this->sendResponse(['error' => 'Method not allowed'], 405);
                    return;
                }
                $this->createQuestion();
                break;

            case 'update':
                if ($method !== 'POST') {
                    $this->sendResponse(['error' => 'Method not allowed'], 405);
                    return;
                }
                $this->updateQuestion();
                break;

            case 'delete':
                if ($method !== 'DELETE') {
                    $this->sendResponse(['error' => 'Method not allowed'], 405);
                    return;
                }
                $this->deleteQuestion($id);
                break;

            case 'move':
                if ($method !== 'POST') {
                    $this->sendResponse(['error' => 'Method not allowed'], 405);
                    return;
                }
                $this->moveQuestion();
                break;

            case 'reorder':
                if ($method !== 'POST') {
                    $this->sendResponse(['error' => 'Method not allowed'], 405);
                    return;
                }
                $this->reorderQuestions();
                break;

            default:
                if ($method === 'GET' && $id) {
                    $this->getQuestion($id);
                } else {
                    $this->sendResponse(['error' => 'Invalid questions API action'], 400);
                }
        }
    }

    /**
     * Handle groups API endpoints
     */
    private function handleGroupsAPI($action, $id, $method) {
        switch ($action) {
            case 'create':
                if ($method !== 'POST') {
                    $this->sendResponse(['error' => 'Method not allowed'], 405);
                    return;
                }
                $this->createGroup();
                break;

            case 'update':
                if ($method !== 'POST') {
                    $this->sendResponse(['error' => 'Method not allowed'], 405);
                    return;
                }
                $this->updateGroup();
                break;

            case 'delete':
                if ($method !== 'DELETE') {
                    $this->sendResponse(['error' => 'Method not allowed'], 405);
                    return;
                }
                $this->deleteGroup($id);
                break;

            case 'reorder':
                if ($method !== 'POST') {
                    $this->sendResponse(['error' => 'Method not allowed'], 405);
                    return;
                }
                $this->reorderGroups();
                break;

            default:
                if ($method === 'GET' && $id) {
                    $this->getGroup($id);
                } else {
                    $this->sendResponse(['error' => 'Invalid groups API action'], 400);
                }
        }
    }

    /**
     * Create new question
     */
    private function createQuestion() {
        $data = $this->getRequestData();
        
        $requiredFields = ['questionnaire_id', 'question_type', 'question_text'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(['error' => "Missing required field: $field"], 400);
                return;
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO questions_simple 
            (questionnaire_id, question_text, question_type, placeholder_text, help_text, options, is_required, group_id, sort_order) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Get next order position
        $orderPosition = $this->getNextOrderPosition($data['questionnaire_id'], $data['group_id'] ?? null);

        $result = $stmt->execute([
            $data['questionnaire_id'],
            $data['question_text'],
            $data['question_type'],
            $data['placeholder_text'] ?? '',
            $data['help_text'] ?? '',
            $data['options'] ?? '',
            $data['is_required'] ?? 0,
            $data['group_id'] ?? null,
            $orderPosition
        ]);

        if ($result) {
            $questionId = $this->db->lastInsertRowID();
            $question = $this->getQuestionById($questionId);
            $this->sendResponse(['success' => true, 'question' => $question]);
        } else {
            $this->sendResponse(['error' => 'Failed to create question'], 500);
        }
    }

    /**
     * Update existing question
     */
    private function updateQuestion() {
        $data = $this->getRequestData();
        
        if (empty($data['id'])) {
            $this->sendResponse(['error' => 'Missing question ID'], 400);
            return;
        }

        // Check if question is fixed (not editable)
        $stmt = $this->db->prepare("SELECT is_fixed FROM questions_simple WHERE id = ?");
        $stmt->execute([$data['id']]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($question && $question['is_fixed']) {
            $this->sendResponse(['error' => 'Fixed contact questions cannot be edited'], 403);
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE questions_simple 
            SET question_text = ?, question_type = ?, placeholder_text = ?, help_text = ?, options = ?, is_required = ?
            WHERE id = ? AND is_fixed = 0
        ");

        $result = $stmt->execute([
            $data['question_text'],
            $data['question_type'],
            $data['placeholder_text'] ?? '',
            $data['help_text'] ?? '',
            $data['options'] ?? '',
            $data['is_required'] ?? 0,
            $data['id']
        ]);

        if ($result) {
            $question = $this->getQuestionById($data['id']);
            $this->sendResponse(['success' => true, 'question' => $question]);
        } else {
            $this->sendResponse(['error' => 'Failed to update question'], 500);
        }
    }

    /**
     * Delete question
     */
    private function deleteQuestion($questionId) {
        if (empty($questionId)) {
            $this->sendResponse(['error' => 'Missing question ID'], 400);
            return;
        }

        // Check if question is fixed (not deletable)
        $stmt = $this->db->prepare("SELECT is_fixed FROM questions_simple WHERE id = ?");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($question && $question['is_fixed']) {
            $this->sendResponse(['error' => 'Fixed contact questions cannot be deleted'], 403);
            return;
        }

        $stmt = $this->db->prepare("DELETE FROM questions_simple WHERE id = ? AND is_fixed = 0");
        $result = $stmt->execute([$questionId]);

        if ($result) {
            $this->sendResponse(['success' => true, 'message' => 'Question deleted']);
        } else {
            $this->sendResponse(['error' => 'Failed to delete question'], 500);
        }
    }

    /**
     * Move question to different group
     */
    private function moveQuestion() {
        $data = $this->getRequestData();
        
        $requiredFields = ['question_id', 'target_group_id', 'position'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $this->sendResponse(['error' => "Missing required field: $field"], 400);
                return;
            }
        }

        // Update question's group and position
        $stmt = $this->db->prepare("
            UPDATE questions_simple 
            SET group_id = ?, sort_order = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $data['target_group_id'] === 'ungrouped' ? null : $data['target_group_id'],
            $data['position'],
            $data['question_id']
        ]);

        if ($result) {
            $this->reorderQuestionsInGroup($data['target_group_id']);
            $this->sendResponse(['success' => true, 'message' => 'Question moved']);
        } else {
            $this->sendResponse(['error' => 'Failed to move question'], 500);
        }
    }

    /**
     * Reorder questions within a group
     */
    private function reorderQuestions() {
        $data = $this->getRequestData();
        
        if (empty($data['questions']) || empty($data['group_id'])) {
            $this->sendResponse(['error' => 'Missing questions array or group_id'], 400);
            return;
        }

        $this->db->exec('BEGIN TRANSACTION');

        try {
            $stmt = $this->db->prepare("UPDATE questions_simple SET sort_order = ? WHERE id = ?");
            
            foreach ($data['questions'] as $index => $questionId) {
                $stmt->execute([$index + 1, $questionId]);
            }

            $this->db->exec('COMMIT');
            $this->sendResponse(['success' => true, 'message' => 'Questions reordered']);
        } catch (Exception $e) {
            $this->db->exec('ROLLBACK');
            $this->sendResponse(['error' => 'Failed to reorder questions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get single question
     */
    private function getQuestion($questionId) {
        $question = $this->getQuestionById($questionId);
        if ($question) {
            $this->sendResponse($question);
        } else {
            $this->sendResponse(['error' => 'Question not found'], 404);
        }
    }

    /**
     * Create new group
     */
    private function createGroup() {
        $data = $this->getRequestData();
        
        $requiredFields = ['questionnaire_id', 'name'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(['error' => "Missing required field: $field"], 400);
                return;
            }
        }

        // Get next order position for groups
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_position FROM question_groups WHERE questionnaire_id = ?");
        $stmt->execute([$data['questionnaire_id']]);
        $orderPosition = $stmt->fetchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO question_groups 
            (questionnaire_id, name, description, sort_order) 
            VALUES (?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['questionnaire_id'],
            $data['name'],
            $data['description'] ?? '',
            $orderPosition
        ]);

        if ($result) {
            $groupId = $this->db->lastInsertRowID();
            
            // Move questions to the new group if provided
            if (!empty($data['question_ids'])) {
                $this->moveQuestionsToGroup($data['question_ids'], $groupId);
            }

            $group = $this->getGroupById($groupId);
            $this->sendResponse(['success' => true, 'group' => $group]);
        } else {
            $this->sendResponse(['error' => 'Failed to create group'], 500);
        }
    }

    /**
     * Update group
     */
    private function updateGroup() {
        $data = $this->getRequestData();
        
        if (empty($data['id'])) {
            $this->sendResponse(['error' => 'Missing group ID'], 400);
            return;
        }

        // Check if group is fixed (not editable)
        $stmt = $this->db->prepare("SELECT is_fixed FROM question_groups WHERE id = ?");
        $stmt->execute([$data['id']]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($group && $group['is_fixed']) {
            $this->sendResponse(['error' => 'Fixed contact group cannot be edited'], 403);
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE question_groups 
            SET name = ?, description = ?
            WHERE id = ? AND is_fixed = 0
        ");

        $result = $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['id']
        ]);

        if ($result) {
            $group = $this->getGroupById($data['id']);
            $this->sendResponse(['success' => true, 'group' => $group]);
        } else {
            $this->sendResponse(['error' => 'Failed to update group'], 500);
        }
    }

    /**
     * Delete group
     */
    private function deleteGroup($groupId) {
        if (empty($groupId)) {
            $this->sendResponse(['error' => 'Missing group ID'], 400);
            return;
        }

        // Check if group is fixed (not deletable)
        $stmt = $this->db->prepare("SELECT is_fixed FROM question_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($group && $group['is_fixed']) {
            $this->sendResponse(['error' => 'Fixed contact group cannot be deleted'], 403);
            return;
        }

        $this->db->exec('BEGIN TRANSACTION');

        try {
            // Move all questions from this group to ungrouped
            $stmt = $this->db->prepare("UPDATE questions_simple SET group_id = NULL WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // Delete the group
            $stmt = $this->db->prepare("DELETE FROM question_groups WHERE id = ? AND is_fixed = 0");
            $stmt->execute([$groupId]);

            $this->db->exec('COMMIT');
            $this->sendResponse(['success' => true, 'message' => 'Group deleted']);
        } catch (Exception $e) {
            $this->db->exec('ROLLBACK');
            $this->sendResponse(['error' => 'Failed to delete group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reorder groups
     */
    private function reorderGroups() {
        $data = $this->getRequestData();
        
        if (empty($data['groups'])) {
            $this->sendResponse(['error' => 'Missing groups array'], 400);
            return;
        }

        $this->db->exec('BEGIN TRANSACTION');

        try {
            $stmt = $this->db->prepare("UPDATE question_groups SET sort_order = ? WHERE id = ?");
            
            foreach ($data['groups'] as $index => $groupId) {
                $stmt->execute([$index + 1, $groupId]);
            }

            $this->db->exec('COMMIT');
            $this->sendResponse(['success' => true, 'message' => 'Groups reordered']);
        } catch (Exception $e) {
            $this->db->exec('ROLLBACK');
            $this->sendResponse(['error' => 'Failed to reorder groups: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get single group
     */
    private function getGroup($groupId) {
        $group = $this->getGroupById($groupId);
        if ($group) {
            $this->sendResponse($group);
        } else {
            $this->sendResponse(['error' => 'Group not found'], 404);
        }
    }

    /**
     * Helper methods
     */
    private function getRequestData() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            return array_merge($_POST, $_GET);
        }
    }

    private function getQuestionById($questionId) {
        $stmt = $this->db->prepare("
            SELECT q.*, qg.name as group_name 
            FROM questions_simple q 
            LEFT JOIN question_groups qg ON q.group_id = qg.id 
            WHERE q.id = ?
        ");
        $stmt->execute([$questionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getGroupById($groupId) {
        $stmt = $this->db->prepare("SELECT * FROM question_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getNextOrderPosition($questionnaireId, $groupId = null) {
        if ($groupId) {
            $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM questions_simple WHERE questionnaire_id = ? AND group_id = ?");
            $stmt->execute([$questionnaireId, $groupId]);
        } else {
            $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM questions_simple WHERE questionnaire_id = ? AND group_id IS NULL");
            $stmt->execute([$questionnaireId]);
        }
        return $stmt->fetchColumn();
    }

    private function reorderQuestionsInGroup($groupId) {
        if ($groupId === 'ungrouped') {
            $stmt = $this->db->prepare("
                SELECT id FROM questions_simple 
                WHERE group_id IS NULL 
                ORDER BY sort_order, id
            ");
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("
                SELECT id FROM questions_simple 
                WHERE group_id = ? 
                ORDER BY sort_order, id
            ");
            $stmt->execute([$groupId]);
        }

        $questions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $updateStmt = $this->db->prepare("UPDATE questions_simple SET sort_order = ? WHERE id = ?");
        foreach ($questions as $index => $questionId) {
            $updateStmt->execute([$index + 1, $questionId]);
        }
    }

    private function moveQuestionsToGroup($questionIds, $groupId) {
        if (empty($questionIds)) return;

        $placeholders = str_repeat('?,', count($questionIds) - 1) . '?';
        $stmt = $this->db->prepare("UPDATE questions_simple SET group_id = ? WHERE id IN ($placeholders)");
        $params = array_merge([$groupId], $questionIds);
        $stmt->execute($params);
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// Handle the request if this file is called directly
if (!defined('INCLUDED_API')) {
    $requestPath = $_SERVER['REQUEST_URI'] ?? '';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Parse query string from path
    $pathParts = parse_url($requestPath);
    $path = $pathParts['path'] ?? '';

    try {
        // Include database connection
        require_once __DIR__ . '/../config/database.php';
        
        $api = new QuestionnaireBuilderAPI($pdo);
        $api->handleRequest($path, $requestMethod);
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
    }
}
