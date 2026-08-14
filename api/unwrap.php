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
 *  - vidaraa.cc/e/{id}      (Streamix)  : POST /api/stream {filecode} -> streaming_url (JWPlayer)
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

function fetchUrl($url, $referer = null, $post = false, $postBody = null) {
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
        if ($postBody !== null) {
            $opts[CURLOPT_POSTFIELDS] = $postBody;
            $opts[CURLOPT_HTTPHEADER] = array_merge($hdrs, ['Content-Type: application/json']);
        }
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
} elseif (strpos($host, 'vidaraa') !== false) {
    // ---- Streamix (vidaraa.cc) ----
    if (!preg_match('#/e/([A-Za-z0-9]+)#', $embed, $m)) {
        echo json_encode(['ok' => false, 'error' => 'bad vidaraa url']);
        exit;
    }
    $id = $m[1];
    $scheme = parse_url($embed, PHP_URL_SCHEME) ?: 'https';
    $api = fetchUrl($scheme . '://' . $host . '/api/stream', $embed, true, json_encode(['filecode' => $id, 'device' => 'web']));
    $payload = $api;
    if ($payload === null) {
        echo json_encode(['ok' => false, 'error' => 'vidaraa api unreachable']);
        exit;
    }
    $j = json_decode($payload, true);
    $file = $j['streaming_url'] ?? null;
    if (!$file) {
        echo json_encode(['ok' => false, 'error' => 'no streaming url from vidaraa']);
        exit;
    }
    $subs = [];
    if (!empty($j['subtitles']) && is_array($j['subtitles'])) {
        foreach ($j['subtitles'] as $s) {
            if (!empty($s['file_path']) && !empty($s['language'])) {
                $subs[] = ['lang' => $s['language'], 'url' => $s['file_path']];
            }
        }
    }
    $result = ['ok' => true, 'type' => 'hls', 'url' => proxyUrl($file), 'subs' => $subs];
} elseif (strpos($host, 'dood') !== false || strpos($host, 'vidmoly') !== false || strpos($host, 'doodstream') !== false) {
    // ---- DoodStream family (dood.to/dood.re/vidmoly.to) ----
    if (!preg_match('#/e/([A-Za-z0-9]+)#', $embed, $m)) {
        echo json_encode(['ok' => false, 'error' => 'bad dood url']);
        exit;
    }
    $id = $m[1];
    $scheme = parse_url($embed, PHP_URL_SCHEME) ?: 'https';
    $page = fetchUrl($embed, $embed);
    if ($page === null) {
        echo json_encode(['ok' => false, 'error' => 'dood unreachable']);
        exit;
    }
    $token = null;
    if (preg_match('#token=([a-f0-9]+)#', $page, $tm)) $token = $tm[1];
    $api = fetchUrl("$scheme://$host/pass_md5/$id" . ($token ? "?token=$token" : ''), $embed);
    if ($api === null) {
        echo json_encode(['ok' => false, 'error' => 'dood pass_md5 failed']);
        exit;
    }
    $path = trim(strip_tags($api));
    if (!preg_match('#^/#', $path) && strpos($path, '://') === false) {
        $path = '/' . ltrim($path, '/');
    }
    if (strpos($path, '://') === false) $path = "$scheme://$host$path";
    $play = fetchUrl($path, $embed);
    if ($play === null) {
        echo json_encode(['ok' => false, 'error' => 'dood play page failed']);
        exit;
    }
    if (isset($_GET['debug'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'TOKEN: ' . ($token ?: 'none') . "\nPATH: $path\nLEN: " . strlen($play) . "\n";
        echo substr($play, 0, 1500);
        exit;
    }
    $file = null;
    if (preg_match('#<video[^>]+src="([^"]+)"#i', $play, $vm)) {
        $file = $vm[1];
    } elseif (preg_match('#https?://[^"\']+\.(?:mp4|m3u8)(?:\?[^"\']*)?#i', $play, $um)) {
        $file = $um[0];
    }
    if (!$file) {
        echo json_encode(['ok' => false, 'error' => 'no file on dood play page']);
        exit;
    }
    $result = ['ok' => true, 'type' => strpos($file, '.m3u8') !== false ? 'hls' : 'mp4', 'url' => $file, 'subs' => []];
} elseif (strpos($host, 'streamtape') !== false) {
    // ---- StreamTape ----
    if (!preg_match('#/e/([A-Za-z0-9]+)#', $embed, $m)) {
        echo json_encode(['ok' => false, 'error' => 'bad streamtape url']);
        exit;
    }
    $scheme = parse_url($embed, PHP_URL_SCHEME) ?: 'https';
    $page = fetchUrl($embed, $embed);
    if ($page === null) {
        echo json_encode(['ok' => false, 'error' => 'streamtape unreachable']);
        exit;
    }
    if (isset($_GET['debug'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'LEN: ' . strlen($page) . "\n";
        echo substr($page, 0, 2000);
        exit;
    }
    $file = null;
    if (preg_match('#innerHTML\s*=\s*unescape\(\'([^\']+)\'\)#i', $page, $im) ||
        preg_match('#innerHTML\s*=\s*\'([^\']+)\'#i', $page, $im)) {
        $dec = html_entity_decode(stripcslashes($im[1]), ENT_QUOTES);
        if (preg_match('#https?://[^"\']+\.(?:mp4|m3u8)(?:\?[^"\']*)?#i', $dec, $um)) {
            $file = $um[0];
        }
    }
    if (!$file && preg_match('#https?://[^"\']+\.(?:mp4|m3u8)(?:\?[^"\']*)?#i', $page, $um)) {
        $file = $um[0];
    }
    if (!$file) {
        echo json_encode(['ok' => false, 'error' => 'no file on streamtape']);
        exit;
    }
    $result = ['ok' => true, 'type' => strpos($file, '.m3u8') !== false ? 'hls' : 'mp4', 'url' => $file, 'subs' => []];
} elseif (strpos($host, 'voe') !== false) {
    // ---- Voe.sx ----
    if (!preg_match('#/e/([A-Za-z0-9]+)#', $embed, $m)) {
        echo json_encode(['ok' => false, 'error' => 'bad voe url']);
        exit;
    }
    $id = $m[1];
    $scheme = parse_url($embed, PHP_URL_SCHEME) ?: 'https';
    $page = fetchUrl($embed, $embed);
    if ($page === null) {
        echo json_encode(['ok' => false, 'error' => 'voe unreachable']);
        exit;
    }
    $file = null;
    $hash = null;
    if (preg_match('#\?hash=([a-zA-Z0-9]+)#', $page, $hm)) $hash = $hm[1];
    if ($hash) {
        foreach (["$scheme://$host/api/make/$id?hash=$hash", "$scheme://$host/api/player/$id?hash=$hash"] as $apiUrl) {
            $api = fetchUrl($apiUrl, $embed);
            if ($api === null) continue;
            $j = json_decode($api, true);
            $cand = $j['data']['file'] ?? $j['data']['url'] ?? $j['file'] ?? $j['url'] ?? null;
            if ($cand && preg_match('#^https?://#i', $cand)) { $file = $cand; break; }
            if (preg_match('#https?://[^"\'\\\]+\\.(?:mp4|m3u8)[^"\'\\\]*#i', $api, $um)) { $file = $um[0]; break; }
        }
    }
    if (!$file && preg_match('#https?://[^"\']+\.(?:mp4|m3u8)(?:\?[^"\']*)?#i', $page, $um)) {
        $file = $um[0];
    }
    if (!$file) {
        echo json_encode(['ok' => false, 'error' => 'no file on voe']);
        exit;
    }
    $result = ['ok' => true, 'type' => strpos($file, '.m3u8') !== false ? 'hls' : 'mp4', 'url' => $file, 'subs' => []];
} else {
    // ---- Universal fallback ("watermark removal") ----
    // Fetch the embed page and scan it for a direct video URL. If the host
    // leaks an m3u8/mp4 anywhere (video tags, JSON config, unescape blocks),
    // we rip it out of the iframe and play it in OUR clean player — no
    // watermark, no broken embed controls.
    if (isset($_GET['debug'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "EMBED: $embed\n";
    }
    $page = fetchUrl($embed, $embed);
    if ($page === null) {
        echo json_encode(['ok' => false, 'error' => 'embed unreachable']);
        exit;
    }
    if (isset($_GET['debug'])) {
        echo 'LEN: ' . strlen($page) . "\n";
        echo substr($page, 0, 2000);
        exit;
    }
    $file = null;
    if (preg_match('#<video[^>]+src=["\']([^"\']+)["\']#i', $page, $vm)) {
        $file = $vm[1];
    } elseif (preg_match('#<source[^>]+src=["\']([^"\']+)["\']#i', $page, $vm)) {
        $file = $vm[1];
    }
    if (!$file && preg_match_all('#["\'](?:file|src|url|hls|mp4|stream)["\']\s*:\s*["\']([^"\']+\.(?:m3u8|mp4)[^"\']*)["\']#i', $page, $um)) {
        foreach ($um[1] as $cand) {
            if ($cand !== '') { $file = $cand; break; }
        }
    }
    if (!$file && preg_match('#unescape\(\'([^\']+)\'\)#i', $page, $im)) {
        $dec = html_entity_decode(stripcslashes($im[1]), ENT_QUOTES);
        if (preg_match('#https?://[^"\']+\.(?:m3u8|mp4)[^"\']*#i', $dec, $dm)) {
            $file = $dm[0];
        }
    }
    if (!$file && preg_match('#https?://[^"\']+\.(?:m3u8|mp4)(?:\?[^"\']*)?#i', $page, $um)) {
        $file = $um[0];
    }
    if (!$file) {
        echo json_encode(['ok' => false, 'error' => 'no direct source']);
        exit;
    }
    $result = ['ok' => true, 'type' => stripos($file, '.m3u8') !== false ? 'hls' : 'mp4', 'url' => $file, 'subs' => []];
}

file_put_contents($cacheFile, json_encode($result));
echo json_encode($result);