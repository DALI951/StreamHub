<?php
header('Content-Type: text/plain; charset=utf-8');

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

$tests = [
    'tv10.egydead.live',
    'www.faselhd.com',
    'mycima.win',
    'arabseed.show',
    'akwam.ws',
    'www.google.com',
];

foreach ($tests as $host) {
    echo "{$host}: ";
    // Try DNS resolution first
    $ip = gethostbyname($host);
    if ($ip === $host) {
        echo "DNS FAILED\n";
        continue;
    }
    echo "DNS={$ip} ";
    
    $ch = curl_init("https://{$host}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    
    if ($errno) echo "ERR({$errno}): {$err}\n";
    else echo "HTTP {$code}\n";
}
