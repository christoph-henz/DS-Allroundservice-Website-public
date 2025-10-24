<?php

namespace DSAllround\Utils;

// Autoloader is loaded in submit-questionnaire.php or index.php
// require_once __DIR__ . '/../../vendor/autoload.php';

use TCPDF;

class PDFGenerator
{
    private $submissionData;
    private $service;
    private $reference;
    private $isProduction;

    public function __construct($submissionData, $service, $reference, $isProduction = false)
    {
        $this->submissionData = $submissionData;
        $this->service = $service;
        $this->reference = $reference;
        $this->isProduction = $isProduction;
    }

    /**
     * Generate PDF document for the questionnaire submission
     */
    public function generatePDF(): string
    {
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('DS-Allroundservice');
        $pdf->SetAuthor('DS-Allroundservice');
        $pdf->SetTitle('Anfrage ' . $this->reference . ' - ' . $this->service['name']);
        $pdf->SetSubject($this->service['name'] . ' Anfrage');
        $pdf->SetKeywords('DS-Allroundservice, ' . $this->service['name'] . ', Anfrage, ' . $this->reference);

        // Set default header data
        $pdf->SetHeaderData('', 0, 'DS-Allroundservice', $this->service['name'] . ' Anfrage - Ref: ' . $this->reference);

        // Set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set font
        $pdf->SetFont('helvetica', '', 11);

        // Add a page
        $pdf->AddPage();

        // Generate content
        $html = $this->generateHTML();
        
        // Print text using writeHTMLCell()
        $pdf->writeHTML($html, true, false, true, false, '');

        // Save PDF to string
        return $pdf->Output('', 'S');
    }

    /**
     * Generate HTML content for PDF based on service and grouped questions
     */
    private function generateHTML(): string
    {
        $serviceName = htmlspecialchars($this->service['name']);
        $serviceColor = $this->service['color'] ?? '#007cba';
        $date = date('d.m.Y H:i:s');

        $html = <<<HTML
        <style>
            .header {
                background-color: {$serviceColor};
                color: white;
                padding: 20px;
                margin-bottom: 25px;
                border-radius: 8px;
            }
            .reference-info {
                background-color: #f8f9fa;
                padding: 15px;
                border-left: 4px solid {$serviceColor};
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .section {
                margin-bottom: 25px;
                page-break-inside: avoid;
            }
            .section-title {
                background-color: {$serviceColor};
                color: white;
                padding: 12px 15px;
                font-weight: bold;
                font-size: 14px;
                margin-bottom: 0;
                border-top-left-radius: 6px;
                border-top-right-radius: 6px;
            }
            .section-content {
                border: 1px solid #dee2e6;
                border-top: none;
                padding: 20px;
                border-bottom-left-radius: 6px;
                border-bottom-right-radius: 6px;
                background-color: #fff;
            }
            .group-section {
                margin-bottom: 20px;
                border: 1px solid #e9ecef;
                border-radius: 6px;
                overflow: hidden;
            }
            .group-title {
                background-color: #f8f9fa;
                color: #495057;
                padding: 12px 15px;
                font-weight: bold;
                font-size: 13px;
                border-bottom: 1px solid #e9ecef;
                margin: 0;
            }
            .group-content {
                padding: 15px;
            }
            .question-item {
                margin-bottom: 15px;
                padding-bottom: 12px;
                border-bottom: 1px solid #f1f3f4;
            }
            .question-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
            .question-text {
                font-weight: bold;
                color: #495057;
                margin-bottom: 6px;
                font-size: 12px;
                line-height: 1.4;
            }
            .answer-text {
                color: #212529;
                font-size: 11px;
                padding-left: 12px;
                line-height: 1.5;
                background-color: #f8f9fa;
                padding: 8px 12px;
                border-radius: 4px;
            }
            .contact-info {
                background-color: #e7f3ff;
                padding: 15px;
                border: 1px solid #b3d7ff;
                border-radius: 6px;
                margin-bottom: 15px;
            }
            .contact-info strong {
                color: {$serviceColor};
            }
            .metadata {
                font-size: 10px;
                color: #6c757d;
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #dee2e6;
            }
        </style>

        <div class="header">
            <h1 style="margin: 0; font-size: 18px;">{$serviceName} Anfrage</h1>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 12px;">Kundenanfrage erstellt am {$date}</p>
        </div>

        <div class="reference-info">
            <p style="margin: 0;"><strong>Referenznummer:</strong> {$this->reference}</p>
            <p style="margin: 5px 0 0 0;"><strong>Service:</strong> {$serviceName}</p>
        </div>

        <div class="section">
            <div class="section-title">Kontaktdaten</div>
            <div class="section-content">
                <div class="contact-info">
                    {$this->generateContactInfo()}
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Fragebogen Details</div>
            <div class="section-content">
                {$this->generateQuestionnaireContent()}
            </div>
        </div>

        <div class="metadata">
            <p><strong>Erstellt:</strong> {$date}</p>
            <p><strong>System:</strong> DS-Allroundservice Webformular</p>
            <p><strong>Version:</strong> 2.0</p>
        </div>

        HTML;

        return $html;
    }

    /**
     * Generate contact information section from submission data
     * Extracts contact data from fixed contact fields in answers array
     */
    private function generateContactInfo(): string
    {
        $html = '';
        $contactData = [];
        
        // Extract contact information from answers (fixed contact fields)
        if (isset($this->submissionData['answers']) && is_array($this->submissionData['answers'])) {
            $contactFieldNames = [
                'Vorname' => 'Vorname',
                'Nachname' => 'Nachname',
                'E-Mail Adresse' => 'E-Mail',
                'Telefonnummer' => 'Telefon',
                'Mobilnummer' => 'Mobil'
            ];
            
            foreach ($this->submissionData['answers'] as $answer) {
                $questionText = $answer['question_text'] ?? '';
                $answerText = $answer['answer_text'] ?? '';
                
                // Check if this is a contact field
                if (isset($contactFieldNames[$questionText]) && !empty($answerText)) {
                    $label = $contactFieldNames[$questionText];
                    $contactData[$label] = htmlspecialchars($answerText);
                }
            }
        }
        
        // Fallback: Try legacy contact fields if no answers found
        if (empty($contactData)) {
            $legacyFields = [
                'customer_name' => 'Name',
                'name' => 'Name',
                'customer_email' => 'E-Mail',  
                'email' => 'E-Mail',
                'customer_phone' => 'Telefon',
                'phone' => 'Telefon'
            ];
            
            foreach ($legacyFields as $field => $label) {
                if (isset($this->submissionData[$field]) && !empty($this->submissionData[$field])) {
                    $contactData[$label] = htmlspecialchars($this->submissionData[$field]);
                }
            }
        }
        
        // Build HTML from contact data
        if (!empty($contactData)) {
            // Combine first and last name if both exist
            if (isset($contactData['Vorname']) && isset($contactData['Nachname'])) {
                $fullName = $contactData['Vorname'] . ' ' . $contactData['Nachname'];
                $html .= "<p style='margin: 0 0 5px 0;'><strong>Name:</strong> {$fullName}</p>";
                unset($contactData['Vorname'], $contactData['Nachname']);
            }
            
            // Add remaining contact fields
            foreach ($contactData as $label => $value) {
                $html .= "<p style='margin: 0 0 5px 0;'><strong>{$label}:</strong> {$value}</p>";
            }
        }
        
        if (empty($html)) {
            $html = "<p style='margin: 0; color: #6c757d; font-style: italic;'>Keine Kontaktdaten verfügbar</p>";
        }
        
        return $html;
    }

    /**
     * Generate questionnaire content with grouped questions
     * Excludes contact fields which are displayed separately
     */
    private function generateQuestionnaireContent(): string
    {
        $html = '';
        
        // Define contact field names to exclude from questionnaire details
        $contactFieldNames = ['Vorname', 'Nachname', 'E-Mail Adresse', 'Telefonnummer', 'Mobilnummer'];
        
        // Check if we have grouped answers in submission_answers format
        if (isset($this->submissionData['answers']) && is_array($this->submissionData['answers'])) {
            // Filter out contact fields from answers
            $filteredAnswers = array_filter($this->submissionData['answers'], function($answer) use ($contactFieldNames) {
                $questionText = $answer['question_text'] ?? '';
                return !in_array($questionText, $contactFieldNames);
            });
            
            // Group answers by their group or display individually
            $groupedAnswers = $this->groupAnswersBySection($filteredAnswers);
            
            foreach ($groupedAnswers as $groupName => $answers) {
                // Skip empty groups
                if (empty($answers)) {
                    continue;
                }
                
                $html .= <<<HTML
                <div class="group-section">
                    <div class="group-title">{$groupName}</div>
                    <div class="group-content">
                HTML;
                
                foreach ($answers as $answer) {
                    $questionText = htmlspecialchars($answer['question_text']);
                    $answerText = $this->formatAnswer($answer['answer_text'], $answer['answer_data'] ?? null);
                    
                    $html .= <<<HTML
                    <div class="question-item">
                        <div class="question-text"><strong>Frage:</strong> {$questionText}</div>
                        <div class="answer-text"><strong>Antwort:</strong> {$answerText}</div>
                    </div>
                    HTML;
                }
                
                $html .= <<<HTML
                    </div>
                </div>
                HTML;
            }
        } else {
            // Fallback: display all form data
            $html .= $this->generateFallbackContent();
        }
        
        return $html;
    }

    /**
     * Group answers by section/group for better organization
     */
    private function groupAnswersBySection($answers): array
    {
        $grouped = [];
        
        foreach ($answers as $answer) {
            // Try to determine group from question text or use default
            $groupName = $this->determineQuestionGroup($answer['question_text']);
            
            if (!isset($grouped[$groupName])) {
                $grouped[$groupName] = [];
            }
            
            $grouped[$groupName][] = $answer;
        }
        
        return $grouped;
    }

    /**
     * Determine which group a question belongs to based on content
     */
    private function determineQuestionGroup($questionText): string
    {
        $questionLower = strtolower($questionText);
        
        // Service-specific grouping
        switch (strtolower($this->service['slug'])) {
            case 'umzuege':
            case 'umzug':
                if (strpos($questionLower, 'adresse') !== false || strpos($questionLower, 'zimmer') !== false || 
                    strpos($questionLower, 'stockwerk') !== false || strpos($questionLower, 'wann') !== false) {
                    return 'Umzugsdetails';
                } elseif (strpos($questionLower, 'verpackung') !== false || strpos($questionLower, 'packen') !== false || 
                          strpos($questionLower, 'montage') !== false || strpos($questionLower, 'service') !== false) {
                    return 'Zusätzliche Services';
                }
                break;
                
            case 'transport':
                if (strpos($questionLower, 'abhol') !== false || strpos($questionLower, 'liefer') !== false || 
                    strpos($questionLower, 'von') !== false || strpos($questionLower, 'nach') !== false) {
                    return 'Transport Details';
                } elseif (strpos($questionLower, 'gegenstände') !== false || strpos($questionLower, 'größe') !== false) {
                    return 'Transportgut';
                }
                break;
                
            case 'entruempelung':
                if (strpos($questionLower, 'räume') !== false || strpos($questionLower, 'größe') !== false) {
                    return 'Objektdetails';
                } elseif (strpos($questionLower, 'entsorgung') !== false || strpos($questionLower, 'trennung') !== false) {
                    return 'Entsorgung';
                }
                break;
                
            case 'aufloesung':
                if (strpos($questionLower, 'haushalt') !== false || strpos($questionLower, 'wohnung') !== false) {
                    return 'Haushaltsdetails';
                } elseif (strpos($questionLower, 'verwertung') !== false || strpos($questionLower, 'verkauf') !== false) {
                    return 'Verwertung';
                }
                break;
        }
        
        return 'Allgemeine Angaben';
    }

    /**
     * Format answer based on its type and data
     */
    private function formatAnswer($answerText, $answerData = null): string
    {
        // If we have structured data, use it
        if (!empty($answerData)) {
            $data = json_decode($answerData, true);
            if (is_array($data)) {
                return htmlspecialchars(implode(', ', $data));
            }
        }
        
        // Handle different answer formats
        if (is_array($answerText)) {
            return htmlspecialchars(implode(', ', $answerText));
        }
        
        if (is_bool($answerText)) {
            return $answerText ? 'Ja' : 'Nein';
        }
        
        if (empty($answerText)) {
            return '<em style="color: #6c757d;">Keine Antwort</em>';
        }
        
        // Format longer texts with line breaks
        $formatted = htmlspecialchars((string)$answerText);
        $formatted = nl2br($formatted);
        
        return $formatted;
    }

    /**
     * Generate fallback content when structured data is not available
     */
    private function generateFallbackContent(): string
    {
        $html = <<<HTML
        <div class="group-section">
            <div class="group-title">Eingaben</div>
            <div class="group-content">
        HTML;
        
        foreach ($this->submissionData as $key => $value) {
            // Skip system fields
            if (in_array($key, ['service_id', 'template_id', 'reference', 'csrf_token'])) {
                continue;
            }
            
            $label = $this->formatFieldLabel($key);
            $formattedValue = $this->formatAnswer($value);
            
            $html .= <<<HTML
            <div class="question-item">
                <div class="question-text"><strong>Frage:</strong> {$label}</div>
                <div class="answer-text"><strong>Antwort:</strong> {$formattedValue}</div>
            </div>
            HTML;
        }
        
        $html .= <<<HTML
            </div>
        </div>
        HTML;
        
        return $html;
    }

    /**
     * Format field label for display
     */
    private function formatFieldLabel($fieldKey): string
    {
        // Convert field keys to readable labels
        $labels = [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
            'phone' => 'Telefonnummer',
            'address' => 'Adresse',
            'message' => 'Nachricht',
            'notes' => 'Anmerkungen'
        ];
        
        if (isset($labels[$fieldKey])) {
            return $labels[$fieldKey];
        }
        
        // Auto-format field keys
        return ucfirst(str_replace(['_', '-'], ' ', $fieldKey));
    }

    /**
     * Save PDF to file with appropriate location based on environment
     */
    public function savePDF(string $filename = null): array
    {
        try {
            $pdfContent = $this->generatePDF();
            
            // Determine save location
            if ($this->isProduction) {
                // In production, save to temp for email attachment
                $directory = sys_get_temp_dir() . '/ds-questionnaires';
            } else {
                // Local environment: save to data/questionnaires
                $directory = __DIR__ . '/../../data/questionnaires';
            }
            
            // Create directory if it doesn't exist
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Generate filename if not provided
            if (!$filename) {
                $timestamp = date('Y-m-d_H-i-s');
                $serviceSlug = $this->service['slug'];
                $filename = "{$serviceSlug}_anfrage_{$this->reference}_{$timestamp}.pdf";
            }
            
            $filepath = $directory . '/' . $filename;
            
            // Save file
            $success = file_put_contents($filepath, $pdfContent) !== false;
            
            return [
                'success' => $success,
                'filepath' => $filepath,
                'filename' => $filename,
                'directory' => $directory,
                'is_production' => $this->isProduction
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'filepath' => null,
                'filename' => null
            ];
        }
    }

    /**
     * Get PDF content as string for email attachment
     */
    public function getPDFContent(): string
    {
        return $this->generatePDF();
    }

    /**
     * Get appropriate filename for the PDF
     */
    public function getFilename(): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $serviceSlug = $this->service['slug'];
        return "{$serviceSlug}_anfrage_{$this->reference}_{$timestamp}.pdf";
    }
}
