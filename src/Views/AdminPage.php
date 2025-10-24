<?php declare(strict_types=1);

namespace DSAllround\Views;
use Exception;

require_once 'Page.php';
require_once 'CookieHandler.php';

class AdminPage extends Page
{
    /**
     * Properties
     */
    private $auth_middleware;
    private $currentUser;
    
    /**
     * Debug mode - set to false to hide debug buttons and info panels
     * Controls: Connection Test, Event Store, Snapshot, Debug Scroll buttons
     */
    private const DEBUG_MODE = false;  // Set to false to disable debug features

    /**
     * Instantiates members (to be defined above).
     * Calls the constructor of the parent i.e. page class.
     * So, the database connection is established.
     * @throws Exception
     */
    protected function __construct()
    {
        parent::__construct();
        
        // Initialize authentication
        $this->initAuthentication();
    }

    /**
     * Cleans up whatever is needed.
     * Calls the destructor of the parent i.e. page class.
     * So, the database connection is closed.
     */
    public function __destruct()
    {
        parent::__destruct();
    }

    /**
     * This main-function has the only purpose to create an instance
     * of the class and to get all the things going.
     * I.e. the operations of the class are called to produce
     * the output of the HTML-file.
     * The name "main" is no keyword for php. It is just used to
     * indicate that function as the central starting point.
     * To make it simpler this is a static function. That is you can simply
     * call it without first creating an instance of the class.
     * @return void
     */
    public static function main():void
    {
        try {
            // Create instance and check authentication
            $page = new AdminPage();
            
            // Check if user is authenticated with required permissions
            if (!$page->isAuthenticated()) {
                // Redirect to login page
                header('Location: /login');
                exit;
            }
            
            // Generiere ein zufälliges Token und speichere es in der Sitzung
            if (!isset($_SESSION['token'])) {
                $_SESSION['token'] = bin2hex(random_bytes(32));
            }
            
            $page->generateView();
        } catch (Exception $e) {
            error_log("AdminPage error: " . $e->getMessage());
            header('Location: /login?error=system');
            exit;
        }
    }

    /**
     * Initialize authentication middleware
     */
    private function initAuthentication(): void
    {
        require_once __DIR__ . '/../../auth_middleware.php';
        $this->auth_middleware = new \AuthMiddleware();
    }

    /**
     * Check if user is authenticated with required permissions
     * @return bool
     */
    private function isAuthenticated(): bool
    {
        // Check if user is logged in and has permission to access admin
        $userInfo = $this->auth_middleware->checkSession();
        
        if (!$userInfo) {
            return false;
        }
        
        // Check if user has admin permission (at least Moderator level)
        $hasPermission = $this->auth_middleware->hasPermission('admin_access');
        
        if (!$hasPermission) {
            return false;
        }
        
        // Store current user info for use in admin panel
        $this->currentUser = $userInfo;
        return true;
    }

    /**
     * First the required data is fetched and then the HTML is
     * assembled for output. i.e. the header is generated, the content
     * of the page ("view") is inserted and -if available- the content of
     * all views contained is generated.
     * Finally, the footer is added.
     * @return void
     */
    protected function generateView():void
    {
        $this->generatePageHeader('Admin Dashboard'); //to do: set optional parameters

        $this->generateMainBody();
    }

    private function generateMainBody(){
        $this->generateHTML();
    }

    protected function additionalMetaData(): void
    {
        //Links for css or js
        echo <<< HTML
                <link rel="stylesheet" href="/public/assets/css/admin.css">
                <link rel="stylesheet" href="/public/assets/css/questionnaire-builder.css">
                <link rel="preconnect" href="https://fonts.googleapis.com">
                <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        HTML;
    }

    /**
     * Get dashboard statistics from database
     */
    private function getDashboardStats(): array
    {
        $stats = [
            'todaySubmissions' => 0,
            'activeServices' => 0,
            'monthlySubmissions' => 0,
            'conversionRate' => 0
        ];

        try {
            // Today's submissions
            if ($this->isLocal) {
                $stmt = $this->_database->prepare(
                    "SELECT COUNT(*) as count FROM questionnaire_submissions 
                     WHERE DATE(submitted_at) = DATE('now')"
                );
            } else {
                $stmt = $this->_database->prepare(
                    "SELECT COUNT(*) as count FROM questionnaire_submissions 
                     WHERE DATE(submitted_at) = CURDATE()"
                );
            }
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['todaySubmissions'] = $result['count'] ?? 0;

            // Active services
            $stmt = $this->_database->prepare("SELECT COUNT(*) as count FROM services WHERE is_active = 1");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['activeServices'] = $result['count'] ?? 0;

            // Monthly submissions
            if ($this->isLocal) {
                $stmt = $this->_database->prepare(
                    "SELECT COUNT(*) as count FROM questionnaire_submissions 
                     WHERE strftime('%Y-%m', submitted_at) = strftime('%Y-%m', 'now')"
                );
            } else {
                $stmt = $this->_database->prepare(
                    "SELECT COUNT(*) as count FROM questionnaire_submissions 
                     WHERE YEAR(submitted_at) = YEAR(NOW()) AND MONTH(submitted_at) = MONTH(NOW())"
                );
            }
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['monthlySubmissions'] = $result['count'] ?? 0;

            // Simple conversion rate calculation
            $stats['conversionRate'] = $stats['monthlySubmissions'] > 0 ? 
                min(round(($stats['todaySubmissions'] / max($stats['monthlySubmissions'], 1)) * 100, 1), 100) : 0;

        } catch (\PDOException $e) {
            error_log("Database error in getDashboardStats: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get recent submissions from database
     */
    private function getRecentSubmissions(): array
    {
        try {
            $stmt = $this->_database->prepare(
                "SELECT qs.*, s.name as service_name 
                 FROM questionnaire_submissions qs
                 JOIN services s ON qs.service_id = s.id
                 ORDER BY qs.submitted_at DESC 
                 LIMIT 5"
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Database error in getRecentSubmissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get count of new submissions (status = 'new')
     */
    private function getNewSubmissionsCount(): int
    {
        try {
            $stmt = $this->_database->prepare(
                "SELECT COUNT(*) as count FROM questionnaire_submissions 
                 WHERE status = 'new'"
            );
            $stmt->execute();
            $result = $stmt->fetch();
            return (int)($result['count'] ?? 0);
        } catch (\PDOException $e) {
            error_log("Database error in getNewSubmissionsCount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get services list from database
     */
    private function getServices(): array
    {
        try {
            $stmt = $this->_database->prepare(
                "SELECT s.*, COUNT(qs.id) as submission_count
                 FROM services s
                 LEFT JOIN questionnaire_submissions qs ON s.id = qs.service_id
                 GROUP BY s.id, s.name, s.slug, s.title, s.description, s.icon, s.color, s.sort_order, s.is_active, s.created_at, s.updated_at
                 ORDER BY s.sort_order ASC, s.name ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Database error in getServices: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get service statistics for chart
     */
    private function getServiceStats(): array
    {
        try {
            $stmt = $this->_database->prepare(
                "SELECT s.name, COUNT(qs.id) as submissions
                 FROM services s
                 LEFT JOIN questionnaire_submissions qs ON s.id = qs.service_id
                 WHERE s.is_active = 1
                 GROUP BY s.id, s.name
                 ORDER BY submissions DESC"
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Database error in getServiceStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all users from database for user management
     */
    private function getUsers(): array
    {
        try {
            // Check if current user is Admin
            $isAdmin = $this->currentUser['role'] === 'Admin';
            
            if ($isAdmin) {
                // Admins can see all users
                $stmt = $this->_database->prepare("
                    SELECT id, username, email, first_name, last_name, role, is_active, 
                           last_login, login_attempts, created_at
                    FROM users
                    ORDER BY created_at DESC
                ");
                $stmt->execute();
            } else {
                // Non-admins cannot see Admin accounts
                $stmt = $this->_database->prepare("
                    SELECT id, username, email, first_name, last_name, role, is_active, 
                           last_login, login_attempts, created_at
                    FROM users
                    WHERE role != 'Admin'
                    ORDER BY created_at DESC
                ");
                $stmt->execute();
            }
            
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Database error in getUsers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Format date for display
     */
    private function formatDate(string $date): string
    {
        return date('d.m.Y H:i', strtotime($date));
    }

    /**
     * Generate sidebar navigation menu based on user permissions
     */
    private function generateSidebarMenu(): string
    {
        $menuItems = [
            [
                'group' => 'Dashboard',
                'items' => [
                    [
                        'permission' => 'dashboard_view',
                        'href' => '#dashboard',
                        'section' => 'dashboard',
                        'icon' => 'fas fa-tachometer-alt',
                        'title' => 'Übersicht',
                        'active' => true
                    ]
                ]
            ],
            [
                'group' => 'Services',
                'items' => [
                    [
                        'permission' => 'services_view',
                        'href' => '#services',
                        'section' => 'services',
                        'icon' => 'fas fa-cogs',
                        'title' => 'Service-Verwaltung'
                    ],
                    [
                        'permission' => 'service_pages_view',
                        'href' => '#service-pages',
                        'section' => 'service-pages',
                        'icon' => 'fas fa-file-alt',
                        'title' => 'Seiteninhalte'
                    ]
                ]
            ],
            [
                'group' => 'Content',
                'items' => [
                    [
                        'permission' => 'media_view',
                        'href' => '#media',
                        'section' => 'media',
                        'icon' => 'fas fa-images',
                        'title' => 'Medien-Verwaltung'
                    ]
                ]
            ],
            [
                'group' => 'Fragebögen',
                'items' => [
                    [
                        'permission' => 'questionnaires_view',
                        'href' => '#questionnaires',
                        'section' => 'questionnaires',
                        'icon' => 'fas fa-clipboard-list',
                        'title' => 'Fragebogen-Builder'
                    ],
                    [
                        'permission' => 'questions_view',
                        'href' => '#questions',
                        'section' => 'questions',
                        'icon' => 'fas fa-question-circle',
                        'title' => 'Fragen-Verwaltung'
                    ]
                ]
            ],
            [
                'group' => 'Anfragen',
                'items' => [
                    [
                        'permission' => 'submissions_view',
                        'href' => '#submissions',
                        'section' => 'submissions',
                        'icon' => 'fas fa-inbox',
                        'title' => 'Neue Anfragen'
                    ],
                    [
                        'permission' => 'submission_archive_view',
                        'href' => '#submission-archive',
                        'section' => 'submission-archive',
                        'icon' => 'fas fa-archive',
                        'title' => 'Anfragen-Archiv'
                    ],
                    [
                        'permission' => 'email_inbox_view',
                        'href' => '#email-inbox',
                        'section' => 'email-inbox',
                        'icon' => 'fas fa-envelope',
                        'title' => 'E-Mail-Posteingang'
                    ]
                ]
            ],
            [
                'group' => 'System',
                'items' => [
                    [
                        'permission' => 'users_view',
                        'href' => '#users',
                        'section' => 'users',
                        'icon' => 'fas fa-users',
                        'title' => 'Benutzerverwaltung'
                    ],
                    [
                        'permission' => 'settings_view',
                        'href' => '#settings',
                        'section' => 'settings',
                        'icon' => 'fas fa-cog',
                        'title' => 'Einstellungen'
                    ],
                    [
                        'permission' => 'email_templates_view',
                        'href' => '#emails',
                        'section' => 'emails',
                        'icon' => 'fas fa-envelope',
                        'title' => 'E-Mail-Verwaltung'
                    ],
                    [
                        'permission' => 'logs_view',
                        'href' => '#logs',
                        'section' => 'logs',
                        'icon' => 'fas fa-history',
                        'title' => 'System-Logs'
                    ]
                ]
            ]
        ];

        $menuHtml = '';
        
        foreach ($menuItems as $group) {
            $visibleItems = [];
            
            // Check which items are visible for this user
            foreach ($group['items'] as $item) {
                if ($this->auth_middleware->hasPermission($item['permission'])) {
                    $visibleItems[] = $item;
                }
            }
            
            // Only show group if it has visible items
            if (!empty($visibleItems)) {
                $menuHtml .= '<div class="menu-group">';
                $menuHtml .= '<h4>' . htmlspecialchars($group['group']) . '</h4>';
                $menuHtml .= '<ul>';
                
                foreach ($visibleItems as $item) {
                    $activeClass = isset($item['active']) && $item['active'] ? ' active' : '';
                    $menuHtml .= '<li><a href="' . htmlspecialchars($item['href']) . '" class="menu-link' . $activeClass . '" data-section="' . htmlspecialchars($item['section']) . '" title="' . htmlspecialchars($item['title']) . '">';
                    $menuHtml .= '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
                    $menuHtml .= '<span>' . htmlspecialchars($item['title']) . '</span>';
                    $menuHtml .= '</a></li>';
                }
                
                $menuHtml .= '</ul>';
                $menuHtml .= '</div>';
            }
        }
        
        return $menuHtml;
    }

    /**
     * Generate access denied section for unauthorized users
     */
    private function generateAccessDeniedSection(string $sectionTitle): string
    {
        return <<<HTML
        <div class="access-denied">
            <div class="empty-state">
                <i class="fas fa-lock" style="font-size: 3rem; color: #e74c3c; margin-bottom: 1rem;"></i>
                <h3>Zugriff verweigert</h3>
                <p>Sie haben nicht die erforderlichen Berechtigungen für den Bereich "{$sectionTitle}".</p>
            </div>
        </div>
HTML;
    }

    /**
     * Generate content sections with permission checks
     */
    private function generateContentSections(array $stats, array $recentSubmissions, array $services, array $serviceStats, array $users): void
    {
        // Dashboard Section
        if ($this->auth_middleware->hasPermission('dashboard_view')) {
            $this->generateDashboardSection($stats, $recentSubmissions, $serviceStats);
        }
        
        // Services Section
        if ($this->auth_middleware->hasPermission('services_view')) {
            $this->generateServicesSection($services);
        } else {
            echo '<section id="services-section" class="content-section">';
            echo $this->generateAccessDeniedSection('Service-Verwaltung');
            echo '</section>';
        }
        
        // Service Pages Section
        if ($this->auth_middleware->hasPermission('service_pages_view')) {
            $this->generateServicePagesSection($services);
        } else {
            echo '<section id="service-pages-section" class="content-section">';
            echo $this->generateAccessDeniedSection('Seiteninhalte');
            echo '</section>';
        }
        if ($this->auth_middleware->hasPermission('media_view')) {
            // Temporarily skip: $this->generateMediaSection();
            echo '<section id="media-section" class="content-section" style="display:none;">MEDIA SECTION SKIPPED FOR DEBUG</section>';
        } else {
            echo '<section id="media-section" class="content-section">';
            echo $this->generateAccessDeniedSection('Medien-Verwaltung');
            echo '</section>';
        }        
        // Questionnaires Section
        if ($this->auth_middleware->hasPermission('questionnaires_view')) {
            $this->generateQuestionnairesSection($services);
        } else {
            echo '<section id="questionnaires-section" class="content-section">';
            echo $this->generateAccessDeniedSection('Fragebogen-Builder');
            echo '</section>';
        }
        
        // Questions Section
        if ($this->auth_middleware->hasPermission('questions_view')) {
            $this->generateQuestionsSection();
        } else {
            echo '<section id="questions-section" class="content-section">';
            echo $this->generateAccessDeniedSection('Fragen-Verwaltung');
            echo '</section>';
        }
        
        // Submissions Section
        if ($this->auth_middleware->hasPermission('submissions_view')) {
            $this->generateSubmissionsSection();
        } else {
            echo '<section id="submissions-section" class="content-section">';
            echo $this->generateAccessDeniedSection('Neue Anfragen');
            echo '</section>';
        }
        
        // Submission Archive Section
        if ($this->auth_middleware->hasPermission('submission_archive_view')) {
            $this->generateSubmissionArchiveSection();
        } else {
            echo '<section id="submission-archive-section" class="content-section">';
            echo $this->generateAccessDeniedSection('Anfragen-Archiv');
            echo '</section>';
        }
        
        // E-Mail Inbox Section
        if ($this->auth_middleware->hasPermission('email_inbox_view')) {
            // E-Mail section is already included in the main HTML
        } else {
            echo '<section id="email-inbox-section" class="content-section">';
            echo $this->generateAccessDeniedSection('E-Mail-Posteingang');
            echo '</section>';
        }
        
        // Settings Section
        if ($this->auth_middleware->hasPermission('settings_view')) {
            $this->generateSettingsSection();
        } else {
            echo '<section id="settings-section" class="content-section">';
            echo $this->generateAccessDeniedSection('Einstellungen');
            echo '</section>';
        }
        
        // Users Section
        if ($this->auth_middleware->hasPermission('users_view')) {
            $this->generateUsersSection($users);
        } else {
            echo '<section id="users-section" class="content-section">';
            echo $this->generateAccessDeniedSection('Benutzerverwaltung');
            echo '</section>';
        }
    }

    /**
     * Generate dashboard section
     */
    private function generateDashboardSection(array $stats, array $recentSubmissions, array $serviceStats): void
    {
        echo <<<HTML
                <!-- Dashboard Section -->
                <section id="dashboard-section" class="content-section active">
                    <div class="section-header">
                        <h2>Dashboard Übersicht</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i>
                                Aktualisieren
                            </button>
                        </div>
                    </div>

                    <div class="dashboard-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="totalSubmissions">{$stats['todaySubmissions']}</h3>
                                <p>Anfragen heute</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="activeServices">{$stats['activeServices']}</h3>
                                <p>Aktive Services</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-month"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="monthlySubmissions">{$stats['monthlySubmissions']}</h3>
                                <p>Anfragen diesen Monat</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="conversionRate">{$stats['conversionRate']}%</h3>
                                <p>Conversion Rate</p>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-content">
                        <div class="dashboard-left">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Neue Anfragen</h3>
                                    <a href="#submissions" class="btn btn-outline btn-sm">Alle anzeigen</a>
                                </div>
                                <div class="card-body">
                                    <div class="recent-submissions">
HTML;
        
        // Generate recent submissions
        if (empty($recentSubmissions)) {
            echo '<div class="empty-state">Keine neuen Anfragen</div>';
        } else {
            foreach ($recentSubmissions as $submission) {
                $customerName = htmlspecialchars($submission['customer_name'] ?? 'Unbekannt');
                $serviceName = htmlspecialchars($submission['service_name'] ?? 'Unbekannter Service');
                $submittedAt = $this->formatDate($submission['submitted_at']);
                
                echo <<<HTML
                                        <div class="submission-item">
                                            <div class="submission-header">
                                                <strong>{$customerName}</strong>
                                                <span class="service-badge">{$serviceName}</span>
                                            </div>
                                            <div class="submission-meta">
                                                <span>{$submittedAt}</span>
                                            </div>
                                        </div>
HTML;
            }
        }

        echo <<<HTML
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dashboard-right">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Service-Statistiken</h3>
                                </div>
                                <div class="card-body">
HTML;

        // Generate service chart
        if (empty($serviceStats)) {
            echo '<div class="empty-state">Keine Daten verfügbar</div>';
        } else {
            // Calculate max value for chart scaling
            $maxValue = max(array_column($serviceStats, 'submissions'));
            $maxValue = $maxValue > 0 ? $maxValue : 1;
            
            echo '<div class="simple-chart">';
            foreach ($serviceStats as $service) {
                $serviceName = htmlspecialchars($service['name']);
                $submissions = (int)$service['submissions'];
                $percentage = ($submissions / $maxValue) * 100;
                
                echo <<<HTML
                                        <div class="chart-item">
                                            <div class="chart-label">{$serviceName}</div>
                                            <div class="chart-bar">
                                                <div class="chart-fill" style="width: {$percentage}%"></div>
                                            </div>
                                            <div class="chart-value">{$submissions}</div>
                                        </div>
HTML;
            }
            echo '</div>';
        }

        echo <<<HTML
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
HTML;
    }

    /**
     * Generate admin HTML content
     */
    private function generateHTML(): void
    {
        // Get data from database
        $stats = $this->getDashboardStats();
        $recentSubmissions = $this->getRecentSubmissions();
        $services = $this->getServices();
        $serviceStats = $this->getServiceStats();
        $users = $this->getUsers();
        $newSubmissionsCount = $this->getNewSubmissionsCount();

        // Get current user info for display
        $userName = $this->currentUser['username'] ?? 'Unbekannt';
        $userRole = $this->currentUser['role'] ?? 'Unbekannt';
        
        // Generate sidebar menu based on permissions
        $sidebarMenu = $this->generateSidebarMenu();
        
        // Check permissions for content sections
        $permissions = [
            'dashboard' => $this->auth_middleware->hasPermission('dashboard_view'),
            'services' => $this->auth_middleware->hasPermission('services_view'),
            'services_manage' => $this->auth_middleware->hasPermission('services_manage'),
            'service_pages' => $this->auth_middleware->hasPermission('service_pages_view'),
            'service_pages_manage' => $this->auth_middleware->hasPermission('service_pages_manage'),
            'media' => $this->auth_middleware->hasPermission('media_view'),
            'media_manage' => $this->auth_middleware->hasPermission('media_manage'),
            'email_templates' => $this->auth_middleware->hasPermission('email_templates_view'),
            'email_templates_manage' => $this->auth_middleware->hasPermission('email_templates_manage'),
            'questionnaires' => $this->auth_middleware->hasPermission('questionnaires_view'),
            'questionnaires_manage' => $this->auth_middleware->hasPermission('questionnaires_manage'),
            'questions' => $this->auth_middleware->hasPermission('questions_view'),
            'questions_manage' => $this->auth_middleware->hasPermission('questions_manage'),
            'submissions' => $this->auth_middleware->hasPermission('submissions_view'),
            'submissions_manage' => $this->auth_middleware->hasPermission('submissions_manage'),
            'submission_archive' => $this->auth_middleware->hasPermission('submission_archive_view'),
            'submission_archive_manage' => $this->auth_middleware->hasPermission('submission_archive_manage'),
            'users' => $this->auth_middleware->hasPermission('users_view'),
            'users_manage' => $this->auth_middleware->hasPermission('users_manage'),
            'settings' => $this->auth_middleware->hasPermission('settings_view'),
            'settings_manage' => $this->auth_middleware->hasPermission('settings_manage'),
            'logs_view' => $this->auth_middleware->hasPermission('logs_view')
        ];

        // Generate HTML content
        echo <<<HTML
    <div class="admin-container admin-panel">
        <!-- Sidebar Navigation -->
        <nav class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <img src="public/assets/img/logo.png" alt="DS Allroundservice" class="sidebar-logo">
                <h3>Administration</h3>
                <button class="sidebar-toggle" id="sidebarToggle" title="Menü ein-/ausklappen">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            
            <div class="sidebar-menu">
{$sidebarMenu}
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 id="pageTitle">Dashboard</h1>
                </div>
                
                <div class="header-right">
                    <div class="header-actions">
                        <button class="btn-icon" title="Neue Nachricht" onclick="showNewMessageInfo()">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn-icon" title="Benachrichtigungen" onclick="navigateToSection('submissions')">
                            <i class="fas fa-bell"></i>
HTML;
        
        // Only show badge if there are new submissions
        if ($newSubmissionsCount > 0) {
            echo "<span class=\"notification-badge\">{$newSubmissionsCount}</span>";
        }
        
        echo <<<HTML
                        </button>
                    </div>
                    
                    <div class="user-menu">
                        <div class="user-info">
                            <span class="user-name">$userName</span>
                            <span class="user-role">$userRole</span>
                        </div>
                        <div class="user-dropdown">
                            <button class="user-avatar" id="userMenuToggle">
                                <i class="fas fa-user-circle"></i>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="user-dropdown-menu" id="userDropdownMenu">
                                <div class="dropdown-header">
                                    <strong>$userName</strong>
                                    <small>$userRole</small>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a href="#profile" class="dropdown-item">
                                    <i class="fas fa-user"></i>
                                    <span>Profil anzeigen</span>
                                </a>
                                <a href="#change-password" class="dropdown-item" onclick="openPasswordChangeModal(event)">
                                    <i class="fas fa-key"></i>
                                    <span>Passwort ändern</span>
                                </a>
                                <a href="#settings" class="dropdown-item">
                                    <i class="fas fa-cog"></i>
                                    <span>Einstellungen</span>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="#logout" class="dropdown-item logout-item" onclick="handleLogout(event)">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Abmelden</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Sections -->
            <div class="admin-content">
                
                <!-- Dashboard Section -->
                <section id="dashboard-section" class="content-section active">
                    <div class="section-header">
                        <h2>Dashboard Übersicht</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i>
                                Aktualisieren
                            </button>
                        </div>
                    </div>

                    <div class="dashboard-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="totalSubmissions">{$stats['todaySubmissions']}</h3>
                                <p>Anfragen heute</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="activeServices">{$stats['activeServices']}</h3>
                                <p>Aktive Services</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="monthlySubmissions">{$stats['monthlySubmissions']}</h3>
                                <p>Anfragen diesen Monat</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="conversionRate">{$stats['conversionRate']}%</h3>
                                <p>Conversion Rate</p>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-content">
                        <div class="dashboard-left">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Neueste Anfragen</h3>
                                    <a href="#submissions" class="view-all">Alle anzeigen</a>
                                </div>
                                <div class="card-body">
                                    <div id="recentSubmissions" class="submissions-list">
HTML;

        // Generate recent submissions
        if (empty($recentSubmissions)) {
            echo '<div class="empty-state">Keine neuen Anfragen</div>';
        } else {
            foreach ($recentSubmissions as $submission) {
                $customerName = htmlspecialchars($submission['customer_name'] ?? 'Unbekannt');
                $serviceName = htmlspecialchars($submission['service_name'] ?? 'Unbekannter Service');
                $submittedAt = $this->formatDate($submission['submitted_at']);
                
                echo <<<HTML
                                        <div class="submission-item">
                                            <div class="submission-header">
                                                <strong>{$customerName}</strong>
                                                <span class="submission-date">{$submittedAt}</span>
                                            </div>
                                            <div class="submission-service">{$serviceName}</div>
                                            <div class="submission-actions">
                                                <button class="btn btn-sm btn-outline" onclick="viewSubmission('{$submission['id']}')">
                                                    <i class="fas fa-eye"></i> Anzeigen
                                                </button>
                                            </div>
                                        </div>
HTML;
            }
        }

        echo <<<HTML
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dashboard-right">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Service Performance</h3>
                                </div>
                                <div class="card-body">
                                    <div id="serviceChart" class="chart-container">
HTML;

        // Generate service chart
        if (empty($serviceStats)) {
            echo '<div class="empty-state">Keine Daten verfügbar</div>';
        } else {
            // Calculate max value for chart scaling
            $maxValue = max(array_column($serviceStats, 'submissions'));
            $maxValue = $maxValue > 0 ? $maxValue : 1;
            
            echo '<div class="simple-chart">';
            foreach ($serviceStats as $service) {
                $serviceName = htmlspecialchars($service['name']);
                $submissions = (int)$service['submissions'];
                $percentage = ($submissions / $maxValue) * 100;
                
                echo <<<HTML
                                            <div class="chart-item">
                                                <div class="chart-label">{$serviceName}</div>
                                                <div class="chart-bar">
                                                    <div class="chart-fill" style="width: {$percentage}%"></div>
                                                    <span class="chart-value">{$submissions}</span>
                                                </div>
                                            </div>
HTML;
            }
            echo '</div>';
        }

        echo <<<HTML
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Services Management Section -->
                <section id="services-section" class="content-section">
                    <div class="section-header">
                        <h2>Service-Verwaltung</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="openServiceModal()">
                                <i class="fas fa-plus"></i>
                                Neuer Service
                            </button>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-container">
                                <table id="servicesTable" class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Slug</th>
                                            <th>Status</th>
                                            <th>Reihenfolge</th>
                                            <th>Anfragen</th>
                                            <th>Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody id="servicesTableBody">
HTML;

        // Generate services table
        if (empty($services)) {
            echo '<tr><td colspan="6" class="text-center">Keine Services gefunden</td></tr>';
        } else {
            foreach ($services as $service) {
                $serviceName = htmlspecialchars($service['name']);
                $serviceSlug = htmlspecialchars($service['slug']);
                $serviceIcon = $service['icon'] ? 'public/assets/img/' . htmlspecialchars($service['icon']) : '';
                $serviceColor = htmlspecialchars($service['color'] ?? '#007cba');
                $isActive = (bool)$service['is_active'];
                $statusClass = $isActive ? 'active' : 'inactive';
                $statusText = $isActive ? 'Aktiv' : 'Inaktiv';
                $sortOrder = (int)$service['sort_order'];
                $submissionCount = (int)($service['submission_count'] ?? 0);
                
                
                $iconDisplay = $serviceIcon ? 
                    "<img src=\"{$serviceIcon}\" alt=\"{$serviceName}\" style=\"width: 16px; height: 16px; margin-right: 8px; object-fit: contain;\">" :
                    "<i class=\"fas fa-cog\" style=\"color: {$serviceColor}; margin-right: 8px;\"></i>";
                    
                echo <<<HTML
                                        <tr>
                                            <td data-label="Service">
                                                <div class="service-info">
                                                    {$iconDisplay}
                                                    <strong>{$serviceName}</strong>
                                                </div>
                                            </td>
                                            <td data-label="Slug"><code>{$serviceSlug}</code></td>
                                            <td data-label="Status">
                                                <span class="status-badge {$statusClass}">
                                                    {$statusText}
                                                </span>
                                            </td>
                                            <td data-label="Reihenfolge">{$sortOrder}</td>
                                            <td data-label="Anfragen">{$submissionCount}</td>
                                            <td data-label="Aktionen">
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-outline" onclick="editService({$service['id']})">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteService({$service['id']})" title="Löschen">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
HTML;
            }
        }

        echo <<<HTML
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Other sections with placeholder content -->
                <section id="service-pages-section" class="content-section">
                    <div class="section-header">
                        <h2>Seiteninhalte verwalten</h2>
                        <div class="section-actions">
                            <select id="servicePageSelect" class="form-select" onchange="loadServicePageContent()">
                                <option value="">Service auswählen...</option>
HTML;
        
        // Populate service select options with service IDs
        foreach ($services as $service) {
            $serviceName = htmlspecialchars($service['name']);
            $serviceSlug = htmlspecialchars($service['slug']);
            $serviceId = (int)$service['id'];
            echo "<option value=\"{$serviceSlug}\" data-service-id=\"{$serviceId}\">{$serviceName}</option>";
        }
        
        echo <<<HTML
                            </select>
                        </div>
                    </div>

                    <div id="servicePageContent" class="service-page-content" style="display: block;">
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>Service auswählen</h3>
                            <p>Wählen Sie einen Service aus, um die Seiteninhalte zu bearbeiten.</p>
                        </div>
                    </div>
                    
                    <!-- Service Content Form (initially hidden) -->
                    <div id="serviceContentForm" class="content-form" style="display: none;">
                        <form id="servicePageForm" class="admin-form">
                            <input type="hidden" id="servicePageId" name="service_id">
                            <input type="hidden" id="servicePageSlug" name="service_slug">
                            
                            <!-- Debug Info -->
                            <div class="form-section" style="background: #e8f4f8; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                                <h4 style="color: #0c5460;">🔍 Debug-Info</h4>
                                <p>Dieser Bereich sollte angezeigt werden, wenn Sie einen Service auswählen.</p>
                                <p><strong>Status:</strong> Formular ist geladen</p>
                            </div>
                            
                            <!-- SEO Settings -->
                            <div class="form-section">
                                <h3>SEO-Einstellungen</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="metaTitle">Meta-Titel</label>
                                        <input type="text" id="metaTitle" name="meta_title" class="form-control" maxlength="60">
                                        <small class="form-help">Optimale Länge: 50-60 Zeichen</small>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="metaDescription">Meta-Beschreibung</label>
                                        <textarea id="metaDescription" name="meta_description" class="form-control" rows="3" maxlength="160"></textarea>
                                        <small class="form-help">Optimale Länge: 150-160 Zeichen</small>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="metaKeywords">Keywords</label>
                                        <input type="text" id="metaKeywords" name="meta_keywords" class="form-control">
                                        <small class="form-help">Komma-getrennte Keywords</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hero Section -->
                            <div class="form-section">
                                <h3>Hero-Bereich</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="heroTitle">Titel</label>
                                        <input type="text" id="heroTitle" name="hero_title" class="form-control">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="heroSubtitle">Untertitel</label>
                                        <textarea id="heroSubtitle" name="hero_subtitle" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="heroCtaText">CTA-Button Text</label>
                                        <input type="text" id="heroCtaText" name="hero_cta_text" class="form-control" value="Jetzt kostenlos anfragen">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Intro Section -->
                            <div class="form-section">
                                <h3>Einführungsbereich</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="introTitle">Titel</label>
                                        <input type="text" id="introTitle" name="intro_title" class="form-control">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="introContent">Inhalt</label>
                                        <textarea id="introContent" name="intro_content" class="form-control" rows="5"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Features Section -->
                            <div class="form-section">
                                <h3>Features / Leistungen</h3>
                                <div class="form-row">
                                    <div class="form-group half-width">
                                        <label for="featuresTitle">Titel</label>
                                        <input type="text" id="featuresTitle" name="features_title" class="form-control">
                                    </div>
                                    <div class="form-group half-width">
                                        <label for="featuresSubtitle">Untertitel</label>
                                        <input type="text" id="featuresSubtitle" name="features_subtitle" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Features</label>
                                    <div id="featuresContainer" class="features-container">
                                        <!-- Dynamic features will be added here -->
                                    </div>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="addFeature()">
                                        <i class="fas fa-plus"></i> Feature hinzufügen
                                    </button>
                                    <input type="hidden" id="featuresContent" name="features_content">
                                </div>
                            </div>
                            
                            <!-- Process Section -->
                            <div class="form-section">
                                <h3>Ablaufschritte</h3>
                                <div class="form-row">
                                    <div class="form-group half-width">
                                        <label for="processTitle">Titel</label>
                                        <input type="text" id="processTitle" name="process_title" class="form-control">
                                    </div>
                                    <div class="form-group half-width">
                                        <label for="processSubtitle">Untertitel</label>
                                        <input type="text" id="processSubtitle" name="process_subtitle" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Schritte</label>
                                    <div id="processContainer" class="process-container">
                                        <!-- Dynamic process steps will be added here -->
                                    </div>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="addProcessStep()">
                                        <i class="fas fa-plus"></i> Schritt hinzufügen
                                    </button>
                                    <input type="hidden" id="processContent" name="process_content">
                                </div>
                            </div>
                            
                            <!-- Pricing Section -->
                            <div class="form-section">
                                <h3>Preise</h3>
                                <div class="form-row">
                                    <div class="form-group half-width">
                                        <label for="pricingTitle">Titel</label>
                                        <input type="text" id="pricingTitle" name="pricing_title" class="form-control">
                                    </div>
                                    <div class="form-group half-width">
                                        <label for="pricingSubtitle">Untertitel</label>
                                        <input type="text" id="pricingSubtitle" name="pricing_subtitle" class="form-control">
                                    </div>
                                </div>
                                <div class="info-box">
                                    <i class="fas fa-info-circle"></i>
                                    <p>Die Preise werden aus der Service-Verwaltung übernommen. Bearbeiten Sie die Preise im Services-Tab.</p>
                                </div>
                            </div>
                            
                            <!-- FAQ Section -->
                            <div class="form-section">
                                <h3>FAQ / Häufige Fragen</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="faqTitle">FAQ Titel</label>
                                        <input type="text" id="faqTitle" name="faq_title" class="form-control">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="faqContent">FAQ Inhalt (JSON Format)</label>
                                        <textarea id="faqContent" name="faq_content" class="form-control" rows="10" placeholder='[{"question": "Frage", "answer": "Antwort"}, {...}]'></textarea>
                                        <small class="form-help">JSON Format: Array von Objekten mit "question" und "answer" Feldern</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="resetServicePageForm()">Zurücksetzen</button>
                                <button type="submit" class="btn btn-primary">Inhalte speichern</button>
                            </div>
                        </form>
                    </div>
                </section>

                <!-- Media Management Section -->
                <section id="media-section" class="content-section">
                    <div class="section-header">
                        <h2>Bilder verwalten</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="openUploadModal()">
                                <i class="fas fa-upload"></i>
                                Bilder hochladen
                            </button>
                        </div>
                    </div>

                    <div class="media-controls">
                        <div class="media-filters">
                            <select id="mediaFilter" class="form-select" onchange="filterMedia()">
                                <option value="">Alle Dateien</option>
                                <option value="services">Bilder</option>
                                <option value="icons" disabled>Videos</option>
                                <option value="hero" disabled>Dokumente</option>
                                <option value="other" disabled>Sonstige</option>
                            </select>
                        </div>
                        
                        <div class="media-search">
                            <input type="text" id="mediaSearch" placeholder="Bilder suchen..." onkeyup="searchMedia()">
                            <i class="fas fa-search"></i>
                        </div>
                        
                        <div class="media-view-toggle">
                            <button class="btn-icon" onclick="setMediaView('grid')" title="Gitteransicht">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="btn-icon" onclick="setMediaView('list')" title="Listenansicht">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>

                    <div id="mediaGallery" class="media-gallery">
                        <div class="loading">Lade Medien...</div>
                    </div>
                </section>

                <!-- Questionnaires Section -->
                <section id="questionnaires-section" class="content-section">
                    <div class="section-header">
                        <h2>Fragebogen-Templates</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="showCreateQuestionnaireModal()">
                                <i class="fas fa-plus"></i>
                                Neuer Fragebogen
                            </button>
                            <button class="btn btn-outline" onclick="refreshQuestionnaires()">
                                <i class="fas fa-sync-alt"></i>
                                Aktualisieren
                            </button>
                        </div>
                    </div>

                    <div class="content-grid">
                        <!-- Search and Filter -->
                        <div class="grid-header">
                            <div class="search-box">
                                <input type="text" id="questionnaireSearch" placeholder="Fragebogen suchen..." oninput="filterQuestionnaires()">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="filter-options">
                                <select id="questionnaireServiceFilter" onchange="filterQuestionnaires()">
                                    <option value="">Alle Services</option>
HTML;
        
        // Add service options to questionnaire service filter
        foreach ($services as $service) {
            $serviceName = htmlspecialchars($service['name']);
            $serviceSlug = htmlspecialchars($service['slug']);
            $serviceId = (int)$service['id'];
            echo "<option value=\"{$serviceSlug}\" data-service-id=\"{$serviceId}\">{$serviceName}</option>";
        }
        
        echo <<<HTML
                                </select>
                                <select id="questionnaireStatusFilter" onchange="filterQuestionnaires()">
                                    <option value="">Alle Status</option>
                                    <option value="active">Aktiv</option>
                                    <option value="draft">Entwurf</option>
                                    <option value="archived">Archiviert</option>
                                </select>
                            </div>
                        </div>

                        <!-- Questionnaires List -->
                        <div class="data-table-container">
                            <table class="data-table" id="questionnairesTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Service</th>
                                        <th>Fragen</th>
                                        <th>Status</th>
                                        <th>Erstellt</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody id="questionnairesTableBody">
                                    <tr>
                                        <td colspan="6" class="loading-message">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Lade Fragebogen-Templates...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Questionnaire Editor (Hidden by default) -->
                        <div class="questionnaire-editor" id="questionnaireEditor" style="display: none;">
                            <div class="editor-header">
                                <h3 id="questionnaireEditorTitle">Fragebogen bearbeiten</h3>
                                <div class="editor-actions">
                                    <button class="btn btn-outline" onclick="previewQuestionnaire()">
                                        <i class="fas fa-eye"></i>
                                        Vorschau
                                    </button>
                                    <button class="btn btn-success" onclick="saveQuestionnaire()">
                                        <i class="fas fa-save"></i>
                                        Speichern
                                    </button>
                                    <button class="btn btn-secondary" onclick="cancelQuestionnaireEdit()">
                                        <i class="fas fa-times"></i>
                                        Abbrechen
                                    </button>
                                </div>
                            </div>
                            <div class="editor-content">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="questionnaireName">Name *</label>
                                        <input type="text" id="questionnaireName" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="questionnaireService">Service *</label>
                                        <select id="questionnaireService" required>
                                            <option value="">Service wählen...</option>
HTML;
        
        // Populate service select options with service IDs for questionnaires
        foreach ($services as $service) {
            $serviceName = htmlspecialchars($service['name']);
            $serviceSlug = htmlspecialchars($service['slug']);
            $serviceId = (int)$service['id'];
            echo "<option value=\"{$serviceSlug}\" data-service-id=\"{$serviceId}\">{$serviceName}</option>";
        }
        
        echo <<<HTML
                                        </select>
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="questionnaireDescription">Beschreibung</label>
                                        <textarea id="questionnaireDescription" rows="3" placeholder="Optionale Beschreibung des Fragebogens..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="questionnaireStatus">Status</label>
                                        <select id="questionnaireStatus">
                                            <option value="draft">Entwurf</option>
                                            <option value="active">Aktiv</option>
                                            <option value="archived">Archiviert</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Questions Management -->
                                <div class="questions-section">
                                    <div class="section-header">
                                        <h4>Fragen</h4>
                                        <div class="section-actions">
                                            <button class="btn btn-sm btn-primary" onclick="addQuestionToQuestionnaire()">
                                                <i class="fas fa-plus"></i>
                                                Frage hinzufügen
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Question Types Palette (hidden by default) -->
                                    <div class="question-palette" id="questionPalette" style="display: none;">
                                        <div class="palette-header">
                                            <h5>Fragetypen</h5>
                                            <button class="btn btn-sm" onclick="toggleQuestionPalette()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <div class="palette-items">
                                            <div class="palette-item" data-type="text" draggable="true">
                                                <i class="fas fa-font"></i>
                                                <span>Text</span>
                                            </div>
                                            <div class="palette-item" data-type="email" draggable="true">
                                                <i class="fas fa-envelope"></i>
                                                <span>E-Mail</span>
                                            </div>
                                            <div class="palette-item" data-type="phone" draggable="true">
                                                <i class="fas fa-phone"></i>
                                                <span>Telefon</span>
                                            </div>
                                            <div class="palette-item" data-type="textarea" draggable="true">
                                                <i class="fas fa-align-left"></i>
                                                <span>Textbereich</span>
                                            </div>
                                            <div class="palette-item" data-type="select" draggable="true">
                                                <i class="fas fa-list"></i>
                                                <span>Auswahl</span>
                                            </div>
                                            <div class="palette-item" data-type="radio" draggable="true">
                                                <i class="fas fa-dot-circle"></i>
                                                <span>Radio</span>
                                            </div>
                                            <div class="palette-item" data-type="checkbox" draggable="true">
                                                <i class="fas fa-check-square"></i>
                                                <span>Checkbox</span>
                                            </div>
                                            <div class="palette-item" data-type="date" draggable="true">
                                                <i class="fas fa-calendar"></i>
                                                <span>Datum</span>
                                            </div>
                                            <div class="palette-item" data-type="number" draggable="true">
                                                <i class="fas fa-hashtag"></i>
                                                <span>Zahl</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Groups and Questions Container -->
                                    <div class="questions-container" id="questionsContainer">
                                        <!-- New Group Drop Zone -->
                                        <div class="new-group-zone" id="newGroupZone" style="display: none;">
                                            <div class="drop-zone" id="newGroupDropZone">
                                                <i class="fas fa-layer-group"></i>
                                                <p>Ziehen Sie Fragen hierher, um eine neue Gruppe zu erstellen</p>
                                            </div>
                                        </div>

                                        <!-- Groups Container -->
                                        <div class="groups-container" id="groupsContainer">
                                            <!-- Groups will be inserted here dynamically -->
                                        </div>

                                        <!-- Ungrouped Questions -->
                                        <div class="question-group ungrouped-questions" id="ungroupedQuestions">
                                            <div class="group-header">
                                                <h5>Nicht gruppierte Fragen</h5>
                                                <div class="group-actions">
                                                    <button class="btn btn-sm btn-outline" onclick="addQuestionToQuestionnaire()" title="Fragetypen anzeigen">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="questions-list" id="questionsList" data-group-id="ungrouped">
                                                <div class="empty-message">
                                                    <i class="fas fa-question-circle"></i>
                                                    <p>Noch keine Fragen hinzugefügt</p>
                                                    <button class="btn btn-primary" onclick="addQuestionToQuestionnaire()">
                                                        Erste Frage hinzufügen
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Questions Management Section -->
                <section id="questions-section" class="content-section">
                    <div class="section-header">
                        <h2>Fragen verwalten</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="showCreateQuestionModal()">
                                <i class="fas fa-plus"></i>
                                Neue Frage
                            </button>
                            <button class="btn btn-outline" onclick="refreshQuestions()">
                                <i class="fas fa-sync-alt"></i>
                                Aktualisieren
                            </button>
                        </div>
                    </div>

                    <div class="content-grid">
                        <!-- Search and Filter -->
                        <div class="grid-header">
                            <div class="search-box">
                                <input type="text" id="questionSearch" placeholder="Frage suchen..." oninput="searchQuestions()">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="filter-options">
                                <select id="questionTypeFilter" onchange="filterQuestions()">
                                    <option value="">Alle Typen</option>
                                    <option value="text">Text</option>
                                    <option value="textarea">Textbereich</option>
                                    <option value="select">Auswahl</option>
                                    <option value="radio">Radio-Button</option>
                                    <option value="checkbox">Checkbox</option>
                                    <option value="number">Zahl</option>
                                    <option value="email">E-Mail</option>
                                    <option value="tel">Telefon</option>
                                    <option value="date">Datum</option>
                                </select>
                            </div>
                        </div>

                        <!-- Questions List -->
                        <div class="data-table-container">
                            <table class="data-table" id="questionsTable">
                                <thead>
                                    <tr>
                                        <th>Frage</th>
                                        <th>Typ</th>
                                        <th>Erforderlich</th>
                                        <th>Verwendet in</th>
                                        <th>Erstellt</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody id="questionsTableBody">
                                    <tr>
                                        <td colspan="6" class="loading-message">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Lade Fragen...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Question Editor (Hidden by default) -->
                        <div class="question-editor" id="questionEditor" style="display: none;">
                            <div class="editor-header">
                                <h3 id="questionEditorTitle">Frage bearbeiten</h3>
                                <div class="editor-actions">
                                    <button class="btn btn-outline" onclick="previewQuestion()">
                                        <i class="fas fa-eye"></i>
                                        Vorschau
                                    </button>
                                    <button class="btn btn-success" onclick="saveQuestion()">
                                        <i class="fas fa-save"></i>
                                        Speichern
                                    </button>
                                    <button class="btn btn-secondary" onclick="cancelQuestionEdit()">
                                        <i class="fas fa-times"></i>
                                        Abbrechen
                                    </button>
                                </div>
                            </div>
                            <div class="editor-content">
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label for="questionText">Fragetext *</label>
                                        <input type="text" id="questionText" required placeholder="Ihre Frage...">
                                    </div>
                                    <div class="form-group">
                                        <label for="questionType">Eingabetyp *</label>
                                        <select id="questionType" required onchange="updateQuestionOptions()">
                                            <option value="">Typ wählen...</option>
                                            <option value="text">Text (kurz)</option>
                                            <option value="textarea">Textbereich (lang)</option>
                                            <option value="select">Dropdown-Auswahl</option>
                                            <option value="radio">Radio-Buttons</option>
                                            <option value="checkbox">Checkboxes</option>
                                            <option value="number">Zahl</option>
                                            <option value="email">E-Mail-Adresse</option>
                                            <option value="tel">Telefonnummer</option>
                                            <option value="date">Datum</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="questionRequired">Erforderlich?</label>
                                        <select id="questionRequired">
                                            <option value="0">Nein</option>
                                            <option value="1">Ja</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="questionOrder">Reihenfolge</label>
                                        <input type="number" id="questionOrder" min="1" value="1">
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="questionPlaceholder">Platzhalter-Text</label>
                                        <input type="text" id="questionPlaceholder" placeholder="Optional: Hilfetext für Benutzer...">
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="questionHelp">Hilfetext</label>
                                        <textarea id="questionHelp" rows="2" placeholder="Optional: Zusätzliche Erklärungen..."></textarea>
                                    </div>

                                    <!-- Options for select, radio, checkbox -->
                                    <div class="form-group full-width" id="questionOptionsSection" style="display: none;">
                                        <label for="questionOptions">Optionen (eine pro Zeile)</label>
                                        <textarea id="questionOptions" rows="5" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                                        <small>Jede Zeile wird zu einer separaten Option</small>
                                    </div>

                                    <!-- Validation rules -->
                                    <div class="form-group" id="questionValidationSection">
                                        <label for="questionValidation">Validierung</label>
                                        <input type="text" id="questionValidation" placeholder="z.B. min:3, max:100, regex:...">
                                        <small>Optional: Validierungsregeln</small>
                                    </div>
                                </div>

                                <!-- Question Preview -->
                                <div class="question-preview-section">
                                    <h4>Vorschau</h4>
                                    <div class="question-preview" id="questionPreview">
                                        <p class="preview-placeholder">Wählen Sie einen Fragentyp um eine Vorschau zu sehen</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Submissions Section -->
                <section id="submissions-section" class="content-section">
                    <div class="section-header">
                        <h2>Eingehende Anfragen</h2>
                        <div class="section-actions">
                            <button class="btn btn-outline" onclick="showExportNotImplemented()">
                                <i class="fas fa-download"></i>
                                Exportieren
                            </button>
                            <button class="btn btn-outline" onclick="refreshSubmissions()">
                                <i class="fas fa-sync-alt"></i>
                                Aktualisieren
                            </button>
                        </div>
                    </div>

                    <div class="content-grid">
                        <!-- Search and Filter -->
                        <div class="grid-header">
                            <div class="search-box">
                                <input type="text" id="submissionSearch" placeholder="Anfrage suchen..." oninput="searchSubmissions()">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="filter-options">
                                <select id="submissionServiceFilter" onchange="filterSubmissions()">
                                    <option value="">Alle Services</option>
                                    <!-- Wird über JavaScript befüllt -->
                                </select>
                                <select id="submissionStatusFilter" onchange="filterSubmissions()">
                                    <option value="">Alle Status</option>
                                    <option value="new">Neu</option>
                                    <option value="in_progress">In Bearbeitung</option>
                                    <option value="completed">Abgeschlossen</option>
                                </select>
                                <input type="date" id="submissionDateFilter" onchange="filterSubmissions()">
                            </div>
                        </div>

                        <!-- Submissions List -->
                        <div class="data-table-container">
                            <table class="data-table" id="submissionsTable">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Service</th>
                                        <th>Kontakt</th>
                                        <th>Status</th>
                                        <th>Antworten</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody id="submissionsTableBody">
                                    <tr>
                                        <td colspan="6" class="loading-message">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Lade Anfragen...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Submission Details (Hidden by default) -->
                        <div class="submission-details" id="submissionDetails" style="display: none;">
                            <div class="details-header">
                                <h3 id="submissionDetailsTitle">Anfrage-Details</h3>
                                <div class="details-actions">
                                    <button class="btn btn-primary" onclick="respondToSubmission()">
                                        <i class="fas fa-reply"></i>
                                        Antworten
                                    </button>
                                    <button class="btn btn-outline" onclick="showPDFExportNotImplemented()">
                                        <i class="fas fa-file-pdf"></i>
                                        PDF
                                    </button>
                                    <button class="btn btn-secondary" onclick="closeSubmissionDetails()">
                                        <i class="fas fa-times"></i>
                                        Schließen
                                    </button>
                                </div>
                            </div>
                            <div class="details-content">
                                <div class="submission-info">
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <label>Service:</label>
                                            <span id="submissionService">-</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Eingereicht am:</label>
                                            <span id="submissionDate">-</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Status:</label>
                                            <span id="submissionStatus">-</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Kontakt:</label>
                                            <span id="submissionContact">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="submission-answers">
                                    <h4>Antworten</h4>
                                    <div id="submissionAnswersList">
                                        <!-- Wird über JavaScript befüllt -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Submission Archive Section -->
                <section id="submission-archive-section" class="content-section">
                    <div class="section-header">
                        <h2>Archiv (Anfragen älter als 30 Tage)</h2>
                        <div class="section-actions">
                            <button class="btn btn-outline" onclick="showArchiveExportNotImplemented()">
                                <i class="fas fa-download"></i>
                                Archiv exportieren
                            </button>
                            <button class="btn btn-outline" onclick="refreshArchive()">
                                <i class="fas fa-sync-alt"></i>
                                Aktualisieren
                            </button>
                        </div>
                    </div>

                    <!-- Archive Statistics -->
                    <div class="dashboard-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-archive"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="archivedSubmissionsCount">0</h3>
                                <p>Archivierte Anfragen</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="archivedOffersCount">0</h3>
                                <p>Archivierte Angebote</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-euro-sign"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="archivedOffersValue">0,00 €</h3>
                                <p>Gesamtwert Angebote</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-content">
                                <h3 id="oldestArchiveDate">-</h3>
                                <p>Ältester Eintrag</p>
                            </div>
                        </div>
                    </div>

                    <div class="content-grid">
                        <!-- Archive Search and Filter -->
                        <div class="grid-header">
                            <div class="search-box">
                                <input type="text" id="archiveSearch" placeholder="Archivierte Anfragen suchen..." oninput="searchArchive()">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="filter-options">
                                <select id="archiveServiceFilter" onchange="filterArchive()">
                                    <option value="">Alle Services</option>
                                    <!-- Wird über JavaScript befüllt -->
                                </select>
                                <select id="archiveStatusFilter" onchange="filterArchive()">
                                    <option value="">Alle Status</option>
                                    <option value="new">Neu</option>
                                    <option value="in_progress">In Bearbeitung</option>
                                    <option value="angebot_erstellt">Angebot erstellt</option>
                                    <option value="abgeschlossen">Abgeschlossen</option>
                                </select>
                                <select id="archiveDaysFilter" onchange="filterArchive()">
                                    <option value="30">Älter als 30 Tage</option>
                                    <option value="60">Älter als 60 Tage</option>
                                    <option value="90">Älter als 90 Tage</option>
                                    <option value="180">Älter als 6 Monate</option>
                                    <option value="365">Älter als 1 Jahr</option>
                                </select>
                            </div>
                        </div>

                        <!-- Archived Submissions List -->
                        <div class="data-table-container">
                            <table class="data-table" id="archivedSubmissionsTable">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Service</th>
                                        <th>Kontakt</th>
                                        <th>Status</th>
                                        <th>Alter (Tage)</th>
                                        <th>Angebote</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody id="archivedSubmissionsTableBody">
                                    <tr>
                                        <td colspan="7" class="loading-message">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Lade archivierte Anfragen...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Archived Submission Details (Hidden by default) -->
                        <div class="submission-details" id="archivedSubmissionDetails" style="display: none;">
                            <div class="details-header">
                                <h3 id="archivedSubmissionDetailsTitle">Archivierte Anfrage-Details</h3>
                                <div class="details-actions">
                                    <button class="btn btn-outline" onclick="showPDFExportNotImplemented()">
                                        <i class="fas fa-file-pdf"></i>
                                        PDF
                                    </button>
                                    <button class="btn btn-outline" onclick="showRestoreNotImplemented()">
                                        <i class="fas fa-undo"></i>
                                        Wiederherstellen
                                    </button>
                                    <button class="btn btn-secondary" onclick="closeArchivedSubmissionDetails()">
                                        <i class="fas fa-times"></i>
                                        Schließen
                                    </button>
                                </div>
                            </div>
                            <div class="details-content">
                                <div class="submission-info">
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <label>Service:</label>
                                            <span id="archivedSubmissionService">-</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Eingereicht am:</label>
                                            <span id="archivedSubmissionDate">-</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Archiviert seit:</label>
                                            <span id="archivedSubmissionAge">-</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Status:</label>
                                            <span id="archivedSubmissionStatus">-</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Kontakt:</label>
                                            <span id="archivedSubmissionContact">-</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Referenz:</label>
                                            <span id="archivedSubmissionReference">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="submission-answers">
                                    <h4>Antworten</h4>
                                    <div id="archivedSubmissionAnswersList">
                                        <!-- Wird über JavaScript befüllt -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- E-Mail-Posteingang Section -->
                <section id="email-inbox-section" class="content-section">
                    <div class="section-header">
                        <h2>E-Mail-Posteingang</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="refreshEmailInbox()">
                                <i class="fas fa-sync-alt"></i>
                                Aktualisieren
                            </button>
HTML;
                            
                            // Show debug buttons only if DEBUG_MODE is enabled
                            if (self::DEBUG_MODE) {
                                echo <<<'HTML'
                            <button class="btn btn-secondary" onclick="testEmailConnection()">
                                <i class="fas fa-plug"></i>
                                Verbindung testen
                            </button>
                            <button class="btn btn-info" onclick="showEventStorePanel()">
                                <i class="fas fa-bolt"></i>
                                Event Store
                            </button>
                            <button class="btn btn-success" onclick="createEmailSnapshot()">
                                <i class="fas fa-camera"></i>
                                Snapshot
                            </button>
                            <button class="btn btn-warning" onclick="debugEmailScrolling()">
                                <i class="fas fa-bug"></i>
                                Debug Scroll
                            </button>
HTML;
                            }
                            
                            echo <<<'HTML'
                        </div>
                    </div>
HTML;
                    
                    // Show Event Sourcing Info panel only if DEBUG_MODE is enabled
                    if (self::DEBUG_MODE) {
                        echo <<<'HTML'
                    
                    <!-- Event Sourcing Info -->
                    <div class="email-event-store-info" style="display: none;">
                        <!-- Wird über JavaScript befüllt -->
                    </div>
HTML;
                    }
                    
                    echo <<<'HTML'

                    <div class="email-inbox-container">
                        <!-- Sidebar mit Ordnern -->
                        <div class="email-sidebar">
                            <div class="email-folders">
                                <div class="folder-item active" data-folder="inbox" onclick="selectEmailFolder('inbox')">
                                    <i class="fas fa-inbox"></i>
                                    <span>Posteingang</span>
                                    <span class="unread-count" id="inboxUnreadCount">0</span>
                                </div>
                                <div class="folder-item" data-folder="sent" onclick="selectEmailFolder('sent')">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Gesendet</span>
                                </div>
                                <div class="folder-item" data-folder="drafts" onclick="selectEmailFolder('drafts')">
                                    <i class="fas fa-file-alt"></i>
                                    <span>Entwürfe</span>
                                </div>
                                <div class="folder-item" data-folder="spam" onclick="selectEmailFolder('spam')">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Spam</span>
                                </div>
                            </div>
                        </div>

                        <!-- E-Mail-Liste -->
                        <div class="email-list-container">
                            <div class="email-list-header">
                                <div class="email-search">
                                    <input type="text" id="emailSearch" placeholder="E-Mails durchsuchen..." oninput="searchEmails()">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="email-filters">
                                    <select id="emailFilter" onchange="filterEmails()">
                                        <option value="all">Alle E-Mails</option>
                                        <option value="unread">Ungelesen</option>
                                        <option value="flagged">Markiert</option>
                                        <option value="attachments">Mit Anhang</option>
                                    </select>
                                </div>
                            </div>

                            <div class="email-list" id="emailList">
                                <div class="loading-message">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Lade E-Mails...
                                </div>
                            </div>
                        </div>

                        <!-- E-Mail-Vorschau -->
                        <div class="email-preview-container" id="emailPreviewContainer">
                            <div class="no-email-selected">
                                <i class="fas fa-envelope-open-text"></i>
                                <h3>Keine E-Mail ausgewählt</h3>
                                <p>Wählen Sie eine E-Mail aus der Liste aus, um sie anzuzeigen</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Settings Section -->
                <section id="settings-section" class="content-section">
                    <div class="section-header">
                        <h2>System-Einstellungen</h2>
                        <div class="section-actions">
                            <button class="btn btn-secondary" onclick="loadSettings()">
                                <i class="fas fa-refresh"></i>
                                Neu laden
                            </button>
HTML;
        
        // Only show "Add New Setting" button for Admins
        $isAdmin = ($_SESSION['role'] ?? '') === 'Admin';
        if ($isAdmin) {
            echo <<<HTML
                            <button class="btn btn-primary" onclick="addNewSetting()">
                                <i class="fas fa-plus"></i>
                                Neue Einstellung
                            </button>
HTML;
        }
        
        echo <<<HTML
                            <button class="btn btn-success" onclick="saveAllSettings()">
                                <i class="fas fa-save"></i>
                                Alle speichern
                            </button>
                        </div>
                    </div>

                    <div class="settings-search">
                        <div class="search-container">
                            <input type="text" id="settingsSearch" placeholder="Settings durchsuchen..." class="form-control" onkeyup="filterSettings()">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>

                    <div class="settings-list">
                        <div id="settings-container">
                            <!-- Wird dynamisch gefüllt -->
                        </div>
                    </div>
                </section>

                <!-- Users Management Section -->
HTML;

        // Only show users section if user has users_view permission
        if ($permissions['users']) {
            echo <<<HTML
                <section id="users-section" class="content-section">
                    <div class="section-header">
                        <h2>Benutzerverwaltung</h2>
                        <div class="section-actions">
                            <button class="btn btn-secondary" onclick="refreshUsers()">
                                <i class="fas fa-refresh"></i>
                                Neu laden
                            </button>
                            <button class="btn btn-primary" onclick="openAddUserModal()">
                                <i class="fas fa-user-plus"></i>
                                Neuer Benutzer
                            </button>
                        </div>
                    </div>

                    <div class="users-search">
                        <div class="search-container">
                            <input type="text" id="usersSearch" placeholder="Benutzer durchsuchen..." class="form-control" onkeyup="filterUsers()">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>

                    <div class="users-table-container">
                        <table class="data-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Benutzername</th>
                                    <th>E-Mail</th>
                                    <th>Name</th>
                                    <th>Rolle</th>
                                    <th>Status</th>
                                    <th>Letzter Login</th>
                                    <th>Erstellt</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
HTML;

            // Generate users table
            if (empty($users)) {
                echo '<tr><td colspan="8" class="text-center">Keine Benutzer gefunden</td></tr>';
            } else {
                foreach ($users as $user) {
                    $userId = (int)$user['id'];
                    $username = htmlspecialchars($user['username']);
                    $email = htmlspecialchars($user['email']);
                    $firstName = htmlspecialchars($user['first_name'] ?? '');
                    $lastName = htmlspecialchars($user['last_name'] ?? '');
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Nicht angegeben';
                    $role = htmlspecialchars($user['role']);
                    $isActive = (bool)$user['is_active'];
                    $statusClass = $isActive ? 'active' : 'inactive';
                    $statusText = $isActive ? 'Aktiv' : 'Inaktiv';
                    $lastLogin = $user['last_login'] ? $this->formatDate($user['last_login']) : 'Nie';
                    $createdAt = $this->formatDate($user['created_at']);
                    $loginAttempts = (int)$user['login_attempts'];
                    
                    // Security indicators
                    $securityClass = '';
                    $securityIcon = '';
                    if ($loginAttempts >= 3) {
                        $securityClass = 'text-warning';
                        $securityIcon = '<i class="fas fa-exclamation-triangle" title="Mehrere Fehlversuche"></i>';
                    }
                    
                    echo <<<HTML
                                <tr data-user-id="$userId">
                                    <td data-label="Benutzername">
                                        <div class="user-info-cell">
                                            <strong>$username</strong>
                                            $securityIcon
                                        </div>
                                    </td>
                                    <td data-label="E-Mail">$email</td>
                                    <td data-label="Name">$fullName</td>
                                    <td data-label="Rolle">
                                        <span class="role-badge role-$role">$role</span>
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-badge $statusClass">$statusText</span>
                                    </td>
                                    <td data-label="Letzter Login" class="$securityClass">$lastLogin</td>
                                    <td data-label="Erstellt">$createdAt</td>
                                    <td data-label="Aktionen">
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-primary" onclick="editUser($userId)" title="Bearbeiten">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-warning" onclick="resetUserPassword($userId)" title="Passwort zurücksetzen">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button class="btn-icon btn-secondary" onclick="toggleUserStatus($userId, $isActive)" title="Status ändern">
                                                <i class="fas fa-toggle-" . ($isActive ? 'on' : 'off') . "></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
HTML;
                }
            }

            echo <<<HTML
                            </tbody>
                        </table>
                    </div>
                </section>
HTML;
        } else {
            // Show access denied message for users without users_view permission
            echo <<<HTML
                <section id="users-section" class="content-section">
                    <div class="access-denied">
                        <div class="empty-state">
                            <i class="fas fa-lock" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i>
                            <h3>Zugriff verweigert</h3>
                            <p>Sie haben keine Berechtigung zur Benutzerverwaltung.</p>
                        </div>
                    </div>
                </section>
HTML;
        }

        // Email Management Section
        if ($this->auth_middleware->hasPermission('email_templates_view')) {
            echo <<<HTML
                <!-- Email Management Section -->
                <section id="emails-section" class="content-section">
                    <div class="section-header">
                        <h2>E-Mail-Verwaltung</h2>
                        <div class="section-actions">
                            <button class="btn btn-secondary" onclick="loadEmailTemplates()">
                                <i class="fas fa-refresh"></i>
                                Neu laden
                            </button>
                            <button class="btn btn-primary" onclick="showNewEmailTemplateModal()">
                                <i class="fas fa-plus"></i>
                                Neue Vorlage
                            </button>
                        </div>
                    </div>

                    <div class="email-templates-container">
                        <div class="email-templates-list" id="email-templates-container">
                            <div class="loading-message">
                                <i class="fas fa-spinner fa-spin"></i>
                                Lade E-Mail-Templates...
                            </div>
                        </div>
                    </div>
                </section>
HTML;
        } else {
            // Show access denied message for users without email_templates_view permission
            echo <<<HTML
                <section id="emails-section" class="content-section">
                    <div class="access-denied">
                        <div class="empty-state">
                            <i class="fas fa-lock" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i>
                            <h3>Zugriff verweigert</h3>
                            <p>Sie haben nicht die erforderlichen Berechtigungen für diesen Bereich.</p>
                        </div>
                    </div>
                </section>
HTML;
        }

        // Logs Section - Only for Admins
        if ($permissions['logs_view']) {
            echo <<<HTML
                <!-- System Logs Section -->
                <section id="logs-section" class="content-section">
                    <div class="section-header">
                        <h2>System-Logs</h2>
                        <div class="section-actions">
                            <button class="btn btn-outline" onclick="refreshLogs()">
                                <i class="fas fa-sync-alt"></i>
                                Aktualisieren
                            </button>
                            <button class="btn btn-outline" onclick="exportLogs()">
                                <i class="fas fa-download"></i>
                                Exportieren
                            </button>
                        </div>
                    </div>

                    <!-- Log Type Tabs -->
                    <div class="logs-tabs">
                        <button class="tab-btn active" onclick="switchLogType('activity')" data-log-type="activity">
                            <i class="fas fa-user-clock"></i> Aktivitäts-Log
                        </button>
                        <button class="tab-btn" onclick="switchLogType('sessions')" data-log-type="sessions">
                            <i class="fas fa-sign-in-alt"></i> Benutzer-Sitzungen
                        </button>
                        <button class="tab-btn" onclick="switchLogType('email')" data-log-type="email">
                            <i class="fas fa-envelope-open-text"></i> E-Mail-Logs
                        </button>
                    </div>

                    <!-- Activity Log Tab -->
                    <div class="log-content active" id="activity-log-content">
                        <div class="content-grid">
                            <div class="grid-header">
                                <div class="search-box">
                                    <input type="text" id="activityLogSearch" placeholder="Aktivitäten suchen..." oninput="searchActivityLog()">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="filter-options">
                                    <select id="activityUserFilter" onchange="filterActivityLog()">
                                        <option value="">Alle Benutzer</option>
                                        <!-- Wird über JavaScript befüllt -->
                                    </select>
                                    <select id="activityActionFilter" onchange="filterActivityLog()">
                                        <option value="">Alle Aktionen</option>
                                        <option value="login_success">Login erfolgreich</option>
                                        <option value="login_failed">Login fehlgeschlagen</option>
                                        <option value="logout">Logout</option>
                                        <option value="session_created">Sitzung erstellt</option>
                                        <option value="create">Erstellt</option>
                                        <option value="update">Aktualisiert</option>
                                        <option value="delete">Gelöscht</option>
                                        <option value="view">Angesehen</option>
                                    </select>
                                    <input type="date" id="activityDateFilter" onchange="filterActivityLog()">
                                </div>
                            </div>

                            <div class="data-table-container">
                                <table class="data-table" id="activityLogTable">
                                    <thead>
                                        <tr>
                                            <th onclick="sortActivityLog('timestamp')">
                                                Zeitstempel <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortActivityLog('user')">
                                                Benutzer <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortActivityLog('action')">
                                                Aktion <i class="fas fa-sort"></i>
                                            </th>
                                            <th>Details</th>
                                            <th onclick="sortActivityLog('ip')">
                                                IP-Adresse <i class="fas fa-sort"></i>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="activityLogTableBody">
                                        <tr>
                                            <td colspan="5" class="loading-message">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                Lade Aktivitäts-Logs...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="pagination" id="activityLogPagination">
                                <!-- Wird über JavaScript befüllt -->
                            </div>
                        </div>
                    </div>

                    <!-- Sessions Log Tab -->
                    <div class="log-content" id="sessions-log-content">
                        <div class="content-grid">
                            <div class="grid-header">
                                <div class="search-box">
                                    <input type="text" id="sessionsLogSearch" placeholder="Sitzungen suchen..." oninput="searchSessionsLog()">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="filter-options">
                                    <select id="sessionsUserFilter" onchange="filterSessionsLog()">
                                        <option value="">Alle Benutzer</option>
                                        <!-- Wird über JavaScript befüllt -->
                                    </select>
                                    <select id="sessionsStatusFilter" onchange="filterSessionsLog()">
                                        <option value="">Alle Status</option>
                                        <option value="active">Aktiv</option>
                                        <option value="expired">Abgelaufen</option>
                                        <option value="logged_out">Abgemeldet</option>
                                    </select>
                                    <input type="date" id="sessionsDateFilter" onchange="filterSessionsLog()">
                                </div>
                            </div>

                            <div class="data-table-container">
                                <table class="data-table" id="sessionsLogTable">
                                    <thead>
                                        <tr>
                                            <th onclick="sortSessionsLog('created_at')">
                                                Erstellt <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortSessionsLog('user')">
                                                Benutzer <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortSessionsLog('ip')">
                                                IP-Adresse <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortSessionsLog('last_activity')">
                                                Letzte Aktivität <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortSessionsLog('status')">
                                                Status <i class="fas fa-sort"></i>
                                            </th>
                                            <th>Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sessionsLogTableBody">
                                        <tr>
                                            <td colspan="6" class="loading-message">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                Lade Sitzungs-Logs...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="pagination" id="sessionsLogPagination">
                                <!-- Wird über JavaScript befüllt -->
                            </div>
                        </div>
                    </div>

                    <!-- Email Log Tab -->
                    <div class="log-content" id="email-log-content">
                        <div class="content-grid">
                            <div class="grid-header">
                                <div class="search-box">
                                    <input type="text" id="emailLogSearch" placeholder="E-Mail-Logs suchen..." oninput="searchEmailLog()">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="filter-options">
                                    <select id="emailStatusFilter" onchange="filterEmailLog()">
                                        <option value="">Alle Status</option>
                                        <option value="sent">Gesendet</option>
                                        <option value="failed">Fehlgeschlagen</option>
                                        <option value="simulated_localhost">Simuliert (Localhost)</option>
                                    </select>
                                    <select id="emailTypeFilter" onchange="filterEmailLog()">
                                        <option value="">Alle Typen</option>
                                        <option value="auto_receipt_confirmation">Empfangsbestätigung</option>
                                        <option value="team_new_request_notification">Team-Benachrichtigung</option>
                                        <option value="quote_delivery">Angebot</option>
                                        <option value="request_confirmation">Anfrage-Bestätigung</option>
                                        <option value="request_declined">Anfrage abgelehnt</option>
                                        <option value="site_visit_request">Besichtigungstermin</option>
                                        <option value="completion_invoice">Abschlussrechnung</option>
                                    </select>
                                    <input type="date" id="emailDateFilter" onchange="filterEmailLog()">
                                </div>
                            </div>

                            <div class="data-table-container">
                                <table class="data-table" id="emailLogTable">
                                    <thead>
                                        <tr>
                                            <th onclick="sortEmailLog('timestamp')">
                                                Zeitstempel <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortEmailLog('recipient')">
                                                Empfänger <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortEmailLog('subject')">
                                                Betreff <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortEmailLog('type')">
                                                Typ <i class="fas fa-sort"></i>
                                            </th>
                                            <th onclick="sortEmailLog('status')">
                                                Status <i class="fas fa-sort"></i>
                                            </th>
                                            <th>Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody id="emailLogTableBody">
                                        <tr>
                                            <td colspan="6" class="loading-message">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                Lade E-Mail-Logs...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="pagination" id="emailLogPagination">
                                <!-- Wird über JavaScript befüllt -->
                            </div>
                        </div>
                    </div>
                </section>
HTML;
        } else {
            echo <<<HTML
                <section id="logs-section" class="content-section">
                    <div class="access-denied">
                        <div class="empty-state">
                            <i class="fas fa-lock" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i>
                            <h3>Zugriff verweigert</h3>
                            <p>Sie haben nicht die erforderlichen Berechtigungen für System-Logs.</p>
                            <p><small>Nur Administratoren können auf diesen Bereich zugreifen.</small></p>
                        </div>
                    </div>
                </section>
HTML;
        }

        echo <<<HTML
            </div>
        </main>
    </div>

    <!-- User Edit Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="userModalTitle">Benutzer bearbeiten</h3>
                <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="userForm" class="admin-form">
                    <input type="hidden" id="userId" name="userId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="userUsername">Benutzername *</label>
                            <input type="text" id="userUsername" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="userEmail">E-Mail *</label>
                            <input type="email" id="userEmail" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="userFirstName">Vorname</label>
                            <input type="text" id="userFirstName" name="first_name">
                        </div>
                        <div class="form-group">
                            <label for="userLastName">Nachname</label>
                            <input type="text" id="userLastName" name="last_name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="userRole">Rolle *</label>
                            <select id="userRole" name="role" required>
                                <option value="Mitarbeiter">Mitarbeiter</option>
                                <option value="Moderator">Moderator</option>
                                <option value="Chef">Chef</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="userActive">Status</label>
                            <select id="userActive" name="is_active">
                                <option value="1">Aktiv</option>
                                <option value="0">Inaktiv</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" id="passwordSection" style="display: none;">
                        <label for="userPassword">Neues Passwort</label>
                        <input type="password" id="userPassword" name="password" minlength="6">
                        <small class="form-help">Leer lassen, um Passwort nicht zu ändern</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('userModal')">
                    Abbrechen
                </button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">
                    <i class="fas fa-save"></i> Speichern
                </button>
            </div>
        </div>
    </div>

    <!-- Service Modal -->
    <div id="serviceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="serviceModalTitle">Service hinzufügen</h3>
                <button class="modal-close" onclick="closeModal('serviceModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="serviceForm">
                    <input type="hidden" id="serviceId" name="id">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="serviceName">Name *</label>
                            <input type="text" id="serviceName" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="serviceSlug">URL-Slug *</label>
                            <input type="text" id="serviceSlug" name="slug" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="serviceTitle">Seiten-Titel</label>
                        <input type="text" id="serviceTitle" name="title" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="serviceDescription">Beschreibung</label>
                        <textarea id="serviceDescription" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="serviceIcon">Icon</label>
                            <select id="serviceIcon" name="icon" class="form-control">
                                <option value="">-- Bild auswählen --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="serviceColor">Farbe</label>
                            <input type="color" id="serviceColor" name="color" class="form-control" value="#007cba">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="serviceSortOrder">Reihenfolge</label>
                            <input type="number" id="serviceSortOrder" name="sort_order" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label for="serviceActive">Status</label>
                            <select id="serviceActive" name="is_active" class="form-select">
                                <option value="1">Aktiv</option>
                                <option value="0">Inaktiv</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Preise Section -->
                    <div class="form-section">
                        <h4>Preise verwalten</h4>
                        <div class="pricing-controls">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addPriceItem()">
                                <i class="fas fa-plus"></i> Preis hinzufügen
                            </button>
                        </div>
                        <div id="pricingItems" class="pricing-items">
                            <!-- Dynamic pricing items will be added here -->
                        </div>
                        <input type="hidden" id="pricingData" name="pricing_data">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('serviceModal')">Abbrechen</button>
                <button type="submit" class="btn btn-primary" onclick="saveService()">Speichern</button>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bilder hochladen</h3>
                <button class="modal-close" onclick="closeModal('uploadModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="upload-area" id="uploadArea">
                    <div class="upload-content">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h4>Bilder hier hinziehen oder klicken zum Auswählen</h4>
                        <p>Unterstützte Formate: JPG, PNG, GIF, WebP, SVG (max. 5MB)</p>
                        <input type="file" id="fileInput" multiple accept="image/*,.svg" style="display: none;">
                    </div>
                </div>
                <div id="uploadPreview" class="upload-preview"></div>
                <div id="uploadProgress" class="upload-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <span class="progress-text">0%</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Abbrechen</button>
                <button type="button" class="btn btn-primary" onclick="startUpload()" id="uploadBtn" disabled>
                    <i class="fas fa-upload"></i>
                    Hochladen
                </button>
            </div>
        </div>
    </div>

    <!-- Group Edit Modal -->
    <div id="editGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Gruppe bearbeiten</h3>
                <button class="modal-close" onclick="closeModal('editGroupModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="editGroupName">Gruppenname *</label>
                    <input type="text" id="editGroupName" class="form-control" placeholder="Gruppenname eingeben" required>
                </div>
                <div class="form-group">
                    <label for="editGroupDescription">Beschreibung</label>
                    <textarea id="editGroupDescription" class="form-control" rows="3" placeholder="Gruppenbeschreibung eingeben (optional)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editGroupModal')">Abbrechen</button>
                <button type="button" class="btn btn-primary" onclick="saveGroupChanges()" id="saveGroupBtn">
                    <i class="fas fa-save"></i>
                    Speichern
                </button>
            </div>
        </div>
    </div>

    <!-- Response Modal -->
    <div id="responseModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3>Auf Anfrage antworten</h3>
                <button class="modal-close" onclick="closeModal('responseModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="response-summary">
                    <div class="submission-info">
                        <h4 id="responseSummaryTitle">Anfrage Details</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Kunde:</label>
                                <span id="responseCustomerName">-</span>
                            </div>
                            <div class="info-item">
                                <label>Service:</label>
                                <span id="responseServiceName">-</span>
                            </div>
                            <div class="info-item">
                                <label>Datum:</label>
                                <span id="responseDate">-</span>
                            </div>
                            <div class="info-item">
                                <label>Referenz:</label>
                                <span id="responseReference">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="completion-stats">
                        <div class="completion-badge">
                            <div class="completion-circle">
                                <span id="responseCompletionPercent">0%</span>
                            </div>
                            <div class="completion-text">
                                <span id="responseCompletionCount">0 von 0</span>
                                <small>Fragen beantwortet</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="response-tabs">
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="showResponseTab('actions')">
                            <i class="fas fa-tasks"></i> Aktionen
                        </button>
                        <button class="tab-btn" onclick="showResponseTab('offers')">
                            <i class="fas fa-file-invoice-dollar"></i> Angebote
                        </button>
                        <button class="tab-btn" onclick="showResponseTab('answers')">
                            <i class="fas fa-list-alt"></i> Antworten ansehen
                        </button>
                        <button class="tab-btn" onclick="showResponseTab('status')">
                            <i class="fas fa-flag"></i> Status ändern
                        </button>
                    </div>
                    
                    <!-- Actions Tab -->
                    <div class="tab-content active" id="actionsTab">
                        <div class="action-grid">
                            <div class="action-card">
                                <div class="action-header">
                                    <i class="fas fa-envelope"></i>
                                    <h4>E-Mail senden</h4>
                                </div>
                                <div class="action-body">
                                    <div class="form-group">
                                        <label>E-Mail Template:</label>
                                        <select id="emailTemplate" class="form-select">
                                            <option value="confirmation">Bestätigung</option>
                                            <option value="quote_ready">Angebot bereit</option>
                                            <option value="appointment">Terminvorschlag</option>
                                            <option value="follow_up">Nachfrage</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Persönliche Nachricht (optional):</label>
                                        <textarea id="customMessage" rows="3" placeholder="Zusätzliche persönliche Nachricht..."></textarea>
                                    </div>
                                    <button class="btn btn-primary" onclick="showEmailNotImplemented()">
                                        <i class="fas fa-paper-plane"></i> E-Mail senden
                                    </button>
                                </div>
                            </div>
                            
                            <div class="action-card">
                                <div class="action-header">
                                    <i class="fas fa-file-invoice"></i>
                                    <h4>Angebot erstellen</h4>
                                </div>
                                <div class="action-body">
                                    <p>Erstellt ein PDF-Angebot basierend auf den Antworten des Kunden.</p>
                                    <button class="btn btn-primary" onclick="generateOffer()">
                                        <i class="fas fa-file-pdf"></i> Angebot generieren
                                    </button>
                                </div>
                            </div>
                            
                            <div class="action-card">
                                <div class="action-header">
                                    <i class="fas fa-calendar-check"></i>
                                    <h4>Termin planen</h4>
                                </div>
                                <div class="action-body">
                                    <div class="form-group">
                                        <label>Terminart:</label>
                                        <select class="form-select">
                                            <option>Besichtigung vor Ort</option>
                                            <option>Beratungsgespräch</option>
                                            <option>Service-Termin</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary" onclick="showAppointmentNotImplemented()">
                                        <i class="fas fa-calendar-plus"></i> Termin vorschlagen
                                    </button>
                                </div>
                            </div>
                            
                            <div class="action-card">
                                <div class="action-header">
                                    <i class="fas fa-phone"></i>
                                    <h4>Kontakt aufnehmen</h4>
                                </div>
                                <div class="action-body">
                                    <div class="contact-info">
                                        <div class="contact-item">
                                            <i class="fas fa-envelope"></i>
                                            <span id="responseCustomerEmail">-</span>
                                        </div>
                                        <div class="contact-item">
                                            <i class="fas fa-phone"></i>
                                            <span id="responseCustomerPhone">-</span>
                                        </div>
                                    </div>
                                    <button class="btn btn-outline" onclick="showContactNotImplemented()">
                                        <i class="fas fa-external-link-alt"></i> Kontakt öffnen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Offers Tab -->
                    <div class="tab-content" id="offersTab">
                        <div class="offers-section">
                            <div class="offers-header">
                                <h4>Erstellte Angebote</h4>
                                <button class="btn btn-outline" onclick="refreshOffers()">
                                    <i class="fas fa-sync-alt"></i> Aktualisieren
                                </button>
                            </div>
                            
                            <div id="offersContainer">
                                <div class="loading-message">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Lade Angebote...
                                </div>
                            </div>
                            
                            <div class="offers-actions">
                                <button class="btn btn-primary" onclick="generateOffer()">
                                    <i class="fas fa-plus"></i> Neues Angebot erstellen
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Answers Tab -->
                    <div class="tab-content" id="answersTab">
                        <div class="answers-content">
                            <!-- E-Mail Korrespondenz Section -->
                            <div class="email-correspondence-section">
                                <h4><i class="fas fa-envelope"></i> E-Mail Korrespondenz</h4>
                                <div class="submission-emails" id="submissionEmailsList">
                                    <div class="loading-spinner">
                                        <i class="fas fa-spinner fa-spin"></i> Lade E-Mails...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Tab -->
                    <div class="tab-content" id="statusTab">
                        <div class="status-form">
                            <div class="form-group">
                                <label>Status ändern:</label>
                                <select id="newStatus" class="form-select">
                                    <option value="neu">Neu</option>
                                    <option value="in_bearbeitung">In Bearbeitung</option>
                                    <option value="angebot_erstellt">Angebot erstellt</option>
                                    <option value="termin_geplant">Termin geplant</option>
                                    <option value="abgeschlossen">Abgeschlossen</option>
                                    <option value="storniert">Storniert</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Notizen (optional):</label>
                                <textarea id="statusNotes" rows="4" placeholder="Interne Notizen zum Status..."></textarea>
                            </div>
                            <button class="btn btn-primary" onclick="updateStatus()">
                                <i class="fas fa-save"></i> Status aktualisieren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Angebot erstellen Modal -->
    <div id="offerModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3>Angebot erstellen</h3>
                <button class="modal-close" onclick="closeModal('offerModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="offerForm">
                    <div class="offer-summary">
                        <h4>Anfrage-Details</h4>
                        <div class="offer-info-grid">
                            <div class="info-item">
                                <label>Kunde:</label>
                                <span id="offerCustomerName">-</span>
                            </div>
                            <div class="info-item">
                                <label>Service:</label>
                                <span id="offerServiceName">-</span>
                            </div>
                            <div class="info-item">
                                <label>Referenz:</label>
                                <span id="offerReference">-</span>
                            </div>
                            <div class="info-item">
                                <label>Datum:</label>
                                <span id="offerDate">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="offer-sections">
                        <!-- Preise Section -->
                        <div class="offer-section">
                            <h4>
                                <i class="fas fa-euro-sign"></i>
                                Preisgestaltung
                            </h4>
                            <div class="pricing-items" id="offerPricingItems">
                                <!-- Dynamic pricing items will be added here -->
                            </div>
                            <div class="pricing-controls">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="addOfferPriceItem()">
                                    <i class="fas fa-plus"></i> Position hinzufügen
                                </button>
                            </div>
                            <div class="pricing-total">
                                <div class="total-row">
                                    <label>Gesamtsumme (netto):</label>
                                    <span id="offerTotalNet" class="total-amount">0,00 €</span>
                                </div>
                                <div class="total-row">
                                    <label>MwSt. (19%):</label>
                                    <span id="offerTotalVAT" class="vat-amount">0,00 €</span>
                                </div>
                                <div class="total-row total-final">
                                    <label>Gesamtsumme (brutto):</label>
                                    <span id="offerTotalGross" class="total-final-amount">0,00 €</span>
                                </div>
                            </div>
                        </div>

                        <!-- Anmerkungen Section -->
                        <div class="offer-section">
                            <h4>
                                <i class="fas fa-sticky-note"></i>
                                Anmerkungen & Bedingungen
                            </h4>
                            <div class="form-group">
                                <label for="offerNotes">Zusätzliche Anmerkungen:</label>
                                <textarea id="offerNotes" rows="4" class="form-control" placeholder="Besondere Hinweise, Bedingungen oder Anmerkungen zum Angebot..."></textarea>
                            </div>
                            <div class="form-group">
                                <label for="offerTerms">Zahlungsbedingungen:</label>
                                <textarea id="offerTerms" rows="3" class="form-control" placeholder="z.B. Zahlung nach Rechnungsstellung, 14 Tage Zahlungsziel...">Zahlung nach Rechnungsstellung mit 14 Tagen Zahlungsziel.
Alle Preise verstehen sich zzgl. der gesetzlichen MwSt.
Angebot gültig für 30 Tage ab Ausstellungsdatum.</textarea>
                            </div>
                        </div>

                        <!-- Gültigkeit Section -->
                        <div class="offer-section">
                            <h4>
                                <i class="fas fa-calendar-alt"></i>
                                Gültigkeit & Termine
                            </h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="offerValidUntil">Angebot gültig bis:</label>
                                    <input type="date" id="offerValidUntil" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="offerExecutionDate">Gewünschter Ausführungstermin:</label>
                                    <input type="date" id="offerExecutionDate" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('offerModal')">
                    Abbrechen
                </button>
                <button type="button" class="btn btn-outline" onclick="previewOffer()">
                    <i class="fas fa-eye"></i> Vorschau
                </button>
                <button type="button" class="btn btn-primary" onclick="generateOfferPDF()">
                    <i class="fas fa-file-pdf"></i> PDF erstellen
                </button>
            </div>
        </div>
    </div>

    <!-- Setting Edit Modal -->
    <div id="settingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="settingModalTitle">Einstellung bearbeiten</h3>
                <button class="modal-close" onclick="closeModal('settingModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="settingForm">
                    <input type="hidden" id="settingId" name="id">
                    <div class="form-group">
                        <label for="settingKey">Setting Key *</label>
                        <input type="text" id="settingKey" name="key" class="form-control" required>
                        <small class="form-text text-muted">Eindeutiger Schlüssel für die Einstellung (nur Buchstaben, Zahlen und Unterstriche)</small>
                    </div>
                    <div class="form-group">
                        <label for="settingValue">Wert</label>
                        <div id="settingValueContainer">
                            <!-- Wird basierend auf Typ dynamisch erstellt -->
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="settingType">Typ</label>
                            <select id="settingType" name="type" class="form-control" onchange="updateSettingValueField()">
                                <option value="string">String (Text)</option>
                                <option value="int">Integer (Zahl)</option>
                                <option value="bool">Boolean (Ja/Nein)</option>
                                <option value="json">JSON (Objekt/Array)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="settingPublic">Öffentlich sichtbar</label>
                            <select id="settingPublic" name="is_public" class="form-control">
                                <option value="1">Ja (öffentlich verfügbar)</option>
                                <option value="0" class="admin-only-option">Nein (nur Admin)</option>
                            </select>
                            <small class="form-text text-muted">Öffentliche Einstellungen können von der Website frontend abgerufen werden</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="settingDescription">Beschreibung</label>
                        <textarea id="settingDescription" name="description" class="form-control" rows="3" placeholder="Beschreibung der Einstellung (optional)"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('settingModal')">Abbrechen</button>
                <button class="btn btn-primary" onclick="saveSetting()">Speichern</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

HTML;
        
        // Output JavaScript data separately to avoid heredoc issues
        echo '<script>';
        echo 'window.adminData = {';
        echo 'services: ' . json_encode($services, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ',';
        echo 'stats: ' . json_encode($stats, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ',';
        echo 'recentSubmissions: ' . json_encode($recentSubmissions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ',';
        echo 'serviceStats: ' . json_encode($serviceStats, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ',';
        echo 'permissions: ' . json_encode($permissions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ',';
        echo 'currentUser: ' . json_encode(['role' => $userRole, 'username' => $userName], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ',';
        echo 'debugMode: ' . (self::DEBUG_MODE ? 'true' : 'false');
        echo '};';
        
        // Add permission-based section control
        echo <<<'JS'
        
        // Apply permission-based access control
        document.addEventListener('DOMContentLoaded', function() {
            const permissions = window.adminData.permissions;
            
            // Hide sections without view permission and show access denied message
            const sections = {
                'services-section': permissions.services,
                'service-pages-section': permissions.service_pages,
                'media-section': permissions.media,
                'emails-section': permissions.email_templates,
                'questionnaires-section': permissions.questionnaires,
                'questions-section': permissions.questions,
                'submissions-section': permissions.submissions,
                'submission-archive-section': permissions.submission_archive,
                'settings-section': permissions.settings,
                'users-section': permissions.users,
                'logs-section': permissions.logs_view
            };
            
            // Hide manage buttons without manage permission
            const manageButtons = {
                'services-section .section-actions': permissions.services_manage,
                'service-pages-section .section-actions button[onclick*="save"]': permissions.service_pages_manage,
                'media-section .section-actions': permissions.media_manage,
                'emails-section .section-actions': permissions.email_templates_manage,
                'questionnaires-section .section-actions': permissions.questionnaires_manage,
                'questions-section .section-actions': permissions.questions_manage,
                'submissions-section .action-buttons': permissions.submissions_manage,
                'submission-archive-section .action-buttons': permissions.submission_archive_manage,
                'settings-section .section-actions': permissions.settings_manage,
                'users-section .section-actions': permissions.users_manage
            };
            
            // Apply section visibility
            Object.keys(sections).forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (section && !sections[sectionId]) {
                    section.innerHTML = `
                        <div class="access-denied">
                            <div class="empty-state">
                                <i class="fas fa-lock" style="font-size: 3rem; color: #e74c3c; margin-bottom: 1rem;"></i>
                                <h3>Zugriff verweigert</h3>
                                <p>Sie haben nicht die erforderlichen Berechtigungen für diesen Bereich.</p>
                            </div>
                        </div>
                    `;
                }
            });
            
            // Hide manage buttons
            Object.keys(manageButtons).forEach(selector => {
                if (!manageButtons[selector]) {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(element => {
                        element.style.display = 'none';
                    });
                }
            });
            
            // Add read-only indicators for users with view but not manage permissions
            if (permissions.services && !permissions.services_manage) {
                addReadOnlyIndicator('services-section', 'Sie können Services nur einsehen, nicht bearbeiten.');
            }
            if (permissions.service_pages && !permissions.service_pages_manage) {
                addReadOnlyIndicator('service-pages-section', 'Sie können Seiteninhalte nur einsehen, nicht bearbeiten.');
            }
            if (permissions.settings && !permissions.settings_manage) {
                addReadOnlyIndicator('settings-section', 'Sie können Einstellungen nur einsehen, nicht bearbeiten.');
            }
            if (permissions.email_templates && !permissions.email_templates_manage) {
                addReadOnlyIndicator('emails-section', 'Sie können E-Mail-Vorlagen nur einsehen, nicht bearbeiten.');
            }
            if (permissions.users && !permissions.users_manage) {
                addReadOnlyIndicator('users-section', 'Sie können Benutzer nur einsehen, nicht verwalten.');
            }
        });
        
        function addReadOnlyIndicator(sectionId, message) {
            const section = document.getElementById(sectionId);
            if (section) {
                const header = section.querySelector('.section-header');
                if (header) {
                    const indicator = document.createElement('div');
                    indicator.className = 'read-only-indicator';
                    indicator.style.cssText = 'background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 0.5rem 1rem; border-radius: 4px; margin-top: 0.5rem; font-size: 0.875rem;';
                    indicator.innerHTML = `<i class="fas fa-eye"></i> ${message}`;
                    header.appendChild(indicator);
                }
            }
        }
        
        // Admin Role Protection
        document.addEventListener('DOMContentLoaded', function() {
            const currentUserRole = window.adminData?.currentUser?.role;
            
            // Hide Admin option in role dropdown if current user is not Admin
            if (currentUserRole !== 'Admin') {
                const roleSelect = document.getElementById('userRole');
                if (roleSelect) {
                    const adminOption = roleSelect.querySelector('option[value="Admin"]');
                    if (adminOption) {
                        adminOption.style.display = 'none';
                    }
                }
            }
        });
        
        // Helper function to check if current user can manage admin accounts
        function canManageAdminAccounts(userId = null) {
            const currentUserRole = window.adminData?.currentUser?.role;
            
            if (currentUserRole === 'Admin') {
                return true; // Admins can manage all accounts
            }
            
            if (userId) {
                // Check if target user is Admin by finding the row
                const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                if (row) {
                    const cells = row.querySelectorAll('td');
                    const targetRole = cells[3]?.querySelector('.role-badge')?.textContent;
                    return targetRole !== 'Admin'; // Non-admins can only manage non-admin accounts
                }
            }
            
            return false;
        }

JS;
        
        // Pricing management JavaScript
        echo <<<'JS'
        // Navigation Helper
        function navigateToSection(sectionName) {
            if (typeof showSection === 'function') {
                showSection(sectionName);
            } else if (window.showSection) {
                window.showSection(sectionName);
            }
        }
        
        // Show info for features in development
        function showNewMessageInfo() {
            showToast('Nachrichtenfunktion wird noch implementiert', 'info');
        }
        
        function showEmailNotImplemented() {
            showToast('E-Mail-Funktion wird noch implementiert', 'info');
        }
        
        function showAppointmentNotImplemented() {
            showToast('Terminplanungs-Funktion wird noch implementiert', 'info');
        }
        
        function showContactNotImplemented() {
            showToast('Kontaktmanagement-Funktion wird noch implementiert', 'info');
        }
        
        function showExportNotImplemented() {
            showToast('Export-Funktion wird noch implementiert', 'info');
        }
        
        function showArchiveExportNotImplemented() {
            showToast('Archiv-Export-Funktion wird noch implementiert', 'info');
        }
        
        function showPDFExportNotImplemented() {
            showToast('PDF-Export-Funktion wird noch implementiert', 'info');
        }
        
        function showRestoreNotImplemented() {
            showToast('Wiederherstellungs-Funktion wird noch implementiert', 'info');
        }
        
        // Logs Management Functions
        let currentLogType = 'activity';
        let currentLogPage = 1;
        let logsPerPage = 50;
        let currentLogSort = { field: 'created_at', direction: 'desc' };
        
        // Helper function to translate email status to German
        function translateEmailStatus(status) {
            const translations = {
                'sent': 'Gesendet',
                'failed': 'Fehlgeschlagen',
                'simulated_localhost': 'Simuliert (Localhost)',
                'pending': 'Ausstehend'
            };
            return translations[status] || status;
        }
        
        function switchLogType(type) {
            currentLogType = type;
            currentLogPage = 1;
            
            // Update active tab
            document.querySelectorAll('.logs-tabs .tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-log-type="${type}"]`).classList.add('active');
            
            // Show corresponding content
            document.querySelectorAll('.log-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`${type}-log-content`).classList.add('active');
            
            // Load data for the selected log type
            loadLogs(type);
        }
        
        function loadLogs(type = currentLogType) {
            const tableBody = document.getElementById(`${type}LogTableBody`);
            if (!tableBody) {
                console.error(`Table body not found for type: ${type}`);
                return;
            }
            
            console.log(`Loading logs for type: ${type}, page: ${currentLogPage}`);
            
            tableBody.innerHTML = `<tr><td colspan="5" class="loading-message"><i class="fas fa-spinner fa-spin"></i> Lade ${type}-Logs...</td></tr>`;
            
            // Prepare filters
            const filters = getLogFilters(type);
            const url = `/api/admin.php?action=get-${type}-logs&page=${currentLogPage}&limit=${logsPerPage}&sort=${currentLogSort.field}&direction=${currentLogSort.direction}&${filters}`;
            
            console.log('Fetching logs from:', url);
            
            fetch(url)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Received data:', data);
                    if (data.success) {
                        renderLogTable(type, data.logs);
                        renderPagination(type, data.pagination);
                    } else {
                        console.error('API returned error:', data.message);
                        showToast(data.message || 'Fehler beim Laden der Logs', 'error');
                        tableBody.innerHTML = `<tr><td colspan="5" class="text-center">Fehler beim Laden der Daten</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error('Error loading logs:', error);
                    showToast('Netzwerkfehler beim Laden der Logs', 'error');
                    tableBody.innerHTML = `<tr><td colspan="5" class="text-center">Netzwerkfehler</td></tr>`;
                });
        }
        
        function getLogFilters(type) {
            const params = new URLSearchParams();
            
            if (type === 'activity') {
                const search = document.getElementById('activityLogSearch')?.value;
                const user = document.getElementById('activityUserFilter')?.value;
                const action = document.getElementById('activityActionFilter')?.value;
                const date = document.getElementById('activityDateFilter')?.value;
                
                if (search) params.append('search', search);
                if (user) params.append('user', user);
                if (action) params.append('log_action', action); // Changed to log_action to avoid conflict
                if (date) params.append('date', date);
            } else if (type === 'sessions') {
                const search = document.getElementById('sessionsLogSearch')?.value;
                const user = document.getElementById('sessionsUserFilter')?.value;
                const status = document.getElementById('sessionsStatusFilter')?.value;
                const date = document.getElementById('sessionsDateFilter')?.value;
                
                if (search) params.append('search', search);
                if (user) params.append('user', user);
                if (status) params.append('status', status);
                if (date) params.append('date', date);
            } else if (type === 'email') {
                const search = document.getElementById('emailLogSearch')?.value;
                const status = document.getElementById('emailStatusFilter')?.value;
                const emailType = document.getElementById('emailTypeFilter')?.value;
                const date = document.getElementById('emailDateFilter')?.value;
                
                if (search) params.append('search', search);
                if (status) params.append('status', status);
                if (emailType) params.append('type', emailType);
                if (date) params.append('date', date);
            }
            
            return params.toString();
        }
        
        function renderLogTable(type, logs) {
            const tableBody = document.getElementById(`${type}LogTableBody`);
            
            if (!logs || logs.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center">Keine Einträge gefunden</td></tr>';
                return;
            }
            
            tableBody.innerHTML = '';
            
            logs.forEach(log => {
                const row = document.createElement('tr');
                
                if (type === 'activity') {
                    row.innerHTML = `
                        <td>${formatDateTime(log.timestamp)}</td>
                        <td>${escapeHtml(log.username || 'System')}</td>
                        <td><span class="action-badge ${log.action}">${escapeHtml(log.action)}</span></td>
                        <td>${escapeHtml(log.details || '-')}</td>
                        <td>${escapeHtml(log.ip_address || '-')}</td>
                    `;
                } else if (type === 'sessions') {
                    const statusClass = log.status === 'active' ? 'active' : 'inactive';
                    const isAdmin = window.adminData?.currentUser?.role === 'Admin';
                    const terminateButton = (log.status === 'active' && isAdmin) 
                        ? `<button class="btn btn-sm btn-danger" onclick="terminateSession('${log.session_id}')" title="Nur Administratoren können Sessions beenden">Beenden</button>` 
                        : '-';
                    
                    row.innerHTML = `
                        <td>${formatDateTime(log.created_at)}</td>
                        <td>${escapeHtml(log.username)}</td>
                        <td>${escapeHtml(log.ip_address || '-')}</td>
                        <td>${formatDateTime(log.last_activity)}</td>
                        <td><span class="status-badge ${statusClass}">${escapeHtml(log.status)}</span></td>
                        <td>${terminateButton}</td>
                    `;
                } else if (type === 'email') {
                    // Map status to CSS class
                    let statusClass = 'pending';
                    if (log.status === 'sent') {
                        statusClass = 'success';
                    } else if (log.status === 'failed') {
                        statusClass = 'error';
                    } else if (log.status === 'simulated_localhost') {
                        statusClass = 'simulated_localhost';
                    }
                    
                    // Translate status and type to German
                    const statusText = translateEmailStatus(log.status);
                    const typeText = translateEmailType(log.type);
                    
                    row.innerHTML = `
                        <td>${formatDateTime(log.timestamp)}</td>
                        <td>${escapeHtml(log.recipient)}</td>
                        <td>${escapeHtml(log.subject)}</td>
                        <td>${escapeHtml(typeText)}</td>
                        <td><span class="status-badge ${statusClass}">${escapeHtml(statusText)}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="viewEmailLog(${log.id})">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </td>
                    `;
                }
                
                tableBody.appendChild(row);
            });
        }
        
        function renderPagination(type, pagination) {
            const paginationContainer = document.getElementById(`${type}LogPagination`);
            if (!paginationContainer || !pagination) return;
            
            const { current_page, total_pages, total_records } = pagination;
            
            let html = `<div class="pagination-info">Zeige Seite ${current_page} von ${total_pages} (${total_records} Einträge)</div>`;
            html += '<div class="pagination-buttons">';
            
            if (current_page > 1) {
                html += `<button class="btn btn-sm btn-outline" onclick="changePage(${current_page - 1})"><i class="fas fa-chevron-left"></i> Zurück</button>`;
            }
            
            // Page numbers
            const maxButtons = 5;
            let startPage = Math.max(1, current_page - Math.floor(maxButtons / 2));
            let endPage = Math.min(total_pages, startPage + maxButtons - 1);
            
            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === current_page ? 'btn-primary' : 'btn-outline';
                html += `<button class="btn btn-sm ${activeClass}" onclick="changePage(${i})">${i}</button>`;
            }
            
            if (current_page < total_pages) {
                html += `<button class="btn btn-sm btn-outline" onclick="changePage(${current_page + 1})">Weiter <i class="fas fa-chevron-right"></i></button>`;
            }
            
            html += '</div>';
            paginationContainer.innerHTML = html;
        }
        
        function changePage(page) {
            currentLogPage = page;
            loadLogs(currentLogType);
        }
        
        function sortActivityLog(field) {
            sortLog('activity', field);
        }
        
        function sortSessionsLog(field) {
            sortLog('sessions', field);
        }
        
        function sortEmailLog(field) {
            sortLog('email', field);
        }
        
        function sortLog(type, field) {
            if (currentLogSort.field === field) {
                currentLogSort.direction = currentLogSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentLogSort.field = field;
                currentLogSort.direction = 'desc';
            }
            loadLogs(type);
        }
        
        function searchActivityLog() {
            currentLogPage = 1;
            loadLogs('activity');
        }
        
        function filterActivityLog() {
            currentLogPage = 1;
            loadLogs('activity');
        }
        
        function searchSessionsLog() {
            currentLogPage = 1;
            loadLogs('sessions');
        }
        
        function filterSessionsLog() {
            currentLogPage = 1;
            loadLogs('sessions');
        }
        
        function searchEmailLog() {
            currentLogPage = 1;
            loadLogs('email');
        }
        
        function filterEmailLog() {
            currentLogPage = 1;
            loadLogs('email');
        }
        
        function refreshLogs() {
            loadLogs(currentLogType);
            showToast('Logs aktualisiert', 'success');
        }
        
        function exportLogs() {
            const filters = getLogFilters(currentLogType);
            window.open(`/api/admin.php?action=export-${currentLogType}-logs&${filters}`, '_blank');
        }
        
        function terminateSession(sessionId) {
            if (!confirm('Möchten Sie diese Sitzung wirklich beenden?')) return;
            
            // Get CSRF token from sessionStorage or admin object
            const csrfToken = sessionStorage.getItem('csrf_token') || (window.admin && window.admin.csrfToken) || '';
            
            console.log('🔍 Terminating session:', sessionId);
            console.log('🔍 CSRF Token:', csrfToken ? 'Present' : 'Missing');
            
            fetch('/api/admin.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    action: 'terminate-session',
                    session_id: sessionId,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                console.log('🔍 Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('🔍 Response data:', data);
                if (data.success) {
                    showToast('Sitzung erfolgreich beendet', 'success');
                    loadLogs('sessions');
                } else {
                    const errorMsg = data.error || data.message || 'Fehler beim Beenden der Sitzung';
                    console.error('❌ Error:', errorMsg);
                    showToast(errorMsg, 'error');
                }
            })
            .catch(error => {
                console.error('❌ Network error:', error);
                showToast('Netzwerkfehler: ' + error.message, 'error');
            });
        }
        
        function viewEmailLog(logId) {
            // TODO: Implement email log detail view
            showToast('E-Mail-Details-Ansicht wird noch implementiert', 'info');
        }
        
        function formatDateTime(datetime) {
            if (!datetime) return '-';
            const date = new Date(datetime);
            return date.toLocaleString('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function translateEmailStatus(status) {
            const statusMap = {
                'sent': 'Gesendet',
                'failed': 'Fehlgeschlagen',
                'simulated_localhost': 'Simuliert (Localhost)',
                'pending': 'Ausstehend'
            };
            return statusMap[status] || status;
        }
        
        function translateEmailType(templateKey) {
            const typeMap = {
                'auto_receipt_confirmation': 'Empfangsbestätigung',
                'team_new_request_notification': 'Team-Benachrichtigung',
                'quote_delivery': 'Angebot',
                'request_confirmation': 'Anfrage-Bestätigung',
                'request_declined': 'Anfrage abgelehnt',
                'site_visit_request': 'Besichtigungstermin',
                'completion_invoice': 'Abschlussrechnung'
            };
            return typeMap[templateKey] || templateKey;
        }
        
        function loadUsersList() {
            console.log('Loading users list for filter dropdown');
            
            fetch('/api/admin.php?action=get-users-list')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.users) {
                        console.log('Users loaded:', data.users.length);
                        
                        // Populate activity log user filter
                        const activityUserFilter = document.getElementById('activityUserFilter');
                        if (activityUserFilter) {
                            // Keep the "Alle Benutzer" option
                            activityUserFilter.innerHTML = '<option value="">Alle Benutzer</option>';
                            data.users.forEach(user => {
                                const option = document.createElement('option');
                                option.value = user.id;
                                option.textContent = `${user.username} (${user.role})`;
                                activityUserFilter.appendChild(option);
                            });
                        }
                        
                        // Populate sessions log user filter
                        const sessionsUserFilter = document.getElementById('sessionsUserFilter');
                        if (sessionsUserFilter) {
                            sessionsUserFilter.innerHTML = '<option value="">Alle Benutzer</option>';
                            data.users.forEach(user => {
                                const option = document.createElement('option');
                                option.value = user.id;
                                option.textContent = `${user.username} (${user.role})`;
                                sessionsUserFilter.appendChild(option);
                            });
                        }
                    } else {
                        console.error('Failed to load users:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                });
        }
        
        // Load logs when logs section is opened
        document.addEventListener('DOMContentLoaded', function() {
            const logsSection = document.getElementById('logs-section');
            if (logsSection) {
                console.log('Logs section found, setting up observer');
                
                // Load activity logs when section becomes active
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.target.classList.contains('active')) {
                            console.log('Logs section activated, loading activity logs and users');
                            loadUsersList(); // Load users for filter dropdown
                            loadLogs('activity');
                            observer.disconnect();
                        }
                    });
                });
                observer.observe(logsSection, { attributes: true, attributeFilter: ['class'] });
                
                // If section is already active, load immediately
                if (logsSection.classList.contains('active')) {
                    console.log('Logs section already active, loading immediately');
                    loadUsersList(); // Load users for filter dropdown
                    loadLogs('activity');
                }
            } else {
                console.error('Logs section not found in DOM');
            }
        });
        
        // Logout Handler
        function handleLogout(event) {
            event.preventDefault();
            
            // Show confirmation dialog
            if (confirm('Möchten Sie sich wirklich abmelden?')) {
                // Call logout API
                fetch('/api/auth.php?action=logout', {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    // Redirect to login page regardless of API response
                    window.location.href = '/login';
                })
                .catch(error => {
                    console.error('Logout error:', error);
                    // Still redirect to login even if API call fails
                    window.location.href = '/login';
                });
            }
        }
        
        // Users Management Functions
        function refreshUsers() {
            location.reload();
        }
        
        function filterUsers() {
            const searchTerm = document.getElementById('usersSearch').value.toLowerCase();
            const tableRows = document.querySelectorAll('#usersTableBody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const shouldShow = text.includes(searchTerm);
                row.style.display = shouldShow ? '' : 'none';
            });
        }
        
        function openAddUserModal() {
            resetUserForm();
            document.getElementById('userModalTitle').textContent = 'Neuer Benutzer';
            document.getElementById('passwordSection').style.display = 'block';
            document.getElementById('userPassword').required = true;
            openModal('userModal');
        }
        
        function editUser(userId) {
            // Get user data from table row
            const row = document.querySelector(`tr[data-user-id="${userId}"]`);
            if (!row) return;
            
            // Extract data from table cells
            const cells = row.querySelectorAll('td');
            const username = cells[0].querySelector('strong').textContent;
            const email = cells[1].textContent;
            const fullName = cells[2].textContent;
            const role = cells[3].querySelector('.role-badge').textContent;
            const isActive = cells[4].querySelector('.status-badge').classList.contains('active') ? '1' : '0';
            
            // Check if trying to edit Admin account when not Admin
            const currentUserRole = window.adminData?.currentUser?.role;
            if (role === 'Admin' && currentUserRole !== 'Admin') {
                showToast('Nur Admins können Admin-Accounts bearbeiten', 'error');
                return;
            }
            
            // Populate form
            document.getElementById('userId').value = userId;
            document.getElementById('userUsername').value = username;
            document.getElementById('userEmail').value = email;
            document.getElementById('userRole').value = role;
            document.getElementById('userActive').value = isActive;
            
            // Try to split full name (basic approach)
            const nameParts = fullName.split(' ');
            if (nameParts.length >= 2 && fullName !== 'Nicht angegeben') {
                document.getElementById('userFirstName').value = nameParts[0];
                document.getElementById('userLastName').value = nameParts.slice(1).join(' ');
            } else {
                document.getElementById('userFirstName').value = '';
                document.getElementById('userLastName').value = '';
            }
            
            document.getElementById('userModalTitle').textContent = 'Benutzer bearbeiten';
            document.getElementById('passwordSection').style.display = 'block';
            document.getElementById('userPassword').required = false;
            
            openModal('userModal');
        }
        
        function resetUserForm() {
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('passwordSection').style.display = 'none';
            document.getElementById('userPassword').required = false;
        }
        
        function saveUser() {
            const form = document.getElementById('userForm');
            const formData = new FormData(form);
            const userId = document.getElementById('userId').value;
            
            // Add action to form data
            formData.append('action', userId ? 'update-user' : 'create-user');
            
            // Validate required fields
            const username = formData.get('username');
            const email = formData.get('email');
            const role = formData.get('role');
            
            if (!username || !email || !role) {
                showToast('Bitte füllen Sie alle Pflichtfelder aus', 'error');
                return;
            }
            
            // Validate password for new users
            if (!userId && !formData.get('password')) {
                showToast('Passwort ist für neue Benutzer erforderlich', 'error');
                return;
            }
            
            // ✅ Add CSRF token
            formData.append('csrf_token', admin.csrfToken || '');
            
            // Send to API
            fetch('/api/admin.php', {
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
                    showToast(data.message || 'Benutzer erfolgreich gespeichert', 'success');
                    closeModal('userModal');
                    // Refresh the page to show updated data
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Fehler beim Speichern des Benutzers', 'error');
                }
            })
            .catch(error => {
                console.error('Error saving user:', error);
                showToast('Fehler beim Speichern des Benutzers', 'error');
            });
        }
        
        function resetUserPassword(userId) {
            // Check if trying to reset Admin password when not Admin  
            if (!canManageAdminAccounts(userId)) {
                showToast('Nur Admins können Admin-Passwörter zurücksetzen', 'error');
                return;
            }
            
            if (confirm('Möchten Sie das Passwort dieses Benutzers wirklich zurücksetzen?\\n\\nEin neues temporäres Passwort wird generiert.')) {
                const formData = new FormData();
                formData.append('action', 'reset-user-password');
                formData.append('userId', userId);
                formData.append('csrf_token', admin.csrfToken || '');
                
                fetch('/api/admin.php', {
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
                        showToast(`Neues Passwort: ${data.newPassword}`, 'success');
                        // You might want to show this in a more secure way
                        alert(`Neues temporäres Passwort für den Benutzer:\\n\\n${data.newPassword}\\n\\nBitte geben Sie dieses dem Benutzer sicher weiter.`);
                    } else {
                        showToast(data.message || 'Fehler beim Zurücksetzen des Passworts', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error resetting password:', error);
                    showToast('Fehler beim Zurücksetzen des Passworts', 'error');
                });
            }
        }
        
        function toggleUserStatus(userId, currentStatus) {
            // Check if trying to modify Admin status when not Admin  
            if (!canManageAdminAccounts(userId)) {
                showToast('Nur Admins können Admin-Accounts deaktivieren', 'error');
                return;
            }
            
            const newStatus = currentStatus ? 0 : 1;
            const statusText = newStatus ? 'aktivieren' : 'deaktivieren';
            
            if (confirm(`Möchten Sie diesen Benutzer wirklich ${statusText}?`)) {
                const formData = new FormData();
                formData.append('action', 'toggle-user-status');
                formData.append('userId', userId);
                formData.append('status', newStatus);
                formData.append('csrf_token', admin.csrfToken || '');
                
                fetch('/api/admin.php', {
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
                        showToast(data.message || 'Benutzerstatus erfolgreich geändert', 'success');
                        // Refresh the page to show updated data
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Fehler beim Ändern des Benutzerstatus', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error toggling user status:', error);
                    showToast('Fehler beim Ändern des Benutzerstatus', 'error');
                });
            }
        }
        
        // User Dropdown Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const userMenuToggle = document.getElementById('userMenuToggle');
            const userDropdown = userMenuToggle?.closest('.user-dropdown');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            
            if (userMenuToggle && userDropdown) {
                // Toggle dropdown on click
                userMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!userDropdown.contains(e.target)) {
                        userDropdown.classList.remove('active');
                    }
                });
                
                // Handle dropdown item clicks
                const dropdownItems = userDropdownMenu?.querySelectorAll('.dropdown-item');
                dropdownItems?.forEach(item => {
                    item.addEventListener('click', function(e) {
                        const href = this.getAttribute('href');
                        
                        // Handle different dropdown actions
                        if (href === '#profile') {
                            e.preventDefault();
                            // TODO: Implement profile view
                            showToast('Profil-Ansicht wird noch implementiert', 'info');
                        } else if (href === '#settings') {
                            e.preventDefault();
                            // Navigate to settings section
                            if (window.showSection) {
                                window.showSection('settings');
                            }
                            userDropdown.classList.remove('active');
                        } else if (href === '#logout') {
                            // Logout is handled by the onclick handler
                            return;
                        }
                    });
                });
            }
        });
        
        // Pricing Management Functions
        let pricingCounter = 0;
        
        function addPriceItem() {
            const container = document.getElementById('pricingItems');
            const itemId = 'price_' + (++pricingCounter);
            
            const itemHtml = `
                <div class="pricing-item" id="${itemId}">
                    <div class="pricing-item-header">
                        <strong>Preis ${pricingCounter}</strong>
                        <button type="button" class="pricing-item-remove" onclick="removePriceItem('${itemId}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="pricing-item-controls">
                        <div class="form-group">
                            <label>Beschreibung</label>
                            <input type="text" class="form-control price-description" placeholder="z.B. Standardpreis, Komfortpaket...">
                        </div>
                        <div class="form-group">
                            <label>Preis (€)</label>
                            <input type="number" step="0.01" min="0" class="form-control price-amount" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Einheit</label>
                            <input type="text" class="form-control price-unit" placeholder="pauschal, pro Stunde...">
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', itemHtml);
            updatePricingData();
        }
        
        function removePriceItem(itemId) {
            const item = document.getElementById(itemId);
            if (item) {
                item.remove();
                updatePricingData();
            }
        }
        
        function updatePricingData() {
            const items = document.querySelectorAll('.pricing-item');
            const pricingData = [];
            
            items.forEach(item => {
                const description = item.querySelector('.price-description').value;
                const amount = item.querySelector('.price-amount').value;
                const unit = item.querySelector('.price-unit').value;
                
                if (description || amount || unit) {
                    pricingData.push({
                        description: description,
                        amount: parseFloat(amount) || 0,
                        unit: unit
                    });
                }
            });
            
            document.getElementById('pricingData').value = JSON.stringify(pricingData);
        }
        
        function loadPricingData(pricingDataJson) {
            const container = document.getElementById('pricingItems');
            container.innerHTML = '';
            pricingCounter = 0;
            
            if (pricingDataJson) {
                try {
                    const pricingData = JSON.parse(pricingDataJson);
                    pricingData.forEach(item => {
                        addPriceItem();
                        const lastItem = container.lastElementChild;
                        lastItem.querySelector('.price-description').value = item.description || '';
                        lastItem.querySelector('.price-amount').value = item.amount || '';
                        lastItem.querySelector('.price-unit').value = item.unit || '';
                    });
                } catch (e) {
                    console.error('Error parsing pricing data:', e);
                }
            }
        }
        
        // Add event listeners for real-time updates
        document.addEventListener('change', function(e) {
            if (e.target.closest('.pricing-item')) {
                updatePricingData();
            }
        });
        
        document.addEventListener('keyup', function(e) {
            if (e.target.closest('.pricing-item')) {
                updatePricingData();
            }
        });
        
        // Service Content Management Functions
        let featureCounter = 0;
        let processCounter = 0;
        
        function loadServicePageContent() {
            const serviceSelect = document.getElementById('servicePageSelect');
            const serviceSlug = serviceSelect.value;
            
            if (!serviceSlug) {
                // If no service selected, hide both forms and clear URL query
                document.getElementById('servicePageContent').style.display = 'block';
                document.getElementById('serviceContentForm').style.display = 'none';
                
                // Update URL to remove service query parameter
                if (window.updateUrlHash) {
                    window.updateUrlHash('service-pages');
                } else {
                    // Fallback: update hash without query parameters
                    if (window.location.hash.includes('service-pages')) {
                        window.location.hash = '#service-pages';
                    }
                }
                return;
            }
            
            // Get service ID from selected option
            const selectedOption = serviceSelect.selectedOptions[0];
            const serviceId = selectedOption ? selectedOption.dataset.serviceId : null;
            // Update URL with service parameter
            if (window.updateUrlHash && serviceId) {
                window.updateUrlHash('service-pages', { service_id: serviceId });
            } else if (serviceId) {
                // Fallback: manually update hash
                window.location.hash = `#service-pages?service_id=${serviceId}`;
            }
            
            // Show content form and load data
            document.getElementById('servicePageContent').style.display = 'none';
            document.getElementById('serviceContentForm').style.display = 'block';
            
            // Load service data via AJAX
            fetch(`/api/admin.php?action=service-page-content&slug=${serviceSlug}`)
                .then(response => {
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        populateServiceForm(data.service, data.content);
                    } else {
                        console.error('API Error:', data.error || 'Unknown error');
                        alert('Fehler beim Laden der Service-Daten: ' + (data.error || 'Unbekannter Fehler'));
                    }
                })
                .catch(error => {
                    console.error('Error loading service content:', error);
                    alert('Fehler beim Laden der Service-Inhalte.');
                });
        }

        // Initialize page - removed auto-loading to show empty state by default
        document.addEventListener('DOMContentLoaded', function() {
        });
        
        function populateServiceForm(service, content) {
            // Set service data
            document.getElementById('servicePageId').value = service.id;
            document.getElementById('servicePageSlug').value = service.slug;
            
            // Populate form fields
            document.getElementById('metaTitle').value = content?.meta_title || service.title || '';
            document.getElementById('metaDescription').value = content?.meta_description || service.description || '';
            document.getElementById('metaKeywords').value = content?.meta_keywords || '';
            
            document.getElementById('heroTitle').value = content?.hero_title || service.title || '';
            document.getElementById('heroSubtitle').value = content?.hero_subtitle || service.description || '';
            
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
            
            // Load features
            loadFeatures(content?.features_content);
            
            // Load process steps
            loadProcessSteps(content?.process_content);
        }
        
        function addFeature() {
            const container = document.getElementById('featuresContainer');
            const featureId = 'feature_' + (++featureCounter);
            
            const featureHtml = `
                <div class="feature-item" id="${featureId}">
                    <button type="button" class="remove-btn" onclick="removeFeature('${featureId}')">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Icon/Emoji</label>
                            <input type="text" class="form-control feature-icon" placeholder="📦" maxlength="10">
                        </div>
                        <div class="form-group">
                            <label>Titel</label>
                            <input type="text" class="form-control feature-title" placeholder="Feature-Titel">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Beschreibung</label>
                        <textarea class="form-control feature-description" rows="2" placeholder="Beschreibung des Features..."></textarea>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', featureHtml);
            updateFeaturesData(); // Update data after adding
        }
        
        function removeFeature(featureId) {
            document.getElementById(featureId).remove();
            updateFeaturesData(); // Update data after removal
        }
        
        function addProcessStep() {
            const container = document.getElementById('processContainer');
            const stepId = 'process_' + (++processCounter);
            
            const stepHtml = `
                <div class="process-item" id="${stepId}">
                    <button type="button" class="remove-btn" onclick="removeProcessStep('${stepId}')">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Titel</label>
                            <input type="text" class="form-control process-title" placeholder="Schritt-Titel">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Beschreibung</label>
                        <textarea class="form-control process-description" rows="2" placeholder="Beschreibung des Schritts..."></textarea>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', stepHtml);
            updateProcessData(); // Update data after adding
        }
        
        function removeProcessStep(stepId) {
            document.getElementById(stepId).remove();
            updateProcessData(); // Update data after removal
        }
        
        function loadFeatures(featuresContent) {
            const container = document.getElementById('featuresContainer');
            container.innerHTML = '';
            featureCounter = 0;
            
            if (featuresContent) {
                try {
                    const features = JSON.parse(featuresContent);
                    features.forEach(feature => {
                        addFeature();
                        const lastFeature = container.lastElementChild;
                        lastFeature.querySelector('.feature-icon').value = feature.icon || '';
                        lastFeature.querySelector('.feature-title').value = feature.title || '';
                        lastFeature.querySelector('.feature-description').value = feature.description || '';
                    });
                } catch (e) {
                    console.error('Error parsing features:', e);
                }
            }
            
            // Add one empty feature if none exist
            if (container.children.length === 0) {
                addFeature();
            }
        }
        
        function loadProcessSteps(processContent) {
            const container = document.getElementById('processContainer');
            container.innerHTML = '';
            processCounter = 0;
            
            if (processContent) {
                try {
                    const steps = JSON.parse(processContent);
                    steps.forEach(step => {
                        addProcessStep();
                        const lastStep = container.lastElementChild;
                        lastStep.querySelector('.process-title').value = step.title || '';
                        lastStep.querySelector('.process-description').value = step.description || '';
                    });
                } catch (e) {
                    console.error('Error parsing process steps:', e);
                }
            }
            
            // Add default process steps if none exist
            if (container.children.length === 0) {
                const defaultSteps = [
                    { title: 'Beratung', description: 'Kostenlose Erstberatung und Bedarfsanalyse' },
                    { title: 'Planung', description: 'Detaillierte Planung des Vorgehens' },
                    { title: 'Durchführung', description: 'Professionelle Umsetzung durch unser Team' },
                    { title: 'Abschluss', description: 'Finale Kontrolle und Übergabe' }
                ];
                
                defaultSteps.forEach(step => {
                    addProcessStep();
                    const lastStep = container.lastElementChild;
                    lastStep.querySelector('.process-title').value = step.title;
                    lastStep.querySelector('.process-description').value = step.description;
                });
            }
        }
        
        function resetServicePageForm() {
            document.getElementById('servicePageContent').style.display = 'block';
            document.getElementById('serviceContentForm').style.display = 'none';
            document.getElementById('servicePageSelect').value = '';
            
            // Clear features and process containers
            document.getElementById('featuresContainer').innerHTML = '';
            document.getElementById('processContainer').innerHTML = '';
            featureCounter = 0;
            processCounter = 0;
        }
        
        // Update features data in real-time
        function updateFeaturesData() {
            const features = [];
            document.querySelectorAll('.feature-item').forEach(item => {
                const icon = item.querySelector('.feature-icon').value;
                const title = item.querySelector('.feature-title').value;
                const description = item.querySelector('.feature-description').value;
                
                if (title.trim()) {
                    features.push({ icon: icon.trim(), title: title.trim(), description: description.trim() });
                }
            });
            document.getElementById('featuresContent').value = JSON.stringify(features);
        }
        
        // Update process steps data in real-time
        function updateProcessData() {
            const processSteps = [];
            document.querySelectorAll('.process-item').forEach(item => {
                const title = item.querySelector('.process-title').value;
                const description = item.querySelector('.process-description').value;
                
                if (title.trim()) {
                    processSteps.push({ title: title.trim(), description: description.trim() });
                }
            });
            document.getElementById('processContent').value = JSON.stringify(processSteps);
        }
        
        // Add event listeners for real-time updates
        document.addEventListener('change', function(e) {
            if (e.target.closest('.feature-item')) {
                updateFeaturesData();
            }
            if (e.target.closest('.process-item')) {
                updateProcessData();
            }
        });
        
        document.addEventListener('keyup', function(e) {
            if (e.target.closest('.feature-item')) {
                updateFeaturesData();
            }
            if (e.target.closest('.process-item')) {
                updateProcessData();
            }
        });
        
        // Handle service page form submission
        document.getElementById('servicePageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Collect features data
            const features = [];
            document.querySelectorAll('.feature-item').forEach(item => {
                const icon = item.querySelector('.feature-icon').value;
                const title = item.querySelector('.feature-title').value;
                const description = item.querySelector('.feature-description').value;
                
                if (title && description) {
                    features.push({ icon, title, description });
                }
            });
            
            // Collect process steps data
            const processSteps = [];
            document.querySelectorAll('.process-item').forEach(item => {
                const title = item.querySelector('.process-title').value;
                const description = item.querySelector('.process-description').value;
                
                if (title && description) {
                    processSteps.push({ title, description });
                }
            });
            
            // Prepare form data
            const formData = new FormData(this);
            formData.append('action', 'save-service-page-content');
            formData.append('features_content', JSON.stringify(features));
            formData.append('process_content', JSON.stringify(processSteps));
            formData.append('csrf_token', admin.csrfToken || '');
            
            // Submit to admin API
            fetch('/api/admin.php', {
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
                    alert('Seiteninhalte erfolgreich gespeichert!');
                } else {
                    alert('Fehler beim Speichern: ' + (data.message || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error saving service content:', error);
                alert('Fehler beim Speichern der Seiteninhalte.');
            });
        });
        
        // Simple toast function for inline JavaScript
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if (!container) {
                // Fallback to alert if no toast container
                alert(message);
                return;
            }
            
            const toast = document.createElement('div');
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            toast.className = `toast toast-${type}`;
            toast.style.zIndex = '10000'; // Ensure high z-index
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="${icons[type]}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            // Force layout and show animation
            toast.offsetHeight; // Force reflow
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
            
            // Create a protected timer that survives DOM changes
            const toastId = Date.now() + Math.random();
            window.activeToasts = window.activeToasts || {};
            window.activeToasts[toastId] = {
                element: toast,
                timer: setTimeout(() => {
                    if (toast && toast.parentElement) {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(100%)';
                        setTimeout(() => {
                            if (toast && toast.parentElement) {
                                toast.remove();
                            }
                            delete window.activeToasts[toastId];
                        }, 300); // Animation time
                    }
                }, 10000) // Show for 10 seconds
            };
        }
        
        // Settings Management Functions
        function loadSettings() {
            fetch('/api/admin.php?action=settings')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Store admin status globally
                        if (!window.adminData) {
                            window.adminData = {};
                        }
                        window.adminData.isAdmin = data.isAdmin || false;
                        window.adminData.userRole = data.userRole || '';
                        displaySettings(data.settings);
                    } else {
                        console.error('Error loading settings:', data.message);
                        showToast('Fehler beim Laden der Einstellungen', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading settings:', error);
                    showToast('Fehler beim Laden der Einstellungen', 'error');
                });
        }

        function displaySettings(settings) {
            const settingsContainer = document.getElementById('settings-container');
            
            // Clear existing content
            if (settingsContainer) {
                settingsContainer.innerHTML = '';
            }
            
            // Store all settings globally for filtering
            window.allSettings = settings;
            
            // Display all settings
            settings.forEach(setting => {
                const settingHtml = createSettingElement(setting);
                settingsContainer.appendChild(settingHtml);
            });
        }

        function createSettingElement(setting) {
            const div = document.createElement('div');
            div.className = 'setting-item' + (setting.is_public == 0 ? ' admin-only-setting' : '');
            div.setAttribute('data-key', (setting.key || '').toLowerCase());
            div.setAttribute('data-type', setting.type || '');
            div.setAttribute('data-description', (setting.description || '').toLowerCase());
            div.setAttribute('data-value', (setting.value || '').toString().toLowerCase());
            
            const displayValue = formatSettingValue(setting.value, setting.type);
            
            // Determine category for display
            const category = setting.key && setting.key.startsWith('contact_') ? 'Kontakt' :
                           setting.key && setting.key.startsWith('social_') ? 'Social Media' :
                           setting.key && setting.key.startsWith('seo_') ? 'SEO' :
                           setting.key && setting.key.startsWith('office_') ? 'Steuer & Office' :
                           setting.key && (setting.key.startsWith('system_') || setting.key.startsWith('google_') || setting.key.startsWith('maintenance_') || setting.key.startsWith('admin_')) ? 'System' :
                           'Allgemein';
            
            const adminOnlyBadge = setting.is_public == 0 ? 
                '<span class="setting-admin-badge">🔒 Admin-Only</span>' : 
                '<span class="setting-public-badge">👁️ Öffentlich</span>';
            
            div.innerHTML = `
                <div class="setting-content">
                    <div class="setting-info">
                        <div class="setting-header">
                            <strong>${setting.key || 'Unbekannt'}</strong>
                            <span class="setting-category-badge">${category}</span>
                            ${adminOnlyBadge}
                        </div>
                        <div class="setting-value">${displayValue}</div>
                        ${setting.description ? `<small class="text-muted">${setting.description}</small>` : ''}
                    </div>
                    <div class="setting-actions">
                        <span class="setting-type-badge">${setting.type || 'unknown'}</span>
                        <button class="btn btn-outline btn-sm" onclick="editSetting(${setting.id || 0})">
                            <i class="fas fa-edit"></i> Bearbeiten
                        </button>
                    </div>
                </div>
            `;
            
            return div;
        }

        function formatSettingValue(value, type) {
            switch (type) {
                case 'bool':
                    return value === '1' || value === 'true' ? 'Ja' : 'Nein';
                case 'json':
                    try {
                        const jsonObj = JSON.parse(value);
                        const keys = Object.keys(jsonObj);
                        if (keys.length === 0) {
                            return '<em>Leer</em>';
                        } else {
                            // Create a formatted display of all key-value pairs
                            const keyValuePairs = keys.map(key => {
                                const displayValue = String(jsonObj[key]).length > 20 
                                    ? String(jsonObj[key]).substring(0, 20) + '...' 
                                    : jsonObj[key];
                                return `<span class="json-pair"><strong>${key}:</strong> ${displayValue}</span>`;
                            });
                            
                            if (keys.length <= 4) {
                                // Show all pairs for small objects
                                return keyValuePairs.join('<br>');
                            } else {
                                // Show first 3 pairs + count for large objects
                                return keyValuePairs.slice(0, 3).join('<br>') + 
                                       `<br><small class="text-muted">+${keys.length - 3} weitere...</small>`;
                            }
                        }
                    } catch (e) {
                        return `<span class="text-danger">Ungültiges JSON</span>`;
                    }
                default:
                    return value || '-';
            }
        }

        function editSetting(settingId) {
            if (!settingId) {
                // Create new setting
                resetSettingForm();
                document.getElementById('settingModalTitle').textContent = 'Neue Einstellung erstellen';
                openModal('settingModal');
                return;
            }
            
            // Load existing setting
            fetch(`/api/admin.php?action=get-setting&id=${settingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateSettingForm(data.setting);
                        document.getElementById('settingModalTitle').textContent = 'Einstellung bearbeiten';
                        // Make key field read-only for existing settings (instead of disabled)
                        document.getElementById('settingKey').readOnly = true;
                        openModal('settingModal');
                    } else {
                        showToast('Fehler beim Laden der Einstellung', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading setting:', error);
                    showToast('Fehler beim Laden der Einstellung', 'error');
                });
        }

        function populateSettingForm(setting) {
            document.getElementById('settingId').value = setting.id || '';
            document.getElementById('settingKey').value = setting.key || '';
            document.getElementById('settingType').value = setting.type || 'string';
            document.getElementById('settingPublic').value = setting.is_public || '0';
            document.getElementById('settingDescription').value = setting.description || '';
            
            // Update value field based on type
            updateSettingValueField();
            
            // Set the actual value based on type
            if (setting.type === 'json') {
                // For JSON type, we need to populate both visual and raw editors
                setTimeout(() => {
                    const rawTextarea = document.querySelector('#jsonRawEditor textarea');
                    if (rawTextarea) {
                        rawTextarea.value = setting.value || '{}';
                    }
                    
                    // Generate visual editor with the JSON value
                    if (setting.value) {
                        generateJsonEditor(setting.value);
                    }
                }, 50);
            } else {
                // For non-JSON types
                setTimeout(() => {
                    const valueInput = document.querySelector('#settingValueContainer input, #settingValueContainer textarea, #settingValueContainer select');
                    if (valueInput) {
                        valueInput.value = setting.value || '';
                    }
                }, 50);
            }
        }

        function resetSettingForm() {
            document.getElementById('settingForm').reset();
            document.getElementById('settingId').value = '';
            document.getElementById('settingKey').readOnly = false; // Enable key field for new settings
            updateSettingValueField();
        }

        function updateSettingValueField() {
            const type = document.getElementById('settingType').value;
            const container = document.getElementById('settingValueContainer');
            const currentValue = container.querySelector('input, textarea, select')?.value || '';
            
            let html = '';
            switch (type) {
                case 'bool':
                    html = `<select class="form-control" name="value">
                        <option value="0" ${currentValue === '0' || currentValue === 'false' ? 'selected' : ''}>Nein</option>
                        <option value="1" ${currentValue === '1' || currentValue === 'true' ? 'selected' : ''}>Ja</option>
                    </select>`;
                    break;
                case 'json':
                    html = `
                        <div class="json-editor">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="jsonEditMode" id="jsonEditModeVisual" value="visual" checked>
                                <label class="form-check-label" for="jsonEditModeVisual">Visueller Editor</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="jsonEditMode" id="jsonEditModeRaw" value="raw">
                                <label class="form-check-label" for="jsonEditModeRaw">JSON Text</label>
                            </div>
                            
                            <div id="jsonVisualEditor" class="json-visual-editor">
                                <!-- Will be populated by generateJsonEditor() -->
                            </div>
                            
                            <div id="jsonRawEditor" class="json-raw-editor" style="display: none;">
                                <textarea class="form-control" name="value" rows="6" placeholder='{"key": "value"}'>${currentValue}</textarea>
                                <small class="form-text text-muted">Gültiges JSON Format erforderlich</small>
                            </div>
                        </div>`;
                    break;
                case 'int':
                    html = `<input type="number" class="form-control" name="value" value="${currentValue}">`;
                    break;
                default: // string
                    html = `<input type="text" class="form-control" name="value" value="${currentValue}">`;
                    break;
            }
            
            container.innerHTML = html;
            
            // If JSON type, setup the editor
            if (type === 'json') {
                setupJsonEditor(currentValue);
            }
        }

        function setupJsonEditor(jsonValue) {
            // Setup mode switching
            document.querySelectorAll('input[name="jsonEditMode"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    toggleJsonEditMode(this.value);
                });
            });
            
            // Generate visual editor
            generateJsonEditor(jsonValue);
        }

        function toggleJsonEditMode(mode) {
            const visualEditor = document.getElementById('jsonVisualEditor');
            const rawEditor = document.getElementById('jsonRawEditor');
            
            if (mode === 'visual') {
                visualEditor.style.display = 'block';
                rawEditor.style.display = 'none';
                
                // Sync from raw to visual
                const rawTextarea = rawEditor.querySelector('textarea');
                if (rawTextarea && rawTextarea.value.trim()) {
                    try {
                        // Validate JSON before generating visual editor
                        JSON.parse(rawTextarea.value);
                        generateJsonEditor(rawTextarea.value);
                    } catch (e) {
                        console.warn('Invalid JSON in raw editor, keeping visual editor as is');
                    }
                }
            } else {
                visualEditor.style.display = 'none';
                rawEditor.style.display = 'block';
                
                // Sync from visual to raw
                const jsonData = collectJsonFromVisualEditor();
                const rawTextarea = rawEditor.querySelector('textarea');
                if (rawTextarea) {
                    rawTextarea.value = JSON.stringify(jsonData, null, 2);
                }
            }
        }

        function generateJsonEditor(jsonValue) {
            const container = document.getElementById('jsonVisualEditor');
            
            let jsonData = {};
            if (jsonValue) {
                try {
                    jsonData = JSON.parse(jsonValue);
                } catch (e) {
                    console.error('Invalid JSON:', e);
                    container.innerHTML = '<div class="alert alert-warning">Ungültiges JSON - verwende den Text-Editor</div>';
                    return;
                }
            }
            
            let html = '<div class="json-fields">';
            
            // Generate input fields for each JSON property
            Object.keys(jsonData).forEach((key, index) => {
                const value = jsonData[key];
                html += `
                    <div class="json-field-row mb-2">
                        <div class="row">
                            <div class="col-md-4">
                                <input type="text" class="form-control json-key" value="${key}" placeholder="Schlüssel" data-index="${index}">
                            </div>
                            <div class="col-md-7">
                                <input type="text" class="form-control json-value" value="${value}" placeholder="Wert" data-index="${index}">
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeJsonField(${index})">×</button>
                            </div>
                        </div>
                    </div>`;
            });
            
            html += `
                </div>
                <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addJsonField()">+ Feld hinzufügen</button>
                <input type="hidden" name="value" id="jsonHiddenValue">`;
            
            container.innerHTML = html;
            
            // Update hidden field with current JSON
            updateJsonHiddenField();
            
            // Add event listeners to update hidden field when values change
            container.querySelectorAll('.json-key, .json-value').forEach(input => {
                input.addEventListener('input', updateJsonHiddenField);
            });
        }

        function addJsonField() {
            const container = document.querySelector('.json-fields');
            const index = container.children.length;
            
            const newFieldHtml = `
                <div class="json-field-row mb-2">
                    <div class="row">
                        <div class="col-md-4">
                            <input type="text" class="form-control json-key" value="" placeholder="Schlüssel" data-index="${index}">
                        </div>
                        <div class="col-md-7">
                            <input type="text" class="form-control json-value" value="" placeholder="Wert" data-index="${index}">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeJsonField(${index})">×</button>
                        </div>
                    </div>
                </div>`;
            
            container.insertAdjacentHTML('beforeend', newFieldHtml);
            
            // Add event listeners to new inputs
            const newRow = container.lastElementChild;
            newRow.querySelectorAll('.json-key, .json-value').forEach(input => {
                input.addEventListener('input', updateJsonHiddenField);
            });
            
            updateJsonHiddenField();
        }

        function removeJsonField(index) {
            const fieldRow = document.querySelector(`[data-index="${index}"]`).closest('.json-field-row');
            fieldRow.remove();
            updateJsonHiddenField();
        }

        function updateJsonHiddenField() {
            const jsonData = collectJsonFromVisualEditor();
            const hiddenField = document.getElementById('jsonHiddenValue');
            if (hiddenField) {
                hiddenField.value = JSON.stringify(jsonData);
            }
        }

        function collectJsonFromVisualEditor() {
            const jsonData = {};
            const keyInputs = document.querySelectorAll('.json-key');
            const valueInputs = document.querySelectorAll('.json-value');
            
            keyInputs.forEach((keyInput, index) => {
                const key = keyInput.value.trim();
                const valueInput = valueInputs[index];
                const value = valueInput ? valueInput.value : '';
                
                if (key) {
                    jsonData[key] = value;
                }
            });
            
            return jsonData;
        }

        function saveSetting() {
            const form = document.getElementById('settingForm');
            const formData = new FormData(form);
            formData.append('action', 'save-setting');
            
            // Special handling for JSON type
            const typeField = document.getElementById('settingType');
            const settingType = typeField ? typeField.value : 'string';
            
            if (settingType === 'json') {
                // Check which JSON edit mode is active
                const visualMode = document.getElementById('jsonEditModeVisual')?.checked;
                
                if (visualMode) {
                    // Get value from visual editor (hidden field)
                    const hiddenJsonValue = document.getElementById('jsonHiddenValue');
                    if (hiddenJsonValue) {
                        formData.set('value', hiddenJsonValue.value);
                    }
                } else {
                    // Get value from raw textarea
                    const rawTextarea = document.querySelector('#jsonRawEditor textarea');
                    if (rawTextarea) {
                        formData.set('value', rawTextarea.value);
                    }
                }
            } else {
                // Get value from dynamic field for non-JSON types
                const valueInput = document.querySelector('#settingValueContainer input, #settingValueContainer textarea, #settingValueContainer select');
                if (valueInput) {
                    formData.set('value', valueInput.value);
                }
            }
            
            // Ensure key is included even if the field is disabled
            const keyField = document.getElementById('settingKey');
            if (keyField && keyField.value) {
                formData.set('key', keyField.value);
            }
            
            // ✅ Add CSRF token
            formData.append('csrf_token', admin.csrfToken || '');
            
            fetch('/api/admin.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': admin.csrfToken || ''
                }
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error(`Invalid JSON response: ${text}`);
                    }
                });
            })
            .then(data => {
                
                if (data.success) {
                    closeModal('settingModal');
                    
                    // Show toast with protection against DOM manipulation
                    showToast('Einstellung gespeichert', 'success');
                    
                    // Delay the reload significantly to ensure toast is visible
                    setTimeout(() => {
                        loadSettings(); // Reload settings after toast has time to appear
                    }, 1500); // Increased delay to 1.5 seconds
                    
                    // Clear any cached form data
                    setTimeout(() => {
                        document.getElementById('settingForm').reset();
                    }, 100);
                } else {
                    console.error('Backend error:', data.error || data.message);
                    showToast('Fehler beim Speichern: ' + (data.error || data.message || 'Unbekannter Fehler'), 'error');
                }
            })
            .catch(error => {
                console.error('Error saving setting:', error);
                showToast('Fehler beim Speichern der Einstellung: ' + error.message, 'error');
            });
        }

        function addNewSetting() {
            resetSettingForm();
            document.getElementById('settingModalTitle').textContent = 'Neue Einstellung erstellen';
            // Make the key field editable for new settings
            document.getElementById('settingKey').disabled = false;
            
            // Check if user is admin and disable admin-only options if not
            if (window.adminData && !window.adminData.isAdmin) {
                const adminOnlyOption = document.querySelector('#settingPublic option[value="0"]');
                if (adminOnlyOption) {
                    adminOnlyOption.disabled = true;
                    adminOnlyOption.textContent = 'Nein (nur Admin) - Nicht verfügbar';
                }
                // Set to public by default for non-admins
                document.getElementById('settingPublic').value = '1';
            }
            
            openModal('settingModal');
        }

        function saveAllSettings() {
            // This could be used for bulk updates in the future
            // For now, just show a message that individual saving is required
            showToast('Einstellungen werden einzeln gespeichert. Verwenden Sie "Bearbeiten" für jede Einstellung.', 'info');
        }

        function filterSettings() {
            const searchTerm = document.getElementById('settingsSearch').value.toLowerCase();
            const settingsContainer = document.getElementById('settings-container');
            
            if (!window.allSettings) {
                return; // No settings loaded yet
            }
            
            // Clear container
            settingsContainer.innerHTML = '';
            
            // Filter and display settings
            const filteredSettings = window.allSettings.filter(setting => {
                return (setting.key || '').toLowerCase().includes(searchTerm) ||
                       (setting.value || '').toString().toLowerCase().includes(searchTerm) ||
                       (setting.description && setting.description.toLowerCase().includes(searchTerm)) ||
                       (setting.type || '').toLowerCase().includes(searchTerm);
            });
            
            if (filteredSettings.length === 0) {
                settingsContainer.innerHTML = '<div class="no-results">Keine Einstellungen gefunden.</div>';
                return;
            }
            
            filteredSettings.forEach(setting => {
                const settingHtml = createSettingElement(setting);
                settingsContainer.appendChild(settingHtml);
            });
        }

        // Initialize settings when settings tab is activated
        document.addEventListener('DOMContentLoaded', function() {
            const settingsTabButton = document.querySelector('[data-section="settings"]');
            if (settingsTabButton) {
                settingsTabButton.addEventListener('click', function() {
                    setTimeout(() => {
                        loadSettings();
                    }, 100);
                });
            }
            
            // Also listen for hash changes to auto-load settings when navigating via URL
            if (window.location.hash === '#settings') {
                setTimeout(() => {
                    loadSettings();
                }, 200);
            }
            
            window.addEventListener('hashchange', function() {
                if (window.location.hash === '#settings') {
                    setTimeout(() => {
                        loadSettings();
                    }, 100);
                }
            });
        });
        
        JS;
        
        echo '</script>';

        // Password Change Modal - placed for global availability
        echo <<<HTML
    <!-- Password Change Modal -->
    <div id="passwordChangeModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Passwort ändern</h3>
                <span class="close" onclick="closePasswordChangeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="passwordChangeForm" onsubmit="return changePassword(event)">
                    <div class="form-group">
                        <label for="currentPassword">Aktuelles Passwort:</label>
                        <input type="password" id="currentPassword" name="currentPassword" required 
                               class="form-control" placeholder="Aktuelles Passwort eingeben">
                    </div>
                    <div class="form-group">
                        <label for="newPassword">Neues Passwort:</label>
                        <input type="password" id="newPassword" name="newPassword" required 
                               class="form-control" placeholder="Neues Passwort eingeben"
                               minlength="8">
                        <small class="form-text text-muted">
                            Mindestens 8 Zeichen, mit Groß- und Kleinbuchstaben sowie einer Zahl
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Passwort bestätigen:</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required 
                               class="form-control" placeholder="Neues Passwort wiederholen">
                    </div>
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <small class="strength-text" id="strengthText"></small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePasswordChangeModal()">
                    Abbrechen
                </button>
                <button type="submit" form="passwordChangeForm" class="btn btn-primary">
                    Passwort ändern
                </button>
            </div>
        </div>
    </div>

    <!-- Email Template Modal -->
    <div id="emailTemplateModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="emailTemplateModalTitle">E-Mail-Vorlage bearbeiten</h3>
                <span class="close" onclick="closeEmailTemplateModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="emailTemplateForm">
                    <input type="hidden" id="emailTemplateId" name="id">
                    
                    <div class="form-group">
                        <label for="emailTemplateName">Template-Schlüssel:</label>
                        <input type="text" id="emailTemplateName" name="template_key" required placeholder="z.B. customer_confirmation">
                        <small class="help-text">Eindeutiger Bezeichner für das Template</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="emailTemplateSubject">Betreff:</label>
                        <input type="text" id="emailTemplateSubject" name="subject" required>
                        <small class="help-text">Verfügbare Variablen: {customer_name}, {order_id}, {service_type}, {appointment_date}, {customer_address}, {customer_phone}, {total_price}</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="emailTemplateContent">Inhalt:</label>
                        <textarea id="emailTemplateContent" name="content" rows="12" required></textarea>
                        <small class="help-text">HTML und Variablen werden unterstützt. Verwenden Sie {variable_name} für Platzhalter.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="emailTemplateActive" name="is_active" checked>
                            Vorlage aktiv
                        </label>
                    </div>
                    
                    <div class="email-variables-help">
                        <h4>Verfügbare Variablen:</h4>
                        <div class="variables-grid">
                            <div class="variable-item">
                                <strong>{customer_name}</strong> - Name des Kunden
                            </div>
                            <div class="variable-item">
                                <strong>{customer_email}</strong> - E-Mail-Adresse
                            </div>
                            <div class="variable-item">
                                <strong>{customer_phone}</strong> - Telefonnummer
                            </div>
                            <div class="variable-item">
                                <strong>{customer_address}</strong> - Adresse
                            </div>
                            <div class="variable-item">
                                <strong>{order_id}</strong> - Auftrags-ID
                            </div>
                            <div class="variable-item">
                                <strong>{service_type}</strong> - Art der Dienstleistung
                            </div>
                            <div class="variable-item">
                                <strong>{appointment_date}</strong> - Termindatum
                            </div>
                            <div class="variable-item">
                                <strong>{total_price}</strong> - Gesamtpreis
                            </div>
                            <div class="variable-item">
                                <strong>{company_name}</strong> - DS-Allroundservice
                            </div>
                            <div class="variable-item">
                                <strong>{current_date}</strong> - Aktuelles Datum
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="previewEmailTemplate()">
                            <i class="fas fa-eye"></i>
                            Vorschau
                        </button>
                        <button type="button" class="btn btn-info" onclick="sendTestEmail()">
                            <i class="fas fa-paper-plane"></i>
                            Test-E-Mail
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Speichern
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeEmailTemplateModal()">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Email Preview Modal -->
    <div id="emailPreviewModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3>E-Mail-Vorschau</h3>
                <span class="close" onclick="closeEmailPreviewModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="email-preview">
                    <div class="email-header">
                        <strong>Betreff:</strong> <span id="previewSubject"></span>
                    </div>
                    <div class="email-content" id="previewContent">
                        <!-- Vorschau wird hier angezeigt -->
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEmailPreviewModal()">
                    Schließen
                </button>
            </div>
        </div>
    </div>
HTML;
        
        echo '</script>';
        echo '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>';
        echo '<link rel="stylesheet" href="public/assets/css/admin-drag-drop.css">';
        echo '<script src="public/assets/js/admin.js"></script>';
        echo '<script src="public/assets/js/admin-drag-drop.js"></script>';
        
        // Additional JavaScript for enhanced service page navigation
        echo '<script>';
        echo <<<'JS'
        
        // Enhanced service page navigation with URL updates
        document.addEventListener('DOMContentLoaded', function() {  
            // Wait for admin.js to load completely
            setTimeout(function() {
                // Service Pages Navigation
                const serviceSelect = document.getElementById('servicePageSelect');
                if (serviceSelect) {
                    // Add enhanced change event listener
                    serviceSelect.addEventListener('change', function() {
                        const selectedOption = this.selectedOptions[0];
                        const serviceSlug = this.value;
                        const serviceId = selectedOption ? selectedOption.dataset.serviceId : null;
                        
                        if (serviceSlug && serviceId && window.updateUrlHash) {
                            // Update URL with service ID
                            window.updateUrlHash('service-pages', { service_id: serviceId });
                        } else if (!serviceSlug && window.updateUrlHash) {
                            // Clear service parameter when no service selected
                            window.updateUrlHash('service-pages');
                        }
                    });
                }
                
                // Questionnaire Service Filter Navigation
                const questionnaireServiceFilter = document.getElementById('questionnaireServiceFilter');
                if (questionnaireServiceFilter) {
                    questionnaireServiceFilter.addEventListener('change', function() {
                        const selectedOption = this.selectedOptions[0];
                        const serviceSlug = this.value;
                        const serviceId = selectedOption ? selectedOption.dataset.serviceId : null;
                        
                        if (serviceId && window.updateUrlHash) {
                            // Get current query parameters
                            const { query } = window.parseUrlHashAndQuery ? window.parseUrlHashAndQuery() : { query: {} };
                            
                            // Update service filter in URL
                            const newQuery = { ...query, service_id: serviceId };
                            delete newQuery.service; // Remove old service slug if present
                            
                            window.updateUrlHash('questionnaires', newQuery);
                        } else if (!serviceSlug && window.updateUrlHash) {
                            // Remove service filter from URL
                            const { query } = window.parseUrlHashAndQuery ? window.parseUrlHashAndQuery() : { query: {} };
                            const newQuery = { ...query };
                            delete newQuery.service_id;
                            delete newQuery.service;
                            
                            window.updateUrlHash('questionnaires', newQuery);
                        }
                    });
                }
                
                // Questionnaire Status Filter Navigation
                const questionnaireStatusFilter = document.getElementById('questionnaireStatusFilter');
                if (questionnaireStatusFilter) {
                    questionnaireStatusFilter.addEventListener('change', function() {
                        const status = this.value;
                        
                        if (window.updateUrlHash) {
                            // Get current query parameters
                            const { query } = window.parseUrlHashAndQuery ? window.parseUrlHashAndQuery() : { query: {} };
                            
                            if (status) {
                                // Add status filter to URL
                                const newQuery = { ...query, status: status };
                                window.updateUrlHash('questionnaires', newQuery);
                            } else {
                                // Remove status filter from URL
                                const newQuery = { ...query };
                                delete newQuery.status;
                                window.updateUrlHash('questionnaires', newQuery);
                            }
                        }
                    });
                }
            }, 200);
        });
        
JS;
        echo '</script>';
    }
    
    /**
     * Generate emails management section
     */
    private function generateEmailsSection(): void
    {
        echo <<<HTML
                <section id="emails-section" class="content-section">
                    <div class="section-header">
                        <h2>E-Mail-Verwaltung</h2>
                        <div class="section-actions">
                            <button class="btn btn-secondary" onclick="loadEmailTemplates()">
                                <i class="fas fa-refresh"></i>
                                Neu laden
                            </button>
                            <button class="btn btn-primary" onclick="showNewEmailTemplateModal()">
                                <i class="fas fa-plus"></i>
                                Neue Vorlage
                            </button>
                        </div>
                    </div>

                    <div class="email-templates-container">
                        <div class="email-templates-list" id="email-templates-container">
                            <div class="loading-message">
                                <i class="fas fa-spinner fa-spin"></i>
                                Lade E-Mail-Templates...
                            </div>
                        </div>
                    </div>
                </section>
HTML;
    }
}

AdminPage::main();
