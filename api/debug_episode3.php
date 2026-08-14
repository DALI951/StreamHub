<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$testUrl = 'https://tv10.egydead.live/episode/the-mentalist-1-season/episode-1/';

// First, let's try a plain GET request to see the episode page
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
curl_close($ch);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Page title ===\n";
if (preg_match('/<title>(.*?)<\/title>/si', $response, $m)) {
    echo $m[1] . "\n";
}

// Check if we're on the actual episode page or redirected
echo "\n=== Canonical URL ===\n";
if (preg_match('/rel="canonical"\s+href="([^"]+)"/i', $response, $m)) {
    echo $m[1] . "\n";
}

// Look for the single post content area
echo "\n=== Article / Post content area ===\n";
if (preg_match('/<article[^>]*>(.*?)<\/article>/si', $response, $m)) {
    $article = $m[0];
    echo "Found article tag (" . strlen($article) . " chars)\n\n";
    
    // Look for any player-related content
    echo "Looking for player elements...\n";
    if (preg_match_all('/class="[^"]*(?:player|watch|embed|view|servers|tab-content|tab-pane|server)[^"]*"/i', $article, $m)) {
        foreach ($m[0] as $class) {
            echo "  $class\n";
        }
    }
    
    // Look for data attributes in the article
    echo "\nData attributes in article:\n";
    preg_match_all('/data-[a-z-]+="[^"]+"/i', $article, $m);
    foreach (array_unique($m[0]) as $attr) {
        echo "  $attr\n";
    }
    
    // Look for iframes in article
    echo "\nIframes in article:\n";
    preg_match_all('/<iframe[^>]+src="([^"]+)"/i', $article, $m);
    foreach ($m[1] as $src) {
        echo "  $src\n";
    }
    
    // Show first 3000 chars of article
    echo "\n=== Article HTML (first 3000) ===\n";
    echo substr($article, 0, 3000) . "\n";
} else {
    echo "No article tag found.\n";
    
    // Try main content area
    echo "\nLooking for main content...\n";
    if (preg_match('/<div[^>]+class="[^"]*(?:single|entry|post|article|content)[^"]*"[^>]*>/i', $response, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[1];
        echo "Found content div at $pos:\n";
        echo substr($response, $pos, 3000) . "\n";
    }
}

// Look for View button / AJAX trigger
echo "\n=== View/Play buttons ===\n";
preg_match_all('/<(?:button|a)[^>]*(?:class="[^"]*(?:view|play|watch|btn)[^"]*"|onclick)[^>]*>(.*?)<\/(?:button|a)>/si', $response, $m);
foreach ($m[0] as $i => $btn) {
    echo "  Button $i: " . substr(strip_tags($btn), 0, 100) . "\n";
    // Show attributes
    if (preg_match('/onclick="([^"]+)"/i', $btn, $oc)) {
        echo "    onclick: " . $oc[1] . "\n";
    }
}
