<?php
// Production index.php - Apache CGI compatible
// Routes working now

// CRITICAL: Absolute error suppression FIRST
error_reporting(0);
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
set_error_handler(function() { return true; });

// Start output buffering
ob_start();

require __DIR__ . '/vendor/autoload.php';

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Remove base path if present
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = str_replace('/index.php', '', $scriptName);

if ($basePath !== '' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
if (empty($path)) {
    $path = '/';
}

require __DIR__ . '/src/Router.php';
require __DIR__ . '/src/ServiceRouter.php';

$router = new DSAllround\Router();

// Static routes
$router->add("/", function() {
    require __DIR__ . '/src/Views/Home.php';
});
$router->add("/contact", function() {
    require __DIR__ . '/src/Views/Contact.php';
});
$router->add("/impressum", function() {
    require __DIR__ . '/src/Views/Impressum.php';
});
$router->add("/datenschutz", function() {
    require __DIR__ . '/src/Views/Datenschutz.php';
});
$router->add("/agb", function() {
    require __DIR__ . '/src/Views/AGB.php';
});

// Admin routes
$router->add("/login", function() {
    require __DIR__ . '/src/Views/LoginPage.php';
});
$router->add("/admin", function() {
    require __DIR__ . '/src/Views/AdminPage.php';
});
$router->add("/admin-api", function() {
    require __DIR__ . '/api/admin.php';
});
$router->add("/api/admin.php", function() {
    require __DIR__ . '/api/admin.php';
});
$router->add("/api/auth.php", function() {
    require __DIR__ . '/api/auth.php';
});

// Questionnaire Builder Test
$router->add("/questionnaire-builder-test", function() {
    require __DIR__ . '/src/Views/QuestionnaireBuilderTest.php';
});

// Debug Tools
$router->add("/debug-email", function() {
    require __DIR__ . '/debug/test_emailservice.php';
});
$router->add("/debug-mail", function() {
    require __DIR__ . '/debug/test_mail_function.php';
});
$router->add("/debug-templates", function() {
    require __DIR__ . '/debug/check_templates.php';
});
$router->add("/debug-headers", function() {
    require __DIR__ . '/debug/test_headers.php';
});

// Success pages and API
$router->add("/anfrage-erfolgreich", function() {
    require __DIR__ . '/src/Views/QuestionnaireSuccess.php';
});
$router->add("/questionnaire-success", function() {
    require __DIR__ . '/src/Views/QuestionnaireSuccess.php';
});
$router->add("/submit-inquiry", function() {
    require __DIR__ . '/api/questionnaire_api.php';
});
$router->add("/api/submit-questionnaire.php", function() {
    require __DIR__ . '/api/submit-questionnaire.php';
});

// Questionnaire Builder API
$router->add("/api/questions/*", function() {
    define('INCLUDED_API', true);
    require __DIR__ . '/api/questionnaire-builder.php';
});
$router->add("/api/groups/*", function() {
    define('INCLUDED_API', true);
    require __DIR__ . '/api/questionnaire-builder.php';
});

// Admin API groups support
$router->add("/api/groups/questionnaire/*", function() {
    $_REQUEST['action'] = 'questionnaire-groups';
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    if (count($pathParts) >= 4) {
        $_REQUEST['id'] = $pathParts[3];
    }
    require __DIR__ . '/api/admin.php';
});

// Dynamic service routes
try {
    $serviceRouter = new DSAllround\ServiceRouter();
    $serviceRouter->registerServiceRoutes($router);
} catch (Throwable $e) {
    // Silently continue
}

// Routing ausführen
$router->dispatch($path);
