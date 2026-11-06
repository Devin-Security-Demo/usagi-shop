<?php

declare(strict_types=1);

namespace Usagi\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/lib/auth.php';

final class RememberCookieTest extends TestCase
{
    public function testParsesUidAndToken(): void
    {
        $token = str_repeat('ab', 32);
        $this->assertSame(['uid' => 42, 'token' => $token], \parse_remember_cookie('42:' . $token));
    }

    /**
     * @dataProvider invalidCookies
     */
    public function testRejectsInvalidCookies(mixed $value): void
    {
        $this->assertNull(\parse_remember_cookie($value));
    }

    public static function invalidCookies(): array
    {
        $token = str_repeat('ab', 32);
        return [
            'serialized object' => ['O:31:"GuzzleHttp\\Cookie\\FileCookieJar":1:{s:8:"filename";s:8:"/tmp/pwn";}'],
            'legacy serialized array' => [serialize(['uid' => 1, 'token' => $token])],
            'array' => [['uid' => 1, 'token' => $token]],
            'empty' => [''],
            'missing token' => ['1:'],
            'zero uid' => ['0:' . $token],
            'negative uid' => ['-1:' . $token],
            'short token' => ['1:abcd'],
            'uppercase token' => ['1:' . strtoupper($token)],
            'trailing newline' => ['1:' . $token . "\n"],
            'extra segment' => ['1:' . $token . ':x'],
        ];
    }
}
