<?php
error_reporting(E_ERROR | E_PARSE);

$apiBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api'), '/');

$url = trim($_GET['url'] ?? '');
if (empty($url) || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('bad url');
}

$dl  = isset($_GET['dl']);
$ref = trim($_GET['ref'] ?? '');
$head = (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') || isset($_GET['head']);

$isPlaylist = (bool)preg_match('/\.m3u8($|\?)/i', $url);
$forceMpeg = isset($_GET['mpeg']);

$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Accept: */*',
];
if ($ref) $headers[] = 'Referer: ' . $ref;
if (isset($_SERVER['HTTP_RANGE'])) $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$status, &$respHeaders) {
        $t = trim($line);
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $t, $m)) {
            $status = (int)$m[1];
        } elseif (stripos($t, 'Content-Type:') === 0) {
            $respHeaders['content-type'] = trim(substr($t, 13));
        } elseif (stripos($t, 'Content-Length:') === 0) {
            $respHeaders['content-length'] = trim(substr($t, 15));
        } elseif (stripos($t, 'Content-Range:') === 0) {
            $respHeaders['content-range'] = trim(substr($t, 14));
        }
        return strlen($line);
    },
]);

$body = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

// decimal-ASCII obfuscated playlist (EarnVids): decode before anything else
$decodedPlaylist = decodeDecimalAscii((string)$body);
if ($decodedPlaylist !== null && strpos($decodedPlaylist, '#EXTM3U') !== false) {
    $body = $decodedPlaylist;
    $isPlaylist = true;
    $respHeaders['content-type'] = 'application/vnd.apple.mpegurl';
}

// PNG-wrapped TS segments (EarnVids -> tiktokcdn): strip the fake header
$stripN = 0;
if (!$isPlaylist) {
    $clientRange = isset($_SERVER['HTTP_RANGE']);
    if ($clientRange) {
        // refetch full body so the PNG header is present to strip, then serve range locally
        $ch2 = curl_init($url);
        $h2 = [];
        foreach ($headers as $h) {
            if (stripos($h, 'Range:') !== 0) $h2[] = $h;
        }
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => $h2,
        ]);
        $body = curl_exec($ch2);
        $info = curl_getinfo($ch2);
        curl_close($ch2);
    }
    $stripped = stripPngWrap((string)$body);
    $body = $stripped[0];
    $stripN = $stripped[1];
    if ($stripN > 0) {
        $respHeaders['content-type'] = 'video/mp2t';
        unset($respHeaders['content-range'], $respHeaders['content-length']);
    }
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Referer');

if ($err) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    exit('proxy error');
}

$status = $status ?? (int)($info['http_code'] ?? 200);
http_response_code($status);

if ($head) {
    if (!empty($respHeaders['content-type'])) header('Content-Type: ' . $respHeaders['content-type']);
    if (!empty($respHeaders['content-length'])) header('Content-Length: ' . $respHeaders['content-length']);
    if (!empty($respHeaders['content-range'])) header('Content-Range: ' . $respHeaders['content-range']);
    exit;
}

if ($isPlaylist && $body && strpos($body, '#EXTM3U') !== false) {
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: no-store');
    echo rewritePlaylist($body, $url, $dl, $ref);
    exit;
}

if ($isPlaylist && !$body) {
    http_response_code(204);
    exit;
}

if (!empty($respHeaders['content-type'])) header('Content-Type: ' . $respHeaders['content-type']);
if ($dl) {
    $name = basename(parse_url($url, PHP_URL_PATH)) ?: 'video.mp4';
    header('Content-Disposition: attachment; filename="' . $name . '"');
}

if ($isPlaylist) {
    if ($body) echo $body;
    else http_response_code(204);
    exit;
}

if (is_string($body)) {
    $len = strlen($body);
    if (!$isPlaylist && isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $rm)) {
        $start = (int)$rm[1];
        $end = $rm[2] === '' ? $len - 1 : min((int)$rm[2], $len - 1);
        if ($start > $end || $start >= $len) {
            http_response_code(416);
            header('Content-Range: bytes */' . $len);
            exit;
        }
        http_response_code(206);
        header('Accept-Ranges: bytes');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $len);
        header('Content-Length: ' . ($end - $start + 1));
        echo substr($body, $start, $end - $start + 1);
        exit;
    }
    if ($stripN > 0) header('Content-Length: ' . $len);
    echo $body;
}
exit;

function rewritePlaylist(string $body, string $base, bool $dl, string $ref = ''): string {
    global $apiBase;
    $q = ($dl ? '&dl=1' : '') . ($ref ? '&ref=' . rawurlencode($ref) : '');
    // propagate the token param from the master URL to every rewritten child
    $baseQuery = parse_url($base, PHP_URL_QUERY) ?? '';
    $tok = '';
    if (preg_match('/(?:^|&)token=([^&]+)/', $baseQuery, $tm)) {
        $tok = (strpos($q, '?') !== false ? '&' : '') . 'token=' . $tm[1];
        $tok = '&token=' . $tm[1];
    }
    $lines = explode("\n", $body);
    $out = [];
    foreach ($lines as $line) {
        $line = rtrim($line, "\r");
        if ($line === '') {
            $out[] = $line;
            continue;
        }
        if ($line[0] === '#') {
            if (preg_match('#^#EXT-X-(MAP|KEY|MEDIA):#i', $line)) {
                $line = preg_replace_callback('/URI="([^"]+)"/', function ($mm) use ($base, $q, $tok) {
                    global $apiBase;
                    return 'URI="' . $apiBase . '/proxy.php?url=' . rawurlencode(resolveUrl($base, $mm[1])) . $q . $tok . '"';
                }, $line);
            }
            $out[] = $line;
            continue;
        }
        $abs = resolveUrl($base, $line);
        $lineTok = (strpos($abs, 'token=') !== false) ? '' : $tok;
        $out[] = $apiBase . '/proxy.php?url=' . rawurlencode($abs) . $q . $lineTok;
    }
    return implode("\n", $out);
}

function decodeDecimalAscii(string $body): ?string {
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

function stripPngWrap(string $body): array {
    // returns [strippedBody, strippedBytes] ; null if not PNG-wrapped
    if (strlen($body) < 80) return [$body, 0];
    if (strncmp($body, "\x89PNG\r\n\x1a\n", 8) !== 0) return [$body, 0];
    $limit = min(strlen($body), 8192);
    for ($i = 8; $i < $limit; $i++) {
        if (ord($body[$i]) !== 0x47) continue;
        if ($i + 376 < strlen($body) && ord($body[$i + 188]) === 0x47 && ord($body[$i + 376]) === 0x47) {
            return [substr($body, $i), $i];
        }
    }
    return [$body, 0];
}

function resolveUrl(string $base, string $u): string {
    if (preg_match('#^https?://#i', $u)) return $u;
    if (strpos($u, '//') === 0) return (parse_url($base, PHP_URL_SCHEME) ?: 'https') . ':' . $u;
    $p = parse_url($base);
    $scheme = $p['scheme'] ?? 'https';
    $host = $p['host'] ?? '';
    if ($u[0] === '/') return "$scheme://$host$u";
    $path = $p['path'] ?? '/';
    $dir = preg_replace('#/[^/]*$#', '', $path);
    if ($dir === '') $dir = '/';
    return "$scheme://$host$dir/$u";
}
