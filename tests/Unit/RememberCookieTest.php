<?php

declare(strict_types=1);

namespace Usagi\Tests;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Cookie\SetCookie;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RememberCookieTest extends TestCase
{
    private string $target;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/lib/auth.php';
        $this->target = sys_get_temp_dir() . '/usagi_remember_' . bin2hex(random_bytes(8)) . '.php';
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        @unlink($this->target);
        unset($_COOKIE[REMEMBER_COOKIE]);
    }

    public function testParsesIssuedFormat(): void
    {
        $token = str_repeat('ab', 32);
        self::assertSame(['uid' => 42, 'token' => $token], parse_remember_cookie('42:' . $token));
    }

    public function testRejectsMalformedValues(): void
    {
        $token = str_repeat('ab', 32);
        foreach ([
            serialize(['uid' => 1, 'token' => $token]),
            '0:' . $token,
            '-1:' . $token,
            '1:' . strtoupper($token),
            '1:' . substr($token, 1),
            "1:{$token}\n",
            ' 1:' . $token,
            '1:' . $token . ':x',
            ['1:' . $token],
        ] as $value) {
            self::assertNull(parse_remember_cookie($value), var_export($value, true));
        }
    }

    #[RunInSeparateProcess]
    public function testSerializedObjectCookieCreatesNoObjectOrFileAndIsCleared(): void
    {
        $payload = self::fileCookieJarGadget($this->target, '<?php echo 1;');
        self::assertStringStartsWith('O:', $payload);

        $_COOKIE[REMEMBER_COOKIE] = $payload;
        auth_restore_from_cookie();
        gc_collect_cycles();

        self::assertFileDoesNotExist($this->target);
        self::assertFalse(class_exists(FileCookieJar::class, false));
        self::assertArrayNotHasKey(REMEMBER_COOKIE, $_COOKIE);
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    private static function fileCookieJarGadget(string $file, string $content): string
    {
        $cookie = self::serializeObject(SetCookie::class, [
            [SetCookie::class, 'data', [
                'Name' => 'x', 'Value' => $content, 'Domain' => 'a', 'Path' => '/', 'Max-Age' => null,
                'Expires' => time() + 3600, 'Secure' => false, 'Discard' => false, 'HttpOnly' => false,
            ]],
        ]);
        return self::serializeObject(FileCookieJar::class, [
            [FileCookieJar::class, 'filename', $file],
            [FileCookieJar::class, 'storeSessionCookies', true],
            [CookieJar::class, 'cookies', [$cookie]],
            [CookieJar::class, 'strictMode', false],
        ]);
    }

    /**
     * Builds a serialized object string by hand so the gadget class is never loaded by the test itself.
     *
     * @param list<array{string, string, mixed}> $props
     */
    private static function serializeObject(string $class, array $props): string
    {
        $body = '';
        foreach ($props as [$owner, $name, $value]) {
            $body .= serialize("\0{$owner}\0{$name}");
            $body .= is_array($value) && isset($value[0]) && is_string($value[0]) && str_starts_with($value[0], 'O:')
                ? 'a:1:{i:0;' . $value[0] . '}'
                : serialize($value);
        }
        return sprintf('O:%d:"%s":%d:{%s}', strlen($class), $class, count($props), $body);
    }
}
