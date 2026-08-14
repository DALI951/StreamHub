<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

// Test with a real episode URL from the search results (encoded)
$testUrl = urldecode('https://tv10.egydead.live/episode/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%84%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%b3%d8%a7%d8%a8%d8%b9-%d8%a7%d9%84%d8%ad%d9%84%d9%82%d8%a9-13/');

echo "=== Testing real episode URL ===\n";
echo "URL: $testUrl\n\n";

// Step 1: GET the episode page
echo "--- Step 1: GET request ---\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
]);
$response = curl_exec($ch);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode\n";
echo "Final URL: $finalUrl\n";
echo "Length: " . strlen($response) . "\n";

if (preg_match('/<title>(.*?)<\/title>/si', $response, $m)) {
    echo "Title: " . strip_tags($m[1]) . "\n";
}

// Check if this is an episode page (not home)
if (preg_match('/rel="canonical"\s+href="([^"]+)"/i', $response, $m)) {
    echo "Canonical: " . $m[1] . "\n";
}

// Check for data-link
preg_match_all('/data-link="([^"]+)"/i', $response, $dlMatches);
echo "\ndata-link: " . count($dlMatches[1]) . "\n";
foreach ($dlMatches[1] as $dl) {
    echo "  $dl\n";
}

// Check for iframes
preg_match_all('/<iframe[^>]+src="([^"]+)"/i', $response, $iframes);
echo "\nIframes: " . count($iframes[1]) . "\n";
foreach ($iframes[1] as $src) {
    echo "  $src\n";
}

// Check for servers section
echo "\n=== Looking for player/servers section ===\n";
if (preg_match('/class="[^"]*(?:servers|single-server|watch|player|tab-content|tab-pane)[^"]*"/i', $response, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[1];
    echo "Found class at $pos:\n";
    echo substr($response, max(0, $pos - 100), 1500) . "\n";
}

// Check for AJAX endpoint that loads servers
echo "\n=== AJAX patterns ===\n";
if (preg_match('/data-ajax="([^"]+)"/i', $response, $m)) {
    echo "data-ajax: " . $m[1] . "\n";
}

// Look for any script that triggers server loading
preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $response, $scripts);
foreach ($scripts[1] as $i => $script) {
    if (strlen($script) > 50 && preg_match('/server|embed|player|view|tab|ajax|fetch|getServer/i', $script)) {
        echo "\n--- Script #$i (" . strlen($script) . " chars) ---\n";
        echo substr($script, 0, 1200) . "\n";
    }
}

// Now try POST with View=1
echo "\n=== Step 2: POST with View=1 ===\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['View' => '1']),
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Content-Type: application/x-www-form-urlencoded',
        'X-Requested-With: XMLHttpRequest',
    ],
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    CURLOPT_REFERER        => $testUrl,
]);
$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode2\n";
echo "Length: " . strlen($response2) . "\n";

// Check what we got back
if (preg_match('/<title>(.*?)<\/title>/si', $response2, $m)) {
    echo "Title: " . strip_tags($m[1]) . "\n";
}

preg_match_all('/data-link="([^"]+)"/i', $response2, $dlMatches2);
echo "data-link: " . count($dlMatches2[1]) . "\n";
foreach ($dlMatches2[1] as $dl) {
    echo "  $dl\n";
}

preg_match_all('/<iframe[^>]+src="([^"]+)"/i', $response2, $iframes2);
echo "Iframes: " . count($iframes2[1]) . "\n";
foreach ($iframes2[1] as $src) {
    echo "  $src\n";
}

// If still no data-link, try the theme's Ajax endpoint directly
echo "\n=== Step 3: Try theme Ajax endpoint ===\n";
$ajaxBase = 'https://tv10.egydead.live/wp-content/themes/egydeadc-taq/Ajax';

// Try common Ajax patterns for this theme
$ajaxUrls = [
    "$ajaxBase/Servers.php",
    "$ajaxBase/servers.php", 
    "$ajaxBase/GetServers.php",
    "$ajaxBase/get-servers.php",
    "$ajaxBase/View.php",
    "$ajaxBase/view.php",
    "$ajaxBase/Player.php",
    "$ajaxBase/player.php",
    "$ajaxBase/Episode.php",
    "$ajaxBase/episode.php",
];

foreach ($ajaxUrls as $ajaxUrl) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $ajaxUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_REFERER        => $testUrl,
    ]);
    $ajaxResp = curl_exec($ch);
    $ajaxCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($ajaxCode === 200 && strlen($ajaxResp) > 0) {
        echo "  $ajaxUrl => HTTP $ajaxCode, " . strlen($ajaxResp) . " bytes\n";
        if (strlen($ajaxResp) < 500) {
            echo "    Content: " . substr($ajaxResp, 0, 300) . "\n";
        } else {
            echo "    First 300: " . substr($ajaxResp, 0, 300) . "\n";
        }
    }
}

// Also try POST to the Ajax endpoint with post ID
echo "\n=== Step 4: Try POST to Ajax with post data ===\n";
// Extract post ID from page
$postId = '';
if (preg_match('/post-(\d+)/i', $response, $m)) {
    $postId = $m[1];
}
if (!$postId && preg_match('/data-id="(\d+)"/i', $response, $m)) {
    $postId = $m[1];
}
echo "Post ID: " . ($postId ?: 'not found') . "\n";

if ($postId) {
    // Try posting to various Ajax endpoints
    $ajaxEndpoints = [
        "$ajaxBase/Servers.php",
        "$ajaxBase/GetServers.php",
        "https://tv10.egydead.live/wp-admin/admin-ajax.php",
    ];
    
    foreach ($ajaxEndpoints as $endpoint) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'action' => 'get_servers',
                'post' => $postId,
                'id' => $postId,
                'View' => '1',
            ]),
            CURLOPT_HTTPHEADER     => [
                'Accept: */*',
                'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
                'Content-Type: application/x-www-form-urlencoded',
                'X-Requested-With: XMLHttpRequest',
            ],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_REFERER        => $testUrl,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (strlen($resp) > 0) {
            echo "\n$endpoint => HTTP $code, " . strlen($resp) . " bytes\n";
            echo "Content: " . substr($resp, 0, 500) . "\n";
        }
    }
}
