/**
 * Admin Drag & Drop Extensions
 * Erweitert die Admin-Oberfläche um Drag & Drop Funktionalität für Fragebögen
 */

class AdminDragDrop {
    constructor() {
        this.questionnaireId = null;
        this.sortableInstances = [];
        this.groups = [];
        this.pendingGroupCreation = null;
        this.currentDraggedItem = null;
        this.csrfToken = null; // CSRF-Token speichern
        this.init();
    }

    init() {
        this.loadCSRFToken();
        this.attachEventListeners();
        
        // Warten bis DOM und Admin.js geladen sind
        this.initializeWhenReady();
    }

    /**
     * Load CSRF token from sessionStorage or admin instance
     */
    loadCSRFToken() {
        // Try to get from sessionStorage
        this.csrfToken = sessionStorage.getItem('csrf_token');
        
        // Or try to get from admin instance if available
        if (!this.csrfToken && window.admin && window.admin.csrfToken) {
            this.csrfToken = window.admin.csrfToken;
        }
        
        // Try META tag
        if (!this.csrfToken) {
            const metaToken = document.querySelector('meta[name="csrf-token"]');
            if (metaToken) {
                this.csrfToken = metaToken.getAttribute('content');
            }
        }
        
        console.log('🔑 Loaded CSRF token:', this.csrfToken ? 'Available' : 'Not found');
    }

    /**
     * Get headers with CSRF token for API requests
     */
    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'X-CSRF-Token': this.csrfToken || ''
        };
    }

    initializeWhenReady() {
        // Prüfe ob alle erforderlichen Funktionen verfügbar sind
        if (typeof window.loadQuestionnaireQuestions !== 'undefined' && 
            typeof window.displayQuestionnaireQuestions !== 'undefined') {
            
            this.enhanceAdminInterface();
        } else {
            setTimeout(() => this.initializeWhenReady(), 100);
        }
    }

    enhanceAdminInterface() {
        
        // Override der ursprünglichen displayQuestionnaireQuestions Funktion
        const originalDisplayQuestions = window.displayQuestionnaireQuestions;
        
        window.displayQuestionnaireQuestions = (questions) => {

            // Prevent multiple rapid calls
            if (this._displayInProgress) {
                return;
            }
            this._displayInProgress = true;
            
            try {
                // Always show with groups if groups exist, otherwise normal display
                if (this.groups && this.groups.length > 0) {
                    this.displayQuestionsWithGroups(questions);
                } else {
                    originalDisplayQuestions(questions);
                    // Kurz warten bis DOM aktualisiert ist, dann enhanced sortable aktivieren
                    setTimeout(() => this.makeEnhancedSortable(questions), 100);
                }
            } finally {
                setTimeout(() => {
                    this._displayInProgress = false;
                }, 200);
            }
        };

        // Override der editQuestionnaire Funktion für bessere Integration
        const originalEditQuestionnaire = window.editQuestionnaire;
        
        window.editQuestionnaire = async (id) => {
            console.log('📝 editQuestionnaire override called with ID:', id);
            this.questionnaireId = id;
            console.log('✅ Set questionnaireId to:', this.questionnaireId);
            
            // Erst Gruppen laden, dann editQuestionnaire aufrufen
            console.log('🔄 Loading questionnaire groups...');
            await this.loadQuestionnaireGroups(id);
            console.log('✅ Groups loaded, calling original editQuestionnaire');
            originalEditQuestionnaire(id);
        };
    }

    attachEventListeners() {
        document.addEventListener('click', (e) => {
            // Toggle Question Palette
            if (e.target.closest('button[onclick*="toggleQuestionPalette"]')) {
                this.toggleQuestionPalette();
                return;
            }

            // Add Question to Questionnaire (enhanced)
            if (e.target.closest('button[onclick*="addQuestionToQuestionnaire"]')) {
                this.showQuestionPalette();
                return;
            }

            // Create new group
            if (e.target.closest('[data-action="create-group"]')) {
                this.createNewGroup();
                return;
            }

            // Edit group
            if (e.target.closest('[data-action="edit-group"]')) {
                const groupId = e.target.closest('[data-action="edit-group"]').dataset.groupId;
                this.editGroup(groupId);
                return;
            }

            // Delete group
            if (e.target.closest('[data-action="delete-group"]')) {
                const groupId = e.target.closest('[data-action="delete-group"]').dataset.groupId;
                this.deleteGroup(groupId);
                return;
            }
        });

        // Add backup event listener directly to groups container
        setTimeout(() => {
            const groupsContainer = document.getElementById('groupsContainer');
            if (groupsContainer) {
                groupsContainer.addEventListener('click', (e) => {
                    if (e.target.closest('[data-action="edit-group"]')) {
                        const groupId = e.target.closest('[data-action="edit-group"]').dataset.groupId;
                        this.editGroup(groupId);
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });
            }
        }, 3000);

        // Drag start für Palette-Items
        document.addEventListener('dragstart', (e) => {
            if (e.target.classList.contains('palette-item')) {
                const questionType = e.target.dataset.type;
                e.dataTransfer.setData('text/plain', JSON.stringify({
                    type: 'new-question',
                    questionType: questionType,
                    isNew: true
                }));
                e.target.style.opacity = '0.5';
            }

            if (e.target.classList.contains('question-item')) {
                const questionId = e.target.dataset.questionId;
                this.currentDraggedItem = e.target;
                e.dataTransfer.setData('text/plain', JSON.stringify({
                    type: 'existing-question',
                    questionId: questionId,
                    isNew: false
                }));
                e.target.style.opacity = '0.5';
            }
        });

        // Drag end
        document.addEventListener('dragend', (e) => {
            if (e.target.classList.contains('palette-item') || e.target.classList.contains('question-item')) {
                e.target.style.opacity = '';
            }
            this.currentDraggedItem = null;
        });
    }

    toggleQuestionPalette() {
        const palette = document.getElementById('questionPalette');
        palette.style.display = palette.style.display === 'none' ? 'block' : 'none';
    }

    showQuestionPalette() {
        const palette = document.getElementById('questionPalette');
        palette.style.display = 'block';
    }

    async loadQuestionnaireGroups(questionnaireId) {
        try {
            const response = await fetch(`api/admin.php?action=questionnaire-groups&id=${questionnaireId}`);
            if (response.ok) {
                const data = await response.json();
                
                if (data.success) {
                    this.groups = data.groups || [];
                } else {
                    console.error('❌ Groups API error:', data.error);
                    this.groups = [];
                }
            } else {
                console.error('❌ Groups API HTTP error:', response.status);
                this.groups = [];
            }
        } catch (error) {
            console.error('❌ Error loading questionnaire groups:', error);
            this.groups = [];
        }
    }

    displayQuestionsWithGroups(questions) {
        const container = document.getElementById('questionsList');
        const groupsContainer = document.getElementById('groupsContainer');
        
        if (!container || !groupsContainer) {
            console.error('❌ Required containers not found:', {
                questionsList: !!container,
                groupsContainer: !!groupsContainer
            });
            return;
        }

        // Leere Container
        groupsContainer.innerHTML = '';
        container.innerHTML = '';

        // Gruppierte Fragen anzeigen
        if (this.groups && this.groups.length > 0) {
            this.groups.forEach(group => {
                const groupQuestions = questions.filter(q => q.group_id == group.id);
                this.renderGroup(group, groupQuestions);
            });
        }

        // Nicht gruppierte Fragen
        const ungroupedQuestions = questions.filter(q => !q.group_id || q.group_id === null);
        this.renderUngroupedQuestions(ungroupedQuestions);

        // Drag & Drop aktivieren
        setTimeout(() => this.initializeSortables(), 100);
    }

    renderGroup(group, questions) {
        const groupsContainer = document.getElementById('groupsContainer');
        
        const groupHtml = `
            <div class="question-group" data-group-id="${group.id}">
                <div class="group-header" draggable="true">
                    <div class="group-drag-handle" title="Gruppe ziehen">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    <div class="group-info">
                        <h5 class="group-title">${group.name}</h5>
                        ${group.description ? `<p class="group-description">${group.description}</p>` : ''}
                    </div>
                    <div class="group-actions">
                        <button class="btn btn-sm btn-outline" data-action="edit-group" data-group-id="${group.id}" title="Bearbeiten">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" data-action="delete-group" data-group-id="${group.id}" title="Löschen">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="questions-list" data-group-id="${group.id}">
                    ${questions.length > 0 ? this.renderQuestions(questions) : this.renderEmptyGroupMessage()}
                </div>
            </div>
        `;

        groupsContainer.insertAdjacentHTML('beforeend', groupHtml);
    }

    renderUngroupedQuestions(questions) {
        const container = document.getElementById('questionsList');
        
        if (questions.length === 0) {
            container.innerHTML = `
                <div class="empty-message">
                    <i class="fas fa-question-circle"></i>
                    <p>Noch keine Fragen hinzugefügt</p>
                    <button class="btn btn-primary" onclick="addQuestionToQuestionnaire()">
                        Erste Frage hinzufügen
                    </button>
                </div>
            `;
        } else {
            container.innerHTML = this.renderQuestions(questions);
        }
    }

    renderQuestions(questions) {
        return questions.map((question, index) => this.renderQuestionItem(question, index)).join('');
    }

    renderQuestionItem(question, index = 0) {
        return `
            <div class="question-item" data-question-id="${question.id}" data-question-type="${question.question_type}" draggable="true">
                <div class="question-drag-handle">
                    <i class="fas fa-grip-vertical"></i>
                </div>
                <div class="question-content">
                    <div class="question-header">
                        <div class="question-info">
                            <span class="question-number">${index + 1}.</span>
                            <span class="question-text">${question.question_text}</span>
                            <span class="question-type badge badge-secondary">${this.getQuestionTypeText(question.question_type)}</span>
                            ${question.is_required ? '<span class="badge badge-warning">Erforderlich</span>' : ''}
                        </div>
                        <div class="question-actions">
                            <button class="btn btn-sm btn-outline" onclick="editQuestionInQuestionnaire(${question.id})" title="Bearbeiten">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="removeQuestionFromQuestionnaire(${question.id})" title="Entfernen">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    ${question.help_text ? `<div class="question-help"><small class="text-muted">${question.help_text}</small></div>` : ''}
                </div>
            </div>
        `;
    }

    renderEmptyGroupMessage() {
        return `
            <div class="empty-group-message">
                <i class="fas fa-question-circle"></i>
                <p>Ziehen Sie Fragen hierher</p>
            </div>
        `;
    }

    initializeSortables() {
        // Cleanup existing sortables
        this.sortableInstances.forEach(sortable => sortable.destroy());
        this.sortableInstances = [];

        // Groups sortable - entire groups can be reordered, but NOT dropped into questions
        const groupsContainer = document.getElementById('groupsContainer');
        if (groupsContainer) {
            const groupSortable = new Sortable(groupsContainer, {
                group: {
                    name: 'groups',
                    pull: false, // Groups cannot be pulled into other containers
                    put: false   // No other elements can be put into groups container
                },
                animation: 300,
                handle: '.group-drag-handle', // Use specific drag handle instead of entire header
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: (evt) => this.handleGroupReorder(evt)
            });
            this.sortableInstances.push(groupSortable);
        }

        // Questions lists sortable - questions within groups
        document.querySelectorAll('.questions-list').forEach(questionsList => {
            const groupId = questionsList.dataset.groupId;
            const isUngroupedList = groupId === 'ungrouped';
            
            const questionSortable = new Sortable(questionsList, {
                group: {
                    name: 'questions',
                    pull: true,    // Questions can be pulled from this container
                    put: function(to, from) {
                        // Only allow individual questions, not entire groups
                        return from.el.classList.contains('questions-list');
                    }
                },
                animation: 300,
                handle: '.question-drag-handle',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                filter: '.empty-message, .empty-group-message',
                onAdd: (evt) => this.handleQuestionMove(evt),
                onUpdate: (evt) => this.handleQuestionReorder(evt),
                onRemove: (evt) => this.handleQuestionRemove(evt)
            });
            this.sortableInstances.push(questionSortable);
        });

        // Ungrouped questions sortable - with special logic for group creation
        const ungroupedQuestions = document.getElementById('questionsList');
        if (ungroupedQuestions) {
            const ungroupedSortable = new Sortable(ungroupedQuestions, {
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
                filter: '.empty-message',
                onAdd: (evt) => this.handleQuestionMove(evt),
                onUpdate: (evt) => this.handleUngroupedQuestionReorder(evt),
                onRemove: (evt) => this.handleQuestionRemove(evt),
                // Special handling for dropping question on question
                onMove: (evt, originalEvent) => this.handleQuestionOnQuestion(evt, originalEvent)
            });
            this.sortableInstances.push(ungroupedSortable);
        }

        // Question palette sortable
        const questionPalette = document.querySelector('.palette-items');
        if (questionPalette) {
            const paletteSortable = new Sortable(questionPalette, {
                group: {
                    name: 'questions',
                    pull: 'clone',
                    put: false
                },
                sort: false,
                animation: 300,
                ghostClass: 'sortable-ghost'
            });
            this.sortableInstances.push(paletteSortable);
        }

        // Initialize drop zones and special handlers
        this.initializeDropZones();
    }

    // New method to replace old initializeNewGroupDropZone
    initializeDropZones() {
        // New group drop zone
        this.initializeNewGroupDropZone();
        
        // Initialize question-on-question drop detection
        this.initializeQuestionDropDetection();
    }

    initializeNewGroupDropZone() {
        const dropZone = document.getElementById('newGroupDropZone');
        if (!dropZone) return;

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', (e) => {
            if (!dropZone.contains(e.relatedTarget)) {
                dropZone.classList.remove('drag-over');
            }
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            
            const data = JSON.parse(e.dataTransfer.getData('text/plain'));
            if (data.type === 'existing-question') {
                this.createGroupWithQuestion(data.questionId);
            }
        });
    }

    initializeQuestionDropDetection() {
        // Add visual feedback when dragging questions over other questions
        document.addEventListener('dragover', (e) => {
            if (e.target.closest('.question-item')) {
                const targetQuestion = e.target.closest('.question-item');
                const container = targetQuestion.closest('#questionsList');
                
                // Only show drop feedback in ungrouped questions
                if (container) {
                    e.preventDefault();
                    targetQuestion.classList.add('drop-target');
                }
            }
        });

        document.addEventListener('dragleave', (e) => {
            if (e.target.closest('.question-item')) {
                e.target.closest('.question-item').classList.remove('drop-target');
            }
        });

        document.addEventListener('drop', (e) => {
            document.querySelectorAll('.question-item').forEach(item => {
                item.classList.remove('drop-target');
            });
        });
    }

    handleEmptyGroup(groupId) {
        
        // Show empty group message
        const groupContainer = document.querySelector(`[data-group-id="${groupId}"] .questions-list`);
        if (groupContainer) {
            groupContainer.innerHTML = this.renderEmptyGroupMessage();
        }

        // Ask user if they want to delete the empty group
        setTimeout(() => {
            if (confirm('Diese Gruppe ist jetzt leer. Möchten Sie sie löschen?')) {
                this.deleteGroup(groupId);
            }
        }, 500); // Small delay to let the UI update
    }

    makeEnhancedSortable(questions) {
        const questionsList = document.getElementById('questionsList');
        if (!questionsList) {
            return;
        }

        // Cleanup existing
        this.sortableInstances.forEach(sortable => sortable.destroy());
        this.sortableInstances = [];

        // Drag handles zu bestehenden Fragen hinzufügen
        const questionItems = questionsList.querySelectorAll('.question-item');
        
        questionItems.forEach((question, index) => {
            if (!question.querySelector('.question-drag-handle')) {
                const dragHandle = document.createElement('div');
                dragHandle.className = 'question-drag-handle';
                dragHandle.innerHTML = '<i class="fas fa-grip-vertical"></i>';
                dragHandle.style.cssText = `
                    position: absolute;
                    left: 10px;
                    top: 50%;
                    transform: translateY(-50%);
                    cursor: grab;
                    color: #6c757d;
                    font-size: 14px;
                    padding: 5px;
                `;
                
                // Styling für question item
                question.style.position = 'relative';
                question.style.paddingLeft = '40px';
                question.insertBefore(dragHandle, question.firstChild);
            }
            question.draggable = true;
            question.dataset.questionId = question.dataset.questionId || questions[index]?.id || index;
        });

        const sortable = new Sortable(questionsList, {
            group: {
                name: 'questions', // Same group as other question lists
                pull: true,        // Questions can be pulled from ungrouped
                put: function(to, from) {
                    // Only allow individual questions, not entire groups or group containers
                    return from.el.classList.contains('questions-list');
                }
            },
            animation: 300,
            handle: '.question-drag-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            filter: '.empty-message',
            onStart: (evt) => {
                console.log('🎬 Sortable onStart - Ungrouped Questions');
                console.log('📦 Item:', evt.item);
                console.log('🆔 Question ID:', evt.item.dataset.questionId);
                
                this.currentDraggedItem = evt.item;
                this.addDropTargetHighlights();
            },
            onEnd: (evt) => {
                console.log('🎬 Sortable onEnd - Ungrouped Questions');
                console.log('📦 Event:', evt);
                console.log('📍 From:', evt.from?.id);
                console.log('📍 To:', evt.to?.id);
                console.log('🔢 Old Index:', evt.oldIndex);
                console.log('🔢 New Index:', evt.newIndex);
                
                this.currentDraggedItem = null;
                
                // Store the target state before cleanup for group creation check
                const groupCreationTarget = document.querySelector('.group-creation-target');
                const positionChangeTarget = document.querySelector('.position-change-target');
                
                console.log('🎯 Group creation target found:', !!groupCreationTarget);
                console.log('🎯 Position change target found:', !!positionChangeTarget);
                
                // Store target IDs before cleanup
                // Clean up visual feedback
                this.removeDropTargetHighlights();
                this.removeGroupPreview(); // Zusätzlich: Group Preview aufräumen
                
                // Clean up all zone-related styles
                document.querySelectorAll('.group-creation-target, .position-change-target, .drop-target-active, .group-preview-source').forEach(item => {
                    item.classList.remove('group-creation-target', 'position-change-target', 'drop-target-active', 'group-preview-source');
                    item.style.cursor = '';
                    item.style.border = '';
                    item.style.backgroundColor = '';
                    item.style.opacity = '';
                });
                
                // Remove zone indicators
                this.removeZoneIndicators();
                
                this.currentDraggedItem = null;
                
                // Check if dropped on another question for group creation
                console.log('🔍 Checking for group creation...');
                console.log('📋 Pending group creation:', this.pendingGroupCreation);
                console.log('📋 Is group creation?', this.pendingGroupCreation?.isGroupCreation);
                
                // Prioritize group creation if it was detected during drag
                if (this.pendingGroupCreation && this.pendingGroupCreation.isGroupCreation === true) {
                    console.log('🔗 Processing group creation from pending state');
                    console.log('🔗 Creating group with questions:', [
                        this.pendingGroupCreation.draggedQuestionId,
                        this.pendingGroupCreation.targetQuestionId
                    ]);
                    
                    this.createGroupFromQuestions([
                        this.pendingGroupCreation.draggedQuestionId,
                        this.pendingGroupCreation.targetQuestionId
                    ]);
                    
                    // Clear pending state
                    this.pendingGroupCreation = null;
                    console.log('✅ Group creation completed, pending state cleared');
                } else if (evt.to && evt.to.id === 'questionsList') {
                    console.log('📍 Dropped in ungrouped questions - calling handleUngroupedQuestionReorder');
                    this.handleUngroupedQuestionReorder(evt);
                } else {
                    console.log('📍 Dropped elsewhere - calling checkForGroupCreation');
                    this.checkForGroupCreation(evt);
                }
            },
            onChange: (evt) => {
                console.log('🔄 Sortable onChange - Ungrouped Questions');
                console.log('📦 Event:', evt);
                
                // Called when dragging, check if over another question
                this.handleQuestionOverQuestion(evt);
            },
            onMove: (evt) => {
                console.log('🚚 Sortable onMove - Ungrouped Questions');
                console.log('📦 Related:', evt.related);
                console.log('📦 Dragged:', evt.dragged);
                console.log('📦 Original Event:', evt.originalEvent);
                
                // Update cursor and visual feedback during drag based on drop zone
                const related = evt.related;
                const dragged = evt.dragged;
                
                // Check if we're in ungrouped questions area
                const inUngroupedArea = evt.to && evt.to.id === 'questionsList';
                console.log('📍 In ungrouped area:', inUngroupedArea);
                
                if (related && related.classList.contains('question-item') && inUngroupedArea && dragged) {
                    console.log('✅ Valid move conditions met');
                    console.log('🔍 Related element details:', {
                        tagName: related.tagName,
                        className: related.className,
                        id: related.id,
                        questionId: related.dataset.questionId
                    });
                    
                    const rect = related.getBoundingClientRect();
                    const mouseX = evt.originalEvent?.clientX || evt.clientX || 0;
                    const mouseY = evt.originalEvent?.clientY || evt.clientY || 0;
                    
                    console.log('🖱️ Mouse position:', { mouseX, mouseY });
                    console.log('📏 Target rect:', rect);
                    
                    // Zone calculation: Outer quarters (0-25% and 75-100%) for group creation
                    // Middle half (25-75%) for reordering
                    const leftQuarter = rect.left + rect.width * 0.25;
                    const rightQuarter = rect.left + rect.width * 0.75;
                    
                    const inLeftZone = mouseX < leftQuarter;
                    const inRightZone = mouseX > rightQuarter;
                    const inMiddleZone = mouseX >= leftQuarter && mouseX <= rightQuarter;
                    
                    console.log('🎯 Zones:', {
                        inLeftZone,
                        inRightZone, 
                        inMiddleZone,
                        mouseX,
                        leftQuarter,
                        rightQuarter,
                        rectLeft: rect.left,
                        rectWidth: rect.width
                    });
                    
                    // Clean up previous states first
                    console.log('🧹 Cleaning up previous states...');
                    related.classList.remove('group-creation-target', 'position-change-target', 'drop-target-active');
                    related.style.border = '';
                    related.style.backgroundColor = '';
                    
                    if (inLeftZone || inRightZone) {
                        // Outer zones: Group creation
                        console.log('🔗 Applying group creation styles...');
                        console.log('🎨 Setting border to: 2px dashed #007cba');
                        console.log('🎨 Setting background to: rgba(0, 124, 186, 0.1)');
                        
                        related.classList.add('group-creation-target');
                        related.style.cursor = 'copy';
                        related.style.border = '2px dashed #007cba !important';
                        related.style.backgroundColor = 'rgba(0, 124, 186, 0.1) !important';
                        related.style.transition = 'all 0.2s ease';
                        
                        // Force style application
                        related.offsetHeight; // Trigger reflow
                        
                        console.log('🎨 Applied styles - checking result:', {
                            border: related.style.border,
                            backgroundColor: related.style.backgroundColor,
                            computedBorder: getComputedStyle(related).border,
                            computedBackground: getComputedStyle(related).backgroundColor
                        });
                        
                        // Add zone indicator
                        this.showZoneIndicator(related, 'Gruppe bilden', '#007cba');
                        
                        // Also apply visual feedback to dragged item
                        if (dragged) {
                            dragged.classList.add('group-preview-source');
                            dragged.style.opacity = '0.8';
                        }

                        // Store pending group creation
                        this.pendingGroupCreation = {
                            draggedQuestionId: dragged.dataset.questionId,
                            targetQuestionId: related.dataset.questionId,
                            position: evt.newIndex,
                            isGroupCreation: true,
                            zone: inLeftZone ? 'left' : 'right'
                        };
                    } else if (inMiddleZone) {
                        // Middle zone: Position change/reordering
                        console.log('📋 Applying reorder styles...');
                        console.log('🎨 Setting border to: 2px solid #28a745');
                        console.log('🎨 Setting background to: rgba(40, 167, 69, 0.1)');
                        
                        related.classList.add('position-change-target');
                        related.style.cursor = 'move';
                        related.style.border = '2px solid #28a745 !important';
                        related.style.backgroundColor = 'rgba(40, 167, 69, 0.1) !important';
                        related.style.transition = 'all 0.2s ease';
                        
                        // Force style application
                        related.offsetHeight; // Trigger reflow
                        
                        console.log('🎨 Applied styles - checking result:', {
                            border: related.style.border,
                            backgroundColor: related.style.backgroundColor,
                            computedBorder: getComputedStyle(related).border,
                            computedBackground: getComputedStyle(related).backgroundColor
                        });
                        
                        // Add zone indicator  
                        this.showZoneIndicator(related, 'Reihenfolge ändern', '#28a745');

                        // Clear any pending group creation for reordering
                        this.pendingGroupCreation = {
                            draggedQuestionId: dragged.dataset.questionId,
                            targetQuestionId: related.dataset.questionId,
                            position: evt.newIndex,
                            isGroupCreation: false,
                            zone: 'middle'
                        };
                    } else {
                        console.log('❓ No specific zone detected');
                    }
                } else {
                    // Clean up any existing highlights
                    document.querySelectorAll('.group-creation-target, .position-change-target, .drop-target-active').forEach(item => {
                        item.classList.remove('group-creation-target', 'position-change-target', 'drop-target-active');
                        item.style.cursor = '';
                        item.style.border = '';
                        item.style.backgroundColor = '';
                    });
                    
                    // Clean up dragged item styles
                    document.querySelectorAll('.group-preview-source').forEach(item => {
                        item.classList.remove('group-preview-source');
                        item.style.opacity = '';
                    });
                    
                    // Remove zone indicators
                    this.removeZoneIndicators();
                }
                return true; // Allow move
            }
        });

        this.sortableInstances.push(sortable);
    }

    // Event Handlers
    handleGroupReorder(evt) {
        const groupIds = Array.from(document.querySelectorAll('.question-group')).map(group => group.dataset.groupId);
        this.saveGroupOrder(groupIds);
    }

    handleQuestionOnQuestion(evt, originalEvent) {
        console.log('🎯 handleQuestionOnQuestion called');
        console.log('📍 Event details:', evt);
        
        // Check if we're dropping a question directly on another question
        const related = evt.related;
        const dragged = evt.dragged;
        
        console.log('🔍 Related element:', related);
        console.log('🔍 Dragged element:', dragged);
        
        if (related && related.classList.contains('question-item') && 
            dragged && dragged.classList.contains('question-item')) {
            
            console.log('✅ Both elements are question items');
            
            // Only allow this in ungrouped questions area
            const container = evt.to;
            console.log('📦 Container:', container);
            console.log('📦 Container ID:', container?.id);
            
            if (container && container.id === 'questionsList') {
                console.log('✅ Container is questionsList - preparing group creation');
                
                const draggedQuestionId = dragged.dataset.questionId;
                const targetQuestionId = related.dataset.questionId;
                
                console.log('🔢 Dragged question ID:', draggedQuestionId);
                console.log('🔢 Target question ID:', targetQuestionId);
                
                // **VISUAL FEEDBACK DIREKT HIER ANWENDEN**
                console.log('🎨 Applying visual feedback to related element...');
                
                // Zone detection based on mouse position (if available)
                const mouseX = originalEvent?.clientX || evt.originalEvent?.clientX || 0;
                const rect = related.getBoundingClientRect();
                
                console.log('🖱️ Mouse X:', mouseX);
                console.log('📏 Related rect:', rect);
                
                // Zone calculation
                const leftQuarter = rect.left + rect.width * 0.25;
                const rightQuarter = rect.left + rect.width * 0.75;
                
                const inLeftZone = mouseX > 0 && mouseX < leftQuarter;
                const inRightZone = mouseX > rightQuarter;
                const inMiddleZone = mouseX >= leftQuarter && mouseX <= rightQuarter;
                
                console.log('🎯 Zone detection:', {
                    mouseX,
                    leftQuarter,
                    rightQuarter,
                    inLeftZone,
                    inRightZone,
                    inMiddleZone
                });
                
                // Clear previous visual effects
                this.removeZoneIndicators();
                related.style.outline = '';
                related.style.backgroundColor = '';
                
                // Apply visual feedback based on zone or default to group creation
                if (inLeftZone || inRightZone || mouseX === 0) {
                    // Group creation zone (default if no mouse position)
                    console.log('🔗 Applying group creation visual feedback');
                    related.style.outline = '4px dashed #007cba !important';
                    related.style.outlineOffset = '2px !important';
                    related.style.backgroundColor = 'rgba(0, 124, 186, 0.2) !important';
                    related.style.position = 'relative !important';
                    related.style.zIndex = '9998 !important';
                    
                    // Show indicator
                    this.showZoneIndicator(related, 'Gruppe bilden', '#007cba');
                    
                    this.pendingGroupCreation = {
                        draggedQuestionId: draggedQuestionId,
                        targetQuestionId: targetQuestionId,
                        position: evt.newIndex,
                        isGroupCreation: true
                    };
                } else if (inMiddleZone) {
                    // Reordering zone
                    console.log('📋 Applying reorder visual feedback');
                    related.style.outline = '4px solid #28a745 !important';
                    related.style.outlineOffset = '2px !important';
                    related.style.backgroundColor = 'rgba(40, 167, 69, 0.2) !important';
                    related.style.position = 'relative !important';
                    related.style.zIndex = '9998 !important';
                    
                    // Show indicator
                    this.showZoneIndicator(related, 'Reihenfolge ändern', '#28a745');
                    
                    this.pendingGroupCreation = {
                        draggedQuestionId: draggedQuestionId,
                        targetQuestionId: targetQuestionId,
                        position: evt.newIndex,
                        isGroupCreation: false
                    };
                }
                
                console.log('💾 Stored pending group creation:', this.pendingGroupCreation);
                
                return true;
            } else {
                console.log('❌ Container is not questionsList:', container?.id);
            }
        } else {
            console.log('❌ Not both question items:');
            console.log('   - Related has question-item class:', related?.classList.contains('question-item'));
            console.log('   - Dragged has question-item class:', dragged?.classList.contains('question-item'));
        }
        
        console.log('↩️ Returning true (allowing drop)');
        return true;
    }

    handleUngroupedQuestionReorder(evt) {
        console.log('🔄 handleUngroupedQuestionReorder called');
        console.log('📍 Event details:', evt);
        console.log('💾 Pending group creation:', this.pendingGroupCreation);
        
        // Check if this was a question-on-question drop that should create a group
        if (this.pendingGroupCreation) {
            console.log('✅ Found pending group creation');
            
            // Check for both old and new format
            const { draggedQuestionId, targetQuestionId, isGroupCreation } = this.pendingGroupCreation;
            
            // Accept group creation if explicitly marked OR if we have both question IDs
            const shouldCreateGroup = isGroupCreation || (draggedQuestionId && targetQuestionId);
            
            console.log('� Group creation analysis:', {
                draggedQuestionId,
                targetQuestionId,
                isGroupCreation,
                shouldCreateGroup
            });
            
            if (shouldCreateGroup) {
                console.log('�🔗 Creating group with questions:', [draggedQuestionId, targetQuestionId]);
                
                // Create group with both questions
                this.createGroupFromQuestions([draggedQuestionId, targetQuestionId]);
                this.pendingGroupCreation = null;
                
                console.log('🧹 Cleared pending group creation');
                return;
            }
        }

        console.log('📋 No pending group creation - handling normal reorder');
        
        // Normal reorder
        const questionElements = document.querySelectorAll('#questionsList .question-item');
        const questionIds = Array.from(questionElements).map(el => el.dataset.questionId);
        
        console.log('📝 Question IDs for reorder:', questionIds);
        
        this.saveQuestionOrder(questionIds, null);
    }

    handleQuestionMove(evt) {
        const questionElement = evt.item;
        const targetContainer = evt.to;
        const questionId = questionElement.dataset.questionId;
        const newIndex = evt.newIndex;

        // Determine target group
        let targetGroupId = null;
        if (targetContainer.classList.contains('questions-list')) {
            targetGroupId = targetContainer.dataset.groupId;
            // Convert 'ungrouped' to null for API
            if (targetGroupId === 'ungrouped') {
                targetGroupId = null;
            }
        }

        // Update question's group assignment
        this.moveQuestionToGroup(questionId, targetGroupId, newIndex);
    }

    handleQuestionReorder(evt) {
        const questionId = evt.item.dataset.questionId;
        const container = evt.to;
        const newIndex = evt.newIndex;

        // Determine group
        let groupId = null;
        if (container.classList.contains('questions-list')) {
            groupId = container.dataset.groupId;
        }

        // Get all questions in this container for order update
        const questionElements = container.querySelectorAll('.question-item');
        const questionIds = Array.from(questionElements).map(el => el.dataset.questionId);
        
        this.saveQuestionOrder(questionIds, groupId);
    }

    handleQuestionRemove(evt) {
        const sourceContainer = evt.from;
        
        // Check if source was a group
        if (sourceContainer.classList.contains('questions-list')) {
            const groupId = sourceContainer.dataset.groupId;
            const remainingQuestions = sourceContainer.querySelectorAll('.question-item').length;
            
            // If group is now empty, offer to delete it
            if (remainingQuestions === 0 && groupId && groupId !== 'ungrouped') {
                this.handleEmptyGroup(groupId);
            }
        }
    }

    handleSimpleReorder(evt) {
        console.log('📋 handleSimpleReorder called');
        console.log('📍 Event:', evt);
        
        // Get current question order
        const questionElements = document.querySelectorAll('#questionsList .question-item');
        const questionIds = Array.from(questionElements).map(el => el.dataset.questionId);
        
        console.log('📝 Current question order:', questionIds);
        
        this.saveQuestionOrder(questionIds);
    }

    // New methods for enhanced drag & drop with group creation
    addDropTargetHighlights() {
        const questionItems = document.querySelectorAll('#questionsList .question-item');
        questionItems.forEach(item => {
            if (item !== this.currentDraggedItem) {
                item.classList.add('potential-drop-target');
                item.style.cursor = 'copy'; // Einfügen-Cursor für Drop-Ziele
            }
        });
    }

    removeDropTargetHighlights() {
        document.querySelectorAll('.potential-drop-target').forEach(item => {
            item.classList.remove('potential-drop-target');
            item.style.cursor = ''; // Cursor zurücksetzen
        });
        document.querySelectorAll('.drop-target-active').forEach(item => {
            item.classList.remove('drop-target-active');
            item.style.cursor = ''; // Cursor zurücksetzen
        });
        // Entferne auch Gruppenpreviews
        this.removeGroupPreview();
    }

    showGroupPreview(targetElement) {
        // Entferne vorherige Previews
        this.removeGroupPreview();
         
        // Zeige visuellen Gruppenpreview
        const draggedElement = this.currentDraggedItem;
        if (draggedElement && targetElement && draggedElement !== targetElement) {
            // Beide Elemente als Gruppe markieren
            draggedElement.classList.add('group-preview', 'group-preview-source');
            targetElement.classList.add('group-preview', 'group-preview-target');
            
            // Gruppierungsindikator hinzufügen
            this.addGroupIndicator(targetElement);
        }
    }

    removeGroupPreview() {
        const previewElements = document.querySelectorAll('.group-preview');
        previewElements.forEach(el => {
            el.classList.remove('group-preview', 'group-preview-source', 'group-preview-target');
            // Reset inline styles
            el.style.border = '';
            el.style.backgroundColor = '';
        });
        
        // Entferne Gruppierungsindikatoren
        const indicators = document.querySelectorAll('.group-indicator');
        indicators.forEach(indicator => indicator.remove());
    }

    // Test function to manually apply visual effects
    testVisualEffects() {
        console.log('🧪 Testing visual effects...');
        
        // First check if CSS file is loaded
        console.log('🔍 Checking CSS file...');
        const cssLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
        const dragDropCSS = cssLinks.find(link => link.href.includes('admin-drag-drop.css'));
        console.log('📋 CSS file loaded:', !!dragDropCSS);
        if (dragDropCSS) {
            console.log('📎 CSS file URL:', dragDropCSS.href);
        }
        
        const firstQuestion = document.querySelector('.question-item');
        if (firstQuestion) {
            console.log('🎯 Found test target:', firstQuestion);
            
            // Test blue border (group creation)
            console.log('🔵 Testing blue border...');
            firstQuestion.style.border = '3px dashed #007cba !important';
            firstQuestion.style.backgroundColor = 'rgba(0, 124, 186, 0.2) !important';
            firstQuestion.style.transition = 'all 0.3s ease';
            firstQuestion.style.zIndex = '9999';
            
            // Force reflow to ensure styles apply
            firstQuestion.offsetHeight;
            
            console.log('🔍 Applied blue styles:', {
                border: firstQuestion.style.border,
                backgroundColor: firstQuestion.style.backgroundColor,
                computedBorder: getComputedStyle(firstQuestion).border,
                computedBackground: getComputedStyle(firstQuestion).backgroundColor
            });
            
            setTimeout(() => {
                console.log('🟢 Testing green border...');
                firstQuestion.style.border = '3px solid #28a745 !important';
                firstQuestion.style.backgroundColor = 'rgba(40, 167, 69, 0.2) !important';
                
                console.log('🔍 Applied green styles:', {
                    border: firstQuestion.style.border,
                    backgroundColor: firstQuestion.style.backgroundColor,
                    computedBorder: getComputedStyle(firstQuestion).border,
                    computedBackground: getComputedStyle(firstQuestion).backgroundColor
                });
                
                setTimeout(() => {
                    console.log('🧹 Cleaning up test...');
                    firstQuestion.style.border = '';
                    firstQuestion.style.backgroundColor = '';
                    firstQuestion.style.zIndex = '';
                }, 2000);
            }, 2000);
        } else {
            console.log('❌ No test target found');
            console.log('🔍 Available elements:', document.querySelectorAll('.question-item, [data-question-id]'));
        }
    }

    showZoneIndicator(element, text, color) {
        console.log('🎨 showZoneIndicator called:', { text, color, element });
        
        // Remove existing indicators
        this.removeZoneIndicators();
        
        // Create new indicator with maximum visibility
        const indicator = document.createElement('div');
        indicator.className = 'zone-indicator-tooltip';
        indicator.textContent = text;
        
        // Very aggressive styling to ensure visibility
        indicator.style.cssText = `
            position: fixed !important;
            background: ${color} !important;
            color: white !important;
            padding: 8px 12px !important;
            border-radius: 4px !important;
            font-size: 14px !important;
            font-weight: bold !important;
            z-index: 99999 !important;
            pointer-events: none !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important;
            border: 2px solid white !important;
            white-space: nowrap !important;
            font-family: Arial, sans-serif !important;
        `;
        
        // Position at top of viewport for maximum visibility
        const rect = element.getBoundingClientRect();
        const indicatorTop = Math.max(10, rect.top - 40);
        const indicatorLeft = Math.max(10, rect.left + (rect.width / 2) - 60);
        
        indicator.style.top = `${indicatorTop}px`;
        indicator.style.left = `${indicatorLeft}px`;
        
        console.log('🎯 Indicator positioning:', {
            top: indicatorTop,
            left: indicatorLeft,
            elementRect: rect
        });
        
        // Add to body for maximum z-index effect
        document.body.appendChild(indicator);
        
        // Also add very visible border effect to the element
        element.style.outline = `4px solid ${color} !important`;
        element.style.outlineOffset = '2px !important';
        element.style.position = 'relative !important';
        element.style.zIndex = '9998 !important';
        
        // Add a background flash effect
        const originalBg = element.style.backgroundColor;
        element.style.backgroundColor = `${color}33 !important`; // 20% opacity
        
        console.log('✅ Zone indicator created with enhanced visibility');
        console.log('🔍 Indicator in DOM:', document.body.contains(indicator));
        console.log('🔍 Element outline applied:', element.style.outline);
        
        return indicator;
    }

    removeZoneIndicators() {
        console.log('🧹 removeZoneIndicators called');
        
        // Remove tooltip indicators
        document.querySelectorAll('.zone-indicator, .zone-indicator-tooltip').forEach(indicator => {
            console.log('🗑️ Removing indicator:', indicator);
            indicator.remove();
        });
        
        // Clean up element styles
        document.querySelectorAll('.question-item').forEach(element => {
            element.style.outline = '';
            element.style.outlineOffset = '';
            element.style.backgroundColor = '';
            element.style.border = '';
            element.style.zIndex = '';
        });
        
        console.log('🧹 All indicators and styles cleaned up');
    }

    showGroupCreationPreview(targetElement) {
        console.log('🎨 showGroupCreationPreview called');
        console.log('🎯 Target element:', targetElement);
        console.log('🎯 Current dragged item:', this.currentDraggedItem);
        
        // Entferne vorherige Previews
        this.removeGroupPreview();
         
        // Zeige visuellen Gruppenpreview
        const draggedElement = this.currentDraggedItem;
        if (draggedElement && targetElement && draggedElement !== targetElement) {
            console.log('✅ Adding group creation preview');
            
            // Beide Elemente als Gruppe markieren
            draggedElement.classList.add('group-preview', 'group-preview-source');
            targetElement.classList.add('group-preview', 'group-preview-target');
            
            // Gruppierungsindikator hinzufügen
            this.addGroupIndicator(targetElement);
            
            // Zusätzlicher visueller Hinweis
            targetElement.style.border = '2px dashed #007cba';
            targetElement.style.backgroundColor = 'rgba(0, 124, 186, 0.1)';
        } else {
            console.log('❌ Cannot show preview - missing elements or same element');
        }
    }

    addGroupIndicator(targetElement) {
        // Prüfe ob bereits ein Indikator existiert
        if (targetElement.querySelector('.group-indicator')) {
            return;
        }
        
        // Erstelle visuellen Gruppierungsindikator
        const indicator = document.createElement('div');
        indicator.className = 'group-indicator';
        indicator.innerHTML = `
            <div class="group-indicator-content">
                <i class="fas fa-layer-group"></i>
                <span>Neue Gruppe erstellen</span>
            </div>
        `;
        
        // Positioniere den Indikator
        targetElement.appendChild(indicator);
    }

    handleQuestionOverQuestion(evt) {
        // Remove previous active highlights
        document.querySelectorAll('.drop-target-active').forEach(item => {
            item.classList.remove('drop-target-active');
        });
        
        // Add highlight to the item we're hovering over
        if (evt.related && evt.related !== this.currentDraggedItem) {
            evt.related.classList.add('drop-target-active');
        }
    }

    checkForGroupCreation(evt) {
        console.log('🔍 checkForGroupCreation called');
        console.log('📍 Event details:', evt);
        
        const draggedItem = evt.item;
        const draggedId = draggedItem.dataset.questionId;
        
        console.log('🔢 Dragged question ID:', draggedId);
        console.log('💾 Pending group creation:', this.pendingGroupCreation);
        
        // Check if we have a pending group creation
        if (this.pendingGroupCreation && this.pendingGroupCreation.isGroupCreation) {
            console.log('✅ Found pending group creation with isGroupCreation flag');
            
            const { draggedQuestionId, targetQuestionId } = this.pendingGroupCreation;
            
            console.log('🔗 Questions for group:', { draggedQuestionId, targetQuestionId });
            
            // Clean up visual feedback
            this.removeGroupPreview();
            
            // Check if questions are already in the same group
            const draggedGroupId = this.getQuestionGroupId(draggedQuestionId);
            const targetGroupId = this.getQuestionGroupId(targetQuestionId);
            
            console.log('📊 Group IDs:', { draggedGroupId, targetGroupId });
            
            if (draggedGroupId === targetGroupId && draggedGroupId !== null) {
                console.log('📋 Questions already in same group - handling simple reorder');
                this.handleSimpleReorder(evt);
                this.pendingGroupCreation = null;
                return;
            }
            
            console.log('🆕 Creating new group...');
            // Create group from questions
            this.createGroupFromQuestions([draggedQuestionId, targetQuestionId]);
            this.pendingGroupCreation = null;
            return;
        }
        
        console.log('📝 No pending group creation with isGroupCreation flag');
        
        // Check if this was a move from a group to ungrouped (removing from group)
        const draggedFromGroup = evt.from && evt.from.classList.contains('grouped-questions');
        const droppedInUngrouped = evt.to && evt.to.id === 'questionsList';
        
        console.log('📊 Move analysis:', { draggedFromGroup, droppedInUngrouped });
        
        if (draggedFromGroup && droppedInUngrouped) {
            console.log('🗑️ Removing question from group');
            this.removeQuestionFromGroup(draggedId);
            this.pendingGroupCreation = null;
            return;
        }
        
        // Clean up any remaining styling
        this.removeGroupPreview();
        
        console.log('📋 Handling simple reorder');
        // For normal repositioning without specific zone targeting
        this.handleSimpleReorder(evt);
        this.pendingGroupCreation = null;
    }

    // API calls
    async moveQuestionToGroup(questionId, groupId, position) {
        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    action: 'move-question-to-group',
                    question_id: questionId,
                    target_group_id: groupId,
                    position: position,
                    questionnaire_id: this.questionnaireId
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Frage verschoben', 'success');
                } else {
                    throw new Error(data.error || 'Failed to move question');
                }
            } else {
                throw new Error(`HTTP ${response.status}: Failed to move question`);
            }
        } catch (error) {
            console.error('❌ Error moving question:', error);
            this.showNotification('Fehler beim Verschieben der Frage: ' + error.message, 'error');
        }
    }

    async saveQuestionOrder(questionIds, groupId = null) {
        if (!this.questionnaireId) return;

        try {
     
            // Convert question IDs to integers
            const questions = questionIds.map(id => parseInt(id));
            
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    action: 'reorder-questions',
                    questionnaire_id: parseInt(this.questionnaireId),
                    questions: questions,
                    group_id: groupId === 'ungrouped' ? null : groupId
                })
            });


            if (response.ok) {
                const data = await response.json();

                if (data.success) {
                    this.showNotification('Reihenfolge gespeichert', 'success');
                } else {
                    console.error('❌ Reorder error:', data.error);
                    this.showNotification('Fehler beim Speichern: ' + data.error, 'error');
                }
            } else {
                console.error('❌ Reorder HTTP error:', response.status);
                this.showNotification('HTTP-Fehler beim Speichern', 'error');
            }
        } catch (error) {
            console.error('❌ Error saving question order:', error);
            this.showNotification('Fehler beim Speichern der Reihenfolge', 'error');
        }
    }

    async saveGroupOrder(groupIds) {
        if (!this.questionnaireId) return;

        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    action: 'reorder-groups',
                    questionnaire_id: this.questionnaireId,
                    groups: groupIds
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Gruppen-Reihenfolge gespeichert', 'success');
                } else {
                    console.error('❌ Group reorder error:', data.error);
                }
            } else {
                console.error('❌ Group reorder HTTP error:', response.status);
            }
        } catch (error) {
            console.error('❌ Error saving group order:', error);
            this.showNotification('Fehler beim Speichern der Gruppen-Reihenfolge', 'error');
        }
    }

    async createGroupFromQuestions(questionIds) {
        console.log('🔧 createGroupFromQuestions called with:', questionIds);
        console.log('📝 Current questionnaireId:', this.questionnaireId);
        
        // Generate automatic group name
        const groupName = `Neue Gruppe ${Date.now().toString().slice(-6)}`;
        console.log('📛 Generated group name:', groupName);
        
        // Validate inputs
        if (!this.questionnaireId) {
            console.error('❌ No questionnaireId set!');
            this.showNotification('Fehler: Kein Fragebogen ausgewählt', 'error');
            return;
        }
        
        if (!questionIds || questionIds.length === 0) {
            console.error('❌ No question IDs provided!');
            this.showNotification('Fehler: Keine Fragen ausgewählt', 'error');
            return;
        }
        
        console.log('🚀 Preparing API request...');
        const requestData = {
            action: 'create-group-from-questions',
            questionnaire_id: parseInt(this.questionnaireId),
            name: groupName,
            question_ids: questionIds.map(id => parseInt(id)),
            description: `Automatisch erstellte Gruppe aus ${questionIds.length} Fragen`
        };
        
        console.log('📤 Request data:', requestData);
        
        // Check CSRF token
        console.log('🔍 Current CSRF token:', this.csrfToken);
        console.log('🔍 Headers:', this.getHeaders());
        
        // Try to reload CSRF token if not available
        if (!this.csrfToken) {
            console.log('⚠️ No CSRF token, trying to reload...');
            this.loadCSRFToken();
            console.log('🔄 After reload - CSRF token:', this.csrfToken);
        }
        
        try {
            console.log('🌐 Sending API request...');
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify(requestData)
            });
            
            console.log('📨 Response status:', response.status);
            const result = await response.json();
            console.log('📨 Response data:', result);
            
            if (result.success) {
                console.log('✅ Group created successfully!');
                this.showNotification(`Gruppe "${groupName}" erfolgreich erstellt!`, 'success');
                
                // Reload the questionnaire to show the new group
                this.loadQuestionnaireGroups();
                
                return result;
            } else {
                console.error('❌ API Error:', result.error);
                
                // If CSRF token error, try to reload the page
                if (result.error && result.error.includes('CSRF')) {
                    console.log('🔄 CSRF token error detected, suggesting page reload');
                    this.showNotification('CSRF-Token abgelaufen. Bitte laden Sie die Seite neu.', 'error');
                    
                    // Try to reload CSRF token
                    this.loadCSRFToken();
                } else {
                    this.showNotification(`Fehler beim Erstellen der Gruppe: ${result.error}`, 'error');
                }
                return null;
            }
        } catch (error) {
            console.error('❌ Network/API Error:', error);
            this.showNotification('Netzwerkfehler beim Erstellen der Gruppe', 'error');
            return null;
        }
    }

    async createGroupWithQuestion(questionId) {
        const groupName = prompt('Name für die neue Gruppe:');
        if (!groupName) return;

        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    action: 'create-group',
                    questionnaire_id: this.questionnaireId,
                    name: groupName,
                    question_ids: [questionId]
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Neue Gruppe erstellt', 'success');
                    
                    // Reload questions to show new group structure
                    window.loadQuestionnaireQuestions(this.questionnaireId);
                    this.loadQuestionnaireGroups(this.questionnaireId);
                } else {
                    throw new Error(data.error || 'Failed to create group');
                }
            } else {
                throw new Error(`HTTP ${response.status}: Failed to create group`);
            }
        } catch (error) {
            console.error('❌ Error creating group:', error);
            this.showNotification('Fehler beim Erstellen der Gruppe: ' + error.message, 'error');
        }
    }

    async createNewGroup() {
        const groupName = prompt('Name für die neue Gruppe:');
        if (!groupName) return;

        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    action: 'create-group',
                    questionnaire_id: this.questionnaireId,
                    name: groupName,
                    description: ''
                })
            });

            if (response.ok) {
                const result = await response.json();
                this.showNotification('Neue Gruppe erstellt', 'success');
                
                // Reload groups and questions
                this.loadQuestionnaireGroups(this.questionnaireId);
                window.loadQuestionnaireQuestions(this.questionnaireId);
            } else {
                throw new Error('Failed to create group');
            }
        } catch (error) {
            console.error('Error creating group:', error);
            this.showNotification('Fehler beim Erstellen der Gruppe', 'error');
        }
    }

    async editGroup(groupId) {
        const group = this.groups.find(g => g.id === groupId);
        if (!group) return;

        const newName = prompt('Neuer Name für die Gruppe:', group.name);
        if (!newName || newName === group.name) return;

        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    action: 'update-group',
                    id: groupId,
                    name: newName,
                    description: group.description
                })
            });

            if (response.ok) {
                this.showNotification('Gruppe aktualisiert', 'success');
                
                // Update local data
                group.name = newName;
                
                // Update UI
                const groupElement = document.querySelector(`[data-group-id="${groupId}"] .group-title`);
                if (groupElement) {
                    groupElement.textContent = newName;
                }
            } else {
                throw new Error('Failed to update group');
            }
        } catch (error) {
            console.error('Error updating group:', error);
            this.showNotification('Fehler beim Aktualisieren der Gruppe', 'error');
        }
    }

    getCurrentQuestions() {
        // Try to get questions from current DOM or stored data
        const questionItems = document.querySelectorAll('.question-item[data-question-id]');
        const questions = [];
        
        questionItems.forEach(item => {
            const questionId = item.dataset.questionId;
            const questionText = item.querySelector('.question-text')?.textContent || '';
            const questionType = item.dataset.questionType || 'text';
            const groupId = item.closest('.question-group')?.dataset.groupId || null;
            
            questions.push({
                id: questionId,
                question_text: questionText,
                question_type: questionType,
                group_id: groupId === 'ungrouped' ? null : groupId
            });
        });
        
        return questions;
    }

    async deleteGroup(groupId) {
        if (!confirm('Sind Sie sicher, dass Sie diese Gruppe löschen möchten? Alle Fragen werden in "Nicht gruppierte Fragen" verschoben.')) {
            return;
        }

        try {
            // First, get all questions from the group that will be deleted
            const groupElement = document.querySelector(`.question-group[data-group-id="${groupId}"]`);
            const questionsToMove = [];
            
            if (groupElement) {
                const questionItems = groupElement.querySelectorAll('.question-item[data-question-id]');
                questionItems.forEach(item => {
                    const questionId = item.dataset.questionId;
                    const questionText = item.querySelector('.question-text')?.textContent || '';
                    const questionType = item.dataset.questionType || 'text';
                    const isRequired = item.querySelector('.badge-warning') ? true : false;
                    const helpText = item.querySelector('.question-help small')?.textContent || '';
                    
                    questionsToMove.push({
                        id: questionId,
                        question_text: questionText,
                        question_type: questionType,
                        is_required: isRequired,
                        help_text: helpText,
                        group_id: null // Will become ungrouped
                    });
                });
            }
            
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    action: 'delete-group',
                    id: groupId
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Gruppe gelöscht', 'success');
                    
                    // Remove the group from local groups array
                    this.groups = this.groups.filter(group => group.id != groupId);
                    
                    // Move questions to ungrouped area in UI
                    if (questionsToMove.length > 0) {
                        const ungroupedContainer = document.querySelector('#questionsList');
                        if (ungroupedContainer) {
                            // Remove empty message if present
                            const emptyMessage = ungroupedContainer.querySelector('.empty-message');
                            if (emptyMessage) {
                                emptyMessage.remove();
                            }
                            
                            // Add each question using the correct renderQuestionItem method
                            questionsToMove.forEach((question, index) => {
                                const questionHtml = this.renderQuestionItem(question, ungroupedContainer.children.length + index);
                                ungroupedContainer.insertAdjacentHTML('beforeend', questionHtml);
                            });
                        }
                    }
                    
                    // Remove the group element from DOM
                    if (groupElement) {
                        groupElement.remove();
                    }
                    
                    // Reinitialize sortables after DOM changes with delay for proper initialization
                    setTimeout(() => {
                        this.initializeSortables();
                        
                        // Also reinitialize enhanced sortable for ungrouped questions  
                        const ungroupedQuestions = Array.from(document.querySelectorAll('#questionsList .question-item'));
                        if (ungroupedQuestions.length > 0) {
                            this.makeEnhancedSortable(ungroupedQuestions.map(item => ({
                                id: item.dataset.questionId,
                                question_text: item.querySelector('.question-text')?.textContent || '',
                                question_type: item.dataset.questionType || 'text'
                            })));
                        }
                        
                    }, 100);
                } else {
                    throw new Error(data.error || 'Failed to delete group');
                }
            } else {
                throw new Error(`HTTP ${response.status}: Failed to delete group`);
            }
        } catch (error) {
            console.error('❌ Error deleting group:', error);
            this.showNotification('Fehler beim Löschen der Gruppe: ' + error.message, 'error');
        }
    }

    editGroup(groupId) {
        
        // Add the test group to the groups array if it doesn't exist
        if (!this.groups.some(g => g.id == groupId)) {
            this.groups.push({
                id: groupId,
                name: 'Test Gruppe',
                description: 'Test Beschreibung'
            });
        }
        
        // Find the group data
        const group = this.groups.find(g => g.id == groupId);
        if (!group) {
            this.showNotification('Gruppe nicht gefunden', 'error');
            return;
        }

        // Populate the modal with current group data
        const nameField = document.getElementById('editGroupName');
        const descField = document.getElementById('editGroupDescription');
        const modal = document.getElementById('editGroupModal');
        
        if (!nameField || !descField || !modal) {
            console.error('Modal elements not found');
            return;
        }
        
        nameField.value = group.name;
        descField.value = group.description || '';
        
        // Store the group ID for saving
        modal.dataset.groupId = groupId;
        
        // Show the modal with the correct CSS classes
        modal.style.display = 'block';
        modal.classList.add('show');
    }

    async saveGroupChanges() {
        const modal = document.getElementById('editGroupModal');
        const groupId = modal.dataset.groupId;
        const name = document.getElementById('editGroupName').value.trim();
        const description = document.getElementById('editGroupDescription').value.trim();
        
        if (!name) {
            this.showNotification('Gruppenname ist erforderlich', 'error');
            return;
        }
        
        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    action: 'update-group',
                    id: groupId,
                    name: name,
                    description: description
                })
            });

            if (response.ok) {
                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Full response:', responseText);
                    throw new Error('Server returned invalid JSON: ' + responseText.substring(0, 100));
                }
                
                if (data.success) {
                    this.showNotification('Gruppe aktualisiert', 'success');
                    
                    // Update the group in local data
                    const groupIndex = this.groups.findIndex(g => g.id == groupId);
                    if (groupIndex !== -1) {
                        this.groups[groupIndex].name = name;
                        this.groups[groupIndex].description = description;
                    }
                    
                    // Update the UI
                    const groupElement = document.querySelector(`.question-group[data-group-id="${groupId}"]`);
                    if (groupElement) {
                        const titleElement = groupElement.querySelector('.group-title');
                        const descriptionElement = groupElement.querySelector('.group-description');
                        
                        if (titleElement) {
                            titleElement.textContent = name;
                        }
                        
                        if (description) {
                            if (descriptionElement) {
                                descriptionElement.textContent = description;
                            } else {
                                // Add description element if it doesn't exist
                                const groupInfo = groupElement.querySelector('.group-info');
                                const descEl = document.createElement('p');
                                descEl.className = 'group-description';
                                descEl.textContent = description;
                                groupInfo.appendChild(descEl);
                            }
                        } else if (descriptionElement) {
                            // Remove description if empty
                            descriptionElement.remove();
                        }
                    }
                    
                    // Close the modal
                    this.closeModal('editGroupModal');
                } else {
                    throw new Error(data.error || 'Failed to update group');
                }
            } else {
                throw new Error('HTTP ' + response.status + ': Failed to update group');
            }
        } catch (error) {
            console.error('Error updating group:', error);
            this.showNotification('Fehler beim Aktualisieren der Gruppe: ' + error.message, 'error');
        }
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            // Use setTimeout to allow transition to complete before hiding
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
            
            // Clear form data
            if (modalId === 'editGroupModal') {
                document.getElementById('editGroupName').value = '';
                document.getElementById('editGroupDescription').value = '';
                delete modal.dataset.groupId;
            }
        }
    }

    async removeQuestionFromGroup(questionId) {
        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    action: 'remove-question-from-group',
                    question_id: questionId
                })
            });

            if (response.ok) {
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Frage aus Gruppe entfernt', 'success');
                    
                    // Reload questions and groups
                    this.loadQuestionnaireGroups(this.questionnaireId);
                    window.loadQuestionnaireQuestions(this.questionnaireId);
                } else {
                    throw new Error(data.message || 'Failed to remove question from group');
                }
            } else {
                throw new Error('Failed to remove question from group');
            }
        } catch (error) {
            console.error('Error removing question from group:', error);
            this.showNotification('Fehler beim Entfernen der Frage aus der Gruppe', 'error');
        }
    }

    // Helper methods for zone-based drag & drop
    getQuestionGroupId(questionId) {
        // Find which group (if any) contains this question
        const questionElement = document.querySelector(`[data-question-id="${questionId}"]`);
        if (!questionElement) return null;
        
        const groupContainer = questionElement.closest('.question-group[data-group-id]');
        return groupContainer ? groupContainer.dataset.groupId : null;
    }

    revertToOriginalPosition(evt) {
        // SortableJS handles this automatically when we don't make API changes
        // Just clean up visual feedback
        this.removeGroupPreview();
        document.querySelectorAll('.group-creation-target, .position-change-target, .drop-target-active').forEach(item => {
            item.classList.remove('group-creation-target', 'position-change-target', 'drop-target-active');
            item.style.cursor = '';
        });
    }

    // Utility functions
    getQuestionTypeText(type) {
        const types = {
            'text': 'Text',
            'email': 'E-Mail',
            'phone': 'Telefon', 
            'textarea': 'Textbereich',
            'select': 'Auswahl',
            'radio': 'Radio',
            'checkbox': 'Checkbox',
            'date': 'Datum',
            'number': 'Zahl'
        };
        return types[type] || type;
    }

    showNotification(message, type = 'info') {
        // Use existing admin notification system
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
        } else {
            console.warn(`${type.toUpperCase()}: ${message}`);
        }
    }

    showDeleteEmptyGroupDialog(groupId) {
        if (confirm('Diese Gruppe ist jetzt leer. Möchten Sie sie löschen?')) {
            this.deleteGroup(groupId);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.adminDragDrop = new AdminDragDrop();
    
    // Add test group HTML directly for debugging
    setTimeout(() => {
        const groupsContainer = document.getElementById('groupsContainer');
        if (groupsContainer) {
            groupsContainer.innerHTML = `
                <div class="question-group" data-group-id="test-1">
                    <div class="group-header" draggable="true">
                        <div class="group-drag-handle" title="Gruppe ziehen">
                            <i class="fas fa-grip-vertical"></i>
                        </div>
                        <div class="group-info">
                            <h5 class="group-title">Test Gruppe</h5>
                            <p class="group-description">Test Beschreibung</p>
                        </div>
                        <div class="group-actions">
                            <button class="btn btn-sm btn-outline" data-action="edit-group" data-group-id="test-1" title="Bearbeiten">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" data-action="delete-group" data-group-id="test-1" title="Löschen">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="questions-list" data-group-id="test-1">
                        <div class="empty-group-message">
                            <i class="fas fa-question-circle"></i>
                            <p>Ziehen Sie Fragen hierher</p>
                        </div>
                    </div>
                </div>
            `;
        }
    }, 2000);
});

// Global functions for compatibility
window.toggleGroupMode = () => window.adminDragDrop?.toggleGroupMode();
window.toggleQuestionPalette = () => window.adminDragDrop?.toggleQuestionPalette();
window.closeModal = (modalId) => window.adminDragDrop?.closeModal(modalId);
window.saveGroupChanges = () => window.adminDragDrop?.saveGroupChanges();

// Debug and test functions
window.testDragDropVisuals = () => window.adminDragDrop?.testVisualEffects();
window.dragDropManager = () => window.adminDragDrop;

// Check CSRF token availability
window.checkCSRFToken = () => {
    console.log('🔍 CSRF Token Debug:');
    console.log('- sessionStorage:', sessionStorage.getItem('csrf_token'));
    console.log('- window.admin:', window.admin?.csrfToken);
    console.log('- META tag:', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));
    console.log('- adminDragDrop instance:', window.adminDragDrop?.csrfToken);
    
    // Try to get fresh token
    if (window.adminDragDrop) {
        window.adminDragDrop.loadCSRFToken();
        console.log('- After reload:', window.adminDragDrop.csrfToken);
    }
};

// Emergency visual test function
window.forceVisualTest = () => {
    console.log('🚨 Emergency visual test');
    const questions = document.querySelectorAll('.question-item');
    console.log('📋 Found questions:', questions.length);
    
    if (questions.length > 0) {
        const first = questions[0];
        console.log('🎯 Testing on first question:', first);
        
        // Apply very obvious visual effects
        first.style.cssText += `
            outline: 5px solid red !important;
            background: yellow !important;
            transform: scale(1.05) !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 0 20px rgba(255,0,0,0.8) !important;
        `;
        
        // Create floating message
        const msg = document.createElement('div');
        msg.textContent = '🔥 VISUAL TEST ACTIVE';
        msg.style.cssText = `
            position: fixed !important;
            top: 50px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            background: red !important;
            color: white !important;
            padding: 20px !important;
            font-size: 20px !important;
            font-weight: bold !important;
            z-index: 999999 !important;
            border-radius: 10px !important;
        `;
        document.body.appendChild(msg);
        
        setTimeout(() => {
            first.style.cssText = '';
            msg.remove();
        }, 3000);
    }
};

// Test zone detection and visual feedback directly
window.testZoneVisuals = () => {
    console.log('🎯 Testing zone visuals...');
    const questions = document.querySelectorAll('.question-item');
    
    if (questions.length >= 2) {
        const target = questions[1]; // Use second question as target
        console.log('🎯 Target element:', target);
        
        // Test blue (group creation) visual
        console.log('🔵 Testing group creation visual...');
        target.style.outline = '4px dashed #007cba !important';
        target.style.outlineOffset = '2px !important';
        target.style.backgroundColor = 'rgba(0, 124, 186, 0.2) !important';
        target.style.position = 'relative !important';
        target.style.zIndex = '9998 !important';
        
        // Test zone indicator
        if (window.adminDragDrop) {
            window.adminDragDrop.showZoneIndicator(target, 'Gruppe bilden', '#007cba');
        }
        
        setTimeout(() => {
            console.log('🟢 Testing reorder visual...');
            target.style.outline = '4px solid #28a745 !important';
            target.style.backgroundColor = 'rgba(40, 167, 69, 0.2) !important';
            
            if (window.adminDragDrop) {
                window.adminDragDrop.showZoneIndicator(target, 'Reihenfolge ändern', '#28a745');
            }
            
            setTimeout(() => {
                console.log('🧹 Cleaning up...');
                target.style.outline = '';
                target.style.backgroundColor = '';
                target.style.position = '';
                target.style.zIndex = '';
                
                if (window.adminDragDrop) {
                    window.adminDragDrop.removeZoneIndicators();
                }
            }, 3000);
        }, 3000);
    } else {
        console.log('❌ Need at least 2 questions for zone test');
    }
};

// Test group creation directly with CSRF check
window.testGroupCreation = () => {
    console.log('🧪 Testing group creation...');
    
    // First check CSRF token
    if (window.adminDragDrop) {
        console.log('🔍 Current CSRF token:', window.adminDragDrop.csrfToken);
        
        if (!window.adminDragDrop.csrfToken) {
            console.log('⚠️ No CSRF token found, trying to reload...');
            window.adminDragDrop.loadCSRFToken();
        }
        
        const questions = document.querySelectorAll('.question-item');
        
        if (questions.length >= 2) {
            const firstId = questions[0].dataset.questionId;
            const secondId = questions[1].dataset.questionId;
            
            console.log('🔧 Creating group with questions:', [firstId, secondId]);
            
            // Set pending group creation state
            window.adminDragDrop.pendingGroupCreation = {
                draggedQuestionId: firstId,
                targetQuestionId: secondId,
                isGroupCreation: true
            };
            
            // Call group creation directly
            window.adminDragDrop.createGroupFromQuestions([firstId, secondId]);
        } else {
            console.log('❌ Need at least 2 questions');
        }
    } else {
        console.log('❌ adminDragDrop not available');
    }
};
