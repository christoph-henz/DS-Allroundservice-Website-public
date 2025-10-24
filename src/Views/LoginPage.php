<?php declare(strict_types=1);

namespace DSAllround\Views;

class LoginPage extends Page
{
    /**
     * Instantiates members and establishes database connection
     */
    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * Clean up database connection
     */
    public function __destruct()
    {
        parent::__destruct();
    }

    /**
     * Main function to create instance and generate output
     */
    public static function main(): void
    {
        try {
            $page = new self();
            $page->generateView();
        } catch (\Exception $e) {
            error_log("Error creating login page: " . $e->getMessage());
            // Show basic error page
            echo "<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Application Error</h1><p>Please try again later.</p></body></html>";
        }
    }

    /**
     * Generate the complete login page
     */
    protected function generateView(): void
    {
        $this->generateLoginPage();
    }

    /**
     * Generate the login page HTML
     */
    private function generateLoginPage(): void
    {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>DS Allroundservice - Admin Login</title>
            <link rel="stylesheet" href="public/assets/css/login.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <link rel="icon" type="image/png" href="public/assets/img/logo.png">
        </head>
        <body>
            <div class="login-container">
                <div class="login-background">
                    <div class="background-overlay"></div>
                </div>
                
                <div class="login-form-container">
                    <div class="login-header">
                        <img src="public/assets/img/logo.png" alt="DS Allroundservice Logo" class="login-logo">
                        <h1>Admin Panel</h1>
                        <p>Anmeldung erforderlich</p>
                    </div>

                    <form id="loginForm" class="login-form">
                        <div class="form-group">
                            <label for="username">
                                <i class="fas fa-user"></i>
                                Benutzername oder E-Mail
                            </label>
                            <input type="text" id="username" name="username" required autocomplete="username">
                            <div class="input-border"></div>
                        </div>

                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i>
                                Passwort
                            </label>
                            <div class="password-input-container">
                                <input type="password" id="password" name="password" required autocomplete="current-password">
                                <button type="button" id="togglePassword" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="input-border"></div>
                        </div>

                        <div class="form-options">
                            <label class="remember-me">
                                <input type="checkbox" id="remember" name="remember">
                                <span class="checkmark"></span>
                                Angemeldet bleiben
                            </label>
                        </div>

                        <button type="submit" class="login-button">
                            <span class="button-text">Anmelden</span>
                            <div class="button-loader" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </button>

                        <div id="loginError" class="error-message" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i>
                            <span id="errorText"></span>
                        </div>

                        <div id="loginSuccess" class="success-message" style="display: none;">
                            <i class="fas fa-check-circle"></i>
                            <span>Anmeldung erfolgreich! Weiterleitung...</span>
                        </div>
                    </form>

                    <div class="login-footer">
                        <p>DS Allroundservice Admin Panel</p>
                        <p>&copy; <?= date('Y') ?> - Alle Rechte vorbehalten</p>
                    </div>
                </div>
            </div>

            <script src="public/assets/js/login.js"></script>
        </body>
        </html>
        <?php
    }
}

LoginPage::main();