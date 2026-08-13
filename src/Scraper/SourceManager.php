<?php
require_once __DIR__ . '/BaseScraper.php';
require_once __DIR__ . '/EgyDeadScraper.php';
require_once __DIR__ . '/Cima4uScraper.php';
require_once __DIR__ . '/TopCinemaScraper.php';
require_once __DIR__ . '/FaselHDScraper.php';
require_once __DIR__ . '/MyCimaScraper.php';
require_once __DIR__ . '/ArabSeedScraper.php';
require_once __DIR__ . '/AkwamScraper.php';
require_once __DIR__ . '/BlkomScraper.php';

class SourceManager {
    private array $scrapers = [];
    private array $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../../config.php';
        $this->loadScrapers();
    }

    private function loadScrapers(): void {
        $classMap = [
            'EgyDeadScraper'   => EgyDeadScraper::class,
            'Cima4uScraper'    => Cima4uScraper::class,
            'TopCinemaScraper' => TopCinemaScraper::class,
            'FaselHDScraper'   => FaselHDScraper::class,
            'MyCimaScraper'    => MyCimaScraper::class,
            'ArabSeedScraper'  => ArabSeedScraper::class,
            'AkwamScraper'     => AkwamScraper::class,
            'BlkomScraper'     => BlkomScraper::class,
        ];
        foreach ($this->config['sources'] as $name => $info) {
            $className = $info['class'] ?? null;
            if ($className && isset($classMap[$className]) && class_exists($classMap[$className])) {
                $this->scrapers[$name] = new $classMap[$className]($info['base'], $name);
                $this->scrapers[$name]->priority = $info['priority'] ?? 99;
            }
        }
        uasort($this->scrapers, fn($a, $b) => ($a->priority ?? 99) <=> ($b->priority ?? 99));
    }

    public function detectSource(string $url): ?BaseScraper {
        foreach ($this->scrapers as $name => $scraper) {
            if ($scraper->detectFromUrl($url)) {
                return $scraper;
            }
        }
        return null;
    }

    public function getScraper(string $name): ?BaseScraper {
        return $this->scrapers[$name] ?? null;
    }

    public function resolveSource(string $source): ?BaseScraper {
        $source = strtolower(trim($source));
        if ($source === '') return null;

        $scraper = $this->getScraper($source);
        if ($scraper) return $scraper;

        $candidates = [
            $source,
            preg_replace('/^tv\d+\./', '', $source),       // "tv8.egydead" -> "egydead"
            preg_replace('/^www\./', '', $source),
            preg_replace('/\.(live|com|net|ws|top|win|show|fan|tv)$/i', '', $source),
        ];

        foreach ($this->scrapers as $name => $s) {
            $host = strtolower((string) parse_url($s->getBaseUrl(), PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host);
            if ($host === '' || $host === null) continue;

            foreach ($candidates as $cand) {
                if ($cand === '' || $cand === null) continue;
                if ($cand === $host || $cand === $name) return $s;
                if (strpos($host, $cand . '.') === 0) return $s;   // host starts with candidate label(s)
                if (strpos($cand, $host . '.') === 0) return $s;   // candidate is a mirror prefix of host
            }
        }
        return null;
    }

    public function getAllScrapers(): array {
        return $this->scrapers;
    }

    public function searchAll(string $query): array {
        $allResults = [];
        $mh = curl_multi_init();
        $handles = [];

        foreach ($this->scrapers as $name => $scraper) {
            $url = $this->buildSearchUrl($scraper, $query);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => $this->config['scraping']['user_agent'],
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
                ],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$name] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
        } while ($active && $status === CURLM_OK);

        foreach ($handles as $name => $ch) {
            $html = curl_multi_getcontent($ch);
            $errno = curl_errno($ch);
            curl_multi_remove_handle($mh, $ch);

            if ($html && !$errno) {
                $scraper = $this->scrapers[$name];
                $results = $scraper->parseSearchResults($html);
                foreach ($results as &$r) {
                    $r['source'] = $name;
                }
                unset($r);
                $allResults = array_merge($allResults, $results);
            }
        }
        curl_multi_close($mh);

        usort($allResults, function ($a, $b) {
            return ($a['source'] === 'egydead' ? 0 : 1) - ($b['source'] === 'egydead' ? 0 : 1);
        });

        return $allResults;
    }

    private function buildSearchUrl(BaseScraper $scraper, string $query): string {
        $encoded = urlencode($query);
        $base    = rtrim($scraper->getBaseUrl(), '/');

        $patterns = [
            'egydead'   => "{$base}/?s={$encoded}",
            'cima4u'    => "{$base}/search/{$encoded}",
            'topcinema' => "{$base}/search/{$encoded}",
            'faselhd'   => "{$base}/?s={$encoded}",
            'mycima'    => "{$base}/search/{$encoded}",
            'arabseed'  => "{$base}/search/{$encoded}",
            'akwam'     => "{$base}/search/{$encoded}",
            'blkom'     => "{$base}/search?query={$encoded}&page=1",
        ];

        return $patterns[$scraper->getSourceName()] ?? "{$base}/?s={$encoded}";
    }
}
