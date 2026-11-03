<?php

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['_token'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(419);
        echo '<!doctype html><meta charset="utf-8"><title>419</title><p>ページの有効期限が切れました。もう一度お試しください。</p><p><a href="/">トップへ戻る</a></p>';
        exit;
    }
}
