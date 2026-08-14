<?php
header('Content-Type: text/plain; charset=utf-8');

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

$tests = [
    'https://www.egydead.today',
    'https://www.cima4ua.top',
    'https://topcinema.fan',
    'https://www.faselhd.com',
    'https://mycima.win',
    'https://arabseed.show',
    'https://akwam.ws',
    'https://httpbin.org/ip',
    'https://www.google.com',
];

foreach ($tests as $url) {
    echo "{$url} -> ";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_NOBODY => true,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno) {
        echo "ERR({$errno}): {$err}\n";
    } else {
        echo "HTTP {$code}\n";
    }
}
