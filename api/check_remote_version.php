<?php
/**
 * Remote File Check
 * Überprüft, ob die admin.php auf dem Server die korrekte Version hat
 */

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 Checking loaded admin.php version...\n\n";

// Check if AdminAPI class is loaded
if (class_exists('AdminAPI', false)) {
    echo "✅ AdminAPI class is loaded\n";
} else {
    echo "❌ AdminAPI class not yet loaded\n";
}

// Show current file modification time
$adminFile = __DIR__ . '/admin.php';
if (file_exists($adminFile)) {
    echo "📄 File: admin.php\n";
    echo "📅 Modified: " . date('Y-m-d H:i:s', filemtime($adminFile)) . "\n";
    echo "📊 Size: " . number_format(filesize($adminFile)) . " bytes\n";
    
    // Check first 2000 chars for syntax indicators
    $content = file_get_contents($adminFile, false, null, 0, 2000);
    
    if (strpos($content, 'INT AUTO_INCREMENT') !== false) {
        echo "✅ MySQL syntax detected (AUTO_INCREMENT)\n";
    } else if (strpos($content, 'AUTOINCREMENT') !== false) {
        echo "❌ SQLite syntax detected (AUTOINCREMENT) - FILE NOT UPDATED!\n";
    }
    
    if (strpos($content, 'ENGINE=InnoDB') !== false) {
        echo "✅ InnoDB engine detected\n";
    }
} else {
    echo "❌ admin.php not found!\n";
}

echo "\n🌐 Server Info:\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
