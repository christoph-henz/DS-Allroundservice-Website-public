/**
 * DS Allroundservice - Admin Panel JavaScript
 * Handles all admin interface functionality
 */

// Global error handler to catch pricing data errors
window.addEventListener('error', function(e) {
    if (e.message && e.message.includes('Cannot read properties of null')) {
        console.error('🚨 Null reference error caught:', {
            message: e.message,
            filename: e.filename,
            lineno: e.lineno,
            colno: e.colno,
            stack: e.error?.stack
        });
    }
});

// ============================================================================
// Utility Functions
// ============================================================================

function showNotification(message, type = 'info', duration = 5000) {
    // Remove existing notifications
    const existing = document.querySelectorAll('.notification');
    existing.forEach(n => n.remove());

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icon = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    }[type] || 'fas fa-info-circle';

    notification.innerHTML = `
        <div class="notification-content">
            <i class="${icon}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    // Add to document
    document.body.appendChild(notification);

    // Auto-remove after duration
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, duration);

    // Add animation
    setTimeout(() => notification.classList.add('show'), 10);
}

function previewQuestionnaire(id) {
    // Find questionnaire data
    const questionnaire = questionnairesData.find(q => q.id === id);
    if (!questionnaire) {
        showNotification('Ansicht wird noch implementiert', 'info');
        return;
    }
    showNotification('Ansicht wird noch implementiert', 'info');
    /* Create preview modal
    const modalHtml = `
        <div id="questionnairePreviewModal" class="modal modal-large show">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Fragebogen-Vorschau: ${questionnaire.title}</h3>
                    <button class="modal-close" onclick="closeQuestionnairePreviewModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="questionnaire-preview">
                        <div class="questionnaire-info">
                            <h4>${questionnaire.title}</h4>
                            ${questionnaire.description ? `<p class="description">${questionnaire.description}</p>` : ''}
                            
                            <div class="questionnaire-meta">
                                <span class="badge badge-${questionnaire.status === 'active' ? 'success' : 'secondary'}">${questionnaire.status === 'active' ? 'Aktiv' : 'Entwurf'}</span>
                                ${questionnaire.service_types ? `<span class="services">Services: ${questionnaire.service_types}</span>` : ''}
                            </div>
                        </div>
                        
                        <div class="preview-placeholder" style="text-align: center; padding: 3rem; border: 2px dashed #ddd; border-radius: 8px; margin-top: 2rem;">
                            <i class="fas fa-file-alt" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                            <p>Fragebogen-Vorschau</p>
                            <small class="text-muted">Hier würden die verknüpften Fragen angezeigt werden</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeQuestionnairePreviewModal()">Schließen</button>
                    <button class="btn btn-primary" onclick="editQuestionnaire(${id})">Bearbeiten</button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.body.style.overflow = 'hidden';
    */
}

function closeQuestionnairePreviewModal() {
    const modal = document.getElementById('questionnairePreviewModal');
    if (modal) {
        modal.remove();
    }
    document.body.style.overflow = '';
}

class AdminPanel {
    constructor() {
        this.currentSection = 'dashboard';
        // Use the api path where the API actually exists
        this.apiBase = 'api/admin.php';
        this.csrfToken = null; // ✅ CSRF-Token speichern
        this.init();
    }

    async init() {
        // ✅ CSRF-Token vom Session laden (wird beim Login gesetzt)
        await this.loadCSRFToken();
        
        this.bindEvents();
        this.loadDashboardData();
        this.checkMobileView();
        this.initializeImageUpload();
        this.loadIconOptions(); // Load images for dropdowns
        this.handleUrlParameters(); // Handle URL parameters on load
    }
    
    /**
     * ✅ CSRF-Token aus Session/LocalStorage laden oder vom Server holen
     */
    async loadCSRFToken() {
        // Versuche Token aus sessionStorage zu laden
        this.csrfToken = sessionStorage.getItem('csrf_token');
        
        if (!this.csrfToken) {
            // Wenn kein Token vorhanden, vom Server holen (nach Login)
            try {
                const response = await fetch('api/auth.php?action=check-session', {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                
                if (data.success && data.csrf_token) {
                    this.csrfToken = data.csrf_token;
                    sessionStorage.setItem('csrf_token', this.csrfToken);
                }
            } catch (error) {
                console.error('❌ Fehler beim Laden des CSRF-Tokens:', error);
            }
        }
    }
    
    /**
     * ✅ CSRF-Token setzen (nach Login)
     */
    setCSRFToken(token) {
        this.csrfToken = token;
        sessionStorage.setItem('csrf_token', token);
    }

    handleUrlParameters() {
        // Check if there's a hash in the URL
        const hash = window.location.hash;
        
        if (hash) {
            // Parse hash (e.g., #submissions?id=123)
            const hashMatch = hash.match(/#([^?]+)(\?(.+))?/);
            
            if (hashMatch) {
                const section = hashMatch[1];
                const queryString = hashMatch[3];
                
                // Switch to the section
                if (section) {
                    this.switchSection(section);
                }
                
                // Parse query parameters
                if (queryString) {
                    const params = new URLSearchParams(queryString);
                    const submissionId = params.get('id');
                    
                    if (submissionId && section === 'submissions') {
                        // Wait for submissions to load, then highlight the specific one
                        setTimeout(() => {
                            const submissionElement = document.querySelector(`[data-submission-id="${submissionId}"]`);
                            if (submissionElement) {
                                submissionElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                submissionElement.classList.add('highlight');
                                
                                setTimeout(() => {
                                    submissionElement.classList.remove('highlight');
                                }, 2000);
                            }
                        }, 500);
                    }
                }
            }
        }
    }

    bindEvents() {
        // Sidebar navigation
        document.addEventListener('click', (e) => {
            if (e.target.matches('.menu-link')) {
                e.preventDefault();
                const section = e.target.dataset.section;
                this.switchSection(section);
            }
        });

        // Sidebar toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleSidebar();
        });

        // Mobile menu toggle
        const mobileToggleBtn = document.getElementById('mobileMenuToggle');
        
        mobileToggleBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleMobileMenu();
        });
        
        // Close mobile menu when clicking on menu link
        document.querySelectorAll('.menu-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    setTimeout(() => {
                        document.getElementById('adminSidebar')?.classList.remove('show');
                    }, 300);
                }
            });
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            const sidebar = document.getElementById('adminSidebar');
            const mobileToggle = document.getElementById('mobileMenuToggle');
            
            const isMobile = window.innerWidth <= 768;
            const hasShow = sidebar?.classList.contains('show');
            const clickedOnSidebar = sidebar?.contains(e.target);
            const clickedOnToggle = mobileToggle && (e.target === mobileToggle || mobileToggle.contains(e.target));
            
            // Only close if: on mobile, menu is open, clicked outside sidebar AND outside toggle button
            if (isMobile && hasShow && !clickedOnSidebar && !clickedOnToggle) {
                sidebar.classList.remove('show');
            }
        });

        // Modal events
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal')) {
                this.closeModal(e.target.id);
            }
        });

        // Form submissions
        document.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit(e.target);
        });

        // File upload
        this.initFileUpload();

        // Settings tabs
        document.addEventListener('click', (e) => {
            if (e.target.matches('.tab-btn')) {
                this.switchTab(e.target.dataset.tab);
            }
        });

        // Window resize
        window.addEventListener('resize', () => {
            this.checkMobileView();
        });

        // Auto-refresh dashboard
        setInterval(() => {
            if (this.currentSection === 'dashboard') {
                this.refreshDashboardStats();
            }
        }, 60000); // Refresh every minute
    }

    // ========================================================================
    // Form Handling
    // ========================================================================

    handleFormSubmit(form) {
        // Handle different forms based on their ID or context
        const formId = form.id;
        
        // Check if it's an email template form
        if (form.closest('#email-template-modal') || formId === 'email-template-form') {
            saveEmailTemplate();
            return;
        }
        
        switch(formId) {
            case 'servicePageForm':
                this.saveServicePageContent();
                break;
            case 'serviceForm':
                this.saveService();
                break;
            case 'questionForm':
                this.saveQuestion();
                break;
            default:
                console.warn('Unknown form submitted:', form);
                break;
        }
    }

    // ========================================================================
    // Navigation & Layout
    // ========================================================================

    switchSection(section) {
        // Update active menu item
        document.querySelectorAll('.menu-link').forEach(link => {
            link.classList.toggle('active', link.dataset.section === section);
        });

        // Hide all sections
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });

        // Show selected section
        const targetSection = document.getElementById(`${section}-section`);
        if (targetSection) {
            targetSection.classList.add('active');
            this.currentSection = section;
            
            // Update page title
            const titles = {
                'dashboard': 'Dashboard',
                'services': 'Service-Verwaltung',
                'service-pages': 'Seiteninhalte',
                'questionnaires': 'Fragebogen-Templates',
                'questions': 'Fragen verwalten',
                'submissions': 'Eingehende Anfragen',
                'submission-archive': 'Archiv',
                'media': 'Bilder verwalten',
                'emails': 'E-Mail-Verwaltung',
                'settings': 'Einstellungen',
                'users': 'Benutzer',
                'backup': 'Backup & Wartung',
                'statistics': 'Statistiken'
            };
            
            document.getElementById('pageTitle').textContent = titles[section] || 'Administration';
            
            // Load section data
            this.loadSectionData(section);
        }
    }

    toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        
        // Only allow collapse/expand on desktop
        if (window.innerWidth > 768) {
            sidebar.classList.toggle('collapsed');
            
            // Store preference
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
    }

    toggleMobileMenu() {
        const sidebar = document.getElementById('adminSidebar');
        
        // Toggle show class for mobile menu
        // The button is only visible on mobile via CSS, so we don't need to check window width
        sidebar?.classList.toggle('show');
    }

    checkMobileView() {
        const sidebar = document.getElementById('adminSidebar');
        
        if (window.innerWidth <= 768) {
            // Mobile: Remove collapsed, keep show if it was active
            sidebar.classList.remove('collapsed');
        } else {
            // Desktop: Remove mobile show, restore collapsed preference
            sidebar.classList.remove('show');
            const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            sidebar.classList.toggle('collapsed', collapsed);
        }
    }

    switchTab(tabId) {
        // Update active tab
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabId);
        });

        // Show selected tab content
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.toggle('active', pane.id === `${tabId}-tab`);
        });
    }

    // ========================================================================
    // Data Loading
    // ========================================================================

    async loadSectionData(section) {
        switch (section) {
            case 'dashboard':
                this.loadDashboardData();
                break;
            case 'services':
                this.loadServicesData();
                break;
            case 'service-pages':
                this.loadServicePagesData();
                break;
            case 'questionnaires':
                loadQuestionnaires();
                break;
            case 'questions':
                loadQuestions();
                break;
            case 'submissions':
                loadSubmissions();
                break;
            case 'submission-archive':
                initializeArchive();
                break;
            case 'media':
                this.loadMediaData();
                break;
            case 'emails':
                loadEmailTemplates();
                break;
            case 'settings':
                this.loadSettingsData();
                break;
        }
    }

    async loadDashboardData() {
        try {
            // Use data from PHP if available, otherwise make API call
            if (window.adminData && window.adminData.stats) {
                this.updateDashboardStats(window.adminData.stats);
                this.updateRecentSubmissions(window.adminData.recentSubmissions);
                this.updateServiceChart(window.adminData.serviceStats);
                return;
            }
            
            const response = await fetch(`${this.apiBase}?action=dashboard`);
            const data = await response.json();

            if (data.success) {
                this.updateDashboardStats(data.stats);
                this.updateRecentSubmissions(data.recentSubmissions);
                this.updateServiceChart(data.serviceStats);
            }
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showToast('Fehler beim Laden der Dashboard-Daten', 'error');
        }
    }

    async refreshDashboardStats() {
        try {
            const response = await fetch(`${this.apiBase}?action=stats`);
            const data = await response.json();

            if (data.success) {
                this.updateDashboardStats(data.stats);
            }
        } catch (error) {
            console.error('Error refreshing stats:', error);
        }
    }

    updateDashboardStats(stats) {
        document.getElementById('totalSubmissions').textContent = stats.todaySubmissions || '0';
        document.getElementById('activeServices').textContent = stats.activeServices || '0';
        document.getElementById('monthlySubmissions').textContent = stats.monthlySubmissions || '0';
        document.getElementById('conversionRate').textContent = (stats.conversionRate || 0) + '%';
    }

    updateRecentSubmissions(submissions) {
        const container = document.getElementById('recentSubmissions');
        
        if (!submissions || submissions.length === 0) {
            container.innerHTML = '<div class="empty-state">Keine neuen Anfragen</div>';
            return;
        }

        const html = submissions.map(submission => `
            <div class="submission-item">
                <div class="submission-header">
                    <strong>${this.escapeHtml(submission.customer_name)}</strong>
                    <span class="submission-date">${this.formatDate(submission.submitted_at)}</span>
                </div>
                <div class="submission-service">${this.escapeHtml(submission.service_name)}</div>
                <div class="submission-actions">
                    <button class="btn btn-sm btn-outline" onclick="admin.viewSubmission('${submission.id}')">
                        <i class="fas fa-eye"></i> Anzeigen
                    </button>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    updateServiceChart(serviceStats) {
        const container = document.getElementById('serviceChart');
        
        if (!serviceStats || serviceStats.length === 0) {
            container.innerHTML = '<div class="empty-state">Keine Daten verfügbar</div>';
            return;
        }

        // Simple chart implementation
        const maxValue = Math.max(...serviceStats.map(s => s.submissions));
        
        const html = serviceStats.map(service => `
            <div class="chart-item">
                <div class="chart-label">${this.escapeHtml(service.name)}</div>
                <div class="chart-bar">
                    <div class="chart-fill" style="width: ${(service.submissions / maxValue * 100)}%"></div>
                    <span class="chart-value">${service.submissions}</span>
                </div>
            </div>
        `).join('');

        container.innerHTML = `<div class="simple-chart">${html}</div>`;
    }

    async loadServicesData() {
        try {
            const response = await fetch(`${this.apiBase}?action=services`);
            const data = await response.json();

            if (data.success) {
                this.updateServicesTable(data.services);
            }
        } catch (error) {
            console.error('Error loading services data:', error);
            this.showToast('Fehler beim Laden der Services', 'error');
        }
    }

    updateServicesTable(services) {
        const tbody = document.getElementById('servicesTableBody');
        
        if (!services || services.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">Keine Services gefunden</td></tr>';
            return;
        }

        const html = services.map(service => `
            <tr>
                <td>
                    <div class="service-info">
                        <i class="${service.icon || 'fas fa-cog'}" style="color: ${service.color}; margin-right: 8px;"></i>
                        <strong>${this.escapeHtml(service.name)}</strong>
                    </div>
                </td>
                <td><code>${service.slug}</code></td>
                <td>
                    <span class="status-badge ${service.is_active ? 'active' : 'inactive'}">
                        ${service.is_active ? 'Aktiv' : 'Inaktiv'}
                    </span>
                </td>
                <td>${service.sort_order}</td>
                <td>${service.submission_count || 0}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline" onclick="admin.editService(${service.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="admin.deleteService(${service.id})" title="Löschen">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        tbody.innerHTML = html;
    }

    async loadServicePagesData() {
        try {
            // Use data from PHP if available, otherwise make API call
            if (window.adminData && window.adminData.services) {
                this.updateServicePageSelect(window.adminData.services);
                return;
            }
            
            const response = await fetch(`${this.apiBase}?action=service-list`);
            const data = await response.json();

            if (data.success) {
                this.updateServicePageSelect(data.services);
            }
        } catch (error) {
            console.error('Error loading service pages data:', error);
            this.showToast('Fehler beim Laden der Service-Daten', 'error');
        }
    }

    updateServicePageSelect(services) {
        const select = document.getElementById('servicePageSelect');
        if (!select) return;
        
        // Ensure services is an array
        if (!Array.isArray(services)) {
            console.error('Services is not an array:', services);
            return;
        }
        
        const options = services.map(service => 
            `<option value="${service.slug}">${this.escapeHtml(service.name)}</option>`
        ).join('');
        
        select.innerHTML = '<option value="">Service auswählen...</option>' + options;
    }

    async loadMediaData() {
        try {
            const response = await fetch(`${this.apiBase}?action=images`);
            const data = await response.json();

            if (data.success) {
                this.updateMediaGallery(data.images);
            } else {
                console.error('API Error:', data.error);
                this.showToast('Fehler beim Laden der Bilder: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Error loading media data:', error);
            this.showToast('Fehler beim Laden der Bilder', 'error');
        }
    }

    updateMediaGallery(images) {
        const gallery = document.getElementById('mediaGallery');
        
        if (!images || images.length === 0) {
            gallery.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-images"></i>
                    <h3>Keine Bilder gefunden</h3>
                    <p>Laden Sie Bilder hoch, um sie hier zu sehen.</p>
                    <button class="btn btn-primary" onclick="openModal('uploadModal')">
                        <i class="fas fa-upload"></i>
                        Erste Bilder hochladen
                    </button>
                </div>
            `;
            return;
        }

        const html = images.map(image => `
            <div class="media-item" data-image-id="${image.id}">
                <div class="media-preview" style="background-image: url('${image.path}')"></div>
                <div class="media-info">
                    <div class="media-name">${this.escapeHtml(image.name)}</div>
                    <div class="media-details">
                        ${image.size_formatted || 'Unbekannte Größe'} • 
                        ${image.dimensions || 'Unbekannte Auflösung'}
                    </div>
                    <div class="media-date">
                        ${new Date(image.created_at).toLocaleDateString('de-DE')}
                    </div>
                </div>
                <div class="media-actions">
                    <button class="btn btn-sm btn-outline" onclick="copyImagePath('${image.path}')" title="Pfad kopieren">
                        <i class="fas fa-copy"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteImage(${image.id})" title="Löschen">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');

        gallery.innerHTML = html;
    }

    // ========================================================================
    // Service Management
    // ========================================================================

    openServiceModal(serviceId = null) {
        const modal = document.getElementById('serviceModal');
        const title = document.getElementById('serviceModalTitle');
        const form = document.getElementById('serviceForm');

        if (serviceId) {
            title.textContent = 'Service bearbeiten';
            // Load images first, then load service data to ensure dropdown is populated
            this.loadIconOptions().then(() => {
                this.loadServiceData(serviceId);
            });
        } else {
            title.textContent = 'Service hinzufügen';
            form.reset();
            document.getElementById('serviceId').value = '';
            // Reset pricing data
            this.clearPricingData();
            // Load images for new service
            this.loadIconOptions();
        }

        this.showModal('serviceModal');
    }

    async loadIconOptions() {
        try {
            const response = await fetch(`${this.apiBase}?action=images`);
            const data = await response.json();

            const iconSelect = document.getElementById('serviceIcon');
            if (iconSelect && data.success && data.images) {
                // Clear existing options except the first one
                iconSelect.innerHTML = '<option value="">-- Bild auswählen --</option>';
                
                // Sort images alphabetically by name
                const sortedImages = data.images.sort((a, b) => a.name.localeCompare(b.name));
                
                // Add image options
                sortedImages.forEach(image => {
                    const option = document.createElement('option');
                    option.value = image.name;
                    option.textContent = image.name;
                    iconSelect.appendChild(option);
                });
                
                return true; // Signal successful loading
            }
            return false;
        } catch (error) {
            console.error('Error loading icon options:', error);
            return false;
        }
    }

    async loadServiceData(serviceId) {
        try {
            const response = await fetch(`${this.apiBase}?action=service&id=${serviceId}`);
            const data = await response.json();
            if (data.success && data.service) {
                const service = data.service;
                document.getElementById('serviceId').value = service.id;
                document.getElementById('serviceName').value = service.name;
                document.getElementById('serviceSlug').value = service.slug;

                // Additional debug: Check if field exists and is visible
                const slugField = document.getElementById('serviceSlug');
                
                // Try to trigger a visual update
                if (slugField) {
                    slugField.dispatchEvent(new Event('input', { bubbles: true }));
                    slugField.dispatchEvent(new Event('change', { bubbles: true }));
                }
                document.getElementById('serviceTitle').value = service.title || '';
                document.getElementById('serviceDescription').value = service.description || '';
                document.getElementById('serviceIcon').value = service.icon || '';
                document.getElementById('serviceColor').value = service.color || '#007cba';
                document.getElementById('serviceSortOrder').value = service.sort_order || 0;
                document.getElementById('serviceActive').value = service.is_active ? '1' : '0';
                
                // Load pricing data
                this.loadPricingData(service.pricing_data);
            } else {
                console.error('❌ Failed to load service data:', data);
            }
        } catch (error) {
            console.error('Error loading service data:', error);
            this.showToast('Fehler beim Laden der Service-Daten', 'error');
        }
    }

    async saveService() {
        const form = document.getElementById('serviceForm');
        if (!form) {
            console.error('❌ Service form not found!');
            return;
        }

        // Debug: Check the hidden ID field specifically
        const serviceIdField = document.getElementById('serviceId');
        
        const formData = new FormData(form);
        formData.append('action', 'save-service');
        
        // Add pricing data
        const pricingData = this.getPricingData();
        formData.append('pricing_data', JSON.stringify(pricingData));
        formData.append('csrf_token', this.csrfToken || ''); // ✅ CSRF-Token hinzufügen

        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': this.csrfToken || ''
                }
            });
            
            const data = await response.json();

            if (data.success) {
                this.showToast('Service erfolgreich gespeichert', 'success');
                this.closeModal('serviceModal');
                this.loadServicesData();
            } else {
                console.error('❌ Server error:', data.error || data.message);
                this.showToast(data.error || data.message || 'Fehler beim Speichern', 'error');
            }
        } catch (error) {
            console.error('❌ Network/Parse error:', error);
            this.showToast('Fehler beim Speichern des Services', 'error');
        }
    }

    editService(serviceId) {
        this.openServiceModal(serviceId);
    }

    async deleteService(serviceId) {
        if (!confirm('Möchten Sie diesen Service wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
            return;
        }

        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken || ''
                },
                body: JSON.stringify({
                    action: 'delete-service',
                    id: serviceId,
                    csrf_token: this.csrfToken || ''
                })
            });
            const data = await response.json();

            if (data.success) {
                this.showToast('Service erfolgreich gelöscht', 'success');
                this.loadServicesData();
            } else {
                this.showToast(data.message || 'Fehler beim Löschen', 'error');
            }
        } catch (error) {
            console.error('Error deleting service:', error);
            this.showToast('Fehler beim Löschen des Services', 'error');
        }
    }

    // ========================================================================
    // File Upload
    // ========================================================================

    initFileUpload() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        
        if (!uploadArea || !fileInput) return;

        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = Array.from(e.dataTransfer.files);
            this.handleFileSelection(files);
        });

        fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            this.handleFileSelection(files);
        });
    }

    handleFileSelection(files) {
        const validFiles = files.filter(file => {
            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            if (!validTypes.includes(file.type)) {
                this.showToast(`${file.name}: Ungültiger Dateityp`, 'error');
                return false;
            }
            
            if (file.size > maxSize) {
                this.showToast(`${file.name}: Datei zu groß (max. 10MB)`, 'error');
                return false;
            }
            
            return true;
        });

        if (validFiles.length > 0) {
            this.showUploadPreview(validFiles);
            document.getElementById('uploadBtn').disabled = false;
        }
    }

    showUploadPreview(files) {
        const preview = document.getElementById('uploadPreview');
        preview.innerHTML = '';

        files.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="${file.name}" class="preview-image">
                    <button class="preview-remove" onclick="admin.removePreview(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });

        this.selectedFiles = files;
    }

    removePreview(index) {
        this.selectedFiles.splice(index, 1);
        
        if (this.selectedFiles.length === 0) {
            document.getElementById('uploadPreview').innerHTML = '';
            document.getElementById('uploadBtn').disabled = true;
        } else {
            this.showUploadPreview(this.selectedFiles);
        }
    }

    async startUpload() {
        if (!this.selectedFiles || this.selectedFiles.length === 0) return;

        const progressContainer = document.getElementById('uploadProgress');
        const progressFill = progressContainer.querySelector('.progress-fill');
        const progressText = progressContainer.querySelector('.progress-text');
        
        progressContainer.style.display = 'block';

        const formData = new FormData();
        formData.append('action', 'upload-media');
        formData.append('csrf_token', this.csrfToken || ''); // ✅ CSRF-Token hinzufügen
        
        this.selectedFiles.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': this.csrfToken || ''
                }
            });

            // Simulate progress for better UX
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 10;
                progressFill.style.width = progress + '%';
                progressText.textContent = progress + '%';
                
                if (progress >= 100) {
                    clearInterval(progressInterval);
                }
            }, 100);

            const data = await response.json();

            if (data.success) {
                this.showToast(`${data.uploadedCount} Datei(en) erfolgreich hochgeladen`, 'success');
                this.closeModal('uploadModal');
                this.loadMediaData();
            } else {
                this.showToast(data.message || 'Fehler beim Hochladen', 'error');
            }
        } catch (error) {
            console.error('Error uploading files:', error);
            this.showToast('Fehler beim Hochladen der Dateien', 'error');
        } finally {
            progressContainer.style.display = 'none';
            progressFill.style.width = '0%';
            progressText.textContent = '0%';
        }
    }

    openUploadModal() {
        document.getElementById('uploadPreview').innerHTML = '';
        document.getElementById('uploadBtn').disabled = true;
        this.selectedFiles = [];
        this.showModal('uploadModal');
    }

    // ========================================================================
    // Modal Management
    // ========================================================================

    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
        }
    }

    // ========================================================================
    // Toast Notifications
    // ========================================================================

    showToast(message, type = 'info', title = '') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };

        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="${icons[type]}"></i>
            </div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${this.escapeHtml(title)}</div>` : ''}
                <div class="toast-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="toast-close">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(toast);

        // Show toast
        setTimeout(() => toast.classList.add('show'), 100);

        // Auto-remove after 5 seconds
        const autoRemove = setTimeout(() => {
            this.removeToast(toast);
        }, 5000);

        // Manual close
        toast.querySelector('.toast-close').addEventListener('click', () => {
            clearTimeout(autoRemove);
            this.removeToast(toast);
        });
    }

    removeToast(toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }

    // ========================================================================
    // Utility Functions
    // ========================================================================

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    formatFileSize(bytes) {
        const sizes = ['B', 'KB', 'MB', 'GB'];
        if (bytes === 0) return '0 B';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
    }

    // ========================================================================
    // Global Functions (for onclick handlers)
    // ========================================================================

    refreshDashboard() {
        this.loadDashboardData();
        this.showToast('Dashboard aktualisiert', 'success');
    }

    viewSubmission(submissionId) {
        // Navigate to submissions section with the specific submission ID
        window.location.hash = `#submissions?id=${submissionId}`;
        
        // Switch to submissions section
        this.switchSection('submissions');
        
        // Wait for section to load, then highlight/scroll to the submission
        setTimeout(() => {
            const submissionElement = document.querySelector(`[data-submission-id="${submissionId}"]`);
            if (submissionElement) {
                submissionElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                submissionElement.classList.add('highlight');
                
                // Remove highlight after animation
                setTimeout(() => {
                    submissionElement.classList.remove('highlight');
                }, 2000);
            }
        }, 300);
    }

    // ========================================================================
    // Media Management Functions
    // ========================================================================
    filterMedia() {
        const filter = document.getElementById('mediaFilter').value;
        this.currentMediaFilter = filter;
        this.applyMediaFilters();
    }

    searchMedia() {
        const query = document.getElementById('mediaSearch').value;
        this.currentMediaSearch = query;
        this.applyMediaFilters();
    }

    applyMediaFilters() {
        const gallery = document.getElementById('mediaGallery');
        const mediaItems = gallery.querySelectorAll('.media-item');
        
        const searchQuery = (this.currentMediaSearch || '').toLowerCase().trim();
        const filterType = this.currentMediaFilter || 'all';
        
        let visibleCount = 0;
        
        mediaItems.forEach(item => {
            const imageName = item.querySelector('.media-name')?.textContent.toLowerCase() || '';
            const imageDetails = item.querySelector('.media-details')?.textContent.toLowerCase() || '';
            const imageDate = item.querySelector('.media-date')?.textContent.toLowerCase() || '';
            
            // Search filter
            let matchesSearch = true;
            if (searchQuery) {
                matchesSearch = imageName.includes(searchQuery) || 
                               imageDetails.includes(searchQuery) ||
                               imageDate.includes(searchQuery);
            }
            
            // Type filter (based on file extension or size)
            let matchesFilter = true;
            if (filterType !== 'all') {
                const fileSize = imageDetails.match(/[\d.]+\s*(KB|MB|GB)/i);
                const sizeValue = fileSize ? parseFloat(fileSize[0]) : 0;
                const sizeUnit = fileSize ? fileSize[0].toUpperCase().slice(-2) : '';
                
                switch (filterType) {
                    case 'images':
                        // All items are images by default
                        matchesFilter = true;
                        break;
                    case 'large':
                        // Images larger than 1MB
                        matchesFilter = (sizeUnit === 'MB' && sizeValue > 1) || sizeUnit === 'GB';
                        break;
                    case 'small':
                        // Images smaller than 500KB
                        matchesFilter = sizeUnit === 'KB' || (sizeUnit === 'MB' && sizeValue < 0.5);
                        break;
                    case 'recent':
                        // Images from last 7 days
                        const dateText = item.querySelector('.media-date')?.textContent || '';
                        const imageDate = this.parseGermanDate(dateText);
                        if (imageDate) {
                            const daysDiff = (Date.now() - imageDate.getTime()) / (1000 * 60 * 60 * 24);
                            matchesFilter = daysDiff <= 7;
                        } else {
                            matchesFilter = false;
                        }
                        break;
                }
            }
            
            // Show/hide item based on filters
            const shouldShow = matchesSearch && matchesFilter;
            item.style.display = shouldShow ? 'block' : 'none';
            
            if (shouldShow) {
                visibleCount++;
            }
        });
        
        // Update results count or show empty state
        this.updateMediaFilterResults(visibleCount, mediaItems.length);
    }

    updateMediaFilterResults(visibleCount, totalCount) {
        const gallery = document.getElementById('mediaGallery');
        
        // Remove existing result info
        const existingInfo = gallery.querySelector('.filter-results-info');
        if (existingInfo) {
            existingInfo.remove();
        }
        
        if (visibleCount === 0 && totalCount > 0) {
            // Show "no results" message
            const noResults = document.createElement('div');
            noResults.className = 'filter-results-info empty-state';
            noResults.innerHTML = `
                <i class="fas fa-search"></i>
                <h3>Keine Bilder gefunden</h3>
                <p>Ihre Suche ergab keine Treffer. Versuchen Sie andere Suchbegriffe oder Filter.</p>
                <button class="btn btn-secondary" onclick="admin.clearMediaFilters()">
                    <i class="fas fa-times"></i> Filter zurücksetzen
                </button>
            `;
            gallery.insertBefore(noResults, gallery.firstChild);
        } else if (visibleCount < totalCount) {
            // Show results count
            const resultsInfo = document.createElement('div');
            resultsInfo.className = 'filter-results-info';
            resultsInfo.innerHTML = `
                <div class="results-count">
                    <i class="fas fa-filter"></i>
                    Zeige ${visibleCount} von ${totalCount} Bildern
                    <button class="btn btn-sm btn-link" onclick="admin.clearMediaFilters()">
                        Zurücksetzen
                    </button>
                </div>
            `;
            gallery.insertBefore(resultsInfo, gallery.firstChild);
        }
    }

    clearMediaFilters() {
        // Reset search input
        const searchInput = document.getElementById('mediaSearch');
        if (searchInput) {
            searchInput.value = '';
        }
        
        // Reset filter select
        const filterSelect = document.getElementById('mediaFilter');
        if (filterSelect) {
            filterSelect.value = 'all';
        }
        
        // Clear current filters
        this.currentMediaSearch = '';
        this.currentMediaFilter = 'all';
        
        // Reapply (which will show all)
        this.applyMediaFilters();
    }

    parseGermanDate(dateString) {
        // Parse German date format (e.g., "07.10.2025")
        const match = dateString.match(/(\d{2})\.(\d{2})\.(\d{4})/);
        if (match) {
            const day = parseInt(match[1], 10);
            const month = parseInt(match[2], 10) - 1; // Month is 0-indexed
            const year = parseInt(match[3], 10);
            return new Date(year, month, day);
        }
        return null;
    }

    setMediaView(view) {
        const gallery = document.getElementById('mediaGallery');
        gallery.className = `media-gallery media-${view}`;
        
        // Update active button
        document.querySelectorAll('.media-view-toggle .btn-icon').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.closest('.btn-icon').classList.add('active');
    }

    async uploadImages() {
        const fileInput = document.getElementById('fileInput');
        const files = fileInput.files;
        
        if (!files || files.length === 0) {
            this.showToast('Bitte wählen Sie Dateien zum Hochladen aus', 'warning');
            return;
        }

        const formData = new FormData();
        for (let i = 0; i < files.length; i++) {
            formData.append('images[]', files[i]);
        }
        formData.append('csrf_token', this.csrfToken || ''); // ✅ CSRF-Token hinzufügen

        try {
            const response = await fetch(`${this.apiBase}?action=upload-images`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': this.csrfToken || ''
                }
            });

            const data = await response.json();

            if (data.success) {
                this.showToast(`${data.uploaded} Bild(er) erfolgreich hochgeladen`, 'success');
                if (data.errors && data.errors.length > 0) {
                    this.showToast(`Fehler bei ${data.errors.length} Datei(en): ${data.errors[0]}`, 'warning');
                }
                this.loadMediaData(); // Refresh gallery
                closeModal('uploadModal');
                fileInput.value = ''; // Clear input
                document.getElementById('uploadPreview').innerHTML = '';
                document.getElementById('uploadBtn').disabled = true;
            } else {
                this.showToast('Fehler beim Hochladen: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Error uploading images:', error);
            this.showToast('Fehler beim Hochladen der Bilder', 'error');
        }
    }

    async deleteImageById(imageId) {
        if (!confirm('Möchten Sie dieses Bild wirklich löschen?')) {
            return;
        }

        try {
            const response = await fetch(`${this.apiBase}?action=delete-image&id=${imageId}`, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                this.showToast('Bild erfolgreich gelöscht', 'success');
                this.loadMediaData(); // Refresh gallery
            } else {
                this.showToast('Fehler beim Löschen: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Error deleting image:', error);
            this.showToast('Fehler beim Löschen des Bildes', 'error');
        }
    }

    copyImagePath(path) {
        navigator.clipboard.writeText(path).then(() => {
            this.showToast('Bildpfad in Zwischenablage kopiert', 'success');
        }).catch(err => {
            console.error('Error copying to clipboard:', err);
            this.showToast('Fehler beim Kopieren', 'error');
        });
    }

    // File upload drag & drop functionality
    initializeImageUpload() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');

        if (uploadArea && fileInput) {
            // Drag & Drop handlers
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('drag-over');
            });

            uploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    this.updateUploadPreview();
                }
            });

            uploadArea.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', () => {
                this.updateUploadPreview();
            });
        }
    }

    updateUploadPreview() {
        const fileInput = document.getElementById('fileInput');
        const preview = document.getElementById('uploadPreview');
        const uploadBtn = document.getElementById('uploadBtn');
        
        if (!fileInput || !preview) return;

        const files = Array.from(fileInput.files);
        
        if (files.length === 0) {
            preview.innerHTML = '';
            uploadBtn.disabled = true;
            return;
        }

        uploadBtn.disabled = false;

        const html = files.map((file, index) => `
            <div class="file-item">
                <i class="fas fa-image"></i>
                <span class="file-name">${this.escapeHtml(file.name)}</span>
                <span class="file-size">${this.formatFileSize(file.size)}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="admin.removeUploadFile(${index})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');

        preview.innerHTML = html;
    }

    removeUploadFile(index) {
        const fileInput = document.getElementById('fileInput');
        if (!fileInput) return;

        const dt = new DataTransfer();
        const files = Array.from(fileInput.files);
        
        files.forEach((file, i) => {
            if (i !== index) {
                dt.items.add(file);
            }
        });

        fileInput.files = dt.files;
        this.updateUploadPreview();
    }

    async loadServicePageContent() {
        const serviceSlug = document.getElementById('servicePageSelect').value;
        
        if (!serviceSlug) {
            // If no service selected, show empty state
            document.getElementById('servicePageContent').style.display = 'block';
            document.getElementById('serviceContentForm').style.display = 'none';
            return;
        }
        
        // Show content form and load data
        document.getElementById('servicePageContent').style.display = 'none';
        document.getElementById('serviceContentForm').style.display = 'block';
        
        // Load service data via AJAX using the new API endpoint
        try {
            const response = await fetch(`api/admin.php?action=service-page-content&slug=${serviceSlug}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.populateNewServiceForm(data.service, data.content);
            } else {
                console.error('API Error:', data.error || 'Unknown error');
                this.showToast('Fehler beim Laden der Service-Daten: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        } catch (error) {
            console.error('Error loading service content:', error);
            this.showToast('Fehler beim Laden der Service-Inhalte.', 'error');
        }
    }

    populateNewServiceForm(service, content) {
        
        // Set service data
        document.getElementById('servicePageId').value = service.id;
        document.getElementById('serviceSlug').value = service.slug;
        
        // Populate form fields
        document.getElementById('metaTitle').value = content?.meta_title || service.title || '';
        document.getElementById('metaDescription').value = content?.meta_description || service.description || '';
        document.getElementById('metaKeywords').value = content?.meta_keywords || '';
        
        document.getElementById('heroTitle').value = content?.hero_title || service.title || '';
        document.getElementById('heroSubtitle').value = content?.hero_subtitle || service.description || '';
        document.getElementById('heroCtaText').value = content?.hero_cta_text || 'Jetzt kostenlos anfragen';
        
        document.getElementById('introTitle').value = content?.intro_title || `Ihr zuverlässiger Partner für ${service.name}`;
        document.getElementById('introContent').value = content?.intro_content || service.description || '';
        
        document.getElementById('featuresTitle').value = content?.features_title || `Unsere ${service.name}-Leistungen`;
        document.getElementById('featuresSubtitle').value = content?.features_subtitle || 'Alles aus einer Hand für Ihren perfekten Service';
        
        document.getElementById('processTitle').value = content?.process_title || `So läuft Ihr ${service.name} ab`;
        document.getElementById('processSubtitle').value = content?.process_subtitle || 'In einfachen Schritten zu Ihrem Ziel';
        
        document.getElementById('pricingTitle').value = content?.pricing_title || `${service.name}-Preise`;
        document.getElementById('pricingSubtitle').value = content?.pricing_subtitle || 'Transparente Preisgestaltung ohne versteckte Kosten';
        
        document.getElementById('faqTitle').value = content?.faq_title || 'Häufige Fragen';
        document.getElementById('faqContent').value = content?.faq_content || '';
        
        // Load features if the function exists (defined in PHP)
        if (typeof loadFeatures === 'function') {
            loadFeatures(content?.features_content);
        }
        
        // Load process steps if the function exists (defined in PHP)  
        if (typeof loadProcessSteps === 'function') {
            loadProcessSteps(content?.process_content);
        }        
    }

    async saveServicePageContent() {
        const form = document.getElementById('servicePageForm');
        const formData = new FormData(form);
        const serviceSlug = document.getElementById('servicePageSelect').value;
        
        formData.append('action', 'save-service-page-content');
        formData.append('service_slug', serviceSlug);
        formData.append('csrf_token', this.csrfToken || ''); // ✅ CSRF-Token hinzufügen

        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': this.csrfToken || ''
                }
            });
            const data = await response.json();

            if (data.success) {
                this.showToast('Seiteninhalte erfolgreich gespeichert', 'success');
            } else {
                this.showToast(data.message || 'Fehler beim Speichern', 'error');
            }
        } catch (error) {
            console.error('Error saving service page content:', error);
            this.showToast('Fehler beim Speichern der Seiteninhalte', 'error');
        }
    }

    loadSettingsData() {
        // Settings are loaded directly from the HTML
        // No API call needed as settings are rendered server-side
        console.log('Settings section loaded');
    }

    async saveSettings() {
        // Settings save logic
        this.showToast('Einstellungen gespeichert', 'success');
    }

    // ========================================================================
    // Utility Methods
    // ========================================================================

    makeRequest(action, params, callback) {
        const isGet = ['email-templates', 'email-template', 'services', 'service', 'dashboard', 'stats'].includes(action);
        
        if (isGet) {
            // GET request
            const queryParams = new URLSearchParams(params);
            queryParams.set('action', action);
            
            fetch(`${this.apiBase}?${queryParams.toString()}`, {
                credentials: 'same-origin', // ✅ Session-Cookies senden
                headers: {
                    'X-CSRF-Token': this.csrfToken || '' // ✅ CSRF-Token auch bei GET senden
                }
            })
                .then(response => {
                    // ✅ Prüfe auf 401 Unauthorized
                    if (response.status === 401) {
                        console.error('❌ Nicht authentifiziert - Weiterleitung zum Login');
                        window.location.href = '/admin/login.php';
                        return { success: false, error: 'Nicht authentifiziert' };
                    }
                    // ✅ Prüfe auf 403 Forbidden
                    if (response.status === 403) {
                        console.error('❌ Keine Berechtigung');
                        showNotification('Keine Berechtigung für diese Aktion', 'error');
                        return { success: false, error: 'Keine Berechtigung' };
                    }
                    return response.json();
                })
                .then(callback)
                .catch(error => {
                    console.error('API Error:', error);
                    callback({ success: false, error: 'Network error' });
                });
        } else {
            // POST request
            const formData = new FormData();
            formData.append('action', action);
            formData.append('csrf_token', this.csrfToken || ''); // ✅ CSRF-Token in POST
            
            for (const key in params) {
                formData.append(key, params[key]);
            }
            
            fetch(this.apiBase, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin', // ✅ Session-Cookies senden
                headers: {
                    'X-CSRF-Token': this.csrfToken || '' // ✅ CSRF-Token im Header
                }
            })
                .then(response => {
                    // ✅ Prüfe auf 401 Unauthorized
                    if (response.status === 401) {
                        console.error('❌ Nicht authentifiziert - Weiterleitung zum Login');
                        window.location.href = '/admin/login.php';
                        return { success: false, error: 'Nicht authentifiziert' };
                    }
                    // ✅ Prüfe auf 403 Forbidden
                    if (response.status === 403) {
                        console.error('❌ Keine Berechtigung');
                        showNotification('Keine Berechtigung für diese Aktion', 'error');
                        return { success: false, error: 'Keine Berechtigung' };
                    }
                    // ✅ Prüfe auf 429 Rate Limit
                    if (response.status === 429) {
                        console.error('⚠️ Zu viele Anfragen');
                        showNotification('Zu viele Anfragen. Bitte warten Sie einen Moment.', 'warning');
                        return { success: false, error: 'Rate limit exceeded' };
                    }
                    return response.json();
                })
                .then(callback)
                .catch(error => {
                    console.error('API Error:', error);
                    callback({ success: false, error: 'Network error' });
                });
        }
    }

    // ========================================================================
    // Pricing Management
    // ========================================================================

    addPriceItem() {
        const pricingContainer = document.getElementById('pricingItems');
        
        const priceItem = document.createElement('div');
        priceItem.className = 'pricing-item';
        priceItem.innerHTML = `
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label>Bezeichnung</label>
                    <input type="text" class="price-description" placeholder="z.B. Umzug 1-Zimmer" required>
                </div>
                <div class="form-group">
                    <label>Preis</label>
                    <input type="number" class="price-value" step="0.01" min="0" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Einheit</label>
                    <input type="text" class="price-unit" placeholder="€" value="€">
                </div>
                <div class="form-group pricing-controls">
                    <button type="button" class="btn btn-sm btn-danger" onclick="admin.removePriceItem(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        pricingContainer.appendChild(priceItem);
    }

    removePriceItem(button) {
        const priceItem = button.closest('.pricing-item');
        if (priceItem) {
            priceItem.remove();
        }
    }

    getPricingData() {
        const pricingItems = document.querySelectorAll('.pricing-item');
        const pricingData = [];
        
        pricingItems.forEach((item, index) => {
            const descriptionEl = item.querySelector('.price-description');
            const valueEl = item.querySelector('.price-value');
            const unitEl = item.querySelector('.price-unit');
            
            // Check if elements exist before accessing their values
            if (descriptionEl && valueEl && unitEl) {
                const description = descriptionEl.value.trim();
                const value = parseFloat(valueEl.value) || 0;
                const unit = unitEl.value.trim() || '€';
                
                if (description && value > 0) {
                    pricingData.push({
                        description: description,
                        value: value,
                        unit: unit
                    });
                }
            } else {
                console.warn(`💰 Item ${index}: Missing elements`);
            }
        });
        
        return pricingData;
    }

    loadPricingData(pricingDataString) {
        const pricingContainer = document.getElementById('pricingItems');
        
        // Clear existing items
        pricingContainer.innerHTML = '';
        
        let pricingData = [];
        if (pricingDataString) {
            try {
                pricingData = typeof pricingDataString === 'string' 
                    ? JSON.parse(pricingDataString) 
                    : pricingDataString;
            } catch (e) {
                console.error('Error parsing pricing data:', e);
                pricingData = [];
            }
        }
        
        if (pricingData && pricingData.length > 0) {
            pricingData.forEach(price => {
                this.addPriceItem();
                const lastItem = pricingContainer.lastElementChild;
                
                // Safely access pricing elements with null checks
                const descriptionEl = lastItem.querySelector('.price-description');
                const valueEl = lastItem.querySelector('.price-value');
                const unitEl = lastItem.querySelector('.price-unit');
                
                if (descriptionEl) descriptionEl.value = price.description || '';
                if (valueEl) valueEl.value = price.value || 0;
                if (unitEl) unitEl.value = price.unit || '€';
            });
        } else {
            // Add one empty item by default
            this.addPriceItem();
        }
    }

    clearPricingData() {
        const pricingContainer = document.getElementById('pricingItems');
        pricingContainer.innerHTML = '';
        // Add one empty item
        this.addPriceItem();
    }
}

// ============================================================================
// Email Template Management Functions
// ============================================================================

function loadEmailTemplates() {
    
    admin.makeRequest('email-templates', {}, (response) => {
        if (response.success && response.templates) {
            updateEmailTemplatesList(response.templates);
        } else {
            console.error('Error loading email templates:', response.error);
        }
    });
}

function updateEmailTemplatesList(templates) {
    const container = document.getElementById('email-templates-container');
    
    if (!container) {
        console.error('Email templates container not found');
        return;
    }
    
    if (!templates || templates.length === 0) {
        container.innerHTML = '<div class="empty-state">Keine E-Mail-Templates gefunden</div>';
        return;
    }

    const html = templates.map(template => `
        <div class="template-card" data-template-id="${template.id}">
            <div class="template-header">
                <h4>${admin.escapeHtml(template.template_key || 'Unbenannt')}</h4>
                <div class="template-status ${template.is_active ? 'active' : 'inactive'}">
                    ${template.is_active ? 'Aktiv' : 'Inaktiv'}
                </div>
            </div>
            <div class="template-subject">
                ${admin.escapeHtml(template.subject)}
            </div>
            <div class="template-actions">
                <button class="btn btn-sm btn-outline" onclick="editEmailTemplate(${template.id})" title="Bearbeiten">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline" onclick="previewEmailTemplate(${template.id})" title="Vorschau">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteEmailTemplate(${template.id})" title="Löschen">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function createEmailTemplate() {
    document.getElementById('templateEditor').style.display = 'block';
    document.getElementById('editorTitle').textContent = 'Neues E-Mail-Template erstellen';
    
    // Clear form
    document.getElementById('templateKey').value = '';
    document.getElementById('templateSubject').value = '';
    document.getElementById('templateBodyHtml').value = '';
    document.getElementById('templateBodyText').value = '';
    document.getElementById('templateVariables').value = '[]';
    document.getElementById('templateActive').value = '1';
    
    // Remove template ID from form
    const form = document.querySelector('#templateEditor form');
    if (form) {
        const idInput = form.querySelector('input[name="id"]');
        if (idInput) idInput.remove();
    }
}

// Modal Management Functions
function showNewEmailTemplateModal() {
    
    const modal = document.getElementById('emailTemplateModal');
    const title = document.getElementById('emailTemplateModalTitle');
    const form = document.getElementById('emailTemplateForm');
    
    // Add null checks
    if (!modal) {
        console.error('Email template modal not found');
        alert('Modal nicht gefunden! Bitte laden Sie die Seite neu.');
        return;
    }
    if (!form) {
        console.error('Email template form not found');
        alert('Formular nicht gefunden! Bitte laden Sie die Seite neu.');
        return;
    }
    if (!title) {
        console.error('Email template modal title not found');
        return;
    }
    
    // Reset form
    form.reset();
    const emailTemplateId = document.getElementById('emailTemplateId');
    if (emailTemplateId) {
        emailTemplateId.value = '';
    }
    title.textContent = 'Neue E-Mail-Vorlage erstellen';
    
    // Show modal using the proper CSS classes for centering
    modal.style.display = 'flex'; // Use flex instead of block for centering
    modal.classList.add('show');
    
    // Remove any conflicting styles
    modal.style.visibility = '';
    modal.style.opacity = '';
    
    // Also try focus for accessibility
    if (document.getElementById('emailTemplateName')) {
        setTimeout(() => {
            document.getElementById('emailTemplateName').focus();
        }, 100);
    }
}

function closeEmailTemplateModal() {
    const modal = document.getElementById('emailTemplateModal');
    if (modal) {
        modal.classList.remove('show');
        // Wait for CSS transition to complete before hiding
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300); // Match the CSS transition duration
    } else {
        console.error('Email template modal not found for closing');
    }
}

function closeEmailPreviewModal() {
    const modal = document.getElementById('emailPreviewModal');
    if (modal) {
        modal.classList.remove('show');
        // Wait for CSS transition to complete before hiding
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300); // Match the CSS transition duration
    } else {
        console.error('Email preview modal not found for closing');
    }
}

function editEmailTemplate(id) {
    admin.makeRequest('email-template', { id: id }, (response) => {
        if (response.success && response.template) {
            const template = response.template;
            
            // Get all modal elements with null checks
            const modal = document.getElementById('emailTemplateModal');
            const title = document.getElementById('emailTemplateModalTitle');
            const form = document.getElementById('emailTemplateForm');
            const idField = document.getElementById('emailTemplateId');
            const nameField = document.getElementById('emailTemplateName');
            const subjectField = document.getElementById('emailTemplateSubject');
            const contentField = document.getElementById('emailTemplateContent');
            const activeField = document.getElementById('emailTemplateActive');
            
            // Check if all elements exist
            if (!modal) {
                console.error('Email template modal not found');
                this.showToast('Email template modal not found');
                return;
            }
            if (!form) {
                this.showToast('Email template form not found');
                alert('Formular nicht gefunden! Bitte laden Sie die Seite neu.');
                return;
            }
            
            // Fill modal form with template data
            if (idField) idField.value = template.id || '';
            if (nameField) nameField.value = template.template_key || '';
            if (subjectField) subjectField.value = template.subject || '';
            if (contentField) contentField.value = template.body_html || '';
            if (activeField) activeField.checked = template.is_active == 1;
            
            // Update modal title
            if (title) title.textContent = 'E-Mail-Vorlage bearbeiten';
            
            // Show modal using the same method as showNewEmailTemplateModal()
            modal.style.display = 'flex'; // Use flex for proper centering
            modal.classList.add('show');
            
            // Remove any conflicting styles
            modal.style.visibility = '';
            modal.style.opacity = '';
            
            // Focus on first input field
            if (nameField) {
                setTimeout(() => {
                    nameField.focus();
                }, 100);
            }
        } else {
            console.error('Failed to load template:', response.error);
            alert('Fehler beim Laden der Vorlage: ' + (response.error || 'Unbekannter Fehler'));
        }
    });
}

function saveEmailTemplate() {
    const formData = {
        id: document.getElementById('emailTemplateId').value || '',
        template_key: document.getElementById('emailTemplateName').value,
        subject: document.getElementById('emailTemplateSubject').value,
        body_html: document.getElementById('emailTemplateContent').value,
        body_text: '', // Default empty
        variables: '[]', // Default empty array
        is_active: document.getElementById('emailTemplateActive').checked ? 1 : 0
    };
    
    if (!formData.template_key || !formData.subject || !formData.body_html) {
        alert('Bitte füllen Sie alle erforderlichen Felder aus.');
        return;
    }
    
    admin.makeRequest('save-email-template', formData, (response) => {
        if (response.success) {
            alert('E-Mail-Template erfolgreich gespeichert!');
            closeEmailTemplateModal();
            loadEmailTemplates();
        } else {
            alert('Fehler beim Speichern: ' + (response.error || 'Unbekannter Fehler'));
        }
    });
}

function deleteEmailTemplate(id) {
    if (!confirm('Möchten Sie dieses E-Mail-Template wirklich löschen?')) {
        return;
    }
    
    admin.makeRequest('delete-email-template', { id: id }, (response) => {
        if (response.success) {
            alert('E-Mail-Template erfolgreich gelöscht!');
            loadEmailTemplates();
        } else {
            alert('Fehler beim Löschen: ' + (response.error || 'Unbekannter Fehler'));
        }
    });
}

function cancelEmailTemplateEdit() {
    document.getElementById('templateEditor').style.display = 'none';
    // Remove template ID from form
    const idInput = document.querySelector('#templateEditor input[name="id"]');
    if (idInput) idInput.remove();
}

function previewEmailTemplate() {
    const subject = document.getElementById('emailTemplateSubject').value;
    const content = document.getElementById('emailTemplateContent').value;
    
    if (!subject || !content) {
        alert('Bitte füllen Sie Betreff und Inhalt aus, bevor Sie eine Vorschau anzeigen.');
        return;
    }
    
    // Set preview content
    document.getElementById('previewSubject').textContent = subject;
    document.getElementById('previewContent').innerHTML = content;
    
    // Show preview modal with proper centering
    const previewModal = document.getElementById('emailPreviewModal');
    previewModal.style.display = 'flex';
    previewModal.classList.add('show');
}

function sendTestEmail() {
    const formData = {
        template_key: document.getElementById('emailTemplateName').value,
        subject: document.getElementById('emailTemplateSubject').value,
        body_html: document.getElementById('emailTemplateContent').value
    };
    
    if (!formData.subject || !formData.body_html) {
        alert('Bitte füllen Sie alle erforderlichen Felder aus.');
        return;
    }
    
    const testEmail = prompt('Bitte geben Sie die E-Mail-Adresse für die Test-E-Mail ein:');
    if (!testEmail) return;
    
    formData.test_email = testEmail;
    
    admin.makeRequest('send-test-email', formData, (response) => {
        if (response.success) {
            alert('Test-E-Mail erfolgreich versendet!');
        } else {
            alert('Fehler beim Versenden der Test-E-Mail: ' + (response.error || 'Unbekannter Fehler'));
        }
    });
}

function switchTemplateTab(tabName) {
    // Remove active class from all tabs
    document.querySelectorAll('.template-editor-tabs .tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.template-editor-tabs .tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    
    // Add active class to selected tab
    document.querySelector(`[onclick="switchTemplateTab('${tabName}')"]`).classList.add('active');
    document.getElementById(`${tabName}-tab`).classList.add('active');
}

function insertVariable(variable) {
    const activeTab = document.querySelector('.template-editor-tabs .tab-pane.active');
    if (activeTab) {
        const textarea = activeTab.querySelector('textarea');
        if (textarea) {
            const cursorPos = textarea.selectionStart;
            const textBefore = textarea.value.substring(0, cursorPos);
            const textAfter = textarea.value.substring(textarea.selectionEnd);
            textarea.value = textBefore + variable + textAfter;
            textarea.focus();
            textarea.setSelectionRange(cursorPos + variable.length, cursorPos + variable.length);
        }
    }
}

function filterEmailTemplates() {
    const filterValue = document.getElementById('templateTypeFilter').value;
    const templateCards = document.querySelectorAll('.template-card');
    
    templateCards.forEach(card => {
        const templateKey = card.querySelector('h4').textContent.toLowerCase();
        let show = true;
        
        if (filterValue === 'customer' && !templateKey.includes('customer') && !templateKey.includes('confirmation')) {
            show = false;
        } else if (filterValue === 'admin' && !templateKey.includes('admin') && !templateKey.includes('new_') && !templateKey.includes('assignment')) {
            show = false;
        } else if (filterValue === 'system' && !templateKey.includes('system') && !templateKey.includes('password')) {
            show = false;
        }
        
        card.style.display = show ? 'block' : 'none';
    });
}

function searchEmailTemplates() {
    const searchValue = document.getElementById('templateSearch').value.toLowerCase();
    const templateCards = document.querySelectorAll('.template-card');
    
    templateCards.forEach(card => {
        const templateKey = card.querySelector('h4').textContent.toLowerCase();
        const subject = card.querySelector('.template-subject').textContent.toLowerCase();
        const show = templateKey.includes(searchValue) || subject.includes(searchValue);
        card.style.display = show ? 'block' : 'none';
    });
}

function previewEmailTemplate(id) {
    // Vorschau-Modal öffnen
    showEmailPreview(id);
}

async function showEmailPreview(id) {
    try {
        // Template-Daten laden
        const response = await fetch(`api/admin.php?action=preview-email-template&id=${id}`);
        const data = await response.json();
        
        if (!data.success) {
            alert('Fehler beim Laden der Vorschau: ' + data.error);
            return;
        }
        
        // Vorschau-Modal erstellen und anzeigen
        createPreviewModal(data);
        
    } catch (error) {
        console.error('Fehler beim Laden der E-Mail-Vorschau:', error);
        alert('Fehler beim Laden der E-Mail-Vorschau');
    }
}

function createPreviewModal(templateData) {

    // Vorschau-Modal HTML erstellen
    const modalHtml = `
        <div id="emailPreviewModal" class="modal modal-large">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>E-Mail-Vorschau: ${templateData.template_key}</h3>
                    <button class="modal-close" onclick="closeEmailPreviewModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="email-preview-container">
                        <div class="preview-controls">
                            <button class="btn btn-secondary" id="showHtmlBtn" onclick="showHtmlPreview()">
                                <i class="fas fa-code"></i> HTML
                            </button>
                            <button class="btn btn-outline" id="showTextBtn" onclick="showTextPreview()">
                                <i class="fas fa-file-text"></i> Text
                            </button>
                            <button class="btn btn-primary" onclick="sendTestEmail('${templateData.template_key}')">
                                <i class="fas fa-paper-plane"></i> Test senden
                            </button>
                        </div>
                        
                        <div class="email-preview-content">
                            <div class="email-header">
                                <div class="email-subject">
                                    <strong>Betreff:</strong> ${templateData.subject}
                                </div>
                                <div class="email-meta">
                                    <span>Template: ${templateData.template_key}</span>
                                    <span>Vorschau mit Beispieldaten</span>
                                </div>
                            </div>
                            
                            <div class="email-body-container">
                                <div id="htmlPreview" class="email-body html-preview active">
                                    ${templateData.body_html}
                                </div>
                                <div id="textPreview" class="email-body text-preview">
                                    <pre>${templateData.body_text}</pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="preview-variables">
                            <h4>Verfügbare Variablen:</h4>
                            <div class="variables-list">
                                ${Object.keys(templateData.variables).map(key => 
                                    `<span class="variable-tag">${key}: ${templateData.variables[key]}</span>`
                                ).join('')}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeEmailPreviewModal()">Schließen</button>
                </div>
            </div>
        </div>
    `;
    
    // Modal zum DOM hinzufügen
    const existingModal = document.getElementById('emailPreviewModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Modal anzeigen
    const modal = document.getElementById('emailPreviewModal');
    
    if (modal) {
        // CSS-Klasse für Sichtbarkeit setzen
        modal.classList.add('show');
        
        // CSS Debug-Informationen
        const computedStyle = window.getComputedStyle(modal);
        
        // Body scroll blockieren
        document.body.style.overflow = 'hidden';
    } else {
        console.error('❌ Modal-Element NICHT gefunden im DOM!');
        alert('Fehler: Modal-Element konnte nicht erstellt werden');
    }
    
    // ESC-Key Handler
    document.addEventListener('keydown', handlePreviewModalEscape);
}

function showHtmlPreview() {
    document.getElementById('htmlPreview').classList.add('active');
    document.getElementById('textPreview').classList.remove('active');
    document.getElementById('showHtmlBtn').classList.add('btn-secondary');
    document.getElementById('showHtmlBtn').classList.remove('btn-outline');
    document.getElementById('showTextBtn').classList.add('btn-outline');
    document.getElementById('showTextBtn').classList.remove('btn-secondary');
}

function showTextPreview() {
    document.getElementById('textPreview').classList.add('active');
    document.getElementById('htmlPreview').classList.remove('active');
    document.getElementById('showTextBtn').classList.add('btn-secondary');
    document.getElementById('showTextBtn').classList.remove('btn-outline');
    document.getElementById('showHtmlBtn').classList.add('btn-outline');
    document.getElementById('showHtmlBtn').classList.remove('btn-secondary');
}

function closeEmailPreviewModal() {
    const modal = document.getElementById('emailPreviewModal');
    if (modal) {
        modal.remove();
    }
    
    // Body scroll wieder aktivieren
    document.body.style.overflow = '';
    
    // Event Listener entfernen
    document.removeEventListener('keydown', handlePreviewModalEscape);
}

function handlePreviewModalEscape(event) {
    if (event.key === 'Escape') {
        closeEmailPreviewModal();
    }
}

function sendTestEmail(templateKey) {
    const email = prompt('Test-E-Mail senden an:');
    if (email && email.trim()) {
        // Hier würde die Test-E-Mail-Funktionalität implementiert werden
        alert(`Test-E-Mail für Template "${templateKey}" wird an ${email} gesendet.\n(Funktionalität wird in einer späteren Version implementiert)`);
    }
}

function previewCurrentTemplate() { 
    // Aktuelle Template-Daten aus dem Editor sammeln
    const templateKey = document.getElementById('templateKey')?.value || 'preview';
    const subject = document.getElementById('templateSubject')?.value || 'Betreff';
    const bodyHtml = document.getElementById('templateBodyHtml')?.value || '';
    const bodyText = document.getElementById('templateBodyText')?.value || '';
    const variables = document.getElementById('templateVariables')?.value || '{}';
    
    try {
        // Variablen parsen
        let templateVars = {};
        if (variables.trim()) {
            templateVars = JSON.parse(variables);
        }
        
        // Live-Vorschau mit aktuellen Editor-Daten erstellen
        const previewData = {
            template_key: templateKey,
            subject: subject,
            body_html: bodyHtml,
            body_text: bodyText,
            variables: generateSampleVariables(templateVars)
        };
        
        createLivePreview(previewData);
        
    } catch (error) {
        console.error('Fehler beim Erstellen der Live-Vorschau:', error);
        alert('Fehler beim Erstellen der Vorschau. Bitte überprüfen Sie die Variablen-JSON.');
    }
}

function generateSampleVariables(templateVars) {
    // Basis-Beispieldaten
    const baseData = {
        customer_name: 'Max Mustermann',
        customer_email: 'max@example.com',
        customer_phone: '+49 123 456789',
        service_name: 'Umzugsservice',
        reference: 'REQ-2025-DEMO',
        priority_text: 'Normal',
        status_text: 'In Bearbeitung',
        submitted_at: new Date().toLocaleDateString('de-DE') + ' ' + new Date().toLocaleTimeString('de-DE'),
        company_name: 'DS-Allroundservice',
        company_email: 'info@ds-allroundservice.de',
        company_phone: '+49 123 456789',
        admin_url: window.location.origin + '/admin',
        website_url: window.location.origin
    };
    
    // Template-spezifische Variablen mit Beispielwerten füllen
    Object.keys(templateVars).forEach(key => {
        if (!baseData[key]) {
            baseData[key] = `[${key}]`; // Platzhalter für unbekannte Variablen
        }
    });
    
    return baseData;
}

function createLivePreview(templateData) {

    // Variablen in Template-Strings ersetzen
    const subject = processTemplateString(templateData.subject, templateData.variables);
    const bodyHtml = processConditionals(processTemplateString(templateData.body_html, templateData.variables), templateData.variables);
    const bodyText = processConditionals(processTemplateString(templateData.body_text, templateData.variables), templateData.variables);
    
    // Vorschau-Modal mit verarbeiteten Daten erstellen
    const processedData = {
        ...templateData,
        subject: subject,
        body_html: bodyHtml,
        body_text: bodyText
    };
    
    createPreviewModal(processedData);
}

function processTemplateString(template, variables) {
    if (!template) return '';
    
    return template.replace(/\{\{([^}]+)\}\}/g, function(match, key) {
        const trimmedKey = key.trim();
        return variables[trimmedKey] !== undefined ? variables[trimmedKey] : match;
    });
}

function processConditionals(template, variables) {
    if (!template) return '';
    
    return template.replace(/\{\{#([^}]+)\}\}(.*?)\{\{\/\1\}\}/gs, function(match, key, content) {
        const trimmedKey = key.trim();
        if (variables[trimmedKey] && variables[trimmedKey] !== '') {
            return processTemplateString(content, variables);
        }
        return '';
    });
}

function testEmailTemplates() {
    // Implementation für Test-E-Mails
    alert('Test-E-Mail-Funktion wird in einer zukünftigen Version verfügbar sein.');
}

// Test email sending with real data
function sendTestEmail() {
    const recipientEmail = prompt('Geben Sie eine E-Mail-Adresse für den Test ein:');
    if (!recipientEmail) return;
    
    const templateKey = prompt('Template-Schlüssel (z.B. auto_receipt_confirmation):', 'auto_receipt_confirmation');
    if (!templateKey) return;
    
    // Test variables
    const testVariables = {
        customer_name: 'Max Mustermann',
        service_name: 'Haushaltsauflösung Premium',
        service_type: 'Haushaltsauflösung',
        reference: 'TEST-' + Date.now(),
        submitted_at: new Date().toLocaleString('de-DE'),
        appointment_date: 'Nach Vereinbarung'
    };
    
    fetch('/api/admin.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': admin.csrfToken || ''
        },
        body: JSON.stringify({
            action: 'send_test_email',
            template_key: templateKey,
            recipient_email: recipientEmail,
            variables: testVariables,
            csrf_token: admin.csrfToken || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Test-E-Mail erfolgreich versendet!', 'success');
        } else {
            showNotification('Fehler beim Versenden: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        console.error('Error sending test email:', error);
        showNotification('Fehler beim Versenden der Test-E-Mail', 'error');
    });
}

// Initialize admin panel
const admin = new AdminPanel();

// Global functions for onclick handlers
window.refreshDashboard = () => admin.refreshDashboard();
window.openServiceModal = (id) => admin.openServiceModal(id);
window.editService = (id) => admin.editService(id);
window.deleteService = (id) => admin.deleteService(id);
// Modal functions
window.openModal = (id) => admin.openModal(id);
window.closeModal = (id) => admin.closeModal(id);
window.saveService = () => admin.saveService();
window.openUploadModal = () => admin.openUploadModal();
window.startUpload = () => admin.startUpload();
window.loadServicePageContent = () => admin.loadServicePageContent();
window.filterMedia = () => admin.filterMedia();
window.searchMedia = () => admin.searchMedia();
window.setMediaView = (view) => admin.setMediaView(view);
window.saveSettings = () => admin.saveSettings();

// Email template functions
window.loadEmailTemplates = loadEmailTemplates;
window.createEmailTemplate = createEmailTemplate;
window.editEmailTemplate = editEmailTemplate;
window.saveEmailTemplate = saveEmailTemplate;
window.deleteEmailTemplate = deleteEmailTemplate;
window.cancelEmailTemplateEdit = cancelEmailTemplateEdit;
window.switchTemplateTab = switchTemplateTab;
window.insertVariable = insertVariable;
window.filterEmailTemplates = filterEmailTemplates;
window.searchEmailTemplates = searchEmailTemplates;
window.previewEmailTemplate = previewEmailTemplate;
window.previewCurrentTemplate = previewCurrentTemplate;
window.testEmailTemplates = testEmailTemplates;

// Email preview functions
window.showEmailPreview = showEmailPreview;
window.createPreviewModal = createPreviewModal;
window.showHtmlPreview = showHtmlPreview;
window.showTextPreview = showTextPreview;
window.closeEmailPreviewModal = closeEmailPreviewModal;
window.sendTestEmail = sendTestEmail;

// ============================================================================
// Questionnaires Management
// ============================================================================

// Global variables for questionnaires
let questionnairesData = [];
let questionsData = [];
let submissionsData = [];
let currentQuestionnaireId = null;
let currentQuestionId = null;
let currentSubmissionId = null;
let currentModalSubmissionId = null; // Separate variable for modal context

// Questionnaires Functions
async function loadQuestionnaires() {
    
    try {
        const response = await fetch('api/admin.php?action=questionnaires');
                
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const text = await response.text();
        if (!text.trim()) {
            throw new Error('Empty response from server');
        }
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('❌ JSON Parse Error:', parseError);
            console.error('❌ Raw response:', text);
            showNotification('Server-Antwort ist kein gültiges JSON', 'error');
            return;
        }
        
        if (data.success) {            
            questionnairesData = data.questionnaires || [];
            displayQuestionnaires(questionnairesData);
            
            // Check for questionnaire ID in URL and open for editing
            const { query } = parseUrlHashAndQuery();
            if (query.id) {
                const questionnaireId = parseInt(query.id);
                
                // Wait a moment for DOM to be ready
                setTimeout(() => {
                    editQuestionnaire(questionnaireId);
                }, 100);
            }
        } else {
            console.error('❌ API returned error:', data.error);
            showNotification('Fehler beim Laden der Fragebogen: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Network/System error in loadQuestionnaires:', error);
        console.error('❌ Error stack:', error.stack);
        showNotification('Netzwerkfehler beim Laden der Fragebogen: ' + error.message, 'error');
    }
}

function displayQuestionnaires(questionnaires) {
    const tbody = document.getElementById('questionnairesTableBody');    
    if (!tbody) {
        console.error('❌ questionnairesTableBody element not found in DOM');
        return;
    }
    
    if (questionnaires.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-message">
                    <i class="fas fa-clipboard-list"></i>
                    <p>Keine Fragebogen-Templates gefunden</p>
                    <button class="btn btn-primary" onclick="showCreateQuestionnaireModal()">
                        Ersten Fragebogen erstellen
                    </button>
                </td>
            </tr>
        `;
        return;
    }
    
    const html = questionnaires.map((questionnaire, index) => {
        return `
        <tr data-questionnaire-id="${questionnaire.id}">
            <td data-label="Name">
                <strong>${escapeHtml(questionnaire.name || 'Unbenannt')}</strong>
                ${questionnaire.description ? `<br><small class="text-muted">${escapeHtml(questionnaire.description)}</small>` : ''}
            </td>
            <td data-label="Service"><span class="badge badge-info">${escapeHtml(questionnaire.service_name || 'N/A')}</span></td>
            <td data-label="Fragen">
                <span class="question-count">${questionnaire.question_count || 0}</span>
                <small> Fragen</small>
            </td>
            <td data-label="Status">
                <span class="badge badge-${getStatusBadgeClass(questionnaire.status)}">${getStatusText(questionnaire.status)}</span>
            </td>
            <td data-label="Erstellt">
                <small>${formatDate(questionnaire.created_at)}</small>
            </td>
            <td data-label="Aktionen">
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline" onclick="editQuestionnaire(${questionnaire.id})" title="Bearbeiten">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="previewQuestionnaire(${questionnaire.id})" title="Vorschau">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="duplicateQuestionnaire(${questionnaire.id})" title="Duplizieren">
                        <i class="fas fa-copy"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteQuestionnaire(${questionnaire.id})" title="Löschen">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        `;
    }).join('');
    
    tbody.innerHTML = html;
}

// Helper function for HTML escaping
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showCreateQuestionnaireModal() {
    currentQuestionnaireId = null;
    document.getElementById('questionnaireEditorTitle').textContent = 'Neuer Fragebogen';
    document.getElementById('questionnaireName').value = '';
    document.getElementById('questionnaireService').value = '';
    document.getElementById('questionnaireDescription').value = '';
    document.getElementById('questionnaireStatus').value = 'draft';
    
    // Load services for dropdown
    loadServicesForQuestionnaire();
    
    // Clear questions list
    document.getElementById('questionsList').innerHTML = `
        <div class="empty-message">
            <i class="fas fa-question-circle"></i>
            <p>Noch keine Fragen hinzugefügt</p>
            <button class="btn btn-primary" onclick="addQuestionToQuestionnaire()">
                Erste Frage hinzufügen
            </button>
        </div>
    `;
    
    // Show editor
    document.getElementById('questionnaireEditor').style.display = 'block';
    document.querySelector('#questionnaires-section .data-table-container').style.display = 'none';
}

function editQuestionnaire(id) {    
    const questionnaire = questionnairesData.find(q => q.id == id);
    
    if (!questionnaire) {
        console.error('❌ Questionnaire not found with ID:', id);
        showNotification('Fragebogen nicht gefunden', 'error');
        return;
    }
    
    currentQuestionnaireId = id;
    
    // Update URL to include questionnaire ID
    if (window.updateUrlHash) {
        const { query } = window.parseUrlHashAndQuery ? window.parseUrlHashAndQuery() : { query: {} };
        window.updateUrlHash('questionnaires', { ...query, id: id });
    }
    
    // Fill form fields
    const elements = {
        title: document.getElementById('questionnaireEditorTitle'),
        name: document.getElementById('questionnaireName'),
        service: document.getElementById('questionnaireService'),
        description: document.getElementById('questionnaireDescription'),
        status: document.getElementById('questionnaireStatus')
    };
    
    if (elements.title) elements.title.textContent = 'Fragebogen bearbeiten';
    if (elements.name) elements.name.value = questionnaire.name || '';
    if (elements.service) elements.service.value = questionnaire.service_id || questionnaire.service_slug || '';
    if (elements.description) elements.description.value = questionnaire.description || '';
    if (elements.status) elements.status.value = questionnaire.status || 'draft';
    
    // Load services and questions
    loadServicesForQuestionnaire();
    loadQuestionnaireQuestions(id);
    
    // Show editor and hide table
    const editor = document.getElementById('questionnaireEditor');
    const table = document.querySelector('#questionnaires-section .data-table-container');
    if (editor) {
        editor.style.display = 'block';
    }
    if (table) {
        table.style.display = 'none';
    }
}

async function loadServicesForQuestionnaire() {
    try {
        const response = await fetch('api/admin.php?action=service-list');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('questionnaireService');
            select.innerHTML = '<option value="">Service wählen...</option>' +
                data.services.map(service => 
                    `<option value="${service.id}">${service.name}</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Error loading services:', error);
    }
}

async function loadQuestionnaireQuestions(questionnaireId) {
    
    try {
        const response = await fetch(`api/admin.php?action=questionnaire-questions&id=${questionnaireId}`);
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', text);
            showNotification('Fehler beim Laden der Fragen: Ungültige API-Antwort', 'error');
            return;
        }
        
        if (data.success) {
            displayQuestionnaireQuestions(data.questions);
            
            // Also update drag & drop if available
            if (window.AdminDragDrop && window.AdminDragDrop.instance) {
                window.AdminDragDrop.instance.loadQuestions(data.questions);
            }
        } else {
            console.error('API error:', data.error);
            showNotification('Fehler beim Laden der Fragen: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Network error loading questions:', error);
        showNotification('Netzwerkfehler beim Laden der Fragen', 'error');
    }
}

function displayQuestionnaireQuestions(questions) {
    const container = document.getElementById('questionsList');
    
    // Check if the container exists and is visible
    if (!container) {
        console.warn('questionsList element not found');
        return;
    }
    
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
        return;
    }
    
    // Group questions by group_id if groups exist
    const grouped = questions.reduce((acc, question) => {
        const groupId = question.group_id || 'ungrouped';
        if (!acc[groupId]) {
            acc[groupId] = {
                name: question.group_name || 'Nicht gruppiert',
                questions: []
            };
        }
        acc[groupId].questions.push(question);
        return acc;
    }, {});
    
    let html = '';
    
    // Render each group
    Object.keys(grouped).forEach(groupId => {
        const group = grouped[groupId];
        
        if (groupId === 'ungrouped') {
            // Render ungrouped questions directly in the container
            html += group.questions.map((question, index) => renderQuestionItem(question, index)).join('');
        } else {
            // Render grouped questions (for future group support)
            html += `
                <div class="question-group" data-group-id="${groupId}">
                    <div class="group-title">${group.name}</div>
                    ${group.questions.map((question, index) => renderQuestionItem(question, index)).join('')}
                </div>
            `;
        }
    });
    
    container.innerHTML = html;
    
    // Initialize drag and drop if available
    if (window.AdminDragDrop && window.AdminDragDrop.instance) {
        window.AdminDragDrop.instance.updateSortable();
    }
}

function renderQuestionItem(question, index) {
    return `
        <div class="question-item" data-question-id="${question.id}">
            <div class="question-header">
                <div class="question-info">
                    <span class="question-number">${index + 1}.</span>
                    <span class="question-text">${question.question_text}</span>
                    <span class="question-type badge badge-secondary">${getQuestionTypeText(question.question_type)}</span>
                    ${question.is_required ? '<span class="badge badge-warning">Erforderlich</span>' : ''}
                </div>
                <div class="question-actions">
                    <button class="btn btn-sm btn-outline" onclick="moveQuestionUp(${question.id})" title="Nach oben">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="moveQuestionDown(${question.id})" title="Nach unten">
                        <i class="fas fa-chevron-down"></i>
                    </button>
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
    `;
}

async function saveQuestionnaire() {
    const name = document.getElementById('questionnaireName').value.trim();
    const serviceId = document.getElementById('questionnaireService').value;
    const description = document.getElementById('questionnaireDescription').value.trim();
    const status = document.getElementById('questionnaireStatus').value;
    
    if (!name) {
        showNotification('Bitte geben Sie einen Namen ein', 'error');
        return;
    }
    
    if (!serviceId) {
        showNotification('Bitte wählen Sie einen Service', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', currentQuestionnaireId ? 'update-questionnaire' : 'create-questionnaire');
    if (currentQuestionnaireId) {
        formData.append('id', currentQuestionnaireId);
    }
    formData.append('name', name);
    formData.append('service_id', serviceId);
    formData.append('description', description);
    formData.append('status', status);
    formData.append('csrf_token', admin.csrfToken || ''); // ✅ CSRF-Token hinzufügen
    
    try {
        const response = await fetch('api/admin.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': admin.csrfToken || ''
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(currentQuestionnaireId ? 'Fragebogen aktualisiert' : 'Fragebogen erstellt', 'success');
            cancelQuestionnaireEdit();
            loadQuestionnaires();
        } else {
            showNotification('Fehler: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error saving questionnaire:', error);
        showNotification('Fehler beim Speichern', 'error');
    }
}

function cancelQuestionnaireEdit() {
    currentQuestionnaireId = null;
    
    // Update URL to remove questionnaire ID but keep other query parameters
    if (window.updateUrlHash && window.parseUrlHashAndQuery) {
        const { query } = window.parseUrlHashAndQuery();
        const newQuery = { ...query };
        delete newQuery.id; // Remove the questionnaire ID
        window.updateUrlHash('questionnaires', newQuery);
    }
    
    document.getElementById('questionnaireEditor').style.display = 'none';
    document.querySelector('#questionnaires-section .data-table-container').style.display = 'block';
}

async function deleteQuestionnaire(id) {
    if (!confirm('Sind Sie sicher, dass Sie diesen Fragebogen löschen möchten?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete-questionnaire');
    formData.append('id', id);
    formData.append('csrf_token', admin.csrfToken || ''); // ✅ CSRF-Token hinzufügen
    
    try {
        const response = await fetch('api/admin.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': admin.csrfToken || ''
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Fragebogen gelöscht', 'success');
            loadQuestionnaires();
        } else {
            showNotification('Fehler: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error deleting questionnaire:', error);
        showNotification('Fehler beim Löschen', 'error');
    }
}

function refreshQuestionnaires() {
    loadQuestionnaires();
    showNotification('Fragebogen aktualisiert', 'info');
}

function searchQuestionnaires() {
    const searchTerm = document.getElementById('questionnaireSearch').value.toLowerCase();
    const filtered = questionnairesData.filter(questionnaire => 
        questionnaire.name.toLowerCase().includes(searchTerm) ||
        (questionnaire.description && questionnaire.description.toLowerCase().includes(searchTerm))
    );
    displayQuestionnaires(filtered);
}

function filterQuestionnaires() {
    const statusFilter = document.getElementById('questionnaireStatusFilter').value;
    const serviceFilter = document.getElementById('questionnaireServiceFilter') ? 
        document.getElementById('questionnaireServiceFilter').value : '';
    
    let filtered = questionnairesData || [];
    
    // Apply service filter
    if (serviceFilter) {
        const serviceFilterElement = document.getElementById('questionnaireServiceFilter');
        const selectedOption = serviceFilterElement.selectedOptions[0];
        const serviceId = selectedOption ? selectedOption.dataset.serviceId : null;
        
        const beforeLength = filtered.length;
        filtered = filtered.filter(questionnaire => {
            const matches = questionnaire.service_slug === serviceFilter || 
                           questionnaire.service_id == serviceId ||
                           questionnaire.service_types === serviceFilter;
            return matches;
        });
    }
    
    // Apply status filter
    if (statusFilter) {
        filtered = filtered.filter(questionnaire => questionnaire.status === statusFilter);
    }
    
    // Apply search if exists
    const searchTerm = document.getElementById('questionnaireSearch') ? 
        document.getElementById('questionnaireSearch').value.toLowerCase() : '';
    if (searchTerm) {
        filtered = filtered.filter(questionnaire => 
            (questionnaire.name && questionnaire.name.toLowerCase().includes(searchTerm)) ||
            (questionnaire.title && questionnaire.title.toLowerCase().includes(searchTerm)) ||
            (questionnaire.description && questionnaire.description.toLowerCase().includes(searchTerm))
        );
    }
    displayQuestionnaires(filtered);
}

function duplicateQuestionnaire(id) {
    const questionnaire = questionnairesData.find(q => q.id === id);
    if (!questionnaire) return;
    
    // Set current questionnaire to null to create new
    currentQuestionnaireId = null;
    
    document.getElementById('questionnaireEditorTitle').textContent = 'Fragebogen duplizieren';
    document.getElementById('questionnaireName').value = questionnaire.name + ' (Kopie)';
    document.getElementById('questionnaireService').value = questionnaire.service_id;
    document.getElementById('questionnaireDescription').value = questionnaire.description || '';
    document.getElementById('questionnaireStatus').value = 'draft'; // Always set to draft
    
    // Load services and questions from original questionnaire
    loadServicesForQuestionnaire();
    loadQuestionnaireQuestions(id);
    
    // Show editor
    document.getElementById('questionnaireEditor').style.display = 'block';
    document.querySelector('#questionnaires-section .data-table-container').style.display = 'none';
}

// ============================================================================
// Question Management Functions for Questionnaire Editor
// ============================================================================

function moveQuestionUp(questionId) {
    // Implementation would call API to reorder questions
    showNotification('Funktion noch nicht implementiert', 'info');
}

function moveQuestionDown(questionId) {
    // Implementation would call API to reorder questions
    showNotification('Funktion noch nicht implementiert', 'info');
}

function editQuestionInQuestionnaire(questionId) {
    // Implementation would open question editor for this specific question
    showNotification('Funktion noch nicht implementiert', 'info');
}

async function removeQuestionFromQuestionnaire(questionId) {
    if (!confirm('Möchten Sie diese Frage aus dem Fragebogen entfernen?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove-question-from-questionnaire');
    formData.append('questionnaire_id', currentQuestionnaireId);
    formData.append('question_id', questionId);
    formData.append('csrf_token', admin.csrfToken || ''); // ✅ CSRF-Token hinzufügen
    
    try {
        const response = await fetch('api/admin.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': admin.csrfToken || ''
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Frage entfernt', 'success');
            // ✅ Use drag & drop instance if available to refresh properly
            if (window.AdminDragDrop && window.AdminDragDrop.instance && currentQuestionnaireId) {
                // Reload fresh data from API
                const questionsResponse = await fetch(`api/admin.php?action=questionnaire-questions&id=${currentQuestionnaireId}`);
                const questionsData = await questionsResponse.json();
                
                if (questionsData.success) {
                    // Update drag & drop instance with fresh data
                    window.AdminDragDrop.instance.loadQuestions(questionsData.questions);
                }
            } else if (currentQuestionnaireId) {
                // Fallback to old method if drag & drop not available
                loadQuestionnaireQuestions(currentQuestionnaireId);
            }
        } else {
            showNotification('Fehler: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error removing question:', error);
        showNotification('Fehler beim Entfernen', 'error');
    }
}

// ============================================================================
// Drag & Drop Integration
// ============================================================================

function toggleGroupMode() {
    const btn = document.getElementById('groupModeBtn');
    const container = document.getElementById('questionsContainer');
    const newGroupZone = document.getElementById('newGroupZone');
    const groupsContainer = document.getElementById('groupsContainer');
    
    if (container.classList.contains('group-mode')) {
        // Disable group mode
        container.classList.remove('group-mode');
        btn.innerHTML = '<i class="fas fa-layer-group"></i> Gruppen-Modus';
        newGroupZone.style.display = 'none';
    } else {
        // Enable group mode
        container.classList.add('group-mode');
        btn.innerHTML = '<i class="fas fa-list"></i> Listen-Modus';
        newGroupZone.style.display = 'block';
        
        // Initialize drag & drop if not already done
        if (window.AdminDragDrop && !window.AdminDragDrop.instance) {
            window.AdminDragDrop.instance = new window.AdminDragDrop();
        }
    }
}

function toggleQuestionPalette() {
    const palette = document.getElementById('questionPalette');
    if (palette.style.display === 'none' || !palette.style.display) {
        palette.style.display = 'block';
    } else {
        palette.style.display = 'none';
    }
}

// ============================================================================
// Questions Management - Detailed Implementation
// ============================================================================

function showCreateQuestionModal() {
    currentQuestionId = null;
    
    // Update URL to remove any question ID but stay on questions page
    if (window.updateUrlHash) {
        const { query } = window.parseUrlHashAndQuery ? window.parseUrlHashAndQuery() : { query: {} };
        const newQuery = { ...query };
        delete newQuery.id;
        window.updateUrlHash('questions', newQuery);
    }
    
    document.getElementById('questionEditorTitle').textContent = 'Neue Frage';
    
    // Clear form
    document.getElementById('questionText').value = '';
    document.getElementById('questionType').value = '';
    document.getElementById('questionRequired').value = '0';
    document.getElementById('questionOrder').value = '1';
    document.getElementById('questionPlaceholder').value = '';
    document.getElementById('questionHelp').value = '';
    document.getElementById('questionOptions').value = '';
    document.getElementById('questionValidation').value = '';
    
    // Hide options section
    document.getElementById('questionOptionsSection').style.display = 'none';
    
    // Clear preview
    document.getElementById('questionPreview').innerHTML = '<p class="preview-placeholder">Wählen Sie einen Fragentyp um eine Vorschau zu sehen</p>';
    
    // Show editor
    document.getElementById('questionEditor').style.display = 'block';
    document.querySelector('#questions-section .data-table-container').style.display = 'none';
}

function editQuestion(id) {
    const question = questionsData.find(q => q.id === id);
    if (!question) {
        console.error('❌ Question not found with ID:', id);
        showNotification('Frage nicht gefunden', 'error');
        return;
    }
    
    currentQuestionId = id;
    
    // Update URL to include question ID
    if (window.updateUrlHash) {
        const { query } = window.parseUrlHashAndQuery ? window.parseUrlHashAndQuery() : { query: {} };
        window.updateUrlHash('questions', { ...query, id: id });
    }
    
    document.getElementById('questionEditorTitle').textContent = 'Frage bearbeiten';
    
    // Fill form
    document.getElementById('questionText').value = question.question_text;
    document.getElementById('questionType').value = question.question_type;
    document.getElementById('questionRequired').value = question.is_required ? '1' : '0';
    document.getElementById('questionOrder').value = question.question_order || 1;
    document.getElementById('questionPlaceholder').value = question.placeholder_text || '';
    document.getElementById('questionHelp').value = question.help_text || '';
    document.getElementById('questionOptions').value = question.options || '';
    document.getElementById('questionValidation').value = question.validation_rules || '';
    
    // Update options section and preview
    updateQuestionOptions();
    updateQuestionPreview();
    
    // Show editor
    document.getElementById('questionEditor').style.display = 'block';
    document.querySelector('#questions-section .data-table-container').style.display = 'none';
}

function updateQuestionOptions() {
    const questionType = document.getElementById('questionType').value;
    const optionsSection = document.getElementById('questionOptionsSection');
    
    // Show options section for select, radio, checkbox types
    if (['select', 'radio', 'checkbox'].includes(questionType)) {
        optionsSection.style.display = 'block';
    } else {
        optionsSection.style.display = 'none';
    }
    
    // Update preview
    updateQuestionPreview();
}

function updateQuestionPreview() {
    const questionText = document.getElementById('questionText').value;
    const questionType = document.getElementById('questionType').value;
    const isRequired = document.getElementById('questionRequired').value === '1';
    const placeholder = document.getElementById('questionPlaceholder').value;
    const helpText = document.getElementById('questionHelp').value;
    const options = document.getElementById('questionOptions').value;
    
    const previewContainer = document.getElementById('questionPreview');
    
    if (!questionText || !questionType) {
        previewContainer.innerHTML = '<p class="preview-placeholder">Geben Sie einen Fragetext und wählen Sie einen Typ um eine Vorschau zu sehen</p>';
        return;
    }
    
    let previewHtml = `
        <div class="form-group">
            <label for="preview-input">
                ${questionText}
                ${isRequired ? '<span class="required-indicator">*</span>' : ''}
            </label>
    `;
    
    switch (questionType) {
        case 'text':
            previewHtml += `<input type="text" id="preview-input" placeholder="${placeholder}" disabled>`;
            break;
            
        case 'textarea':
            previewHtml += `<textarea id="preview-input" rows="3" placeholder="${placeholder}" disabled></textarea>`;
            break;
            
        case 'select':
            const selectOptions = options.split('\n').filter(opt => opt.trim()).map(opt => `<option value="${opt.trim()}">${opt.trim()}</option>`).join('');
            previewHtml += `
                <select id="preview-input" disabled>
                    <option value="">-- Bitte wählen --</option>
                    ${selectOptions}
                </select>
            `;
            break;
            
        case 'radio':
            const radioOptions = options.split('\n').filter(opt => opt.trim()).map((opt, index) => 
                `<div class="radio-option">
                    <input type="radio" name="preview-radio" id="preview-radio-${index}" value="${opt.trim()}" disabled>
                    <label for="preview-radio-${index}">${opt.trim()}</label>
                </div>`
            ).join('');
            previewHtml += `<div class="radio-group">${radioOptions}</div>`;
            break;
            
        case 'checkbox':
            const checkboxOptions = options.split('\n').filter(opt => opt.trim()).map((opt, index) => 
                `<div class="checkbox-option">
                    <input type="checkbox" id="preview-checkbox-${index}" value="${opt.trim()}" disabled>
                    <label for="preview-checkbox-${index}">${opt.trim()}</label>
                </div>`
            ).join('');
            previewHtml += `<div class="checkbox-group">${checkboxOptions}</div>`;
            break;
            
        case 'number':
            previewHtml += `<input type="number" id="preview-input" placeholder="${placeholder}" disabled>`;
            break;
            
        case 'email':
            previewHtml += `<input type="email" id="preview-input" placeholder="${placeholder || 'name@beispiel.de'}" disabled>`;
            break;
            
        case 'tel':
            previewHtml += `<input type="tel" id="preview-input" placeholder="${placeholder || '+49 123 456789'}" disabled>`;
            break;
            
        case 'date':
            previewHtml += `<input type="date" id="preview-input" disabled>`;
            break;
            
        default:
            previewHtml += `<input type="text" id="preview-input" placeholder="${placeholder}" disabled>`;
    }
    
    if (helpText) {
        previewHtml += `<div class="help-text">${helpText}</div>`;
    }
    
    previewHtml += `</div>`;
    
    previewContainer.innerHTML = previewHtml;
}

async function saveQuestion() {
    const questionText = document.getElementById('questionText').value.trim();
    const questionType = document.getElementById('questionType').value;
    const isRequired = document.getElementById('questionRequired').value;
    const questionOrder = document.getElementById('questionOrder').value;
    const placeholder = document.getElementById('questionPlaceholder').value.trim();
    const helpText = document.getElementById('questionHelp').value.trim();
    const options = document.getElementById('questionOptions').value.trim();
    const validation = document.getElementById('questionValidation').value.trim();
    
    if (!questionText) {
        showNotification('Bitte geben Sie einen Fragetext ein', 'error');
        return;
    }
    
    if (!questionType) {
        showNotification('Bitte wählen Sie einen Fragetyp', 'error');
        return;
    }
    
    // Validate options for select/radio/checkbox types
    if (['select', 'radio', 'checkbox'].includes(questionType) && !options) {
        showNotification('Bitte geben Sie Optionen für diesen Fragetyp ein', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', currentQuestionId ? 'update-question' : 'create-question');
    if (currentQuestionId) {
        formData.append('id', currentQuestionId);
    }
    formData.append('question_text', questionText);
    formData.append('question_type', questionType);
    formData.append('is_required', isRequired);
    formData.append('placeholder_text', placeholder);
    formData.append('help_text', helpText);
    formData.append('options', options);
    formData.append('validation_rules', validation);
    formData.append('csrf_token', admin.csrfToken || ''); // ✅ CSRF-Token hinzufügen
    
    try {
        const response = await fetch('api/admin.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': admin.csrfToken || ''
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(currentQuestionId ? 'Frage aktualisiert' : 'Frage erstellt', 'success');
            cancelQuestionEdit();
            loadQuestions();
        } else {
            showNotification('Fehler: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error saving question:', error);
        showNotification('Fehler beim Speichern', 'error');
    }
}

function cancelQuestionEdit() {
    currentQuestionId = null;
    
    // Update URL to remove question ID
    if (window.updateUrlHash) {
        const { query } = window.parseUrlHashAndQuery ? window.parseUrlHashAndQuery() : { query: {} };
        const newQuery = { ...query };
        delete newQuery.id;
        window.updateUrlHash('questions', newQuery);
    }
    
    document.getElementById('questionEditor').style.display = 'none';
    document.querySelector('#questions-section .data-table-container').style.display = 'block';
}

async function deleteQuestion(id) {
    const question = questionsData.find(q => q.id === id);
    if (!question) return;
    
    if (!confirm(`Sind Sie sicher, dass Sie die Frage "${question.question_text}" löschen möchten?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete-question');
    formData.append('id', id);
    formData.append('csrf_token', admin.csrfToken || ''); // ✅ CSRF-Token hinzufügen
    
    try {
        const response = await fetch('api/admin.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': admin.csrfToken || ''
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Frage gelöscht', 'success');
            loadQuestions();
        } else {
            showNotification('Fehler: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error deleting question:', error);
        showNotification('Fehler beim Löschen der Frage', 'error');
    }
}

// Function to show usage warning when trying to delete a used question
function showUsageWarning(questionId, usedIn) {
    const message = `Diese Frage kann nicht gelöscht werden, da sie in folgenden Fragebögen verwendet wird:\n\n${usedIn}\n\nEntfernen Sie die Frage zuerst aus allen Fragebögen, bevor Sie sie löschen.`;
    alert(message);
}

function refreshQuestions() {
    loadQuestions();
    showNotification('Fragen aktualisiert', 'info');
}

function searchQuestions() {
    const searchTerm = document.getElementById('questionSearch').value.toLowerCase();
    const filtered = questionsData.filter(question => 
        question.question_text.toLowerCase().includes(searchTerm) ||
        (question.help_text && question.help_text.toLowerCase().includes(searchTerm))
    );
    displayQuestions(filtered);
}

function filterQuestions() {
    const typeFilter = document.getElementById('questionTypeFilter').value;
    let filtered = questionsData;
    
    if (typeFilter) {
        filtered = filtered.filter(question => question.question_type === typeFilter);
    }
    
    // Apply search if exists
    const searchTerm = document.getElementById('questionSearch').value.toLowerCase();
    if (searchTerm) {
        filtered = filtered.filter(question => 
            question.question_text.toLowerCase().includes(searchTerm) ||
            (question.help_text && question.help_text.toLowerCase().includes(searchTerm))
        );
    }
    
    displayQuestions(filtered);
}

function previewQuestion(id) {
    const question = questionsData.find(q => q.id === id);
    if (!question) return;
    
    // Create a temporary preview modal
    const modalHtml = `
        <div id="questionPreviewModal" class="modal modal-large show">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Frage-Vorschau: ${question.question_text}</h3>
                    <button class="modal-close" onclick="closeQuestionPreviewModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="question-preview-container">
                        <div class="form-group">
                            <label>
                                ${question.question_text}
                                ${question.is_required ? '<span class="required-indicator">*</span>' : ''}
                            </label>
                            ${generateQuestionInput(question)}
                            ${question.help_text ? `<div class="help-text">${question.help_text}</div>` : ''}
                        </div>
                        
                        <div class="question-meta" style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                            <p><strong>Typ:</strong> ${getQuestionTypeText(question.question_type)}</p>
                            <p><strong>Erforderlich:</strong> ${question.is_required ? 'Ja' : 'Nein'}</p>
                            ${question.validation_rules ? `<p><strong>Validierung:</strong> ${question.validation_rules}</p>` : ''}
                            <p><strong>Verwendet in:</strong> ${question.usage_count || 0} Fragebogen</p>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="closeQuestionPreviewModal()">Schließen</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.body.style.overflow = 'hidden';
}

function generateQuestionInput(question) {
    const placeholder = question.placeholder_text || '';
    
    switch (question.question_type) {
        case 'text':
            return `<input type="text" placeholder="${placeholder}" disabled>`;
            
        case 'textarea':
            return `<textarea rows="3" placeholder="${placeholder}" disabled></textarea>`;
            
        case 'select':
            const selectOptions = (question.options || '').split('\n').filter(opt => opt.trim()).map(opt => 
                `<option value="${opt.trim()}">${opt.trim()}</option>`
            ).join('');
            return `
                <select disabled>
                    <option value="">-- Bitte wählen --</option>
                    ${selectOptions}
                </select>
            `;
            
        case 'radio':
            const radioOptions = (question.options || '').split('\n').filter(opt => opt.trim()).map((opt, index) => 
                `<div class="radio-option">
                    <input type="radio" name="preview-radio" id="radio-${index}" value="${opt.trim()}" disabled>
                    <label for="radio-${index}">${opt.trim()}</label>
                </div>`
            ).join('');
            return `<div class="radio-group">${radioOptions}</div>`;
            
        case 'checkbox':
            const checkboxOptions = (question.options || '').split('\n').filter(opt => opt.trim()).map((opt, index) => 
                `<div class="checkbox-option">
                    <input type="checkbox" id="checkbox-${index}" value="${opt.trim()}" disabled>
                    <label for="checkbox-${index}">${opt.trim()}</label>
                </div>`
            ).join('');
            return `<div class="checkbox-group">${checkboxOptions}</div>`;
            
        case 'number':
            return `<input type="number" placeholder="${placeholder}" disabled>`;
            
        case 'email':
            return `<input type="email" placeholder="${placeholder || 'name@beispiel.de'}" disabled>`;
            
        case 'tel':
            return `<input type="tel" placeholder="${placeholder || '+49 123 456789'}" disabled>`;
            
        case 'date':
            return `<input type="date" disabled>`;
            
        default:
            return `<input type="text" placeholder="${placeholder}" disabled>`;
    }
}

function closeQuestionPreviewModal() {
    const modal = document.getElementById('questionPreviewModal');
    if (modal) {
        modal.remove();
    }
    document.body.style.overflow = '';
}

async function duplicateQuestion(id) {
    const question = questionsData.find(q => q.id === id);
    if (!question) return;
    
    if (!confirm(`Möchten Sie die Frage "${question.question_text}" duplizieren?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'create-question');
    formData.append('question_text', question.question_text + ' (Kopie)');
    formData.append('question_type', question.question_type);
    formData.append('is_required', question.is_required ? '1' : '0');
    formData.append('placeholder_text', question.placeholder_text || '');
    formData.append('help_text', question.help_text || '');
    formData.append('options', question.options || '');
    formData.append('validation_rules', question.validation_rules || '');
    formData.append('csrf_token', admin.csrfToken || ''); // ✅ CSRF-Token hinzufügen
    
    try {
        const response = await fetch('api/admin.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': admin.csrfToken || ''
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Frage dupliziert', 'success');
            loadQuestions();
        } else {
            showNotification('Fehler: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error duplicating question:', error);
        showNotification('Fehler beim Duplizieren', 'error');
    }
}

// ============================================================================
// Questionnaire-specific Question Management
// ============================================================================

function addQuestionToQuestionnaire() {
    // Show question selection modal
    showQuestionSelectionModal();
}

async function showQuestionSelectionModal() {
    // Load all questions if not already loaded
    if (questionsData.length === 0) {
        await loadQuestions();
    }
    
    const modalHtml = `
        <div id="questionSelectionModal" class="modal modal-large show">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Frage zum Fragebogen hinzufügen</h3>
                    <button class="modal-close" onclick="closeQuestionSelectionModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="selection-options">
                        <button class="btn btn-primary" onclick="showCreateQuestionInModal()">
                            <i class="fas fa-plus"></i>
                            Neue Frage erstellen
                        </button>
                        <button class="btn btn-outline" onclick="showExistingQuestions()">
                            <i class="fas fa-list"></i>
                            Vorhandene Frage verwenden
                        </button>
                    </div>
                    
                    <div id="questionSelectionContent">
                        <p class="text-center">Wählen Sie eine Option oben</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeQuestionSelectionModal()">Zurück</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.body.style.overflow = 'hidden';
}

function closeQuestionSelectionModal() {
    const modal = document.getElementById('questionSelectionModal');
    if (modal) {
        modal.remove();
    }
    document.body.style.overflow = '';
}

function showExistingQuestions() {
    const content = document.getElementById('questionSelectionContent');
    
    if (questionsData.length === 0) {
        content.innerHTML = `
            <div class="empty-message">
                <i class="fas fa-question-circle"></i>
                <p>Noch keine Fragen vorhanden</p>
                <button class="btn btn-primary" onclick="showCreateQuestionInModal()">
                    Erste Frage erstellen
                </button>
            </div>
        `;
        return;
    }
    
    content.innerHTML = `
        <div class="existing-questions">
            <div class="search-box" style="margin-bottom: 1rem;">
                <input type="text" id="questionSelectionSearch" placeholder="Frage suchen..." oninput="filterQuestionSelection()">
                <i class="fas fa-search"></i>
            </div>
            
            <div class="questions-list" id="questionSelectionList">
                ${questionsData.map(question => `
                    <div class="question-selection-item">
                        <div class="question-info">
                            <strong>${question.question_text}</strong>
                            <div class="question-meta">
                                <span class="badge badge-info">${getQuestionTypeText(question.question_type)}</span>
                                ${question.is_required ? '<span class="badge badge-warning">Erforderlich</span>' : ''}
                            </div>
                            ${question.help_text ? `<small class="text-muted">${question.help_text}</small>` : ''}
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="addExistingQuestionToQuestionnaire(${question.id})">
                            <i class="fas fa-plus"></i>
                            Hinzufügen
                        </button>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function showCreateQuestionInModal() {
    const content = document.getElementById('questionSelectionContent');
    
    content.innerHTML = `
        <div class="create-question-form">
            <h4>Neue Frage erstellen</h4>
            <form id="modalQuestionForm">
                <div class="form-group">
                    <label for="modalQuestionText">Fragetext *</label>
                    <input type="text" id="modalQuestionText" required placeholder="Ihre Frage hier...">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="modalQuestionType">Eingabetyp *</label>
                        <select id="modalQuestionType" required onchange="updateModalQuestionOptions()">
                            <option value="">Typ wählen...</option>
                            <option value="text">Textfeld</option>
                            <option value="textarea">Textbereich</option>
                            <option value="select">Auswahl-Dropdown</option>
                            <option value="radio">Radio-Buttons</option>
                            <option value="checkbox">Checkboxen</option>
                            <option value="number">Zahl</option>
                            <option value="email">E-Mail</option>
                            <option value="tel">Telefon</option>
                            <option value="date">Datum</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modalQuestionRequired">Erforderlich?</label>
                        <select id="modalQuestionRequired">
                            <option value="0">Nein</option>
                            <option value="1">Ja</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modalQuestionPlaceholder">Platzhalter-Text</label>
                    <input type="text" id="modalQuestionPlaceholder" placeholder="Optional: Hilfetext für Benutzer...">
                </div>
                
                <div class="form-group">
                    <label for="modalQuestionHelp">Hilfetext</label>
                    <input type="text" id="modalQuestionHelp" placeholder="Optional: Zusätzliche Erklärungen...">
                </div>
                
                <!-- Options for select, radio, checkbox -->
                <div class="form-group" id="modalQuestionOptionsSection" style="display: none;">
                    <label for="modalQuestionOptions">Optionen (eine pro Zeile)</label>
                    <textarea id="modalQuestionOptions" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="showExistingQuestions()">
                        <i class="fas fa-arrow-left"></i>
                        Zurück
                    </button>
                    <button type="button" class="btn btn-success" onclick="saveAndAddQuestionToQuestionnaire()">
                        <i class="fas fa-save"></i>
                        Erstellen & Hinzufügen
                    </button>
                </div>
            </form>
        </div>
    `;
}

function updateModalQuestionOptions() {
    const questionType = document.getElementById('modalQuestionType').value;
    const optionsSection = document.getElementById('modalQuestionOptionsSection');
    
    // Show options section for select, radio, checkbox types
    if (['select', 'radio', 'checkbox'].includes(questionType)) {
        optionsSection.style.display = 'block';
    } else {
        optionsSection.style.display = 'none';
    }
}

async function saveAndAddQuestionToQuestionnaire() {
    const questionText = document.getElementById('modalQuestionText').value.trim();
    const questionType = document.getElementById('modalQuestionType').value;
    const isRequired = document.getElementById('modalQuestionRequired').value;
    const placeholder = document.getElementById('modalQuestionPlaceholder').value.trim();
    const helpText = document.getElementById('modalQuestionHelp').value.trim();
    const options = document.getElementById('modalQuestionOptions')?.value.trim() || '';
    
    if (!questionText) {
        showNotification('Bitte geben Sie einen Fragetext ein', 'error');
        return;
    }
    
    if (!questionType) {
        showNotification('Bitte wählen Sie einen Fragentyp', 'error');
        return;
    }
    
    // Validate options for select/radio/checkbox types
    if (['select', 'radio', 'checkbox'].includes(questionType) && !options) {
        showNotification('Bitte geben Sie Optionen für diesen Fragetyp ein', 'error');
        return;
    }
    
    if (!currentQuestionnaireId) {
        showNotification('Bitte speichern Sie zuerst den Fragebogen', 'error');
        return;
    }
    
    try {
        // First, create the question
        const questionData = new FormData();
        questionData.append('action', 'create-question');
        questionData.append('question_text', questionText);
        questionData.append('question_type', questionType);
        questionData.append('is_required', isRequired);
        questionData.append('placeholder_text', placeholder);
        questionData.append('help_text', helpText);
        questionData.append('options', options);
        questionData.append('validation_rules', '');
        questionData.append('csrf_token', admin.csrfToken || ''); // ✅ CSRF-Token hinzufügen
        
        const questionResponse = await fetch('api/admin.php', {
            method: 'POST',
            body: questionData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': admin.csrfToken || ''
            }
        });
        
        const questionResult = await questionResponse.json();
        
        if (!questionResult.success) {
            showNotification('Fehler beim Erstellen der Frage: ' + questionResult.error, 'error');
            return;
        }
        
        // Reload questions to get the new question ID
        await loadQuestions();
        
        // Find the newly created question (it should be the first one due to ORDER BY id DESC)
        const newQuestion = questionsData[0];
        if (newQuestion && newQuestion.question_text === questionText) {
            // Add the question to the questionnaire
            await addExistingQuestionToQuestionnaire(newQuestion.id);
        } else {
            showNotification('Frage erstellt, aber konnte nicht zum Fragebogen hinzugefügt werden', 'warning');
            closeQuestionSelectionModal();
        }
    } catch (error) {
        console.error('Error creating and adding question:', error);
        showNotification('Fehler beim Erstellen der Frage', 'error');
    }
}

function filterQuestionSelection() {
    const searchTerm = document.getElementById('questionSelectionSearch').value.toLowerCase();
    const items = document.querySelectorAll('.question-selection-item');
    
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

async function addExistingQuestionToQuestionnaire(questionId) {
    if (!currentQuestionnaireId) {
        showNotification('Bitte speichern Sie zuerst den Fragebogen', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add-question-to-questionnaire');
    formData.append('questionnaire_id', currentQuestionnaireId);
    formData.append('question_id', questionId);
    formData.append('csrf_token', admin.csrfToken || ''); // ✅ CSRF-Token hinzufügen
    
    try {
        const response = await fetch('api/admin.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': admin.csrfToken || ''
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Frage hinzugefügt', 'success');
            closeQuestionSelectionModal();
            loadQuestionnaireQuestions(currentQuestionnaireId);
        } else {
            showNotification('Fehler: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error adding question to questionnaire:', error);
        showNotification('Fehler beim Hinzufügen', 'error');
    }
}

// Questions Management Functions
async function loadQuestions() {
    try {
        const response = await fetch('api/admin.php?action=questions');
        const data = await response.json();
        
        if (data.success) {
            questionsData = data.questions;
            displayQuestions(questionsData);
            
            // Check for question ID in URL and open for editing
            const { query } = parseUrlHashAndQuery();
            if (query.id) {
                const questionId = parseInt(query.id);
                
                // Wait a moment for DOM to be ready
                setTimeout(() => {
                    editQuestion(questionId);
                }, 100);
            }
        } else {
            showNotification('Fehler beim Laden der Fragen: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error loading questions:', error);
        showNotification('Fehler beim Laden der Fragen', 'error');
    }
}

function getQuestionTypeText(type) {
    const types = {
        'text': 'Text',
        'textarea': 'Textbereich',
        'select': 'Auswahl',
        'radio': 'Radio Button',
        'checkbox': 'Checkboxen',
        'number': 'Zahl',
        'email': 'E-Mail',
        'tel': 'Telefon',
        'date': 'Datum'
    };
    return types[type] || type;
}

function displayQuestions(questions) {
    const tbody = document.getElementById('questionsTableBody');
    if (!tbody) return;
    
    if (questions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-message">
                    <i class="fas fa-question-circle"></i>
                    <p>Keine Fragen gefunden</p>
                    <button class="btn btn-primary" onclick="showCreateQuestionModal()">
                        Erste Frage erstellen
                    </button>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = questions.map(question => {
        const usedIn = question.used_in || '-';
        const canDelete = question.can_delete;
        const usageText = question.usage_count > 0 ? 
            `${question.usage_count} Fragebogen` : 
            'Nicht verwendet';
        
        // Create delete button with conditional styling and functionality
        const deleteButton = canDelete 
            ? `<button class="btn btn-sm btn-danger" onclick="deleteQuestion(${question.id})" title="Frage löschen">
                   <i class="fas fa-trash"></i>
               </button>`
            : `<button class="btn btn-sm btn-danger disabled" onclick="showUsageWarning(${question.id}, '${question.used_in}')" title="Frage wird verwendet und kann nicht gelöscht werden">
                   <i class="fas fa-trash"></i>
               </button>`;
        
        return `
        <tr data-question-id="${question.id}">
            <td data-label="Frage">
                <strong>${question.question_text}</strong>
                ${question.help_text ? `<br><small class="text-muted">${question.help_text}</small>` : ''}
            </td>
            <td data-label="Typ"><span class="badge badge-info">${getQuestionTypeText(question.question_type)}</span></td>
            <td data-label="Erforderlich">
                <span class="badge badge-${question.is_required ? 'warning' : 'secondary'}">
                    ${question.is_required ? 'Ja' : 'Nein'}
                </span>
            </td>
            <td data-label="Verwendet in">
                <div class="usage-info" title="${usedIn}">
                    ${usedIn.length > 30 ? usedIn.substring(0, 30) + '...' : usedIn}
                    ${question.usage_count > 0 ? `<br><span class="usage-count">${usageText}</span>` : ''}
                </div>
            </td>
            <td data-label="Erstellt">
                <small>${formatDate(question.created_at)}</small>
            </td>
            <td data-label="Aktionen">
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline" onclick="editQuestion(${question.id})" title="Bearbeiten">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="previewQuestion(${question.id})" title="Vorschau">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="duplicateQuestion(${question.id})" title="Duplizieren">
                        <i class="fas fa-copy"></i>
                    </button>
                    ${deleteButton}
                </div>
            </td>
        </tr>
        `;
    }).join('');
}

// Helper Functions
function getStatusBadgeClass(status) {
    const classes = {
        'active': 'success',
        'draft': 'warning',
        'archived': 'secondary'
    };
    return classes[status] || 'secondary';
}

function getStatusText(status) {
    const texts = {
        'active': 'Aktiv',
        'draft': 'Entwurf',
        'archived': 'Archiviert'
    };
    return texts[status] || status;
}

function getQuestionTypeText(type) {
    const types = {
        'text': 'Text',
        'textarea': 'Textbereich',
        'select': 'Auswahl',
        'radio': 'Radio',
        'checkbox': 'Checkbox',
        'number': 'Zahl',
        'email': 'E-Mail',
        'tel': 'Telefon',
        'date': 'Datum'
    };
    return types[type] || type;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE');
}

// Submissions Management Functions
async function loadSubmissions() {
    try {
        const response = await fetch('api/admin.php?action=submissions');
        const data = await response.json();
        
        if (data.success) {
            submissionsData = data.submissions;
            displaySubmissions(submissionsData);
            
            // Preload all emails when submissions section is opened
            preloadAllEmails();
            
            // Check for submission ID in URL and open for viewing
            const { query } = parseUrlHashAndQuery();
            if (query.id) {
                const submissionId = parseInt(query.id);
                
                // Wait a moment for DOM to be ready
                setTimeout(() => {
                    viewSubmission(submissionId);
                }, 100);
            }
        } else {
            showNotification('Fehler beim Laden der Anfragen: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error loading submissions:', error);
        showNotification('Fehler beim Laden der Anfragen', 'error');
    }
}

function displaySubmissions(submissions) {
    const tbody = document.getElementById('submissionsTableBody');
    if (!tbody) return;
    
    if (submissions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-message">
                    <i class="fas fa-inbox"></i>
                    <p>Keine Anfragen gefunden</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = submissions.map(submission => `
        <tr>
            <td data-label="Datum">
                <small>${formatDate(submission.submitted_at)}</small>
            </td>
            <td data-label="Service"><span class="badge badge-info">${submission.service_name || 'N/A'}</span></td>
            <td data-label="Kontakt">
                ${submission.contact_email ? `<strong>${submission.contact_email}</strong>` : ''}
                ${submission.contact_phone ? `<br><small>${submission.contact_phone}</small>` : ''}
            </td>
            <td data-label="Status">
                <span class="badge badge-${getStatusBadgeClass(submission.status || 'new')}">${getStatusText(submission.status || 'new')}</span>
            </td>
            <td data-label="Antworten">
                <span class="question-count">${submission.answer_count || 0}</span>
                <small> Antworten</small>
            </td>
            <td data-label="Aktionen">
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline" onclick="viewSubmission(${submission.id})" title="Details anzeigen">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="respondToSubmission(${submission.id})" title="Antworten">
                        <i class="fas fa-reply"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="exportSubmissionPDF(${submission.id})" title="PDF Export">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function viewSubmission(id) {
    
    // Update URL to include submission ID
    if (window.updateUrlHash) {
        const { query } = window.parseUrlHashAndQuery ? window.parseUrlHashAndQuery() : { query: {} };
        window.updateUrlHash('submissions', { ...query, id: id });
    }
    
    try {
        const response = await fetch(`api/admin.php?action=submission&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            displaySubmissionDetails(data.submission, data.answers);
        } else {
            showNotification('Fehler beim Laden der Anfrage: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error loading submission:', error);
        showNotification('Fehler beim Laden der Anfrage', 'error');
    }
}

function displaySubmissionDetails(submission, answers) {
    currentSubmissionId = submission.id;
    
    document.getElementById('submissionDetailsTitle').textContent = `Anfrage #${submission.id}`;
    document.getElementById('submissionService').textContent = submission.service_name || 'N/A';
    document.getElementById('submissionDate').textContent = formatDate(submission.submitted_at);
    document.getElementById('submissionStatus').innerHTML = `<span class="badge badge-${getStatusBadgeClass(submission.status || 'new')}">${getStatusText(submission.status || 'new')}</span>`;
    document.getElementById('submissionContact').textContent = submission.contact_email || submission.contact_phone || 'N/A';
    
    const answersList = document.getElementById('submissionAnswersList');
    if (answers && answers.length > 0) {
        answersList.innerHTML = answers.map(answer => `
            <div class="answer-item">
                <div class="answer-question">${answer.question_text}</div>
                <div class="answer-value">${answer.answer_text || 'Keine Antwort'}</div>
            </div>
        `).join('');
    } else {
        answersList.innerHTML = '<div class="empty-message"><p>Keine Antworten verfügbar</p></div>';
    }
    
    // Don't load emails automatically - they will be loaded when the answers tab is opened
    // Reset emails container for this submission
    const emailsList = document.getElementById('submissionEmailsList');
    if (emailsList) {
        emailsList.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Lade E-Mails...</div>';
    }
    
    // Show details and hide table
    document.getElementById('submissionDetails').style.display = 'block';
    document.querySelector('#submissions-section .data-table-container').style.display = 'none';
}

function closeSubmissionDetails() {
    currentSubmissionId = null;
    document.getElementById('submissionDetails').style.display = 'none';
    document.querySelector('#submissions-section .data-table-container').style.display = 'block';
}

// Load emails for a specific submission
async function loadSubmissionEmails(submissionId) {
    
    const emailsList = document.getElementById('submissionEmailsList');
    
    if (!emailsList) {
        console.error('📧 ERROR: submissionEmailsList element not found in DOM!');
        return;
    }
    
    try {
        // Show loading spinner
        emailsList.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Lade E-Mails...</div>';
        const response = await fetch(`api/admin.php?action=submission-emails&id=${submissionId}`, {
            method: 'GET',
            credentials: 'include', // Include session cookies
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.warn('📧 Non-JSON response:', text.substring(0, 500));
            throw new Error(`Response is not JSON. Content-Type: ${contentType}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            displaySubmissionEmails(data.emails, data.contact_email);
        } else {
            console.error('📧 API returned error:', data.error);
            emailsList.innerHTML = `<div class="empty-message"><p>Fehler beim Laden der E-Mails: ${data.error}</p></div>`;
        }
    } catch (error) {
        console.error('📧 Error loading submission emails:', error);
        emailsList.innerHTML = `
            <div class="empty-message">
                <p><strong>Fehler beim Laden der E-Mails:</strong></p>
                <p>${error.message}</p>
                <button onclick="loadSubmissionEmails(${submissionId})" style="margin-top: 10px; padding: 8px 16px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    🔄 Erneut versuchen
                </button>
            </div>
        `;
    }
}

// Display emails for submission
function displaySubmissionEmails(emails, contactEmail) {
    const emailsList = document.getElementById('submissionEmailsList');
    
    if (!emails || emails.length === 0) {
        emailsList.innerHTML = `
            <div class="empty-message">
                <p>Keine E-Mails gefunden</p>
                ${contactEmail ? `<small>Kontakt-E-Mail: ${contactEmail}</small>` : ''}
            </div>
        `;
        return;
    }
    
    const emailsHtml = emails.map(email => {
        // Handle the complex email format with objects
        let fromText = email.from;
        if (Array.isArray(email.from) && email.from.length > 0) {
            const fromObj = email.from[0];
            fromText = fromObj.name ? `${fromObj.name} <${fromObj.email}>` : fromObj.email;
        }
        
        return `
            <div class="email-item ${email.seen ? '' : 'unread'}">
                <div class="email-header">
                    <div class="email-from">
                        <i class="fas fa-envelope${email.seen ? '-open' : ''}"></i>
                        <span class="from-text">${escapeHtml(fromText)}</span>
                    </div>
                    <div class="email-date">
                        ${email.date_formatted}
                    </div>
                </div>
                <div class="email-subject">
                    ${escapeHtml(email.subject)}
                    ${email.has_attachments ? '<i class="fas fa-paperclip" title="Hat Anhänge"></i>' : ''}
                </div>
                <div class="email-preview">
                    ${escapeHtml(email.body_preview)}
                </div>
                <div class="email-actions">
                    <button class="btn btn-sm btn-outline" onclick="viewEmailDetails('${email.uid}')">
                        <i class="fas fa-eye"></i> Details
                    </button>
                </div>
            </div>
        `;
    }).join('');
    
    emailsList.innerHTML = `
        <div class="emails-header">
            <div class="contact-info">
                <strong>Kontakt-E-Mail:</strong> ${contactEmail}
                <span class="email-count">(${emails.length} E-Mail${emails.length !== 1 ? 's' : ''})</span>
            </div>
        </div>
        <div class="emails-list">
            ${emailsHtml}
        </div>
    `;
}

// View email details (placeholder - can be extended)
function viewEmailDetails(emailUid) {
    showNotification(`E-Mail Details für UID: ${emailUid}`, 'info');
    // This can be extended to show a modal with full email content
}

// Preload all emails when submissions section is opened
async function preloadAllEmails() {
    try {        
        // Load emails using Event Store for efficiency
        const response = await fetch('api/admin.php?action=inbox-emails&limit=1000');
        const data = await response.json();
        
        if (data.success) {
            // Store emails globally for quick access
            window.preloadedEmails = data.emails;
        } else {
            console.warn('Failed to preload emails:', data.error);
        }
    } catch (error) {
        console.error('Error preloading emails:', error);
    }
}

function searchSubmissions() {
    const searchTerm = document.getElementById('submissionSearch').value.toLowerCase();
    const filtered = submissionsData.filter(submission => 
        (submission.contact_email && submission.contact_email.toLowerCase().includes(searchTerm)) ||
        (submission.contact_phone && submission.contact_phone.toLowerCase().includes(searchTerm)) ||
        (submission.service_name && submission.service_name.toLowerCase().includes(searchTerm))
    );
    displaySubmissions(filtered);
}

function filterSubmissions() {
    const serviceFilter = document.getElementById('submissionServiceFilter').value;
    const statusFilter = document.getElementById('submissionStatusFilter').value;
    const dateFilter = document.getElementById('submissionDateFilter').value;
    
    let filtered = submissionsData;
    
    if (serviceFilter) {
        filtered = filtered.filter(submission => submission.service_id == serviceFilter);
    }
    
    if (statusFilter) {
        filtered = filtered.filter(submission => (submission.status || 'new') === statusFilter);
    }
    
    if (dateFilter) {
        filtered = filtered.filter(submission => {
            const submissionDate = new Date(submission.submitted_at).toISOString().split('T')[0];
            return submissionDate === dateFilter;
        });
    }
    
    // Apply search if exists
    const searchTerm = document.getElementById('submissionSearch').value.toLowerCase();
    if (searchTerm) {
        filtered = filtered.filter(submission => 
            (submission.contact_email && submission.contact_email.toLowerCase().includes(searchTerm)) ||
            (submission.contact_phone && submission.contact_phone.toLowerCase().includes(searchTerm)) ||
            (submission.service_name && submission.service_name.toLowerCase().includes(searchTerm))
        );
    }
    
    displaySubmissions(filtered);
}

function refreshSubmissions() {
    loadSubmissions();
    showNotification('Anfragen aktualisiert', 'info');
}

function exportSubmissions() {
    // Implement submission export functionality
    showNotification('Export-Funktion noch nicht implementiert', 'info');
}

function respondToSubmission(id) {
    // Implement response functionality
    showNotification('Antwort-Funktion noch nicht implementiert', 'info');
}

function exportSubmissionPDF(id) {
    if (!id) {
        showNotification('Submission ID fehlt', 'error');
        return;
    }
    
    // Find the PDF export button to show loading state
    const pdfButton = document.querySelector(`[onclick="exportSubmissionPDF(${id})"]`);
    let originalButtonText = '';
    
    if (pdfButton) {
        originalButtonText = pdfButton.innerHTML;
        pdfButton.innerHTML = '🔄 PDF wird erstellt...';
        pdfButton.disabled = true;
    }
    
    try {
        // Show notification that PDF is being generated
        showNotification('PDF wird generiert...', 'info');
        
        // Create download URL and trigger download
        const downloadUrl = `/api/admin.php?action=exportPDF&id=${id}&download=true`;
        
        // Use window.location for reliable download behavior
        window.location.href = downloadUrl;
        
        // Show success notification after a short delay
        setTimeout(() => {
            showNotification('PDF Download gestartet! Überprüfen Sie Ihren Downloads-Ordner.', 'success');
            
            // Reset button state
            if (pdfButton) {
                pdfButton.innerHTML = '✅ Download gestartet!';
                setTimeout(() => {
                    pdfButton.innerHTML = originalButtonText;
                    pdfButton.disabled = false;
                }, 2000);
            }
        }, 1000);
        
    } catch (error) {
        console.error('PDF Export Error:', error);
        showNotification('Fehler beim PDF Export: ' + error.message, 'error');
        
        // Reset button state
        if (pdfButton) {
            pdfButton.innerHTML = originalButtonText;
            pdfButton.disabled = false;
        }
    }
}

// Alternative PDF export method using fetch and blob (for better error handling)
async function exportSubmissionPDFBlob(id) {
    if (!id) {
        showNotification('Submission ID fehlt', 'error');
        return;
    }
    
    const pdfButton = document.querySelector(`[onclick="exportSubmissionPDF(${id})"]`) || 
                     document.querySelector(`[onclick="exportSubmissionPDFBlob(${id})"]`);
    let originalButtonText = '';
    
    if (pdfButton) {
        originalButtonText = pdfButton.innerHTML;
        pdfButton.innerHTML = '🔄 PDF wird erstellt...';
        pdfButton.disabled = true;
    }
    
    try {
        showNotification('PDF wird generiert...', 'info');
        
        const response = await fetch(`/api/admin.php?action=exportPDF&id=${id}&download=true`);
        
        if (!response.ok) {
            throw new Error(`Server Error: ${response.status}`);
        }
        
        // Get PDF blob
        const blob = await response.blob();
        
        // Get filename from response headers
        const contentDisposition = response.headers.get('content-disposition');
        let filename = `Anfrage_${id}.pdf`;
        if (contentDisposition) {
            const matches = contentDisposition.match(/filename="([^"]+)"/);
            if (matches) filename = matches[1];
        }
        
        // Create and trigger download
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        
        // Cleanup
        setTimeout(() => {
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }, 100);
        
        showNotification('PDF erfolgreich heruntergeladen!', 'success');
        
        // Reset button state
        if (pdfButton) {
            pdfButton.innerHTML = '✅ PDF heruntergeladen!';
            setTimeout(() => {
                pdfButton.innerHTML = originalButtonText;
                pdfButton.disabled = false;
            }, 2000);
        }
        
    } catch (error) {
        console.error('PDF Export Error:', error);
        showNotification('Fehler beim PDF Export: ' + error.message, 'error');
        
        // Reset button state
        if (pdfButton) {
            pdfButton.innerHTML = originalButtonText;
            pdfButton.disabled = false;
        }
    }
}

// Export global functions
window.showNotification = showNotification;
window.previewQuestionnaire = previewQuestionnaire;
window.closeQuestionnairePreviewModal = closeQuestionnairePreviewModal;

window.showCreateQuestionnaireModal = showCreateQuestionnaireModal;
window.editQuestionnaire = editQuestionnaire;
window.saveQuestionnaire = saveQuestionnaire;
window.cancelQuestionnaireEdit = cancelQuestionnaireEdit;
window.deleteQuestionnaire = deleteQuestionnaire;
window.refreshQuestionnaires = refreshQuestionnaires;
window.searchQuestionnaires = searchQuestionnaires;
window.filterQuestionnaires = filterQuestionnaires;
window.previewQuestionnaire = previewQuestionnaire;
window.duplicateQuestionnaire = duplicateQuestionnaire;

window.showCreateQuestionModal = showCreateQuestionModal;
window.editQuestion = editQuestion;
window.updateQuestionOptions = updateQuestionOptions;
window.updateQuestionPreview = updateQuestionPreview;
window.saveQuestion = saveQuestion;
window.cancelQuestionEdit = cancelQuestionEdit;
window.deleteQuestion = deleteQuestion;
window.refreshQuestions = refreshQuestions;
window.searchQuestions = searchQuestions;
window.filterQuestions = filterQuestions;
window.previewQuestion = previewQuestion;
window.duplicateQuestion = duplicateQuestion;
window.closeQuestionPreviewModal = closeQuestionPreviewModal;

window.addQuestionToQuestionnaire = addQuestionToQuestionnaire;
window.showQuestionSelectionModal = showQuestionSelectionModal;
window.closeQuestionSelectionModal = closeQuestionSelectionModal;
window.showExistingQuestions = showExistingQuestions;
window.showCreateQuestionInModal = showCreateQuestionInModal;
window.updateModalQuestionOptions = updateModalQuestionOptions;
window.saveAndAddQuestionToQuestionnaire = saveAndAddQuestionToQuestionnaire;
window.filterQuestionSelection = filterQuestionSelection;
window.addExistingQuestionToQuestionnaire = addExistingQuestionToQuestionnaire;

// Question management in questionnaire editor
window.moveQuestionUp = moveQuestionUp;
window.moveQuestionDown = moveQuestionDown;
window.editQuestionInQuestionnaire = editQuestionInQuestionnaire;
window.removeQuestionFromQuestionnaire = removeQuestionFromQuestionnaire;

// Drag & drop functions
window.toggleGroupMode = toggleGroupMode;
window.toggleQuestionPalette = toggleQuestionPalette;

// Pricing management functions
window.addPriceItem = () => admin.addPriceItem();
window.removePriceItem = (button) => admin.removePriceItem(button);

window.loadSubmissions = loadSubmissions;
window.refreshSubmissions = refreshSubmissions;
window.searchSubmissions = searchSubmissions;
window.filterSubmissions = filterSubmissions;
window.exportSubmissions = exportSubmissions;
window.viewSubmission = viewSubmission;
window.closeSubmissionDetails = closeSubmissionDetails;
window.loadSubmissionEmails = loadSubmissionEmails;
window.displaySubmissionEmails = displaySubmissionEmails;
window.viewEmailDetails = viewEmailDetails;
window.preloadAllEmails = preloadAllEmails;
window.respondToSubmission = respondToSubmission;
window.exportSubmissionPDF = exportSubmissionPDF;
window.exportSubmissionPDFBlob = exportSubmissionPDFBlob;
window.showUsageWarning = showUsageWarning;

// Enhanced Response Functions
window.showResponseModal = showResponseModal;
window.showResponseTab = showResponseTab;
window.sendResponseEmail = sendResponseEmail;
window.generateOffer = generateOffer;
window.updateStatus = updateStatus;

// Image management global functions
window.uploadImages = () => admin.uploadImages();
window.deleteImage = (imageId) => admin.deleteImageById(imageId);
window.copyImagePath = (path) => admin.copyImagePath(path);
window.startUpload = () => admin.uploadImages();
window.openUploadModal = () => openModal('uploadModal');

// ============================================================================
// Enhanced Submissions Management
// ============================================================================

// Global variable to store current submission data
let currentSubmissionData = null;

/**
 * Enhanced respond to submission function
 */
function respondToSubmission(submissionId) {
    if (!submissionId) {
        showNotification('Keine Submission ID gefunden', 'error');
        return;
    }
    
    // Show loading
    showNotification('Lade Submission-Details...', 'info', 2000);
    
    // Load submission stats
    fetch(`api/admin.php?action=submission-stats&id=${submissionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentSubmissionData = data;
                showResponseModal(data);
            } else {
                showNotification('Fehler beim Laden der Submission: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .catch(error => {
            console.error('Error loading submission stats:', error);
            showNotification('Fehler beim Laden der Submission-Details', 'error');
        });
}

/**
 * Show the enhanced response modal
 */
function showResponseModal(submissionData) {
    
    // CRITICAL: Set both variables to ensure reliability
    currentSubmissionId = submissionData.submission.id;
    currentModalSubmissionId = submissionData.submission.id;
    
    // Extract contact data from answers
    const formData = submissionData.submission.form_data ? JSON.parse(submissionData.submission.form_data) : {};
    const answers = formData.answers || {};
    const contactData = extractContactDataFromAnswers(answers);
    
    // Populate modal with data
    document.getElementById('responseCustomerName').textContent = contactData.name || submissionData.submission.contact_name || '-';
    document.getElementById('responseServiceName').textContent = submissionData.submission.service_name || '-';
    document.getElementById('responseDate').textContent = formatDate(submissionData.submission.submitted_at || submissionData.submission.created_at) || '-';
    document.getElementById('responseReference').textContent = submissionData.submission.reference || '-';
    document.getElementById('responseCustomerEmail').textContent = contactData.email || submissionData.submission.contact_email || '-';
    document.getElementById('responseCustomerPhone').textContent = contactData.phone || submissionData.submission.contact_phone || '-';
    
    // Load internal notes into the notes field
    const notesField = document.getElementById('statusNotes');
    if (notesField) {
        notesField.value = submissionData.submission.internal_notes || '';
    }
    
    // Update completion stats
    const percentage = submissionData.completion_percentage || 0;
    const answeredCount = submissionData.answered_count || 0;
    const totalQuestions = submissionData.total_questions || 0;
    
    document.getElementById('responseCompletionPercent').textContent = percentage + '%';
    document.getElementById('responseCompletionCount').textContent = `${answeredCount} von ${totalQuestions}`;
    
    // Update completion circle
    const circle = document.querySelector('.completion-circle');
    const degrees = (percentage / 100) * 360;
    circle.style.background = `conic-gradient(#28a745 0deg, #28a745 ${degrees}deg, #e9ecef ${degrees}deg)`;
    
    // Show modal
    openModal('responseModal');
    
    // Show actions tab by default
    showResponseTab('actions');
}

/**
 * Load answers preview
 */
function loadAnswersPreview(questionPreviews) {
    const container = document.getElementById('answersPreview');
    
    if (!questionPreviews || Object.keys(questionPreviews).length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <p>Keine Antworten gefunden</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    for (const [questionId, question] of Object.entries(questionPreviews)) {
        const statusClass = question.answered ? 'answered' : 'empty';
        const answerText = question.answered ? question.preview : 'Nicht beantwortet';
        
        html += `
            <div class="answer-item ${statusClass}">
                <div class="answer-question">${question.text}</div>
                <div class="answer-response ${statusClass}">${answerText}</div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

/**
 * Show specific tab in response modal
 */
function showResponseTab(tabName) {
    
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.tab-btn[onclick="showResponseTab('${tabName}')"]`).classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(tabName + 'Tab').classList.add('active');
    
    // Load specific tab content
    if (tabName === 'answers' && currentSubmissionData) {
        // Only load email correspondence when answers tab is opened
        const submissionId = currentSubmissionId || currentModalSubmissionId;
        if (submissionId) {
            loadSubmissionEmails(submissionId);
        } else {
            console.warn('🔍 No submission ID available for email loading (currentSubmissionId:', currentSubmissionId, ', currentModalSubmissionId:', currentModalSubmissionId, ')');
        }
    } else if (tabName === 'offers' && currentSubmissionData) {
        loadSubmissionOffers(currentSubmissionData.submission.id);
    }
}

/**
 * Send response email
 */
function sendResponseEmail() {
    if (!currentSubmissionData) {
        showNotification('Keine Submission-Daten gefunden', 'error');
        return;
    }
    
    const template = document.getElementById('emailTemplate').value;
    const customMessage = document.getElementById('customMessage').value;
    
    const formData = new FormData();
    formData.append('id', currentSubmissionData.submission.id);
    formData.append('template', template);
    formData.append('message', customMessage);
    formData.append('csrf_token', admin.csrfToken || '');
    
    showNotification('Sende E-Mail...', 'info', 2000);
    
    fetch('api/admin.php?action=send-response-email', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-CSRF-Token': admin.csrfToken || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('E-Mail erfolgreich versendet!', 'success');
            // Clear form
            document.getElementById('customMessage').value = '';
        } else {
            showNotification('Fehler beim Senden der E-Mail: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        console.error('Error sending email:', error);
        showNotification('Fehler beim Senden der E-Mail', 'error');
    });
}

/**
 * Generate offer - Open offer modal
 */
function generateOffer() {
    
    if (!currentSubmissionData) {
        showNotification('Keine Submission-Daten gefunden', 'error');
        return;
    }
    
    // First, open modal
    openModal('offerModal');
    
    // Wait for modal to be shown, then populate
    setTimeout(() => {
        populateOfferModal();
    }, 200);
}

/**
 * Populate offer modal with submission data
 */
function populateOfferModal() {
    
    // Populate offer modal with submission data
    const formData = currentSubmissionData.submission.form_data ? JSON.parse(currentSubmissionData.submission.form_data) : {};
    const answers = formData.answers || {};
    const contactData = extractContactDataFromAnswers(answers);
    
    document.getElementById('offerCustomerName').textContent = contactData.name || currentSubmissionData.submission.contact_name || '-';
    document.getElementById('offerServiceName').textContent = currentSubmissionData.submission.service_name || '-';
    document.getElementById('offerReference').textContent = currentSubmissionData.submission.reference || '-';
    document.getElementById('offerDate').textContent = formatDate(currentSubmissionData.submission.submitted_at || currentSubmissionData.submission.created_at) || '-';
    
    // Set default dates
    const today = new Date();
    const validUntil = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days from now
    const executionDate = new Date(today.getTime() + (7 * 24 * 60 * 60 * 1000)); // 7 days from now
    
    document.getElementById('offerValidUntil').value = validUntil.toISOString().split('T')[0];
    document.getElementById('offerExecutionDate').value = executionDate.toISOString().split('T')[0];
    
    // Clear and initialize pricing items
    const pricingContainer = document.getElementById('offerPricingItems');
    
    if (pricingContainer) {
        pricingContainer.innerHTML = '';
        offerPricingCounter = 0;
        // Add one default pricing item
        addOfferPriceItem();
    } else {
        console.error('Pricing container not found!');
    }
}

/**
 * Update submission status
 */
function updateStatus() {
    if (!currentSubmissionData) {
        showNotification('Keine Submission-Daten gefunden', 'error');
        return;
    }
    
    const status = document.getElementById('newStatus').value;
    const notes = document.getElementById('statusNotes').value;
    
    if (!status) {
        showNotification('Bitte wählen Sie einen Status aus', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('id', currentSubmissionData.submission.id);
    formData.append('status', status);
    formData.append('notes', notes);
    formData.append('csrf_token', admin.csrfToken || '');
    
    showNotification('Aktualisiere Status...', 'info', 2000);
    
    fetch('api/admin.php?action=update-submission-status', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-CSRF-Token': admin.csrfToken || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Status erfolgreich aktualisiert!', 'success');
            // Refresh submissions table
            if (typeof loadSubmissions === 'function') {
                loadSubmissions();
            }
            // Close modal
            closeModal('responseModal');
        } else {
            showNotification('Fehler beim Aktualisieren des Status: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        showNotification('Fehler beim Aktualisieren des Status', 'error');
    });
}

/**
 * Enhanced loadSubmissions with answer stats
 */
function loadSubmissionsEnhanced() {
    const tableBody = document.getElementById('submissionsTableBody');
    
    // Show loading
    tableBody.innerHTML = `
        <tr>
            <td colspan="7" class="loading-message">
                <i class="fas fa-spinner fa-spin"></i>
                Lade Anfragen...
            </td>
        </tr>
    `;
    
    fetch('api/admin.php?action=submissions')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.submissions) {
                renderEnhancedSubmissionsTable(data.submissions);
                
                // Show filter info if available
                if (data.filter_info) {
                    showSubmissionsFilterInfo(data.filter_info);
                }
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            Fehler beim Laden der Anfragen: ${data.error || 'Unbekannter Fehler'}
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading submissions:', error);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        Fehler beim Laden der Anfragen
                    </td>
                </tr>
            `;
        });
}

/**
 * Show filter information for submissions
 */
function showSubmissionsFilterInfo(filterInfo) {
    // Remove any existing filter info
    const existingInfo = document.querySelector('.submissions-filter-info');
    if (existingInfo) {
        existingInfo.remove();
    }
    
    // Find submissions section header
    const submissionsSection = document.getElementById('submissions-section');
    const sectionHeader = submissionsSection?.querySelector('.section-header');
    
    if (sectionHeader) {
        // Create filter info banner
        const filterInfoElement = document.createElement('div');
        filterInfoElement.className = 'submissions-filter-info';
        filterInfoElement.innerHTML = `
            <div class="filter-info-content">
                <div class="filter-info-icon">
                    <i class="fas fa-filter"></i>
                </div>
                <div class="filter-info-text">
                    <strong>Aktive Filterung:</strong> 
                    ${filterInfo.description}
                    <small>Ältere abgeschlossene Anfragen finden Sie im <a href="#submission-archive" class="archive-link" onclick="admin.switchSection('submission-archive'); return false;">Archiv</a></small>
                </div>
                <button class="filter-info-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        // Insert after section header
        sectionHeader.insertAdjacentElement('afterend', filterInfoElement);
    }
}

/**
 * Render enhanced submissions table with answer completion
 */
function renderEnhancedSubmissionsTable(submissions) {
    const tableBody = document.getElementById('submissionsTableBody');
    
    if (!submissions || submissions.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-message">
                    <i class="fas fa-inbox"></i>
                    Keine Anfragen gefunden
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    submissions.forEach(submission => {
        // Extract contact data from questionnaire answers
        const answersData = submission.form_data ? JSON.parse(submission.form_data) : {};
        const answers = answersData.answers || {};
        const contactData = extractContactDataFromAnswers(answers);
        
        const statusBadge = getStatusBadge(submission.status);
        
        html += `
            <tr>
                <td>${formatDate(submission.submitted_at || submission.created_at)}</td>
                <td>
                    <div class="service-name">${submission.service_name || 'Unbekannt'}</div>
                    <div class="reference-number">${submission.reference || ''}</div>
                </td>
                <td>
                    <div class="contact-info">
                        <div class="contact-name">${contactData.name || 'Unbekannt'}</div>
                        <div class="contact-details">
                            ${contactData.email ? `<i class="fas fa-envelope"></i> ${contactData.email}` : ''}
                            ${contactData.phone ? `<br><i class="fas fa-phone"></i> ${contactData.phone}` : ''}
                        </div>
                    </div>
                </td>
                <td>${statusBadge}</td>
                <td>
                    <button class="answer-btn" onclick="respondToSubmission(${submission.id})">
                        <i class="fas fa-reply"></i>
                        Antworten
                    </button>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline" onclick="viewSubmission(${submission.id})" title="Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="exportSubmissionPDF(${submission.id})" title="PDF Export">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
}

/**
 * Extract contact data from questionnaire answers
 */
function extractContactDataFromAnswers(answers) {
    const contactData = {
        name: null,
        email: null,
        phone: null
    };
    
    // Check if answers is in the new format (array with answer_text)
    if (Array.isArray(answers) && answers.length > 0) {
        // Check if first element has the new format structure
        const firstAnswer = answers[0];
        if (firstAnswer && typeof firstAnswer === 'object' && 
            'question_text' in firstAnswer && 'answer_text' in firstAnswer) {
            
            // New format: array of objects with answer_text
            answers.forEach(answer => {
                // Check if answer object has required properties and they are valid
                if (!answer || typeof answer !== 'object') return;
                if (!answer.question_text || !answer.answer_text) return;
                if (answer.answer_text === null || answer.answer_text === undefined) return;
                
                // Ensure both are strings
                if (typeof answer.question_text !== 'string' || typeof answer.answer_text !== 'string') return;
                
                const questionText = answer.question_text.toLowerCase();
                const answerText = answer.answer_text.trim();
                
                if (!answerText) return;
                
                // Extract name
                if (questionText.includes('vor-') && questionText.includes('nachname')) {
                    contactData.name = answerText;
                } else if (questionText.includes('name') && !contactData.name) {
                    contactData.name = answerText;
                }
                
                // Extract email
                if (questionText.includes('e-mail') || questionText.includes('email')) {
                    contactData.email = answerText;
                }
                
                // Extract phone
                if (questionText.includes('telefon') || questionText.includes('phone')) {
                    contactData.phone = answerText;
                }
            });
            
            return contactData;
        }
    }
    
    // Old format or fallback: associative array with field keys
    const nameFields = [
        'name', 'vorname_nachname', 'vor_und_nachname', 'kunde_name', 
        'kundename', 'vollstaendiger_name', 'full_name', 'customer_name',
        'vor-und-nachname', 'vor_nachname', 'nachname_vorname'
    ];
    
    const emailFields = [
        'email', 'e_mail', 'e-mail', 'email_adresse', 'emailadresse',
        'mail', 'kontakt_email', 'kunde_email'
    ];
    
    const phoneFields = [
        'telefon', 'phone', 'telefonnummer', 'tel', 'handy', 'mobile',
        'kontakt_telefon', 'kunde_telefon', 'telefon_nummer'
    ];
    
    // Extract name
    for (const field of nameFields) {
        if (answers[field] && typeof answers[field] === 'string' && answers[field].trim()) {
            contactData.name = answers[field].trim();
            break;
        }
    }
    
    // Extract email
    for (const field of emailFields) {
        if (answers[field] && typeof answers[field] === 'string' && answers[field].trim()) {
            contactData.email = answers[field].trim();
            break;
        }
    }
    
    // Extract phone
    for (const field of phoneFields) {
        if (answers[field] && typeof answers[field] === 'string' && answers[field].trim()) {
            contactData.phone = answers[field].trim();
            break;
        }
    }
    
    return contactData;
}

// ============================================================================
// Offer Management Functions
// ============================================================================

let offerPricingCounter = 0;

/**
 * Add offer price item
 */
function addOfferPriceItem() {
    
    const container = document.getElementById('offerPricingItems');
    if (!container) {
        console.error('offerPricingItems container not found!');
        return;
    }
    
    const itemId = 'offer_price_' + (++offerPricingCounter);
    
    const itemHtml = `
        <div class="pricing-item" id="${itemId}">
            <div class="form-group">
                <label>Beschreibung/Leistung</label>
                <input type="text" class="form-control price-description" placeholder="z.B. Umzugsservice, Rabatt, Gutschrift...">
            </div>
            <div class="form-group">
                <label>Einzelpreis (€)</label>
                <input type="number" step="0.01" class="form-control price-amount" placeholder="0.00" onchange="(async () => { try { await calculateOfferTotal(); } catch(e) { console.error('Error in price-amount change:', e); } })()">
            </div>
            <div class="form-group">
                <label>Menge</label>
                <input type="number" min="1" class="form-control price-quantity" value="1" onchange="(async () => { try { await calculateOfferTotal(); } catch(e) { console.error('Error in price-quantity change:', e); } })()">
            </div>
            <button type="button" class="pricing-item-remove" onclick="removeOfferPriceItem('${itemId}')">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', itemHtml);
    calculateOfferTotal().catch(e => console.error('Error calculating total:', e));
}

/**
 * Remove offer price item
 */
function removeOfferPriceItem(itemId) {
    const item = document.getElementById(itemId);
    if (item) {
        item.remove();
        calculateOfferTotal().catch(e => console.error('Error calculating total:', e));
    }
}

/**
 * Calculate offer total
 */
/**
 * Calculate offer total with VAT settings from backend
 */
async function calculateOfferTotal() {
    try {
        const items = document.querySelectorAll('#offerPricingItems .pricing-item');
        let totalNet = 0;
        
        items.forEach(item => {
            const amountEl = item.querySelector('.price-amount') || item.querySelector('.price-value');
            const quantityEl = item.querySelector('.price-quantity');
            
            const amount = amountEl ? parseFloat(amountEl.value) || 0 : 0;
            const quantity = quantityEl ? parseInt(quantityEl.value) || 1 : 1;
            totalNet += amount * quantity;
        });
        
        // Get VAT setting from backend
        let vatRate = 0.19; // Default 19%
        let isKleinunternehmer = false;
        
        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': admin.csrfToken || ''
                },
                body: `action=get-vat-setting&csrf_token=${encodeURIComponent(admin.csrfToken || '')}`
            });
            
            const data = await response.json();
            if (data.success) {
                isKleinunternehmer = data.isKleinunternehmer;
                vatRate = data.vatRate;
            }
        } catch (error) {
            console.error('Error fetching VAT settings:', error);
        }
        
        const totalVAT = totalNet * vatRate;
        const totalGross = totalNet + totalVAT;
        
        const totalNetEl = document.getElementById('offerTotalNet');
        const totalVATEl = document.getElementById('offerTotalVAT');
        const totalGrossEl = document.getElementById('offerTotalGross');
        
        if (totalNetEl) totalNetEl.textContent = totalNet.toFixed(2).replace('.', ',') + ' €';
        if (totalVATEl) {
            const vatText = isKleinunternehmer ? 
                `0,00 € (Kleinunternehmer §19 UStG)` : 
                `${totalVAT.toFixed(2).replace('.', ',')} € (${(vatRate * 100).toFixed(0)}%)`;
            totalVATEl.textContent = vatText;
        }
        if (totalGrossEl) totalGrossEl.textContent = totalGross.toFixed(2).replace('.', ',') + ' €';
    } catch (error) {
        console.error('Error calculating offer total:', error);
    }
}

// Add updatePricingData as alias for calculateOfferTotal to catch any stray calls
let updatePricingDataCalls = 0;
async function updatePricingData() {
    updatePricingDataCalls++;
    await calculateOfferTotal();
}

// Make it globally available
window.updatePricingData = updatePricingData;

/**
 * Preview offer
 */
function previewOffer() {
    showNotification('Vorschau-Funktion wird implementiert...', 'info');
}

/**
 * Generate offer PDF
 */
function generateOfferPDF() {    
    if (!currentSubmissionData) {
        showNotification('Keine Submission-Daten gefunden', 'error');
        return;
    }
    
    // Collect pricing data
    const pricingItems = [];
    const pricingElements = document.querySelectorAll('#offerPricingItems .pricing-item');
    
    pricingElements.forEach((item, index) => {
        const descriptionElement = item.querySelector('.price-description');
        const amountElement = item.querySelector('.price-amount');
        const quantityElement = item.querySelector('.price-quantity');
        
        if (descriptionElement && amountElement && quantityElement) {
            const description = descriptionElement.value;
            const amount = parseFloat(amountElement.value) || 0;
            const quantity = parseInt(quantityElement.value) || 1;
            
            if (description.trim() && amount !== 0) {
                pricingItems.push({
                    description: description.trim(),
                    amount: amount,
                    quantity: quantity,
                    total: amount * quantity
                });
            }
        }
    });
    
    if (pricingItems.length === 0) {
        showNotification('Bitte mindestens eine Preisposition hinzufügen', 'error');
        return;
    }
    
    // Collect form data
    const formData = new FormData();
    formData.append('action', 'generate-offer');
    formData.append('id', currentSubmissionData.submission.id);
    formData.append('pricing_items', JSON.stringify(pricingItems));
    formData.append('offer_notes', document.getElementById('offerNotes').value);
    formData.append('offer_terms', document.getElementById('offerTerms').value);
    formData.append('valid_until', document.getElementById('offerValidUntil').value);
    formData.append('execution_date', document.getElementById('offerExecutionDate').value);
    formData.append('csrf_token', admin.csrfToken || '');
    
    showNotification('Generiere Angebot-PDF...', 'info', 3000);
    
    fetch('api/admin.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-CSRF-Token': admin.csrfToken || ''
        }
    })
    .then(response => response.json())
    .then(data => {        
        if (data.success) {
            showNotification('Angebot erfolgreich generiert!', 'success');
            closeModal('offerModal');
            
            // Download PDF if available
            if (data.pdf_url) {
                const link = document.createElement('a');
                link.href = data.pdf_url;
                link.download = data.filename || 'angebot.pdf';
                link.click();
            }
        } else {
            showNotification('Fehler beim Generieren des Angebots: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        console.error('Error generating offer:', error);
        showNotification('Fehler beim Generieren des Angebots', 'error');
    });
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const statusConfig = {
        'neu': { class: 'secondary', icon: 'fas fa-clock', text: 'Neu' },
        'in_bearbeitung': { class: 'warning', icon: 'fas fa-cog', text: 'In Bearbeitung' },
        'angebot_erstellt': { class: 'info', icon: 'fas fa-file-invoice', text: 'Angebot erstellt' },
        'termin_geplant': { class: 'primary', icon: 'fas fa-calendar-check', text: 'Termin geplant' },
        'abgeschlossen': { class: 'success', icon: 'fas fa-check-circle', text: 'Abgeschlossen' },
        'storniert': { class: 'danger', icon: 'fas fa-times-circle', text: 'Storniert' }
    };
    
    const config = statusConfig[status] || statusConfig['neu'];
    return `<span class="badge badge-${config.class}"><i class="${config.icon}"></i> ${config.text}</span>`;
}

// Override the original loadSubmissions function
if (typeof loadSubmissions === 'function') {
    window.loadSubmissionsOriginal = loadSubmissions;
}

// Export offer functions to global scope
window.addOfferPriceItem = addOfferPriceItem;
window.removeOfferPriceItem = removeOfferPriceItem;
window.calculateOfferTotal = calculateOfferTotal;
window.previewOffer = previewOffer;
window.generateOfferPDF = generateOfferPDF;
window.populateOfferModal = populateOfferModal;
window.loadSubmissionOffers = loadSubmissionOffers;
window.refreshOffers = refreshOffers;
window.downloadOfferPDF = downloadOfferPDF;
window.updateOfferStatus = updateOfferStatus;

// ========================================
// OFFERS MANAGEMENT FUNCTIONS
// ========================================

/**
 * Load offers for current submission
 */
function loadSubmissionOffers(submissionId) {
    
    const container = document.getElementById('offersContainer');
    if (!container) {
        console.error('Offers container not found');
        return;
    }
    
    container.innerHTML = '<div class="loading-message"><i class="fas fa-spinner fa-spin"></i> Lade Angebote...</div>';
    
    fetch(`api/admin.php?action=submission-offers&submission_id=${submissionId}`)
        .then(response => response.json())
        .then(data => {
            
            if (data.success) {
                displayOffers(data.offers);
            } else {
                container.innerHTML = `<div class="error-message"><i class="fas fa-exclamation-triangle"></i> Fehler: ${data.error || 'Unbekannter Fehler'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error loading offers:', error);
            container.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i> Fehler beim Laden der Angebote</div>';
        });
}

/**
 * Display offers in the container
 */
function displayOffers(offers) {
    const container = document.getElementById('offersContainer');
    
    if (!offers || offers.length === 0) {
        container.innerHTML = `
            <div class="no-offers">
                <i class="fas fa-file-invoice"></i>
                <h5>Noch keine Angebote erstellt</h5>
                <p>Erstellen Sie ein neues Angebot für diese Anfrage.</p>
            </div>
        `;
        return;
    }
    
    const offersHtml = offers.map(offer => {
        
        // Validate offer ID
        if (!offer.id) {
            console.error('Offer missing ID:', offer);
            return '';
        }
        
        return `
        <div class="offer-item">
            <div class="offer-header">
                <span class="offer-number">${offer.offer_number || 'Unbekannt'}</span>
                <div class="offer-status">
                    ${offer.status_badge || ''}
                </div>
            </div>
            <div class="offer-body">
                <div class="offer-details">
                    <div class="offer-detail">
                        <span class="offer-detail-label">Erstellt</span>
                        <span class="offer-detail-value">${offer.formatted_date || formatDate(offer.created_at)}</span>
                    </div>
                    <div class="offer-detail">
                        <span class="offer-detail-label">Gesamtbetrag</span>
                        <span class="offer-detail-value offer-total">${offer.formatted_total || (parseFloat(offer.total_gross).toFixed(2) + ' €')}</span>
                    </div>
                    <div class="offer-detail">
                        <span class="offer-detail-label">Netto</span>
                        <span class="offer-detail-value">${parseFloat(offer.total_net).toFixed(2)} €</span>
                    </div>
                    <div class="offer-detail">
                        <span class="offer-detail-label">MwSt.</span>
                        <span class="offer-detail-value">${parseFloat(offer.total_vat).toFixed(2)} €</span>
                    </div>
                    ${offer.valid_until ? `
                    <div class="offer-detail">
                        <span class="offer-detail-label">Gültig bis</span>
                        <span class="offer-detail-value">${formatDate(offer.valid_until)}</span>
                    </div>
                    ` : ''}
                </div>
                
                <div class="offer-actions">
                    <button class="btn btn-primary" onclick="downloadOfferPDF('${offer.pdf_path || ''}')" title="PDF herunterladen">
                        <i class="fas fa-download"></i> PDF
                    </button>
                    <button class="btn btn-outline" onclick="previewOffer(${offer.id})" title="Angebot anzeigen">
                        <i class="fas fa-eye"></i> Vorschau
                    </button>
                    <select class="form-select" onchange="if(this.value !== '') updateOfferStatus(${offer.id}, this.value)" title="Status ändern">
                        <option value="" disabled ${offer.status == null ? 'selected' : ''}>Status ändern</option>
                        <option value="0" ${offer.status == 0 ? 'selected' : ''}>Entwurf</option>
                        <option value="1" ${offer.status == 1 ? 'selected' : ''}>Versendet</option>
                        <option value="2" ${offer.status == 2 ? 'selected' : ''}>Angenommen</option>
                        <option value="3" ${offer.status == 3 ? 'selected' : ''}>Abgelehnt</option>
                        <option value="4" ${offer.status == 4 ? 'selected' : ''}>Abgelaufen</option>
                    </select>
                </div>
            </div>
        </div>
    `}).filter(html => html !== '').join('');
    
    container.innerHTML = offersHtml;
}

/**
 * Refresh offers for current submission
 */
function refreshOffers() {
    if (currentSubmissionData && currentSubmissionData.submission) {
        loadSubmissionOffers(currentSubmissionData.submission.id);
    }
}

/**
 * Download offer PDF
 */
function downloadOfferPDF(pdfPath) {
    if (!pdfPath) {
        showNotification('PDF-Pfad nicht gefunden', 'error');
        return;
    }
    
    // Create download link
    const link = document.createElement('a');
    link.href = '/' + pdfPath;
    link.download = '';
    link.target = '_blank';
    
    // Trigger download
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showNotification('PDF wird heruntergeladen...', 'success', 2000);
}

/**
 * Update offer status
 */
function updateOfferStatus(offerId, newStatus) {
    if (!newStatus || newStatus === '') {
        console.info('No status selected, returning');
        return;
    }
    
    // Convert to integer
    newStatus = parseInt(newStatus);
    if (isNaN(newStatus) || newStatus < 0 || newStatus > 4) {
        showNotification('Ungültiger Status-Wert', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update-offer-status');
    formData.append('offer_id', offerId);
    formData.append('status', newStatus);
    formData.append('csrf_token', admin.csrfToken || '');
    
    fetch('api/admin.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-CSRF-Token': admin.csrfToken || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            refreshOffers(); // Refresh the offers list
        } else {
            showNotification(data.error || 'Fehler beim Aktualisieren des Status', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating offer status:', error);
        showNotification('Fehler beim Aktualisieren des Status', 'error');
    });
}

/**
 * Format date for display
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('de-DE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return dateString;
    }
}
window.loadSubmissions = loadSubmissionsEnhanced;

// ========================================
// ARCHIVE FUNCTIONS
// ========================================

/**
 * Load archived submissions
 */
function loadArchivedSubmissions() {
    const search = document.getElementById('archiveSearch')?.value || '';
    const serviceFilter = document.getElementById('archiveServiceFilter')?.value || '';
    const statusFilter = document.getElementById('archiveStatusFilter')?.value || '';
    const daysFilter = document.getElementById('archiveDaysFilter')?.value || '30';
    
    const params = new URLSearchParams({
        action: 'archived-submissions',
        days: daysFilter,
        search: search,
        service_id: serviceFilter,
        status: statusFilter,
        limit: 100
    });
    
    showNotification('Lade archivierte Anfragen...', 'info', 2000);
    
    fetch(`api/admin.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayArchivedSubmissions(data.submissions);
                updateArchiveStats(data);
            } else {
                showNotification('Fehler beim Laden der archivierten Anfragen: ' + (data.error || 'Unbekannter Fehler'), 'error');
                console.error('API Error:', data);
            }
        })
        .catch(error => {
            showNotification('Fehler beim Laden der archivierten Anfragen', 'error');
            console.error('Error:', error);
        });
}

/**
 * Get contact name from form data (helper function for archive)
 */
function getContactFromFormData(formDataString) {
    try {
        const formData = JSON.parse(formDataString);
        if (formData.answers && Array.isArray(formData.answers)) {
            const contactData = extractContactDataFromAnswers(formData.answers);
            return contactData.name;
        }
        // Fallback: try to extract from form_data directly
        return extractContactDataFromAnswers(formData)?.name;
    } catch (error) {
        console.error('Error parsing form data for contact:', error);
        return null;
    }
}

/**
 * Get email from form data (helper function for archive)
 */
function getEmailFromFormData(formDataString) {
    try {
        const formData = JSON.parse(formDataString);
        if (formData.answers && Array.isArray(formData.answers)) {
            const contactData = extractContactDataFromAnswers(formData.answers);
            return contactData.email;
        }
        // Fallback: try to extract from form_data directly
        return extractContactDataFromAnswers(formData)?.email;
    } catch (error) {
        console.error('Error parsing form data for email:', error);
        return null;
    }
}

/**
 * Format currency (helper function for archive)
 */
function formatCurrency(amount) {
    if (!amount || isNaN(amount)) return '0,00 €';
    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

/**
 * Get status class for submission status (helper function for archive)
 */
function getStatusClass(status) {
    const statusConfig = {
        'neu': 'secondary',
        'new': 'secondary',
        'in_bearbeitung': 'warning', 
        'in_progress': 'warning',
        'angebot_erstellt': 'info',
        'offer_created': 'info',
        'termin_geplant': 'primary',
        'scheduled': 'primary',
        'abgeschlossen': 'success',
        'completed': 'success',
        'storniert': 'danger',
        'cancelled': 'danger'
    };
    
    return statusConfig[status] || 'secondary';
}

/**
 * Get status text for submission status (extended version)
 */
function getSubmissionStatusText(status) {
    const statusTexts = {
        'neu': 'Neu',
        'new': 'Neu',
        'in_bearbeitung': 'In Bearbeitung',
        'in_progress': 'In Bearbeitung',
        'angebot_erstellt': 'Angebot erstellt',
        'offer_created': 'Angebot erstellt',
        'termin_geplant': 'Termin geplant',
        'scheduled': 'Termin geplant',
        'abgeschlossen': 'Abgeschlossen',
        'completed': 'Abgeschlossen',
        'storniert': 'Storniert',
        'cancelled': 'Storniert'
    };
    
    return statusTexts[status] || status;
}

/**
 * Display archived submissions in table
 */
function displayArchivedSubmissions(submissions) {
    const tbody = document.getElementById('archivedSubmissionsTableBody');
    if (!tbody) return;
    
    if (!submissions || submissions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-message">
                    <div style="text-align: center; padding: 2rem; color: var(--medium-gray);">
                        <i class="fas fa-archive" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 0.5rem 0; font-weight: 600;">Keine archivierten Anfragen gefunden</h3>
                        <p style="margin: 0; font-size: 0.9rem;">Versuchen Sie einen anderen Filter oder Suchbegriff</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = submissions.map(submission => {
        const contactName = getContactFromFormData(submission.form_data) || 'Unbekannt';
        const contactEmail = getEmailFromFormData(submission.form_data) || '';
        const offersCount = submission.offers_count || 0;
        const offersValue = submission.total_offers_value || 0;
        
        // Color-code days old
        const daysOld = submission.days_old || 0;
        let daysClass = '';
        if (daysOld > 180) daysClass = 'very-old';
        else if (daysOld > 90) daysClass = 'old';
        else if (daysOld > 60) daysClass = 'medium';
        
        return `
            <tr style="border-bottom: 1px solid var(--light-gray);">
                <td data-label="Datum" style="vertical-align: middle; padding: 0.75rem 0.5rem;">
                    <div class="submission-date-info">
                        <strong style="display: block; font-size: 0.9rem; margin-bottom: 0.2rem;">
                            ${formatDate(submission.submitted_at)}
                        </strong>
                        <small style="color: var(--medium-gray); font-size: 0.8rem;">
                            ${submission.reference || 'Keine Ref.'}
                        </small>
                    </div>
                </td>
                <td data-label="Service" style="vertical-align: middle; padding: 0.75rem 0.5rem;">
                    <span class="service-badge">
                        ${submission.service_name || 'Unbekannt'}
                    </span>
                </td>
                <td data-label="Kontakt" style="vertical-align: middle; padding: 0.75rem 0.5rem;">
                    <div class="contact-info">
                        <strong>${contactName}</strong>
                        ${contactEmail ? `<small>${contactEmail}</small>` : ''}
                    </div>
                </td>
                <td data-label="Status" style="vertical-align: middle; padding: 0.75rem 0.5rem;">
                    <span class="status-badge ${getStatusClass(submission.status)}">
                        ${getSubmissionStatusText(submission.status)}
                    </span>
                </td>
                <td data-label="Alter" style="vertical-align: middle; padding: 0.75rem 0.5rem; text-align: center;">
                    <span class="days-old ${daysClass}" title="Eingereicht am ${formatDate(submission.submitted_at)}">
                        ${daysOld} Tage
                    </span>
                </td>
                <td data-label="Angebote" style="vertical-align: middle; padding: 0.75rem 0.5rem;">
                    <div class="offers-info">
                        <strong>${offersCount} ${offersCount === 1 ? 'Angebot' : 'Angebote'}</strong>
                        ${offersValue > 0 ? `<small>${formatCurrency(offersValue)}</small>` : ''}
                    </div>
                </td>
                <td data-label="Aktionen" style="vertical-align: middle; padding: 0.75rem 0.5rem;">
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline" onclick="viewArchivedSubmission('${submission.id}')" title="Details anzeigen">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="exportArchivedSubmissionPDF('${submission.id}')" title="PDF exportieren">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button class="btn btn-sm btn-success" onclick="restoreFromArchive('${submission.id}')" title="Zur aktuellen Liste">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Load and display archive statistics
 */
function loadArchiveStats() {
    const daysFilter = document.getElementById('archiveDaysFilter')?.value || '30';
    
    const params = new URLSearchParams({
        action: 'archive-stats',
        days: daysFilter
    });
    
    fetch(`api/admin.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateArchiveStats(data);
            } else {
                console.error('Archive stats error:', data.error);
            }
        })
        .catch(error => {
            console.error('Error loading archive stats:', error);
        });
}

/**
 * Update archive statistics display
 */
function updateArchiveStats(data) {
    const summary = data.summary || {};
    
    // Update stat cards
    document.getElementById('archivedSubmissionsCount').textContent = summary.archived_submissions || 0;
    document.getElementById('archivedOffersCount').textContent = summary.archived_offers || 0;
    document.getElementById('archivedOffersValue').textContent = formatCurrency(summary.total_archived_value || 0);
    document.getElementById('oldestArchiveDate').textContent = summary.oldest_submission ? formatDate(summary.oldest_submission) : '-';
}

/**
 * View archived submission details
 */
function viewArchivedSubmission(submissionId) {
    fetch(`api/admin.php?action=get-submission&id=${submissionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayArchivedSubmissionDetails(data.submission);
            } else {
                showNotification('Fehler beim Laden der Submission-Details: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .catch(error => {
            showNotification('Fehler beim Laden der Submission-Details', 'error');
            console.error('Error:', error);
        });
}

/**
 * Display archived submission details
 */
function displayArchivedSubmissionDetails(submission) {
    const detailsSection = document.getElementById('archivedSubmissionDetails');
    if (!detailsSection) return;
    
    // Update details
    document.getElementById('archivedSubmissionService').textContent = submission.service_name || 'Unbekannt';
    document.getElementById('archivedSubmissionDate').textContent = formatDate(submission.submitted_at);
    document.getElementById('archivedSubmissionAge').textContent = calculateDaysOld(submission.submitted_at) + ' Tage';
    document.getElementById('archivedSubmissionStatus').innerHTML = `<span class="status-badge ${getStatusClass(submission.status)}">${getSubmissionStatusText(submission.status)}</span>`;
    document.getElementById('archivedSubmissionContact').textContent = getContactFromFormData(submission.form_data) || 'Unbekannt';
    document.getElementById('archivedSubmissionReference').textContent = submission.reference || 'Keine Referenz';
    
    // Load answers
    const answersContainer = document.getElementById('archivedSubmissionAnswersList');
    if (submission.form_data) {
        try {
            const formData = JSON.parse(submission.form_data);
            const answers = formData.answers || [];
            
            answersContainer.innerHTML = answers.map(answer => `
                <div class="answer-item">
                    <div class="answer-question">
                        <strong>${answer.question_text}</strong>
                    </div>
                    <div class="answer-text">
                        ${Array.isArray(answer.answer_text) 
                            ? answer.answer_text.join(', ') 
                            : answer.answer_text
                        }
                    </div>
                </div>
            `).join('');
        } catch (error) {
            answersContainer.innerHTML = '<p>Fehler beim Laden der Antworten</p>';
        }
    }
    
    // Show details section
    detailsSection.style.display = 'block';
}

/**
 * Close archived submission details
 */
function closeArchivedSubmissionDetails() {
    const detailsSection = document.getElementById('archivedSubmissionDetails');
    if (detailsSection) {
        detailsSection.style.display = 'none';
    }
}

/**
 * Search archived submissions
 */
function searchArchive() {
    loadArchivedSubmissions();
}

/**
 * Filter archived submissions
 */
function filterArchive() {
    loadArchivedSubmissions();
}

/**
 * Refresh archive data
 */
function refreshArchive() {
    loadArchivedSubmissions();
    loadArchiveStats();
}

/**
 * Export archived submissions
 */
function exportArchivedSubmissions() {
    const params = new URLSearchParams({
        action: 'export-archived-submissions',
        days: document.getElementById('archiveDaysFilter')?.value || '30',
        search: document.getElementById('archiveSearch')?.value || '',
        service_id: document.getElementById('archiveServiceFilter')?.value || '',
        status: document.getElementById('archiveStatusFilter')?.value || ''
    });
    
    window.open(`api/admin.php?${params}`, '_blank');
}

/**
 * Export archived submission PDF
 */
function exportArchivedSubmissionPDF(submissionId) {
    window.open(`api/admin.php?action=export-submission-pdf&id=${submissionId}`, '_blank');
}

/**
 * Restore submission from archive (changes status from 'abgeschlossen' to 'in_bearbeitung')
 */
function restoreFromArchive(submissionId) {
    if (!confirm('Möchten Sie diese Anfrage wirklich zur aktuellen Liste zurückholen?\n\nDer Status wird von "Abgeschlossen" auf "In Bearbeitung" geändert.')) {
        return;
    }
    
    // Get the button for loading state
    const button = document.querySelector(`button[onclick="restoreFromArchive('${submissionId}')"]`);
    const originalHtml = button ? button.innerHTML : '';
    
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
    }
    
    const formData = new FormData();
    formData.append('action', 'restore-submission');
    formData.append('id', submissionId);
    formData.append('csrf_token', admin.csrfToken || '');
    
    fetch('api/admin.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-CSRF-Token': admin.csrfToken || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Anfrage erfolgreich wiederhergestellt!', 'success');
            
            // Remove the row from archive table with animation
            const row = button ? button.closest('tr') : null;
            if (row) {
                row.style.transition = 'opacity 0.3s ease';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    
                    // Check if table is empty
                    const tbody = document.getElementById('archivedSubmissionsTableBody');
                    if (tbody && tbody.children.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="7" class="empty-message">
                                    <div style="text-align: center; padding: 2rem; color: var(--medium-gray);">
                                        <i class="fas fa-archive" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                        <h3 style="margin: 0 0 0.5rem 0; font-weight: 600;">Keine archivierten Anfragen</h3>
                                        <p style="margin: 0; font-size: 0.9rem;">Es sind derzeit keine Anfragen archiviert.</p>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }
                    
                    // Update archive statistics
                    loadArchiveStats();
                }, 300);
            }
            
        } else {
            showNotification('Fehler beim Wiederherstellen: ' + (data.error || 'Unbekannter Fehler'), 'error');
            // Reset button
            if (button) {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('Error restoring submission:', error);
        showNotification('Fehler beim Wiederherstellen der Anfrage', 'error');
        // Reset button  
        if (button) {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    });
}

/**
 * Calculate days old from date string
 */
function calculateDaysOld(dateString) {
    const submissionDate = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - submissionDate);
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

/**
 * Initialize archive when section is loaded
 */
function initializeArchive() {
    // Load service options for filter
    if (window.adminData && window.adminData.services) {
        const serviceFilter = document.getElementById('archiveServiceFilter');
        if (serviceFilter) {
            serviceFilter.innerHTML = '<option value="">Alle Services</option>' +
                window.adminData.services.map(service => 
                    `<option value="${service.id}">${service.name}</option>`
                ).join('');
        }
    }
    
    // Load initial data
    loadArchivedSubmissions();
    loadArchiveStats();
}

// Export functions to global scope
window.loadArchivedSubmissions = loadArchivedSubmissions;
window.viewArchivedSubmission = viewArchivedSubmission;
window.closeArchivedSubmissionDetails = closeArchivedSubmissionDetails;
window.searchArchive = searchArchive;
window.filterArchive = filterArchive;
window.refreshArchive = refreshArchive;
window.exportArchivedSubmissions = exportArchivedSubmissions;
window.exportArchivedSubmissionPDF = exportArchivedSubmissionPDF;
window.restoreFromArchive = restoreFromArchive;
window.initializeArchive = initializeArchive;

// ============================================================================
// URL Hash Navigation Support with Query Parameters
// ============================================================================

/**
 * Parse URL hash and query parameters
 */
function parseUrlHashAndQuery() {
    const fullHash = window.location.hash.substring(1); // Remove the # character
    const [section, queryString] = fullHash.split('?');
    
    const params = new URLSearchParams(queryString || '');
    const query = {};
    
    for (const [key, value] of params) {
        query[key] = value;
    }
    
    return {
        section: section || 'dashboard',
        query: query
    };
}

/**
 * Build URL hash with query parameters
 */
function buildUrlHash(section, query = {}) {
    let hash = section;
    
    const queryParams = new URLSearchParams();
    for (const [key, value] of Object.entries(query)) {
        if (value !== null && value !== undefined && value !== '') {
            queryParams.append(key, value);
        }
    }
    
    const queryString = queryParams.toString();
    if (queryString) {
        hash += '?' + queryString;
    }
    
    return '#' + hash;
}

/**
 * Show specific section and hide others
 */
function showSection(sectionName) {
    // Hide all content sections
    const allSections = document.querySelectorAll('.content-section');
    allSections.forEach(section => {
        section.style.display = 'none';
        section.classList.remove('active');
    });
    
    // Show the requested section
    const sectionId = sectionName + '-section';
    const targetSection = document.getElementById(sectionId);
    
    if (targetSection) {
        targetSection.style.display = 'block';
        targetSection.classList.add('active');
        
        // Scroll to top of content
        const adminContent = document.querySelector('.admin-content');
        if (adminContent) {
            adminContent.scrollTop = 0;
        }
    } else {
        console.warn(`Section not found: ${sectionId}`);
    }
}

/**
 * Navigate to section based on URL hash with query support
 */
function navigateToHashSection() {
    const { section, query } = parseUrlHashAndQuery();
    
    if (!section || section === '') {
        // No section, show dashboard by default
        showSection('dashboard');
        return;
    }
    
    // Map of hash values to section IDs
    const hashToSection = {
        'dashboard': 'dashboard-section',
        'statistics': 'statistics-section',
        'services': 'services-section',
        'service-pages': 'service-pages-section',
        'questionnaires': 'questionnaires-section',
        'questions': 'questions-section',
        'submissions': 'submissions-section',
        'submission-archive': 'submission-archive-section',
        'archive': 'submission-archive-section', // Alias for archive
        'email-inbox': 'email-inbox-section',
        'media': 'media-section',
        'emails': 'emails-section',
        'settings': 'settings-section',
        'users': 'users-section',
        'backup': 'backup-section',
        'logs': 'logs-section'
    };
    
    // Check if the hash corresponds to a valid section
    if (hashToSection[section]) {
        const sectionId = hashToSection[section];
        showSection(section);
        
        // Update sidebar active state
        updateSidebarActiveState(section);
        
        // Special handling for certain sections with query parameters
        handleSpecialSectionLoad(section, query);
    } else {
        // Invalid hash, default to dashboard
        window.location.hash = '#dashboard';
        showSection('dashboard');
    }
}

/**
 * Update sidebar navigation active state
 */
function updateSidebarActiveState(activeSection) {
    // Remove active class from all menu links
    const menuLinks = document.querySelectorAll('.sidebar-menu .menu-link');
    menuLinks.forEach(link => link.classList.remove('active'));
    
    // Add active class to current section
    const activeMenuLink = document.querySelector(`.sidebar-menu .menu-link[data-section="${activeSection}"]`);
    if (activeMenuLink) {
        activeMenuLink.classList.add('active');
    }
}

/**
 * Handle special loading for certain sections with query parameter support
 */
function handleSpecialSectionLoad(section, query = {}) {
    switch (section) {
        case 'submissions':
            // Load submissions data
            if (typeof loadSubmissions === 'function') {
                loadSubmissions();
            }
            
            // Handle submission-specific queries
            if (query.id) {
                setTimeout(() => {
                    if (typeof viewSubmission === 'function') {
                        viewSubmission(query.id);
                    }
                }, 500);
            }
            if (query.status) {
                setTimeout(() => {
                    const statusFilter = document.getElementById('submissionsStatusFilter');
                    if (statusFilter) {
                        statusFilter.value = query.status;
                        if (typeof filterSubmissions === 'function') {
                            filterSubmissions();
                        }
                    }
                }, 500);
            }
            break;
            
        case 'submission-archive':
        case 'archive':
            // Initialize archive
            if (typeof initializeArchive === 'function') {
                initializeArchive();
            }
            
            // Handle archive-specific queries
            if (query.days) {
                setTimeout(() => {
                    const daysFilter = document.getElementById('archiveDaysFilter');
                    if (daysFilter) {
                        daysFilter.value = query.days;
                        if (typeof filterArchive === 'function') {
                            filterArchive();
                        }
                    }
                }, 500);
            }
            break;
            
        case 'questionnaires':
            // Load questionnaires
            if (typeof loadQuestionnaires === 'function') {
                loadQuestionnaires();
            }
            
            // Handle questionnaire-specific queries
            if (query.id) {
                setTimeout(() => {
                    if (typeof editQuestionnaire === 'function') {
                        editQuestionnaire(query.id);
                    }
                }, 500);
            }
            
            // Handle service filter for questionnaires
            if (query.service_id || query.service) {
                setTimeout(() => {
                    const serviceFilter = document.getElementById('questionnaireServiceFilter');
                    if (serviceFilter) {
                        const serviceValue = query.service_id || query.service;
                        
                        // Find the correct option
                        let optionFound = false;
                        for (let option of serviceFilter.options) {
                            if (option.dataset.serviceId === serviceValue || option.value === serviceValue) {
                                serviceFilter.value = option.value;
                                optionFound = true;
                                break;
                            }
                        }
                        
                        if (optionFound && typeof filterQuestionnaires === 'function') {
                            filterQuestionnaires();
                        }
                    }
                }, 500);
            }
            
            // Handle status filter for questionnaires  
            if (query.status) {
                setTimeout(() => {
                    const statusFilter = document.getElementById('questionnaireStatusFilter');
                    if (statusFilter) {
                        statusFilter.value = query.status;
                        if (typeof filterQuestionnaires === 'function') {
                            filterQuestionnaires();
                        }
                    }
                }, 500);
            }
            
            // Handle questionnaire creation for specific service
            if (query.create && (query.service_id || query.service)) {
                setTimeout(() => {
                    if (typeof showCreateQuestionnaireModal === 'function') {
                        showCreateQuestionnaireModal();
                        
                        // Pre-select the service in the creation modal
                        const serviceSelect = document.getElementById('questionnaireService');
                        if (serviceSelect) {
                            const serviceValue = query.service_id || query.service;
                            for (let option of serviceSelect.options) {
                                if (option.dataset.serviceId === serviceValue || option.value === serviceValue) {
                                    serviceSelect.value = option.value;
                                    break;
                                }
                            }
                        }
                    }
                }, 500);
            }
            break;
            
        case 'services':
            // Load services
            if (typeof loadServices === 'function') {
                loadServices();
            }
            
            // Handle service-specific queries
            if (query.id) {
                setTimeout(() => {
                    if (typeof editService === 'function') {
                        editService(query.id);
                    }
                }, 500);
            }
            break;
            
        case 'service-pages':
            // Handle service page queries - this is the main feature requested
            if (query.service_id || query.service) {
                setTimeout(() => {
                    const serviceSelect = document.getElementById('servicePageSelect');
                    if (serviceSelect) {
                        // Try service_id first, then service slug
                        const serviceValue = query.service_id || query.service;
                        
                        // Find the correct option value
                        let optionFound = false;
                        
                        // First try to match by service ID (data-service-id attribute)
                        if (query.service_id) {
                            for (let option of serviceSelect.options) {
                                if (option.dataset.serviceId === serviceValue) {
                                    serviceSelect.value = option.value;
                                    optionFound = true;
                                    break;
                                }
                            }
                        }
                        
                        // If not found by ID, try by slug (option value)
                        if (!optionFound && query.service) {
                            for (let option of serviceSelect.options) {
                                if (option.value === serviceValue) {
                                    serviceSelect.value = option.value;
                                    optionFound = true;
                                    break;
                                }
                            }
                        }
                        
                        // If still not found, try by name (case insensitive)
                        if (!optionFound) {
                            for (let option of serviceSelect.options) {
                                if (option.textContent.toLowerCase().includes(serviceValue.toLowerCase())) {
                                    serviceSelect.value = option.value;
                                    optionFound = true;
                                    break;
                                }
                            }
                        }
                        
                        if (optionFound) {
                            // Trigger the change event to load the service content
                            serviceSelect.dispatchEvent(new Event('change'));
                            
                            if (typeof loadServicePageContent === 'function') {
                                loadServicePageContent();
                            }
                        } else {
                            console.warn(`Service not found: ${serviceValue}`);
                            // List available options for debugging
                            const availableOptions = Array.from(serviceSelect.options)
                                .filter(opt => opt.value)
                                .map(opt => ({ value: opt.value, id: opt.dataset.serviceId, name: opt.textContent }));
                        }
                    } else {
                        console.warn('Service select element not found');
                    }
                }, 500);
            }
            break;
            
        case 'emails':
            // Check if section exists (even if hidden)
            const emailSection = document.getElementById('emails-section');
            
            if (emailSection) {
                // Make sure section is visible
                emailSection.style.display = 'block';
                
                // Wait a moment for DOM updates
                setTimeout(() => {
                    const emailContainer = document.getElementById('email-templates-container');
                    
                    if (emailContainer && typeof loadEmailTemplates === 'function') {
                        loadEmailTemplates();
                    } else {
                        console.error('Email container or loadEmailTemplates function not found');
                    }
                }, 200);
            } else {
                console.error('Email section not found in DOM');
            }
            
            // Handle template-specific queries for emails section
            if (query.template) {
                setTimeout(() => {
                    if (typeof editEmailTemplate === 'function') {
                        editEmailTemplate(query.template);
                    }
                }, 500);
            }
            break;
            
        case 'questions':
            // Load questions
            if (typeof loadQuestions === 'function') {
                loadQuestions();
            }
            
            // Handle question-specific queries
            if (query.type) {
                setTimeout(() => {
                    const typeFilter = document.getElementById('questionTypeFilter');
                    if (typeFilter) {
                        typeFilter.value = query.type;
                        if (typeof filterQuestions === 'function') {
                            filterQuestions();
                        }
                    }
                }, 500);
            }
            break;
    }
}

/**
 * Update URL hash with optional query parameters
 */
function updateUrlHash(section, query = {}) {
    // Build the new hash with query parameters
    const newHash = buildUrlHash(section, query);
    
    if (window.location.hash !== newHash) {
        window.location.hash = newHash;
    }
}

/**
 * Enhanced showSection function with hash and query support
 */
function showSectionWithHash(section, query = {}) {
    showSection(section);
    updateUrlHash(section, query);
    updateSidebarActiveState(section);
    handleSpecialSectionLoad(section, query);
}

/**
 * Navigation helper functions for common scenarios
 */
function navigateToServicePage(serviceId) {
    updateUrlHash('service-pages', { service_id: serviceId });
}

function navigateToSubmission(submissionId) {
    updateUrlHash('submissions', { id: submissionId });
}

function navigateToArchiveWithFilter(days) {
    updateUrlHash('submission-archive', { days: days });
}

function navigateToQuestionnaire(questionnaireId) {
    updateUrlHash('questionnaires', { id: questionnaireId });
}

function navigateToQuestionnairesByService(serviceId) {
    updateUrlHash('questionnaires', { service_id: serviceId });
}

function navigateToQuestionnairesByStatus(status) {
    updateUrlHash('questionnaires', { status: status });
}

function navigateToCreateQuestionnaireForService(serviceId) {
    updateUrlHash('questionnaires', { create: 'true', service_id: serviceId });
}

// ============================================================================
// Initialize Hash Navigation
// ============================================================================

// Listen for hash changes
window.addEventListener('hashchange', navigateToHashSection);

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Email Template Form Handler
    const emailTemplateForm = document.getElementById('emailTemplateForm');
    if (emailTemplateForm) {
        emailTemplateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveEmailTemplate();
        });
    }
    
    // Wait a bit for other initializations to complete
    setTimeout(() => {
        navigateToHashSection();
    }, 100);
});

// Override existing navigation clicks to use hash navigation
document.addEventListener('click', function(e) {
    const menuLink = e.target.closest('.menu-link[data-section]');
    if (menuLink) {
        e.preventDefault();
        const section = menuLink.getAttribute('data-section');
        updateUrlHash(section);
        // The hashchange event will handle the actual navigation
    }
});

// Export hash navigation functions
window.navigateToHashSection = navigateToHashSection;
window.updateUrlHash = updateUrlHash;
window.showSectionWithHash = showSectionWithHash;
window.showSection = showSection;
window.parseUrlHashAndQuery = parseUrlHashAndQuery;
window.buildUrlHash = buildUrlHash;
window.navigateToServicePage = navigateToServicePage;
window.navigateToSubmission = navigateToSubmission;
window.navigateToArchiveWithFilter = navigateToArchiveWithFilter;
window.navigateToQuestionnaire = navigateToQuestionnaire;
window.navigateToQuestionnairesByService = navigateToQuestionnairesByService;
window.navigateToQuestionnairesByStatus = navigateToQuestionnairesByStatus;
window.navigateToCreateQuestionnaireForService = navigateToCreateQuestionnaireForService;

// ============================================================================
// User Menu Functionality
// ============================================================================

// Initialize user menu dropdown
document.addEventListener('DOMContentLoaded', function() {
    const userMenuToggle = document.getElementById('userMenuToggle');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuToggle && userDropdown) {
        userMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            userDropdown.classList.toggle('show');
            userMenuToggle.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuToggle.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
                userMenuToggle.classList.remove('active');
            }
        });
        
        // Close dropdown on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                userDropdown.classList.remove('show');
                userMenuToggle.classList.remove('active');
            }
        });
    }
});

// ============================================================================
// Authentication Check
// ============================================================================

// Check authentication status periodically
setInterval(function() {
    checkAuthStatus();
}, 300000); // Check every 5 minutes

async function checkAuthStatus() {
    try {
        const response = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check-session'
        });
        
        const data = await response.json();
        
        if (!data.success || !data.authenticated) {
            // Session expired, redirect to login
            window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.pathname);
        }
    } catch (error) {
        console.warn('Auth check failed:', error);
    }
}

// ============================================================================
// Password Change Functions
// ============================================================================

/**
 * Open password change modal
 */
function openPasswordChangeModal(event) {
    
    if (event) {
        event.preventDefault();
    }
    const modal = document.getElementById('passwordChangeModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('show');
        
        // Reset form
        const form = document.getElementById('passwordChangeForm');
        if (form) {
            form.reset();
        }
        
        // Hide password strength indicator
        const strengthDiv = document.getElementById('passwordStrength');
        if (strengthDiv) {
            strengthDiv.style.display = 'none';
        }
        
        // Focus on current password field
        const currentPasswordInput = document.getElementById('currentPassword');
        if (currentPasswordInput) {
            setTimeout(() => {
                currentPasswordInput.focus();
            }, 100);
        }
    } else {
        console.error('❌ Password change modal not found in DOM!');
    }
}

/**
 * Close password change modal
 */
function closePasswordChangeModal() {
    const modal = document.getElementById('passwordChangeModal');
    if (modal) {
        modal.classList.remove('show');
        // Use setTimeout to allow CSS transition to complete
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
        
        // Reset form
        const form = document.getElementById('passwordChangeForm');
        if (form) {
            form.reset();
        }
        
        // Hide password strength indicator
        const strengthDiv = document.getElementById('passwordStrength');
        if (strengthDiv) {
            strengthDiv.style.display = 'none';
        }
    }
}

/**
 * Check password strength
 */
function checkPasswordStrength(password) {
    const strengthDiv = document.getElementById('passwordStrength');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    
    if (!password || password.length === 0) {
        strengthDiv.style.display = 'none';
        return;
    }
    
    strengthDiv.style.display = 'block';
    
    let score = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 8) score += 1;
    else feedback.push('mindestens 8 Zeichen');
    
    // Lowercase check
    if (/[a-z]/.test(password)) score += 1;
    else feedback.push('Kleinbuchstaben');
    
    // Uppercase check
    if (/[A-Z]/.test(password)) score += 1;
    else feedback.push('Großbuchstaben');
    
    // Number check
    if (/\d/.test(password)) score += 1;
    else feedback.push('Zahlen');
    
    // Special character check
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) score += 1;
    
    // Update strength bar
    const percentage = (score / 5) * 100;
    strengthFill.style.width = percentage + '%';
    
    // Update colors and text
    if (score <= 2) {
        strengthFill.style.backgroundColor = '#dc3545'; // red
        strengthText.textContent = 'Schwach - benötigt: ' + feedback.join(', ');
        strengthText.style.color = '#dc3545';
    } else if (score <= 3) {
        strengthFill.style.backgroundColor = '#ffc107'; // yellow
        strengthText.textContent = 'Mittel - fehlt: ' + feedback.join(', ');
        strengthText.style.color = '#ffc107';
    } else if (score <= 4) {
        strengthFill.style.backgroundColor = '#28a745'; // green
        strengthText.textContent = 'Stark';
        strengthText.style.color = '#28a745';
    } else {
        strengthFill.style.backgroundColor = '#007bff'; // blue
        strengthText.textContent = 'Sehr stark';
        strengthText.style.color = '#007bff';
    }
    
    return score;
}

/**
 * Change password
 */
async function changePassword(event) {
    event.preventDefault();
    
    const currentPassword = document.getElementById('currentPassword').value.trim();
    const newPassword = document.getElementById('newPassword').value.trim();
    const confirmPassword = document.getElementById('confirmPassword').value.trim();
    
    // Validate inputs
    if (!currentPassword) {
        showNotification('Bitte geben Sie Ihr aktuelles Passwort ein', 'error');
        return false;
    }
    
    if (!newPassword) {
        showNotification('Bitte geben Sie ein neues Passwort ein', 'error');
        return false;
    }
    
    if (newPassword !== confirmPassword) {
        showNotification('Die neuen Passwörter stimmen nicht überein', 'error');
        return false;
    }
    
    if (newPassword.length < 8) {
        showNotification('Das neue Passwort muss mindestens 8 Zeichen lang sein', 'error');
        return false;
    }
    
    if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(newPassword)) {
        showNotification('Das neue Passwort muss mindestens einen Kleinbuchstaben, einen Großbuchstaben und eine Zahl enthalten', 'error');
        return false;
    }
    
    if (currentPassword === newPassword) {
        showNotification('Das neue Passwort muss sich vom aktuellen Passwort unterscheiden', 'error');
        return false;
    }
    
    try {
        const response = await fetch('/api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'change-password',
                currentPassword: currentPassword,
                newPassword: newPassword,
                confirmPassword: confirmPassword
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Passwort wurde erfolgreich geändert', 'success');
            closePasswordChangeModal();
        } else {
            showNotification(data.message || 'Fehler beim Ändern des Passworts', 'error');
        }
        
    } catch (error) {
        console.error('Password change error:', error);
        showNotification('Fehler beim Ändern des Passworts', 'error');
    }
    
    return false;
}

// Add event listeners for password strength checking
document.addEventListener('DOMContentLoaded', function() {
    
    // Check if modal exists
    const passwordModal = document.getElementById('passwordChangeModal');
    
    const newPasswordInput = document.getElementById('newPassword');
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });
    }
    
    // Close modal when clicking outside
    if (passwordModal) {
        passwordModal.addEventListener('click', function(event) {
            if (event.target === passwordModal) {
                closePasswordChangeModal();
            }
        });
    }
});

// ============================================================================
// Global Test Function
// ============================================================================

/**
 * Test function to debug password modal - call from browser console
 */
window.testPasswordModal = function() {    
    const modal = document.getElementById('passwordChangeModal');
    // Try to open modal directly
    if (typeof openPasswordChangeModal === 'function') {
        openPasswordChangeModal();
    } else {
        console.error('❌ openPasswordChangeModal function not found!');
    }
};

// ============================================================================
// E-Mail Inbox Management
// ============================================================================

/**
 * E-Mail Inbox Class
 */
class EmailInboxManager {
    constructor() {
        this.currentFolder = 'inbox';
        this.selectedEmail = null;
        this.emails = [];
        this.currentFilter = 'all';
        this.searchTerm = '';
        this.activeController = null; // Track active fetch controller
        this.activeTimeouts = new Set(); // Track active timeouts
        this.cleanup = this.cleanup.bind(this);
        
        // ⚡ Performance optimization: Email details cache
        this.emailCache = new Map();
        this.CACHE_DURATION = 5 * 60 * 1000; // 5 minutes
        this.prefetchQueue = [];
        this.isPrefetching = false;
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', this.cleanup);
        window.addEventListener('unload', this.cleanup);
        
        // ⚡ Start periodic cache cleanup (every 2 minutes)
        this.cacheCleanupInterval = setInterval(() => {
            this.cleanupCache();
        }, 2 * 60 * 1000);
    }
    
    /**
     * Centralized cleanup function
     */
    cleanup() {
        
        // Abort active controller
        if (this.activeController) {
            this.activeController.abort();
            this.activeController = null;
        }
        
        // Clear all active timeouts
        this.activeTimeouts.forEach(timeoutId => {
            clearTimeout(timeoutId);
        });
        this.activeTimeouts.clear();
        
        // Clear cache cleanup interval
        if (this.cacheCleanupInterval) {
            clearInterval(this.cacheCleanupInterval);
            this.cacheCleanupInterval = null;
        }
        
        // Clear email cache
        this.emailCache.clear();
        this.prefetchQueue = [];
    }
    
    /**
     * Debug function to check email structure
     */
    async debugEmailStructure() {
        try {
            
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'debug-email-structure'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.table(data.sample_emails);
                return data;
            } else {
                console.error('❌ Debug failed:', data.error);
                return null;
            }
            
        } catch (error) {
            console.error('❌ Error debugging email structure:', error);
            return null;
        }
    }
    
    /**
     * Initialize email inbox with debugging
     */
    async init() {        
        try {
            // Initialize scroll containers first
            this.initializeScrollContainers();
            
            // Load emails
            await this.loadEmails();
            
            // Update unread count
            await this.updateUnreadCount();
        } catch (error) {
            console.error('❌ Error initializing email inbox:', error);
            this.showError('Fehler beim Initialisieren des E-Mail-Posteingangs');
        }
    }
    
    /**
     * Load emails from server with retry logic
     */
    async loadEmails(retryCount = 0) {
        const maxRetries = 3;
        const retryDelay = Math.min(1000 * Math.pow(2, retryCount), 5000); // Exponential backoff, max 5s
        let timeoutId = null;
        
        try {
            const emailList = document.getElementById('emailList');
            if (emailList) {
                const retryText = retryCount > 0 ? ` (Versuch ${retryCount + 1}/${maxRetries + 1})` : '';
                emailList.innerHTML = `<div class="loading-message"><i class="fas fa-spinner fa-spin"></i> Lade E-Mails...${retryText}</div>`;
            }
            
            // Cleanup any previous request
            if (this.activeController) {
                this.activeController.abort();
            }
            
            // Create new controller
            this.activeController = new AbortController();
            timeoutId = setTimeout(() => {
                console.warn('⚠️ Email loading timeout after 30 seconds');
                this.activeController?.abort();
            }, 30000); // 30 seconds timeout
            
            this.activeTimeouts.add(timeoutId);
            
            const response = await fetch(`api/admin.php?action=inbox-emails&folder=${this.currentFolder}&limit=20`, {
                signal: this.activeController.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                }
            });
            
            // Clear timeout on success
            this.activeTimeouts.delete(timeoutId);
            clearTimeout(timeoutId);
            timeoutId = null;
            
            // Clear the active controller
            this.activeController = null;
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const text = await response.text();
            
            // Check if response is empty
            if (!text || text.trim() === '') {
                console.error('❌ Empty response from server');
                
                // Retry if we haven't exceeded max retries
                if (retryCount < maxRetries) {
                    await new Promise(resolve => setTimeout(resolve, retryDelay));
                    return this.loadEmails(retryCount + 1);
                }
                
                this.showError('Leere Antwort vom Server. E-Mail-Service möglicherweise nicht verfügbar.');
                this.loadFallbackEmails();
                return;
            }
            
            // Check if response is HTML (error page) - but NOT if it's JSON with HTML content
            if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html>') || 
                (text.includes('<br />') && !text.trim().startsWith('{') && !text.includes('{"success"'))) {
                console.error('❌ Server returned HTML instead of JSON - possible PHP error');
                
                // Don't show fallback message immediately - check if it's a real error first
                if (text.includes('Fatal error') || text.includes('Parse error')) {
                    this.showError('PHP-Fehler beim Laden der E-Mails. Siehe Konsole für Details.');
                } else {
                    this.showError('Server-Fehler beim Laden der E-Mails. Bitte Konsole prüfen.');
                }
                
                // Try to load fallback data only as last resort
                this.loadFallbackEmails();
                return;
            }
            
            let data;
            try {
                // Handle PHP warnings before JSON by extracting JSON part
                let jsonText = text;
                if (text.includes('<br />') && text.includes('{"success"')) {
                    // Extract JSON part after PHP warnings
                    const jsonStart = text.indexOf('{"success"');
                    if (jsonStart > 0) {
                        jsonText = text.substring(jsonStart);
                        console.warn('⚠️ PHP warnings detected before JSON response, extracting JSON part');
                    }
                }
                
                data = JSON.parse(jsonText);
            } catch (parseError) {
                console.error('❌ JSON Parse Error:', parseError);
                console.error('❌ Raw response that failed to parse:', text);
                this.showError('Ungültige JSON-Antwort vom Server.');
                this.loadFallbackEmails();
                return;
            }
            
            if (data.success) {
                this.emails = data.emails;
                this.displayEmails();
                
                // Display Event Sourcing information if available
                if (data.loading_method) {
                    if (data.event_stats) {
                        this.displayEventStoreInfo(data.event_stats, data.loading_method);
                    }
                }
            } else {
                throw new Error(data.message || 'Fehler beim Laden der E-Mails');
            }
            
        } catch (error) {
            // Cleanup timeout if still active
            if (timeoutId) {
                this.activeTimeouts.delete(timeoutId);
                clearTimeout(timeoutId);
            }
            
            // Clear active controller
            this.activeController = null;
            
            console.error('Error loading emails:', error);
            
            // Handle specific error types
            if (error.name === 'AbortError') {
                console.warn('⚠️ Request was aborted (timeout or user navigation)');
                this.showError('E-Mail-Laden wurde abgebrochen. Bitte versuchen Sie es erneut.');
                return;
            }
            
            if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
                this.showError('Netzwerkfehler beim Laden der E-Mails. Bitte prüfen Sie Ihre Internetverbindung.');
                return;
            }
            
            this.showError('Fehler beim Laden der E-Mails: ' + error.message);
        }
    }

    /**
     * Load fallback emails when IMAP is not available
     */
    loadFallbackEmails() {
        // Mock emails for testing
        this.emails = [
            {
                id: 1,
                subject: 'E-Mail-System - Konfiguration erforderlich',
                from: 'system@ds-allroundservice.de',
                date: new Date().toISOString(),
                read: false,
                has_attachments: false,
                body_preview: 'Das E-Mail-System benötigt die PHP IMAP-Erweiterung für den Zugriff auf echte E-Mails. Derzeit werden Testdaten angezeigt.'
            },
            {
                id: 2,
                subject: 'Willkommen im E-Mail-Posteingang',
                from: 'info@ds-allroundservice.de',
                date: new Date(Date.now() - 3600000).toISOString(),
                read: true,
                has_attachments: false,
                body_preview: 'Dies ist eine Beispiel-E-Mail zur Demonstration der Benutzeroberfläche.'
            }
        ];
        
        this.displayEmails();
    }
    
    /**
     * Display emails in the list
     */
    displayEmails() {
        const emailList = document.getElementById('emailList');
        if (!emailList) return;
        
        // Filter emails
        let filteredEmails = this.emails;
        
        // Apply search filter
        if (this.searchTerm) {
            filteredEmails = filteredEmails.filter(email => 
                email.subject.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
                email.from.some(sender => sender.email.toLowerCase().includes(this.searchTerm.toLowerCase()) || 
                                         sender.name.toLowerCase().includes(this.searchTerm.toLowerCase())) ||
                email.body_preview.toLowerCase().includes(this.searchTerm.toLowerCase())
            );
        }
        
        // Apply type filter
        switch (this.currentFilter) {
            case 'unread':
                filteredEmails = filteredEmails.filter(email => email.unread);
                break;
            case 'flagged':
                filteredEmails = filteredEmails.filter(email => email.flagged);
                break;
            case 'attachments':
                filteredEmails = filteredEmails.filter(email => email.has_attachments);
                break;
        }
        
        if (filteredEmails.length === 0) {
            emailList.innerHTML = '<div class="empty-state">Keine E-Mails gefunden</div>';
            return;
        }
        
        const emailsHtml = filteredEmails.map(email => {
            const sender = email.from.length > 0 ? email.from[0] : { name: 'Unbekannt', email: '' };
            const senderDisplay = sender.name || sender.email;
            
            return `
                <div class="email-item ${email.unread ? 'unread' : ''} ${email.id === this.selectedEmail?.id ? 'selected' : ''}" 
                     onclick="window.emailInbox.selectEmail(${email.id})" data-email-id="${email.id}">
                    <div class="email-item-header">
                        <span class="email-sender" title="${sender.email}">${senderDisplay}</span>
                        <span class="email-date">${this.formatDate(email.date)}</span>
                        ${email.unread ? '<span class="unread-indicator" title="Ungelesen">●</span>' : ''}
                        ${email.has_attachments ? '<i class="fas fa-paperclip email-attachment-icon"></i>' : ''}
                        ${email.flagged ? '<i class="fas fa-star email-flag-icon"></i>' : ''}
                    </div>
                    <div class="email-subject">${email.subject}</div>
                    <div class="email-preview-snippet">${email.body_preview}</div>
                </div>
            `;
        }).join('');
        
        emailList.innerHTML = emailsHtml;
    }
    
    /**
     * Select and display email
     */
    async selectEmail(emailId) {
        try {            
            // Find email in current list
            const email = this.emails.find(e => e.id === emailId);
            if (!email) {
                console.error('Email not found:', emailId);
                return;
            }
            
            // ⚡ OPTIMISTIC UI: Update selection immediately
            this.updateEmailSelection(emailId);
            
            // ⚡ CACHE CHECK: Load from cache if available
            const cached = this.emailCache.get(emailId);
            if (cached && Date.now() - cached.timestamp < this.CACHE_DURATION) {
                this.selectedEmail = cached.email;
                this.displayEmailPreview(this.selectedEmail);
                
                // Mark as read in background if needed
                if (email.unread) {
                    this.markEmailAsRead(emailId).then(() => {
                        email.unread = false;
                        this.selectedEmail.unread = false; // ← FIX: Update selectedEmail too!
                        
                        // ← FIX: Update cache too!
                        this.emailCache.set(emailId, {
                            email: this.selectedEmail,
                            timestamp: Date.now()
                        });
                        
                        this.updateUnreadCount();
                        this.updateEmailVisualStatus(emailId, false); // ← Update button
                    });
                }
                
                // Prefetch adjacent emails in background
                this.prefetchAdjacentEmails(emailId);
                return;
            }
            
            // ⚡ LOADING STATE: Show loading indicator
            const container = document.getElementById('emailPreviewContainer');
            if (container) {
                container.innerHTML = `
                    <div class="email-loading">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <p>E-Mail wird geladen...</p>
                    </div>
                `;
            }
            
            // Get detailed email data from server
            const startTime = performance.now();
            const response = await fetch(`api/admin.php?action=email-details&id=${emailId}`, {
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('❌ JSON Parse Error:', parseError);
                console.error('❌ Full response:', text);
                throw new Error('Ungültige JSON-Antwort vom Server');
            }
            
            const loadTime = Math.round(performance.now() - startTime)
            
            if (data.success) {
                
                this.selectedEmail = data.email;
                
                // ⚡ CACHE: Store in cache for future access
                this.emailCache.set(emailId, {
                    email: data.email,
                    timestamp: Date.now()
                });
                
                this.displayEmailPreview(this.selectedEmail);
                
                // Mark as read if unread
                if (email.unread) {
                    await this.markEmailAsRead(emailId);
                    email.unread = false;
                    this.selectedEmail.unread = false; // ← FIX: Update selectedEmail too!
                    
                    // ← FIX: Update cache too!
                    this.emailCache.set(emailId, {
                        email: this.selectedEmail,
                        timestamp: Date.now()
                    });
                    
                    await this.updateUnreadCount();
                    this.updateEmailVisualStatus(emailId, false); // ← Update button
                }
                
                // ⚡ PREFETCH: Load adjacent emails in background
                this.prefetchAdjacentEmails(emailId);
                
            } else {
                console.error('❌ Server returned success=false');
                console.error('❌ Error message:', data.message || data.error);
                console.error('❌ Full response data:', data);
                
                // Show error in preview container
                if (container) {
                    const errorMsg = data.message || data.error || 'Unbekannter Fehler';
                    container.innerHTML = `
                        <div class="email-loading" style="color: var(--danger-color);">
                            <i class="fas fa-exclamation-triangle fa-3x"></i>
                            <p><strong>Fehler beim Laden der E-Mail</strong></p>
                            <p>${errorMsg}</p>
                        </div>
                    `;
                }
                
                throw new Error(data.message || data.error || 'Fehler beim Laden der E-Mail-Details');
            }
            
        } catch (error) {
            console.error('❌ Error selecting email:', error);
            console.error('❌ Error stack:', error.stack);
            
            // Show user-friendly error
            const container = document.getElementById('emailPreviewContainer');
            if (container) {
                container.innerHTML = `
                    <div class="email-loading" style="color: var(--danger-color);">
                        <i class="fas fa-exclamation-triangle fa-3x"></i>
                        <p><strong>Fehler beim Öffnen der E-Mail</strong></p>
                        <p>${error.message}</p>
                        <button class="btn btn-primary" onclick="window.emailInbox.selectEmail(${emailId})">
                            <i class="fas fa-redo"></i> Erneut versuchen
                        </button>
                    </div>
                `;
            }
            
            this.showError('Fehler beim Öffnen der E-Mail: ' + error.message);
        }
    }
    
    /**
     * Display email preview
     */
    displayEmailPreview(email) {
        const container = document.getElementById('emailPreviewContainer');
        if (!container) return;
        
        const sender = email.from.length > 0 ? email.from[0] : { name: 'Unbekannt', email: '' };
        const recipients = email.to.map(to => to.full).join(', ');
        const ccRecipients = email.cc && email.cc.length > 0 ? email.cc.map(cc => cc.full).join(', ') : '';
        
        // Attachments list
        const attachmentsHtml = email.attachments && email.attachments.length > 0 ? `
            <div class="email-attachments">
                <h4><i class="fas fa-paperclip"></i> Anhänge (${email.attachments.length})</h4>
                <div class="attachment-list">
                    ${email.attachments.map((att, index) => `
                        <div class="attachment-item">
                            <div class="attachment-info">
                                <i class="fas fa-file"></i>
                                <span class="attachment-name">${att.filename}</span>
                                <span class="attachment-size">${this.formatFileSize(att.size)}</span>
                            </div>
                            <button class="btn btn-sm btn-primary" 
                                    onclick="window.emailInbox.downloadAttachment('${email.uid}', ${index}, '${att.filename}')"
                                    title="Anhang herunterladen">
                                <i class="fas fa-download"></i> Herunterladen
                            </button>
                        </div>
                    `).join('')}
                </div>
            </div>
        ` : '';
        
        // Generate read/unread button separately to avoid template string issues
        let readStatusButton;
        if (email.unread) {
            readStatusButton = `<button class="btn btn-warning" onclick="window.emailInbox.toggleReadStatus(${email.id})" title="Als gelesen markieren"><i class="fas fa-envelope-open"></i> Als gelesen markieren</button>`;
        } else {
            readStatusButton = `<button class="btn btn-success" onclick="window.emailInbox.toggleReadStatus(${email.id})" title="Als ungelesen markieren"><i class="fas fa-envelope"></i> Als ungelesen markieren</button>`;
        }
        
        container.innerHTML = `
            <div class="email-preview">
                <div class="email-preview-header">
                    <div class="email-preview-subject">${email.subject}</div>
                    <div class="email-preview-meta">
                        <div class="meta-row">
                            <span class="label">Von:</span>
                            <span>${sender.full}</span>
                        </div>
                        <div class="meta-row">
                            <span class="label">An:</span>
                            <span>${recipients}</span>
                        </div>
                        ${ccRecipients ? `
                        <div class="meta-row">
                            <span class="label">CC:</span>
                            <span>${ccRecipients}</span>
                        </div>
                        ` : ''}
                        <div class="meta-row">
                            <span class="label">Datum:</span>
                            <span>${this.formatDateTime(email.date)}</span>
                        </div>
                    </div>
                </div>
                <div class="email-preview-content">
                    ${email.body}
                </div>
                ${attachmentsHtml}
            </div>
            <div class="email-preview-actions">
                <button class="btn btn-primary" onclick="window.emailInbox.replyToEmail(${email.id})">
                    <i class="fas fa-reply"></i> Antworten
                </button>
                <button class="btn btn-outline" onclick="window.emailInbox.forwardEmail(${email.id})">
                    <i class="fas fa-share"></i> Weiterleiten
                </button>
                ${readStatusButton}
                <button class="btn btn-danger" onclick="window.emailInbox.deleteEmail(${email.id})">
                    <i class="fas fa-trash"></i> Löschen
                </button>
            </div>
        `;
    }
    
    /**
     * Update email selection in list
     */
    updateEmailSelection(emailId = null) {
        const emailItems = document.querySelectorAll('.email-item');
        const targetId = emailId || (this.selectedEmail ? this.selectedEmail.id : null);
        
        emailItems.forEach(item => {
            const itemId = parseInt(item.dataset.emailId);
            if (itemId === targetId) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    }
    
    /**
     * Mark email as read
     */
    async markEmailAsRead(emailId) {        
        try {
            // Find email in current list
            const email = this.emails.find(e => e.id === emailId);
            if (email) {
                const wasUnread = email.unread;
                if (!wasUnread) {
                    return;
                }
            }
            
            const requestBody = {
                action: 'mark-email-read',
                id: emailId,
                csrf_token: admin.csrfToken || ''
            };
            
            const response = await fetch('api/admin.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': admin.csrfToken || ''
                },
                body: JSON.stringify(requestBody)
            });
            
            const data = await response.json();
            if (!data.success) {
                console.error('❌ API returned error:', data.error || data.message);
                throw new Error(data.error || data.message);
            }
            
            // Update email status locally
            if (email) {
                email.unread = false;
                
                // Update visual appearance immediately
                this.updateEmailVisualStatus(emailId, false);
            }
            
            // Update unread count
            await this.updateUnreadCount();
        } catch (error) {
            throw error;
        }
    }
    
    /**
     * Toggle read status
     */
    async toggleReadStatus(emailId) {
        
        const email = this.emails.find(e => e.id === emailId);
        if (!email) {
            console.error('❌ Email not found with ID:', emailId);
            return;
        }
        
        // Show immediate visual feedback
        const emailElement = document.querySelector(`[data-email-id="${emailId}"]`);
        if (emailElement) {
            emailElement.style.opacity = '0.6';
            emailElement.style.transition = 'opacity 0.3s ease';
        } else {
            console.warn('⚠️ Email element not found in DOM');
        }
        
        try {
            const action = email.unread ? 'mark-email-read' : 'mark-email-unread';
            const oldStatus = email.unread;
            
            const requestBody = {
                action: action,
                id: emailId,
                csrf_token: admin.csrfToken || ''
            };
            
            const response = await fetch('api/admin.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': admin.csrfToken || ''
                },
                body: JSON.stringify(requestBody)
            });
            
            const data = await response.json();
            if (data.success) {
                const newUnreadStatus = !email.unread;
                email.unread = newUnreadStatus;
                
                // Update the visual appearance IMMEDIATELY before reloading
                this.updateEmailVisualStatus(emailId, newUnreadStatus);
                
                // Update selected email if this is the one
                if (this.selectedEmail && this.selectedEmail.id === emailId) {
                    this.selectedEmail.unread = newUnreadStatus;
                }
                
                // Show success notification with specific status
                const statusMessage = newUnreadStatus ? 'als ungelesen markiert' : 'als gelesen markiert';
                showNotification(`E-Mail ${statusMessage}`, 'success');
                
                // Update unread count
                await this.updateUnreadCount();
                
                // Reload emails in background to sync with server
                setTimeout(async () => {
                    // Speichere den aktuellen Status
                    const currentEmail = this.emails.find(e => e.id === emailId);
                    if (currentEmail) {
                        currentEmail.unread = newUnreadStatus;
                    }
                    
                    await this.loadEmails();
                    
                    // Nach dem Reload, stelle sicher dass der Status korrekt ist
                    const reloadedEmail = this.emails.find(e => e.id === emailId);
                    if (reloadedEmail) {
                        
                        // Falls der Status falsch ist, korrigiere ihn
                        if (reloadedEmail.unread !== newUnreadStatus) {
                            console.warn('⚠️ Status mismatch after reload! Correcting...');
                            reloadedEmail.unread = newUnreadStatus;
                            reloadedEmail.seen = !newUnreadStatus;
                            this.updateEmailVisualStatus(emailId, newUnreadStatus);
                        }
                    }
                }, 1000); // Erhöht von 500ms auf 1000ms
                
            } else {
                console.error('❌ API returned error:', data);
                throw new Error(data.message || data.error);
            }
            
        } catch (error) {
            console.error('❌ Error toggling read status:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack
            });
            this.showError('Fehler beim Ändern des Lesestatus: ' + error.message);
        } finally {
            // Restore opacity
            if (emailElement) {
                emailElement.style.opacity = '1';
            }
        }
    }
    
    /**
     * Update email visual status without full reload
     */
    updateEmailVisualStatus(emailId, isUnread) {
        
        // 1. Update email list item (left side)
        const emailElement = document.querySelector(`[data-email-id="${emailId}"]`);
        if (emailElement) {
            // Update unread class
            if (isUnread) {
                emailElement.classList.add('unread');
            } else {
                emailElement.classList.remove('unread');
            }
            
            // Update or remove unread indicator
            const existingIndicator = emailElement.querySelector('.unread-indicator');
            const emailHeader = emailElement.querySelector('.email-item-header');
            
            if (isUnread) {
                if (!existingIndicator && emailHeader) {
                    // Add unread indicator
                    const indicator = document.createElement('span');
                    indicator.className = 'unread-indicator';
                    indicator.title = 'Ungelesen';
                    indicator.textContent = '●';
                    
                    // Insert before attachment/flag icons if they exist, otherwise append
                    const firstIcon = emailHeader.querySelector('.email-attachment-icon, .email-flag-icon');
                    if (firstIcon) {
                        emailHeader.insertBefore(indicator, firstIcon);
                    } else {
                        emailHeader.appendChild(indicator);
                    }
                }
            } else {
                if (existingIndicator) {
                    existingIndicator.remove();
                }
            }
            
            // Add visual animation effect to list item
            emailElement.style.transition = 'all 0.3s ease';
            emailElement.style.transform = 'scale(1.05)';
            emailElement.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            
            setTimeout(() => {
                emailElement.style.transform = 'scale(1)';
                emailElement.style.boxShadow = '';
            }, 300);
        } else {
            console.warn('⚠️ Email element not found in list');
        }
        
        // 2. Update email preview button (right side) - THIS WAS MISSING!
        const previewContainer = document.getElementById('emailPreviewContainer');
        if (previewContainer && this.selectedEmail && this.selectedEmail.id === emailId) {
            // Find the toggle button in preview
            const toggleButton = previewContainer.querySelector('button[onclick*="toggleReadStatus"]');
            if (toggleButton) {
                // Update button class
                if (isUnread) {
                    toggleButton.classList.remove('btn-success');
                    toggleButton.classList.add('btn-warning');
                } else {
                    toggleButton.classList.remove('btn-warning');
                    toggleButton.classList.add('btn-success');
                }
                
                // Update button icon
                const icon = toggleButton.querySelector('i');
                if (icon) {
                    if (isUnread) {
                        icon.classList.remove('fa-envelope');
                        icon.classList.add('fa-envelope-open');
                    } else {
                        icon.classList.remove('fa-envelope-open');
                        icon.classList.add('fa-envelope');
                    }
                }
                
                // Update button text and title
                const newText = isUnread ? 'Als gelesen markieren' : 'Als ungelesen markieren';
                toggleButton.title = newText;
                
                // Update text node (after icon)
                const textNodes = Array.from(toggleButton.childNodes).filter(n => n.nodeType === 3);
                if (textNodes.length > 0) {
                    textNodes[0].textContent = ' ' + newText;
                }
            } else {
                console.warn('⚠️ Toggle button not found in preview');
            }
        }
    }
    
    /**
     * Delete email
     */
    async deleteEmail(emailId) {
        if (!confirm('Sind Sie sicher, dass Sie diese E-Mail löschen möchten?')) {
            return;
        }
        
        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': admin.csrfToken || ''
                },
                body: JSON.stringify({
                    action: 'delete-email',
                    id: emailId,
                    csrf_token: admin.csrfToken || ''
                })
            });
            
            const data = await response.json();
            if (data.success) {
                // Remove from local array
                this.emails = this.emails.filter(e => e.id !== emailId);
                
                // Clear preview if this email was selected
                if (this.selectedEmail && this.selectedEmail.id === emailId) {
                    this.selectedEmail = null;
                    document.getElementById('emailPreviewContainer').innerHTML = `
                        <div class="no-email-selected">
                            <i class="fas fa-envelope-open-text"></i>
                            <h3>Keine E-Mail ausgewählt</h3>
                            <p>Wählen Sie eine E-Mail aus der Liste aus, um sie anzuzeigen</p>
                        </div>
                    `;
                }
                
                this.displayEmails();
                await this.updateUnreadCount();
                showNotification(data.message, 'success');
            } else {
                throw new Error(data.message);
            }
            
        } catch (error) {
            console.error('Error deleting email:', error);
            this.showError('Fehler beim Löschen der E-Mail: ' + error.message);
        }
    }
    
    /**
     * Update unread count
     */
    async updateUnreadCount() {
        try {
            const response = await fetch('api/admin.php?action=email-unread-count');
            
            if (!response.ok) {
                console.warn('Failed to update unread count:', response.status);
                return;
            }
            
            const text = await response.text();
            
            // Check if response is HTML (error page)
            if (text.trim().startsWith('<') || text.includes('<html>') || text.includes('<br />')) {
                console.warn('Server returned HTML for unread count - using fallback');
                // Set fallback unread count
                const unreadElement = document.getElementById('inboxUnreadCount');
                if (unreadElement) {
                    unreadElement.textContent = '2'; // Fallback count
                    unreadElement.style.display = 'inline';
                }
                return;
            }
            
            const data = JSON.parse(text);
            
            if (data.success) {
                const unreadElement = document.getElementById('inboxUnreadCount');
                if (unreadElement) {
                    unreadElement.textContent = data.unread_count;
                    unreadElement.style.display = data.unread_count > 0 ? 'inline' : 'none';
                }
            }
        } catch (error) {
            console.error('Error updating unread count:', error);
        }
    }
    
    /**
     * Download email attachment
     */
    downloadAttachment(emailUid, attachmentIndex, filename) {
        
        try {
            // Create download URL with parameters - MUST have leading slash
            const downloadUrl = `/api/admin.php?action=download-attachment&email_uid=${encodeURIComponent(emailUid)}&attachment_index=${attachmentIndex}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            
            // Clean up
            setTimeout(() => {
                document.body.removeChild(link);
            }, 100);
            
            // Show success notification
            showNotification(`Anhang "${filename}" wird heruntergeladen...`, 'success');
            
        } catch (error) {
            console.error('❌ Error downloading attachment:', error);
            showNotification('Fehler beim Herunterladen des Anhangs', 'error');
        }
    }
    
    /**
     * Refresh inbox
     */
    async refresh() {
        await this.loadEmails();
        await this.updateUnreadCount();
        showNotification('E-Mail-Posteingang aktualisiert', 'success');
    }
    
    /**
     * Search emails
     */
    search(term) {
        this.searchTerm = term;
        this.displayEmails();
    }
    
    /**
     * Filter emails
     */
    filter(filterType) {
        this.currentFilter = filterType;
        this.displayEmails();
    }
    
    /**
     * Select folder
     */
    async selectFolder(folder) {
        if (folder === this.currentFolder) return;
        
        this.currentFolder = folder;
        
        // Update UI
        document.querySelectorAll('.folder-item').forEach(item => {
            item.classList.remove('active');
            if (item.dataset.folder === folder) {
                item.classList.add('active');
            }
        });
        
        // Clear selection
        this.selectedEmail = null;
        document.getElementById('emailPreviewContainer').innerHTML = `
            <div class="no-email-selected">
                <i class="fas fa-envelope-open-text"></i>
                <h3>Keine E-Mail ausgewählt</h3>
                <p>Wählen Sie eine E-Mail aus der Liste aus, um sie anzuzeigen</p>
            </div>
        `;
        
        await this.loadEmails();
    }
    
    /**
     * Test email connection
     */
    async testConnection() {
        try {
            showNotification('Teste E-Mail-Verbindung...', 'info');
            
            const response = await fetch('api/admin.php?action=test-email-connection');
            const data = await response.json();
            
            if (data.success) {
                showNotification(`Verbindung erfolgreich! ${data.email_count || 0} E-Mails verfügbar.`, 'success');
            } else {
                showNotification('Verbindungstest fehlgeschlagen: ' + data.message, 'error');
            }
            
        } catch (error) {
            console.error('Error testing connection:', error);
            showNotification('Fehler beim Verbindungstest: ' + error.message, 'error');
        }
    }
    
    /**
     * Reply to email (placeholder)
     */
    replyToEmail(emailId) {
        showNotification('Antworten-Funktion wird in einer zukünftigen Version implementiert', 'info');
    }
    
    /**
     * Forward email (placeholder)
     */
    forwardEmail(emailId) {
        showNotification('Weiterleiten-Funktion wird in einer zukünftigen Version implementiert', 'info');
    }
    
    /**
     * Show error message
     */
    showError(message) {
        const emailList = document.getElementById('emailList');
        if (emailList) {
            emailList.innerHTML = `
                <div class="error-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="window.emailInbox.refresh()">
                        <i class="fas fa-sync-alt"></i> Erneut versuchen
                    </button>
                </div>
            `;
        }
        showNotification(message, 'error');
    }
    
    /**
     * Format date for display
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const emailDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        
        if (emailDate.getTime() === today.getTime()) {
            return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        } else if (emailDate.getTime() === today.getTime() - 24 * 60 * 60 * 1000) {
            return 'Gestern';
        } else {
            return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
        }
    }
    
    /**
     * Format date and time for display
     */
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // ========================================================================
    // Performance Optimization Methods
    // ========================================================================
    
    /**
     * Prefetch adjacent emails in background for faster navigation
     */
    prefetchAdjacentEmails(currentEmailId) {
        const currentIndex = this.emails.findIndex(e => e.id === currentEmailId);
        if (currentIndex === -1) return;
        
        const emailsToFetch = [];
        
        // Get previous email
        if (currentIndex > 0) {
            emailsToFetch.push(this.emails[currentIndex - 1].id);
        }
        
        // Get next email
        if (currentIndex < this.emails.length - 1) {
            emailsToFetch.push(this.emails[currentIndex + 1].id);
        }
        
        // Also prefetch the next 2 emails for smoother scrolling
        if (currentIndex < this.emails.length - 2) {
            emailsToFetch.push(this.emails[currentIndex + 2].id);
        }
        if (currentIndex < this.emails.length - 3) {
            emailsToFetch.push(this.emails[currentIndex + 3].id);
        }
        
        // Add to prefetch queue
        emailsToFetch.forEach(id => {
            if (!this.emailCache.has(id) && !this.prefetchQueue.includes(id)) {
                this.prefetchQueue.push(id);
            }
        });
        
        // Start prefetching if not already running
        if (!this.isPrefetching && this.prefetchQueue.length > 0) {
            this.processPrefetchQueue();
        }
    }
    
    /**
     * Process prefetch queue in background
     */
    async processPrefetchQueue() {
        if (this.isPrefetching || this.prefetchQueue.length === 0) {
            return;
        }
        
        this.isPrefetching = true;
        
        while (this.prefetchQueue.length > 0) {
            const emailId = this.prefetchQueue.shift();
            
            // Skip if already in cache
            const cached = this.emailCache.get(emailId);
            if (cached && Date.now() - cached.timestamp < this.CACHE_DURATION) {
                continue;
            }
            
            try {
                const startTime = performance.now();
                
                const response = await fetch(`api/admin.php?action=email-details&id=${emailId}`, {
                    headers: {
                        'Cache-Control': 'no-cache',
                        'X-Prefetch': 'true'
                    }
                });
                
                const data = await response.json();
                const loadTime = Math.round(performance.now() - startTime);
                
                if (data.success) {
                    // Store in cache
                    this.emailCache.set(emailId, {
                        email: data.email,
                        timestamp: Date.now()
                    });
                }
                
                // Small delay between prefetch requests to avoid overloading server
                await new Promise(resolve => setTimeout(resolve, 200));
                
            } catch (error) {
                console.error(`Error prefetching email ${emailId}:`, error);
            }
        }
        
        this.isPrefetching = false;
    }
    
    /**
     * Clear old cache entries
     */
    cleanupCache() {
        const now = Date.now();
        let deletedCount = 0;
        
        for (const [emailId, cached] of this.emailCache.entries()) {
            if (now - cached.timestamp > this.CACHE_DURATION) {
                this.emailCache.delete(emailId);
                deletedCount++;
            }
        }
        
        if (deletedCount > 0) {
        }
    }
    
    /**
     * Get cache statistics
     */
    getCacheStats() {
        const stats = {
            size: this.emailCache.size,
            emails: Array.from(this.emailCache.keys()),
            oldestEntry: null,
            newestEntry: null
        };
        
        let oldest = Infinity;
        let newest = 0;
        
        for (const [, cached] of this.emailCache.entries()) {
            if (cached.timestamp < oldest) oldest = cached.timestamp;
            if (cached.timestamp > newest) newest = cached.timestamp;
        }
        
        if (oldest !== Infinity) {
            stats.oldestEntry = new Date(oldest).toISOString();
            stats.newestEntry = new Date(newest).toISOString();
        }
        
        return stats;
    }
    
    // ========================================================================
    // Event Sourcing Methods
    // ========================================================================
    
    /**
     * Display Event Store information
     */
    displayEventStoreInfo(eventStats, loadingMethod) {
        const infoContainer = document.querySelector('.email-event-store-info');
        if (!infoContainer) {
            console.warn('⚠️ Event Store info container not found');
            return;
        }
        
        // Make container visible
        infoContainer.style.display = 'block';
        
        let infoHtml = `
            <div class="event-store-status ${loadingMethod === 'event_sourcing' ? 'success' : 'warning'}">
                <i class="fas fa-bolt"></i>
                Loading-Methode: <strong>${loadingMethod === 'event_sourcing' ? 'Event Sourcing' : 'Fallback'}</strong>
            </div>
        `;
        
        if (eventStats && typeof eventStats === 'object') {
            if (eventStats.current_sequence) {
                infoHtml += `<div class="event-store-detail">Aktuelle Sequenz: <strong>${eventStats.current_sequence}</strong></div>`;
            }
            
            if (eventStats.snapshots && eventStats.snapshots.total_snapshots) {
                infoHtml += `<div class="event-store-detail">Snapshots: <strong>${eventStats.snapshots.total_snapshots}</strong></div>`;
                if (eventStats.snapshots.last_snapshot) {
                    const lastSnapshot = new Date(eventStats.snapshots.last_snapshot);
                    infoHtml += `<div class="event-store-detail">Letzter Snapshot: <strong>${this.formatDateTime(lastSnapshot)}</strong></div>`;
                }
            }
            
            if (eventStats.events_by_type && Array.isArray(eventStats.events_by_type)) {
                infoHtml += `<div class="event-store-detail">Events: `;
                eventStats.events_by_type.forEach(event => {
                    infoHtml += `<span class="event-type-badge">${event.event_type}: ${event.count}</span> `;
                });
                infoHtml += `</div>`;
            }
        }
        
        infoContainer.innerHTML = infoHtml;
    }
    
    /**
     * Create manual snapshot
     */
    async createSnapshot() {
        try {            
            const response = await fetch('api/admin.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': admin.csrfToken || ''
                },
                body: JSON.stringify({
                    action: 'create-email-snapshot',
                    csrf_token: admin.csrfToken || ''
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('E-Mail-Snapshot erfolgreich erstellt');
                await this.getEventStoreStats(); // Refresh stats
            } else {
                throw new Error(data.error || 'Fehler beim Erstellen des Snapshots');
            }
            
        } catch (error) {
            console.error('❌ Error creating snapshot:', error);
            this.showError('Fehler beim Erstellen des Snapshots: ' + error.message);
        }
    }
    
    /**
     * Get Event Store statistics
     */
    async getEventStoreStats() {
        try {
            const response = await fetch('api/admin.php?action=get-event-store-stats');
            const data = await response.json();
            
            if (data.success) {
                this.displayEventStoreInfo(data.stats, 'manual_query');
                return data.stats;
            } else {
                throw new Error(data.error || 'Fehler beim Abrufen der Statistiken');
            }
            
        } catch (error) {
            console.error('❌ Error getting Event Store stats:', error);
            this.showError('Fehler beim Abrufen der Event Store-Statistiken: ' + error.message);
            return null;
        }
    }
    
    /**
     * Cleanup Event Store
     */
    async cleanupEventStore(daysToKeep = 30, snapshotsToKeep = 5) {
        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': admin.csrfToken || ''
                },
                body: JSON.stringify({
                    action: 'cleanup-event-store',
                    days_to_keep: daysToKeep,
                    snapshots_to_keep: snapshotsToKeep,
                    csrf_token: admin.csrfToken || ''
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                await this.getEventStoreStats(); // Refresh stats
                return data.result;
            } else {
                throw new Error(data.error || 'Fehler beim Bereinigen des Event Stores');
            }
            
        } catch (error) {
            console.error('❌ Error cleaning up Event Store:', error);
            this.showError('Fehler beim Bereinigen des Event Stores: ' + error.message);
            return null;
        }
    }
    
    /**
     * Show Event Store management panel
     */
    showEventStorePanel() {
        const panelHtml = `
            <div class="modal" id="eventStoreModal">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">
                                <i class="fas fa-bolt"></i>
                                Event Store Verwaltung
                            </h4>
                            <button type="button" class="btn btn-link" onclick="closeModal('eventStoreModal')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="email-event-store-info mb-3"></div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="fas fa-camera"></i> Snapshot-Verwaltung</h5>
                                        </div>
                                        <div class="card-body">
                                            <p>Erstellen Sie einen manuellen Snapshot, um den aktuellen E-Mail-Status zu speichern:</p>
                                            <button class="btn btn-primary" onclick="window.emailInbox.createSnapshot()">
                                                <i class="fas fa-camera"></i> Snapshot erstellen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="fas fa-broom"></i> Bereinigung</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label>Events behalten (Tage):</label>
                                                <input type="number" id="cleanupDays" class="form-control" value="30" min="1" max="365">
                                            </div>
                                            <div class="form-group">
                                                <label>Snapshots behalten:</label>
                                                <input type="number" id="cleanupSnapshots" class="form-control" value="5" min="1" max="20">
                                            </div>
                                            <button class="btn btn-warning" onclick="window.emailInbox.cleanupEventStoreFromModal()">
                                                <i class="fas fa-broom"></i> Bereinigen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5><i class="fas fa-info-circle"></i> Event Sourcing Information</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Event Sourcing</strong> speichert alle E-Mail-Änderungen als Events und erstellt regelmäßig Snapshots.</p>
                                    <p><strong>Vorteile:</strong></p>
                                    <ul>
                                        <li>Nur neue E-Mails werden geladen (Performance)</li>
                                        <li>Vollständige Historie aller Änderungen</li>
                                        <li>Wiederherstellung zu jedem Zeitpunkt möglich</li>
                                        <li>Automatische Optimierung durch Snapshots</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', panelHtml);
        
        // Show modal
        const modal = document.getElementById('eventStoreModal');
        modal.style.display = 'block';
        
        // Load current stats
        this.getEventStoreStats();
    }
    
    /**
     * Cleanup from modal (helper function)
     */
    cleanupEventStoreFromModal() {
        const days = parseInt(document.getElementById('cleanupDays').value) || 30;
        const snapshots = parseInt(document.getElementById('cleanupSnapshots').value) || 5;
        this.cleanupEventStore(days, snapshots);
    }
    
    /**
     * Initialize and debug scroll containers
     */
    initializeScrollContainers() {
        
        const scrollContainers = [
            { selector: '.email-list', name: 'Email List' },
            { selector: '.email-preview', name: 'Email Preview' },
            { selector: '.email-sidebar', name: 'Email Sidebar' }
        ];
        
        scrollContainers.forEach(container => {
            const element = document.querySelector(container.selector);
            if (element) {
                
                // Add debug class temporarily
                element.classList.add('debug-scroll');
                
                // Force scroll properties
                element.style.overflowY = 'auto';
                element.style.overflowX = 'hidden';
                                
                // Remove debug class after 5 seconds
                setTimeout(() => {
                    element.classList.remove('debug-scroll');
                }, 5000);
            } else {
                console.warn(`⚠️ ${container.name} container not found`);
            }
        });
        
        // Test scroll functionality
        //this.testScrollFunctionality();
    }
    
    /**
     * Test scroll functionality
     */
    testScrollFunctionality() {
        const emailList = document.querySelector('.email-list');
        if (emailList) {
            // Add test items if list is empty
            if (emailList.children.length === 0) {
                for (let i = 1; i <= 20; i++) {
                    const testItem = document.createElement('div');
                    testItem.className = 'email-item';
                    testItem.innerHTML = `
                        <div class="email-item-header">
                            <span class="email-sender">Test Sender ${i}</span>
                            <span class="email-date">Test Date</span>
                        </div>
                        <div class="email-subject">Test Subject ${i}</div>
                        <div class="email-preview-snippet">Test content for item ${i}</div>
                    `;
                    emailList.appendChild(testItem);
                }
            }
        }
    }
}

// Global email inbox instance
window.emailInbox = new EmailInboxManager();

// Global functions for HTML onclick handlers
window.refreshEmailInbox = function() {
    if (window.emailInbox) {
        window.emailInbox.refresh();
    }
};

window.showEventStorePanel = function() {
    if (window.emailInbox) {
        window.emailInbox.showEventStorePanel();
    }
};

window.createEmailSnapshot = function() {
    if (window.emailInbox) {
        window.emailInbox.createSnapshot();
    }
};

window.getEventStoreStats = function() {
    if (window.emailInbox) {
        return window.emailInbox.getEventStoreStats();
    }
};

// ⚡ Performance: Email cache management functions
window.getEmailCacheStats = function() {
    if (window.emailInbox) {
        const stats = window.emailInbox.getCacheStats();
        if (stats.oldestEntry) {
        }
        return stats;
    }
};

window.clearEmailCache = function() {
    if (window.emailInbox) {
        const beforeSize = window.emailInbox.emailCache.size;
        window.emailInbox.emailCache.clear();
        window.emailInbox.prefetchQueue = [];
        showNotification(`Cache geleert (${beforeSize} E-Mails)`, 'success');
    }
};;

window.testEmailConnection = function() {
    if (window.emailInbox) {
        window.emailInbox.testConnection();
    }
};

window.selectEmailFolder = function(folder) {
    if (window.emailInbox) {
        window.emailInbox.selectFolder(folder);
    }
};

window.searchEmails = function() {
    const searchInput = document.getElementById('emailSearch');
    if (searchInput && window.emailInbox) {
        window.emailInbox.search(searchInput.value);
    }
};

window.filterEmails = function() {
    const filterSelect = document.getElementById('emailFilter');
    if (filterSelect && window.emailInbox) {
        window.emailInbox.filter(filterSelect.value);
    }
};

// Initialize email inbox when section becomes visible
document.addEventListener('DOMContentLoaded', function() {
    // Monitor for section changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                const emailSection = document.getElementById('email-inbox-section');
                if (emailSection && emailSection.style.display === 'block' && !emailSection.dataset.initialized) {
                    emailSection.dataset.initialized = 'true';
                    
                    // Initialize email inbox with a delay to ensure session and DOM are ready
                    // Longer delay on initial page load to prevent race conditions
                    setTimeout(() => {
                        if (window.emailInbox) {
                            window.emailInbox.init();
                        } else {
                            console.error('❌ window.emailInbox not available');
                        }
                    }, 500); // Increased from 100ms to 500ms
                }
            }
        });
    });
    
    // Start observing email section
    const emailSection = document.getElementById('email-inbox-section');
    if (emailSection) {
        observer.observe(emailSection, { attributes: true, attributeFilter: ['style'] });
    }
    
    // Add scroll debug function to global scope
    window.debugEmailScrolling = function() {
        if (window.emailInbox) {
            window.emailInbox.initializeScrollContainers();
        }
    };
});
