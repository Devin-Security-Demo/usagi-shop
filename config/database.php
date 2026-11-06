<?php

return [
    'driver'   => 'mysql',
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_DATABASE') ?: 'usagi_shop',
    'username' => getenv('DB_USERNAME') ?: 'usagi_app',
    'password' => getenv('DB_PASSWORD') ?: 'Usag1Sh0p#Db2024!',
    'charset'  => 'utf8mb4',
];
