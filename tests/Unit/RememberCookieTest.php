<?php

declare(strict_types=1);

namespace Usagi\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/auth.php';

final class RememberCookieTest extends TestCase
{
    public function testParsesUidAndToken(): void
    {
        $token = str_repeat('ab', 32);

        self::assertSame(['uid' => 42, 'token' => $token], \parse_remember_cookie('42:' . $token));
    }

    public static function invalidValues(): array
    {
        $token = str_repeat('ab', 32);

        return [
            'serialized array'  => [serialize(['uid' => 1, 'token' => $token])],
            'serialized object' => ['O:31:"GuzzleHttp\Cookie\FileCookieJar":0:{}'],
            'missing token'     => ['1:'],
            'zero uid'          => ['0:' . $token],
            'non-numeric uid'   => ['x:' . $token],
            'short token'       => ['1:abcd'],
            'uppercase token'   => ['1:' . strtoupper($token)],
            'trailing newline'  => ["1:{$token}\n"],
            'non-string'        => [['1:' . $token]],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(mixed $value): void
    {
        self::assertNull(\parse_remember_cookie($value));
    }
}
