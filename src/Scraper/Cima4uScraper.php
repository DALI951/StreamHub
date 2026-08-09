<?php
class Cima4uScraper extends BaseScraper {
    public int $priority = 2;

    public function detectFromUrl(string $url): bool {
        return (bool) preg_match('/cima4u\.(top|com|site)/i', $url);
    }

    public function search(string $query): array {
        $html = $this->fetch($this->baseUrl . '/search/' . urlencode($query));
        return $html ? $this->parseSearchResults($html) : [];
    }

    public function parseSearchResults(string $html): array {
        $results = [];
        preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>.*?<img[^>]+alt="([^"]*)"[^>]*>.*?<\/a>/si', $html, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $href  = $matches[1][$i];
            $title = $this->cleanText($matches[2][$i]);
            if (!$title || !preg_match('/\/(movie|series|episode)\//i', $href)) continue;

            $type = 'movie';
            if (preg_match('/\/series\//i', $href)) $type = 'series';
            if (preg_match('/\/episode\//i', $href)) $type = 'series';

            $poster = '';
            if (preg_match('/<img[^>]+src="([^"]+)"/i', $matches[0][$i], $m)) {
                $poster = $this->toAbsolute($m[1]);
            }

            $results[] = [
                'title'  => $title,
                'url'    => $this->toAbsolute($href),
                'poster' => $poster,
                'type'   => $type,
                'source' => 'cima4u',
            ];
        }
        return $results;
    }

    public function getDetails(string $url): ?array {
        $cached = Cache::getMetadata($url, 'cima4u');
        if ($cached) return $cached;

        $html = $this->fetch($url);
        if (!$html) return null;

        $title = '';
        if (preg_match('/<title>(.*?)<\/title>/si', $html, $m)) {
            $title = $this->cleanText(str_replace(['| سيما فور يو', 'Cima4u'], '', $m[1]));
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
            'source'      => 'cima4u',
        ];

        Cache::setMetadata($url, 'cima4u', $data);
        return $data;
    }

    public function getStreams(string $url): array {
        $cached = Cache::getStreams($url, 'cima4u');
        if (!empty($cached)) return $cached;

        $html = $this->fetch($url);
        if (!$html) return [];

        $iframes = $this->extractIframes($html);
        $streams = [];

        foreach ($iframes as $iframeUrl) {
            $iframeUrl = $this->toAbsolute($iframeUrl);
            $iframeHtml = $this->fetch($iframeUrl, [], $url);
            if (!$iframeHtml) continue;

            $packerBlocks = $this->extractPackerBlocks($iframeHtml);
            $texts = [$iframeHtml];
            foreach ($packerBlocks as $block) {
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
                        'stream_url'    => $streamUrl,
                        'stream_type'   => $type,
                        'quality'       => $quality,
                        'quality_label' => $quality ? "{$quality}p" : null,
                        'referer'       => $iframeUrl,
                        'server_name'   => 'Server',
                    ];
                }
            }
        }

        if (!empty($streams)) {
            Cache::setStreams($url, 'cima4u', $streams);
        }
        return $streams;
    }
}
