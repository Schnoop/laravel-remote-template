<?php

return [

    'cache' => true,

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
    ]

];
