<?php

declare(strict_types=1);

namespace Usagi\Tests;

use PHPUnit\Framework\TestCase;
use Usagi\InvoiceStore;

final class InvoiceStoreTest extends TestCase
{
    private string $root;
    private InvoiceStore $store;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/usagi-invoices-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/invoices', 0777, true);
        mkdir($this->root . '/config');
        file_put_contents($this->root . '/invoices/INV-100231.pdf', '%PDF-1.4');
        file_put_contents($this->root . '/config/database.php', '<?php');
        file_put_contents($this->root . '/secret.pdf', '%PDF-1.4');
        symlink($this->root . '/secret.pdf', $this->root . '/invoices/link.pdf');
        $this->store = new InvoiceStore($this->root . '/invoices');
    }

    protected function tearDown(): void
    {
        unlink($this->root . '/invoices/link.pdf');
        unlink($this->root . '/invoices/INV-100231.pdf');
        unlink($this->root . '/config/database.php');
        unlink($this->root . '/secret.pdf');
        rmdir($this->root . '/invoices');
        rmdir($this->root . '/config');
        rmdir($this->root);
    }

    public function testResolvesExistingInvoice(): void
    {
        $this->assertSame(realpath($this->root . '/invoices/INV-100231.pdf'), $this->store->path('INV-100231.pdf'));
    }

    /** @dataProvider rejectedNames */
    public function testRejectsUnsafeOrMissingNames(?string $name): void
    {
        $this->assertNull($this->store->path($name));
    }

    public static function rejectedNames(): array
    {
        return [
            'null'           => [null],
            'empty'          => [''],
            'traversal'      => ['../../config/database.php'],
            'traversal pdf'  => ['../secret.pdf'],
            'absolute'       => ['/etc/passwd'],
            'nested'         => ['sub/INV-100231.pdf'],
            'null byte'      => ["INV-100231.pdf\0.php"],
            'not pdf'        => ['database.php'],
            'missing'        => ['INV-999999.pdf'],
            'symlink escape' => ['link.pdf'],
        ];
    }
}
