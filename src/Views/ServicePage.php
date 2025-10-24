<?php

namespace DSAllround\Views;
use Exception;

require_once 'Page.php';

class ServicePage extends Page
{
    private $service;
    private $serviceContent;
    private $questionnaire;

    /**
     * Instantiates members and loads service data from database
     */
    protected function __construct($serviceSlug)
    {
        parent::__construct();
        $this->loadServiceData($serviceSlug);
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    /**
     * Load service and content data from database
     */
    private function loadServiceData($serviceSlug): void
    {
        try {
            // Load service basic data
            $stmt = $this->_database->prepare("SELECT * FROM services WHERE slug = ? AND is_active = 1");
            $stmt->execute([$serviceSlug]);
            $this->service = $stmt->fetch();

            if (!$this->service) {
                throw new Exception("Service not found or inactive");
            }

            // Load service page content
            $stmt = $this->_database->prepare("SELECT * FROM service_pages WHERE service_id = ?");
            $stmt->execute([$this->service['id']]);
            $this->serviceContent = $stmt->fetch();

            // Load questionnaire if exists
            $stmt = $this->_database->prepare("
                SELECT q.*, qq.question_id, qs.question_text, qs.question_type, qs.options
                FROM questionnaires q 
                LEFT JOIN questionnaire_questions qq ON q.id = qq.questionnaire_id
                LEFT JOIN questions_simple qs ON qq.question_id = qs.id
                WHERE q.service_id = ? AND q.status = 'active'
                ORDER BY qq.sort_order ASC
            ");
            $stmt->execute([$this->service['id']]);
            $questionnaireRows = $stmt->fetchAll();
            
            if (!empty($questionnaireRows)) {
                $this->questionnaire = [
                    'id' => $questionnaireRows[0]['id'],
                    'title' => $questionnaireRows[0]['title'],
                    'description' => $questionnaireRows[0]['description'],
                    'questions' => []
                ];
                
                foreach ($questionnaireRows as $row) {
                    if ($row['question_id']) {
                        $this->questionnaire['questions'][] = [
                            'id' => $row['question_id'],
                            'question_text' => $row['question_text'],
                            'question_type' => $row['question_type'],
                            'options' => $row['options']
                        ];
                    }
                }
            }

        } catch (\PDOException $e) {
            error_log("Database error in loadServiceData: " . $e->getMessage());
            throw new Exception("Service data could not be loaded");
        }
    }

    /**
     * Static factory method to create service page
     */
    public static function create($serviceSlug): void
    {
        try {
            $page = new ServicePage($serviceSlug);
            $page->generateView();
        } catch (Exception $e) {
            // Show 404 error page
            header("HTTP/1.0 404 Not Found");
            header("Content-type: text/html; charset=UTF-8");
            echo "<h1>404 - Service nicht gefunden</h1>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    protected function generateView(): void
    {
        $pageTitle = $this->serviceContent['meta_title'] ?? $this->service['title'];
        $this->generatePageHeader($pageTitle);
        $this->generateMainBody();
    }

    private function generateMainBody(): void
    {
        $this->generateServiceNav();
        $this->generateServiceHero();
        $this->generateServiceIntro();
        $this->generateServiceFeatures();
        $this->generateServiceProcess();
        $this->generateServicePricing();
        $this->generateServiceFAQ();
        $this->generateServiceCTA();
        $this->generateFooter();
    }

    protected function additionalMetaData(): void
    {
        $metaTitle = $this->serviceContent['meta_title'] ?? $this->service['title'];
        $metaDescription = $this->serviceContent['meta_description'] ?? $this->service['description'];
        $metaKeywords = $this->serviceContent['meta_keywords'] ?? '';

        echo <<<HTML
            <meta name="description" content="{$metaDescription}">
            <meta name="keywords" content="{$metaKeywords}">
            <meta property="og:title" content="{$metaTitle}">
            <meta property="og:description" content="{$metaDescription}">
            
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" type="text/css" href="public/assets/css/page-components.css"/>
            <link rel="stylesheet" type="text/css" href="public/assets/css/services.css"/>
            <link rel="stylesheet" type="text/css" href="public/assets/css/home.css"/>
        HTML;
    }

    /**
     * Generate dynamic service navigation
     */
    private function generateServiceNav(): void
    {
        // Load all active services for navigation
        $stmt = $this->_database->prepare("SELECT slug, name FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $stmt->execute();
        $allServices = $stmt->fetchAll();

        echo '<header class="service-header">';
        echo '<nav class="service-nav">';
        echo '<div class="logo-area">';
        echo '<h1>LOGO</h1>';
        echo '<div class="company-info">';
        echo '<div class="company-name">DS-Allroundservice</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="nav-menu">';
        echo '<a href="/">Home</a>';

        foreach ($allServices as $navService) {
            $activeClass = ($navService['slug'] === $this->service['slug']) ? ' class="active"' : '';
            $serviceName = htmlspecialchars($navService['name']);
            $serviceSlug = htmlspecialchars($navService['slug']);
            echo "<a href=\"/{$serviceSlug}\"{$activeClass}>{$serviceName}</a>";
        }

        echo '</div>';
        echo '</nav>';
        echo '</header>';
    }

    private function generateServiceHero(): void
    {
        $heroTitle = $this->serviceContent['hero_title'] ?? $this->service['title'];
        $heroSubtitle = $this->serviceContent['hero_subtitle'] ?? $this->service['description'];
        $serviceName = htmlspecialchars($this->service['name']);

        echo <<<HTML
            <section class="service-hero">
                <div class="service-hero-content">
                    <div class="service-breadcrumb">
                        <a href="/">Home</a>
                        <span>/</span>
                        <span>{$serviceName}</span>
                    </div>
                    <h1>{$heroTitle}</h1>
                    <p>{$heroSubtitle}</p>
                </div>
            </section>
        HTML;
    }

    private function generateServiceIntro(): void
    {
        $introTitle = $this->serviceContent['intro_title'] ?? "Ihr zuverlässiger Partner für {$this->service['name']}";
        $introContent = $this->serviceContent['intro_content'] ?? $this->service['description'];
        
        // Auto-wrap content in paragraphs if not already wrapped
        if ($introContent && !preg_match('/<[^>]+>/', $introContent)) {
            $introContent = '<p>' . nl2br(htmlspecialchars($introContent)) . '</p>';
        }

        echo <<<HTML
            <section class="service-content">
                <div class="container">
                    <div class="service-intro">
                        <h2>{$introTitle}</h2>
                        <div>{$introContent}</div>
                    </div>
                </div>
            </section>
        HTML;
    }

    private function generateServiceFeatures(): void
    {
        $featuresTitle = $this->serviceContent['features_title'] ?? "Unsere {$this->service['name']}-Leistungen";
        $featuresSubtitle = $this->serviceContent['features_subtitle'] ?? "Alles aus einer Hand für Ihren Service";
        
        // Parse features JSON
        $features = [];
        if ($this->serviceContent && $this->serviceContent['features_content']) {
            $features = json_decode($this->serviceContent['features_content'], true) ?? [];
        }

        // Fallback features if none in database
        if (empty($features)) {
            $features = $this->getDefaultFeatures($this->service['slug']);
        }

        echo <<<HTML
            <section class="service-features-section">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">{$featuresTitle}</h2>
                        <p class="section-description">{$featuresSubtitle}</p>
                    </div>
                    <div class="service-features-grid">
        HTML;

        foreach ($features as $feature) {
            $icon = htmlspecialchars($feature['icon'] ?? '⚡');
            $title = htmlspecialchars($feature['title'] ?? '');
            $description = htmlspecialchars($feature['description'] ?? '');

            echo <<<HTML
                        <div class="service-feature-card">
                            <div class="service-feature-icon">{$icon}</div>
                            <h3>{$title}</h3>
                            <p>{$description}</p>
                        </div>
            HTML;
        }

        echo <<<HTML
                    </div>
                </div>
            </section>
        HTML;
    }

    private function generateServiceProcess(): void
    {
        $processTitle = $this->serviceContent['process_title'] ?? "So läuft Ihr {$this->service['name']} ab";
        $processSubtitle = $this->serviceContent['process_subtitle'] ?? "In einfachen Schritten zu Ihrem Ziel";
        
        // Parse process JSON
        $processSteps = [];
        if ($this->serviceContent && $this->serviceContent['process_content']) {
            $processSteps = json_decode($this->serviceContent['process_content'], true) ?? [];
        }

        // Fallback process if none in database
        if (empty($processSteps)) {
            $processSteps = $this->getDefaultProcessSteps($this->service['slug']);
        }

        echo <<<HTML
            <section class="service-process">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">{$processTitle}</h2>
                        <p class="section-description">{$processSubtitle}</p>
                    </div>
                    <div class="process-steps">
        HTML;

        foreach ($processSteps as $index => $step) {
            $stepNumber = $index + 1;
            $title = htmlspecialchars($step['title'] ?? '');
            $description = htmlspecialchars($step['description'] ?? '');

            echo <<<HTML
                        <div class="process-step">
                            <div class="process-number">{$stepNumber}</div>
                            <h4>{$title}</h4>
                            <p>{$description}</p>
                        </div>
            HTML;
        }

        echo <<<HTML
                    </div>
                </div>
            </section>
        HTML;
    }

    private function generateServicePricing(): void
    {
        $pricingTitle = $this->serviceContent['pricing_title'] ?? "{$this->service['name']}-Preise";
        $pricingSubtitle = $this->serviceContent['pricing_subtitle'] ?? "Transparente Preisgestaltung ohne versteckte Kosten";
        
        // Load pricing data from service
        $pricingData = [];
        if ($this->service['pricing_data']) {
            $pricingData = json_decode($this->service['pricing_data'], true) ?? [];
        }
        
        echo <<<HTML
            <section class="service-pricing">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">{$pricingTitle}</h2>
                        <p class="section-description">{$pricingSubtitle}</p>
                    </div>
HTML;
        
        if (!empty($pricingData)) {
            // Display pricing table with database data
            echo '<div class="pricing-table">';
            echo '<div class="pricing-table-header">';
            echo '<h3>' . htmlspecialchars($this->service['name']) . '-Preise</h3>';
            echo '<p>Alle Preise inklusive Material und Versicherung</p>';
            echo '</div>';
            echo '<div class="pricing-table-body">';
            
            foreach ($pricingData as $price) {
                $description = htmlspecialchars($price['description']);
                $value = number_format($price['value'], 0, ',', '.');
                $unit = htmlspecialchars($price['unit']);
                
                echo <<<HTML
                    <div class="pricing-item">
                        <div class="pricing-item-name">{$description}</div>
                        <div class="pricing-item-price">ab {$value}{$unit}</div>
                    </div>
HTML;
            }
            
            echo '</div>';
            echo '</div>';
        } else {
            // Fallback pricing note
            echo <<<HTML
                <div class="pricing-note">
                    <p>Gerne erstellen wir Ihnen ein individuelles Angebot basierend auf Ihren spezifischen Anforderungen.</p>
                </div>
HTML;
        }
        
        echo <<<HTML
                </div>
            </section>
        HTML;
    }

    private function generateServiceFAQ(): void
    {
        $faqTitle = $this->serviceContent['faq_title'] ?? "Häufig gestellte Fragen zu {$this->service['name']}";
        
        // Parse FAQ JSON from database
        $faqs = [];
        if ($this->serviceContent && !empty($this->serviceContent['faq_content'])) {
            $decoded = json_decode($this->serviceContent['faq_content'], true);
            if (is_array($decoded)) {
                $faqs = $decoded;
            }
        }

        // Fallback FAQs if none in database
        if (empty($faqs)) {
            $faqs = $this->getDefaultFAQs($this->service['slug']);
        }

        // Don't render section if no FAQs
        if (empty($faqs)) {
            return;
        }

        echo <<<HTML
            <section class="service-faq">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">{$faqTitle}</h2>
                    </div>
                    <div class="faq-list">
        HTML;

        foreach ($faqs as $index => $faq) {
            $question = htmlspecialchars($faq['question'] ?? '');
            $answer = htmlspecialchars($faq['answer'] ?? '');
            $isFirst = $index === 0 ? ' active' : '';

            echo <<<HTML
                        <div class="faq-item{$isFirst}">
                            <button class="faq-question" onclick="toggleFAQ(this)">
                                <span>{$question}</span>
                                <span class="faq-icon">+</span>
                            </button>
                            <div class="faq-answer">
                                <p>{$answer}</p>
                            </div>
                        </div>
            HTML;
        }

        echo <<<HTML
                    </div>
                </div>
            </section>

            <script>
                function toggleFAQ(button) {
                    const faqItem = button.parentElement;
                    const isActive = faqItem.classList.contains('active');
                    
                    // Close all other FAQs
                    document.querySelectorAll('.faq-item').forEach(item => {
                        item.classList.remove('active');
                        const icon = item.querySelector('.faq-icon');
                        if (icon) icon.textContent = '+';
                    });
                    
                    // Toggle current FAQ
                    if (!isActive) {
                        faqItem.classList.add('active');
                        const icon = button.querySelector('.faq-icon');
                        if (icon) icon.textContent = '−';
                    }
                }
            </script>
        HTML;
    }

    private function generateServiceCTA(): void
    {
        $ctaText = $this->serviceContent['hero_cta_text'] ?? "Jetzt kostenlos anfragen";
        $serviceName = htmlspecialchars($this->service['name']);
        $serviceSlug = htmlspecialchars($this->service['slug']);

        // Load phone number from database
        $phoneNumber = '+49 1522 5650967'; // Fallback
        $phoneNumberClean = '+4915225650967';

        try {
            $stmt = $this->_database->prepare("SELECT setting_value FROM settings WHERE setting_key = 'contact_phone'");
            $stmt->execute();
            $phoneResult = $stmt->fetch();
            if ($phoneResult) {
                $phoneNumber = $phoneResult['setting_value'];
                $phoneNumberClean = preg_replace('/[^0-9+]/', '', $phoneNumber);
            }
        } catch (\PDOException $e) {
            error_log("Error loading phone number in CTA: " . $e->getMessage());
        }

        echo <<<HTML
            <section class="service-cta">
                <div class="container">
                    <div class="cta-content">
                        <h2>Bereit für Ihren {$serviceName}?</h2>
                        <p>Kontaktieren Sie uns noch heute für eine kostenlose Beratung und ein unverbindliches Angebot.</p>
                        <div class="cta-buttons">
                            <a href="tel:{$phoneNumberClean}" class="btn btn-primary">
                                <span class="btn-icon">📞</span>
                                Jetzt anrufen
                            </a>
                            <a href="/{$serviceSlug}-anfrage" class="btn btn-secondary btn-large">
                                <span class="btn-icon">📋</span>
                                {$ctaText}
                            </a>
                        </div>
                        
                        <div class="cta-features">
                            <div class="cta-feature">
                                <span class="feature-icon">✓</span>
                                <span>Kostenlose Beratung</span>
                            </div>
                            <div class="cta-feature">
                                <span class="feature-icon">✓</span>
                                <span>Unverbindliches Angebot</span>
                            </div>
                            <div class="cta-feature">
                                <span class="feature-icon">✓</span>
                                <span>Schnelle Rückmeldung</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        HTML;
    }

    /**
     * Get default features based on service slug (from Umzuege.php)
     */
    private function getDefaultFeatures($serviceSlug): array
    {
        $featureMap = [
            'umzuege' => [
                ['icon' => '📦', 'title' => 'Verpackungsservice', 'description' => 'Professionelles Verpacken Ihrer Habseligkeiten mit hochwertigem Material. Ihre Gegenstände sind bei uns sicher.'],
                ['icon' => '🚚', 'title' => 'Transport', 'description' => 'Sichere Beförderung mit modernen Umzugswagen. Unsere Fahrzeuge sind voll ausgestattet und versichert.'],
                ['icon' => '🔧', 'title' => 'Möbelmontage', 'description' => 'Demontage am alten und Aufbau am neuen Wohnort. Ihre Möbel werden fachgerecht behandelt.'],
                ['icon' => '🧹', 'title' => 'Endreinigung', 'description' => 'Auf Wunsch übernehmen wir auch die Endreinigung Ihrer alten Wohnung für die Übergabe.'],
                ['icon' => '📋', 'title' => 'Umzugsplanung', 'description' => 'Detaillierte Planung und Koordination aller Umzugsschritte für einen stressfreien Ablauf.'],
                ['icon' => '🏠', 'title' => 'Einrichtungsservice', 'description' => 'Wir helfen Ihnen beim Einrichten und Platzieren der Möbel in Ihrem neuen Zuhause.']
            ],
            'transport' => [
                ['icon' => '🚛', 'title' => 'Möbeltransport', 'description' => 'Sicherer Transport von Möbeln aller Art mit professioneller Ausstattung.'],
                ['icon' => '🎹', 'title' => 'Klaviertransport', 'description' => 'Spezialisierter Transport für Klaviere und andere empfindliche Instrumente.'],
                ['icon' => '⚡', 'title' => 'Geräteverlegung', 'description' => 'Fachgerechte Verlegung von Haushaltsgeräten und Elektronik.'],
                ['icon' => '📦', 'title' => 'Verpackung', 'description' => 'Professionelle Verpackung für sicheren Transport.']
            ],
            'entruempelung' => [
                ['icon' => '🏠', 'title' => 'Keller & Dachboden', 'description' => 'Professionelle Entrümpelung von Kellern, Dachböden und Abstellräumen.'],
                ['icon' => '🧹', 'title' => 'Messie-Wohnungen', 'description' => 'Einfühlsame und diskrete Entrümpelung von Messie-Wohnungen.'],
                ['icon' => '♻️', 'title' => 'Fachgerechte Entsorgung', 'description' => 'Umweltgerechte Entsorgung und Recycling der Gegenstände.'],
                ['icon' => '🚛', 'title' => 'Abtransport', 'description' => 'Kompletter Abtransport aller zu entsorgenden Gegenstände.']
            ],
            'aufloesung' => [
                ['icon' => '🏠', 'title' => 'Komplette Haushaltsauflösung', 'description' => 'Vollständige Auflösung von Haushalten mit allen erforderlichen Arbeitsschritten.'],
                ['icon' => '💎', 'title' => 'Wertgegenstand-Sortierung', 'description' => 'Professionelle Bewertung und Sortierung von wertvollen Gegenständen.'],
                ['icon' => '🔧', 'title' => 'Renovierungsarbeiten', 'description' => 'Auf Wunsch übernehmen wir auch kleinere Renovierungsarbeiten.'],
                ['icon' => '📋', 'title' => 'Bestandsaufnahme', 'description' => 'Detaillierte Dokumentation aller Gegenstände für Erben oder Behörden.']
            ]
        ];
        
        return $featureMap[$serviceSlug] ?? [
            ['icon' => '📦', 'title' => 'Professioneller Service', 'description' => 'Hochwertige Dienstleistung mit erfahrenem Team'],
            ['icon' => '🚚', 'title' => 'Zuverlässigkeit', 'description' => 'Pünktlich und verlässlich für Ihre Bedürfnisse'],
            ['icon' => '💡', 'title' => 'Beratung', 'description' => 'Umfassende Beratung und Planung']
        ];
    }

    /**
     * Get default process steps based on service slug (from Umzuege.php)
     */
    private function getDefaultProcessSteps($serviceSlug): array
    {
        $processMap = [
            'umzuege' => [
                ['title' => 'Kostenlose Beratung', 'description' => 'Wir besichtigen Ihre Wohnung und erstellen ein unverbindliches Angebot.'],
                ['title' => 'Planung & Vorbereitung', 'description' => 'Gemeinsam planen wir alle Details und bereiten den Umzugstag vor.'],
                ['title' => 'Verpackung', 'description' => 'Professionelle Verpackung Ihrer Habseligkeiten mit hochwertigen Materialien.'],
                ['title' => 'Transport & Montage', 'description' => 'Unser erfahrenes Team übernimmt Transport, Demontage und Aufbau der Möbel.'],
                ['title' => 'Nachbetreuung', 'description' => 'Auch nach dem Umzug sind wir für Sie da und kümmern uns um alle Details.']
            ]
        ];
        
        return $processMap[$serviceSlug] ?? [
            ['title' => 'Beratung', 'description' => 'Kostenlose Erstberatung und Bedarfsanalyse'],
            ['title' => 'Planung', 'description' => 'Detaillierte Planung des Vorgehens'],
            ['title' => 'Durchführung', 'description' => 'Professionelle Umsetzung durch unser Team'],
            ['title' => 'Abschluss', 'description' => 'Finale Kontrolle und Übergabe']
        ];
    }

    /**
     * Get default FAQs based on service slug
     */
    private function getDefaultFAQs($serviceSlug): array
    {
        $faqMap = [
            'umzuege' => [
                ['question' => 'Wie lange im Voraus sollte ich meinen Umzug buchen?', 'answer' => 'Wir empfehlen, Ihren Umzug mindestens 2-4 Wochen im Voraus zu buchen, besonders während der Hauptsaison (Mai-September). Kurzfristige Buchungen sind nach Verfügbarkeit möglich.'],
                ['question' => 'Was kostet ein Umzug?', 'answer' => 'Die Kosten hängen von verschiedenen Faktoren ab: Entfernung, Umfang des Umzugsguts, Stockwerk, zusätzliche Services. Wir erstellen Ihnen gerne ein kostenloses, unverbindliches Angebot nach einer Besichtigung.'],
                ['question' => 'Muss ich meine Sachen selbst verpacken?', 'answer' => 'Nein, wir bieten einen professionellen Verpackungsservice an. Sie können aber auch selbst verpacken, um Kosten zu sparen. Wir stellen Ihnen gerne Verpackungsmaterial zur Verfügung.'],
                ['question' => 'Sind meine Sachen während des Umzugs versichert?', 'answer' => 'Ja, alle Umzüge sind standardmäßig versichert. Bei wertvollen Gegenständen empfehlen wir eine zusätzliche Versicherung, die wir für Sie organisieren können.'],
                ['question' => 'Wie läuft ein Umzug mit Ihnen ab?', 'answer' => 'Nach der Besichtigung und Angebotsannahme planen wir gemeinsam alle Details. Am Umzugstag kommt unser Team pünktlich, verpackt (falls gewünscht), lädt alles ein, transportiert es zum neuen Ort und stellt alles auf. Optional übernehmen wir auch Montagearbeiten.']
            ],
            'transport' => [
                ['question' => 'Welche Gegenstände können Sie transportieren?', 'answer' => 'Wir transportieren nahezu alles: Möbel, Haushaltsgeräte, Klaviere, schwere Lasten, empfindliche Gegenstände. Sprechen Sie uns einfach an!'],
                ['question' => 'Wie weit im Voraus muss ich buchen?', 'answer' => 'Für kurzfristige Transporte reichen oft 1-3 Tage Vorlauf. Bei größeren oder komplexen Transporten empfehlen wir 1-2 Wochen.'],
                ['question' => 'Sind die Gegenstände versichert?', 'answer' => 'Ja, alle Transporte sind versichert. Bei besonders wertvollen Gegenständen kann eine Zusatzversicherung sinnvoll sein.'],
                ['question' => 'Wie berechnen sich die Kosten?', 'answer' => 'Die Kosten richten sich nach Entfernung, Größe/Gewicht der Gegenstände und dem Aufwand. Wir erstellen Ihnen gerne ein individuelles Angebot.']
            ],
            'entruempelung' => [
                ['question' => 'Was kostet eine Entrümpelung?', 'answer' => 'Die Kosten hängen von der Größe des Objekts, der Menge des Materials und der Zugänglichkeit ab. Nach einer Besichtigung erstellen wir Ihnen ein faires Festpreisangebot.'],
                ['question' => 'Wie lange dauert eine Entrümpelung?', 'answer' => 'Je nach Umfang dauert eine Entrümpelung zwischen einigen Stunden und mehreren Tagen. Bei der Besichtigung können wir Ihnen eine genauere Zeitangabe machen.'],
                ['question' => 'Was passiert mit dem entrümpelten Material?', 'answer' => 'Wir entsorgen alles fachgerecht und umweltfreundlich. Wertvolle Gegenstände können nach Absprache verkauft werden, der Erlös wird Ihnen gutgeschrieben.'],
                ['question' => 'Müssen wir bei der Entrümpelung anwesend sein?', 'answer' => 'Das ist nicht zwingend erforderlich. Nach vorheriger Absprache können wir die Entrümpelung auch in Ihrer Abwesenheit durchführen.']
            ],
            'aufloesung' => [
                ['question' => 'Was ist der Unterschied zur Entrümpelung?', 'answer' => 'Bei einer Haushaltsauflösung wird der komplette Haushalt systematisch aufgelöst, sortiert und dokumentiert. Dies ist oft bei Erbfällen oder Umzügen ins Pflegeheim notwendig.'],
                ['question' => 'Wie gehen Sie mit wertvollen Gegenständen um?', 'answer' => 'Wertgegenstände werden separat erfasst und können nach Wunsch verkauft, gespendet oder an Sie übergeben werden. Wir arbeiten dabei transparent und dokumentieren alles.'],
                ['question' => 'Übernehmen Sie auch die Endreinigung?', 'answer' => 'Ja, auf Wunsch führen wir nach der Auflösung eine professionelle Endreinigung durch, sodass die Immobilie übergabefertig ist.'],
                ['question' => 'Wie lange dauert eine Haushaltsauflösung?', 'answer' => 'Je nach Größe des Haushalts benötigen wir 1-5 Tage. Eine genaue Zeitangabe erhalten Sie nach der Besichtigung.']
            ]
        ];
        
        return $faqMap[$serviceSlug] ?? [
            ['question' => 'Wie kann ich einen Termin vereinbaren?', 'answer' => 'Kontaktieren Sie uns telefonisch oder über unser Kontaktformular. Wir melden uns zeitnah bei Ihnen zurück.'],
            ['question' => 'In welchen Regionen sind Sie tätig?', 'answer' => 'Wir sind hauptsächlich im Raum Aschaffenburg und Umgebung tätig. Sprechen Sie uns für andere Regionen gerne an.'],
            ['question' => 'Bieten Sie auch Notfall-Services an?', 'answer' => 'Ja, für dringende Fälle versuchen wir kurzfristige Termine zu ermöglichen. Rufen Sie uns einfach an!']
        ];
    }
}
