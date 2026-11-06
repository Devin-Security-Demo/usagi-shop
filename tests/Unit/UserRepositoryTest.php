<?php

declare(strict_types=1);

namespace Usagi\Tests;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Usagi\UserRepository;

final class UserRepositoryTest extends TestCase
{
    private UserRepository $users;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE, password_hash TEXT, role TEXT)');
        $stmt = $pdo->prepare('INSERT INTO users (id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([1, '管理者', 'admin@usagi.example', password_hash('password', PASSWORD_DEFAULT), 'admin']);
        $stmt->execute([2, '佐藤 花子', 'user1@usagi.example', password_hash('password', PASSWORD_DEFAULT), 'customer']);
        $this->users = new UserRepository($pdo);
    }

    public function testFindsUserByExactEmail(): void
    {
        $user = $this->users->findByEmail('user1@usagi.example');

        self::assertNotNull($user);
        self::assertSame(2, (int) $user['id']);
        self::assertTrue(password_verify('password', $user['password_hash']));
    }

    public function testUnknownEmailReturnsNull(): void
    {
        self::assertNull($this->users->findByEmail('nobody@usagi.example'));
    }

    public static function injectionPayloads(): array
    {
        $hash = password_hash('attacker', PASSWORD_DEFAULT);
        return [
            'or 1=1'           => ["' OR '1'='1"],
            'or 1=1 comment'   => ["' OR 1=1 -- "],
            'admin comment'    => ["admin@usagi.example' -- "],
            'union forged row' => ["x' UNION SELECT 1, 'Mallory', 'm@example', '{$hash}', 'admin' -- "],
        ];
    }

    #[DataProvider('injectionPayloads')]
    public function testSqlInjectionPayloadsMatchNoUser(string $email): void
    {
        self::assertNull($this->users->findByEmail($email));
    }
}
