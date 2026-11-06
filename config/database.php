<?php

$env = static function (string $name, ?string $default = null): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        if ($default === null) {
            throw new RuntimeException("Environment variable {$name} is not set");
        }
        return $default;
    }
    return $value;
};

return [
    'driver'   => 'mysql',
    'host'     => $env('DB_HOST', 'db'),
    'port'     => (int) $env('DB_PORT', '3306'),
    'database' => $env('DB_DATABASE', 'usagi_shop'),
    'username' => $env('DB_USERNAME', 'usagi_app'),
    'password' => $env('DB_PASSWORD'),
    'charset'  => 'utf8mb4',
];
