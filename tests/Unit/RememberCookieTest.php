<?php

declare(strict_types=1);

namespace Usagi\Tests;

use GuzzleHttp\Cookie\FileCookieJar;
use PHPUnit\Framework\TestCase;

final class RememberCookieTest extends TestCase
{
    private string $target;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/lib/auth.php';
        $this->target = sys_get_temp_dir() . '/usagi-remember-' . bin2hex(random_bytes(8)) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->target);
    }

    public function testParseAcceptsIssuedFormat(): void
    {
        $token = str_repeat('ab', 32);
        self::assertSame(['uid' => 42, 'token' => $token], parse_remember_cookie('42:' . $token));
    }

    public function testParseRejectsMalformedValues(): void
    {
        $token = str_repeat('ab', 32);
        foreach (['', '42', ':' . $token, '0:' . $token, 'x:' . $token, '42:' . substr($token, 1), "42:{$token}\n", serialize(['uid' => 42, 'token' => $token])] as $value) {
            self::assertNull(parse_remember_cookie($value), $value);
        }
    }

    public function testSerializedFileCookieJarCreatesNoFileAndClearsCookie(): void
    {
        $jar = new FileCookieJar($this->target);
        $payload = serialize($jar);
        unset($jar);
        @unlink($this->target);
        self::assertStringContainsString('FileCookieJar', $payload);

        $_SESSION = [];
        $_COOKIE[REMEMBER_COOKIE] = $payload;
        auth_restore_from_cookie();
        gc_collect_cycles();

        self::assertFileDoesNotExist($this->target);
        self::assertArrayNotHasKey(REMEMBER_COOKIE, $_COOKIE);
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }
}
