<?php
/**
 * subs.php - Arabic subtitles for any episode/movie via SubDL (subdl.com).
 * SubDL is the Subscene successor: 111 languages, Arabic is #2, server-rendered,
 * no API key needed for browsing/downloading. We scrape, pick the Arabic .srt,
 * convert SRT -> VTT (handles Windows-1256 Arabic) and cache the result in MySQL.
 *
 * ?q=<title>&type=tv|movie[&season=N&episode=N]  -> JSON {ok, url, lang}
 * ?id=<md5>&lang=ar                              -> serves the cached VTT directly
 */

error_reporting(E_ERROR | E_PARSE);
header('Access-Control-Allow-Origin: *');

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
$cacheTtl = 2592000; // 30 days

function sFetch($url, $binary = false) {
    global $ua;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => $ua,
        CURLOPT_REFERER => 'https://subdl.com/',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*'],
    ]);
    $b = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 400 || $b === false) return null;
    return $binary ? $b : (string)$b;
}

function dbInit() {
    require_once __DIR__ . '/../src/Database.php';
    Database::query(
        "CREATE TABLE IF NOT EXISTS subs_cache (
            id CHAR(32) PRIMARY KEY,
            lang VARCHAR(16) NOT NULL DEFAULT 'ar',
            vtt MEDIUMTEXT NOT NULL,
            created_at INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function vttFromCache($id) {
    try {
        dbInit();
        $row = Database::fetchOne("SELECT vtt, created_at FROM subs_cache WHERE id = ?", [$id]);
        if ($row && (time() - (int)$row['created_at']) < 2592000) return $row['vtt'];
    } catch (Exception $e) { /* db down -> re-scrape */ }
    return null;
}

function storeVtt($id, $vtt) {
    try {
        dbInit();
        Database::insert('subs_cache', ['id' => $id, 'lang' => 'ar', 'vtt' => $vtt, 'created_at' => time()]);
    } catch (Exception $e) { /* non-fatal */ }
}

function slugify($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

function seasonWord($n) {
    $words = [1 => 'first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth',
        'eleventh', 'twelfth', 'thirteenth', 'fourteenth', 'fifteenth', 'sixteenth', 'seventeenth', 'eighteenth', 'nineteenth', 'twentieth',
        'twenty-first', 'twenty-second', 'twenty-third', 'twenty-fourth', 'twenty-fifth', 'twenty-sixth', 'twenty-seventh', 'twenty-eighth', 'twenty-ninth', 'thirtieth'];
    return $words[$n] ?? null;
}

function findTitle($q) {
    $page = sFetch('https://subdl.com/search/' . rawurlencode($q));
    if ($page === null) return null;
    if (preg_match_all('#/subtitle/sd(\d+)/([a-z0-9-]+)#i', $page, $m, PREG_SET_ORDER)) {
        $want = slugify($q);
        foreach ($m as $hit) {
            if ($hit[2] === $want) return ['id' => $hit[1], 'slug' => $hit[2]];
        }
        return ['id' => $m[0][1], 'slug' => $m[0][2]];
    }
    return null;
}

// score a release name: prefer non-HI, WEB/720p+ over XviD
function releaseScore($name) {
    $s = 0;
    if (preg_match('/\.(?:WEB|WEB-DL|BluRay|BRRip|AMZN)/i', $name)) $s += 3;
    if (preg_match('/720p|1080p/i', $name)) $s += 2;
    if (preg_match('/HDTV/i', $name)) $s += 1;
    if (preg_match('/(?:\.|\s)HI(?:\.|\s|$)/i', $name)) $s -= 3;
    return $s;
}

function collectZips($html) {
    // entry blocks: release name (with SxxEyy) ... dl.subdl.com zip link
    $out = [];
    $parts = preg_split('#https://dl\.subdl\.com/subtitle/#', $html);
    array_shift($parts);
    foreach ($parts as $i => $seg) {
        if (!preg_match('#^(\d+)-(\d+)\.zip#', $seg, $zm)) continue;
        $zip = 'https://dl.subdl.com/subtitle/' . $zm[1] . '-' . $zm[2] . '.zip';
        $before = $i === 0 ? $seg : $parts[$i - 1];
        $ctx = substr($seg, 0, 2500);
        $name = '';
        if (preg_match('#<h4[^>]*>([^<]+)</h4>#i', $ctx, $hm)) $name = trim($hm[1]);
        if (preg_match('/(S\d{1,2}E\d{1,2}|s\d{1,2}e\d{1,2})/', $name, $em)) {
            $out[] = ['zip' => $zip, 'name' => $name, 'ep' => strtoupper($em[1]), 'score' => releaseScore($name)];
        } else {
            $out[] = ['zip' => $zip, 'name' => $name, 'ep' => '', 'score' => releaseScore($name)];
        }
    }
    return $out;
}

function pickZip($zips, $season, $episode, $isTv) {
    $best = null;
    foreach ($zips as $z) {
        if ($isTv) {
            if (!preg_match('/^S(\d{1,2})E(\d{1,2})$/', $z['ep'], $em)) continue;
            if ((int)$em[1] !== $season || (int)$em[2] !== $episode) continue;
        }
        if ($best === null || $z['score'] > $best['score']) $best = $z;
    }
    return $best;
}

function srtFromZip($zipUrl) {
    $bin = sFetch($zipUrl, true);
    if ($bin === null || strlen($bin) < 60 || substr($bin, 0, 4) !== "PK\x03\x04") return null;
    $tmp = tempnam(sys_get_temp_dir(), 'sdz');
    file_put_contents($tmp, $bin);
    $za = new ZipArchive();
    if ($za->open($tmp) !== true) { @unlink($tmp); return null; }
    $srt = null;
    for ($i = 0; $i < $za->numFiles; $i++) {
        $n = $za->getNameIndex($i);
        if (preg_match('/\.srt$/i', $n)) { $srt = $za->getFromIndex($i); break; }
    }
    $za->close();
    @unlink($tmp);
    if ($srt === null || trim($srt) === '') return null;
    return $srt;
}

function toUtf8($s) {
    if (mb_check_encoding($s, 'UTF-8')) return $s;
    foreach (['CP1256', 'CP1252', 'ISO-8859-1'] as $enc) {
        $c = @iconv($enc, 'UTF-8//TRANSLIT', $s);
        if ($c !== false && mb_check_encoding($c, 'UTF-8')) return $c;
    }
    return $s;
}

function srtToVtt($srt) {
    $srt = toUtf8($srt);
    $srt = preg_replace('/\{[^}]*\}/', '', $srt);
    $srt = preg_replace('/<[^>]+>/', '', $srt);
    $srt = str_replace("\r\n", "\n", $srt);
    $blocks = preg_split('/\n{2,}/', trim($srt));
    $out = ["WEBVTT", ""];
    foreach ($blocks as $b) {
        $lines = explode("\n", $b);
        $ti = -1;
        foreach ($lines as $li => $l) {
            if (strpos($l, '-->') !== false) { $ti = $li; break; }
        }
        if ($ti < 0) continue;
        $time = str_replace(',', '.', $lines[$ti]);
        $cue = trim(implode("\n", array_slice($lines, $ti + 1)));
        if ($cue === '') continue;
        $out[] = $time;
        $out[] = $cue;
        $out[] = '';
    }
    return implode("\n", $out);
}

// ---------- serve cached vtt ----------
if (isset($_GET['id'])) {
    $id = preg_replace('/[^a-f0-9]/', '', $_GET['id']);
    if (strlen($id) !== 32) { http_response_code(400); exit; }
    $vtt = vttFromCache($id);
    if ($vtt === null) { http_response_code(404); exit; }
    header('Content-Type: text/vtt; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    header('Access-Control-Allow-Origin: *');
    echo $vtt;
    exit;
}

// ---------- main: find arabic subs ----------
$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode(['ok' => false, 'error' => 'no query']); exit; }
$isTv = ($_GET['type'] ?? '') === 'tv';
$season = (int)($_GET['season'] ?? 0);
$episode = (int)($_GET['episode'] ?? 0);

$id = md5(strtolower(trim($q)) . '|' . ($isTv ? "tv:$season:$episode" : 'movie'));
$vtt = vttFromCache($id);
if ($vtt !== null) {
    echo json_encode(['ok' => true, 'lang' => 'ar', 'url' => 'api/subs.php?id=' . $id]);
    exit;
}

$title = findTitle($q);
if (!$title) { echo json_encode(['ok' => false, 'error' => 'not found']); exit; }

$html = null;
if ($isTv && $season > 0) {
    $w = seasonWord($season);
    if ($w) $html = sFetch("https://subdl.com/subtitle/sd{$title['id']}/{$title['slug']}/$w-season/arabic");
    if ($html === null) $html = sFetch("https://subdl.com/subtitle/sd{$title['id']}/{$title['slug']}/arabic");
} else {
    $html = sFetch("https://subdl.com/subtitle/sd{$title['id']}/{$title['slug']}/arabic");
}
if ($html === null) { echo json_encode(['ok' => false, 'error' => 'subdl unreachable']); exit; }

$zips = collectZips($html);
$pick = pickZip($zips, $season, $episode, $isTv);
if (!$pick) { echo json_encode(['ok' => false, 'error' => 'no arabic subs']); exit; }

$srt = srtFromZip($pick['zip']);
if ($srt === null) { echo json_encode(['ok' => false, 'error' => 'download failed']); exit; }

$vtt = srtToVtt($srt);
if (trim($vtt) === 'WEBVTT') { echo json_encode(['ok' => false, 'error' => 'empty subtitles']); exit; }

storeVtt($id, $vtt);
echo json_encode(['ok' => true, 'lang' => 'ar', 'url' => 'api/subs.php?id=' . $id]);