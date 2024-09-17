<?php declare(strict_types=1);

use Schnoop\RemoteTemplate\Support\DefaultBladeFilename;

return [
    'guzzle-config' => [
        'allow_redirects' => false,
        'timeout' => 5,
        'connect_timeout' => 5,
    ],

    'url_modifier' => [],

    'blade_modifier' => DefaultBladeFilename::class,

    'remote-delimiter' => 'remote:',

    'ignore-url-suffix' => ['png', 'jpg', 'jpeg', 'css', 'js', 'woff', 'ttf', 'gif', 'svg'],

    'ignore-urls' => ['typo3', 'typo3/'],

    'view-folder' => base_path('resources/views/remote-view-cache/'),

    'hosts' => [
        'default' => [
            'cache' => false,
            'host' => 'https://www.sportschau.de',
            'request_options' => [
                'auth_user' => '',
                'auth_password' => '',
            ],
            'mapping' => [
                '403' => '/fussball/fifa-wm-2022/wm-katar-auslosung-deutschland-gegner-100.html',
                '404' => '/fussball/fifa-wm-2022/audio-dfb-team-so-lief-die-qualifikation-zur-wm-in-katar-100.html',
            ],
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
