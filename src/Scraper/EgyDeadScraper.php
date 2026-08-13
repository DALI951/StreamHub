<?php
class EgyDeadScraper extends BaseScraper {
    public int $priority = 1;

    public function detectFromUrl(string $url): bool {
        return (bool) preg_match('/egydead\.(today|live|ca|fyi)/i', $url);
    }

    public function search(string $query): array {
        $html = $this->fetch($this->baseUrl . '/?s=' . urlencode($query));
        return $html ? $this->parseSearchResults($html) : [];
    }

    public function parseSearchResults(string $html): array {
        $results = [];
        preg_match_all('/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si', $html, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $href  = $matches[1][$i];
            $block = $matches[2][$i];
            $title = '';
            $poster = '';
            if (preg_match('/<h1\s+class="BottomTitle">(.*?)<\/h1>/si', $block, $m)) {
                $title = $this->cleanText($m[1]);
            }
            if (preg_match('/<img[^>]+src="([^"]+)"/i', $block, $m)) {
                $poster = $this->toAbsolute($m[1]);
            }
            $type = 'movie';
            if (preg_match('#/serie/#i', $href))       $type = 'series';
            elseif (preg_match('#/season/#i', $href))  $type = 'season';
            elseif (preg_match('#/episode/#i', $href)) $type = 'episode';
            if ($title) {
                $results[] = [
                    'title'  => $title,
                    'url'    => $this->toAbsolute($href),
                    'poster' => $poster,
                    'type'   => $type,
                    'source' => 'egydead',
                ];
            }
        }
        return $results;
    }

    public function getDetails(string $url): ?array {
        $cached = Cache::getMetadata($url, 'egydead');
        if ($cached) return $cached;

        $html = $this->fetch($url);
        if (!$html) return null;

        $title = '';
        if (preg_match('/property="og:title"[^>]+content="([^"]+)"/i', $html, $m)) {
            $title = $this->cleanText($m[1]);
        } elseif (preg_match('/<title>(.*?)<\/title>/si', $html, $m)) {
            $title = $this->cleanText(str_replace('| ايجي ديد', '', $m[1]));
        }
        $poster = '';
        if (preg_match('/property="og:image"[^>]+content="([^"]+)"/i', $html, $m)) {
            $poster = $m[1];
        }
        $description = '';
        if (preg_match('/property="og:description"[^>]+content="([^"]+)"/i', $html, $m)) {
            $description = $this->cleanText($m[1]);
        }
        $year = '';
        if (preg_match('/\b(19|20)\d{2}\b/', $title, $m)) $year = $m[0];

        $type = 'movie';
        if (preg_match('#/serie/#i', $url))       $type = 'series';
        elseif (preg_match('#/season/#i', $url))  $type = 'season';
        elseif (preg_match('#/episode/#i', $url)) $type = 'episode';

        $isHomepage = false;
        if (preg_match('/og:image["\s]+content="[^"]*EgyDead-Logo/i', $html)) $isHomepage = true;
        if (preg_match('/<title>\s*ايجي\s+ديد\s*$/im', $html)) $isHomepage = true;

        $seasons = [];
        if ($type === 'series') {
            preg_match_all('/<a[^>]+href="([^"]*\/season\/[^"]*)"[^>]*>.*?<\/a>/si', $html, $sMatches);
            $seen = [];
            $host = parse_url($this->baseUrl, PHP_URL_HOST);
            for ($i = 0; $i < count($sMatches[1]); $i++) {
                $sUrl = $this->toAbsolute($sMatches[1][$i]);
                $sHost = parse_url($sUrl, PHP_URL_HOST);
                $path = parse_url($sUrl, PHP_URL_PATH);
                $slug = basename(rtrim($path, '/'));
                if ($sHost === $host && strlen($slug) > 2 && !in_array($sUrl, $seen)) {
                    $seen[] = $sUrl;
                    $seasons[] = ['url' => $sUrl];
                }
            }
        }

        $episodes = [];
        if ($type === 'season' && !$isHomepage) {
            preg_match_all('/<a\s+href="([^"]*\/episode\/[^"]*)"[^>]*>\s*(.*?حلقه.*?)\s*<\/a>/ui', $html, $epMatches);
            if (empty($epMatches[1])) {
                preg_match_all('/<a\s+href="([^"]*\/episode\/[^"]*)"[^>]*>(.*?)<\/a>/si', $html, $epMatches);
            }
            $seenEps = [];
            for ($i = 0; $i < count($epMatches[1]); $i++) {
                $epUrl = $this->toAbsolute(htmlspecialchars_decode($epMatches[1][$i]));
                $epHost = parse_url($epUrl, PHP_URL_HOST);
                if ($epHost !== parse_url($this->baseUrl, PHP_URL_HOST)) continue;
                $text = $this->cleanText(strip_tags($epMatches[2][$i]));
                $epNum = $i + 1;
                if (preg_match('/(\d+)/', $text, $nm)) $epNum = (int) $nm[1];
                if (in_array($epUrl, $seenEps)) continue;
                $seenEps[] = $epUrl;
                $episodes[] = [
                    'number' => $epNum,
                    'url'    => $epUrl,
                    'title'  => $text,
                ];
            }
        }

        if ($type === 'season' && $isHomepage) {
            $episodes = $this->findEpisodesForSeason($url);
            if (!empty($episodes)) {
                $seasonSlug = parse_url($url, PHP_URL_PATH);
                $seasonSlug = basename(rtrim($seasonSlug, '/'));
                $title = $this->cleanText(str_replace(['مسلسل-', 'اموسم-', 'الموسم-', 'مترجم', 'كامل', '-'], ' ', urldecode($seasonSlug)));
            }
        }

        $data = [
            'title'       => $title,
            'url'         => $url,
            'poster'      => $poster,
            'type'        => $type,
            'year'        => $year,
            'description' => $description,
            'source'      => 'egydead',
            'seasons'     => $seasons,
            'episodes'    => $episodes,
        ];

        Cache::setMetadata($url, 'egydead', $data);
        return $data;
    }

    private function findEpisodesForSeason(string $seasonUrl): array {
        $path = parse_url($seasonUrl, PHP_URL_PATH);
        $slug = urldecode(basename(rtrim($path, '/')));
        $host = parse_url($this->baseUrl, PHP_URL_HOST);

        $englishName = '';
        if (preg_match('/^مسلسل-([a-zA-Z0-9-]+)/', $slug, $nm)) {
            $englishName = strtolower($nm[1]);
        }
        if (!$englishName) return [];

        $normalizedSlug = str_replace(['اموسم', 'اموسم-'], ['الموسم', 'الموسم-'], $slug);
        $seasonPart = '';
        if (preg_match('/(موسم[^-]*(?:-(?!مترجم|مدبلج)[^-]+)*)/', $normalizedSlug, $sm)) {
            $seasonPart = $sm[1];
        }
        $cleanSlug = preg_replace('/-(مترجم|مدبلج)-.*$/', '', $normalizedSlug);
        $searchQuery = str_replace('-', ' ', $cleanSlug);
        $searchQuery = preg_replace('/\s+/', ' ', trim($searchQuery));
        $searchUrl = $this->baseUrl . '/?s=' . urlencode($searchQuery);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $searchUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
            ],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        ]);
        $searchHtml = curl_exec($ch);
        curl_close($ch);
        if (!$searchHtml) return [];

        $episodes = [];
        $seen = [];
        preg_match_all('/<li\s+class="movieItem">\s*<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>\s*<\/li>/si', $searchHtml, $matches);
        for ($i = 0; $i < count($matches[1]); $i++) {
            $epUrl = $this->toAbsolute(htmlspecialchars_decode($matches[1][$i]));
            $epHost = parse_url($epUrl, PHP_URL_HOST);
            if ($epHost !== $host) continue;
            $epPath = parse_url($epUrl, PHP_URL_PATH);
            $epSlug = urldecode(basename(rtrim($epPath, '/')));
            if (!str_contains($epSlug, 'حلقه') && !str_contains($epSlug, 'الحلقة') && !preg_match('/s\d+e\d+/i', $epSlug)) continue;
            if (!str_contains(strtolower($epSlug), $englishName)) continue;
            if ($seasonPart && !str_contains($epSlug, $seasonPart)) continue;
            if (in_array($epUrl, $seen)) continue;
            $seen[] = $epUrl;
            $text = '';
            if (preg_match('/<h1\s+class="BottomTitle">(.*?)<\/h1>/si', $matches[2][$i], $tm)) {
                $text = $this->cleanText($tm[1]);
            }
            $epNum = 0;
            if (preg_match('/(\d+)/', $epSlug, $nm)) $epNum = (int) $nm[1];
            if ($epNum === 0 && preg_match('/(\d+)/', $text, $nm)) $epNum = (int) $nm[1];
            $episodes[] = [
                'number' => $epNum,
                'url'    => $epUrl,
                'title'  => $text ?: "الحلقة {$epNum}",
            ];
        }
        usort($episodes, fn($a, $b) => $a['number'] <=> $b['number']);
        return $episodes;
    }

    public function getStreams(string $url): array {
        $cached = Cache::getStreams($url, 'egydead');
        if (!empty($cached)) return $cached;

        $html = $this->fetchPost($url, ['View' => '1']);
        if (!$html) $html = $this->fetch($url);
        if (!$html) return [];

        $streams = [];

        preg_match_all('/<li[^>]+data-link="([^"]+)"[^>]*>(.*?)<\/li>/si', $html, $serverMatches);
        if (!empty($serverMatches[1])) {
            for ($i = 0; $i < count($serverMatches[1]); $i++) {
                $iframeUrl = $this->toAbsolute(htmlspecialchars_decode($serverMatches[1][$i]));
                $serverName = $this->cleanText($serverMatches[2][$i]);
                $resolved = $this->resolveIframe($iframeUrl, $serverName, $url);
                if ($resolved) $streams[] = $resolved;
            }
        }

        if (empty($streams)) {
            $iframes = $this->extractIframes($html);
            foreach ($iframes as $iframeUrl) {
                $iframeUrl = $this->toAbsolute($iframeUrl);
                $resolved = $this->resolveIframe($iframeUrl, '', $url);
                if ($resolved) $streams[] = $resolved;
            }
        }

        if (!empty($streams)) {
            Cache::setStreams($url, 'egydead', $streams);
        }
        return $streams;
    }

    private function fetchPost(string $url, array $data): ?string {
        $config = require __DIR__ . '/../../config.php';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => $config['scraping']['max_redirects'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
            CURLOPT_REFERER        => $url,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false || $httpCode >= 400) return null;
        return $response;
    }

    private function resolveIframe(string $url, string $serverName = '', ?string $referer = null): ?array {
        $html = $this->fetch($url, [], $referer ?: $url);
        if (!$html) return null;

        $packerBlocks = $this->extractPackerBlocks($html);
        $texts = [$html];
        foreach ($packerBlocks as $block) {
            $decoded = $this->decodePacker($block);
            if ($decoded) $texts[] = $decoded;
        }

        $found = [];
        $urlChar = '[^\s"\'<>\[\]]';
        foreach ($texts as $text) {
            // Match standard http/https URLs with video extensions
            preg_match_all("#(https?://{$urlChar}+\.(?:m3u8|mp4){$urlChar}*)#i", $text, $m);
            foreach ($m[1] as $streamUrl) {
                $streamUrl = str_replace('\\/', '/', $streamUrl);
                if (!in_array($streamUrl, $found)) $found[] = $streamUrl;
            }
            // Match protocol-relative URLs (//domain.com/...)
            preg_match_all("#(//{$urlChar}+\.(?:m3u8|mp4){$urlChar}*)#i", $text, $m);
            foreach ($m[1] as $streamUrl) {
                $streamUrl = 'https:' . str_replace('\\/', '/', $streamUrl);
                if (!in_array($streamUrl, $found)) $found[] = $streamUrl;
            }
            // Match wurl patterns: wurl="//domain/path" or wurl="http://domain/path"
            if (preg_match("#wurl\s*[=:]\s*[\"']?(//{$urlChar}+)[\"']?#i", $text, $m)) {
                $streamUrl = 'https:' . str_replace('\\/', '/', $m[1]);
                if (!in_array($streamUrl, $found)) $found[] = $streamUrl;
            }
            // Match MDCore.wurl pattern
            if (preg_match('/MDCore\.wurl\s*=\s*["\']([^"\']+)["\']/i', $text, $m)) {
                $streamUrl = str_replace('\\/', '/', $m[1]);
                if (!str_starts_with($streamUrl, 'http')) $streamUrl = 'https:' . $streamUrl;
                if (!in_array($streamUrl, $found)) $found[] = $streamUrl;
            }
            // Match file:"url" or file:'url' patterns
            if (preg_match('/\bfile\s*[=:]\s*["\']([^"\']+\.(?:m3u8|mp4))["\']/i', $text, $m)) {
                $streamUrl = str_replace('\\/', '/', $m[1]);
                if (!str_starts_with($streamUrl, 'http')) $streamUrl = 'https:' . $streamUrl;
                if (!in_array($streamUrl, $found)) $found[] = $streamUrl;
            }
            // Match src:"url" patterns for video elements
            if (preg_match('/\bsrc\s*[=:]\s*["\']([^"\']+\.(?:m3u8|mp4))["\']/i', $text, $m)) {
                $streamUrl = str_replace('\\/', '/', $m[1]);
                if (!str_starts_with($streamUrl, 'http')) $streamUrl = 'https:' . $streamUrl;
                if (!in_array($streamUrl, $found)) $found[] = $streamUrl;
            }
            // Match video.nurl or similar patterns
            if (preg_match('/nurl\s*[=:]\s*["\']([^"\']+\.(?:m3u8|mp4))["\']/i', $text, $m)) {
                $streamUrl = str_replace('\\/', '/', $m[1]);
                if (!str_starts_with($streamUrl, 'http')) $streamUrl = 'https:' . $streamUrl;
                if (!in_array($streamUrl, $found)) $found[] = $streamUrl;
            }
        }

        if (!empty($found)) {
            $streamUrl = $found[0];
            $type = str_contains($streamUrl, '.m3u8') ? 'hls' : 'mp4';
            $quality = null;
            if (preg_match('/(\d{3,4})p/', $streamUrl, $m)) $quality = $m[1];

            if ($type === 'hls' && $quality === null) {
                $master = $this->fetch($streamUrl, [], $referer ?: $url);
                if ($master && preg_match_all('/RESOLUTION=(\d+)x(\d+)/', $master, $rm)) {
                    $maxW = 0;
                    foreach ($rm[1] as $i => $w) {
                        if ((int)$w > $maxW) $maxW = (int)$w;
                    }
                    if ($maxW >= 1920) $quality = 1080;
                    elseif ($maxW >= 1280) $quality = 720;
                    elseif ($maxW >= 854) $quality = 480;
                    elseif ($maxW >= 640) $quality = 360;
                }
            }

            return [
                'stream_url'    => $streamUrl,
                'stream_type'   => $type,
                'quality'       => $quality,
                'quality_label' => $quality ? "{$quality}p" : null,
                'referer'       => $referer ?: $url,
                'server_name'   => $serverName ?: 'EgyDead',
            ];
        }

        return [
            'stream_url'    => $url,
            'stream_type'   => 'iframe',
            'quality'       => null,
            'quality_label' => $serverName ?: 'Embed',
            'referer'       => null,
            'server_name'   => $serverName ?: 'EgyDead',
        ];
    }
}
