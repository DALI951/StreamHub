<?php
class Cache {
    public static function getMetadata(string $url, string $source): ?array {
        $row = Database::fetchOne(
            "SELECT * FROM cache_metadata WHERE url = ? AND source = ? AND expires_at > NOW()",
            [$url, $source]
        );
        if (!$row) return null;
        if (!empty($row['extra_data'])) {
            $extra = json_decode($row['extra_data'], true);
            if (is_array($extra)) {
                if (isset($extra['episodes'])) $row['episodes'] = $extra['episodes'];
                if (isset($extra['extra'])) $row['extra'] = $extra['extra'];
            }
        }
        if (!empty($row['seasons_data'])) {
            $row['seasons'] = json_decode($row['seasons_data'], true);
        }
        return $row;
    }

    public static function setMetadata(string $url, string $source, array $data, ?int $ttl = null): void {
        $config = require __DIR__ . '/../config.php';
        $ttl = $ttl ?? $config['cache']['metadata_ttl'];
        $expires = date('Y-m-d H:i:s', time() + $ttl);
        $type = $data['type'] ?? 'movie';
        if (!in_array($type, ['movie', 'series', 'season', 'episode', 'anime'])) $type = 'series';

        Database::insert('cache_metadata', [
            'url'          => $url,
            'source'       => $source,
            'title'        => $data['title'] ?? '',
            'title_ar'     => $data['title_ar'] ?? null,
            'type'         => $type,
            'year'         => $data['year'] ?? null,
            'poster'       => $data['poster'] ?? null,
            'banner'       => $data['banner'] ?? null,
            'description'  => $data['description'] ?? null,
            'rating'       => $data['rating'] ?? null,
            'seasons_data' => isset($data['seasons']) ? json_encode($data['seasons']) : null,
            'extra_data'   => json_encode(array_filter([
                'episodes' => $data['episodes'] ?? null,
                'extra'    => $data['extra'] ?? null,
            ])),
            'expires_at'   => $expires,
        ]);
    }

    public static function getStreams(string $contentUrl, string $source): array {
        return Database::fetchAll(
            "SELECT * FROM cache_streams WHERE content_url = ? AND source = ? AND expires_at > NOW() ORDER BY quality DESC",
            [$contentUrl, $source]
        );
    }

    public static function setStreams(string $contentUrl, string $source, array $streams, ?int $ttl = null): void {
        $config = require __DIR__ . '/../config.php';
        $ttl = $ttl ?? $config['cache']['streams_ttl'];
        $expires = date('Y-m-d H:i:s', time() + $ttl);

        Database::query("DELETE FROM cache_streams WHERE content_url = ? AND source = ?", [$contentUrl, $source]);

        foreach ($streams as $stream) {
            Database::insert('cache_streams', [
                'content_url'  => $contentUrl,
                'source'       => $source,
                'quality'      => $stream['quality'] ?? null,
                'quality_label'=> $stream['quality_label'] ?? null,
                'stream_url'   => $stream['stream_url'] ?? '',
                'stream_type'  => $stream['stream_type'] ?? 'hls',
                'referer'      => $stream['referer'] ?? null,
                'server_name'  => $stream['server_name'] ?? null,
                'expires_at'   => $expires,
            ]);
        }
    }

    public static function getSearch(string $query, string $source = ''): ?array {
        $hash = sha1(strtolower(trim($query)) . '|' . strtolower(trim($source)));
        $row = Database::fetchOne(
            "SELECT results FROM search_cache WHERE query_hash = ? AND expires_at > NOW()",
            [$hash]
        );
        return $row ? json_decode($row['results'], true) : null;
    }

    public static function setSearch(string $query, array $results, ?int $ttl = null, string $source = ''): void {
        $config = require __DIR__ . '/../config.php';
        $ttl = $ttl ?? $config['cache']['search_ttl'];
        $hash = sha1(strtolower(trim($query)) . '|' . strtolower(trim($source)));
        $expires = date('Y-m-d H:i:s', time() + $ttl);

        Database::insert('search_cache', [
            'query_hash' => $hash,
            'query_text' => $query,
            'results'    => json_encode($results),
            'expires_at' => $expires,
        ]);
    }

    public static function cleanExpired(): void {
        Database::query("DELETE FROM cache_metadata WHERE expires_at < NOW()");
        Database::query("DELETE FROM cache_streams WHERE expires_at < NOW()");
        Database::query("DELETE FROM search_cache WHERE expires_at < NOW()");
    }
}
