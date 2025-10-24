<?php declare(strict_types=1);

namespace DSAllround\Models;

class Offer
{
    private \PDO $database;
    
    public function __construct(\PDO $database)
    {
        $this->database = $database;
    }
    
    /**
     * Erstellt ein neues Angebot
     */
    public function createOffer(array $data): array
    {
        $offerNumber = $this->generateOfferNumber();
        
        $stmt = $this->database->prepare(
            "INSERT INTO offers (
                submission_id, offer_number, customer_name, customer_email, 
                customer_phone, service_id, service_name, pricing_items,
                total_net, total_vat, total_gross, vat_rate, notes, terms,
                valid_until, execution_date, pdf_path, created_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
        );
        
        $stmt->execute([
            $data['submission_id'],
            $offerNumber,
            $data['customer_name'],
            $data['customer_email'] ?? null,
            $data['customer_phone'] ?? null,
            $data['service_id'],
            $data['service_name'],
            json_encode($data['pricing_items']),
            $data['total_net'],
            $data['total_vat'],
            $data['total_gross'],
            $data['vat_rate'] ?? 19.00,
            $data['notes'] ?? null,
            $data['terms'] ?? null,
            $data['valid_until'] ?? null,
            $data['execution_date'] ?? null,
            $data['pdf_path'] ?? null,
            $data['created_by'] ?? 'admin'
        ]);
        
        $offerId = $this->database->lastInsertId();
        
        return [
            'id' => $offerId,
            'offer_number' => $offerNumber
        ];
    }
    
    /**
     * Aktualisiert den Status eines Angebots
     */
    public function updateOfferStatus(int $offerId, int $status): bool
    {
        $stmt = $this->database->prepare(
            "UPDATE offers SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        
        return $stmt->execute([$status, $offerId]);
    }
    
    /**
     * Markiert Angebot als versendet
     */
    public function markAsSent(int $offerId, string $pdfPath = null): bool
    {
        $stmt = $this->database->prepare(
            "UPDATE offers SET status = 1, sent_at = CURRENT_TIMESTAMP, pdf_path = COALESCE(?, pdf_path) WHERE id = ?"
        );
        
        return $stmt->execute([$pdfPath, $offerId]);
    }
    
    /**
     * Holt alle Angebote für eine Submission
     */
    public function getOffersBySubmission(int $submissionId): array
    {
        $stmt = $this->database->prepare(
            "SELECT * FROM offers WHERE submission_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$submissionId]);
        
        $offers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Debug: Log the raw data from database
        error_log("Offers from DB for submission $submissionId: " . json_encode($offers));
        
        // JSON-Daten dekodieren
        foreach ($offers as &$offer) {
            // Debug: Check if ID exists
            if (!isset($offer['id']) || $offer['id'] === null) {
                error_log("Warning: Offer missing ID: " . json_encode($offer));
            }
            
            if ($offer['pricing_items']) {
                $offer['pricing_items'] = json_decode($offer['pricing_items'], true);
            }
        }
        
        return $offers;
    }
    
    /**
     * Holt ein Angebot nach ID
     */
    public function getOfferById(int $offerId): ?array
    {
        $stmt = $this->database->prepare(
            "SELECT o.*, s.name as service_display_name 
             FROM offers o 
             JOIN services s ON o.service_id = s.id 
             WHERE o.id = ?"
        );
        $stmt->execute([$offerId]);
        
        $offer = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($offer) {
            // JSON-Daten dekodieren
            $offer['pricing_items'] = json_decode($offer['pricing_items'], true);
        }
        
        return $offer ?: null;
    }
    
    /**
     * Generiert eine eindeutige Angebotsnummer
     */
    private function generateOfferNumber(): string
    {
        $prefix = 'ANG';
        $date = date('ymd');
        $random = str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        
        $offerNumber = "{$prefix}-{$date}-{$random}";
        
        // Prüfen ob Nummer bereits existiert
        $stmt = $this->database->prepare("SELECT COUNT(*) FROM offers WHERE offer_number = ?");
        $stmt->execute([$offerNumber]);
        
        if ($stmt->fetchColumn() > 0) {
            // Rekursiv neue Nummer generieren
            return $this->generateOfferNumber();
        }
        
        return $offerNumber;
    }
    
    /**
     * Holt Statistiken für Angebote
     */
    public function getOfferStats(): array
    {
        $stmt = $this->database->prepare(
            "SELECT 
                status,
                COUNT(*) as count,
                SUM(total_gross) as total_value
             FROM offers 
             GROUP BY status"
        );
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Sucht nach Angeboten mit Filtern
     */
    public function searchOffers(array $filters = []): array
    {
        $sql = "SELECT o.*, s.name as service_display_name 
                FROM offers o 
                JOIN services s ON o.service_id = s.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND o.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['service_id'])) {
            $sql .= " AND o.service_id = ?";
            $params[] = $filters['service_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(o.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(o.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (o.customer_name LIKE ? OR o.offer_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        
        $offers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // JSON-Daten dekodieren
        foreach ($offers as &$offer) {
            if ($offer['pricing_items']) {
                $offer['pricing_items'] = json_decode($offer['pricing_items'], true);
            }
        }
        
        return $offers;
    }
    
    /**
     * Formatiert den Angebotsstatus
     */
    public function getStatusText(int $status): string
    {
        $statusMap = [
            0 => 'Entwurf',
            1 => 'Versendet', 
            2 => 'Angenommen',
            3 => 'Abgelehnt',
            4 => 'Abgelaufen'
        ];
        
        return $statusMap[$status] ?? 'Unbekannt';
    }
    
    /**
     * Formatiert den Angebotsstatus als Badge
     */
    public function getStatusBadge(int $status): string
    {
        $badgeConfig = [
            0 => ['class' => 'secondary', 'text' => 'Entwurf'],
            1 => ['class' => 'info', 'text' => 'Versendet'],
            2 => ['class' => 'success', 'text' => 'Angenommen'],
            3 => ['class' => 'danger', 'text' => 'Abgelehnt'],
            4 => ['class' => 'warning', 'text' => 'Abgelaufen']
        ];
        
        $config = $badgeConfig[$status] ?? ['class' => 'secondary', 'text' => 'Unbekannt'];
        
        return "<span class=\"badge badge-{$config['class']}\">{$config['text']}</span>";
    }
}