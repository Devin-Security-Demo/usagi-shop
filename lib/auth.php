<?php

declare(strict_types=1);

const REMEMBER_COOKIE = 'remember';

function current_user(): ?array
{
    static $cache = [];
    $id = $_SESSION['user_id'] ?? null;
    if ($id === null) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $stmt = db()->prepare('SELECT id, name, email, role, avatar, zip, address, phone FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $cache[$id] = $stmt->fetch() ?: null;
    }
    return $cache[$id];
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function login_user(array $user, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['csrf_token']);
    if ($remember) {
        issue_remember_token((int) $user['id']);
    }
}

function issue_remember_token(int $userId): void
{
    $token = bin2hex(random_bytes(32));
    $days = (int) config('remember_days', 30);
    $stmt = db()->prepare('INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))');
    $stmt->execute([$userId, hash('sha256', $token), $days]);

    setcookie(REMEMBER_COOKIE, $userId . ':' . $token, [
        'expires'  => time() + $days * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** @return array{uid: int, token: string}|null */
function parse_remember_cookie(string $value): ?array
{
    if (preg_match('/\A([1-9][0-9]{0,18}):([0-9a-f]{64})\z/', $value, $m) !== 1) {
        return null;
    }
    return ['uid' => (int) $m[1], 'token' => $m[2]];
}

function auth_restore_from_cookie(): void
{
    if (isset($_SESSION['user_id']) || empty($_COOKIE[REMEMBER_COOKIE])) {
        return;
    }

    $data = is_string($_COOKIE[REMEMBER_COOKIE]) ? parse_remember_cookie($_COOKIE[REMEMBER_COOKIE]) : null;
    if ($data === null) {
        forget_remember_cookie();
        return;
    }

    $stmt = db()->prepare('SELECT id, token_hash FROM remember_tokens WHERE user_id = ? AND expires_at > NOW()');
    $stmt->execute([$data['uid']]);
    foreach ($stmt->fetchAll() as $row) {
        if (hash_equals($row['token_hash'], hash('sha256', $data['token']))) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $data['uid'];
            return;
        }
    }
    forget_remember_cookie();
}

function forget_remember_cookie(): void
{
    setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function logout_user(): void
{
    if (isset($_SESSION['user_id'])) {
        db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$_SESSION['user_id']]);
    }
    forget_remember_cookie();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('info', 'ログインしてください。');
        redirect('/login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>403</title><p>このページへのアクセス権限がありません。</p><p><a href="/">トップへ戻る</a></p>';
        exit;
    }
    return $user;
}
