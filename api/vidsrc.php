<?php
/**
 * vidsrc.php - VidSrc source: title -> IMDb id (keyless, wikipedia+wikidata)
 *              -> vidsrcme HLS stream (api.php + ChaCha20 wasm decrypt + token)
 * ?type=movie|tv&q=<title>[&season=N][&episode=N][&imdb=tt...][&tmdb=N]
 * Returns JSON: {ok:true, type:'hls', url, quality_label} or {ok:false, error}
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

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
$wikiUa = 'StreamHub/1.0 (https://github.com/DALI951/StreamHub)';

$type = trim($_GET['type'] ?? 'movie');
if ($type !== 'tv') $type = 'movie';
$q = trim($_GET['q'] ?? '');
$season = isset($_GET['season']) ? (int)$_GET['season'] : null;
$episode = isset($_GET['episode']) ? (int)$_GET['episode'] : null;
$imdb = trim($_GET['imdb'] ?? '');
$tmdb = trim($_GET['tmdb'] ?? '');

if ($q === '' && $imdb === '' && $tmdb === '') {
    echo json_encode(['ok' => false, 'error' => 'missing query']);
    exit;
}

$cacheDir = __DIR__ . '/../cache/';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$vidsrcDebug = [];
function dbg($msg) { global $vidsrcDebug; $vidsrcDebug[] = $msg; }

function fetchText($url, $referer = null, $origin = null, $ua = null, $accept = null) {
    global $ua;
    $hdrs = ['Accept: */*', 'Accept-Language: en-US,en;q=0.9'];
    if ($referer) $hdrs[] = 'Referer: ' . $referer;
    if ($origin) $hdrs[] = 'Origin: ' . $origin;
    if ($accept) $hdrs[] = 'Accept: ' . $accept;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => $ua ?: $GLOBALS['ua'],
        CURLOPT_HTTPHEADER     => $hdrs,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) return null;
    return $body;
}

function uleb32($s, &$pos) {
    $r = 0; $sh = 0;
    while (true) {
        $b = ord($s[$pos++]);
        $r |= ($b & 0x7F) << $sh;
        if (!($b & 0x80)) break;
        $sh += 7;
    }
    return $r;
}

function sleb32($s, &$pos) {
    $r = 0; $sh = 0; $b = 0;
    while (true) {
        $b = ord($s[$pos++]);
        $r |= ($b & 0x7F) << $sh;
        $sh += 7;
        if (!($b & 0x80)) break;
    }
    if ($sh < 32 && ($b & 0x40)) $r |= (-1 << $sh);
    return $r;
}

function extractWasmData($wasm) {
    // returns [offset => dataBytes] for all data segments
    if (strlen($wasm) < 12 || substr($wasm, 0, 4) !== "\0asm") return null;
    $pos = 8;
    $len = strlen($wasm);
    $segs = [];
    while ($pos + 1 < $len) {
        $sec = ord($wasm[$pos]);
        $pos++;
        $size = uleb32($wasm, $pos);
        $end = $pos + $size;
        if ($sec === 11) {
            $cnt = uleb32($wasm, $pos);
            for ($i = 0; $i < $cnt && $pos < $end; $i++) {
                uleb32($wasm, $pos); // memidx
                $op = ord($wasm[$pos]);
                $pos++;
                $off = 0;
                if ($op === 0x41) {
                    $off = sleb32($wasm, $pos);
                } elseif ($op === 0x23) {
                    uleb32($wasm, $pos); // global.get idx
                }
                $op = ord($wasm[$pos]);
                $pos++; // 0x0B end
                $sz = uleb32($wasm, $pos);
                if ($pos + $sz > $len) break;
                $segs[$off] = substr($wasm, $pos, $sz);
                $pos += $sz;
            }
        }
        $pos = $end;
    }
    return $segs;
}

function rotl32($x, $n) {
    return (($x << $n) | ($x >> (32 - $n))) & 0xFFFFFFFF;
}

function chachaQR(&$a, &$b, &$c, &$d) {
    $a = ($a + $b) & 0xFFFFFFFF; $d ^= $a; $d = rotl32($d, 16);
    $c = ($c + $d) & 0xFFFFFFFF; $b ^= $c; $b = rotl32($b, 12);
    $a = ($a + $b) & 0xFFFFFFFF; $d ^= $a; $d = rotl32($d, 8);
    $c = ($c + $d) & 0xFFFFFFFF; $b ^= $c; $b = rotl32($b, 7);
}

function chacha20Block($key, $nonce, $counter) {
    $st = [0x61707865, 0x3320646e, 0x79622d32, 0x6b206574];
    $k = array_values(unpack('V8', $key));
    $st = array_merge($st, $k);
    $st[] = $counter & 0xFFFFFFFF;
    $n = array_values(unpack('V3', $nonce));
    $st = array_merge($st, $n);
    $x = $st;
    for ($i = 0; $i < 10; $i++) {
        chachaQR($x[0],  $x[4],  $x[8],  $x[12]);
        chachaQR($x[1],  $x[5],  $x[9],  $x[13]);
        chachaQR($x[2],  $x[6],  $x[10], $x[14]);
        chachaQR($x[3],  $x[7],  $x[11], $x[15]);
        chachaQR($x[0],  $x[5],  $x[10], $x[15]);
        chachaQR($x[1],  $x[6],  $x[11], $x[12]);
        chachaQR($x[2],  $x[7],  $x[8],  $x[13]);
        chachaQR($x[3],  $x[4],  $x[9],  $x[14]);
    }
    $out = '';
    for ($i = 0; $i < 16; $i++) $out .= pack('V', ($x[$i] + $st[$i]) & 0xFFFFFFFF);
    return $out;
}

function extractCodeSection($wasm) {
    // returns raw bytes of the code section (id 10) or null
    $pos = 8;
    $len = strlen($wasm);
    while ($pos + 1 < $len) {
        $sec = ord($wasm[$pos]);
        $pos++;
        $size = uleb32($wasm, $pos);
        $end = $pos + $size;
        if ($sec === 10) return substr($wasm, $pos, $size);
        $pos = $end;
    }
    return null;
}

function findKeyPairs($code) {
    // pattern: i32.const X; i32.load 2 0; i32.const Y; i32.load 2 0; i32.xor
    // opcodes: 41 <sleb X> 28 02 00 41 <sleb Y> 28 02 00 73
    $pairs = [];
    $i = 0;
    $len = strlen($code);
    while ($i < $len) {
        if (ord($code[$i]) === 0x41) {
            $p = $i + 1;
            $x = sleb32($code, $p);
            $i2 = $p;
            if ($i2 + 7 <= $len && $code[$i2] === "\x28" && $code[$i2 + 1] === "\x02" && $code[$i2 + 2] === "\x00" && $code[$i2 + 3] === "\x41") {
                $p2 = $i2 + 4;
                $y = sleb32($code, $p2);
                $i3 = $p2;
                if ($i3 + 4 <= $len && $code[$i3] === "\x28" && $code[$i3 + 1] === "\x02" && $code[$i3 + 2] === "\x00" && $code[$i3 + 3] === "\x73") {
                    $pairs[] = [$x & ~3, $y & ~3];
                    $i = $i3;
                }
            }
        }
        $i++;
    }
    return $pairs;
}

function decryptVidsrcStreams($encB64, $wasmUrl) {
    $wasm = fetchText($wasmUrl, 'https://data.vidsrcme.ru/', null, null, 'application/wasm');
    if ($wasm === null) { dbg('wasm fetch failed'); return null; }
    dbg('wasm fetched: ' . strlen($wasm) . ' bytes');
    $segs = extractWasmData($wasm);
    if ($segs === null) { dbg('wasm parse failed'); return null; }
    dbg('data segments: ' . count($segs) . ' at ' . implode(',', array_keys($segs)));
    $code = extractCodeSection($wasm);
    if ($code === null) { dbg('no code section'); return null; }
    $pairs = findKeyPairs($code);
    dbg('key pairs: ' . count($pairs) . ' = ' . implode(';', array_map(function ($p) { return $p[0] . ',' . $p[1]; }, $pairs)));

    $bin = base64_decode($encB64, true);
    if ($bin === false || strlen($bin) < 32) { dbg('bad base64 payload'); return null; }
    $nonce = substr($bin, 0, 12);
    $ct = substr($bin, 12);

    $memAt = function ($off) use ($segs) {
        return isset($segs[$off]) ? $segs[$off] : str_repeat("\0", 32);
    };

    $seen = [];
    foreach ($pairs as [$x, $y]) {
        $k = $x . '-' . $y;
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $a = $memAt($x);
        $b = $memAt($y);
        if (strlen($a) < 32 || strlen($b) < 32) continue;
        $key = '';
        for ($i = 0; $i < 32; $i++) $key .= $a[$i] ^ $b[$i];

        $out = '';
        $c = 0;
        $len = strlen($ct);
        for ($off = 0; $off < $len; $off += 64) {
            $ks = chacha20Block($key, $nonce, $c++);
            $chunk = substr($ct, $off, 64);
            $out .= $chunk ^ $ks;
        }
        if (preg_match('#^https?://#i', $out)) {
            dbg("key pair $k works; head: " . substr($out, 0, 60));
            $text = preg_replace('/^\xEF\xBB\xBF/', '', $out);
            return preg_split('/\r?\n/', trim($text));
        }
    }
    dbg('no key pair produced valid plaintext');
    return null;
}

function resolveImdb($q) {
    $cacheDir = $GLOBALS['cacheDir'];
    $cf = $cacheDir . 'vidid_' . md5($q) . '.json';
    if (is_file($cf) && (time() - filemtime($cf)) < 2592000) {
        $c = json_decode(file_get_contents($cf), true);
        if (!empty($c['imdb'])) return $c['imdb'];
    }
    $wikiUa = $GLOBALS['wikiUa'];
    $s = fetchText('https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=' . rawurlencode($q . ' (film)') . '&format=json&srlimit=5', null, null, $wikiUa, 'application/json');
    if ($s === null) $s = fetchText('https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=' . rawurlencode($q) . '&format=json&srlimit=5', null, null, $wikiUa, 'application/json');
    if ($s === null) return null;
    $sj = json_decode($s, true);
    if (empty($sj['query']['search'])) return null;
    usleep(900000);
    $title = $sj['query']['search'][0]['title'];
    $pp = fetchText('https://en.wikipedia.org/w/api.php?action=query&prop=pageprops&titles=' . rawurlencode($title) . '&format=json', null, null, $wikiUa, 'application/json');
    if ($pp === null) return null;
    $pj = json_decode($pp, true);
    $qid = null;
    foreach (($pj['query']['pages'] ?? []) as $pg) {
        if (!empty($pg['pageprops']['wikibase_item'])) { $qid = $pg['pageprops']['wikibase_item']; break; }
    }
    if (!$qid) return null;
    usleep(900000);
    $wd = fetchText('https://www.wikidata.org/w/api.php?action=wbgetentities&ids=' . $qid . '&props=claims&format=json', null, null, $wikiUa, 'application/json');
    if ($wd === null) return null;
    $wj = json_decode($wd, true);
    $imdb = $wj['entities'][$qid]['claims']['P345'][0]['mainsnak']['datavalue']['value'] ?? null;
    if (!$imdb) return null;
    file_put_contents($cf, json_encode(['imdb' => $imdb]));
    return $imdb;
}

$apiBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api'), '/');

function proxyUrl($u) {
    global $apiBase;
    return $apiBase . '/proxy.php?url=' . rawurlencode($u);
}

// ---- resolve id ----
if (preg_match('#^tt\d+$#i', $imdb)) {
    $idParam = 'imdb=' . $imdb;
} elseif (preg_match('#^\d+$#', $tmdb)) {
    $idParam = 'tmdb=' . $tmdb;
} else {
    $resolved = resolveImdb($q);
    if (!$resolved) {
        echo json_encode(['ok' => false, 'error' => 'id not found']);
        exit;
    }
    $idParam = 'imdb=' . $resolved;
}

// ---- fetch from vidsrcme ----
$apiUrl = 'https://data.vidsrcme.ru/api.php?type=' . $type . '&' . $idParam . '&stream_urls';
if ($type === 'tv' && $season) $apiUrl .= '&season=' . $season;
if ($type === 'tv' && $episode) $apiUrl .= '&episode=' . $episode;
$api = fetchText($apiUrl, 'https://cloudorchestranova.com/', 'https://cloudorchestranova.com', null, 'application/json');
if ($api === null) {
    echo json_encode(['ok' => false, 'error' => 'vidsrc api unreachable']);
    exit;
}
$j = json_decode($api, true);
dbg('api.php status: ' . ($j['status_code'] ?? 'n/a') . ', data keys: ' . implode(',', array_keys($j['data'] ?? [])));
if ((int)($j['status_code'] ?? 0) !== 200 || empty($j['data']['stream_urls'])) {
    if (isset($_GET['debug'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "DEBUG: " . implode("\n", $vidsrcDebug) . "\n--- api raw (first 600):\n" . substr($api, 0, 600);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'vidsrc: no stream']);
    exit;
}
$su = $j['data']['stream_urls'];

if (is_string($su)) {
    $wasmUrl = $j['vs']['wasm_url'] ?? null;
    if (!$wasmUrl) {
        echo json_encode(['ok' => false, 'error' => 'vidsrc: no wasm url']);
        exit;
    }
    $urls = decryptVidsrcStreams($su, $wasmUrl);
    if (!$urls) {
        if (isset($_GET['debug'])) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "DEBUG: " . implode("\n", $vidsrcDebug) . "\n--- su head: " . substr($su, 0, 80) . "\n--- wasm url: $wasmUrl";
            exit;
        }
        echo json_encode(['ok' => false, 'error' => 'vidsrc: decrypt failed']);
        exit;
    }
} elseif (is_array($su)) {
    $urls = $su;
} else {
    echo json_encode(['ok' => false, 'error' => 'vidsrc: bad payload']);
    exit;
}

// ---- token ----
$token = $j['data']['token'] ?? '';
$pick = null;
$bestHeight = 0;
foreach ($urls as $u) {
    $u = trim($u);
    if ($u === '' || !preg_match('#^https?://#i', $u)) continue;
    if ($token === '') {
        $host = parse_url($u, PHP_URL_HOST);
        if ($host) {
            $gen = fetchText('https://' . $host . '/generate.php', 'https://' . $host . '/');
            if ($gen !== null && trim($gen) !== '') $token = trim($gen);
        }
    }
    $u2 = $u . (strpos($u, '?') !== false ? '&' : '?') . 'token=' . rawurlencode($token);
    $chk = fetchText($u2, 'https://' . (parse_url($u, PHP_URL_HOST) ?: ''), null, null, 'application/vnd.apple.mpegurl');
    if ($chk === null || strpos($chk, '#EXTM3U') === false) continue;
    // pick the stream whose master has the HIGHEST video resolution (1080p
    // source may appear only in a later stream URL, not the first one)
    $height = 0;
    if (preg_match_all('/RESOLUTION=\d+x(\d+)/', $chk, $rm)) {
        foreach ($rm[1] as $hh) $height = max($height, (int)$hh);
    }
    dbg("stream candidate $u2 -> " . ($height ?: '?') . "p");
    if ($pick === null || $height > $bestHeight) {
        $pick = $u2;
        $bestHeight = $height;
    }
}
if (!$pick) {
    if (isset($_GET['debug'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "DEBUG: " . implode("\n", $vidsrcDebug) . "\n--- decrypted urls:\n" . implode("\n", $urls) . "\n--- token: " . ($token ?: 'none');
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'vidsrc: no playable stream']);
    exit;
}

echo json_encode([
    'ok' => true,
    'type' => 'hls',
    'url' => proxyUrl($pick),
    'quality_label' => $bestHeight ? 'VidSrc ' . $bestHeight . 'p' : 'VidSrc',
    'subs' => [],
]);