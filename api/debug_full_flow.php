<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

// Step 1: Search for "the mentalist" to find the real series URL
echo "=== Step 1: Search for 'the mentalist' ===\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://tv10.egydead.live/?s=the+mentalist',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
]);
$searchHtml = curl_exec($ch);
curl_close($ch);

echo "Search page length: " . strlen($searchHtml) . "\n";

// Parse search results
preg_match_all('/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si', $searchHtml, $matches);
echo "Found " . count($matches[1]) . " results\n";
$seriesUrls = [];
for ($i = 0; $i < count($matches[1]); $i++) {
    $href = $matches[1][$i];
    $block = $matches[2][$i];
    $title = '';
    if (preg_match('/<h1\s+class="BottomTitle">(.*?)<\/h1>/si', $block, $m)) {
        $title = strip_tags($m[1]);
    }
    $type = 'movie';
    if (preg_match('#/serie/#i', $href)) $type = 'series';
    elseif (preg_match('#/season/#i', $href)) $type = 'season';
    elseif (preg_match('#/episode/#i', $href)) $type = 'episode';
    
    echo "  [$type] $title => $href\n";
    if ($type === 'series') $seriesUrls[] = $href;
}

if (empty($seriesUrls)) {
    echo "\nNo series found. Trying alternative search...\n";
    // Try finding any links
    preg_match_all('/href="(https?:\/\/tv10\.egydead\.live\/[^"]+)"/i', $searchHtml, $allLinks);
    foreach (array_unique($allLinks[1]) as $link) {
        if (preg_match('#/(serie|season|episode)/#i', $link)) {
            echo "  Found: $link\n";
            if (preg_match('#/serie/#i', $link)) $seriesUrls[] = $link;
        }
    }
}

// Step 2: Visit the series page to find real season URLs
if (!empty($seriesUrls)) {
    $seriesUrl = $seriesUrls[0];
    echo "\n=== Step 2: Visit series page: $seriesUrl ===\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $seriesUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    ]);
    $seriesHtml = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    echo "Final URL: $finalUrl\n";
    echo "Series page length: " . strlen($seriesHtml) . "\n";
    
    if (preg_match('/<title>(.*?)<\/title>/si', $seriesHtml, $m)) {
        echo "Title: " . strip_tags($m[1]) . "\n";
    }
    
    // Find season links
    echo "\nSeason links:\n";
    preg_match_all('/<a[^>]+href="([^"]*\/season\/[^"]*)"[^>]*>/si', $seriesHtml, $sMatches);
    $seen = [];
    for ($i = 0; $i < count($sMatches[1]); $i++) {
        $sUrl = $sMatches[1][$i];
        if (!in_array($sUrl, $seen)) {
            $seen[] = $sUrl;
            echo "  $sUrl\n";
        }
    }
    
    // Also look for any links containing episode patterns
    echo "\nAll links with season/episode:\n";
    preg_match_all('/href="([^"]*(?:season|episode|الموسم|الحلقة)[^"]*)"/ui', $seriesHtml, $epMatches);
    foreach (array_unique($epMatches[1]) as $link) {
        echo "  $link\n";
    }
    
    // Try visiting a season page
    if (!empty($seen)) {
        $seasonUrl = $seen[0];
        echo "\n=== Step 3: Visit season page: $seasonUrl ===\n";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $seasonUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        ]);
        $seasonHtml = curl_exec($ch);
        $seasonFinalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        echo "Final URL: $seasonFinalUrl\n";
        echo "Season page length: " . strlen($seasonHtml) . "\n";
        
        if (preg_match('/<title>(.*?)<\/title>/si', $seasonHtml, $m)) {
            echo "Title: " . strip_tags($m[1]) . "\n";
        }
        
        // Find episode links
        echo "\nEpisode links:\n";
        preg_match_all('/<a[^>]+href="([^"]*\/episode\/[^"]*)"[^>]*>(.*?)<\/a>/si', $seasonHtml, $epMatches);
        for ($i = 0; $i < count($epMatches[1]); $i++) {
            $epUrl = $epMatches[1][$i];
            $epText = strip_tags($epMatches[2][$i]);
            echo "  [$epText] => $epUrl\n";
        }
        
        // If no episode links, look for any episode-like patterns
        if (empty($epMatches[1])) {
            echo "\nNo episode links found. Looking for other patterns...\n";
            preg_match_all('/href="([^"]*(?:episode|حلقة|الحلقة)[^"]*)"/ui', $seasonHtml, $epMatches2);
            foreach (array_unique($epMatches2[1]) as $link) {
                echo "  $link\n";
            }
            
            // Check data-link
            preg_match_all('/data-link="([^"]+)"/i', $seasonHtml, $dlMatches);
            echo "\ndata-link attributes: " . count($dlMatches[1]) . "\n";
            foreach ($dlMatches[1] as $dl) {
                echo "  $dl\n";
            }
            
            // Check for any AJAX loading patterns
            echo "\nLooking for AJAX content loading...\n";
            if (preg_match('/data-ajax="([^"]+)"/i', $seasonHtml, $m)) {
                echo "  data-ajax: " . $m[1] . "\n";
            }
            
            // Show any script blocks that might reveal server loading
            preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $seasonHtml, $scripts);
            foreach ($scripts[1] as $i => $script) {
                if (strlen($script) > 50 && preg_match('/server|embed|player|view|tab|ajax/i', $script)) {
                    echo "\n--- Script #$i ---\n";
                    echo substr($script, 0, 1000) . "\n";
                }
            }
        }
        
        // Try visiting an actual episode URL
        if (!empty($epMatches[1])) {
            $epUrl = $epMatches[1][0];
            echo "\n=== Step 4: Visit episode page: $epUrl ===\n";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $epUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            ]);
            $epHtml = curl_exec($ch);
            $epFinalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            
            echo "Final URL: $epFinalUrl\n";
            echo "Episode page length: " . strlen($epHtml) . "\n";
            
            if (preg_match('/<title>(.*?)<\/title>/si', $epHtml, $m)) {
                echo "Title: " . strip_tags($m[1]) . "\n";
            }
            
            // Check for data-link on episode page
            preg_match_all('/data-link="([^"]+)"/i', $epHtml, $dlMatches);
            echo "\ndata-link attributes: " . count($dlMatches[1]) . "\n";
            foreach ($dlMatches[1] as $dl) {
                echo "  $dl\n";
            }
            
            // Check for iframes
            preg_match_all('/<iframe[^>]+src="([^"]+)"/i', $epHtml, $iframes);
            echo "\nIframes: " . count($iframes[1]) . "\n";
            foreach ($iframes[1] as $src) {
                echo "  $src\n";
            }
            
            // Look for the player/watch section
            echo "\nLooking for player/watch section...\n";
            if (preg_match('/class="[^"]*(?:player|watch-area|servers|tab-content|single-server)[^"]*"/i', $epHtml, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[1];
                echo "Found at $pos:\n";
                echo substr($epHtml, $pos, 2000) . "\n";
            }
        }
    }
}
