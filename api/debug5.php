<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$seasonUrl = "https://tv10.egydead.live/season/%d9%85%d8%b3%d9%84%d8%b3%d9%84-the-mentalist-%d8%a7%d9%85%d9%88%d8%b3%d9%85-%d8%a7%d9%84%d8%ab%d8%a7%d9%86%d9%8a-%d9%85%d8%aa%d8%b1%d8%ac%d9%85-%d9%83%d8%a7%d9%85%d9%84/";
$path = parse_url($seasonUrl, PHP_URL_PATH);
echo "Path: $path\n\n";
$basename = basename(rtrim($path, '/'));
echo "Basename: $basename\n\n";
$slug = urldecode($basename);
echo "Slug: $slug\n\n";
echo "Slug hex: " . bin2hex($slug) . "\n\n";

// Test each part
echo "Contains مسلسل: " . (strpos($slug, 'مسلسل') !== false ? 'YES' : 'NO') . "\n";
echo "Contains الموسم: " . (strpos($slug, 'الموسم') !== false ? 'YES' : 'NO') . "\n";
echo "Contains مترجم: " . (strpos($slug, 'مترجم') !== false ? 'YES' : 'NO') . "\n";
echo "Contains كامل: " . (strpos($slug, 'كامل') !== false ? 'YES' : 'NO') . "\n\n";

// Test regex
$pattern = '/^(مسلسل-.+?)-(الموسم-.+?)-(مترجم|مدبلج)-(كامل|.+)$/';
echo "Pattern: $pattern\n";
if (preg_match($pattern, $slug, $m)) {
    echo "MATCHED!\n";
    echo "Series: {$m[1]}\n";
    echo "Season: {$m[2]}\n";
} else {
    echo "NO MATCH\n";
    // Try simpler pattern
    $p2 = '/^(مسلسل.+)-(الموسم.+)-(.+)$/';
    echo "\nSimpler pattern: $p2\n";
    if (preg_match($p2, $slug, $m2)) {
        echo "MATCHED!\n";
        echo "Series: {$m2[1]}\n";
        echo "Season: {$m2[2]}\n";
        echo "Rest: {$m2[3]}\n";
    } else {
        echo "Still no match\n";
    }
}
