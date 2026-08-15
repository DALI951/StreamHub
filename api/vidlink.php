<?php
/**
 * vidlink.php - VidLink source: title -> TMDB id -> vidlink.pro API (HLS mode)
 * 1080p H.264 HLS via vidlink.pro's mwVault source. The API returns a
 * CloudFront-signed playlist URL + cookie; segments are relayed through
 * proxy.php (cookie passed via &vk= cookie-key param).
 *
 * ?type=movie|tv&q=<title>[&season=N][&episode=N]
 * Returns JSON: {ok:true, type:'hls', url (proxied), quality_label, title, provider}
 */

error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "FATAL: " . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line'];
    }
});

// Public TMDB key (same as vidcore.php — search access only)
$TMDB_KEY = '94cf27b09f1a04a417c026488485185c';

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

$type = trim($_GET['type'] ?? 'movie');
if ($type !== 'tv') $type = 'movie';
$q = trim($_GET['q'] ?? '');
$season = isset($_GET['season']) ? (int)$_GET['season'] : 1;
$episode = isset($_GET['episode']) ? (int)$_GET['episode'] : 1;
if ($season < 1) $season = 1;
if ($episode < 1) $episode = 1;

if ($q === '') {
    echo json_encode(['ok' => false, 'error' => 'missing query']);
    exit;
}

function fetchText($url, $headers = [], $timeout = 20) {
    $hdrs = array_merge(['Accept: */*', 'Accept-Language: en-US,en;q=0.9'], $headers);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => $hdrs,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) return null;
    return $body;
}

$apiBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api'), '/');
$cacheDir = __DIR__ . '/../cache/';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

// ---- vidlink token: XSalsa20-Poly1305 secretbox, 24-byte zero nonce ----
function vidlinkToken(string $mediaId): string {
    $key = hex2bin('c75136c5668bbfe65a7ecad431a745db68b5f381555b38d8f6c699449cf11fcd');
    $nonce = str_repeat("\x00", 24);
    $timestamp = time() + 480;
    $message = $mediaId . pack('J', $timestamp); // 64-bit big-endian
    $encrypted = sodium_crypto_secretbox($message, $nonce, $key);
    return rtrim(strtr(base64_encode($nonce . $encrypted), '+/', '-_'), '=');
}

// ---- cache (file, 30 min — same policy as vidcore.php) ----
$cacheKey = md5($type . '|' . strtolower($q) . '|' . $season . '|' . $episode);
$cacheFile = $cacheDir . '/vidlink_' . $cacheKey . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 1800) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) {
        echo $cached;
        exit;
    }
}

// ---- 1) TMDB search: title -> best matching id ----
$search = fetchText(
    'https://api.themoviedb.org/3/search/' . $type
    . '?api_key=' . $TMDB_KEY
    . '&query=' . rawurlencode($q)
    . '&language=en-US'
);
if ($search === null) {
    echo json_encode(['ok' => false, 'error' => 'tmdb unreachable']);
    exit;
}
$sj = json_decode($search, true);
$results = $sj['results'] ?? [];
if (!$results) {
    echo json_encode(['ok' => false, 'error' => 'id not found']);
    exit;
}
$qWords = array_values(array_filter(preg_split('/\s+/', strtolower($q)), function ($w) { return strlen($w) > 2; }));
$candidates = [];
foreach (array_slice($results, 0, 5) as $r) {
    $name = (string)($r['name'] ?? $r['title'] ?? '');
    $score = 0;
    foreach ($qWords as $w) if (stripos($name, $w) !== false) $score++;
    $candidates[] = ['id' => (int)($r['id'] ?? 0), 'name' => $name, 'score' => $score];
}
usort($candidates, function ($a, $b) { return $b['score'] <=> $a['score']; });
$candidates = array_values(array_filter($candidates, function ($c) { return $c['id'] > 0 && $c['score'] >= 1; }));
if (!$candidates) {
    foreach (array_slice($results, 0, 3) as $r) {
        $candidates[] = ['id' => (int)($r['id'] ?? 0), 'name' => (string)($r['name'] ?? $r['title'] ?? ''), 'score' => 0];
    }
}
$maxScore = $candidates[0]['score'];

// ---- 2) vidlink API (HLS mode) per candidate until one works ----
$best = null; // [proxiedUrl, quality, title]
foreach ($candidates as $cand) {
    $token = vidlinkToken((string)$cand['id']);
    $api = 'https://vidlink.pro/api/b/' . $type . '/' . $token
        . ($type === 'tv' ? '/' . $season . '/' . $episode : '')
        . '?multiLang=0';
    $body = fetchText($api, [
        'Origin: https://vidlink.pro',
        'Referer: https://vidlink.pro/',
        'X-Playback-Environment: hls',
    ], 25);
    if ($body === null) continue;
    $j = json_decode($body, true);
    if (!$j || empty($j['stream']['playlist'])) continue;

    $pl = $j['stream']['playlist'];
    $cookie = (string)($j['stream']['playlistHeaders']['Cookie'] ?? '');

    // quality from the playlist path (vidlink encodes it: ...-1-1-1080-916/...)
    $quality = 0;
    if (preg_match('#-(\d{3,4})-\d+/local\.m3u8#', $pl, $qm)) {
        $quality = (int)$qm[1];
    } elseif (preg_match('#(\d{3,4})p#i', $pl, $qm2)) {
        $quality = (int)$qm2[1];
    }

    // cookie rides along as a query param (server cache dir is not writable)
    $proxied = $apiBase . '/proxy.php?url=' . rawurlencode($pl)
        . ($cookie !== '' ? '&ck=' . rawurlencode($cookie) : '');

    // Validate the playlist actually fetches (vidlink sometimes returns a
    // broken CloudFront cookie -> CDN 403 -> playback would fail). Only claim
    // ok:true when the proxied playlist really returns an m3u8.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $probeUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $proxied;
    $probe = @fetchText($probeUrl, [], 15);
    if ($probe === null || strpos($probe, '#EXTM3U') !== 0) {
        continue;
    }

    $best = [$proxied, $quality, $cand['name']];
    if ($cand['score'] >= $maxScore && $maxScore > 0) break;
}

$out = [];
if ($best === null) {
    $out = ['ok' => false, 'error' => 'vidlink: no playable stream'];
} else {
    $out = [
        'ok' => true,
        'type' => 'hls',
        'url' => $best[0],
        'quality_label' => $best[1] ? $best[1] . 'p' : 'Auto',
        'title' => $best[2],
        'provider' => 'vidlink',
        'subs' => [],
    ];
}
$json = json_encode($out);
@file_put_contents($cacheFile, $json);
echo $json;