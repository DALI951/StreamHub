<?php
/**
 * unwrap.php - EgyDead embed -> root stream extraction
 * ?url=<embed url>
 * Returns JSON: {ok:true, type:'hls'|'mp4', url, subs:[{lang,url}]} or {ok:false}
 *
 * Supported:
 *  - morencius.com/v/{id}   (EarnVids)  : base36 packer -> links.hls4 -> decimal-ASCII m3u8 -> media playlist
 *  - hgcloud.to/e/{id}      (StreamHG)  : POST /api/sources/{id} -> m3u8
 *  - mixdrop.top/e/{id}     (Mixdrop)   : GET /f/{id} -> JSON wurl/rurl
 */

error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
$ua = $config['scraping']['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

if (!isset($_GET['url'])) {
    echo json_encode(['ok' => false, 'error' => 'missing url']);
    exit;
}
$embed = trim($_GET['url']);
if (!preg_match('#^https?://#i', $embed)) {
    echo json_encode(['ok' => false, 'error' => 'bad url']);
    exit;
}

$cacheDir = __DIR__ . '/../cache/';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheKey = 'unwrap_' . md5($embed);
$cacheFile = $cacheDir . $cacheKey . '.json';

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 1800) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        echo json_encode($cached);
        exit;
    }
}

function fetchUrl($url, $referer = null, $post = false) {
    global $ua;
    $ch = curl_init();
    $hdrs = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Sec-CH-UA: "Chromium";v="125", "Google Chrome";v="125", "Not.A/Brand";v="24"',
        'Sec-CH-UA-Mobile: ?0',
        'Sec-CH-UA-Platform: "Windows"',
        'Upgrade-Insecure-Requests: 1',
    ];
    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_HTTPHEADER     => $hdrs,
    ];
    if ($referer) {
        $opts[CURLOPT_REFERER] = $referer;
    }
    if ($post) {
        $opts[CURLOPT_POST] = true;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    if ($body === false || ($info['http_code'] ?? 0) >= 400) {
        return null;
    }
    return $body;
}

function toBase36($i) {
    $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';
    if ($i < 36) return $alphabet[$i];
    $out = '';
    while ($i > 0) {
        $out = $alphabet[$i % 36] . $out;
        $i = intdiv($i, 36);
    }
    return $out;
}

function unpackPacker($html) {
    // Dean Edwards packer: eval(function(p,a,c,k,e,d){...}('P',a,c,'K'.split('|')))
    $start = strpos($html, 'eval(function(p,a,c,k,e,d)');
    if ($start === false) return null;
    $bodyEnd = strpos($html, "}('", $start);
    if ($bodyEnd === false) return null;
    $pStart = $bodyEnd + 3;
    // scan P string (single-quoted, \' escapes)
    $p = '';
    $i = $pStart;
    $n = strlen($html);
    for (; $i < $n; $i++) {
        $c = $html[$i];
        if ($c === '\\' && $i + 1 < $n) { $p .= $html[$i + 1]; $i++; continue; }
        if ($c === "'") break;
        $p .= $c;
    }
    if ($i >= $n) return null;
    // after P: ,a,c,'K'.split('|'))
    $rest = substr($html, $i + 1);
    if (!preg_match("/^,\s*(\d+),\s*(\d+),\s*'((?:[^'\\\\]|\\\\.)*)'\.split\('\|'\)/s", $rest, $m)) {
        return null;
    }
    $a = (int)$m[1];
    $c = (int)$m[2];
    $k = array_map(function ($x) { return str_replace(["\\'", '\\\\'], ["'", '\\'], $x); }, explode('|', $m[3]));
    if ($a != 36 || $c < 1 || count($k) < $c) return null;
    $out = $p;
    for ($i = $c - 1; $i >= 0; $i--) {
        $tok = toBase36($i);
        if ($k[$i] !== '') {
            $out = preg_replace('/\b' . preg_quote($tok, '/') . '\b/', $k[$i], $out);
        }
    }
    return $out;
}

function decodeDecimalAscii($body) {
    // body = newline-separated decimal ASCII codes, optionally BOM-prefixed
    $b = $body;
    if (strncmp($b, "\xEF\xBB\xBF", 3) === 0) $b = substr($b, 3);
    $t = trim($b);
    if (!preg_match('#^[\d\s]+$#', $t)) return null;
    $nums = preg_split('#\s+#', trim($t));
    if (count($nums) < 10) return null;
    $bytes = '';
    foreach ($nums as $n) {
        if ($n === '') continue;
        $bytes .= chr((int)$n & 0xFF);
    }
    return $bytes;
}

$apiBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api'), '/');

function proxyUrl($u) {
    global $apiBase;
    return $apiBase . '/proxy.php?url=' . rawurlencode($u);
}

$host = parse_url($embed, PHP_URL_HOST);

if (strpos($host, 'morencius.com') !== false || strpos($host, 'earnvids.com') !== false) {
    // ---- EarnVids ----
    if (!preg_match('#/v/([A-Za-z0-9]+)#', $embed, $m)) {
        echo json_encode(['ok' => false, 'error' => 'bad earnvids url']);
        exit;
    }
    $id = $m[1];
    $page = fetchUrl("https://morencius.com/v/$id", 'https://tv10.egydead.live/');
    if ($page === null) {
        echo json_encode(['ok' => false, 'error' => 'embed unreachable']);
        exit;
    }
    if (isset($_GET['debug'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'LEN: ' . strlen($page) . "\n";
        echo 'HAS_PACKER: ' . (strpos($page, 'eval(function') !== false ? 'yes' : 'no') . "\n";
        echo 'HAS_LINKS: ' . (strpos($page, 'var links') !== false ? 'yes' : 'no') . "\n";
        echo $page;
        exit;
    }
    $decoded = unpackPacker($page);
    if ($decoded === null) {
        echo json_encode(['ok' => false, 'error' => 'packer not found']);
        exit;
    }
    // links = {"hls4":"...","hls2":"...","hls3":"..."}
    if (!preg_match('#var links\s*=\s*(\{.*?\});#s', $decoded, $lm)) {
        echo json_encode(['ok' => false, 'error' => 'links not found']);
        exit;
    }
    if (!preg_match_all('#"hls\d+"\s*:\s*"([^"]+)"#', $lm[1], $urls)) {
        echo json_encode(['ok' => false, 'error' => 'no hls links']);
        exit;
    }
    $root = null;
    foreach ($urls[1] as $u) {
        if ($u === '') continue;
        if (preg_match('#^/#', $u)) {
            $root = 'https://morencius.com' . $u;
            break;
        }
    }
    if ($root === null) {
        foreach ($urls[1] as $u) {
            if ($u === '' || !preg_match('#^https?://#', $u)) continue;
            $root = $u;
            break;
        }
    }
    if ($root === null) {
        echo json_encode(['ok' => false, 'error' => 'no usable link']);
        exit;
    }
    // fetch master (decimal-ASCII)
    $master = fetchUrl($root, "https://morencius.com/v/$id");
    if ($master === null) {
        echo json_encode(['ok' => false, 'error' => 'master unreachable']);
        exit;
    }
    if (isset($_GET['debug2'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ROOT: ' . $root . "\nLEN: " . strlen($master) . "\n";
        echo substr($master, 0, 600);
        exit;
    }
    $masterText = decodeDecimalAscii($master);
    if ($masterText === null && strpos($master, '#EXTM3U') !== false) {
        $masterText = $master;
    }
    if ($masterText === null) {
        echo json_encode(['ok' => false, 'error' => 'master not decimal-ascii']);
        exit;
    }
    if (!preg_match('#^[A-Za-z0-9_./\-]+\.m3u8\s*$#m', $masterText, $cm)) {
        echo json_encode(['ok' => false, 'error' => 'no child playlist']);
        exit;
    }
    $base = preg_replace('#[^/]*$#', '', $root);
    $child = $base . trim($cm[0]);
    $media = fetchUrl($child, "https://morencius.com/v/$id");
    if ($media === null) {
        echo json_encode(['ok' => false, 'error' => 'media unreachable']);
        exit;
    }
    $mediaText = decodeDecimalAscii($media);
    if ($mediaText === null && strpos($media, '#EXTM3U') !== false) {
        $mediaText = $media;
    }
    if ($mediaText === null) {
        echo json_encode(['ok' => false, 'error' => 'media not decimal-ascii']);
        exit;
    }
    $result = ['ok' => true, 'type' => 'hls', 'url' => proxyUrl($child), 'subs' => []];
} elseif (strpos($host, 'hgcloud.to') !== false) {
    // ---- StreamHG ----
    if (!preg_match('#/e/([A-Za-z0-9]+)#', $embed, $m)) {
        echo json_encode(['ok' => false, 'error' => 'bad hgcloud url']);
        exit;
    }
    $id = $m[1];
    $api = fetchUrl("https://hgcloud.to/api/sources/$id", "https://hgcloud.to/e/$id", true);
    if ($api === null) {
        echo json_encode(['ok' => false, 'error' => 'streamhg api down']);
        exit;
    }
    $j = json_decode($api, true);
    $file = $j['sources'][0]['file'] ?? null;
    if (!$file) {
        echo json_encode(['ok' => false, 'error' => 'no source in streamhg api']);
        exit;
    }
    $result = ['ok' => true, 'type' => 'hls', 'url' => proxyUrl($file), 'subs' => []];
} elseif (strpos($host, 'mixdrop') !== false) {
    // ---- Mixdrop ----
    if (!preg_match('#/e/([A-Za-z0-9]+)#', $embed, $m)) {
        echo json_encode(['ok' => false, 'error' => 'bad mixdrop url']);
        exit;
    }
    $id = $m[1];
    $api = fetchUrl("https://mixdrop.top/f/$id", "https://mixdrop.top/e/$id");
    if ($api === null) {
        echo json_encode(['ok' => false, 'error' => 'mixdrop api unreachable']);
        exit;
    }
    $j = json_decode($api, true);
    $wurl = $j['wurl'] ?? null;
    if (!$wurl) {
        echo json_encode(['ok' => false, 'error' => 'file not found on mixdrop']);
        exit;
    }
    $result = ['ok' => true, 'type' => 'mp4', 'url' => $wurl, 'subs' => []];
} else {
    echo json_encode(['ok' => false, 'error' => 'unsupported host']);
    exit;
}

file_put_contents($cacheFile, json_encode($result));
echo json_encode($result);