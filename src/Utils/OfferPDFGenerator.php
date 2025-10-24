<?php

class OfferPDFGenerator {
    private $pdf;
    private $companyInfo;
    private $database;
    
    public function __construct($database = null) {
        // Autoloader is loaded in the calling script
        // require_once __DIR__ . '/../../vendor/autoload.php';
        
        $this->database = $database;
        $this->loadCompanyInfoFromDatabase();
    }
    
    /**
     * Load company information from database settings
     */
    private function loadCompanyInfoFromDatabase() {
        // Default fallback values
        $this->companyInfo = [
            'name' => 'DS-Allroundservice',
            'address' => 'Musterstraße 123',
            'city' => '63741 Aschaffenburg',
            'phone' => '+49 124 456789',
            'email' => 'info@ds-allroundservice.de',
            'website' => 'www.ds-allroundservice.de',
            'tax_number' => 'DE123456789'
        ];
        
        if (!$this->database) {
            error_log("OfferPDFGenerator: No database connection provided, using defaults");
            return;
        }
        
        try {
            // Load company settings from database (like in Impressum.php)
            $stmt = $this->database->prepare("
                SELECT setting_key, setting_value, setting_type 
                FROM settings
            ");
            
            $stmt->execute();
            $settings = [];
            
            // Fetch all results - MySQL/PDO compatible (like in Impressum.php)
            while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $key = $result['setting_key'];
                $value = $result['setting_value'];
                $type = $result['setting_type'];
                
                // Convert value based on type
                if ($type === 'json') {
                    $value = json_decode($value, true);
                } elseif (in_array($type, ['string', 'int', 'integer', 'float', 'double', 'bool', 'boolean', 'array', 'object', 'null'])) {
                    settype($value, $type);
                }
                
                $settings[$key] = $value;
            }
            
            if (!empty($settings)) {
                // Map database settings to company info
                $this->mapDatabaseSettings($settings);
                error_log("OfferPDFGenerator: Loaded company info from database");
            } else {
                error_log("OfferPDFGenerator: No company settings found in database, using defaults");
            }
            
        } catch (\Exception $e) {
            error_log("OfferPDFGenerator: Error loading company settings: " . $e->getMessage());
            // Continue with default values
        }
    }
    
    /**
     * Map database settings to company info array
     */
    private function mapDatabaseSettings($settings) {
        // Company name from site_name
        if (isset($settings['site_name'])) {
            $this->companyInfo['name'] = $settings['site_name'];
        }
        
        // Address - parse contact_address into address and city
        if (isset($settings['contact_address'])) {
            $this->parseAddress($settings['contact_address']);
        }
        
        // Phone
        if (isset($settings['contact_phone'])) {
            $this->companyInfo['phone'] = $settings['contact_phone'];
        }
        
        // Email
        if (isset($settings['contact_email'])) {
            $this->companyInfo['email'] = $settings['contact_email'];
        }
        
        // Website
        if (isset($settings['company_website'])) {
            $this->companyInfo['website'] = $this->formatWebsite($settings['company_website']);
        }
        
        // Tax/VAT number
        if (isset($settings['company_vat_id'])) {
            $this->companyInfo['tax_number'] = $settings['company_vat_id'];
        }
        
        // Log what was loaded
        error_log("OfferPDFGenerator: Company info loaded - " . json_encode($this->companyInfo));
    }
    
    /**
     * Parse address string into separate address and city components
     */
    private function parseAddress($addressString) {
        // Try to extract city with postal code pattern
        // Example: "Darmstädter Straße 0 63741 Aschaffenburg"
        if (preg_match('/^(.+?)\s+(\d{5}\s+.+)$/', $addressString, $matches)) {
            $this->companyInfo['address'] = trim($matches[1]);
            $this->companyInfo['city'] = trim($matches[2]);
        } else {
            // Fallback: use full string as address
            $this->companyInfo['address'] = $addressString;
        }
    }
    
    /**
     * Format website URL consistently
     */
    private function formatWebsite($website) {
        if (empty($website)) {
            return '';
        }
        
        // Remove protocol if present
        $website = preg_replace('/^https?:\/\//', '', $website);
        
        // Remove www if present
        $website = preg_replace('/^www\./', '', $website);
        
        return 'www.' . $website;
    }
    
    /**
     * Get company info for external use
     */
    public function getCompanyInfo() {
        return $this->companyInfo;
    }
    
    /**
     * Update company info manually (for testing or special cases)
     */
    public function setCompanyInfo($companyInfo) {
        $this->companyInfo = array_merge($this->companyInfo, $companyInfo);
    }
    
    public function generateOffer($data, $outputPath) {
        // Debug logging
        error_log("PDF Generator - Using company info: " . json_encode($this->companyInfo));
        error_log("PDF Generator - Received data: " . print_r($data, true));
        error_log("PDF Generator - VAT Rate: " . $data['totals']['vat_rate']);
        error_log("PDF Generator - VAT Amount: " . $data['totals']['vat_amount']);
        error_log("PDF Generator - Legal Note: " . ($data['legal_note'] ?? 'none'));
        error_log("PDF Generator - Kleinunternehmerregelung: " . (isset($data['kleinunternehmerregelung']) ? ($data['kleinunternehmerregelung'] ? 'true' : 'false') : 'not set'));
        
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $this->pdf->SetCreator('DS-Allroundservice Admin System');
        $this->pdf->SetAuthor($this->companyInfo['name']);
        $this->pdf->SetTitle('Angebot ' . $data['offer_number']);
        $this->pdf->SetSubject('Angebot für ' . $data['customer']['name']);
        
        // Remove default header/footer
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        
        // Set margins
        $this->pdf->SetMargins(20, 20, 20);
        $this->pdf->SetAutoPageBreak(true, 20); // Smaller bottom margin
        
        // Add a page
        $this->pdf->AddPage();
        
        // Company header
        $this->addCompanyHeader();
        
        // Customer and offer info
        $this->addOfferInfo($data);
        
        // Items table
        $this->addItemsTable($data);

        // Kleinunternehmerregelung legal note
        if (!empty($data['legal_note'])) {
            error_log("PDF Generator - Adding legal note: " . $data['legal_note']);
            $this->pdf->SetFont('helvetica', 'B', 10);
            $this->pdf->SetTextColor(200, 0, 0);
            $this->pdf->MultiCell(0, 8, $data['legal_note'], 0, 'L');
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->Ln(3);
        } else {
            error_log("PDF Generator - No legal note to add");
        }

        // Notes and terms
        $this->addNotesAndTerms($data);

        // Footer - direkt nach dem Inhalt, ohne absolute Positionierung
        $this->addFooter();        // Output PDF to file
        $this->pdf->Output($outputPath, 'F');
        
        return $outputPath;
    }
    
    private function addCompanyHeader() {
        // Logo placeholder (you can add actual logo later)
        $this->pdf->SetFont('helvetica', 'B', 20);
        $this->pdf->SetTextColor(0, 124, 186); // Company blue
        $this->pdf->Cell(0, 10, $this->companyInfo['name'], 0, 1, 'L');
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetTextColor(100, 100, 100);
        $this->pdf->Cell(0, 5, $this->companyInfo['address'], 0, 1, 'L');
        $this->pdf->Cell(0, 5, $this->companyInfo['city'], 0, 1, 'L');
        
        // Build contact line dynamically
        $contactParts = [];
        if (!empty($this->companyInfo['phone'])) {
            $contactParts[] = 'Tel: ' . $this->companyInfo['phone'];
        }
        if (!empty($this->companyInfo['email'])) {
            $contactParts[] = 'E-Mail: ' . $this->companyInfo['email'];
        }
        if (!empty($this->companyInfo['website'])) {
            $contactParts[] = 'Web: ' . $this->companyInfo['website'];
        }
        
        if (!empty($contactParts)) {
            $this->pdf->Cell(0, 5, implode(' | ', $contactParts), 0, 1, 'L');
        }
        
        $this->pdf->Ln(10);
    }
    
    private function addOfferInfo($data) {
        // Title
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell(0, 10, 'ANGEBOT', 0, 1, 'L');
        
        $this->pdf->Ln(5);
        
        // Two columns: Customer info and offer details
        $currentY = $this->pdf->GetY();
        
        // Left column - Customer
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->Cell(90, 6, 'Angebot für:', 0, 1, 'L');
        
        $this->pdf->SetFont('helvetica', '', 10);
        
        // Build customer name from first and last name, with fallback to 'name' field
        $customerName = '';
        if (!empty($data['customer']['first_name']) && !empty($data['customer']['last_name'])) {
            $customerName = $data['customer']['first_name'] . ' ' . $data['customer']['last_name'];
        } elseif (!empty($data['customer']['name'])) {
            $customerName = $data['customer']['name'];
        } elseif (!empty($data['customer']['first_name'])) {
            $customerName = $data['customer']['first_name'];
        } elseif (!empty($data['customer']['last_name'])) {
            $customerName = $data['customer']['last_name'];
        } else {
            $customerName = 'Unbekannter Kunde';
        }
        
        $this->pdf->Cell(90, 5, $customerName, 0, 1, 'L');
        if (!empty($data['customer']['email'])) {
            $this->pdf->Cell(90, 5, $data['customer']['email'], 0, 1, 'L');
        }
        if (!empty($data['customer']['phone'])) {
            $this->pdf->Cell(90, 5, $data['customer']['phone'], 0, 1, 'L');
        }
        
        // Right column - Offer details
        $this->pdf->SetXY(120, $currentY);
        
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->Cell(70, 6, 'Angebotsdaten:', 0, 1, 'L');
        
        $this->pdf->SetX(120);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(35, 5, 'Angebot-Nr.:', 0, 0, 'L');
        $this->pdf->Cell(35, 5, $data['offer_number'], 0, 1, 'L');
        
        $this->pdf->SetX(120);
        $this->pdf->Cell(35, 5, 'Datum:', 0, 0, 'L');
        $this->pdf->Cell(35, 5, $data['date'], 0, 1, 'L');
        
        if (!empty($data['valid_until'])) {
            $this->pdf->SetX(120);
            $this->pdf->Cell(35, 5, 'Gültig bis:', 0, 0, 'L');
            $this->pdf->Cell(35, 5, $data['valid_until'], 0, 1, 'L');
        }
        
        $this->pdf->SetX(120);
        $this->pdf->Cell(35, 5, 'Service:', 0, 0, 'L');
        $this->pdf->Cell(35, 5, $data['submission']['service_name'], 0, 1, 'L');
        
        $this->pdf->SetX(120);
        $this->pdf->Cell(35, 5, 'Referenz:', 0, 0, 'L');
        $this->pdf->Cell(35, 5, $data['submission']['reference'], 0, 1, 'L');
        
        $this->pdf->Ln(8); // Reduced from 10
    }
    
    private function addItemsTable($data) {
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->Cell(0, 8, 'Leistungen und Preise:', 0, 1, 'L');
        $this->pdf->Ln(2);
        
        // Table header
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->Cell(10, 8, 'Pos.', 1, 0, 'C', true);
        $this->pdf->Cell(90, 8, 'Beschreibung', 1, 0, 'L', true);
        $this->pdf->Cell(20, 8, 'Menge', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Einzelpreis', 1, 0, 'R', true);
        $this->pdf->Cell(30, 8, 'Gesamtpreis', 1, 1, 'R', true);
        
        // Table rows
        $this->pdf->SetFont('helvetica', '', 9);
        $position = 1;
        
        foreach ($data['items'] as $item) {
            $this->pdf->Cell(10, 8, $position++, 1, 0, 'C');
            $this->pdf->Cell(90, 8, $item['description'], 1, 0, 'L');
            $this->pdf->Cell(20, 8, number_format($item['quantity'], 0, ',', '.'), 1, 0, 'C');
            $this->pdf->Cell(30, 8, number_format($item['amount'], 2, ',', '.') . ' €', 1, 0, 'R');
            $this->pdf->Cell(30, 8, number_format($item['total'], 2, ',', '.') . ' €', 1, 1, 'R');
        }
        
        // Totals
        $this->pdf->Ln(3);
        
        // Debug logging for PDF totals
        error_log("PDF addItemsTable - VAT Rate: " . $data['totals']['vat_rate']);
        error_log("PDF addItemsTable - VAT Amount: " . $data['totals']['vat_amount']);
        error_log("PDF addItemsTable - Will show VAT line: " . ($data['totals']['vat_rate'] > 0 ? 'yes' : 'no'));
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(150, 6, 'Nettobetrag:', 0, 0, 'R');
        $this->pdf->Cell(30, 6, number_format($data['totals']['net'], 2, ',', '.') . ' €', 0, 1, 'R');
        
        if ($data['totals']['vat_rate'] > 0) {
            error_log("PDF addItemsTable - Adding VAT line");
            $this->pdf->Cell(150, 6, 'MwSt. (' . $data['totals']['vat_rate'] . '%):', 0, 0, 'R');
            $this->pdf->Cell(30, 6, number_format($data['totals']['vat_amount'], 2, ',', '.') . ' €', 0, 1, 'R');
        } else {
            error_log("PDF addItemsTable - Skipping VAT line (rate is 0)");
        }
        
        // Total line
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->Cell(150, 8, 'Gesamtbetrag:', 1, 0, 'R', true);
        $this->pdf->Cell(30, 8, number_format($data['totals']['gross'], 2, ',', '.') . ' €', 1, 1, 'R', true);
        
        $this->pdf->Ln(8); // Reduced from 10
    }
    
    private function addNotesAndTerms($data) {
        if (!empty($data['notes'])) {
            $this->pdf->SetFont('helvetica', 'B', 11);
            $this->pdf->Cell(0, 8, 'Anmerkungen:', 0, 1, 'L');
            
            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->MultiCell(0, 5, $data['notes'], 0, 'L');
            $this->pdf->Ln(3); // Reduced from 5
        }
        
        if (!empty($data['execution_date'])) {
            $this->pdf->SetFont('helvetica', 'B', 10);
            $this->pdf->Cell(0, 6, 'Gewünschter Ausführungstermin: ' . $data['execution_date'], 0, 1, 'L');
            $this->pdf->Ln(2); // Reduced from 3
        }
        
        if (!empty($data['terms'])) {
            $this->pdf->SetFont('helvetica', 'B', 11);
            $this->pdf->Cell(0, 8, 'Zahlungsbedingungen:', 0, 1, 'L');
            
            $this->pdf->SetFont('helvetica', '', 9);
            $this->pdf->MultiCell(0, 4, $data['terms'], 0, 'L');
            $this->pdf->Ln(3); // Reduced from 5
        }
    }
    
    private function addFooter() {
        // Calculate position for footer at bottom of page
        $currentY = $this->pdf->GetY();
        $pageHeight = $this->pdf->getPageHeight();
        $bottomMargin = 20; // Bottom margin
        $footerHeight = 12; // Height needed for footer
        
        // Position footer at bottom of page
        $footerY = $pageHeight - $bottomMargin - $footerHeight;
        
        // If current position is below footer position, footer goes right after content
        if ($currentY >= $footerY) {
            $this->pdf->Ln(5);
        } else {
            // Move to footer position at bottom
            $this->pdf->SetY($footerY);
        }
        
        // Add a subtle separator line
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->Line(20, $this->pdf->GetY(), 190, $this->pdf->GetY());
        $this->pdf->Ln(3);
        
        // Footer content
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetTextColor(100, 100, 100);
        
        $footerText = $this->companyInfo['name'] . ' | ' . 
                     $this->companyInfo['address'] . ', ' . $this->companyInfo['city'] . ' | ' .
                     'Tel: ' . $this->companyInfo['phone'] . ' | ' .
                     'E-Mail: ' . $this->companyInfo['email'];
        
        if (!empty($this->companyInfo['tax_number'])) {
            $footerText .= ' | Steuernummer: ' . $this->companyInfo['tax_number'];
        }
        
        $this->pdf->MultiCell(0, 3, $footerText, 0, 'C');
    }
}