<?php

declare(strict_types=1);

use Usagi\Cart;

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/app.php';
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function yen(int|float|string $amount): string
{
    return '¥' . number_format((int) $amount);
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function cart(): Cart
{
    return new Cart($_SESSION['cart'] ?? []);
}

function save_cart(Cart $cart): void
{
    $_SESSION['cart'] = $cart->items();
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function order_status_label(string $status): string
{
    return match ($status) {
        'processing' => '処理中',
        'shipped'    => '発送済',
        'completed'  => '完了',
        'cancelled'  => 'キャンセル',
        default      => $status,
    };
}

function order_status_badge(string $status): string
{
    $class = match ($status) {
        'processing' => 'badge-status-processing',
        'shipped'    => 'badge-status-shipped',
        'completed'  => 'badge-status-completed',
        default      => 'badge-status-cancelled',
    };
    return '<span class="badge rounded-pill ' . $class . '">' . h(order_status_label($status)) . '</span>';
}

function format_date(?string $datetime, string $format = 'Y年n月j日'): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    return (new DateTimeImmutable($datetime))->format($format);
}

function stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return '<span class="stars" aria-label="' . $rating . ' / 5">'
        . str_repeat('<span class="star on">★</span>', $rating)
        . str_repeat('<span class="star">★</span>', 5 - $rating)
        . '</span>';
}

function avatar_url(?array $user): string
{
    if ($user !== null && !empty($user['avatar'])) {
        return '/uploads/avatars/' . rawurlencode($user['avatar']);
    }
    return '/images/avatar-default.svg';
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_ROOT . '/lib/views/' . $view . '.php';
}

function not_found(): never
{
    http_response_code(404);
    require APP_ROOT . '/public/404.php';
    exit;
}

function invoice_path(string $file, ?string $dir = null): ?string
{
    $base = realpath($dir ?? APP_ROOT . '/storage/invoices');
    $name = basename($file);
    if ($base === false || $name !== $file || !preg_match('/\AINV-\d+\.pdf\z/', $name)) {
        return null;
    }
    $path = realpath($base . DIRECTORY_SEPARATOR . $name);
    if ($path === false || !is_file($path) || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $path;
}
