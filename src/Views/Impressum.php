<?php

namespace DSAllround\Views;
use Exception;
class Impressum extends Page
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
            $page = new Impressum();
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
        $this->generateContent();
        $this->generateFooter();
    }

    protected function additionalMetaData(): void
    {
        //Links for css or js
        echo <<< HTML
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" type="text/css" href="public/assets/css/home.css"/>
            <link rel="stylesheet" type="text/css" href="public/assets/css/law.css"/>
            <script src="public/assets/js/law-behavior.js" defer></script>
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
                  <a href="/">Home</a>
                  <a href="/#about">Über uns</a>
                  <a href="/#services">Leistungen</a>
                  <a href="/#pricing">Preise</a>
                </nav>
                <nav class="mobile-menu" id="mobile-menu">
                  <a href="/">Home</a>
                  <a href="/#about">Über uns</a>
                  <a href="/#services">Leistungen</a>
                  <a href="/#pricing">Preise</a>
                </nav>
              </header>
        HTML;
    }

    private function generateContent() : void {
        $settings = [];
        try {
            $stmt = $this->_database->prepare(
                        "SELECT setting_key, setting_value, setting_type 
                         FROM settings"
                    );
            $stmt->execute();
            
            // Fetch all results at once - MySQL/PDO compatible
            while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $key = $result['setting_key'];
                $value = $result['setting_value'];
                $type = $result['setting_type'];
                
                // Konvertiere den Wert basierend auf dem Typ
                if ($type === 'json') {
                    $value = json_decode($value, true);
                } elseif (in_array($type, ['string', 'int', 'integer', 'float', 'double', 'bool', 'boolean', 'array', 'object', 'null'])) {
                    settype($value, $type);
                }
                // Für andere Typen bleibt der Wert als String
                
                $settings[$key] = $value;
            }
        } catch (\PDOException $e) {
            error_log("Impressum: Database error loading settings: " . $e->getMessage());
            // Fallback values
            $settings = [
                'site_name' => 'DS Allroundservices',
                'contact_address' => 'Darmstädter Straße 0 63741 Aschaffenburg',
                'contact_phone' => '+49 6021 123456',
                'contact_email' => 'info@ds-allroundservice.de',
                'company_vat_id' => 'DE0123456789'
            ];
        }
        
        // Sicherstellen, dass alle benötigten Settings existieren
        $requiredSettings = ['site_name', 'contact_address', 'contact_phone', 'contact_email', 'company_vat_id'];
        foreach ($requiredSettings as $key) {
            if (!isset($settings[$key])) {
                error_log("Impressum: Missing setting key: $key");
                $settings[$key] = '';
            }
        }
        
        // Escape values for safe HTML output
        $site_name = htmlspecialchars($settings['site_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $contact_address = htmlspecialchars($settings['contact_address'] ?? '', ENT_QUOTES, 'UTF-8');
        $contact_phone = htmlspecialchars($settings['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $contact_email = htmlspecialchars($settings['contact_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $company_vat_id = htmlspecialchars($settings['company_vat_id'] ?? '', ENT_QUOTES, 'UTF-8');
        
        // Settings als HTML ausgeben
        echo <<< HTML
        <section class="impressum-content">
            <div class="container">
                <h1>Impressum</h1>
                
                <div class="impressum-section">
                    <h2>Angaben gemäß § 5 TMG</h2>
                    <p>
                        <strong>{$site_name}</strong><br>
                        Daniel Skopek<br>
                        {$contact_address}<br>
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>Kontakt</h2>
                    <p>
                        <strong>Telefon:</strong> {$contact_phone}<br>
                        <strong>E-Mail:</strong> <a href="mailto:{$contact_email}">{$contact_email}</a>
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>Umsatzsteuer-ID</h2>
                    <p>
                        Umsatzsteuer-Identifikationsnummer gemäß §27 a Umsatzsteuergesetz:<br>
                        {$company_vat_id}
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
                    <p>
                        Daniel Skopek<br>
                        {$contact_address}<br>
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>Haftungsausschluss</h2>
                    
                    <h3>Haftung für Inhalte</h3>
                    <p>
                        Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den 
                        allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht 
                        verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen 
                        zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.
                    </p>
                    <p>
                        Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen 
                        Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt 
                        der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden 
                        Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.
                    </p>
                    
                    <h3>Haftung für Links</h3>
                    <p>
                        Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. 
                        Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der 
                        verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich.
                    </p>
                    <p>
                        Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. 
                        Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar. Eine permanente inhaltliche 
                        Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht 
                        zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen.
                    </p>
                    
                    <h3>Urheberrecht</h3>
                    <p>
                        Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen 
                        Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der 
                        Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.
                    </p>
                    <p>
                        Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet. 
                        Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte 
                        Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem 
                        auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei 
                        Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.
                    </p>
                </div>
            </div>
        </section>
        HTML;
    }
}

Impressum::main();


