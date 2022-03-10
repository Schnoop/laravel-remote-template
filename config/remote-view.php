<?php

declare(strict_types=1);

return [
    'guzzle-config' => [
        'allow_redirects' => false,
        'timeout' => 5,
        'connect_timeout' => 5,
    ],

    'remote-delimiter' => 'remote:',

    'ignore-url-suffix' => ['png', 'jpg', 'jpeg', 'css', 'js', 'woff', 'ttf', 'gif', 'svg'],

    'ignore-urls' => ['typo3', 'typo3/'],

    'view-folder' => base_path('resources/views/remote-view-cache/'),

    'hosts' => [
        'default' => [
            'cache' => false,
            'host' => 'https://www.your-first-content-domain.tld',
            'request_options' => [
                'auth_user' => '',
                'auth_password' => '',
            ],
            'mapping' => [],
        ],

        'specific' => [
            'cache' => false,
            'host' => 'https://www.your-second-content-domain.tld',
            'request_options' => [
                'auth' => ['', '', ''],
            ],
            'mapping' => [],
        ],
    ],
];
