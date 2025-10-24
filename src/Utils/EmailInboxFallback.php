<?php

class EmailInboxFallback {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get mock emails for testing without IMAP
     */
    public function getEmails($limit = 50, $folder = 'INBOX') {
        // Return mock emails for testing
        return [
            [
                'id' => 1,
                'subject' => 'Test E-Mail 1',
                'from' => 'test@example.com',
                'date' => date('Y-m-d H:i:s'),
                'read' => false,
                'has_attachments' => false,
                'body_preview' => 'Dies ist eine Test-E-Mail für die Entwicklung des E-Mail-Systems.'
            ],
            [
                'id' => 2,
                'subject' => 'Kundenanfrage - Umzug',
                'from' => 'kunde@test.de',
                'date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'read' => true,
                'has_attachments' => true,
                'body_preview' => 'Guten Tag, ich benötige einen Kostenvoranschlag für einen Umzug.'
            ],
            [
                'id' => 3,
                'subject' => 'Angebot angefordert',
                'from' => 'info@musterfirma.de',
                'date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'read' => false,
                'has_attachments' => false,
                'body_preview' => 'Wir sind interessiert an Ihren Dienstleistungen.'
            ]
        ];
    }

    /**
     * Get mock email details
     */
    public function getEmailDetails($emailId) {
        $emails = [
            1 => [
                'id' => 1,
                'subject' => 'Test E-Mail 1',
                'from' => 'test@example.com',
                'to' => 'info@ds-allroundservice.de',
                'date' => date('Y-m-d H:i:s'),
                'read' => false,
                'has_attachments' => false,
                'body_html' => '<p>Dies ist eine Test-E-Mail für die Entwicklung des E-Mail-Systems.</p><p>Das System funktioniert korrekt, auch ohne IMAP-Erweiterung.</p>',
                'body_text' => 'Dies ist eine Test-E-Mail für die Entwicklung des E-Mail-Systems.\n\nDas System funktioniert korrekt, auch ohne IMAP-Erweiterung.',
                'attachments' => []
            ],
            2 => [
                'id' => 2,
                'subject' => 'Kundenanfrage - Umzug',
                'from' => 'kunde@test.de',
                'to' => 'info@ds-allroundservice.de',
                'date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'read' => true,
                'has_attachments' => true,
                'body_html' => '<p>Guten Tag,</p><p>ich benötige einen Kostenvoranschlag für einen Umzug von München nach Berlin.</p><p>Vielen Dank!</p>',
                'body_text' => 'Guten Tag,\n\nich benötige einen Kostenvoranschlag für einen Umzug von München nach Berlin.\n\nVielen Dank!',
                'attachments' => [
                    ['name' => 'inventar.pdf', 'size' => 245760]
                ]
            ],
            3 => [
                'id' => 3,
                'subject' => 'Angebot angefordert',
                'from' => 'info@musterfirma.de',
                'to' => 'info@ds-allroundservice.de',
                'date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'read' => false,
                'has_attachments' => false,
                'body_html' => '<p>Sehr geehrte Damen und Herren,</p><p>wir sind interessiert an Ihren Dienstleistungen im Bereich Entrümpelung.</p>',
                'body_text' => 'Sehr geehrte Damen und Herren,\n\nwir sind interessiert an Ihren Dienstleistungen im Bereich Entrümpelung.',
                'attachments' => []
            ]
        ];

        return $emails[$emailId] ?? null;
    }

    /**
     * Mark email as read/unread
     */
    public function markAsRead($emailId, $read = true) {
        // Mock implementation
        return true;
    }

    /**
     * Mark email as unread
     */
    public function markAsUnread($emailId) {
        // Mock implementation
        return true;
    }

    /**
     * Delete email
     */
    public function deleteEmail($emailId) {
        // Mock implementation
        return true;
    }

    /**
     * Get unread count
     */
    public function getUnreadCount() {
        return 2; // Mock unread count
    }

    /**
     * Test connection
     */
    public function testConnection() {
        return [
            'success' => false,
            'message' => 'IMAP-Erweiterung ist nicht installiert. Dies ist eine Fallback-Implementierung für Entwicklungszwecke.',
            'imap_available' => false,
            'recommendations' => [
                'Installieren Sie die PHP IMAP-Erweiterung',
                'Unter Windows: Aktivieren Sie extension=imap in php.ini',
                'Unter Linux: Installieren Sie php-imap Paket',
                'Starten Sie den Webserver nach der Installation neu'
            ]
        ];
    }
}