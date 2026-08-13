<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'modalidb',
        'user' => 'modali',
        'pass' => 'waFS6FtEt5Qm1H94!#',
        'charset' => 'utf8mb4',
    ],
    'cache' => [
        'metadata_ttl' => 86400,   // 24 hours
        'streams_ttl'  => 21600,   // 6 hours
        'search_ttl'   => 3600,    // 1 hour
    ],
    'scraping' => [
        'timeout'       => 15,
        'max_redirects' => 3,
        'user_agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    ],
    'sources' => [
        'egydead'   => ['base' => 'https://tv10.egydead.live',   'class' => 'EgyDeadScraper',   'priority' => 1],
        'faselhd'   => ['base' => 'https://www.faselhd.com',     'class' => 'FaselHDScraper',    'priority' => 2],
        'akwam'     => ['base' => 'https://akwam.ws',            'class' => 'AkwamScraper',      'priority' => 3],
        'cima4u'    => ['base' => 'https://www.cima4ua.top',     'class' => 'Cima4uScraper',     'priority' => 4],
        'topcinema' => ['base' => 'https://topcinema.fan',       'class' => 'TopCinemaScraper',  'priority' => 5],
        'mycima'    => ['base' => 'https://mycima.win',          'class' => 'MyCimaScraper',     'priority' => 6],
        'arabseed'  => ['base' => 'https://arabseed.show',       'class' => 'ArabSeedScraper',   'priority' => 7],
        'blkom'     => ['base' => 'http://103.155.92.42',         'class' => 'BlkomScraper',      'priority' => 8],
    ],
];
