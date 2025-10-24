<?php
/**
 * Check for MySQL datetime compatibility in production files
 * Searches for SQLite-specific datetime('now') usage
 */

$productionFiles = [
    'src/Utils/QuestionnaireSubmissionHandler.php',
    'src/Utils/EmailService.php',
    'src/Utils/EmailEventStore.php',
    'api/admin.php',
];

$totalIssues = 0;
$totalFiles = 0;

echo "🔍 Checking MySQL datetime compatibility...\n";
echo str_repeat("=", 60) . "\n\n";

foreach ($productionFiles as $file) {
    $filePath = __DIR__ . '/../' . $file;
    
    if (!file_exists($filePath)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }
    
    $totalFiles++;
    $content = file_get_contents($filePath);
    $issues = [];
    
    // Check for SQLite datetime functions
    if (preg_match_all("/datetime\('now'\)/", $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $issues[] = "datetime('now') at position " . $match[1];
        }
    }
    
    if (preg_match_all("/DATE\('now'\)/", $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $issues[] = "DATE('now') at position " . $match[1];
        }
    }
    
    if (preg_match_all("/strftime\(/", $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $issues[] = "strftime() at position " . $match[1];
        }
    }
    
    if (preg_match_all("/INSERT OR REPLACE/i", $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $issues[] = "INSERT OR REPLACE at position " . $match[1];
        }
    }
    
    if (preg_match_all("/INSERT OR IGNORE/i", $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $issues[] = "INSERT OR IGNORE at position " . $match[1];
        }
    }
    
    if (empty($issues)) {
        echo "✅ $file - OK\n";
    } else {
        echo "❌ $file - " . count($issues) . " issues found:\n";
        foreach ($issues as $issue) {
            echo "   - $issue\n";
        }
        $totalIssues += count($issues);
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 Summary:\n";
echo "   Files checked: $totalFiles\n";
echo "   Total issues: $totalIssues\n";

if ($totalIssues === 0) {
    echo "\n🎉 All production files are MySQL-compatible!\n";
    exit(0);
} else {
    echo "\n⚠️  Please fix the issues above before deploying to production.\n";
    exit(1);
}
