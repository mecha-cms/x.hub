<?php

return [
    'deny' => [
        '/engine' => [
            'delete' => 1,
            'patch' => 1
        ],
        '/engine/log' => 1,
        '/lot' => [
            'delete' => 1,
            'patch' => 1
        ],
        '/lot/.htaccess' => [
            'delete' => 1,
            'patch' => 1
        ],
        'pass.json' => 1,
        'pass.txt' => 1,
        'pass.yaml' => 1,
        'pass.yml' => 1
    ],
    'expiry' => 600, // This field specifies the JWT’s life-span, measured in second(s)
    'omit' => [
        '/lot/asset/.htaccess' => 1,
        '/lot/x/hub/state.php' => 1,
        'pass.json' => 1,
        'pass.txt' => 1,
        'pass.yaml' => 1,
        'pass.yml' => 1
    ],
    'pepper' => '8b0a79306cfa6b0a3b6d6e3f0d2d0f22', // `md5('pepper')`
    'sub' => '/hub'
];