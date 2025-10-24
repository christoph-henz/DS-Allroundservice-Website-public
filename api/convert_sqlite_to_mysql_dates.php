<?php
/**
 * Convert SQLite datetime functions to MySQL
 */

$file = __DIR__ . '/admin.php';
$content = file_get_contents($file);

// Backup erstellen
file_put_contents($file . '.backup', $content);

$replacements = [
    // SQLite datetime('now') → MySQL NOW()
    "datetime('now')" => "NOW()",
    'datetime("now")' => "NOW()",
    
    // SQLite DATE('now') → MySQL CURDATE()
    "DATE('now')" => "CURDATE()",
    'DATE("now")' => "CURDATE()",
    
    // SQLite strftime → MySQL DATE_FORMAT
    "strftime('%Y-%m', submitted_at) = strftime('%Y-%m', 'now')" => "DATE_FORMAT(submitted_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')",
    
    // SQLite INSERT OR IGNORE → MySQL INSERT IGNORE
    'INSERT OR IGNORE' => 'INSERT IGNORE',
];

$count = 0;
foreach ($replacements as $search => $replace) {
    $newContent = str_replace($search, $replace, $content, $tempCount);
    if ($tempCount > 0) {
        echo "✅ Replaced '$search' → '$replace' ($tempCount times)\n";
        $count += $tempCount;
        $content = $newContent;
    }
}

if ($count > 0) {
    file_put_contents($file, $content);
    echo "\n✅ Total replacements: $count\n";
    echo "✅ File updated: $file\n";
    echo "✅ Backup created: $file.backup\n";
} else {
    echo "ℹ️ No replacements needed\n";
}
