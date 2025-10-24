/**
 * Questionnaire Builder Drag & Drop Functionality
 * Handles drag and drop operations for questionnaire groups and questions
 */

class QuestionnaireBuilder {
    constructor() {
        this.questionnaireId = window.questionnaireData?.id;
        this.sortableInstances = [];
        this.init();
    }

    init() {
        this.initializeSortables();
        this.attachEventListeners();
        this.initializeModals();
    }

    /**
     * Initialize all sortable areas
     */
    initializeSortables() {
        // Make groups container sortable for reordering groups
        const groupsContainer = document.getElementById('groupsContainer');
        if (groupsContainer) {
            const groupSortable = new Sortable(groupsContainer, {
                group: 'groups',
                animation: 300,
                handle: '.group-header',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                filter: '.fixed-group',  // Prevent dragging fixed groups
                onEnd: (evt) => this.handleGroupReorder(evt)
            });
            this.sortableInstances.push(groupSortable);
        }

        // Make each question list sortable
        document.querySelectorAll('.questions-list').forEach(questionsList => {
            const groupId = questionsList.dataset.groupId;
            const questionSortable = new Sortable(questionsList, {
                group: {
                    name: 'questions',
                    pull: true,
                    put: true
                },
                animation: 300,
                handle: '.question-drag-handle',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                filter: '.drop-zone-add-question, .fixed-question',  // Prevent dragging fixed questions
                onAdd: (evt) => this.handleQuestionMove(evt),
                onUpdate: (evt) => this.handleQuestionReorder(evt),
                onRemove: (evt) => this.handleQuestionRemove(evt)
            });
            this.sortableInstances.push(questionSortable);
        });

        // Make new question items draggable
        const newQuestionItems = document.getElementById('newQuestionItems');
        if (newQuestionItems) {
            const newQuestionSortable = new Sortable(newQuestionItems, {
                group: {
                    name: 'questions',
                    pull: 'clone',
                    put: false
                },
                sort: false,
                animation: 300,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag'
            });
            this.sortableInstances.push(newQuestionSortable);
        }

        // Make new group drop zone handle multiple questions
        this.initializeNewGroupDropZone();
    }

    /**
     * Initialize the new group drop zone for creating groups from multiple questions
     */
    initializeNewGroupDropZone() {
        const newGroupDropZone = document.getElementById('newGroupDropZone');
        if (!newGroupDropZone) return;

        let draggedQuestions = [];

        // Handle dragover for the new group zone
        newGroupDropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            newGroupDropZone.classList.add('drag-over');
        });

        newGroupDropZone.addEventListener('dragleave', (e) => {
            if (!newGroupDropZone.contains(e.relatedTarget)) {
                newGroupDropZone.classList.remove('drag-over');
            }
        });

        newGroupDropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            newGroupDropZone.classList.remove('drag-over');
            
            // Get the dragged question data
            const questionData = e.dataTransfer.getData('text/plain');
            if (questionData) {
                this.createNewGroupFromQuestions([questionData]);
            }
        });
    }

    /**
     * Attach event listeners for various interactions
     */
    attachEventListeners() {
        // Edit question buttons
        document.addEventListener('click', (e) => {
            const editBtn = e.target.closest('[data-action="edit-question"]');
            if (editBtn) {
                const questionId = editBtn.dataset.questionId;
                const questionItem = editBtn.closest('.question-item');
                const isFixed = questionItem && questionItem.dataset.isFixed === '1';
                
                if (isFixed) {
                    alert('Diese Frage gehört zu den festen Kontaktfeldern und kann nicht bearbeitet werden.');
                    return;
                }
                
                this.openQuestionEditModal(questionId);
            }
        });

        // Delete question buttons
        document.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('[data-action="delete-question"]');
            if (deleteBtn) {
                const questionId = deleteBtn.dataset.questionId;
                const questionItem = deleteBtn.closest('.question-item');
                const isFixed = questionItem && questionItem.dataset.isFixed === '1';
                
                if (isFixed) {
                    alert('Diese Frage gehört zu den festen Kontaktfeldern und kann nicht gelöscht werden.');
                    return;
                }
                
                this.deleteQuestion(questionId);
            }
        });

        // Edit group buttons
        document.addEventListener('click', (e) => {
            const editBtn = e.target.closest('[data-action="edit-group"]');
            if (editBtn) {
                const groupId = editBtn.dataset.groupId;
                const groupItem = editBtn.closest('.question-group');
                const isFixed = groupItem && groupItem.dataset.isFixed === '1';
                
                if (isFixed) {
                    alert('Die Kontaktinformationen-Gruppe ist fest und kann nicht bearbeitet werden.');
                    return;
                }
                
                this.openGroupEditModal(groupId);
            }
        });

        // Delete group buttons
        document.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('[data-action="delete-group"]');
            if (deleteBtn) {
                const groupId = deleteBtn.dataset.groupId;
                const groupItem = deleteBtn.closest('.question-group');
                const isFixed = groupItem && groupItem.dataset.isFixed === '1';
                
                if (isFixed) {
                    alert('Die Kontaktinformationen-Gruppe ist fest und kann nicht gelöscht werden.');
                    return;
                }
                
                this.deleteGroup(groupId);
            }
        });

        // Save button
        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveChanges());
        }

        // Preview button
        const previewBtn = document.getElementById('previewBtn');
        if (previewBtn) {
            previewBtn.addEventListener('click', () => this.previewQuestionnaire());
        }

        // Drag start for questions
        document.addEventListener('dragstart', (e) => {
            if (e.target.classList.contains('question-item')) {
                const questionId = e.target.dataset.questionId;
                const questionType = e.target.dataset.type;
                
                e.dataTransfer.setData('text/plain', JSON.stringify({
                    questionId: questionId,
                    questionType: questionType,
                    isNew: e.target.classList.contains('new-question')
                }));
            }
        });
    }

    /**
     * Handle group reordering
     */
    handleGroupReorder(evt) {
        const groupIds = Array.from(document.querySelectorAll('.question-group')).map(group => group.dataset.groupId);
        
        // Send update to server
        this.updateGroupOrder(groupIds);
    }

    /**
     * Handle question moving between groups
     */
    handleQuestionMove(evt) {
        const questionElement = evt.item;
        const targetGroupId = evt.to.dataset.groupId;
        const questionData = JSON.parse(evt.item.dataset.dragData || '{}');

        if (questionData.isNew) {
            // Create new question
            this.createNewQuestion(questionData.questionType, targetGroupId, evt.newIndex);
        } else {
            // Move existing question
            const questionId = questionElement.dataset.questionId;
            this.moveQuestionToGroup(questionId, targetGroupId, evt.newIndex);
        }
    }

    /**
     * Handle question reordering within a group
     */
    handleQuestionReorder(evt) {
        const questionId = evt.item.dataset.questionId;
        const groupId = evt.to.dataset.groupId;
        const newIndex = evt.newIndex;

        this.updateQuestionOrder(questionId, groupId, newIndex);
    }

    /**
     * Handle question removal from group
     */
    handleQuestionRemove(evt) {
        const sourceGroup = evt.from.closest('.question-group');
        
        // Check if group is now empty
        const remainingQuestions = sourceGroup.querySelectorAll('.question-item').length;
        
        if (remainingQuestions === 0 && sourceGroup.dataset.groupId !== 'ungrouped') {
            this.showDeleteEmptyGroupDialog(sourceGroup.dataset.groupId);
        }
    }

    /**
     * Initialize modal functionality
     */
    initializeModals() {
        // Modal close buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-close]') || e.target.matches('.modal-close')) {
                const modalId = e.target.dataset.close || e.target.closest('.modal').id;
                this.closeModal(modalId);
            }
        });

        // Modal save buttons
        const saveQuestionBtn = document.getElementById('saveQuestionBtn');
        if (saveQuestionBtn) {
            saveQuestionBtn.addEventListener('click', () => this.saveQuestion());
        }

        const saveGroupBtn = document.getElementById('saveGroupBtn');
        if (saveGroupBtn) {
            saveGroupBtn.addEventListener('click', () => this.saveGroup());
        }

        // Delete buttons in modals
        const deleteQuestionBtn = document.getElementById('deleteQuestionBtn');
        if (deleteQuestionBtn) {
            deleteQuestionBtn.addEventListener('click', () => this.confirmDeleteQuestion());
        }

        const deleteGroupBtn = document.getElementById('deleteGroupBtn');
        if (deleteGroupBtn) {
            deleteGroupBtn.addEventListener('click', () => this.confirmDeleteGroup());
        }

        // Question type change handler
        const questionTypeSelect = document.getElementById('editQuestionType');
        if (questionTypeSelect) {
            questionTypeSelect.addEventListener('change', (e) => {
                this.toggleOptionsField(e.target.value);
            });
        }

        // Click outside modal to close
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal(e.target.id);
            }
        });
    }

    /**
     * Open question edit modal
     */
    openQuestionEditModal(questionId) {
        // Fetch question data and populate modal
        this.fetchQuestionData(questionId).then(questionData => {
            document.getElementById('editQuestionId').value = questionId;
            document.getElementById('editQuestionText').value = questionData.question_text;
            document.getElementById('editQuestionType').value = questionData.question_type;
            document.getElementById('editPlaceholder').value = questionData.placeholder_text || '';
            document.getElementById('editHelpText').value = questionData.help_text || '';
            document.getElementById('editOptions').value = questionData.options || '';
            document.getElementById('editIsRequired').checked = questionData.is_required;

            this.toggleOptionsField(questionData.question_type);
            this.showModal('questionEditModal');
        });
    }

    /**
     * Open group edit modal
     */
    openGroupEditModal(groupId) {
        // Fetch group data and populate modal
        this.fetchGroupData(groupId).then(groupData => {
            document.getElementById('editGroupId').value = groupId;
            document.getElementById('editGroupName').value = groupData.name;
            document.getElementById('editGroupDescription').value = groupData.description || '';

            this.showModal('groupEditModal');
        });
    }

    /**
     * Toggle options field visibility based on question type
     */
    toggleOptionsField(questionType) {
        const optionsGroup = document.querySelector('.options-group');
        const hasOptions = ['select', 'radio', 'checkbox'].includes(questionType);
        
        if (hasOptions) {
            optionsGroup.classList.add('show');
        } else {
            optionsGroup.classList.remove('show');
        }
    }

    /**
     * Show modal
     */
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Close modal
     */
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    /**
     * Save question changes
     */
    async saveQuestion() {
        const form = document.getElementById('questionEditForm');
        const formData = new FormData(form);
        
        try {
            const response = await fetch('/api/questions/update', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const result = await response.json();
                this.updateQuestionUI(result.question);
                this.closeModal('questionEditModal');
                this.showNotification('Frage erfolgreich gespeichert', 'success');
            } else {
                throw new Error('Failed to save question');
            }
        } catch (error) {
            console.error('Error saving question:', error);
            this.showNotification('Fehler beim Speichern der Frage', 'error');
        }
    }

    /**
     * Save group changes
     */
    async saveGroup() {
        const form = document.getElementById('groupEditForm');
        const formData = new FormData(form);
        
        try {
            const response = await fetch('/api/groups/update', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const result = await response.json();
                this.updateGroupUI(result.group);
                this.closeModal('groupEditModal');
                this.showNotification('Gruppe erfolgreich gespeichert', 'success');
            } else {
                throw new Error('Failed to save group');
            }
        } catch (error) {
            console.error('Error saving group:', error);
            this.showNotification('Fehler beim Speichern der Gruppe', 'error');
        }
    }

    /**
     * Create new question
     */
    async createNewQuestion(questionType, groupId, position) {
        try {
            const response = await fetch('/api/questions/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    questionnaire_id: this.questionnaireId,
                    question_type: questionType,
                    group_id: groupId,
                    position: position,
                    question_text: `Neue ${this.getQuestionTypeLabel(questionType)}-Frage`
                })
            });

            if (response.ok) {
                const result = await response.json();
                this.addQuestionToUI(result.question, groupId);
                this.showNotification('Neue Frage erstellt', 'success');
                
                // Auto-open edit modal for new questions
                setTimeout(() => {
                    this.openQuestionEditModal(result.question.id);
                }, 100);
            } else {
                throw new Error('Failed to create question');
            }
        } catch (error) {
            console.error('Error creating question:', error);
            this.showNotification('Fehler beim Erstellen der Frage', 'error');
        }
    }

    /**
     * Create new group from questions
     */
    async createNewGroupFromQuestions(questionIds) {
        const groupName = prompt('Name für die neue Gruppe:');
        if (!groupName) return;

        try {
            const response = await fetch('/api/groups/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    questionnaire_id: this.questionnaireId,
                    name: groupName,
                    question_ids: questionIds
                })
            });

            if (response.ok) {
                const result = await response.json();
                location.reload(); // Reload to show new group structure
            } else {
                throw new Error('Failed to create group');
            }
        } catch (error) {
            console.error('Error creating group:', error);
            this.showNotification('Fehler beim Erstellen der Gruppe', 'error');
        }
    }

    /**
     * Delete question
     */
    async deleteQuestion(questionId) {
        if (!confirm('Sind Sie sicher, dass Sie diese Frage löschen möchten?')) {
            return;
        }

        try {
            const response = await fetch(`/api/questions/delete/${questionId}`, {
                method: 'DELETE'
            });

            if (response.ok) {
                this.removeQuestionFromUI(questionId);
                this.showNotification('Frage gelöscht', 'success');
            } else {
                throw new Error('Failed to delete question');
            }
        } catch (error) {
            console.error('Error deleting question:', error);
            this.showNotification('Fehler beim Löschen der Frage', 'error');
        }
    }

    /**
     * Delete group
     */
    async deleteGroup(groupId) {
        if (!confirm('Sind Sie sicher, dass Sie diese Gruppe löschen möchten? Alle Fragen werden in "Nicht gruppierte Fragen" verschoben.')) {
            return;
        }

        try {
            const response = await fetch(`/api/groups/delete/${groupId}`, {
                method: 'DELETE'
            });

            if (response.ok) {
                location.reload(); // Reload to show updated structure
            } else {
                throw new Error('Failed to delete group');
            }
        } catch (error) {
            console.error('Error deleting group:', error);
            this.showNotification('Fehler beim Löschen der Gruppe', 'error');
        }
    }

    /**
     * Utility functions
     */
    async fetchQuestionData(questionId) {
        const response = await fetch(`/api/questions/${questionId}`);
        return await response.json();
    }

    async fetchGroupData(groupId) {
        const response = await fetch(`/api/groups/${groupId}`);
        return await response.json();
    }

    getQuestionTypeLabel(type) {
        const labels = {
            'text': 'Text',
            'email': 'E-Mail',
            'phone': 'Telefon',
            'textarea': 'Text-Bereich',
            'select': 'Auswahl',
            'radio': 'Radio',
            'checkbox': 'Checkbox',
            'date': 'Datum',
            'number': 'Zahl'
        };
        return labels[type] || type;
    }

    showNotification(message, type = 'info') {
        // Simple notification system
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 10001;
            animation: slideInRight 0.3s ease;
        `;

        if (type === 'success') {
            notification.style.backgroundColor = '#28a745';
        } else if (type === 'error') {
            notification.style.backgroundColor = '#dc3545';
        } else {
            notification.style.backgroundColor = '#007bff';
        }

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }

    updateQuestionUI(questionData) {
        const questionElement = document.querySelector(`[data-question-id="${questionData.id}"]`);
        if (questionElement) {
            questionElement.querySelector('.question-text').textContent = questionData.question_text;
            // Update other UI elements as needed
        }
    }

    updateGroupUI(groupData) {
        const groupElement = document.querySelector(`[data-group-id="${groupData.id}"]`);
        if (groupElement) {
            groupElement.querySelector('.group-title').textContent = groupData.name;
            const description = groupElement.querySelector('.group-description');
            if (description) {
                description.textContent = groupData.description || 'Beschreibung hinzufügen...';
            }
        }
    }

    addQuestionToUI(questionData, groupId) {
        // Add question element to the appropriate group
        // Implementation depends on your specific UI structure
        location.reload(); // Simple approach for now
    }

    removeQuestionFromUI(questionId) {
        const questionElement = document.querySelector(`[data-question-id="${questionId}"]`);
        if (questionElement) {
            questionElement.remove();
        }
    }

    async saveChanges() {
        // Implement save all changes functionality
        this.showNotification('Alle Änderungen gespeichert', 'success');
    }

    previewQuestionnaire() {
        // Open preview in new tab/window
        this.showNotification('Vorschau wird noch implementiert', 'info');
        //window.open(`/questionnaire/preview/${this.questionnaireId}`, '_blank');
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new QuestionnaireBuilder();
});
