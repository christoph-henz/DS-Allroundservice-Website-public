<?php

/**
 * EmailEventStore - Event Sourcing System für E-Mail Management
 * Implementiert Snapshots zur Performance-Optimierung
 */

class EmailEventStore {
    private $db;
    private $debug = true;
    
    // Event Types
    const EVENT_EMAIL_RECEIVED = 'email_received';
    const EVENT_EMAIL_READ = 'email_read';
    const EVENT_EMAIL_UNREAD = 'email_unread';
    const EVENT_EMAIL_DELETED = 'email_deleted';
    const EVENT_EMAIL_MOVED = 'email_moved';
    const EVENT_SNAPSHOT_CREATED = 'snapshot_created';
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeTables();
    }
    
    /**
     * Initialisiert die Event Store Tabellen
     * MySQL/MariaDB kompatibel
     */
    private function initializeTables() {
        // Events Table - speichert alle E-Mail Events
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                email_uid VARCHAR(255) NOT NULL,
                event_data TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                sequence_number INT,
                INDEX idx_email_uid (email_uid),
                INDEX idx_timestamp (timestamp),
                INDEX idx_sequence (sequence_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Snapshots Table - speichert E-Mail Snapshots
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_snapshots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                snapshot_type VARCHAR(50) DEFAULT 'full',
                snapshot_data LONGTEXT NOT NULL,
                email_count INT NOT NULL,
                last_uid VARCHAR(255),
                last_sequence_number INT,
                folder VARCHAR(100) DEFAULT 'INBOX',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_active TINYINT(1) DEFAULT 1,
                INDEX idx_created_at (created_at),
                INDEX idx_active (is_active),
                INDEX idx_folder (folder)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Add folder column if it doesn't exist (for existing databases)
        try {
            $this->db->exec("ALTER TABLE email_snapshots ADD COLUMN folder VARCHAR(100) DEFAULT 'INBOX'");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }
        
        // E-Mail State Table - aktuelle States der E-Mails
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_states (
                email_uid VARCHAR(255) PRIMARY KEY,
                current_state TEXT NOT NULL,
                last_event_id INT,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (last_event_id) REFERENCES email_events(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Sequence Counter
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_sequence (
                counter INT PRIMARY KEY DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Initialize sequence if empty
        $count = $this->db->query("SELECT COUNT(*) FROM email_sequence")->fetchColumn();
        if ($count == 0) {
            $this->db->exec("INSERT INTO email_sequence (counter) VALUES (1)");
        }
    }
    
    /**
     * Erstellt ein neues Event
     */
    public function addEvent($eventType, $emailUid, $eventData = null) {
        try {
            $sequenceNumber = $this->getNextSequenceNumber();
            
            $stmt = $this->db->prepare("
                INSERT INTO email_events (event_type, email_uid, event_data, sequence_number) 
                VALUES (?, ?, ?, ?)
            ");
            
            // Clean UTF-8 data before JSON encoding
            $cleanEventData = $eventData ? $this->aggressiveUtf8Clean($eventData) : null;
            $eventDataJson = $cleanEventData ? json_encode($cleanEventData, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null;
            $stmt->execute([$eventType, $emailUid, $eventDataJson, $sequenceNumber]);
            
            $eventId = $this->db->lastInsertId();
            
            // Update email state
            $this->updateEmailState($emailUid, $eventData, $eventId);
            
            $this->debugLog("Event added: $eventType for UID $emailUid (Sequence: $sequenceNumber)");
            
            return $eventId;
        } catch (Exception $e) {
            error_log("Failed to add event: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Lädt E-Mails mit Event Sourcing (mit Snapshot-Optimierung)
     */
    public function loadEmailsWithEventSourcing($emailInbox, string $folder = 'INBOX') {
        try {
            $this->debugLog("Loading emails with event sourcing for folder: $folder");
            
            // 1. Letzten Snapshot für diesen Folder laden
            $lastSnapshot = $this->getLastSnapshot($folder);
            
            if ($lastSnapshot) {
                $this->debugLog("Found snapshot from " . $lastSnapshot['created_at'] . " with " . $lastSnapshot['email_count'] . " emails");
                
                // 2. Snapshot-Daten laden und validieren
                $snapshotEmails = json_decode($lastSnapshot['snapshot_data'], true);
                
                if (!is_array($snapshotEmails)) {
                    error_log("❌ EventStore: Invalid snapshot data, falling back to full reload");
                    error_log("🔧 EventStore: About to call createSnapshot() from fallback path");
                    // Fallback: Vollständige Neuladen
                    $allEmails = $emailInbox->getEmails(50, $folder);
                    error_log("🔧 EventStore: Loaded " . count($allEmails) . " emails for new snapshot");
                    $this->createSnapshot($allEmails, 'full', $folder);
                    return $this->normalizeEmailStructure($allEmails);
                }
                
                error_log("📦 EventStore: Loaded " . count($snapshotEmails) . " emails from snapshot");
                // Debug output disabled due to UTF-8 encoding issues
                error_log("📦 EventStore: Snapshot data sample: [DEBUG DISABLED - UTF-8 ISSUES]");
                
                // 3. Neue E-Mails seit Snapshot laden
                $newEmails = $this->loadNewEmailsSinceSnapshot($emailInbox, $lastSnapshot['last_uid'], $folder);
                
                // 4. Events seit Snapshot anwenden
                $events = $this->getEventsSinceSequence($lastSnapshot['last_sequence_number'] ?: 0);
                error_log("📦 EventStore: Found " . count($events) . " events since sequence " . ($lastSnapshot['last_sequence_number'] ?: 0));
                
                if (count($events) > 0) {
                    error_log("📦 EventStore: First few events: " . json_encode(array_slice($events, 0, 3)));
                }
                
                $updatedEmails = $this->applyEventsToEmails($snapshotEmails, $events);
                error_log("📦 EventStore: Applied events to emails, result count: " . count($updatedEmails));
                
                // 5. Neue E-Mails hinzufügen
                $finalEmails = $this->mergeNewEmails($updatedEmails, $newEmails);
                
                // 6. Prüfen ob neuer Snapshot erstellt werden sollte
                // TEMPORÄR DEAKTIVIERT für Debugging
                // $this->checkCreateNewSnapshot($finalEmails, count($events));
                error_log("📦 EventStore: Snapshot creation temporarily disabled for debugging");
                
                // 7. E-Mail-Datenstruktur normalisieren
                $finalEmails = $this->normalizeEmailStructure($finalEmails);
                
                error_log("📦 EventStore: Returning " . count($finalEmails) . " total emails");
                
                // Prüfe ob neuer Snapshot erstellt werden sollte
                $this->checkCreateNewSnapshot($finalEmails, count($events), $folder);
                
                return $finalEmails;
            } else {
                $this->debugLog("No snapshot found, loading all emails and creating initial snapshot");
                
                // Erstmaliger Load - alle E-Mails laden und Snapshot erstellen
                $allEmails = $emailInbox->getEmails(50, $folder);
                $this->createSnapshot($allEmails, 'full', $folder);
                
                // E-Mail-Datenstruktur normalisieren
                $allEmails = $this->normalizeEmailStructure($allEmails);
                
                return $allEmails;
            }
            
        } catch (Exception $e) {
            error_log("Event sourcing failed: " . $e->getMessage());
            // Fallback: normale E-Mail-Ladung
            return $emailInbox->getEmails(50, $folder);
        }
    }
    
    /**
     * Lädt neue E-Mails seit letztem Snapshot
     */
    private function loadNewEmailsSinceSnapshot($emailInbox, $lastUid, string $folder = 'INBOX') {
        if (!$lastUid) {
            return [];
        }
        
        try {
            // IMAP: neue E-Mails seit bestimmter UID laden
            $newEmails = $emailInbox->getEmailsSinceUID($lastUid);
            
            // Events für neue E-Mails erstellen
            foreach ($newEmails as $email) {
                $this->addEvent(self::EVENT_EMAIL_RECEIVED, $email['uid'], $email);
            }
            
            $this->debugLog("Loaded " . count($newEmails) . " new emails since UID $lastUid in folder $folder");
            
            return $newEmails;
        } catch (Exception $e) {
            error_log("Failed to load new emails since snapshot: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Wendet Events auf E-Mail-Liste an
     */
    private function applyEventsToEmails($emails, $events) {
        error_log("📝 EventStore: applyEventsToEmails called with " . count($emails) . " emails and " . count($events) . " events");
        
        foreach ($events as $event) {
            $emailUid = $event['email_uid'];
            $eventType = $event['event_type'];
            $eventData = $event['event_data'] ? json_decode($event['event_data'], true) : null;
            
            error_log("📝 EventStore: Processing event {$eventType} for UID {$emailUid}");
            
            switch ($eventType) {
                case self::EVENT_EMAIL_READ:
                    $this->markEmailInList($emails, $emailUid, 'seen', true);
                    error_log("📧 EventStore: Marked email {$emailUid} as READ");
                    break;
                    
                case self::EVENT_EMAIL_UNREAD:
                    $this->markEmailInList($emails, $emailUid, 'seen', false);
                    error_log("📧 EventStore: Marked email {$emailUid} as UNREAD");
                    break;
                    
                case self::EVENT_EMAIL_DELETED:
                    $emails = $this->removeEmailFromList($emails, $emailUid);
                    error_log("📧 EventStore: Removed email {$emailUid}");
                    break;
                    
                case self::EVENT_EMAIL_MOVED:
                    if ($eventData && isset($eventData['folder'])) {
                        $this->moveEmailInList($emails, $emailUid, $eventData['folder']);
                        error_log("📧 EventStore: Moved email {$emailUid} to {$eventData['folder']}");
                    }
                    break;
            }
        }
        
        error_log("📝 EventStore: Finished applying events, returning " . count($emails) . " emails");
        return $emails;
    }
    
    /**
     * Erstellt einen neuen Snapshot
     */
    public function createSnapshot($emails, $type = 'full', string $folder = 'INBOX') {
        error_log("🔥🔥🔥 CORRECTED createSnapshot() method called for folder: $folder! 🔥🔥🔥");
        
        try {
            error_log("📦 EventStore: createSnapshot() called with " . count($emails) . " emails");
            
            if (count($emails) > 0) {
                // Debug output disabled due to UTF-8 encoding issues
                error_log("📦 EventStore: Email sample: [DEBUG DISABLED - UTF-8 ISSUES]");
            }
            
            // Normalisiere E-Mail-Daten für konsistente Speicherung
            $normalizedEmails = $this->normalizeEmailStructure($emails);
            
            error_log("📦 EventStore: After normalization: " . count($normalizedEmails) . " emails");
            
            if (count($normalizedEmails) > 0) {
                // Debug output disabled due to UTF-8 encoding issues
                error_log("📦 EventStore: Normalized sample: [DEBUG DISABLED - UTF-8 ISSUES]");
            }
            
            // Deaktiviere alte Snapshots
            $this->db->exec("UPDATE email_snapshots SET is_active = 0 WHERE is_active = 1");
            
            $emailCount = count($normalizedEmails);
            $lastUid = $this->getLastEmailUID($normalizedEmails);
            $lastSequence = $this->getCurrentSequenceNumber();
            
            error_log("📦 EventStore: Snapshot parameters - Count: $emailCount, UID: $lastUid, Seq: $lastSequence, Folder: $folder");
            
            $stmt = $this->db->prepare("
                INSERT INTO email_snapshots 
                (snapshot_type, snapshot_data, email_count, last_uid, last_sequence_number, folder) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            // E-Mails als JSON speichern (nicht nur Metadaten!)
            // FIX: UTF-8 Probleme vor JSON-Encoding bereinigen
            $cleanEmails = $this->aggressiveUtf8Clean($normalizedEmails);
            $snapshotData = $this->encodeWithFallback($cleanEmails);
            
            if ($snapshotData === false) {
                throw new Exception("All encoding strategies failed: " . json_last_error_msg());
            }
            
            error_log("📦 EventStore: JSON data size: " . strlen($snapshotData) . " bytes");
            error_log("📦 EventStore: JSON preview: " . substr($snapshotData, 0, 200));
            
            $result = $stmt->execute([$type, $snapshotData, $emailCount, $lastUid, $lastSequence, $folder]);
            
            if (!$result) {
                throw new Exception("Failed to insert snapshot into database");
            }
            
            $snapshotId = $this->db->lastInsertId();
            
            // Event für Snapshot-Erstellung (NUR Metadaten, nicht die E-Mails!)
            $this->addEvent(self::EVENT_SNAPSHOT_CREATED, 'system', [
                'snapshot_id' => $snapshotId,
                'email_count' => $emailCount,
                'type' => $type,
                'last_uid' => $lastUid
            ]);
            
            // Debug: Prüfe gespeicherte Daten
            error_log("📦 EventStore: Created full snapshot with $emailCount emails (UID: $lastUid, Seq: $lastSequence)");
            error_log("📦 EventStore: Snapshot data size: " . strlen($snapshotData) . " bytes");
            // Debug output disabled due to UTF-8 encoding issues
            error_log("📦 EventStore: First email sample: [DEBUG DISABLED - UTF-8 ISSUES]");
            
            return $snapshotId;
        } catch (Exception $e) {
            error_log("❌ Failed to create snapshot: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Prüft ob ein neuer Snapshot erstellt werden sollte
     */
    private function checkCreateNewSnapshot($emails, $eventCount, string $folder = 'INBOX') {
        // Kriterien für neuen Snapshot:
        // - Kein Snapshot vorhanden
        // - Mehr als 50 Events seit letztem Snapshot
        // - Letzter Snapshot älter als 1 Stunde
        // - Mehr als 1000 E-Mails im aktuellen State
        
        $lastSnapshot = $this->getLastSnapshot($folder);
        
        // FALL 1: Kein Snapshot vorhanden → Neuen erstellen
        if (!$lastSnapshot) {
            $this->debugLog("📦 Creating new snapshot: No snapshot exists for folder $folder");
            $this->createSnapshot($emails, 'auto', $folder);
            return true;
        }
        
        $shouldCreate = false;
        $reason = '';
        
        // FALL 2: Event-basiert
        if ($eventCount > 50) {
            $shouldCreate = true;
            $reason = "More than 50 events ($eventCount)";
        }
        
        // FALL 3: Zeit-basiert - Snapshot älter als 1 Stunde
        $snapshotAge = time() - strtotime($lastSnapshot['created_at']);
        if ($snapshotAge > 3600) { // 1 Stunde = 3600 Sekunden
            $shouldCreate = true;
            $reason = "Snapshot older than 1 hour (" . round($snapshotAge / 60) . " minutes)";
        }
        
        // FALL 4: Größe-basiert
        if (count($emails) > 1000 && count($emails) > $lastSnapshot['email_count'] * 1.2) {
            $shouldCreate = true;
            $reason = "Email count significantly increased";
        }
        
        if ($shouldCreate) {
            $this->debugLog("📦 Creating new snapshot: $reason");
            $this->createSnapshot($emails, 'auto', $folder);
            return true;
        }
        
        return false;
    }
    
    /**
     * Hilfsmethoden
     */
    private function getLastSnapshot(string $folder = 'INBOX') {
        $stmt = $this->db->prepare("
            SELECT * FROM email_snapshots 
            WHERE is_active = 1 AND folder = ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$folder]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getEventsSinceSequence($sequenceNumber) {
        $stmt = $this->db->prepare("
            SELECT * FROM email_events 
            WHERE sequence_number > ? 
            ORDER BY sequence_number ASC
        ");
        $stmt->execute([$sequenceNumber]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getNextSequenceNumber() {
        $this->db->exec("UPDATE email_sequence SET counter = counter + 1");
        return $this->db->query("SELECT counter FROM email_sequence")->fetchColumn();
    }
    
    private function getCurrentSequenceNumber() {
        return $this->db->query("SELECT counter FROM email_sequence")->fetchColumn();
    }
    
    private function updateEmailState($emailUid, $eventData, $eventId) {
        $stmt = $this->db->prepare("
            REPLACE INTO email_states 
            (email_uid, current_state, last_event_id, last_updated) 
            VALUES (?, ?, ?, NOW())
        ");
        
        // Clean event data before JSON encoding
        $cleanEventData = $this->aggressiveUtf8Clean($eventData ?: []);
        $currentState = json_encode($cleanEventData, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $stmt->execute([$emailUid, $currentState, $eventId]);
    }
    
    private function getLastEmailUID($emails) {
        if (empty($emails)) {
            return null;
        }
        
        // Sortiere nach UID und nimm die höchste
        $uids = array_column($emails, 'uid');
        return max($uids);
    }
    
    private function markEmailInList(&$emails, $emailUid, $property, $value) {
        $found = false;
        foreach ($emails as &$email) {
            if ($email['uid'] == $emailUid) {
                $oldValue = $email[$property] ?? 'UNDEFINED';
                $email[$property] = $value;
                
                // Synchronisiere seen und unread Felder
                if ($property === 'seen') {
                    $email['unread'] = !$value;
                } elseif ($property === 'unread') {
                    $email['seen'] = !$value;
                }
                
                error_log("📧 EventStore: Updated email {$emailUid} - {$property}: {$oldValue} → " . var_export($value, true) . ", unread: " . var_export($email['unread'], true));
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            error_log("❌ EventStore: Email UID {$emailUid} not found in list for property {$property}");
            error_log("📋 EventStore: Available UIDs: " . implode(', ', array_column($emails, 'uid')));
        }
    }
    
    private function removeEmailFromList($emails, $emailUid) {
        return array_filter($emails, function($email) use ($emailUid) {
            return $email['uid'] != $emailUid;
        });
    }
    
    private function moveEmailInList(&$emails, $emailUid, $newFolder) {
        foreach ($emails as &$email) {
            if ($email['uid'] == $emailUid) {
                $email['folder'] = $newFolder;
                break;
            }
        }
    }
    
    private function mergeNewEmails($existingEmails, $newEmails) {
        $existingUids = array_column($existingEmails, 'uid');
        
        foreach ($newEmails as $newEmail) {
            if (!in_array($newEmail['uid'], $existingUids)) {
                $existingEmails[] = $newEmail;
            }
        }
        
        return $existingEmails;
    }
    
    /**
     * Öffentliche API-Methoden
     */
    public function markEmailAsRead($emailUid) {
        $result = $this->addEvent(self::EVENT_EMAIL_READ, $emailUid, ['seen' => true]);
        
        // Sofort den Snapshot aktualisieren, um Konsistenz zu gewährleisten
        $this->updateSnapshotEmailStatus($emailUid, 'seen', true);
        
        // Als zusätzliche Sicherheit: Invalidiere Snapshot für nächstes Laden
        // Nur bei kritischen Änderungen wie Read-Status
        $this->invalidateSnapshotIfNeeded();
        
        return $result;
    }
    
    public function markEmailAsUnread($emailUid) {
        $result = $this->addEvent(self::EVENT_EMAIL_UNREAD, $emailUid, ['seen' => false]);
        
        // Sofort den Snapshot aktualisieren
        $this->updateSnapshotEmailStatus($emailUid, 'seen', false);
        
        // Invalidiere Snapshot für nächstes Laden
        $this->invalidateSnapshotIfNeeded();
        
        return $result;
    }
    
    /**
     * Aktualisiert sofort den Status einer E-Mail im aktuellen Snapshot
     */
    private function updateSnapshotEmailStatus($emailUid, $property, $value) {
        try {
            $lastSnapshot = $this->getLastSnapshot();
            if (!$lastSnapshot) {
                error_log("📦 EventStore: No snapshot to update");
                return;
            }
            
            $snapshotEmails = json_decode($lastSnapshot['snapshot_data'], true);
            if (!is_array($snapshotEmails)) {
                error_log("📦 EventStore: Invalid snapshot data");
                return;
            }
            
            // Finde und aktualisiere die E-Mail
            foreach ($snapshotEmails as &$email) {
                if ($email['uid'] == $emailUid) {
                    $email[$property] = $value;
                    
                    // Synchronisiere seen und unread Felder
                    if ($property === 'seen') {
                        $email['unread'] = !$value;
                    } elseif ($property === 'unread') {
                        $email['seen'] = !$value;
                    }
                    
                    error_log("📧 EventStore: Updated email {$emailUid} in snapshot - {$property}: " . var_export($value, true));
                    break;
                }
            }
            
            // Snapshot mit aktualisierten Daten speichern
            $cleanEmails = $this->aggressiveUtf8Clean($snapshotEmails);
            $snapshotJson = json_encode($cleanEmails, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            
            if ($snapshotJson === false) {
                error_log("❌ EventStore: Failed to encode updated snapshot JSON");
                return;
            }
            
            $stmt = $this->db->prepare("
                UPDATE email_snapshots 
                SET snapshot_data = ? 
                WHERE id = ?
            ");
            $stmt->execute([$snapshotJson, $lastSnapshot['id']]);
            
            error_log("📦 EventStore: Updated snapshot {$lastSnapshot['id']} with new status");
            
        } catch (Exception $e) {
            error_log("❌ EventStore: Error updating snapshot: " . $e->getMessage());
        }
    }
    
    /**
     * Invalidiert den aktuellen Snapshot wenn nötig (für kritische Updates)
     * MySQL-kompatible Version
     */
    private function invalidateSnapshotIfNeeded() {
        try {
            // Markiere den letzten Snapshot als "alt" indem wir sein Datum zurücksetzen
            // Das zwingt das System bei nächstem Load einen neuen Snapshot zu erstellen
            // MySQL 5.7 kompatible Version ohne Subquery auf derselben Tabelle
            $stmt = $this->db->prepare("
                UPDATE email_snapshots 
                SET created_at = DATE_SUB(NOW(), INTERVAL 2 HOUR)
                WHERE is_active = 1
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute();
            
            error_log("📦 EventStore: Invalidated snapshot for fresh reload");
            
        } catch (Exception $e) {
            error_log("❌ EventStore: Error invalidating snapshot: " . $e->getMessage());
        }
    }
    
    public function deleteEmail($emailUid) {
        return $this->addEvent(self::EVENT_EMAIL_DELETED, $emailUid, ['deleted' => true]);
    }
    
    public function moveEmail($emailUid, $targetFolder) {
        return $this->addEvent(self::EVENT_EMAIL_MOVED, $emailUid, ['folder' => $targetFolder]);
    }
    
    /**
     * Statistiken und Debugging
     */
    public function getEventStats() {
        $stats = [];
        
        // Event Count by Type
        $stmt = $this->db->query("
            SELECT event_type, COUNT(*) as count 
            FROM email_events 
            GROUP BY event_type
        ");
        $stats['events_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Snapshot Info
        $stmt = $this->db->query("
            SELECT COUNT(*) as total_snapshots,
                   MAX(created_at) as last_snapshot,
                   MAX(email_count) as max_emails
            FROM email_snapshots
        ");
        $stats['snapshots'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Current Sequence
        $stats['current_sequence'] = $this->getCurrentSequenceNumber();
        
        return $stats;
    }
    
    public function cleanupOldEvents($daysToKeep = 30) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$daysToKeep days"));
        
        $stmt = $this->db->prepare("DELETE FROM email_events WHERE timestamp < ?");
        $deleted = $stmt->execute([$cutoffDate]);
        
        $this->debugLog("Cleaned up events older than $daysToKeep days");
        
        return $stmt->rowCount();
    }
    
    public function cleanupOldSnapshots($snapshotsToKeep = 5) {
        $stmt = $this->db->prepare("
            DELETE FROM email_snapshots 
            WHERE id NOT IN (
                SELECT id FROM email_snapshots 
                ORDER BY created_at DESC 
                LIMIT ?
            )
        ");
        
        $deleted = $stmt->execute([$snapshotsToKeep]);
        
        $this->debugLog("Cleaned up old snapshots, kept $snapshotsToKeep most recent");
        
        return $stmt->rowCount();
    }
    
    /**
     * Normalisiert E-Mail-Datenstruktur für konsistente Felder
     */
    private function normalizeEmailStructure($emails) {
        $normalizedEmails = [];
        
        foreach ($emails as $email) {
            // Sicherstellen, dass E-Mail ein Array ist
            if (!is_array($email)) {
                continue;
            }
            
            // 📦 RICH SNAPSHOT: Alle relevanten Felder speichern für schnellen Zugriff
            $normalizedEmail = [
                // IDs
                'id' => $email['id'] ?? $email['uid'] ?? uniqid(),
                'uid' => $email['uid'] ?? $email['id'] ?? uniqid(),
                'message_id' => $email['message_id'] ?? '',
                
                // Content
                'subject' => $email['subject'] ?? 'Kein Betreff',
                'body' => $email['body'] ?? '',
                'body_preview' => '',
                
                // Sender/Empfänger
                'from' => $email['from'] ?? [],
                'to' => $email['to'] ?? [],
                'cc' => $email['cc'] ?? [],
                'bcc' => $email['bcc'] ?? [],
                'reply_to' => $email['reply_to'] ?? [],
                
                // Metadaten
                'date' => $email['date'] ?? date('Y-m-d H:i:s'),
                'size' => $email['size'] ?? 0,
                'priority' => $email['priority'] ?? 'normal',
                
                // Attachments
                'has_attachments' => !empty($email['attachments']) || ($email['has_attachments'] ?? false),
                'attachments' => $email['attachments'] ?? [],
                'attachment_count' => count($email['attachments'] ?? []),
                
                // Flags/Status (wichtig für Performance!)
                'seen' => false,
                'unread' => true,
                'flagged' => $email['flagged'] ?? false,
                'answered' => $email['answered'] ?? false,
                'draft' => $email['draft'] ?? false,
                'deleted' => $email['deleted'] ?? false,
                'recent' => $email['recent'] ?? false,
                
                // Additional flags
                'flags' => $email['flags'] ?? []
            ];
            
            // 🔴 WICHTIG: seen/unread Status korrekt setzen
            // Priorisiere 'unread' wenn vorhanden (Frontend verwendet 'unread')
            if (isset($email['unread'])) {
                $normalizedEmail['unread'] = (bool)$email['unread'];
                $normalizedEmail['seen'] = !$email['unread'];
            } elseif (isset($email['seen'])) {
                $normalizedEmail['seen'] = (bool)$email['seen'];
                $normalizedEmail['unread'] = !$email['seen'];
            }
            
            // Body Preview erstellen (für Liste)
            if (!empty($normalizedEmail['body'])) {
                $preview = strip_tags($normalizedEmail['body']);
                $preview = preg_replace('/\s+/', ' ', $preview);
                $normalizedEmail['body_preview'] = substr(trim($preview), 0, 150) . 
                    (strlen(trim($preview)) > 150 ? '...' : '');
            } else {
                $normalizedEmail['body_preview'] = 'Keine Vorschau verfügbar';
            }
            
            $normalizedEmails[] = $normalizedEmail;
        }
        
        return $normalizedEmails;
    }
    
    /**
     * Bereinigt UTF-8 Probleme in E-Mail-Daten
     */
    private function cleanUtf8Data($emails) {
        $cleanEmails = [];
        
        foreach ($emails as $email) {
            $cleanEmail = [];
            
            foreach ($email as $key => $value) {
                if (is_string($value)) {
                    // UTF-8 bereinigen
                    $cleanValue = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    // Entferne non-printable Zeichen außer Zeilenumbrüchen
                    $cleanValue = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanValue);
                    $cleanEmail[$key] = $cleanValue;
                } elseif (is_array($value)) {
                    // Rekursiv für Arrays
                    $cleanEmail[$key] = $this->cleanUtf8DataRecursive($value);
                } else {
                    $cleanEmail[$key] = $value;
                }
            }
            
            $cleanEmails[] = $cleanEmail;
        }
        
        return $cleanEmails;
    }
    
    /**
     * Rekursive UTF-8 Bereinigung für verschachtelte Arrays
     */
    private function cleanUtf8DataRecursive($data) {
        if (is_array($data)) {
            $clean = [];
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $cleanValue = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    $cleanValue = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanValue);
                    $clean[$key] = $cleanValue;
                } elseif (is_array($value)) {
                    $clean[$key] = $this->cleanUtf8DataRecursive($value);
                } else {
                    $clean[$key] = $value;
                }
            }
            return $clean;
        } elseif (is_string($data)) {
            $cleanValue = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanValue);
        } else {
            return $data;
        }
    }
    
    /**
     * Aggressive UTF-8 Bereinigung als Fallback
     */
    private function forceUtf8Cleanup($emails) {
        $cleanEmails = [];
        
        foreach ($emails as $email) {
            $cleanEmail = [];
            
            foreach ($email as $key => $value) {
                if (is_string($value)) {
                    // Aggressivere Bereinigung
                    $cleanValue = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
                    if ($cleanValue === false) {
                        $cleanValue = 'Invalid UTF-8 content removed';
                    }
                    // Entferne alle problematischen Zeichen
                    $cleanValue = preg_replace('/[^\x20-\x7E\x0A\x0D\xC2-\xF4]/', '', $cleanValue);
                    $cleanEmail[$key] = $cleanValue;
                } elseif (is_array($value)) {
                    $cleanEmail[$key] = $this->forceUtf8CleanupRecursive($value);
                } else {
                    $cleanEmail[$key] = $value;
                }
            }
            
            $cleanEmails[] = $cleanEmail;
        }
        
        return $cleanEmails;
    }
    
    /**
     * Rekursive aggressive UTF-8 Bereinigung
     */
    private function forceUtf8CleanupRecursive($data) {
        if (is_array($data)) {
            $clean = [];
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $cleanValue = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
                    if ($cleanValue === false) {
                        $cleanValue = 'Invalid UTF-8 content removed';
                    }
                    $cleanValue = preg_replace('/[^\x20-\x7E\x0A\x0D\xC2-\xF4]/', '', $cleanValue);
                    $clean[$key] = $cleanValue;
                } elseif (is_array($value)) {
                    $clean[$key] = $this->forceUtf8CleanupRecursive($value);
                } else {
                    $clean[$key] = $value;
                }
            }
            return $clean;
        } elseif (is_string($data)) {
            $cleanValue = @iconv('UTF-8', 'UTF-8//IGNORE', $data);
            if ($cleanValue === false) {
                return 'Invalid UTF-8 content removed';
            }
            return preg_replace('/[^\x20-\x7E\x0A\x0D\xC2-\xF4]/', '', $cleanValue);
        } else {
            return $data;
        }
    }
    
    /**
     * Aggressive UTF-8 cleaning method that removes all non-UTF-8 characters
     */
    private function aggressiveUtf8Clean($data) {
        if (is_string($data)) {
            // Convert to UTF-8 and remove invalid sequences
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $data);
            if ($clean === false) {
                // If iconv fails, use mb_convert_encoding as fallback
                $clean = @mb_convert_encoding($data, 'UTF-8', 'UTF-8');
                if ($clean === false) {
                    // Last resort: remove all non-ASCII characters
                    $clean = preg_replace('/[^\x00-\x7F]/', '', $data);
                }
            }
            return $clean;
        } elseif (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleanKey = $this->aggressiveUtf8Clean($key);
                $cleaned[$cleanKey] = $this->aggressiveUtf8Clean($value);
            }
            return $cleaned;
        } else {
            return $data;
        }
    }
    
    /**
     * Multiple encoding strategies with fallback
     */
    private function encodeWithFallback($data) {
        // Strategy 1: Standard encoding with error handling
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json !== false) {
            return $json;
        }
        
        // Strategy 2: Force UTF-8 and try again
        $forceClean = $this->forceUtf8Cleanup($data);
        $json = json_encode($forceClean, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) {
            return $json;
        }
        
        // Strategy 3: Remove all problematic content
        $stripped = $this->stripProblematicContent($data);
        $json = json_encode($stripped, JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            return $json;
        }
        
        // Strategy 4: Last resort - only basic data
        $basic = $this->extractBasicData($data);
        return json_encode($basic, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Strip all HTML and complex content that might contain encoding issues
     */
    private function stripProblematicContent($data) {
        if (is_string($data)) {
            // Remove HTML tags
            $clean = strip_tags($data);
            // Remove all non-printable characters except newlines and tabs
            $clean = preg_replace('/[^\x20-\x7E\n\r\t]/', '', $clean);
            return $clean;
        } elseif (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                if ($key === 'body') {
                    // For email body, strip HTML and keep only plain text
                    $cleaned[$key] = $this->stripProblematicContent($value);
                } else {
                    $cleaned[$key] = $this->stripProblematicContent($value);
                }
            }
            return $cleaned;
        } else {
            return $data;
        }
    }
    
    /**
     * Extract only basic, safe data for encoding
     */
    private function extractBasicData($data) {
        if (!is_array($data)) {
            return [];
        }
        
        $safe = [];
        foreach ($data as $email) {
            if (!is_array($email)) continue;
            
            $safeEmail = [
                'id' => $email['id'] ?? 0,
                'uid' => $email['uid'] ?? '',
                'message_id' => $this->aggressiveUtf8Clean($email['message_id'] ?? ''),
                'subject' => $this->aggressiveUtf8Clean($email['subject'] ?? ''),
                'date' => $email['date'] ?? '',
                'from' => is_array($email['from']) ? array_map([$this, 'aggressiveUtf8Clean'], $email['from']) : [],
                'to' => is_array($email['to']) ? array_map([$this, 'aggressiveUtf8Clean'], $email['to']) : [],
                'body' => '[Content stripped due to encoding issues]',
                'unread' => $email['unread'] ?? false
            ];
            $safe[] = $safeEmail;
        }
        
        return $safe;
    }
    
    private function debugLog($message) {
        if ($this->debug) {
            error_log("📦 EventStore: $message");
        }
    }
}
