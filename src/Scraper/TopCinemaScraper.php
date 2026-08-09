<?php
class TopCinemaScraper extends BaseScraper {
    public int $priority = 3;

    public function detectFromUrl(string $url): bool {
        return (bool) preg_match('/topcinema\.(fan|com|tv)/i', $url);
    }

    public function search(string $query): array {
        $html = $this->fetch($this->baseUrl . '/search/' . urlencode($query));
        return $html ? $this->parseSearchResults($html) : [];
    }

    public function parseSearchResults(string $html): array {
        $results = [];
        preg_match_all('/<a[^>]+href="([^"]+)"[^>]*class="[^"]*poster[^"]*"[^>]*>.*?<img[^>]+alt="([^"]*)"[^>]*src="([^"]*)"[^>]*>.*?<\/a>/si', $html, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $title = $this->cleanText($matches[2][$i]);
            $type = (strpos($matches[1][$i], '/series/') !== false || strpos($matches[1][$i], '/tv/') !== false) ? 'series' : 'movie';
            if ($title) {
                $results[] = [
                    'title'  => $title,
                    'url'    => $this->toAbsolute($matches[1][$i]),
                    'poster' => $this->toAbsolute($matches[3][$i]),
                    'type'   => $type,
                    'source' => 'topcinema',
                ];
            }
        }
        return $results;
    }

    public function getDetails(string $url): ?array {
        $cached = Cache::getMetadata($url, 'topcinema');
        if ($cached) return $cached;

        $html = $this->fetch($url);
        if (!$html) return null;

        $title = '';
        if (preg_match('/<title>(.*?)<\/title>/si', $html, $m)) {
            $title = $this->cleanText(str_replace(['| توب سينما', 'TopCinema'], '', $m[1]));
        }
        $poster = '';
        if (preg_match('/property="og:image"[^>]+content="([^"]+)"/i', $html, $m)) $poster = $m[1];
        $description = '';
        if (preg_match('/property="og:description"[^>]+content="([^"]+)"/i', $html, $m)) $description = $this->cleanText($m[1]);
        $year = '';
        if (preg_match('/\b(19|20)\d{2}\b/', $html, $m)) $year = $m[0];

        $type = 'movie';
        if (preg_match('/\/(series|tv)\//i', $url)) $type = 'series';

        $data = [
            'title'       => $title,
            'url'         => $url,
            'poster'      => $poster,
            'type'        => $type,
            'year'        => $year,
            'description' => $description,
            'source'      => 'topcinema',
        ];

        Cache::setMetadata($url, 'topcinema', $data);
        return $data;
    }

    public function getStreams(string $url): array {
        $cached = Cache::getStreams($url, 'topcinema');
        if (!empty($cached)) return $cached;

        $html = $this->fetch($url);
        if (!$html) return [];

        preg_match_all('/data-server-id="(\d+)"[^>]*data-link="([^"]+)"/i', $html, $serverMatches);
        $streams = [];

        for ($i = 0; $i < count($serverMatches[1]); $i++) {
            $serverId = $serverMatches[1][$i];
            $serverUrl = $this->toAbsolute($serverMatches[2][$i]);

            $iframeHtml = $this->fetch($serverUrl, [], $url);
            if (!$iframeHtml) continue;

            preg_match('/<iframe[^>]+src="([^"]+)"/i', $iframeHtml, $iframeMatch);
            if (empty($iframeMatch[1])) continue;

            $embedUrl = $this->toAbsolute($iframeMatch[1]);
            $resolved = $this->resolveEmbed($embedUrl, $url);
            if ($resolved) {
                $resolved['server_name'] = "Server " . ($i + 1);
                $streams[] = $resolved;
            }
        }

        if (!empty($streams)) {
            Cache::setStreams($url, 'topcinema', $streams);
        }
        return $streams;
    }

    private function resolveEmbed(string $embedUrl, string $referer): ?array {
        $html = $this->fetch($embedUrl, [], $referer);
        if (!$html) return null;

        $texts = [$html];
        foreach ($this->extractPackerBlocks($html) as $block) {
            $decoded = $this->decodePacker($block);
            if ($decoded) $texts[] = $decoded;
        }

        foreach ($texts as $text) {
            preg_match_all('/(https?:\/\/[^\s"\'<>]+\.(?:m3u8|mp4)[^\s"\'<>]*)/i', $text, $m);
            if (!empty($m[1])) {
                $streamUrl = str_replace('\\/', '/', $m[1][0]);
                $type = str_contains($streamUrl, '.m3u8') ? 'hls' : 'mp4';
                $quality = null;
                if (preg_match('/(\d{3,4})p/', $streamUrl, $qm)) $quality = $qm[1];
                return [
                    'stream_url'    => $streamUrl,
                    'stream_type'   => $type,
                    'quality'       => $quality,
                    'quality_label' => $quality ? "{$quality}p" : null,
                    'referer'       => $embedUrl,
                ];
            }
        }
        return null;
    }
}
