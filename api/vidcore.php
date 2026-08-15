<?php
/**
 * vidcore.php - VidCore source: title -> TMDB id (TMDB search API)
 *              -> streamguide.cfd "Perses" API (VidCore's clean HLS backend:
 *                 multiple mirror providers, no ads, no watermark)
 * ?type=movie|tv&q=<title>[&season=N][&episode=N]
 * Returns JSON: {ok:true, type:'hls', url (proxied), quality_label, title}
 * Falls back to nothing here — app.js falls back to vidsrc.php.
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

// Public TMDB key (ships inside VidCore's own embed JS). Swap for your own
// free TMDB API key anytime — the endpoint only needs search access.
$TMDB_KEY = '94cf27b09f1a04a417c026488485185c';

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

$type = trim($_GET['type'] ?? 'movie');
if ($type !== 'tv') $type = 'movie';
$q = trim($_GET['q'] ?? '');
$season = isset($_GET['season']) ? (int)$_GET['season'] : null;
$episode = isset($_GET['episode']) ? (int)$_GET['episode'] : null;

if ($q === '') {
    echo json_encode(['ok' => false, 'error' => 'missing query']);
    exit;
}

function fetchText($url, $referer = null, $ua = null) {
    $hdrs = ['Accept: */*', 'Accept-Language: en-US,en;q=0.9'];
    if ($referer) $hdrs[] = 'Referer: ' . $referer;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => $ua ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => $hdrs,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) return null;
    return $body;
}

$apiBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api'), '/');

function proxyUrl($u) {
    global $apiBase;
    return $apiBase . '/proxy.php?url=' . rawurlencode($u);
}

function decodeDecimalAscii(string $body): ?string {
    // streamguide.cfd serves some masters as decimal-ASCII (same CDN trick as
    // EarnVids): newline-separated ASCII codes, optionally BOM-prefixed.
    if (strncmp($body, "\xEF\xBB\xBF", 3) === 0) $body = substr($body, 3);
    $t = trim($body);
    if ($t === '' || !preg_match('#^[\d\s]+$#', $t)) return null;
    $nums = preg_split('#\s+#', $t);
    if (count($nums) < 10) return null;
    $bytes = '';
    foreach ($nums as $n) {
        if ($n === '') continue;
        $bytes .= chr((int)$n & 0xFF);
    }
    return $bytes;
}

// ---- cache (file, 30 min — same policy as unwrap.php) ----
$cacheDir = __DIR__ . '/../cache/';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheKey = md5($type . '|' . strtolower($q) . '|' . ($season ?: 0) . '|' . ($episode ?: 0));
$cacheFile = $cacheDir . '/vidcore_' . $cacheKey . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 1800) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false) {
        echo $cached;
        exit;
    }
}

// ---- 1) TMDB search: title -> candidate ids, best title match first ----
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
    // no word match at all — still try the #1 result rather than failing
    foreach (array_slice($results, 0, 3) as $r) {
        $candidates[] = ['id' => (int)($r['id'] ?? 0), 'name' => (string)($r['name'] ?? $r['title'] ?? ''), 'score' => 0];
    }
}
$maxScore = $candidates[0]['score'];

// ---- 2) streamguide.cfd Perses: pick the provider with the highest
//      real resolution (fetch each master, like vidsrc.php does) ----
$best = null;         // [url, height, name]
$bestTitle = '';
$full = false;
$masterFetches = 0;
foreach ($candidates as $cand) {
    $ep = 'https://streamguide.cfd/Perses/' . $type . '/' . $cand['id']
        . ($type === 'tv' ? '/' . ($season ?: 1) . '/' . ($episode ?: 1) : '')
        . '?verify=true';
    $body = fetchText($ep, 'https://vidcore.org/');
    if ($body === null) continue;
    $pj = json_decode($body, true);
    $providers = $pj['providers'] ?? [];
    if (!$providers) continue;
    $bestTitle = $cand['name'];

    foreach ($providers as $prov) {
        foreach (($prov['sources'] ?? []) as $src) {
            if (($src['type'] ?? '') !== 'hls' || empty($src['url'])) continue;
            if ($masterFetches >= 12) break 2;
            $masterFetches++;
            // No referer first: the server IP then gets the FULL master with
            // RESOLUTION tags. With a vidcore.org referer the same URL serves
            // a media playlist (segments inline, no RESOLUTION) — playable,
            // but unlabeled. Fall back if the no-referer fetch fails.
            $m = fetchText($src['url']);
            if ($m === null) $m = fetchText($src['url'], 'https://vidcore.org/');
            if ($m === null) continue;
            // decode decimal-ASCII masters (some server IPs get these)
            $decoded = decodeDecimalAscii((string)$m);
            if ($decoded !== null && strpos($decoded, '#EXTM3U') !== false) $m = $decoded;
            if (strpos($m, '#EXTM3U') === false) continue;
            $height = 0;
            if (preg_match_all('/RESOLUTION=\d+x(\d+)/', $m, $rm)) {
                foreach ($rm[1] as $hh) $height = max($height, (int)$hh);
            }
            if ($best === null || $height > $best[1]) {
                $best = [$src['url'], $height, $prov['name'] ?? 'mirror'];
            }
        }
    }
    if ($best !== null && $cand['score'] >= $maxScore && $maxScore > 0) {
        $full = true; // best-scoring title already has a working stream
        break;
    }
}

$out = [];
if ($best === null) {
    $out = ['ok' => false, 'error' => 'vidcore: no playable stream'];
} else {
    $out = [
        'ok' => true,
        'type' => 'hls',
        'url' => proxyUrl($best[0]),
        'quality_label' => $best[1] ? $best[1] . 'p' : 'Auto',
        'title' => $bestTitle,
        'provider' => $best[2],
        'subs' => [],
    ];
}
$json = json_encode($out);
@file_put_contents($cacheFile, $json);
echo $json;