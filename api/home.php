<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$cacheDir = __DIR__ . '/../cache/';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

$cacheFile = $cacheDir . 'home.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 1200) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        echo json_encode(['categories' => $cached, 'cached' => true]);
        exit;
    }
}

$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

function httpGet(string $url, string $referer = ''): ?string {
    global $UA;
    $headers = [
        'User-Agent: ' . $UA,
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ar,en;q=0.8',
    ];
    if ($referer) $headers[] = 'Referer: ' . $referer;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_ENCODING       => '',
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err !== '' || $body === false || $code >= 400) return null;
    return (string) $body;
}

function cleanText(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = trim(preg_replace('/\s+/u', ' ', strip_tags($s)));
    return $s;
}

function toAbsolute(string $url, string $base): string {
    if (preg_match('#^https?://#i', $url)) return $url;
    if (strpos($url, '//') === 0) return (parse_url($base, PHP_URL_SCHEME) ?: 'https') . ':' . $url;
    if ($url[0] === '/') return (parse_url($base, PHP_URL_SCHEME) ?: 'https') . '://' . (parse_url($base, PHP_URL_HOST) ?: '') . $url;
    $dir = preg_replace('#/[^/]*$#', '', parse_url($base, PHP_URL_PATH) ?: '/');
    return (parse_url($base, PHP_URL_SCHEME) ?: 'https') . '://' . (parse_url($base, PHP_URL_HOST) ?: '') . $dir . '/' . $url;
}

// ---- EgyDead cards (movieItem blocks) ----
function parseEgyDead(string $html, string $base): array {
    $items = [];
    $pattern = '/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si';
    if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) return $items;
    $seen = [];
    foreach ($matches as $m) {
        $href = $m[1];
        $block = $m[2];
        $title = '';
        if (preg_match('/<h1\s+class="BottomTitle">(.*?)<\/h1>/si', $block, $tm)) {
            $title = cleanText($tm[1]);
        }
        if (!$title && preg_match('/<img[^>]+alt="([^"]+)"/i', $block, $tm)) {
            $title = cleanText($tm[1]);
        }
        $poster = '';
        if (preg_match('/<img[^>]+src="([^"]+)"/i', $block, $pm)) {
            $poster = toAbsolute($pm[1], $base);
        }
        if (!$title) continue;
        $type = 'movie';
        if (preg_match('#/serie/#i', $href))       $type = 'series';
        elseif (preg_match('#/season/#i', $href))  $type = 'season';
        elseif (preg_match('#/episode/#i', $href)) $type = 'episode';
        $full = toAbsolute($href, $base);
        $key = strtolower(trim($title));
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $items[] = [
            'title'  => $title,
            'url'    => $full,
            'poster' => $poster,
            'type'   => $type,
            'source' => 'egydead',
        ];
    }
    return $items;
}

// ---- Blkom cards (content-inner blocks) ----
function parseBlkom(string $html, string $base): array {
    $items = [];
    $pattern = '/<div[^>]*class="content"[^>]*>\s*<div[^>]*class="content-inner"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/si';
    if (!preg_match_all($pattern, $html, $blocks, PREG_SET_ORDER)) {
        $pattern = '/<div[^>]*class="content"[^>]*>(.*?)<\/div>\s*<\/div>/si';
        preg_match_all($pattern, $html, $blocks, PREG_SET_ORDER);
    }
    $seen = [];
    foreach ($blocks as $block) {
        $inner = $block[1];
        $href = '';
        if (preg_match('/<div[^>]*class="poster"[^>]*>\s*<a[^>]+href=["\']([^"\']+)["\']/', $inner, $hm)) {
            $href = trim($hm[1]);
        }
        if (!$href && preg_match('/<div[^>]*class="name"[^>]*>\s*<a[^>]+href=["\']([^"\']+)["\']/', $inner, $hm)) {
            $href = trim($hm[1]);
        }
        if (!$href) continue;
        $title = '';
        if (preg_match('/<div[^>]*class="name"[^>]*>\s*<a[^>]*>(.*?)<\/a>/si', $inner, $tm)) {
            $title = cleanText($tm[1]);
        }
        if (!$title && preg_match('/alt=["\']([^"\']+)["\']/', $inner, $am)) {
            $title = rtrim(cleanText($am[1]), ' poster');
        }
        if (!$title) continue;
        $poster = '';
        if (preg_match('/<img[^>]+data-original=["\']([^"\']+)["\']/', $inner, $pm)) {
            $poster = toAbsolute($pm[1], $base);
        }
        if (!$poster && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $inner, $pm)) {
            $poster = toAbsolute($pm[1], $base);
        }
        $key = strtolower(trim($title));
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $items[] = [
            'title'  => $title,
            'url'    => toAbsolute($href, $base),
            'poster' => $poster,
            'type'   => 'anime',
            'source' => 'blkom',
        ];
    }
    return $items;
}

$categories = [];

$freshHtml = httpGet('https://tv10.egydead.live/', 'https://tv10.egydead.live/');
$fresh = $freshHtml ? parseEgyDead($freshHtml, 'https://tv10.egydead.live/') : [];
$fresh = array_values(array_filter($fresh, fn($i) => $i['type'] === 'episode' || $i['type'] === 'movie'));
if (count($fresh) > 0) {
    $categories[] = ['key' => 'fresh', 'title' => 'Fresh Releases', 'items' => array_slice($fresh, 0, 30)];
}

$moviesHtml = httpGet('https://tv10.egydead.live/page/movies', 'https://tv10.egydead.live/');
$movies = $moviesHtml ? parseEgyDead($moviesHtml, 'https://tv10.egydead.live/') : [];
if (count($movies) > 0) {
    $categories[] = ['key' => 'movies', 'title' => 'Movies', 'items' => array_slice($movies, 0, 30)];
}

$seriesHtml = httpGet('https://tv10.egydead.live/series-category/arabic-series/', 'https://tv10.egydead.live/');
$series = $seriesHtml ? parseEgyDead($seriesHtml, 'https://tv10.egydead.live/') : [];
if (count($series) > 0) {
    $categories[] = ['key' => 'series', 'title' => 'Arabic Series', 'items' => array_slice($series, 0, 30)];
}

$animeHtml = httpGet('http://103.155.92.42/anime-list', 'http://103.155.92.42/');
$anime = $animeHtml ? parseBlkom($animeHtml, 'http://103.155.92.42') : [];
if (count($anime) > 0) {
    $categories[] = ['key' => 'anime', 'title' => 'Anime', 'items' => array_slice($anime, 0, 30)];
}

if (count($categories) > 0) {
    file_put_contents($cacheFile, json_encode($categories, JSON_UNESCAPED_UNICODE));
}
echo json_encode(['categories' => $categories, 'cached' => false]);