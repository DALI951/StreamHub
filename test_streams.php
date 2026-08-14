<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Cache.php';
require_once __DIR__ . '/src/Scraper/SourceManager.php';
require_once __DIR__ . '/src/Scraper/BaseScraper.php';
require_once __DIR__ . '/src/Scraper/EgyDeadScraper.php';

header('Content-Type: text/plain; charset=utf-8');

$testUrl = $_GET['url'] ?? '';

if (!$testUrl) {
    echo "Usage: test_streams.php?url=<episode_url>\n";
    echo "Example: test_streams.php?url=https://tv10.egydead.live/episode/the-mentalist-1-season/episode-1/\n";
    exit;
}

echo "=== Stream Resolution Debug ===\n";
echo "URL: $testUrl\n\n";

// Step 1: Check URL detection
echo "--- Step 1: Source Detection ---\n";
$manager = new SourceManager();
$scraper = $manager->detectSource($testUrl);
if (!$scraper) {
    echo "FAILED: Could not detect source from URL\n";
    echo "Trying with source=egydead param...\n";
    $scraper = $manager->getScraper('egydead');
}
if (!$scraper) {
    echo "FATAL: No scraper available\n";
    exit;
}
echo "OK: Scraper = " . get_class($scraper) . "\n";
echo "Base URL: " . $scraper->getBaseUrl() . "\n\n";

// Step 2: Try fetching the episode page with POST
echo "--- Step 2: Fetch Episode Page (POST View=1) ---\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['View' => '1']),
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    CURLOPT_REFERER        => $testUrl,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errno = curl_errno($ch);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Curl Error: " . ($error ?: 'none') . "\n";
echo "Response length: " . strlen($response) . " bytes\n\n";

if (!$response || $httpCode >= 400) {
    echo "FAILED: Could not fetch episode page\n";
    // Try without POST
    echo "\nTrying GET instead...\n";
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL            => $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    ]);
    $response = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    echo "GET HTTP Code: $httpCode2\n";
    echo "GET Response length: " . strlen($response) . " bytes\n";
    if (!$response || $httpCode2 >= 400) {
        echo "FATAL: Cannot fetch episode page at all\n";
        exit;
    }
}

// Step 3: Parse server links
echo "\n--- Step 3: Parse Server Links (data-link) ---\n";
preg_match_all('/<li[^>]+data-link="([^"]+)"[^>]*>(.*?)<\/li>/si', $response, $serverMatches);
echo "Found " . count($serverMatches[1]) . " data-link servers\n";
for ($i = 0; $i < count($serverMatches[1]); $i++) {
    $link = $serverMatches[1][$i];
    $name = strip_tags($serverMatches[2][$i]);
    echo "  [$i] Name: $name | Link: $link\n";
}
echo "\n";

if (empty($serverMatches[1])) {
    echo "--- Trying to find iframes instead ---\n";
    preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\']/', $response, $iframeMatches);
    echo "Found " . count($iframeMatches[1]) . " iframes\n";
    for ($i = 0; $i < count($iframeMatches[1]); $i++) {
        echo "  [$i] " . $iframeMatches[1][$i] . "\n";
    }

    // Also show a snippet of the HTML around servers area
    echo "\n--- HTML Snippet (looking for server-related content) ---\n";
    if (preg_match('/serversList|servers-list|data-link|data-server/i', $response, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1];
        echo substr($response, max(0, $pos - 200), 600) . "\n";
    } else {
        echo "No server markers found in HTML. First 500 chars:\n";
        echo substr($response, 0, 500) . "\n";
    }
}

// Step 4: Test resolving each server
echo "\n--- Step 4: Resolve Each Server ---\n";
for ($i = 0; $i < count($serverMatches[1]); $i++) {
    $embedUrl = trim($serverMatches[1][$i]);
    $name = strip_tags($serverMatches[2][$i]);
    echo "\n--- Server: $name ---\n";
    echo "Embed URL: $embedUrl\n";

    // Fetch the embed page
    $ch3 = curl_init();
    curl_setopt_array($ch3, [
        CURLOPT_URL            => $embedUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_REFERER        => $testUrl,
    ]);
    $embedHtml = curl_exec($ch3);
    $embedCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    $embedErr = curl_error($ch3);
    curl_close($ch3);

    echo "Embed HTTP: $embedCode\n";
    echo "Embed size: " . strlen($embedHtml) . " bytes\n";
    if ($embedErr) echo "Embed error: $embedErr\n";

    if (!$embedHtml || strlen($embedHtml) < 100) {
        echo "SKIP: Too small or empty\n";
        if ($embedHtml) echo "Content: " . substr($embedHtml, 0, 300) . "\n";
        continue;
    }

    // Check for packer blocks
    preg_match_all('/eval\(function\(p,a,c,k,e,d\)\{/si', $embedHtml, $packerMatches);
    echo "Packer blocks found: " . count($packerMatches[0]) . "\n";

    // Check for wurl
    preg_match_all('/wurl\s*[=:]\s*["\']([^"\']+)["\']/i', $embedHtml, $wurlMatches);
    echo "wurl matches: " . count($wurlMatches[1]) . "\n";

    // Check for MDCore
    if (preg_match('/MDCore\s*=\s*\{([^}]+)\}/i', $embedHtml, $mdMatch)) {
        echo "MDCore found: " . substr($mdMatch[1], 0, 200) . "\n";
    }
    if (preg_match('/MDCore\.ref\s*=\s*["\']([^"\']+)["\']/i', $embedHtml, $mdRef)) {
        echo "MDCore.ref: " . $mdRef[1] . "\n";
    }

    // Check for any m3u8 or mp4 URLs
    preg_match_all('/(https?:\/\/[^\s"\'<>\\]+\.(?:m3u8|mp4)[^\s"\'<>\\]*)/i', $embedHtml, $urlMatches);
    echo "Direct video URLs: " . count($urlMatches[1]) . "\n";
    foreach ($urlMatches[1] as $u) {
        echo "  $u\n";
    }

    // Check for file= or src= patterns
    preg_match_all('/\b(?:file|src|source)\s*[=:]\s*["\']([^"\']+\.(?:m3u8|mp4))["\']/i', $embedHtml, $fileMatches);
    echo "file/src patterns: " . count($fileMatches[1]) . "\n";
    foreach ($fileMatches[1] as $u) {
        echo "  $u\n";
    }

    // Show snippet if small enough
    if (strlen($embedHtml) < 5000) {
        echo "\n--- Embed HTML (full) ---\n";
        echo $embedHtml . "\n";
    } else {
        echo "\n--- Embed HTML (first 2000 chars) ---\n";
        echo substr($embedHtml, 0, 2000) . "\n";
    }
}

echo "\n=== Done ===\n";
