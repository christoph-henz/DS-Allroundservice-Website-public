<?php
/**
 * Cache Clearer Script
 * Clears PHP OpCache and resets file stat cache
 */

echo "🔄 Clearing PHP Caches...\n\n";

// Clear OpCache if available
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ OpCache cleared successfully\n";
    } else {
        echo "❌ Failed to clear OpCache\n";
    }
} else {
    echo "ℹ️ OpCache not available\n";
}

// Clear file stat cache
clearstatcache(true);
echo "✅ File stat cache cleared\n";

// Clear realpath cache
if (function_exists('clearstatcache')) {
    clearstatcache(true);
    echo "✅ Realpath cache cleared\n";
}

echo "\n✨ Cache clearing complete!\n";
echo "\n📝 Next steps:\n";
echo "1. Refresh your admin panel\n";
echo "2. If error persists, restart your web server\n";
