<?php

namespace DSAllround\Views;
use Exception;
class Home extends Page
{
    /**
     * Properties
     */

    /**
     * Instantiates members (to be defined above).
     * Calls the constructor of the parent i.e. page class.
     * So, the database connection is established.
     * @throws Exception
     */
    protected function __construct()
    {
        parent::__construct();
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
        // Generiere ein zufälliges Token und speichere es in der Sitzung
        if (!isset($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }
        try {
            $page = new Home();
            $page->generateView();
        } catch (Exception $e) {
            //header("Content-type: text/plain; charset=UTF-8");
            header("Content-type: text/html; charset=UTF-8");
            echo $e->getMessage();
        }
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
        $this->generatePageHeader('DS-Allroundservice'); //to do: set optional parameters

        //$this->generateNav();
        $this->generateMainBody();
        //$this->generatePageFooter();
    }

    private function generateMainBody(){
        $this->generateHeroSection();
        $this->generateAboutSection();
        $this->generateServiceSection();
        $this->generateWhyUsSection();
        $this->generatePricingSection();
        $this->generateFooter();
    }

    protected function additionalMetaData(): void
    {
        // SEO Meta Tags
        echo <<< HTML
            <meta name="description" content="DS-Allroundservice - Ihr zuverlässiger Partner für Umzüge, Transport, Entrümpelung und Hausmeisterdienste. Professionell, schnell und preiswert in Deutschland.">
            <meta name="keywords" content="Umzug, Transport, Entrümpelung, Hausmeister, Wohnungsauflösung, Möbeltransport, Umzugsservice, Kleinumzug, Privatumzug, Firmenumzug">
            <meta name="author" content="DS-Allroundservice">
            <meta name="robots" content="index, follow">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            
            <!-- Open Graph / Facebook -->
            <meta property="og:type" content="website">
            <meta property="og:url" content="https://ds-allroundservice.de/">
            <meta property="og:title" content="DS-Allroundservice - Umzug, Transport & Entrümpelung">
            <meta property="og:description" content="Professionelle Dienstleistungen rund um Umzug, Transport und Hausmeisterdienste. Zuverlässig, schnell und preiswert.">
            <meta property="og:image" content="https://ds-allroundservice.de/public/assets/img/logo.png">
            <meta property="og:locale" content="de_DE">
            <meta property="og:site_name" content="DS-Allroundservice">
            
            <!-- Twitter -->
            <meta property="twitter:card" content="summary_large_image">
            <meta property="twitter:url" content="https://ds-allroundservice.de/">
            <meta property="twitter:title" content="DS-Allroundservice - Umzug, Transport & Entrümpelung">
            <meta property="twitter:description" content="Professionelle Dienstleistungen rund um Umzug, Transport und Hausmeisterdienste. Zuverlässig, schnell und preiswert.">
            <meta property="twitter:image" content="https://ds-allroundservice.de/public/assets/img/logo.png">
            
            <!-- Structured Data (JSON-LD) -->
            <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "LocalBusiness",
                "name": "DS-Allroundservice",
                "description": "Professionelle Dienstleistungen rund um Umzug, Transport und Hausmeisterdienste",
                "url": "https://ds-allroundservice.de",
                "logo": "https://ds-allroundservice.de/public/assets/img/logo.png",
                "image": "https://ds-allroundservice.de/public/assets/img/umzug.webp",
                "priceRange": "€€",
                "telephone": "+49-XXX-XXXXXXX",
                "address": {
                    "@type": "PostalAddress",
                    "addressCountry": "DE"
                },
                "geo": {
                    "@type": "GeoCoordinates"
                },
                "openingHours": "Mo-Fr 08:00-18:00, Sa 09:00-15:00",
                "serviceArea": {
                    "@type": "Country",
                    "name": "Deutschland"
                },
                "hasOfferCatalog": {
                    "@type": "OfferCatalog",
                    "name": "Dienstleistungen",
                    "itemListElement": [
                        {
                            "@type": "Offer",
                            "itemOffered": {
                                "@type": "Service",
                                "name": "Umzugsservice",
                                "description": "Professionelle Umzüge für Privat und Gewerbe"
                            }
                        },
                        {
                            "@type": "Offer",
                            "itemOffered": {
                                "@type": "Service",
                                "name": "Transportservice",
                                "description": "Sicherer Transport aller Art"
                            }
                        },
                        {
                            "@type": "Offer",
                            "itemOffered": {
                                "@type": "Service",
                                "name": "Entrümpelung",
                                "description": "Fachgerechte Entrümpelung und Entsorgung"
                            }
                        },
                        {
                            "@type": "Offer",
                            "itemOffered": {
                                "@type": "Service",
                                "name": "Hausmeisterdienste",
                                "description": "Zuverlässige Hausmeister- und Wartungsdienste"
                            }
                        }
                    ]
                }
            }
            </script>
            
            <!-- Canonical URL -->
            <link rel="canonical" href="https://ds-allroundservice.de/">
            
            <!-- Preconnect for performance -->
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            
            <!-- Fonts and Stylesheets -->
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" type="text/css" href="public/assets/css/home.css"/>
            
            <!-- Scripts -->
            <script src="public/assets/js/home-behavior.js" defer></script>
            <script src="public/assets/js/sticky-header.js" defer></script> 
        HTML;
    }

    private function generateHeroSection(){
        echo <<< HTML
            <section class="hero-section">
              <header class="hero-header">
                <div class="logo-area">
                  <img src="public/assets/img/logo.png" alt="LOGO" class="logo" />
                  <div class="company-info">
                    <div class="company-name">DS-Allroundservice</div>
                    <div class="company-slogan">Zuverlässig. Schnell. Preiswert.</div>
                  </div>
                </div>
                <div class="menu-toggle" aria-label="Menü">&#9776;</div>
                <nav class="desktop-menu">
                  <a href="#home">Home</a>
                  <a href="#about">Über uns</a>
                  <a href="#services">Leistungen</a>
                  <a href="#pricing">Preise</a>
                </nav>
                <nav class="mobile-menu" id="mobile-menu">
                  <a href="#home">Home</a>
                  <a href="#about">Über uns</a>
                  <a href="#services">Leistungen</a>
                  <a href="#pricing">Preise</a>
                </nav>
              </header>
            
              <div class="hero-content">
                <div class="hero-text">
                  <h1 class="hero-title">Ihr Partner für Umzug & Transport</h1>
                  <p class="hero-subtitle">Professionelle Dienstleistungen rund um Umzug, Transport und Hausmeisterdienste</p>
                  <div class="hero-actions">
                    <a href="#services"><button class="btn btn-primary">Unsere Leistungen</button></a>
                  </div>
                  <div class="hero-features">
                    <div class="feature-item">
                      <span class="feature-icon">✓</span>
                      <span>Unverbindliche Beratung</span>
                    </div>
                    <div class="feature-item">
                      <span class="feature-icon">✓</span>
                      <span>Schnell & Zuverlässig</span>
                    </div>
                    <div class="feature-item">
                      <span class="feature-icon">✓</span>
                      <span>Vollversichert</span>
                    </div>
                    <div class="feature-item">
                      <span class="feature-icon">✓</span>
                      <span>Faire Preise</span>
                    </div>
                  </div>
                </div>
              </div>
            </section>
        HTML;
    }

    private function generateAboutSection() {
        echo <<< HTML
            <section class="about-section" id="about">
              <div class="container">
                <div class="about-content">
                  <div class="about-text">
                    <h2 class="section-title">Über DS-Allroundservice</h2>
                    <p class="section-description">
                        Unser Anspruch: zuverlässig, flexibel und persönlich – damit alles von Anfang bis Ende stressfrei verläuft.
                        Mit moderner Arbeitsweise und viel Engagement möchten wir Ihr vertrauensvoller Partner werden.
                    </p>
                    <div class="about-stats">
                      <div class="stat-item">
                        <div class="stat-number">200+</div>
                        <div class="stat-label">Zufriedene Kunden</div>
                      </div>
                      <div class="stat-item">
                        <div class="stat-number">2</div>
                        <div class="stat-label">Jahre Erfahrung</div>
                      </div>
                      <div class="stat-item">
                        <span class="stat-number">24</span>&nbsp;&nbsp;&nbsp;<span class="stat-number">7</span>
                        <div class="stat-label">Erreichbar</div>
                      </div>
                    </div>
                  </div>
                  <div class="about-image">
                    <img src="public/assets/img/umzug.webp" alt="Unser Team bei der Arbeit" />
                  </div>
                </div>
              </div>
            </section>
        HTML;
    }

    private function generateServiceSection() {
        // Load services dynamically from database
        $services = $this->loadServices();
        
        echo <<<HTML
            <section class="services-section" id="services">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">Unsere Leistungen</h2>
                        <p class="section-description">Von A bis Z - wir kümmern uns um Ihre Bedürfnisse</p>
                    </div>
                    <div class="services-grid">
HTML;

        foreach ($services as $service) {
            $slug = htmlspecialchars($service['slug']);
            $name = htmlspecialchars($service['name']);
            $title = htmlspecialchars($service['title']);
            $description = htmlspecialchars($service['description']);
            $icon = htmlspecialchars($service['icon'] ?? 'default-icon.png');
            $color = htmlspecialchars($service['color'] ?? '#007cba');
            
            // Parse features if available
            $features = $this->getServiceFeatures($service['slug']);
            $featuresHtml = '';
            foreach ($features as $feature) {
                $featuresHtml .= '<li>' . htmlspecialchars($feature) . '</li>';
            }

            echo <<<HTML
                        <a href="/{$slug}" class="service-card" style="--service-color: {$color}">
                            <div class="service-icon">
                                <img src="public/assets/img/{$icon}" alt="{$name} Service">
                            </div>
                            <div class="service-content">
                                <h3 class="service-title">{$name}</h3>
                                <p class="service-description">{$description}</p>
                                <ul class="service-features">
                                    {$featuresHtml}
                                </ul>
                            </div>
                        </a>
HTML;
        }

        echo <<<HTML
                    </div>
                </div>
            </section>
HTML;
    }

    /**
     * Load services from database
     */
    private function loadServices(): array {
        try {
            $stmt = $this->_database->prepare(
                "SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Error loading services for home page: " . $e->getMessage());
            // Return default services as fallback
            return [
                ['slug' => 'umzuege', 'name' => 'Umzüge', 'title' => 'Professionelle Umzüge', 'description' => 'Stressfrei von A nach B', 'icon' => 'umzug.webp', 'color' => '#007cba'],
                ['slug' => 'transport', 'name' => 'Transport', 'title' => 'Klein-Transporte', 'description' => 'Sicherer Transport aller Art', 'icon' => 'shipping.png', 'color' => '#28a745'],
                ['slug' => 'entruempelung', 'name' => 'Entrümpelung', 'title' => 'Professionelle Entrümpelung', 'description' => 'Fachgerechte Entsorgung', 'icon' => 'trash.png', 'color' => '#dc3545'],
                ['slug' => 'aufloesung', 'name' => 'Wohnungsauflösung', 'title' => 'Komplette Auflösung', 'description' => 'Diskret und professionell', 'icon' => 'house.png', 'color' => '#fd7e14']
            ];
        }
    }

    /**
     * Load services with pricing data from database
     */
    private function loadServicesWithPricing(): array {
        try {
            $stmt = $this->_database->prepare(
                "SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Error loading services with pricing for home page: " . $e->getMessage());
            // Return default services as fallback
            return [
                ['slug' => 'umzuege', 'name' => 'Umzüge', 'pricing_data' => null],
                ['slug' => 'transport', 'name' => 'Transport', 'pricing_data' => null],
                ['slug' => 'entruempelung', 'name' => 'Entrümpelung', 'pricing_data' => null],
                ['slug' => 'aufloesung', 'name' => 'Wohnungsauflösung', 'pricing_data' => null]
            ];
        }
    }

    /**
     * Get service-specific features for display
     */
    private function getServiceFeatures($serviceSlug): array {
        $featureMap = [
            'umzuege' => ['Komplette Umzugsabwicklung', 'Ein- und Auspackservice', 'Möbelmontage'],
            'transport' => ['Möbeltransport', 'Klaviertransport', 'Geräteverlegung'],
            'entruempelung' => ['Keller & Dachboden', 'Messie-Wohnungen', 'Fachgerechte Entsorgung'],
            'aufloesung' => ['Komplette Haushaltsauflösung', 'Wertgegenstand-Sortierung', 'Renovierungsarbeiten']
        ];
        
        return $featureMap[$serviceSlug] ?? ['Professioneller Service', 'Faire Preise', 'Zuverlässige Ausführung'];
    }

    /**
     * Get service-specific features for pricing display
     */
    private function getServicePricingFeatures($serviceSlug): array {
        $featureMap = [
            'umzuege' => ['Demontage & Montage', 'Verpackungsmaterial', 'Vollversicherung', 'Komplettreinigung optional'],
            'transport' => ['Sicherer Transport', 'Professionelle Ladehilfen', 'Flexible Zeiten', 'Deutschlandweit'],
            'entruempelung' => ['Fachgerechte Entsorgung', 'Wertstoff-Trennung', 'Endreinigung möglich', 'Kostenlose Besichtigung'],
            'aufloesung' => ['Komplettservice', 'Wertsachen-Sortierung', 'Renovierung optional', 'Kostenvoranschlag']
        ];
        
        return $featureMap[$serviceSlug] ?? ['Professioneller Service', 'Faire Preise', 'Zuverlässige Ausführung', 'Kostenlose Beratung'];
    }

    private function generateWhyUsSection() {
        echo <<< HTML
            <section class="why-us-section">
              <div class="container">
                <div class="why-us-content">
                  <div class="why-us-text">
                    <h2 class="section-title">Warum DS-Allroundservice?</h2>
                    <p class="section-description">
                      Wir verstehen, dass Umzüge und Transporte stressig sein können. Deshalb setzen wir alles daran, 
                      Ihnen den bestmöglichen Service zu bieten und jeden Auftrag mit größter Sorgfalt zu behandeln.
                    </p>
                    <div class="benefits-grid">
                      <div class="benefit-item">
                        <div class="benefit-icon">🛡️</div>
                        <div class="benefit-content">
                          <h4>Vollversichert</h4>
                          <p>Alle Transporte sind vollumfänglich versichert</p>
                        </div>
                      </div>
                      <div class="benefit-item">
                        <div class="benefit-icon">⚡</div>
                        <div class="benefit-content">
                          <h4>Schnell & Flexibel</h4>
                          <p>Kurzfristige Termine und flexible Abwicklung</p>
                        </div>
                      </div>
                      <div class="benefit-item">
                        <div class="benefit-icon">💰</div>
                        <div class="benefit-content">
                          <h4>Faire Preise</h4>
                          <p>Transparente Kostenstruktur ohne versteckte Kosten</p>
                        </div>
                      </div>
                      <div class="benefit-item">
                        <div class="benefit-icon">👥</div>
                        <div class="benefit-content">
                          <h4>Erfahrenes Team</h4>
                          <p>Professionelle Mitarbeiter mit langjähriger Erfahrung</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>
        HTML;
    }

    private function generatePricingSection() {
        // Load services with pricing data from database
        $services = $this->loadServicesWithPricing();
        
        echo <<< HTML
            <section class="pricing-section" id="pricing">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">Unsere Preise</h2>
                        <p class="section-description">Faire und transparente Preisgestaltung für alle Dienstleistungen</p>
                    </div>
                    <div class="pricing-grid">
HTML;

        foreach ($services as $index => $service) {
            $name = htmlspecialchars($service['name']);
            $pricingData = $service['pricing_data'] ? json_decode($service['pricing_data'], true) : [];
            
            // Determine if this is the featured card (first service with multiple prices)
            $isFeatured = $index === 0 && count($pricingData) > 1;
            $cardClass = $isFeatured ? 'pricing-card featured' : 'pricing-card';
            
            echo <<<HTML
                        <div class="{$cardClass}">
                            <div class="pricing-header">
                                <h3 class="pricing-title">{$name}</h3>
HTML;
            
            if ($isFeatured) {
                echo '<div class="pricing-badge">Beliebt</div>';
            }
            
            echo <<<HTML
                            </div>
                            <div class="pricing-content">
HTML;
            
            // Display pricing based on number of prices
            if (count($pricingData) > 1) {
                // Multiple prices - display as list
                echo '<div class="price-list">';
                foreach ($pricingData as $price) {
                    $description = htmlspecialchars($price['description']);
                    $value = number_format($price['value'], 0, ',', '.');
                    $unit = htmlspecialchars($price['unit']);
                    
                    echo <<<HTML
                                    <div class="price-item">
                                        <span class="apartment-type">{$description}</span>
                                        <span class="price">ab {$value}{$unit}</span>
                                    </div>
HTML;
                }
                echo '</div>';
            } elseif (count($pricingData) === 1) {
                // Single price - display large
                $price = $pricingData[0];
                $value = number_format($price['value'], 0, ',', '.');
                $unit = htmlspecialchars($price['unit']);
                $description = htmlspecialchars($price['description']);
                
                echo <<<HTML
                                <div class="price-main">ab {$value}{$unit}</div>
                                <div class="price-subtitle">{$description}</div>
HTML;
            } else {
                // No pricing data - show individual prices
                echo '<div class="price-main">Individuelle Preise</div>';
            }
            
            // Add service-specific features
            $features = $this->getServicePricingFeatures($service['slug']);
            echo '<div class="price-features">';
            foreach ($features as $feature) {
                echo '<div class="feature">✓ ' . htmlspecialchars($feature) . '</div>';
            }
            echo '</div>';
            
            echo <<<HTML
                            </div>
                        </div>
HTML;
        }

        echo <<<HTML
                    </div>
                    <div class="pricing-note">
                        <p><strong>Hinweis:</strong> Alle Preise verstehen sich zzgl. MwSt. Die finalen Kosten werden nach einer kostenlosen Besichtigung vor Ort kalkuliert.</p>
                    </div>
                </div>
            </section>
HTML;
    }


}

Home::main();


