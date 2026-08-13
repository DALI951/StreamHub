<?php
error_reporting(E_ERROR | E_PARSE);

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
if (!empty($respHeaders['content-range'])) header('Content-Range: ' . $respHeaders['content-range']);
if (!empty($respHeaders['content-length']) && !$isPlaylist) header('Content-Length: ' . $respHeaders['content-length']);
if ($dl) {
    $name = basename(parse_url($url, PHP_URL_PATH)) ?: 'video.mp4';
    header('Content-Disposition: attachment; filename="' . $name . '"');
}
if ($isPlaylist) {
    if ($body) echo $body;
    else http_response_code(204);
    exit;
}
if (is_string($body)) echo $body;
exit;

function rewritePlaylist(string $body, string $base, bool $dl, string $ref = ''): string {
    $q = ($dl ? '&dl=1' : '') . ($ref ? '&ref=' . rawurlencode($ref) : '');
    $lines = explode("\n", $body);
    $out = [];
    foreach ($lines as $line) {
        $line = rtrim($line, "\r");
        if ($line === '') {
            $out[] = $line;
            continue;
        }
        if ($line[0] === '#') {
            if (preg_match('#^#EXT-X-(MAP|KEY):#i', $line)) {
                $line = preg_replace_callback('/URI="([^"]+)"/', function ($mm) use ($base, $q) {
                    return 'URI="' . 'api/proxy.php?url=' . rawurlencode(resolveUrl($base, $mm[1])) . $q . '"';
                }, $line);
            }
            $out[] = $line;
            continue;
        }
        $abs = resolveUrl($base, $line);
        $out[] = 'api/proxy.php?url=' . rawurlencode($abs) . $q;
    }
    return implode("\n", $out);
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
