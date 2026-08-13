<?php
require_once __DIR__ . '/BaseScraper.php';

class BlkomScraper extends BaseScraper {
    private string $ipFallback = 'http://103.155.92.42';

    public function __construct(string $baseUrl, string $sourceName) {
        parent::__construct($baseUrl, $sourceName);
    }

    protected function fetch(string $url, array $headers = [], ?string $referer = null): ?string {
        $result = parent::fetch($url, $headers, $referer);
        if ($result !== null) return $result;

        $domainUrl = parse_url($url, PHP_URL_HOST);
        $ipHost = parse_url($this->ipFallback, PHP_URL_HOST);
        if ($domainUrl && $ipHost && $domainUrl !== $ipHost) {
            $ipUrl = str_replace('https://' . $domainUrl, $this->ipFallback, $url);
            $ipUrl = str_replace('http://' . $domainUrl, $this->ipFallback, $ipUrl);
            $result = parent::fetch($ipUrl, $headers, $referer);
        } elseif (stripos($url, 'https://') === 0) {
            $httpUrl = 'http://' . substr($url, 8);
            $result = parent::fetch($httpUrl, $headers, $referer);
        }
        return $result;
    }

    public function detectFromUrl(string $url): bool {
        return (bool) preg_match('/animeblkom\.(net|tv)|blkom\.com|103\.155\.92\.42/i', $url);
    }

    public function search(string $query): array {
        $url = $this->baseUrl . '/search?query=' . urlencode($query) . '&page=1';
        $html = $this->fetch($url, [], $this->baseUrl);
        if (!$html) return [];
        return $this->parseSearchResults($html);
    }

    public function parseSearchResults(string $html): array {
        $results = [];
        $pattern = '/<div[^>]*class="content"[^>]*>\s*<div[^>]*class="content-inner"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/si';
        if (!preg_match_all($pattern, $html, $blocks, PREG_SET_ORDER)) {
            $pattern = '/<div[^>]*class="content"[^>]*>(.*?)<\/div>\s*<\/div>/si';
            preg_match_all($pattern, $html, $blocks, PREG_SET_ORDER);
        }

        foreach ($blocks as $block) {
            $inner = $block[1];

            $href = '';
            if (preg_match('/<div[^>]*class="poster"[^>]*>\s*<a[^>]+href=["\']([^"\']+)["\']/', $inner, $hm)) {
                $href = trim($hm[1]);
            }
            if (!$href && preg_match('/<div[^>]*class="name"[^>]*>\s*<a[^>]+href=["\']([^"\']+)["\']/', $inner, $hm)) {
                $href = trim($hm[1]);
            }
            if (!$href) continue;
            $fullUrl = $this->toAbsolute($href);

            $title = '';
            if (preg_match('/<div[^>]*class="name"[^>]*>\s*<a[^>]*>(.*?)<\/a>/si', $inner, $tm)) {
                $title = $this->cleanText($tm[1]);
            }
            if (!$title && preg_match('/alt=["\']([^"\']+)["\']/', $inner, $am)) {
                $title = rtrim($this->cleanText($am[1]), ' poster');
            }
            if (!$title) continue;

            $poster = '';
            if (preg_match('/<img[^>]+data-original=["\']([^"\']+)["\']/', $inner, $pm)) {
                $poster = $this->toAbsolute($pm[1]);
            }
            if (!$poster && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $inner, $pm)) {
                $poster = $this->toAbsolute($pm[1]);
            }

            $results[] = [
                'title'  => $title,
                'url'    => $fullUrl,
                'poster' => $poster,
                'type'   => 'anime',
                'source' => $this->sourceName,
            ];
        }
        return $results;
    }

    public function getDetails(string $url): ?array {
        $html = $this->fetch($url, [], $this->baseUrl);
        if (!$html) return null;

        $title = '';
        if (preg_match('/<div[^>]*class="name"[^>]*>\s*<span[^>]*>\s*<h1[^>]*>(.*?)<\/h1>/si', $html, $tm)) {
            $title = $this->cleanText($tm[1]);
        }
        if (!$title && preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $tm)) {
            $title = $this->cleanText($tm[1]);
        }
        if (!$title && preg_match('/og:title["\s]+content=["\']([^"\']+)["\']/', $html, $tm)) {
            $title = $this->cleanText($tm[1]);
        }
        if (!$title) return null;

        $poster = '';
        if (preg_match('/<div[^>]*class="poster"[^>]*>\s*<img[^>]+(?:data-original|src)=["\']([^"\']+)["\']/', $html, $pm)) {
            $poster = $this->toAbsolute($pm[1]);
        }
        if (!$poster && preg_match('/og:image["\s]+content=["\']([^"\']+)["\']/', $html, $pm)) {
            $poster = $this->toAbsolute($pm[1]);
        }

        $description = '';
        if (preg_match('/<div[^>]*class="story-text"[^>]*>(.*?)<\/div>/si', $html, $dm)) {
            $description = $this->cleanText($dm[1]);
        }
        if (!$description && preg_match('/<div[^>]*class="story"[^>]*>(.*?)<\/div>/si', $html, $dm)) {
            $description = $this->cleanText($dm[1]);
        }
        if (!$description && preg_match('/og:description["\s]+content=["\']([^"\']+)["\']/', $html, $dm)) {
            $description = $this->cleanText($dm[1]);
        }

        $episodes = [];
        $epPattern = '/<ul[^>]*class="[^"]*episodes-links[^"]*"[^>]*>(.*?)<\/ul>/si';
        if (preg_match($epPattern, $html, $epBlock)) {
            $linkPattern = '/<li[^>]*>\s*<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si';
            if (preg_match_all($linkPattern, $epBlock[1], $epMatches, PREG_SET_ORDER)) {
                foreach ($epMatches as $em) {
                    $epUrl = $this->toAbsolute(trim($em[1]));
                    $epHtml = $em[2];
                    $epTitle = '';
                    if (preg_match('/<span[^>]*>(.*?)<\/span>/si', $epHtml, $etm)) {
                        $epTitle = $this->cleanText($etm[1]);
                    }
                    if (!$epTitle) $epTitle = $this->cleanText($epHtml);

                    $epNum = 0;
                    if (preg_match('/(\d+)/', $epTitle, $enm)) {
                        $epNum = (int) $enm[1];
                    }
                    if ($epNum === 0 && preg_match('/\/(\d+)$/', $epUrl, $unm)) {
                        $epNum = (int) $unm[1];
                    }

                    $episodes[] = [
                        'number' => $epNum,
                        'title'  => $epTitle,
                        'url'    => $epUrl,
                    ];
                }
            }
        }

        $type = 'anime';
        if (!empty($episodes)) {
            $type = 'season';
        }

        $data = [
            'title'       => $title,
            'url'         => $url,
            'poster'      => $poster,
            'type'        => $type,
            'year'        => null,
            'description' => $description,
            'source'      => $this->sourceName,
            'seasons'     => [],
            'episodes'    => $episodes,
        ];

        return $data;
    }

    public function getStreams(string $url): array {
        $cached = Cache::getStreams($url, $this->sourceName);
        if (!empty($cached)) return $cached;

        $html = $this->fetch($url, [], $this->baseUrl);
        if (!$html) return [];

        $streams = [];

        $serverPattern = '/<span[^>]*class="[^"]*server[^"]*"[^>]*>\s*<a[^>]+data-src=["\']([^"\']+)["\'][^>]*(?:>(.*?)<\/a>)?/si';
        if (preg_match_all($serverPattern, $html, $serverMatches, PREG_SET_ORDER)) {
            foreach ($serverMatches as $sm) {
                $serverUrl = $this->toAbsolute(trim($sm[1]));
                $serverName = $this->cleanText($sm[2] ?? '');
                if (!$serverName) $serverName = 'Server';

                $isBlkom = (stripos($serverUrl, 'vid4up') !== false || stripos($serverName, 'blkom') !== false);

                if ($isBlkom) {
                    $serverHtml = $this->fetch($serverUrl, [], $url);
                    if ($serverHtml) {
                        $sourcePattern = '/<source[^>]+src=["\']([^"\']+)["\'][^>]*(?:label=["\']([^"\']*)["\'])?/si';
                        if (preg_match_all($sourcePattern, $serverHtml, $sourceMatches, PREG_SET_ORDER)) {
                            foreach ($sourceMatches as $srcm) {
                                $videoUrl = html_entity_decode($srcm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                $quality = $srcm[2] ?: 'auto';
                                $streamType = 'hls';
                                if (preg_match('/\.mp4(\?|$)/i', $videoUrl)) $streamType = 'mp4';
                                elseif (preg_match('/\.m3u8(\?|$)/i', $videoUrl)) $streamType = 'hls';
                                $streams[] = [
                                    'stream_url'    => $videoUrl,
                                    'stream_type'   => $streamType,
                                    'quality'       => $quality,
                                    'quality_label' => $quality,
                                    'referer'       => $url,
                                    'server_name'   => $serverName,
                                ];
                            }
                        }
                    }
                } else {
                    $streams[] = [
                        'stream_url'    => $serverUrl,
                        'stream_type'   => 'iframe',
                        'quality'       => 'auto',
                        'quality_label' => $serverName,
                        'referer'       => $url,
                        'server_name'   => $serverName,
                    ];
                }
            }
        }

        if (empty($streams)) {
            $iframes = $this->extractIframes($html);
            foreach ($iframes as $iframeUrl) {
                $streams[] = [
                    'stream_url'    => $iframeUrl,
                    'stream_type'   => 'iframe',
                    'quality'       => 'auto',
                    'quality_label' => 'Embedded',
                    'referer'       => $url,
                    'server_name'   => 'Embedded',
                ];
            }
        }

        if (!empty($streams)) {
            Cache::setStreams($url, $this->sourceName, $streams);
        }
        return $streams;
    }
}
