<?php

declare(strict_types=1);

// Usage (in the web container): php /var/www/html/bin/set-password.php <email>
// Sets a new random password for the account, revokes its remember-me tokens and prints the password once.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/lib/db.php';

$email = $argv[1] ?? '';
if ($email === '') {
    fwrite(STDERR, "Usage: php /var/www/html/bin/set-password.php <email>\n");
    exit(2);
}

$password = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');

$stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
$stmt->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
if ($stmt->rowCount() !== 1) {
    fwrite(STDERR, "No such user: {$email}\n");
    exit(1);
}
db()->prepare('DELETE t FROM remember_tokens t JOIN users u ON u.id = t.user_id WHERE u.email = ?')->execute([$email]);

echo $password, "\n";
