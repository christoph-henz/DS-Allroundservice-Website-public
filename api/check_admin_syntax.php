<?php
/**
 * Admin.php Syntax Check
 * Überprüft, ob die admin.php MySQL-kompatibel ist
 */

echo "🔍 Checking admin.php for MySQL compatibility...\n\n";

$adminFile = __DIR__ . '/admin.php';

if (!file_exists($adminFile)) {
    echo "❌ admin.php not found!\n";
    exit(1);
}

$content = file_get_contents($adminFile);

// Check for SQLite-specific syntax
$issues = [];

if (strpos($content, 'AUTOINCREMENT') !== false) {
    $issues[] = "❌ Found 'AUTOINCREMENT' (should be 'AUTO_INCREMENT')";
}

if (preg_match('/INTEGER\s+PRIMARY\s+KEY/i', $content)) {
    $issues[] = "❌ Found 'INTEGER PRIMARY KEY' (should be 'INT AUTO_INCREMENT PRIMARY KEY')";
}

if (preg_match('/PRAGMA\s+table_info/i', $content)) {
    $issues[] = "❌ Found 'PRAGMA table_info' (should be 'SHOW COLUMNS')";
}

if (preg_match('/CREATE\s+TABLE.*?INTEGER.*?AUTOINCREMENT/is', $content)) {
    $issues[] = "❌ Found CREATE TABLE with INTEGER AUTOINCREMENT";
}

// Check for correct MySQL syntax
$goodPatterns = 0;

if (preg_match('/INT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', $content)) {
    $goodPatterns++;
    echo "✅ Found correct MySQL AUTO_INCREMENT syntax\n";
}

if (preg_match('/ENGINE\s*=\s*InnoDB/i', $content)) {
    $goodPatterns++;
    echo "✅ Found InnoDB engine declaration\n";
}

if (preg_match('/SHOW\s+COLUMNS\s+FROM/i', $content)) {
    $goodPatterns++;
    echo "✅ Found MySQL SHOW COLUMNS syntax\n";
}

echo "\n";

if (empty($issues)) {
    echo "🎉 No MySQL compatibility issues found!\n";
    echo "✨ admin.php is MySQL-ready with $goodPatterns MySQL-specific patterns\n";
} else {
    echo "⚠️ Found " . count($issues) . " issue(s):\n";
    foreach ($issues as $issue) {
        echo "  $issue\n";
    }
}

echo "\n📊 File Info:\n";
echo "  Size: " . number_format(filesize($adminFile)) . " bytes\n";
echo "  Modified: " . date('Y-m-d H:i:s', filemtime($adminFile)) . "\n";
