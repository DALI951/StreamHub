<?php
class FaselHDScraper extends BaseScraper {
    public int $priority = 4;

    public function detectFromUrl(string $url): bool {
        return (bool) preg_match('/faselhd\.(com|pro|icu)/i', $url);
    }

    public function search(string $query): array {
        $html = $this->fetch($this->baseUrl . '/search/' . urlencode($query));
        return $html ? $this->parseSearchResults($html) : [];
    }

    public function parseSearchResults(string $html): array {
        $results = [];
        preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>.*?<div[^>]*class="[^"]*title[^"]*"[^>]*>(.*?)<\/div>.*?<\/a>/si', $html, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $title = $this->cleanText($matches[2][$i]);
            $type = (strpos($matches[1][$i], '/series/') !== false) ? 'series' : 'movie';
            $poster = '';
            if (preg_match('/<img[^>]+src="([^"]+)"/i', $matches[0][$i], $m)) $poster = $this->toAbsolute($m[1]);
            if ($title) {
                $results[] = [
                    'title'  => $title,
                    'url'    => $this->toAbsolute($matches[1][$i]),
                    'poster' => $poster,
                    'type'   => $type,
                    'source' => 'faselhd',
                ];
            }
        }
        return $results;
    }

    public function getDetails(string $url): ?array {
        $cached = Cache::getMetadata($url, 'faselhd');
        if ($cached) return $cached;

        $html = $this->fetch($url);
        if (!$html) return null;

        $title = '';
        if (preg_match('/<title>(.*?)<\/title>/si', $html, $m)) {
            $title = $this->cleanText(str_replace('| فaselhd', '', $m[1]));
        }
        $poster = '';
        if (preg_match('/property="og:image"[^>]+content="([^"]+)"/i', $html, $m)) $poster = $m[1];
        $description = '';
        if (preg_match('/property="og:description"[^>]+content="([^"]+)"/i', $html, $m)) $description = $this->cleanText($m[1]);
        $year = '';
        if (preg_match('/\b(19|20)\d{2}\b/', $html, $m)) $year = $m[0];
        $type = (strpos($url, '/series/') !== false) ? 'series' : 'movie';

        $data = [
            'title'       => $title, 'url' => $url, 'poster' => $poster,
            'type' => $type, 'year' => $year, 'description' => $description, 'source' => 'faselhd',
        ];
        Cache::setMetadata($url, 'faselhd', $data);
        return $data;
    }

    public function getStreams(string $url): array {
        $cached = Cache::getStreams($url, 'faselhd');
        if (!empty($cached)) return $cached;

        $html = $this->fetch($url);
        if (!$html) return [];

        $iframes = $this->extractIframes($html);
        $streams = [];
        foreach ($iframes as $iframeUrl) {
            $iframeUrl = $this->toAbsolute($iframeUrl);
            $iframeHtml = $this->fetch($iframeUrl, [], $url);
            if (!$iframeHtml) continue;

            $texts = [$iframeHtml];
            foreach ($this->extractPackerBlocks($iframeHtml) as $block) {
                $decoded = $this->decodePacker($block);
                if ($decoded) $texts[] = $decoded;
            }
            foreach ($texts as $text) {
                preg_match_all('/(https?:\/\/[^\s"\'<>]+\.(?:m3u8|mp4)[^\s"\'<>]*)/i', $text, $m);
                foreach ($m[1] as $streamUrl) {
                    $streamUrl = str_replace('\\/', '/', $streamUrl);
                    $type = str_contains($streamUrl, '.m3u8') ? 'hls' : 'mp4';
                    $quality = null;
                    if (preg_match('/(\d{3,4})p/', $streamUrl, $qm)) $quality = $qm[1];
                    $streams[] = [
                        'stream_url' => $streamUrl, 'stream_type' => $type,
                        'quality' => $quality, 'quality_label' => $quality ? "{$quality}p" : null,
                        'referer' => $iframeUrl, 'server_name' => 'FaselHD',
                    ];
                }
            }
        }
        if (!empty($streams)) Cache::setStreams($url, 'faselhd', $streams);
        return $streams;
    }
}
