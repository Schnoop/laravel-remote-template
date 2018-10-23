<?php

return [

    'guzzle-config' => [
        'allow_redirects' => false,
        'timeout' => 5,
        'connect_timeout' => 5
    ],

    'remote-delimiter' => 'remote:',

    'ignore-url-suffix' => [
        'png',
        'jpg',
        'jpeg',
        'css',
        'js',
        'woff',
        'ttf',
        'gif',
        'svg'
    ],

    'view-folder' => base_path('resources/views/remote-view-cache/' . $namespace . '/'),

    'hosts' => [

        'default' => [

            'cache' => false,
            'host' => 'https://www.google.de/',
            'request_options' => [
                'auth_user' => '',
                'auth_password' => '',
            ],
            'mapping' => [

            ],

        ],

        'specific' => [

            'cache' => false,
            'host' => 'https://www.google.com/',
            'request_options' => [
                'auth_user' => '',
                'auth_password' => '',
            ],
            'mapping' => [

            ],
        ]

    ]

];
