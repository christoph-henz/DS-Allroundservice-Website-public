<?php declare(strict_types=1);
// UTF-8 marker äöüÄÖÜß€
//include_once 'CookieHandler.php';
namespace DSAllround\Views;
use Exception;
abstract class Page
{
    // --- ATTRIBUTES ---


    protected ?\PDO $_database = null;
    public $isLocal;

    protected CookieHandler $cookieHandler;


    // --- OPERATIONS ---

    /**
     * Connects to DB and stores
     * the connection in member $_database.
     * Needs name of DB, user, password.
     */
    protected function __construct()
    {
        // DO NOT override error_reporting - index.php sets it correctly for production
        // error_reporting(E_ALL); // REMOVED - causes 500 errors in Apache CGI mode
        
        // Session is already started in index.php
        // Ensure session is available
        if (session_status() === PHP_SESSION_NONE) {
            @session_start(); // Suppress warning if headers already sent
        }

        $this->cookieHandler = new CookieHandler();
        
        // Process cookie form submission BEFORE any output
        $this->processReceivedData();

        // Umgebung prüfen - mit Fallback für CLI-Ausführung
        $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $this->isLocal = ($httpHost === 'localhost') || php_sapi_name() === 'cli';

        if ($this->isLocal) {
            // ✅ Lokale Umgebung: SQLite verwenden
            $sqlitePath = __DIR__ . '/../../database.db'; // Pfad anpassen
            try {
                $this->_database = new \PDO("sqlite:$sqlitePath");
                $this->_database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->_database->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                die("Fehler beim Verbinden mit SQLite: " . $e->getMessage());
            }
        } else {
            // ✅ Live-Umgebung: MySQL/MariaDB verwenden (mit PDO)
            $dbHost = "***********.hosting-data.io";
            $dbUser = "dbu*******";
            $dbPassword = "****************";
            $dbName = "dbs*********";

            try {
                // MySQL-Verbindung mit PDO erstellen
                $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
                $this->_database = new \PDO($dsn, $dbUser, $dbPassword);
                
                // Gleiche Einstellungen wie bei SQLite
                $this->_database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->_database->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
                
                // MySQL-spezifische Einstellung: Emulation von Prepared Statements ausschalten
                $this->_database->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            } catch (\PDOException $e) {
                die("Fehler beim Verbinden mit MySQL: " . $e->getMessage());
            }
        }
    }

    /**
     * Hilfsmethode: Prüft ob MySQL verwendet wird
     * @return bool
     */
    protected function isMySQL(): bool
    {
        return !$this->isLocal;
    }

    /**
     * Hilfsmethode: Gibt den passenden AUTO_INCREMENT Syntax zurück
     * @return string
     */
    protected function getAutoIncrementSyntax(): string
    {
        return $this->isMySQL() ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    /**
     * Hilfsmethode: Gibt den passenden BOOLEAN Typ zurück
     * @return string
     */
    protected function getBooleanType(): string
    {
        return $this->isMySQL() ? 'TINYINT(1)' : 'BOOLEAN';
    }

    /**
     * Hilfsmethode: Gibt den passenden TEXT Typ zurück
     * @param string $size 'small', 'medium', 'long' für MySQL
     * @return string
     */
    protected function getTextType(string $size = 'medium'): string
    {
        if ($this->isMySQL()) {
            switch ($size) {
                case 'small': return 'TEXT';
                case 'long': return 'LONGTEXT';
                default: return 'TEXT';
            }
        }
        return 'TEXT';
    }

    /**
     * Hilfsmethode: Konvertiert SQLite-CREATE TABLE zu MySQL-kompatiblem SQL
     * @param string $sql SQLite CREATE TABLE Statement
     * @return string MySQL/SQLite kompatibles SQL
     */
    protected function convertCreateTableSQL(string $sql): string
    {
        if (!$this->isMySQL()) {
            return $sql; // Für SQLite unverändert zurückgeben
        }
        
        // Für MySQL: Ersetze SQLite-spezifische Syntax
        $sql = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT AUTO_INCREMENT PRIMARY KEY', $sql);
        $sql = str_replace('INTEGER DEFAULT', 'INT DEFAULT', $sql);
        $sql = str_replace('INTEGER NOT NULL', 'INT NOT NULL', $sql);
        $sql = preg_replace('/INTEGER(\s|,|\))/', 'INT$1', $sql);
        
        // BOOLEAN zu TINYINT(1) für MySQL
        $sql = str_replace('BOOLEAN', 'TINYINT(1)', $sql);
        
        // Füge ENGINE und CHARSET für MySQL hinzu (nur wenn nicht schon vorhanden)
        if (!stripos($sql, 'ENGINE=') && stripos($sql, 'CREATE TABLE') !== false) {
            $sql = rtrim($sql);
            if (substr($sql, -1) === ';') {
                $sql = substr($sql, 0, -1);
            }
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        
        return $sql;
    }

    /**
     * Hilfsmethode: Führt CREATE TABLE mit automatischer DB-Anpassung aus
     * @param string $sql SQLite CREATE TABLE Statement
     * @return bool
     */
    protected function execCreateTable(string $sql): bool
    {
        try {
            $convertedSQL = $this->convertCreateTableSQL($sql);
            $this->_database->exec($convertedSQL);
            return true;
        } catch (\PDOException $e) {
            error_log("CREATE TABLE failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Closes the DB connection and cleans up
     */
    public function __destruct()
    {
        //$this->_database->close();
        // to do: close database
    }

    /**
     * Generates the header section of the page.
     * i.e. starting from the content type up to the body-tag.
     * Takes care that all strings passed from outside
     * are converted to safe HTML by htmlspecialchars.
     *
     * @param string $title $title is the text to be used as title of the page
     * @param string $jsFile path to a java script file to be included, default is "" i.e. no java script file
     * @param bool $autoreload  true: auto reload the page every 5 s, false: not auto reload
     * @return void
     */
    protected function generatePageHeader(string $title = "", string $jsFile = "", bool $autoreload = false):void
    {
        $title = htmlspecialchars($title);
        
        // Only send header if headers haven't been sent yet
        if (!headers_sent()) {
            header("Content-type: text/html; charset=UTF-8");
        }

        // Generate CSRF token for forms
        if (!isset($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }

        // to do: handle all parameters
        // to do: output common beginning of HTML code

        echo <<< HTML
            <!DOCTYPE html>
            <html lang="de">
                <head>
                <title>$title</title>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="stylesheet" type="text/css" href="public/assets/css/page-components.css"/>
                <script src="public/assets/js/sticky-header.js" defer></script>
                <script>
                    window.sessionToken = '{$_SESSION['token']}';
                </script>                
        HTML;
        $this->additionalMetaData();

        echo <<< HTML
                </head>
        HTML;
        
        echo "<body>";
        echo $this->cookieHandler->generateCookieBanner();
        $this->generateStickyHeader();
    }

    /**
     * Optional method to be implemented by child classes to add
     * additional metadata to the header
     */
    protected function additionalMetaData(): void
    {
        // Default implementation is empty
    }

    /**
     * Generate sticky navigation header that appears on scroll up
     */
    protected function generateStickyHeader(): void
    {
        // Get current page to determine active navigation
        $currentPage = $this->getCurrentPage();
        
        // Load active services from database for dynamic menu
        $services = $this->loadActiveServices();
        
        // Load phone number from settings
        $phoneNumber = '+49 1522 5650967'; // Default fallback
        $phoneNumberClean = '+4915225650967'; // For tel: link

        try {
            $stmt = $this->_database->prepare("SELECT setting_value FROM settings WHERE setting_key = 'contact_phone'");
            $stmt->execute();
            $phoneResult = $stmt->fetch();
            if ($phoneResult) {
                $phoneNumber = $phoneResult['setting_value'];
                // Remove spaces and special chars for tel: link
                $phoneNumberClean = preg_replace('/[^0-9+]/', '', $phoneNumber);
            }
        } catch (\PDOException $e) {
            error_log("Error loading phone number: " . $e->getMessage());
        }
        
        echo <<< HTML
            <!-- Sticky Header -->
            <header class="sticky-header">
                <nav class="sticky-nav">
                    <div class="sticky-logo">
                        <img src="public/assets/img/logo.png" alt="DS-Allroundservice" class="sticky-logo-img"/>
                        <span class="sticky-company-name">DS-Allroundservice</span>
                    </div>
                    <div class="sticky-menu">
        HTML;
        
        // Generate navigation based on current page
        if ($currentPage === 'home') {
            // Home page: use anchor links to sections
            echo '<a href="#home" class="active">Home</a>';
            echo '<a href="#about">Über uns</a>';
            echo '<a href="#services">Leistungen</a>';
            echo '<a href="#pricing">Preise</a>';
        } else {
            // Service pages: use page links with dynamic services
            echo '<a href="/" class="' . $this->getActiveClass('home', $currentPage) . '">Home</a>';
            
            // Dynamic service links from database
            foreach ($services as $service) {
                $slug = htmlspecialchars($service['slug']);
                $name = htmlspecialchars($service['name']);
                $activeClass = $this->getActiveClass($slug, $currentPage);
                echo '<a href="/' . $slug . '" class="' . $activeClass . '">' . $name . '</a>';
            }
        }
        
        echo <<< HTML
                    </div>
                    <div class="sticky-cta">
                        <a href="tel:{$phoneNumberClean}" class="sticky-phone-btn">
                            📞 Anrufen
                        </a>
                    </div>
                </nav>
            </header>
        HTML;
    }

    /**
     * Get current page identifier for navigation highlighting
     */
    protected function getCurrentPage(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = trim($path, '/');
        
        if (empty($path)) return 'home';
        if (strpos($path, 'umzug') !== false) return 'umzuege';
        if (strpos($path, 'transport') !== false) return 'transport';
        if (strpos($path, 'entruempelung') !== false) return 'entruempelung';
        if (strpos($path, 'aufloesung') !== false) return 'aufloesung';
        if (strpos($path, 'contact') !== false) return 'contact';
        
        return $path;
    }

    /**
     * Get active class for navigation items
     */
    protected function getActiveClass(string $page, string $currentPage): string
    {
        return $page === $currentPage ? 'active' : '';
    }

    /**
     * Generate responsive footer for all pages
     */
    protected function generateFooter(): void
    {
        // Load settings and services from database
        $settings = $this->loadFooterSettings();
        $services = $this->loadActiveServices();
        
        echo <<<HTML
            <footer class="main-footer">
                <div class="footer-container">
                    <!-- Footer Top Section -->
                    <div class="footer-top">
                        <div class="footer-brand">
                            <div class="footer-logo">
                                <img src="public/assets/img/logo.png" alt="DS-Allroundservice Logo" class="footer-logo-img">
                                <div class="footer-brand-text">
                                    <h3>{$settings['company_name']}</h3>
                                    <p>{$settings['company_slogan']}</p>
                                </div>
                            </div>
                            <p class="footer-description">
                                {$settings['company_description']}
                            </p>
                        </div>
                        
                        <div class="footer-services">
                            <h4>Unsere Leistungen</h4>
                            <ul>
HTML;

        // Generate service links from database
        foreach ($services as $service) {
            $slug = htmlspecialchars($service['slug']);
            $name = htmlspecialchars($service['name']);
            echo "<li><a href=\"/{$slug}\">{$name}</a></li>\n";
        }

        echo <<<HTML
                            </ul>
                        </div>
                        
                        <div class="footer-contact">
                            <h4>Kontakt</h4>
                            <div class="contact-item">
                                <span class="contact-icon">📞</span>
                                <div>
                                    <a href="tel:{$settings['contact_phone_clean']}">{$settings['contact_phone']}</a>
                                    <small>{$settings['business_hours']}</small>
                                </div>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">✉️</span>
                                <div>
                                    <a href="mailto:{$settings['contact_email']}">{$settings['contact_email']}</a>
                                    <small>{$settings['email_response_time']}</small>
                                </div>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">📍</span>
                                <div>
                                    {$settings['contact_address']}
                                </div>
                            </div>
                        </div>
                        
                        <div class="footer-social">
                            <h4>Folgen Sie uns</h4>
                            <div class="social-links">
HTML;

        // Generate social media links if available
        if (!empty($settings['social_instagram'])) {
            echo <<<HTML
                                <a href="{$settings['social_instagram']}" class="social-link" aria-label="Instagram">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                    </svg>
                                </a>
HTML;
        }

        if (!empty($settings['social_facebook'])) {
            echo <<<HTML
                                <a href="{$settings['social_facebook']}" class="social-link" aria-label="Facebook">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                    </svg>
                                </a>
HTML;
        }

        echo <<<HTML
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer Bottom Section -->
                    <div class="footer-bottom">
                        <div class="footer-legal">
                            <p>&copy; {$settings['copyright_year']} <!--{$settings['company_name']}--> Christoph Henz. Alle Rechte vorbehalten.</p>

                            <div class="footer-links">
                                <a href="/impressum">Impressum</a>
                                <a href="/datenschutz">Datenschutz</a>
                                <a href="/agb">AGB</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <style>
                /* Footer Styles */
                .main-footer {
                    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
                    color: #ffffff;
                }
                
                .footer-container {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 0 20px;
                }
                
                .footer-top {
                    display: grid;
                    grid-template-columns: 2fr 1fr 1fr 1fr;
                    gap: 40px;
                    padding: 60px 0 40px;
                    border-bottom: 1px solid #404040;
                }
                
                .footer-brand {
                    max-width: 350px;
                }
                
                .footer-logo {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    margin-bottom: 20px;
                }
                
                .footer-logo-img {
                    width: 50px;
                    height: 50px;
                    border-radius: 8px;
                }
                
                .footer-brand-text h3 {
                    margin: 0 0 5px 0;
                    font-size: 24px;
                    font-weight: 700;
                    color: #ffffff;
                }
                
                .footer-brand-text p {
                    margin: 0;
                    font-size: 14px;
                    color: #007cba;
                    font-weight: 500;
                }
                
                .footer-description {
                    color: #cccccc;
                    line-height: 1.6;
                    font-size: 15px;
                }
                
                .footer-services h4,
                .footer-contact h4,
                .footer-social h4 {
                    margin: 0 0 20px 0;
                    font-size: 18px;
                    font-weight: 600;
                    color: #ffffff;
                }
                
                .footer-services ul {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }
                
                .footer-services li {
                    margin-bottom: 12px;
                }
                
                .footer-services a {
                    color: #cccccc;
                    text-decoration: none;
                    font-size: 15px;
                    transition: color 0.3s ease;
                }
                
                .footer-services a:hover {
                    color: #007cba;
                }
                
                .contact-item {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    margin-bottom: 20px;
                }
                
                .contact-icon {
                    font-size: 18px;
                    width: 24px;
                    flex-shrink: 0;
                }
                
                .contact-item a {
                    color: #ffffff;
                    text-decoration: none;
                    font-weight: 500;
                    font-size: 15px;
                    transition: color 0.3s ease;
                }
                
                .contact-item a:hover {
                    color: #007cba;
                }
                
                .contact-item small {
                    display: block;
                    color: #cccccc;
                    font-size: 13px;
                    margin-top: 2px;
                }
                
                .social-links {
                    display: flex;
                    gap: 15px;
                }
                
                .social-link {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 44px;
                    height: 44px;
                    background: #404040;
                    border-radius: 8px;
                    color: #ffffff;
                    text-decoration: none;
                    transition: all 0.3s ease;
                }
                
                .social-link:hover {
                    background: #007cba;
                    transform: translateY(-2px);
                }
                
                .footer-bottom {
                    padding: 30px 0;
                    text-align: center;
                }
                
                .footer-legal {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 20px;
                }
                
                .footer-legal p {
                    margin: 0;
                    color: #cccccc;
                    font-size: 14px;
                }
                
                .footer-links {
                    display: flex;
                    gap: 30px;
                }
                
                .footer-links a {
                    color: #cccccc;
                    text-decoration: none;
                    font-size: 14px;
                    transition: color 0.3s ease;
                }
                
                .footer-links a:hover {
                    color: #007cba;
                }
                
                /* Responsive Design */
                @media (max-width: 768px) {
                    .footer-top {
                        grid-template-columns: 1fr;
                        gap: 40px;
                        padding: 40px 0;
                        text-align: center;
                    }
                    
                    .footer-brand {
                        max-width: 100%;
                    }
                    
                    .footer-logo {
                        justify-content: center;
                    }
                    
                    .footer-legal {
                        flex-direction: column;
                        text-align: center;
                        gap: 15px;
                    }
                    
                    .footer-links {
                        justify-content: center;
                        gap: 20px;
                    }
                    
                    .social-links {
                        justify-content: center;
                    }
                }
                
                @media (max-width: 1024px) and (min-width: 769px) {
                    .footer-top {
                        grid-template-columns: 1fr 1fr;
                        gap: 40px;
                    }
                    
                    .footer-brand {
                        grid-column: 1 / -1;
                        text-align: center;
                        max-width: 100%;
                    }
                    
                    .footer-logo {
                        justify-content: center;
                    }
                }
                </style>
            </footer>
HTML;
    }

    /**
     * Load footer-specific settings from database
     */
    private function loadFooterSettings(): array
    {
        $defaultSettings = [
            'company_name' => 'DS-Allroundservice',
            'company_slogan' => 'Zuverlässig. Schnell. Preiswert.',
            'company_description' => 'Ihr vertrauensvoller Partner für Umzüge, Transport und Hausmeisterdienste. Professionell, zuverlässig und zu fairen Preisen.',
            'contact_phone' => '+49 (0) 123 456 789',
            'contact_phone_clean' => '+49123456789',
            'contact_email' => 'info@ds-allroundservice.de',
            'business_hours' => 'Mo-Fr: 8:00 - 18:00 Uhr',
            'email_response_time' => 'Antwort binnen 24h',
            'contact_address' => 'Musterstraße 123<br>12345 Musterstadt',
            'copyright_year' => date('Y'),
            'social_instagram' => '',
            'social_facebook' => ''
        ];

        try {
            $settingKeys = [
                'company_name', 'company_slogan', 'company_description',
                'contact_phone', 'contact_email', 'business_hours', 'email_response_time',
                'contact_address',
                'social_instagram', 'social_facebook', 'copyright_year'
            ];
            
            // Create placeholders matching the number of keys
            $placeholders = str_repeat('?,', count($settingKeys) - 1) . '?';
            $stmt = $this->_database->prepare("SELECT setting_key, setting_value, setting_type FROM settings WHERE setting_key IN ($placeholders)");
            $stmt->execute($settingKeys);
            $dbSettings = $stmt->fetchAll();

            // Override defaults with database values and handle JSON settings
            foreach ($dbSettings as $setting) {
                $value = $setting['setting_value'];
                $type = $setting['setting_type'] ?? 'string';
                
                // Special handling for JSON settings
                if ($type === 'json') {
                    $value = $this->formatJsonSettingForFooter($value, $setting['setting_key']);
                }
                
                $defaultSettings[$setting['setting_key']] = $value;
            }

            // Generate clean phone number for tel: link
            if (isset($defaultSettings['contact_phone'])) {
                $defaultSettings['contact_phone_clean'] = preg_replace('/[^+\d]/', '', $defaultSettings['contact_phone']);
            }

        } catch (\PDOException $e) {
            error_log("Error loading footer settings: " . $e->getMessage());
            // Return defaults if database error
        }

        return $defaultSettings;
    }

    /**
     * Format JSON settings for footer display
     */
    private function formatJsonSettingForFooter(string $jsonValue, string $settingKey): string
    {
        try {
            $jsonData = json_decode($jsonValue, true);
            
            if (!is_array($jsonData)) {
                return $jsonValue; // Return original if not valid JSON object
            }
            
            // Special formatting based on setting key
            switch ($settingKey) {
                case 'business_hours':
                    // Format opening hours nicely
                    return $this->formatBusinessHours($jsonData);
                
                case 'contact_address':
                    // Format address as single line
                    if (isset($jsonData['street']) && isset($jsonData['city'])) {
                        return $jsonData['street'] . ', ' . $jsonData['city'];
                    }
                    break;
                
                default:
                    // Generic formatting: show first key-value pair or count
                    $keys = array_keys($jsonData);
                    if (count($keys) === 1) {
                        return $jsonData[$keys[0]];
                    } elseif (count($keys) <= 3) {
                        return implode(', ', array_map(function($key) use ($jsonData) {
                            return $key . ': ' . $jsonData[$key];
                        }, $keys));
                    } else {
                        return count($keys) . ' Einträge';
                    }
            }
            
        } catch (Exception $e) {
            error_log("Error formatting JSON setting {$settingKey}: " . $e->getMessage());
        }
        
        return $jsonValue; // Fallback to original value
    }

    /**
     * Format business hours for footer display
     */
    private function formatBusinessHours(array $hours): string
    {
        $workdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $weekend = ['saturday', 'sunday'];
        
        // Check if all workdays have the same hours
        $workdayHours = [];
        foreach ($workdays as $day) {
            if (isset($hours[$day]) && $hours[$day] !== 'Geschlossen') {
                $workdayHours[] = $hours[$day];
            }
        }
        
        // If all workdays are the same, show compact format
        if (count(array_unique($workdayHours)) === 1 && count($workdayHours) === 5) {
            $weekdayTime = $workdayHours[0];
            
            // Check weekend
            $saturdayHours = $hours['saturday'] ?? 'Geschlossen';
            $sundayHours = $hours['sunday'] ?? 'Geschlossen';
            
            if ($saturdayHours === 'Geschlossen' && $sundayHours === 'Geschlossen') {
                return "Mo-Fr: {$weekdayTime}";
            } elseif ($saturdayHours !== 'Geschlossen' && $sundayHours === 'Geschlossen') {
                return "Mo-Fr: {$weekdayTime}, Sa: {$saturdayHours}";
            } else {
                return "Mo-Fr: {$weekdayTime}, Sa: {$saturdayHours}, So: {$sundayHours}";
            }
        }
        
        // Fallback: show today's hours or general info
        $today = strtolower(date('l'));
        $germanDays = [
            'monday' => 'Montag',
            'tuesday' => 'Dienstag', 
            'wednesday' => 'Mittwoch',
            'thursday' => 'Donnerstag',
            'friday' => 'Freitag',
            'saturday' => 'Samstag',
            'sunday' => 'Sonntag'
        ];
        
        if (isset($hours[$today])) {
            $todayGerman = $germanDays[$today] ?? ucfirst($today);
            return "Heute ({$todayGerman}): " . $hours[$today];
        }
        
        return "Öffnungszeiten verfügbar";
    }

    /**
     * Load active services from database for footer display
     */
    private function loadActiveServices(): array
    {
        try {
            $stmt = $this->_database->prepare("SELECT slug, name FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Error loading footer services: " . $e->getMessage());
            // Return default services as fallback
            return [
                ['slug' => 'umzuege', 'name' => 'Umzüge'],
                ['slug' => 'transport', 'name' => 'Transport'],
                ['slug' => 'entruempelung', 'name' => 'Entrümpelung'],
                ['slug' => 'aufloesung', 'name' => 'Wohnungsauflösung']
            ];
        }
    }

    /**
     * Outputs the end of the HTML-file i.e. </body> etc.
     * @return void
     */
    protected function generatePageFooter():void
    {
        echo <<< HERE
             </main>
            </body>
        HERE;
    }

    /**
     * Processes the data that comes in via GET or POST.
     * If every derived page is supposed to do something common
     * with submitted data do it here.
     * E.g. checking the settings of PHP that
     * influence passing the parameters (e.g. magic_quotes).
     * @return void
     */
    protected function processReceivedData():void
    {
        if(isset($_POST[CookieHandler::ALLOW_NECESSARY_KEY]) || isset($_POST[CookieHandler::ALLOW_ANALYTICS_KEY])){
            $this->cookieHandler->setAskedBefore(true);
            // Necessary cookies are always enabled (checked and disabled in form)
            $this->cookieHandler->setAllowNecessary(true);
            // Analytics cookies are optional
            $this->cookieHandler->setAllowAnalytics(isset($_POST[CookieHandler::ALLOW_ANALYTICS_KEY]) && $_POST[CookieHandler::ALLOW_ANALYTICS_KEY]);
            
            // Set session variable for immediate effect (before cookies are sent to browser)
            $_SESSION['cookie_settings_saved'] = true;
            
            // Debug: Log cookie setting
            error_log("Cookie settings saved: Necessary=1, Analytics=" . (isset($_POST[CookieHandler::ALLOW_ANALYTICS_KEY]) ? '1' : '0'));
            
            $uri = $_SERVER['REQUEST_URI'];
            header("HTTP/1.1 303 See Other");
            header("Location: $uri");
            exit();
        }
    }
}
