<?php

namespace DSAllround\Views;
use Exception;
use PDO;

class Datenschutz extends Page
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
            $page = new Datenschutz();
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
            
            while ($result = $stmt->fetch()) {
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
            error_log("Datenschutz: Database error loading settings: " . $e->getMessage());
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
        $requiredSettings = ['site_name', 'contact_address', 'contact_phone', 'contact_email'];
        foreach ($requiredSettings as $key) {
            if (!isset($settings[$key])) {
                error_log("Datenschutz: Missing setting key: $key");
                $settings[$key] = '';
            }
        }
        
        // Escape values for safe HTML output
        $site_name = htmlspecialchars($settings['site_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $contact_address = htmlspecialchars($settings['contact_address'] ?? '', ENT_QUOTES, 'UTF-8');
        $contact_phone = htmlspecialchars($settings['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $contact_email = htmlspecialchars($settings['contact_email'] ?? '', ENT_QUOTES, 'UTF-8');

        echo <<< HTML
            <main class="law-main">
            <section class="impressum-content">
            <div class="container">
                <h1>Datenschutzerklärung</h1>
                <div class="impressum-section">
                    <h2>1. Allgemeine Hinweise</h2>
                    <p>
                        Der Schutz Ihrer persönlichen Daten ist uns ein wichtiges Anliegen. In dieser Datenschutzerklärung informieren wir Sie darüber, welche Daten wir erheben, wie wir diese nutzen und welche Rechte Ihnen in Bezug auf Ihre Daten zustehen.
                    </p>
                </div>
                <div class="impressum-section">
                    <h2>2. Verantwortliche Stelle</h2>
                    <p>
                        Verantwortlich für die Datenverarbeitung auf dieser Website ist:<br>
                        <strong>{$site_name}</strong><br>
                        Daniel Skopek<br>
                        {$contact_address}<br>
                        E-Mail: <a href="mailto:{$contact_email}">{$contact_email}</a>
                    </p>
                </div>
                <div class="impressum-section">
                    <h2>3. Datenerfassung auf unserer Website</h2>
                    <h3>3.1 Kontaktformular und Serviceanfragen</h3>
                    <p>
                        Wenn Sie unser Kontaktformular nutzen oder eine Serviceanfrage über unsere Fragebögen stellen, erheben wir folgende personenbezogene Daten:
                    </p>
                    <ul>
                        <li>Vor- und Nachname</li>
                        <li>E-Mail-Adresse</li>
                        <li>Telefonnummer</li>
                        <li>Adresse (je nach angefragter Dienstleistung)</li>
                        <li>Details zur gewünschten Dienstleistung (z. B. Art der Entrümpelung, Gartenarbeit, Umzug, etc.)</li>
                        <li>Weitere freiwillige Angaben zur Präzisierung Ihrer Anfrage</li>
                    </ul>
                    <p>
                        Die Datenverarbeitung erfolgt, um Ihre Anfrage zu bearbeiten, Sie zu kontaktieren und Ihnen ein individuelles Angebot zu erstellen. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO (Verarbeitung zur Durchführung vorvertraglicher Maßnahmen) sowie Art. 6 Abs. 1 lit. a DSGVO (Einwilligung durch die Nutzung des Formulars).
                    </p>
                    <h3>3.2 Server-Log-Dateien</h3>
                    <p>
                        Bei jedem Aufruf unserer Website werden automatisch Informationen erfasst, die Ihr Browser an unseren Server übermittelt:
                    </p>
                    <ul>
                        <li>Browsertyp und -version</li>
                        <li>Verwendetes Betriebssystem</li>
                        <li>Referrer URL (zuvor besuchte Seite)</li>
                        <li>Hostname des zugreifenden Rechners</li>
                        <li>Uhrzeit der Serveranfrage</li>
                        <li>IP-Adresse</li>
                    </ul>
                    <p>
                        Diese Daten werden nicht mit anderen Datenquellen zusammengeführt und dienen ausschließlich zur Gewährleistung der Funktionalität und Sicherheit unserer Website. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse).
                    </p>
                </div>
                <div class="impressum-section">
                    <h2>4. Speicherdauer der Daten</h2>
                    <p>
                        Wir speichern personenbezogene Daten nur so lange, wie dies für die Bearbeitung Ihrer Anfrage, Bestellung oder Reservierung erforderlich ist. Sofern keine gesetzlichen Aufbewahrungsfristen bestehen, werden die Daten nach Erledigung der Anfrage oder vollständiger Vertragserfüllung gelöscht.
                    </p>
                </div>
                <div class="impressum-section">
                    <h2>5. Weitergabe von Daten</h2>
                    <p>
                        Eine Weitergabe Ihrer persönlichen Daten an Dritte erfolgt nur, wenn dies zur Vertragsabwicklung notwendig ist (z. B. an Lieferdienste), Sie ausdrücklich eingewilligt haben oder eine gesetzliche Verpflichtung besteht.
                    </p>
                </div>
                <div class="impressum-section">
                    <h2>6. Ihre Rechte</h2>
                    <p>Sie haben jederzeit das Recht auf:</p>
                    <ul>
                        <li>Auskunft über Ihre gespeicherten personenbezogenen Daten</li>
                        <li>Berichtigung unrichtiger Daten</li>
                        <li>Löschung Ihrer Daten</li>
                        <li>Einschränkung der Verarbeitung</li>
                        <li>Datenübertragbarkeit</li>
                        <li>Widerspruch gegen die Verarbeitung</li>
                    </ul>
                    <p>
                        Um eines dieser Rechte geltend zu machen, wenden Sie sich bitte an uns unter: <a href="mailto:{$contact_email}">{$contact_email}</a>.
                    </p>
                </div>
                <div class="impressum-section">
                    <h2>7. Externes Hosting</h2>
                    <p>
                        Unsere Website wird bei einem externen Dienstleister (Hoster) gehostet. Die personenbezogenen Daten, die auf dieser Website erfasst werden, werden auf den Servern des Hosters gespeichert. Rechtsgrundlage für die Datenverarbeitung ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse).
                    </p>
                </div>
                <div class="impressum-section">
                    <h2>8. Änderungen dieser Datenschutzerklärung</h2>
                    <p>
                        Wir behalten uns das Recht vor, diese Datenschutzerklärung jederzeit zu ändern, um sie an aktuelle rechtliche Anforderungen oder Änderungen unserer Dienstleistungen anzupassen. Die neue Datenschutzerklärung gilt dann bei Ihrem nächsten Besuch.
                    </p>
                </div>
            </div>
        </section>
        HTML;
    }
}

Datenschutz::main();


