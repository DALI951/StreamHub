<?php
/**
 * probe.php - check if a stream is actually alive before showing it
 * ?url=<stream url>&type=hls|mp4|iframe[&referer=<ref>]
 * Returns JSON: {ok:true|false, reason?:string}
 * Results cached 15 min.
 */

error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
$ua = $config['scraping']['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

$url = trim($_GET['url'] ?? '');
$type = trim($_GET['type'] ?? 'iframe');
$referer = trim($_GET['referer'] ?? '') ?: null;
if ($url === '' || !preg_match('#^https?://#i', $url)) {
    echo json_encode(['ok' => false, 'error' => 'bad url']);
    exit;
}

$cacheDir = __DIR__ . '/../cache/';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheKey = 'probe_' . md5($url . '|' . $type);
$cacheFile = $cacheDir . $cacheKey . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 900) {
    echo file_get_contents($cacheFile);
    exit;
}

function probeFetch($url, $referer, $range = null) {
    global $ua;
    $hdrs = [
        'Accept: */*',
        'Accept-Language: en-US,en;q=0.9',
        'Sec-CH-UA: "Chromium";v="125", "Google Chrome";v="125", "Not.A/Brand";v="24"',
        'Sec-CH-UA-Mobile: ?0',
        'Sec-CH-UA-Platform: "Windows"',
    ];
    $ch = curl_init();
    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_HTTPHEADER     => $hdrs,
    ];
    if ($referer) {
        $opts[CURLOPT_REFERER] = $referer;
    }
    if ($range !== null) {
        $opts[CURLOPT_RANGE] = $range;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [
        'body' => $body === false ? '' : $body,
        'code' => (int)($info['http_code'] ?? 0),
        'ct'   => (string)($info['content_type'] ?? ''),
    ];
}

$result = ['ok' => false, 'reason' => 'unknown'];

if ($type === 'iframe') {
    $r = probeFetch($url, $referer);
    if ($r['body'] === '' || $r['code'] === 0) {
        $result = ['ok' => false, 'reason' => 'unreachable'];
    } elseif ($r['code'] >= 400) {
        $result = ['ok' => false, 'reason' => 'http_' . $r['code']];
    } else {
        $result = ['ok' => true];
    }
} elseif ($type === 'hls') {
    $r = probeFetch($url, $referer, '0-4095');
    if ($r['body'] === '' || $r['code'] === 0) {
        $result = ['ok' => false, 'reason' => 'unreachable'];
    } elseif ($r['code'] >= 400) {
        $result = ['ok' => false, 'reason' => 'http_' . $r['code']];
    } elseif (strpos($r['body'], '#EXTM3U') !== false) {
        $result = ['ok' => true];
    } elseif (stripos($r['ct'], 'html') !== false && strpos(ltrim($r['body']), '<') === 0) {
        $result = ['ok' => false, 'reason' => 'html_page'];
    } else {
        $result = ['ok' => true];
    }
} elseif ($type === 'mp4') {
    $r = probeFetch($url, $referer, '0-4095');
    if ($r['body'] === '' || $r['code'] === 0) {
        $result = ['ok' => false, 'reason' => 'unreachable'];
    } elseif ($r['code'] >= 400) {
        $result = ['ok' => false, 'reason' => 'http_' . $r['code']];
    } elseif (stripos($r['ct'], 'html') !== false && strpos(ltrim($r['body']), '<') === 0 && stripos($r['body'], '<video') === false) {
        $result = ['ok' => false, 'reason' => 'html_page'];
    } else {
        $result = ['ok' => true];
    }
} else {
    $result = ['ok' => true];
}

file_put_contents($cacheFile, json_encode($result));
echo json_encode($result);