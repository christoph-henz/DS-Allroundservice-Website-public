<?php

require_once __DIR__ . '/EmailEventStore.php';

/**
 * E-Mail-Posteingang Verwaltung
 * Handles IMAP/POP3 email retrieval and management for the admin panel
 * Mit Event Sourcing und Snapshot-Unterstützung
 */
class EmailInbox
{
    private $db;
    private $imapServer;
    private $username;
    private $password;
    private $port;
    private $useSSL;
    private $protocol;
    private $connection;
    private $eventStore;
    
    public function __construct(PDO $database)
    {
        $this->db = $database;
        $this->eventStore = new EmailEventStore($database);
        $this->loadEmailSettings();
    }
    
    /**
     * Load email settings from database
     */
    private function loadEmailSettings(): void
    {
        try {
            $settings = [];
            $stmt = $this->db->prepare("
                SELECT setting_key, setting_value 
                FROM settings 
                WHERE setting_key LIKE 'email_%' AND is_public = 0
            ");
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $this->imapServer = $settings['email_imap_server'] ?? '';
            $this->port = (int)($settings['email_imap_port'] ?? 993);
            $this->username = $settings['email_username'] ?? '';
            $this->password = $this->decryptPassword($settings['email_password'] ?? '');
            $this->useSSL = (bool)($settings['email_use_ssl'] ?? true);
            $this->protocol = $settings['email_protocol'] ?? 'imap';
            
        } catch (Exception $e) {
            error_log("Error loading email settings: " . $e->getMessage());
            throw new Exception("E-Mail-Einstellungen konnten nicht geladen werden");
        }
    }
    
    /**
     * Decrypt stored password (simple base64 for now, should use proper encryption)
     */
    private function decryptPassword(string $encryptedPassword): string
    {
        if (empty($encryptedPassword)) {
            return '';
        }
        
        // Simple base64 decoding - in production use proper encryption
        return base64_decode($encryptedPassword);
    }
    
    /**
     * Encrypt password for storage
     */
    public static function encryptPassword(string $password): string
    {
        // Simple base64 encoding - in production use proper encryption
        return base64_encode($password);
    }
    
    /**
     * Connect to email server
     */
    private function connect(string $folder = 'INBOX'): bool
    {
        // Wenn bereits verbunden, prüfe ob es der gleiche Folder ist
        if ($this->connection) {
            // Für Folder-Wechsel müssen wir imap_reopen verwenden
            $sslFlag = $this->useSSL ? '/ssl' : '';
            $protocolFlag = $this->protocol === 'pop3' ? '/pop3' : '/imap';
            $connectionString = "{{$this->imapServer}:{$this->port}{$protocolFlag}{$sslFlag}/novalidate-cert}";
            
            if ($this->protocol === 'imap') {
                $connectionString .= $folder;
            }
            
            // Wechsle zum gewünschten Folder
            if (@imap_reopen($this->connection, $connectionString)) {
                error_log("📂 Switched to folder: $folder");
                return true;
            }
        }
        
        try {
            if (empty($this->imapServer) || empty($this->username) || empty($this->password)) {
                throw new Exception("E-Mail-Einstellungen sind nicht vollständig konfiguriert");
            }
            
            // Build connection string
            $sslFlag = $this->useSSL ? '/ssl' : '';
            $protocolFlag = $this->protocol === 'pop3' ? '/pop3' : '/imap';
            $connectionString = "{{$this->imapServer}:{$this->port}{$protocolFlag}{$sslFlag}/novalidate-cert}";
            
            // For IMAP, add folder name
            if ($this->protocol === 'imap') {
                $connectionString .= $folder;
            }
            
            error_log("Attempting email connection to: " . str_replace($this->password, '***', $connectionString));
            
            $this->connection = imap_open($connectionString, $this->username, $this->password);
            
            if (!$this->connection) {
                $error = imap_last_error();
                throw new Exception("IMAP-Verbindung fehlgeschlagen: " . ($error ?: 'Unbekannter Fehler'));
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Email connection error: " . $e->getMessage());
            throw new Exception("E-Mail-Server-Verbindung fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    /**
     * Disconnect from email server
     */
    private function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }
    
    /**
     * Get emails from inbox
     */
    public function getEmails(int $limit = 50, string $folder = 'INBOX'): array
    {
        try {
            error_log("📂 getEmails called with folder: $folder");
            
            if (!$this->connect($folder)) {
                throw new Exception("Keine Verbindung zum E-Mail-Server");
            }
            
            $emailCount = imap_num_msg($this->connection);
            
            if ($emailCount === 0) {
                return [];
            }
            
            $emails = [];
            $start = max(1, $emailCount - $limit + 1);
            
            // Get emails in reverse order (newest first)
            for ($i = $emailCount; $i >= $start; $i--) {
                try {
                    $uid = imap_uid($this->connection, $i);
                    $header = imap_headerinfo($this->connection, $i);
                    $structure = imap_fetchstructure($this->connection, $i);
                    
                    // Get email body
                    $body = $this->getEmailBody($i, $structure);
                    
                    // Extract sender information
                    $from = $this->extractEmailAddress($header->from ?? []);
                    $to = $this->extractEmailAddress($header->to ?? []);
                    $cc = $this->extractEmailAddress($header->cc ?? []);
                    
                    // Check if email has attachments
                    $hasAttachments = $this->hasAttachments($structure);
                    
                    // 📦 RICH SNAPSHOT: Alle Flags extrahieren für vollständige Snapshots
                    $emails[] = [
                        'id' => $i,
                        'uid' => (string)$uid,
                        'message_id' => $header->message_id ?? '',
                        'from' => $from,
                        'to' => $to,
                        'cc' => $cc,
                        'bcc' => $this->extractEmailAddress($header->bcc ?? []),
                        'reply_to' => $this->extractEmailAddress($header->reply_to ?? []),
                        'subject' => $this->decodeHeaderText($header->subject ?? 'Kein Betreff'),
                        'date' => date('Y-m-d H:i:s', $header->udate ?? time()),
                        'body' => $body,
                        'body_preview' => $this->getBodyPreview($body),
                        'size' => $header->Size ?? 0,
                        'has_attachments' => $hasAttachments,
                        'attachment_count' => $hasAttachments ? $this->getAttachmentCount($structure) : 0,
                        // Flags (wichtig für Snapshot-Performance!)
                        'unread' => ($header->Unseen ?? 'U') === 'U',
                        'seen' => ($header->Unseen ?? 'U') !== 'U',
                        'flagged' => ($header->Flagged ?? '') === 'F',
                        'answered' => ($header->Answered ?? '') === 'A',
                        'draft' => ($header->Draft ?? '') === 'X',
                        'deleted' => ($header->Deleted ?? '') === 'D',
                        'recent' => ($header->Recent ?? '') === 'N',
                    ];
                    
                } catch (Exception $e) {
                    error_log("Error processing email $i: " . $e->getMessage());
                    continue;
                }
            }
            
            return $emails;
            
        } catch (Exception $e) {
            error_log("Error getting emails: " . $e->getMessage());
            throw new Exception("Fehler beim Abrufen der E-Mails: " . $e->getMessage());
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Get emails using Event Sourcing with Snapshots
     * Optimierte Version die nur neue E-Mails seit dem letzten Snapshot lädt
     */
    public function getEmailsWithEventSourcing(int $limit = 50, string $folder = 'INBOX'): array 
    {
        error_log("📦 Starting Event Sourcing email load for folder: $folder");
        return $this->eventStore->loadEmailsWithEventSourcing($this, $folder);
    }
    
    /**
     * Get emails since specific UID (für Event Sourcing)
     * Lädt nur neue E-Mails seit einer bestimmten UID
     */
    public function getEmailsSinceUID(string $lastUid, int $limit = 100, string $folder = 'INBOX'): array 
    {
        try {
            error_log("📂 getEmailsSinceUID called with folder: $folder");
            
            if (!$this->connect($folder)) {
                throw new Exception("Keine Verbindung zum E-Mail-Server");
            }
            
            $emailCount = imap_num_msg($this->connection);
            if ($emailCount === 0) {
                return [];
            }
            
            // Finde die Position der letzten bekannten UID
            $lastPosition = 0;
            for ($i = 1; $i <= $emailCount; $i++) {
                $uid = imap_uid($this->connection, $i);
                if ($uid && $uid <= $lastUid) {
                    $lastPosition = $i;
                } else {
                    break;
                }
            }
            
            // Lade nur E-Mails nach der letzten Position
            $emails = [];
            $start = $lastPosition + 1;
            $end = min($emailCount, $start + $limit - 1);
            
            error_log("Loading new emails from position $start to $end (lastUID: $lastUid)");
            
            for ($i = $end; $i >= $start; $i--) {
                try {
                    $uid = imap_uid($this->connection, $i);
                    $header = imap_headerinfo($this->connection, $i);
                    $structure = imap_fetchstructure($this->connection, $i);
                    
                    if ($uid && $uid > $lastUid) {
                        $body = $this->getEmailBody($i, $structure);
                        $from = $this->extractEmailAddress($header->from ?? []);
                        $to = $this->extractEmailAddress($header->to ?? []);
                        $cc = $this->extractEmailAddress($header->cc ?? []);
                        $hasAttachments = $this->hasAttachments($structure);
                        
                        $emails[] = [
                            'id' => $i,
                            'uid' => (string)$uid,
                            'message_id' => $header->message_id ?? '',
                            'from' => $from,
                            'to' => $to,
                            'cc' => $cc,
                            'subject' => $this->decodeHeaderText($header->subject ?? 'Kein Betreff'),
                            'date' => date('Y-m-d H:i:s', $header->udate ?? time()),
                            'body' => $body,
                            'body_preview' => $this->getBodyPreview($body),
                            'unread' => !($header->Unseen ?? false ? false : true),
                            'flagged' => ($header->Flagged ?? false),
                            'seen' => ($header->Unseen ?? false ? false : true),
                            'size' => $header->Size ?? 0,
                            'has_attachments' => $hasAttachments,
                            'attachment_count' => $hasAttachments ? $this->getAttachmentCount($structure) : 0
                        ];
                    }
                    
                } catch (Exception $e) {
                    error_log("Error processing email $i: " . $e->getMessage());
                    continue;
                }
            }
            
            error_log("Loaded " . count($emails) . " new emails");
            return $emails;
            
        } catch (Exception $e) {
            error_log("Error getting emails since UID: " . $e->getMessage());
            return [];
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Get specific email details
     */
    public function getEmailDetails(int $emailId): ?array
    {
        try {
            if (!$this->connect()) {
                throw new Exception("Keine Verbindung zum E-Mail-Server");
            }
            
            $header = imap_headerinfo($this->connection, $emailId);
            $structure = imap_fetchstructure($this->connection, $emailId);
            
            if (!$header) {
                return null;
            }
            
            // Get UID for this message
            $uid = imap_uid($this->connection, $emailId);
            
            $body = $this->getEmailBody($emailId, $structure);
            $attachments = $this->getAttachments($emailId, $structure);
            
            // ⚡ PERFORMANCE: Only clean body and subject - these are the main sources of encoding issues
            // Email addresses, message IDs, and attachment metadata are typically ASCII-safe
            $cleanBody = $this->ensureUtf8($body);
            $cleanSubject = $this->ensureUtf8($this->decodeHeaderText($header->subject ?? 'Kein Betreff'));
            
            return [
                'id' => $emailId,
                'uid' => (string)$uid,
                'message_id' => $header->message_id ?? '',
                'from' => $this->extractEmailAddress($header->from ?? []),
                'to' => $this->extractEmailAddress($header->to ?? []),
                'cc' => $this->extractEmailAddress($header->cc ?? []),
                'bcc' => $this->extractEmailAddress($header->bcc ?? []),
                'reply_to' => $this->extractEmailAddress($header->reply_to ?? []),
                'subject' => $cleanSubject,
                'date' => date('Y-m-d H:i:s', $header->udate ?? time()),
                'body' => $cleanBody,
                'unread' => !($header->Unseen ?? false ? false : true),
                'flagged' => ($header->Flagged ?? false),
                'size' => $header->Size ?? 0,
                'attachments' => $attachments
            ];
            
        } catch (Exception $e) {
            error_log("Error getting email details: " . $e->getMessage());
            return null;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Ensure string is valid UTF-8 - PERFORMANCE OPTIMIZED
     * Only converts when absolutely necessary
     */
    private function ensureUtf8(string $string): string
    {
        // Empty strings are always valid - FAST PATH
        if ($string === '') {
            return '';
        }
        
        // Check if already valid UTF-8 - FAST PATH (most common case)
        if (mb_check_encoding($string, 'UTF-8')) {
            return $string;
        }
        
        // SLOW PATH: Only reached for invalid UTF-8
        // Try only the most common encodings - prioritized by probability
        static $encodings = ['ISO-8859-1', 'Windows-1252', 'UTF-8'];
        
        foreach ($encodings as $encoding) {
            $converted = @mb_convert_encoding($string, 'UTF-8', $encoding);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }
        
        // Last resort: Remove invalid bytes
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }
    
    /**
     * Clean email addresses array to ensure UTF-8
     */
    private function cleanEmailAddresses(array $addresses): array
    {
        return array_map(function($addr) {
            return [
                'email' => $this->ensureUtf8($addr['email'] ?? ''),
                'name' => $this->ensureUtf8($addr['name'] ?? ''),
                'full' => $this->ensureUtf8($addr['full'] ?? '')
            ];
        }, $addresses);
    }
    
    /**
     * Clean attachments array to ensure UTF-8
     */
    private function cleanAttachments(array $attachments): array
    {
        return array_map(function($att) {
            return [
                'filename' => $this->ensureUtf8($att['filename'] ?? ''),
                'size' => $att['size'] ?? 0,
                'type' => $this->ensureUtf8($att['type'] ?? ''),
                'encoding' => $this->ensureUtf8($att['encoding'] ?? '')
            ];
        }, $attachments);
    }
    
    /**
     * Mark email as read
     */
    public function markAsRead(int $emailId): bool
    {
        try {
            if (!$this->connect()) {
                return false;
            }
            
            return imap_setflag_full($this->connection, $emailId, "\\Seen");
            
        } catch (Exception $e) {
            error_log("Error marking email as read: " . $e->getMessage());
            return false;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Mark email as unread
     */
    public function markAsUnread(int $emailId): bool
    {
        try {
            if (!$this->connect()) {
                return false;
            }
            
            return imap_clearflag_full($this->connection, $emailId, "\\Seen");
            
        } catch (Exception $e) {
            error_log("Error marking email as unread: " . $e->getMessage());
            return false;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Delete email
     */
    public function deleteEmail(int $emailId): bool
    {
        try {
            if (!$this->connect()) {
                return false;
            }
            
            $result = imap_delete($this->connection, $emailId);
            imap_expunge($this->connection); // Permanently delete
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error deleting email: " . $e->getMessage());
            return false;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Event Sourcing-unterstützte E-Mail-Aktionen
     */
    
    /**
     * Mark email as read with Event Sourcing
     */
    public function markAsReadWithEvents(int $emailId): bool
    {
        try {
            error_log("🔍 markAsReadWithEvents called with emailId: " . $emailId);
            
            // Get email UID first
            if (!$this->connect()) {
                error_log("❌ Failed to connect to email server");
                return false;
            }
            
            error_log("✅ Connected to email server");
            
            $uid = imap_uid($this->connection, $emailId);
            error_log("🔍 Email UID for message $emailId: " . ($uid ?: 'NULL'));
            
            $result = imap_setflag_full($this->connection, $emailId, "\\Seen");
            error_log("📧 imap_setflag_full result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if (imap_last_error()) {
                error_log("❌ IMAP Error: " . imap_last_error());
            }
            
            if ($result && $uid) {
                // Add event to event store
                error_log("📝 Adding event to event store for UID: " . $uid);
                $eventResult = $this->eventStore->markEmailAsRead((string)$uid);
                error_log("📝 Event store result: " . ($eventResult ? 'SUCCESS' : 'FAILED'));
            } else {
                error_log("❌ IMAP operation failed or no UID - result: " . ($result ? 'true' : 'false') . ", uid: " . ($uid ?: 'NULL'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("❌ Error marking email as read: " . $e->getMessage());
            return false;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Mark email as read by UID
     */
    public function markAsReadByUID(string $emailUID): bool
    {
        try {
            error_log("🔍 markAsReadByUID called with UID: " . $emailUID);
            
            if (!$this->connect()) {
                error_log("❌ Failed to connect to email server");
                return false;
            }
            
            error_log("✅ Connected to email server");
            
            // Find message number by UID
            $messageNumber = $this->findMessageNumberByUID($emailUID);
            if (!$messageNumber) {
                error_log("❌ Could not find message number for UID: " . $emailUID);
                return false;
            }
            
            error_log("📧 Found message number {$messageNumber} for UID {$emailUID}");
            
            $result = imap_setflag_full($this->connection, $messageNumber, "\\Seen");
            error_log("📧 imap_setflag_full result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if (imap_last_error()) {
                error_log("❌ IMAP Error: " . imap_last_error());
            }
            
            if ($result) {
                // Add event to event store
                error_log("📝 Adding event to event store for UID: " . $emailUID);
                $eventResult = $this->eventStore->markEmailAsRead($emailUID);
                error_log("📝 Event store result: " . ($eventResult ? 'SUCCESS' : 'FAILED'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("❌ Error marking email as read by UID: " . $e->getMessage());
            return false;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Find message number by UID
     */
    private function findMessageNumberByUID(string $targetUID): ?int
    {
        try {
            $emailCount = imap_num_msg($this->connection);
            
            for ($i = 1; $i <= $emailCount; $i++) {
                $uid = imap_uid($this->connection, $i);
                if ($uid && (string)$uid === $targetUID) {
                    return $i;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("❌ Error finding message number by UID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Mark email as unread with Event Sourcing
     */
    public function markAsUnreadWithEvents(int $emailId): bool
    {
        try {
            if (!$this->connect()) {
                return false;
            }
            
            $uid = imap_uid($this->connection, $emailId);
            $result = imap_clearflag_full($this->connection, $emailId, "\\Seen");
            
            if ($result && $uid) {
                // Add event to event store
                $this->eventStore->markEmailAsUnread((string)$uid);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error marking email as unread: " . $e->getMessage());
            return false;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Delete email with Event Sourcing
     */
    public function deleteEmailWithEvents(int $emailId): bool
    {
        try {
            if (!$this->connect()) {
                return false;
            }
            
            $uid = imap_uid($this->connection, $emailId);
            $result = imap_delete($this->connection, $emailId);
            
            if ($result) {
                imap_expunge($this->connection); // Permanently delete
                
                if ($uid) {
                    // Add event to event store
                    $this->eventStore->deleteEmail((string)$uid);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error deleting email: " . $e->getMessage());
            return false;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Force create new snapshot
     */
    public function createSnapshot(): bool
    {
        try {
            $emails = $this->getEmails();
            return $this->eventStore->createSnapshot($emails, 'manual');
        } catch (Exception $e) {
            error_log("Error creating snapshot: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get Event Store statistics
     */
    public function getEventStoreStats(): array
    {
        return $this->eventStore->getEventStats();
    }
    
    /**
     * Cleanup old events and snapshots
     */
    public function cleanupEventStore(int $daysToKeep = 30, int $snapshotsToKeep = 5): array
    {
        $eventsDeleted = $this->eventStore->cleanupOldEvents($daysToKeep);
        $snapshotsDeleted = $this->eventStore->cleanupOldSnapshots($snapshotsToKeep);
        
        return [
            'events_deleted' => $eventsDeleted,
            'snapshots_deleted' => $snapshotsDeleted
        ];
    }
    
    /**
     * Get unread email count
     */
    public function getUnreadCount(): int
    {
        try {
            if (!$this->connect()) {
                return 0;
            }
            
            $status = imap_status($this->connection, "{{$this->imapServer}:{$this->port}/imap/ssl}INBOX", SA_UNSEEN);
            return $status->unseen ?? 0;
            
        } catch (Exception $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Extract email addresses from header objects
     */
    private function extractEmailAddress(array $addresses): array
    {
        $emails = [];
        
        foreach ($addresses as $addr) {
            $email = '';
            $name = '';
            
            if (isset($addr->mailbox) && isset($addr->host)) {
                $email = $addr->mailbox . '@' . $addr->host;
            }
            
            if (isset($addr->personal)) {
                $name = $this->decodeHeaderText($addr->personal);
            }
            
            $emails[] = [
                'email' => $email,
                'name' => $name,
                'full' => $name ? "$name <$email>" : $email
            ];
        }
        
        return $emails;
    }
    
    /**
     * Decode header text (handles encoding)
     */
    private function decodeHeaderText(string $text): string
    {
        $decoded = imap_mime_header_decode($text);
        $result = '';
        
        foreach ($decoded as $part) {
            $result .= $part->text;
        }
        
        return $result;
    }
    
    /**
     * Get email body
     */
    private function getEmailBody(int $emailId, $structure): string
    {
        $body = '';
        
        if ($structure->type == 1 && isset($structure->parts)) { // Multipart
            foreach ($structure->parts as $index => $partStructure) {
                try {
                    $partNumber = $index + 1;
                    // FT_PEEK prevents marking email as read when fetching body
                    $part = imap_fetchbody($this->connection, $emailId, $partNumber, FT_PEEK);
                    
                    if (isset($partStructure->subtype)) {
                        if ($partStructure->subtype == 'HTML') {
                            $body = $this->decodeEmailPart($part, $partStructure->encoding ?? 0);
                            break;
                        } elseif ($partStructure->subtype == 'PLAIN' && empty($body)) {
                            $body = $this->decodeEmailPart($part, $partStructure->encoding ?? 0);
                        }
                    }
                } catch (Exception $e) {
                    error_log('Error processing email part ' . ($index + 1) . ': ' . $e->getMessage());
                    continue;
                }
            }
        } else { // Single part
            // FT_PEEK prevents marking email as read when fetching body
            $body = imap_fetchbody($this->connection, $emailId, "1", FT_PEEK);
            $body = $this->decodeEmailPart($body, $structure->encoding);
        }
        
        return $body;
    }
    
    /**
     * Decode email part based on encoding
     */
    private function decodeEmailPart(string $data, int $encoding): string
    {
        switch ($encoding) {
            case 1: // 8bit
                return imap_8bit($data);
            case 2: // Binary
                return imap_binary($data);
            case 3: // Base64
                return base64_decode($data);
            case 4: // Quoted-printable
                return quoted_printable_decode($data);
            default: // Plain text
                return $data;
        }
    }
    
    /**
     * Get body preview (first 150 chars)
     */
    private function getBodyPreview(string $body): string
    {
        // Strip HTML tags
        $preview = strip_tags($body);
        
        // Clean up whitespace
        $preview = preg_replace('/\s+/', ' ', $preview);
        $preview = trim($preview);
        
        // Truncate to 150 characters
        if (strlen($preview) > 150) {
            $preview = substr($preview, 0, 150) . '...';
        }
        
        return $preview;
    }
    
    /**
     * Check if email has attachments
     */
    private function hasAttachments($structure): bool
    {
        if (isset($structure->parts)) {
            foreach ($structure->parts as $part) {
                if (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get attachment count
     */
    private function getAttachmentCount($structure): int
    {
        $count = 0;
        
        if (isset($structure->parts)) {
            foreach ($structure->parts as $part) {
                if (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get attachments
     */
    private function getAttachments(int $emailId, $structure): array
    {
        $attachments = [];
        
        if (isset($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                if (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
                    $filename = '';
                    
                    if (isset($part->dparameters)) {
                        foreach ($part->dparameters as $param) {
                            if (strtolower($param->attribute) == 'filename') {
                                $filename = $param->value;
                                break;
                            }
                        }
                    }
                    
                    $attachments[] = [
                        'filename' => $filename,
                        'size' => $part->bytes ?? 0,
                        'type' => $part->subtype ?? 'unknown',
                        'part_number' => $index + 1
                    ];
                }
            }
        }
        
        return $attachments;
    }
    
    /**
     * Get a specific attachment from an email by UID and attachment index
     * 
     * @param string $uid Email UID
     * @param int $attachmentIndex Zero-based attachment index
     * @return array|null Attachment data with 'filename', 'content', 'mime_type', 'size'
     */
    public function getAttachment(string $uid, int $attachmentIndex): ?array
    {
        error_log("📎 EmailInbox::getAttachment() - UID: $uid, Index: $attachmentIndex");
        
        if (!$this->connect()) {
            error_log("❌ Failed to connect to IMAP");
            return null;
        }
        
        try {
            // Convert UID to message number
            $msgNo = imap_msgno($this->connection, $uid);
            
            if (!$msgNo) {
                error_log("❌ Could not find message number for UID: $uid");
                return null;
            }
            
            error_log("✅ Message number: $msgNo");
            
            // Get email structure
            $structure = imap_fetchstructure($this->connection, $msgNo);
            
            if (!$structure) {
                error_log("❌ Could not fetch structure for message: $msgNo");
                return null;
            }
            
            // Find attachments
            $currentIndex = 0;
            $targetPart = null;
            $targetPartNumber = null;
            
            if (isset($structure->parts)) {
                foreach ($structure->parts as $partIndex => $part) {
                    if (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
                        if ($currentIndex === $attachmentIndex) {
                            $targetPart = $part;
                            $targetPartNumber = $partIndex + 1;
                            error_log("✅ Found attachment at part number: $targetPartNumber");
                            break;
                        }
                        $currentIndex++;
                    }
                }
            }
            
            if (!$targetPart) {
                error_log("❌ Attachment not found at index: $attachmentIndex");
                return null;
            }
            
            // Get filename - try both dparameters and parameters
            $filename = 'attachment';
            
            // First try dparameters (disposition parameters)
            if (isset($targetPart->dparameters)) {
                foreach ($targetPart->dparameters as $param) {
                    if (strtolower($param->attribute) == 'filename') {
                        $filename = $this->decodeHeaderText($param->value);
                        error_log("📄 Filename from dparameters: $filename");
                        break;
                    }
                }
            }
            
            // Fallback to parameters if filename not found
            if ($filename === 'attachment' && isset($targetPart->parameters)) {
                foreach ($targetPart->parameters as $param) {
                    if (strtolower($param->attribute) == 'name') {
                        $filename = $this->decodeHeaderText($param->value);
                        error_log("📄 Filename from parameters: $filename");
                        break;
                    }
                }
            }
            
            // Get MIME type
            $mimeType = $this->getMimeType($targetPart);
            error_log("🔍 MIME Type: $mimeType");
            
            // Get encoding type
            $encoding = $targetPart->encoding ?? 0;
            $encodingNames = ['7BIT', '8BIT', 'BINARY', 'BASE64', 'QUOTED-PRINTABLE', 'OTHER'];
            $encodingName = $encodingNames[$encoding] ?? 'UNKNOWN';
            error_log("🔐 Encoding: $encodingName (code: $encoding)");
            
            // Fetch attachment content
            $content = imap_fetchbody($this->connection, $msgNo, (string)$targetPartNumber);
            $originalSize = strlen($content);
            error_log("📦 Raw content size: $originalSize bytes");
            
            // Decode content based on encoding
            switch ($encoding) {
                case 0: // 7BIT
                    // No decoding needed
                    error_log("ℹ️ 7BIT encoding - no decoding needed");
                    break;
                    
                case 1: // 8BIT
                    $content = imap_8bit($content);
                    error_log("ℹ️ 8BIT decoded");
                    break;
                    
                case 2: // BINARY
                    // No decoding needed
                    error_log("ℹ️ BINARY encoding - no decoding needed");
                    break;
                    
                case 3: // BASE64
                    $content = base64_decode($content);
                    if ($content === false) {
                        error_log("❌ BASE64 decode failed!");
                        return null;
                    }
                    error_log("ℹ️ BASE64 decoded: " . strlen($content) . " bytes");
                    break;
                    
                case 4: // QUOTED-PRINTABLE
                    $content = quoted_printable_decode($content);
                    error_log("ℹ️ QUOTED-PRINTABLE decoded");
                    break;
                    
                case 5: // OTHER
                default:
                    error_log("⚠️ Unknown encoding ($encoding) - using raw content");
                    break;
            }
            
            $finalSize = strlen($content);
            error_log("✅ Final content size: $finalSize bytes");
            
            return [
                'filename' => $filename,
                'content' => $content,
                'mime_type' => $mimeType,
                'size' => $finalSize,
                'encoding' => $encodingName
            ];
            
        } catch (Exception $e) {
            error_log("❌ Error getting attachment: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return null;
        } finally {
            $this->disconnect();
        }
    }
    
    /**
     * Get MIME type from part structure
     */
    private function getMimeType($part): string
    {
        $primaryTypes = ['TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER'];
        
        $primaryType = isset($part->type) ? $primaryTypes[$part->type] : 'APPLICATION';
        $subType = isset($part->subtype) ? $part->subtype : 'OCTET-STREAM';
        
        return strtolower($primaryType . '/' . $subType);
    }
    
    /**
     * Test email connection
     */
    public function testConnection(): array
    {
        try {
            if ($this->connect()) {
                $emailCount = imap_num_msg($this->connection);
                return [
                    'success' => true,
                    'message' => 'Verbindung erfolgreich',
                    'email_count' => $emailCount
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Verbindung fehlgeschlagen'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } finally {
            $this->disconnect();
        }
    }
}