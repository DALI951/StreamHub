<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/Scraper/SourceManager.php';
require_once __DIR__ . '/../src/Scraper/BaseScraper.php';
require_once __DIR__ . '/../src/Scraper/EgyDeadScraper.php';

// Create a test scraper instance to access protected methods
$manager = new SourceManager();
$scraper = $manager->getScraper('egydead');

// Fetch the EarnVids embed page
$url = 'https://morencius.com/v/mx7p6yocdl1t';
$referer = 'https://tv10.egydead.live/episode/test/';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    CURLOPT_REFERER        => $referer,
]);
$html = curl_exec($ch);
curl_close($ch);

echo "=== EarnVids Embed Analysis ===\n";
echo "Length: " . strlen($html) . "\n\n";

// Extract packer blocks using the scraper's method
$reflection = new ReflectionClass($scraper);
$method = $reflection->getMethod('extractPackerBlocks');
$method->setAccessible(true);
$packerBlocks = $method->invoke($scraper, $html);

echo "Packer blocks found: " . count($packerBlocks) . "\n\n";

if (!empty($packerBlocks)) {
    foreach ($packerBlocks as $i => $block) {
        echo "--- Block #$i (first 500 chars) ---\n";
        echo substr($block, 0, 500) . "\n\n";
        
        // Try to decode it
        $decodeMethod = $reflection->getMethod('decodePacker');
        $decodeMethod->setAccessible(true);
        $decoded = $decodeMethod->invoke($scraper, $block);
        
        if ($decoded) {
            echo "--- Decoded (first 2000 chars) ---\n";
            echo substr($decoded, 0, 2000) . "\n\n";
            
            // Look for video URLs in decoded content
            preg_match_all('#(https?://[^\s"\'<>\[\]]+\.(?:m3u8|mp4)[^\s"\'<>\[\]]*)#i', $decoded, $m);
            echo "Video URLs in decoded: " . count($m[1]) . "\n";
            foreach ($m[1] as $u) {
                echo "  $u\n";
            }
            
            // Check for wurl
            preg_match_all('/wurl\s*[=:]\s*["\']?([^\s"\'<>]+)["\']?/i', $decoded, $wm);
            echo "wurl in decoded: " . count($wm[1]) . "\n";
            foreach ($wm[1] as $u) {
                echo "  $u\n";
            }
            
            // Check for file/src/source
            preg_match_all('#\b(?:file|src|source)\s*[=:]\s*["\']([^"\']+\.(?:m3u8|mp4))["\']#i', $decoded, $fm);
            echo "file/src/source in decoded: " . count($fm[1]) . "\n";
            foreach ($fm[1] as $u) {
                echo "  $u\n";
            }
            
            // Check for nurl
            preg_match_all('#nurl\s*[=:]\s*["\']([^"\']+\.(?:m3u8|mp4))["\']#i', $decoded, $nm);
            echo "nurl in decoded: " . count($nm[1]) . "\n";
            foreach ($nm[1] as $u) {
                echo "  $u\n";
            }
        } else {
            echo "FAILED to decode packer block\n\n";
        }
    }
}

// Also try the full resolveIframe flow
echo "\n=== Full resolveIframe test ===\n";
$resolveMethod = $reflection->getMethod('resolveIframe');
$resolveMethod->setAccessible(true);
$result = $resolveMethod->invoke($scraper, $url, 'EarnVids');
echo "Result: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Test Mixdrop too (even though it shows "not found")
echo "\n\n=== Mixdrop resolveIframe test ===\n";
$url2 = 'https://mixdrop.top/e/rwlzjozjbgx3kl';
$result2 = $resolveMethod->invoke($scraper, $url2, 'Mixdrop');
echo "Result: " . json_encode($result2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Test StreamHG
echo "\n\n=== StreamHG resolveIframe test ===\n";
$url3 = 'https://hgcloud.to/e/sda8hn53rrp8';
$result3 = $resolveMethod->invoke($scraper, $url3, 'StreamHG');
echo "Result: " . json_encode($result3, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
