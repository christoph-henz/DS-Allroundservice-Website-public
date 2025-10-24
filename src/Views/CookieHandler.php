<?php
namespace DSAllround\Views;

class CookieHandler
{
    public const COOKIE_EXPIRATION = 2592000; // 30 Tage in Sekunden
    public const ASKED_BEFORE_KEY = 'cookie_asked_before';
    public const ALLOW_NECESSARY_KEY = 'allow_necessary_cookies';
    public const ALLOW_ANALYTICS_KEY = 'allow_analytics_cookies';

    private bool $askedBefore = false;
    private bool $allowNecessary = false;
    private bool $allowAnalytics = false;

    public function __construct()
    {
        $this->loadCookieSettings();
    }

    private function loadCookieSettings(): void
    {
        $this->askedBefore = isset($_COOKIE[self::ASKED_BEFORE_KEY]) ||
            (isset($_SESSION['cookie_settings_saved']) && $_SESSION['cookie_settings_saved'] === true);

        $this->allowNecessary = isset($_COOKIE[self::ALLOW_NECESSARY_KEY]) &&
            $_COOKIE[self::ALLOW_NECESSARY_KEY] === 'true';

        $this->allowAnalytics = isset($_COOKIE[self::ALLOW_ANALYTICS_KEY]) &&
            $_COOKIE[self::ALLOW_ANALYTICS_KEY] === 'true';
    }

    private function getCookieStyles(): string
    {
        return <<<'EOT'
        <style>
        /* Brave Browser Compatible Privacy Notice Styles */
        
        /* Privacy Overlay - Blur & Lock UI */
        .ds-privacy-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 9998;
            animation: dsPrivacyFadeIn 0.3s ease-in-out;
        }
        
        @keyframes dsPrivacyFadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        .ds-consent-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.95);
            color: #fff;
            padding: 20px;
            z-index: 9999;
            transform: translateY(100%);
            animation: dsPrivacySlideIn 0.5s forwards;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
        }
        
        @keyframes dsPrivacySlideIn {
            to {
                transform: translateY(0);
            }
        }        
        .ds-modal-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .ds-modal-content h2 {
            color: #ffab66;
            margin-bottom: 15px;
        }
        
        .ds-modal-content p a {
            color: #ffab66;
            text-decoration: underline;
            transition: color 0.3s;
        }
        
        .ds-modal-content p a:hover {
            color: #ff9933;
        }
        
        .ds-options-list {
            margin: 20px 0;
        }
        
        .ds-option-item {
            margin: 15px 0;
        }
        
        .ds-option-item label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .ds-option-item input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .ds-option-description {
            font-size: 0.9em;
            color: #ccc;
            margin-top: 5px;
            margin-left: 28px;
            line-height: 1.5;
        }
        
        .ds-button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .ds-button-group button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 15px;
        }
        
        .ds-accept-all-btn {
            background-color: #ffab66;
            color: #000;
        }
        
        .ds-save-prefs-btn {
            background-color: #333;
            color: #fff;
            border: 1px solid #555;
        }
        
        .ds-accept-all-btn:hover {
            background-color: #ff9933;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 171, 102, 0.4);
        }
        
        .ds-save-prefs-btn:hover {
            background-color: #444;
            border-color: #666;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .ds-consent-container {
                padding: 15px;
            }
            
            .ds-button-group {
                flex-direction: column;
            }
            
            .ds-button-group button {
                width: 100%;
            }
        }
        </style>
        EOT;
    }

    private function getCookieScript(): string
    {
        return <<< EOT
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const privacyForm = document.getElementById('dsConsentForm');
                const acceptAllBtn = document.querySelector('.ds-accept-all-btn');
                const savePreferencesBtn = document.querySelector('.ds-save-prefs-btn');
                const analyticsCheckbox = document.getElementById('dsAnalyticsCheckbox');
                const modal = document.getElementById('dsPrivacyModal');
                
                // Lock scrolling when modal is visible
                if (modal) {
                    document.body.style.overflow = 'hidden';
                }
                
                // "Alle akzeptieren" - aktiviert Analytics
                acceptAllBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (analyticsCheckbox) {
                        analyticsCheckbox.checked = true;
                    }
                    // Re-enable scrolling before submit
                    document.body.style.overflow = '';
                    privacyForm.submit();
                });
                
                // "Nur notwendige akzeptieren" - deaktiviert Analytics
                savePreferencesBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (analyticsCheckbox) {
                        analyticsCheckbox.checked = false;
                    }
                    // Re-enable scrolling before submit
                    document.body.style.overflow = '';
                    privacyForm.submit();
                });
            });
            </script>
        EOT;
    }


    public function generateCookieBanner(): string
    {
        if ($this->askedBefore) {
            return '';
        }
        
        // Don't show banner on legal pages (allow users to read terms before accepting)
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        $legalPages = ['/datenschutz', '/agb', '/impressum'];
        
        foreach ($legalPages as $legalPage) {
            if (strpos($currentPath, $legalPage) !== false) {
                return '';
            }
        }

        ob_start();
        ?>
        <!-- Privacy Notice Overlay (Brave Browser Compatible) -->
        <div class="ds-privacy-overlay" style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; background: rgba(0, 0, 0, 0.6) !important; z-index: 9998 !important; display: block !important; opacity: 1 !important; backdrop-filter: blur(8px) !important;"></div>
        
        <!-- Privacy Notice Modal (Brave Browser Compatible) -->
        <div id="dsPrivacyModal" class="ds-consent-container" style="position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; background: rgba(0, 0, 0, 0.95) !important; color: #fff !important; padding: 20px !important; z-index: 9999 !important; display: block !important; visibility: visible !important; opacity: 1 !important;">
            <div class="ds-modal-content">
                <h2>Datenschutz & Cookie-Einstellungen</h2>
                <p>Mit der Nutzung unserer Website stimmen Sie unseren <a href="/datenschutz">Datenschutzbestimmungen</a> und <a href="/agb">AGB</a> zu. Weitere Informationen finden Sie in unserem <a href="/impressum">Impressum</a>.</p>

                <form id="dsConsentForm" method="post">
                    <!-- Hidden input to always submit necessary cookies -->
                    <input type="hidden" name="<?= $this::ALLOW_NECESSARY_KEY ?>" value="1">
                    
                    <div class="ds-options-list">
                        <div class="ds-option-item">
                            <label>
                                <input type="checkbox" checked disabled style="pointer-events: none;">
                                Notwendige Cookies (erforderlich)
                            </label>
                            <p class="ds-option-description">
                                Diese Cookies sind für die Grundfunktionen der Website erforderlich: 
                                Kontaktformular, Anfrageverarbeitung, Session-Verwaltung und Akzeptanz der Datenschutzbestimmungen & AGB.
                            </p>
                        </div>

                        <div class="ds-option-item">
                            <label>
                                <input type="checkbox" name="<?= $this::ALLOW_ANALYTICS_KEY ?>" value="1" id="dsAnalyticsCheckbox">
                                Analyse & Statistik (optional)
                            </label>
                            <p class="ds-option-description">
                                Hilft uns, die Website zu verbessern, indem anonyme Nutzungsstatistiken erfasst werden.
                            </p>
                        </div>
                    </div>

                    <div class="ds-button-group">
                        <button type="submit" class="ds-accept-all-btn">Alle akzeptieren</button>
                        <button type="submit" class="ds-save-prefs-btn">Nur notwendige akzeptieren</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        return $html . $this->getCookieStyles() . $this->getCookieScript();
    }

    public function generateCookieSettings(): void
    {
        $this->loadCookieSettings();
        $checkedNecessary = $this->allowNecessary ? 'checked' : '';
        $checkedAnalytics = $this->allowAnalytics ? 'checked' : '';
        ob_start();

        ?>
        <div class="cookie-settings">
            <h2>Cookie-Einstellungen</h2>
            <form method="post" class="cookie-form">
                <div class="cookie-options">
                    <div class="cookie-option">
                        <label>
                            <input type="checkbox" disabled="disabled" checked="checked" name="<?=$this::ALLOW_NECESSARY_KEY?>" value="1">
                            Notwendige Cookies (erforderlich)
                        </label>
                        <p class="cookie-description">
                            Kontaktformular, Anfrageverarbeitung, Session-Verwaltung, Datenschutz & AGB.
                        </p>
                    </div>

                    <div class="cookie-option">
                        <label>
                            <input type="checkbox" name="<?=$this::ALLOW_ANALYTICS_KEY?>" value="1" <?=$checkedAnalytics?>>
                            Analyse & Statistik (optional)
                        </label>
                        <p class="cookie-description">
                            Anonyme Nutzungsstatistiken zur Verbesserung der Website.
                        </p>
                    </div>
                </div>

                <button type="submit" class="save-button">Einstellungen speichern</button>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        echo $html . $this->getCookieSettingsStyles() . $this->getCookieScript();
    }


    private function getCookieSettingsStyles(): string
    {
        return <<<'EOT'
            <style>
                .cookie-settings {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(0, 0, 0, 0.95);
                color: #000;
                padding: 20px;
                z-index: 9999;
                transform: translateY(100%);
                animation: slidein 0.5s forwards;
                border: #000 1px solid;
            }
            
            @keyframes slidein {
                to {
                    transform: translateY(0);
                }
            } 
            .cookie-settings {
                max-width: 800px;
                margin: 40px auto;
                padding: 20px;
                background: #f5f5f5;
                border-radius: 8px;
            }
        
            .cookie-settings h2 {
                color: #333;
                margin-bottom: 20px;
            }
            
            .cookie-form {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            
            .cookie-option {
                margin: 10px 0;
            }
            
            .save-button {
                padding: 10px 20px;
                background-color: #ffab66;
                color: #000;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                align-self: flex-end;
            }
            
            .save-button:hover {
                background-color: #ff9933;
            }
            </style>
        EOT;
    }

    // Getter und Setter Methoden
    public function isAskedBefore(): bool
    {
        return $this->askedBefore;
    }

    public function setAskedBefore(bool $asked): void
    {
        $this->askedBefore = $asked;
        setcookie(self::ASKED_BEFORE_KEY, '1', time() + self::COOKIE_EXPIRATION, '/');
    }

    public function isAllowNecessary(): bool
    {
        return $this->allowNecessary;
    }

    public function setAllowNecessary(bool $allow): void
    {
        $this->allowNecessary = $allow;
        setcookie(self::ALLOW_NECESSARY_KEY, $allow ? 'true' : 'false', time() + self::COOKIE_EXPIRATION, '/');
    }

    public function isAllowAnalytics(): bool
    {
        return $this->allowAnalytics;
    }

    public function setAllowAnalytics(bool $allow): void
    {
        $this->allowAnalytics = $allow;
        setcookie(self::ALLOW_ANALYTICS_KEY, $allow ? 'true' : 'false', time() + self::COOKIE_EXPIRATION, '/');
    }
}