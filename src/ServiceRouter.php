<?php

namespace DSAllround;
use DSAllround\Views\Page;

class ServiceRouter extends Page 
{
    public function __construct() 
    {
        parent::__construct();
    }

    /**
     * Get all active services from database
     */
    public function getActiveServices(): array 
    {
        try {
            $stmt = $this->_database->prepare("SELECT slug, name FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Error loading services: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Register all service routes dynamically
     */
    public function registerServiceRoutes($router): void 
    {
        $services = $this->getActiveServices();
        
        foreach ($services as $service) {
            $slug = $service['slug'];
            
            // Register main service page route
            $router->add("/{$slug}", function() use ($slug) {
                require_once __DIR__ . '/Views/ServicePage.php';
                \DSAllround\Views\ServicePage::create($slug);
            });
            
            // Register questionnaire route for each service
            $router->add("/{$slug}-anfrage", function() use ($slug) {
                require_once __DIR__ . '/Views/DynamicQuestionnaire.php';
                \DSAllround\Views\DynamicQuestionnaire::create($slug);
            });
        }
    }

    /**
     * Update home page navigation with dynamic services
     */
    public function getNavigationServices(): array 
    {
        return $this->getActiveServices();
    }
}
