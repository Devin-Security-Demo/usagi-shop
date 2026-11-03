<?php

return [
    'name'        => 'うさぎ商事オンラインショップ',
    'company'     => '株式会社うさぎ商事',
    'base_url'    => 'http://localhost:8080',
    'shipping_api' => 'http://127.0.0.1/api/shipping.php',
    'free_shipping_threshold' => 5000,
    'remember_days' => 30,
    'mail' => [
        'host'      => 'mailhog',
        'port'      => 1025,
        'from'      => 'shop@usagi.example',
        'from_name' => 'うさぎ商事オンラインショップ',
    ],
];
