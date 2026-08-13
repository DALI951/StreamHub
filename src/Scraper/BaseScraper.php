<?php
abstract class BaseScraper {
    protected string $baseUrl;
    protected string $sourceName;
    protected int $timeout;
    public int $priority = 99;

    public function __construct(string $baseUrl, string $sourceName) {
        $this->baseUrl    = rtrim($baseUrl, '/');
        $this->sourceName = $sourceName;
        $config           = require __DIR__ . '/../../config.php';
        $this->timeout    = $config['scraping']['timeout'];
    }

    public function getBaseUrl(): string {
        return $this->baseUrl;
    }

    public function getSourceName(): string {
        return $this->sourceName;
    }

    abstract public function search(string $query): array;
    abstract public function getDetails(string $url): ?array;
    abstract public function getStreams(string $url): array;
    abstract public function detectFromUrl(string $url): bool;

    protected function fetch(string $url, array $headers = [], ?string $referer = null): ?string {
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
            CURLOPT_HTTPHEADER     => array_merge([
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
            ], $headers),
            CURLOPT_USERAGENT      => $config['scraping']['user_agent'],
        ]);
        if ($referer) {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false || $httpCode >= 400) {
            return null;
        }
        return $response;
    }

    protected function fetchJson(string $url, array $headers = [], ?string $referer = null): ?array {
        $headers[] = 'Accept: application/json, text/plain, */*';
        $headers[] = 'X-Requested-With: XMLHttpRequest';
        $body = $this->fetch($url, $headers, $referer);
        if (!$body) return null;
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    protected function toAbsolute(string $path): string {
        if (str_starts_with($path, 'http')) return $path;
        if (str_starts_with($path, '//')) return 'https:' . $path;
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    protected function cleanText(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    protected function extractIframes(string $html): array {
        preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\']/', $html, $matches);
        return $matches[1] ?? [];
    }

    protected function extractPackerBlocks(string $html): array {
        $blocks = [];
        $pattern = '/eval\(function\(p,a,c,k,e,d\)\{/si';
        if (preg_match_all($pattern, $html, $starts, PREG_OFFSET_CAPTURE)) {
            foreach ($starts[0] as $start) {
                $offset = $start[1] + strlen($start[0]);
                $rest = substr($html, $offset);
                if (preg_match('/\}\(\'/', $rest, $bodyEnd, PREG_OFFSET_CAPTURE)) {
                    $argsStart = $offset + $bodyEnd[0][1] + 2;
                    $argsStr = substr($html, $argsStart);
                    if (preg_match('/^\'((?:[^\'\\\\]|\\\\.)*)\'/', $argsStr, $payloadMatch)) {
                        $payloadEnd = $argsStart + strlen($payloadMatch[0]);
                        $remaining = substr($html, $payloadEnd);
                        if (preg_match('/\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*\'([^\']+)\'(?:\.split\(\'\|\'\))?\s*(?:,\s*\d+\s*,\s*\{\}\s*\)\s*\)|\)\s*\))\s*\)?/', $remaining, $restMatch)) {
                            $fullEnd = $payloadEnd + strlen($restMatch[0]);
                            $blocks[] = substr($html, $start[1], $fullEnd - $start[1]);
                        }
                    }
                }
            }
        }
        return $blocks;
    }

    protected function decodePacker(string $code): string {
        $pattern = '/eval\(function\(p,a,c,k,e,d\)\{/si';
        if (preg_match($pattern, $code, $startMatch, PREG_OFFSET_CAPTURE)) {
            $offset = $startMatch[0][1] + strlen($startMatch[0][0]);
            $rest = substr($code, $offset);
            if (preg_match('/\}\(\'/', $rest, $bodyEnd, PREG_OFFSET_CAPTURE)) {
                $argsStart = $offset + $bodyEnd[0][1] + 2;
                $argsStr = substr($code, $argsStart);
                if (preg_match('/^\'((?:[^\'\\\\]|\\\\.)*)\'/', $argsStr, $payloadMatch)) {
                    $payload = $payloadMatch[1];
                    $payloadEnd = $argsStart + strlen($payloadMatch[0]);
                    $remaining = substr($code, $payloadEnd);
                    if (preg_match('/\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*\'([^\']+)\'(?:\.split\(\'\|\'\))?\s*(?:,\s*\d+\s*,\s*\{\}\s*\)\s*\)|\)\s*\))/', $remaining, $restMatch)) {
                        $base  = (int)$restMatch[1];
                        $count = (int)$restMatch[2];
                        $dict  = $restMatch[3];
                        return $this->_packerDecode($payload, $base, $count, $dict);
                    }
                }
            }
        }
        return '';
    }

    private function _packerDecode(string $payload, int $base, int $count, string $dict): string {
        $words = explode('|', $dict);
        $result = $payload;
        for ($i = $count - 1; $i >= 0; $i--) {
            $token = $this->_intToToken($i, $base);
            $word = $words[$i] ?? '';
            if ($word !== '') {
                $result = preg_replace('/\b' . preg_quote($token, '/') . '\b/', $word, $result);
            }
        }
        return $result;
    }

    private function _intToToken(int $num, int $base): string {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        if ($num === 0) return '0';
        $result = '';
        while ($num > 0) {
            $result = $chars[$num % $base] . $result;
            $num = intdiv($num, $base);
        }
        return $result;
    }
}
