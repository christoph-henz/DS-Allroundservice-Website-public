<?php

namespace DSAllround\Views;
use Exception;
use PDO;

class AGB extends Page
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
            $page = new AGB();
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
        $stmt = $this->_database->prepare(
                    "SELECT setting_key, setting_value, setting_type 
                     FROM settings"
                );
        $stmt->execute();
        $result = $stmt->fetch();
        while ($result) {
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
            $result = $stmt->fetch();
        }
        
        // Settings als HTML ausgeben
        echo <<< HTML
        <section class="impressum-content">
            <div class="container">
                <h1>Allgemeine Geschäftsbedingungen (AGB)</h1>
                
                <div class="impressum-section">
                    <h2>1. Geltungsbereich</h2>
                    <p>
                        Diese Allgemeinen Geschäftsbedingungen (AGB) gelten für alle Verträge zwischen {$settings['site_name']}, 
                        Daniel Skopek (nachfolgend „Auftragnehmer" genannt) und Kunden (nachfolgend „Auftraggeber" genannt) 
                        über die Erbringung von Dienstleistungen im Bereich Entrümpelung, Gartenarbeit, Umzüge und weiteren 
                        Allround-Services.
                    </p>
                    <p>
                        Abweichende Bedingungen des Auftraggebers werden nicht anerkannt, es sei denn, der Auftragnehmer 
                        stimmt ihrer Geltung ausdrücklich schriftlich zu.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>2. Vertragsschluss</h2>
                    <p>
                        Der Vertrag kommt durch die Annahme des vom Auftragnehmer erstellten Angebots durch den Auftraggeber 
                        zustande. Die Annahme kann mündlich, schriftlich, per E-Mail oder durch konkludentes Handeln erfolgen.
                    </p>
                    <p>
                        Anfragen über das Kontaktformular oder die Fragebögen auf der Website stellen lediglich eine 
                        unverbindliche Anfrage dar. Der Auftragnehmer erstellt daraufhin ein individuelles Angebot.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>3. Leistungsumfang</h2>
                    <p>
                        Der Umfang der zu erbringenden Leistungen ergibt sich aus dem jeweiligen Angebot bzw. der 
                        Auftragsbestätigung. Eventuelle Änderungen oder Ergänzungen bedürfen der schriftlichen Vereinbarung.
                    </p>
                    <p>
                        Der Auftragnehmer ist berechtigt, Teilleistungen zu erbringen, sofern dies dem Auftraggeber zumutbar ist.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>4. Preise und Zahlungsbedingungen</h2>
                    <p>
                        Die Preise richten sich nach dem jeweiligen Angebot und verstehen sich inklusive der gesetzlichen 
                        Mehrwertsteuer, sofern nicht anders angegeben.
                    </p>
                    <p>
                        Die Zahlung ist, sofern nicht anders vereinbart, nach vollständiger Leistungserbringung fällig. 
                        Die Rechnung kann bar, per Überweisung oder nach Vereinbarung auch in anderen Formen beglichen werden.
                    </p>
                    <p>
                        Bei Zahlungsverzug werden Verzugszinsen in Höhe von 5 Prozentpunkten über dem Basiszinssatz berechnet. 
                        Die Geltendmachung weiterer Verzugsschäden bleibt vorbehalten.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>5. Termine und Fristen</h2>
                    <p>
                        Vereinbarte Termine sind für beide Vertragsparteien verbindlich. Bei Verhinderung ist der Auftraggeber 
                        verpflichtet, den Auftragnehmer mindestens 48 Stunden vor dem vereinbarten Termin zu informieren.
                    </p>
                    <p>
                        Bei verspäteter Absage oder Nichterscheinen des Auftraggebers ohne rechtzeitige Benachrichtigung 
                        behält sich der Auftragnehmer vor, eine Ausfallpauschale in Höhe von 50% des vereinbarten 
                        Auftragswertes zu berechnen.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>6. Pflichten des Auftraggebers</h2>
                    <p>
                        Der Auftraggeber verpflichtet sich:
                    </p>
                    <ul>
                        <li>Den Auftragnehmer über besondere Gegebenheiten oder Risiken am Leistungsort zu informieren</li>
                        <li>Für freien Zugang zu den zu bearbeitenden Bereichen zu sorgen</li>
                        <li>Bei Bedarf erforderliche Genehmigungen (z. B. Halteverbotszonen) einzuholen</li>
                        <li>Wertgegenstände und wichtige Dokumente vor Arbeitsbeginn zu sichern</li>
                        <li>Für ausreichende Strom- und Wasserversorgung zu sorgen, sofern erforderlich</li>
                    </ul>
                </div>
                
                <div class="impressum-section">
                    <h2>7. Haftung</h2>
                    <p>
                        Der Auftragnehmer haftet für Schäden nur bei Vorsatz oder grober Fahrlässigkeit. Dies gilt nicht 
                        für Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit.
                    </p>
                    <p>
                        Für leicht fahrlässig verursachte Sach- und Vermögensschäden haftet der Auftragnehmer nur bei 
                        Verletzung einer wesentlichen Vertragspflicht. In diesem Fall ist die Haftung auf den 
                        vertragstypischen, vorhersehbaren Schaden begrenzt.
                    </p>
                    <p>
                        Der Auftraggeber ist verpflichtet, erkennbare Schäden unverzüglich, spätestens jedoch innerhalb 
                        von 48 Stunden nach Leistungserbringung, schriftlich anzuzeigen.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>8. Entsorgung und Verwertung</h2>
                    <p>
                        Bei Entrümpelungs- und Entsorgungsarbeiten erfolgt die Entsorgung des Materials fachgerecht gemäß 
                        den geltenden Umweltschutzbestimmungen. Sofern nichts anderes vereinbart wurde, entscheidet der 
                        Auftragnehmer über die Art der Entsorgung oder Verwertung.
                    </p>
                    <p>
                        Gefährliche Abfälle (z. B. Asbest, Chemikalien) sind vom Auftraggeber anzugeben. Die Entsorgung 
                        solcher Stoffe erfolgt nur nach gesonderter Vereinbarung und gegen Aufpreis.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>9. Widerrufsrecht für Verbraucher</h2>
                    <p>
                        Verbraucher haben gemäß § 312g BGB ein Widerrufsrecht von 14 Tagen. Das Widerrufsrecht erlischt 
                        vorzeitig, wenn der Auftragnehmer die Dienstleistung vollständig erbracht hat und mit der 
                        Ausführung erst begonnen hat, nachdem der Verbraucher dazu seine ausdrückliche Zustimmung gegeben 
                        und gleichzeitig seine Kenntnis davon bestätigt hat, dass er sein Widerrufsrecht bei vollständiger 
                        Vertragserfüllung verliert.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>10. Datenschutz</h2>
                    <p>
                        Die Verarbeitung personenbezogener Daten erfolgt gemäß der Datenschutzerklärung, die auf der 
                        Website unter <a href="/datenschutz">www.ds-allroundservice.de/datenschutz</a> einsehbar ist.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <h2>11. Schlussbestimmungen</h2>
                    <p>
                        Sollten einzelne Bestimmungen dieser AGB unwirksam sein oder werden, bleibt die Wirksamkeit der 
                        übrigen Bestimmungen hiervon unberührt.
                    </p>
                    <p>
                        Es gilt das Recht der Bundesrepublik Deutschland unter Ausschluss des UN-Kaufrechts.
                    </p>
                    <p>
                        Erfüllungsort und Gerichtsstand für alle Streitigkeiten aus diesem Vertrag ist, soweit gesetzlich 
                        zulässig, der Sitz des Auftragnehmers.
                    </p>
                </div>
                
                <div class="impressum-section">
                    <p>
                        <strong>Stand:</strong> Oktober 2025<br>
                        <strong>{$settings['site_name']}</strong><br>
                        Daniel Skopek
                    </p>
                </div>
            </div>
        </section>
        HTML;
    }
}

AGB::main();
