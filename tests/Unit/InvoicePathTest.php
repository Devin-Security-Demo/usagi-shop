<?php

declare(strict_types=1);

namespace Usagi\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/lib/helpers.php';

final class InvoicePathTest extends TestCase
{
    private const DIR = __DIR__ . '/../../storage/invoices';

    public function testReturnsPathForExistingInvoice(): void
    {
        self::assertSame(realpath(self::DIR . '/INV-100231.pdf'), invoice_path('INV-100231.pdf', self::DIR));
    }

    public function testRejectsTraversalAndUnexpectedNames(): void
    {
        foreach (['../../.env', '../../config/database.php', '/etc/passwd', 'sub/INV-100231.pdf', 'INV-999999.pdf', 'INV-100231.php', ''] as $file) {
            self::assertNull(invoice_path($file, self::DIR), $file);
        }
    }
}
