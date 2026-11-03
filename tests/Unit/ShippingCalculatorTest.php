<?php

declare(strict_types=1);

namespace Usagi\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Usagi\ShippingCalculator;

final class ShippingCalculatorTest extends TestCase
{
    public static function regions(): array
    {
        return [
            'tokyo'    => ['150-0001', 'kanto', 660],
            'osaka'    => ['530-0001', 'kansai', 880],
            'sapporo'  => ['060-0001', 'hokkaido', 1200],
            'sendai'   => ['980-0811', 'tohoku', 880],
            'nagoya'   => ['460-0008', 'chubu', 770],
            'hiroshima' => ['730-0011', 'chugoku', 990],
            'matsuyama' => ['790-0001', 'shikoku', 990],
            'fukuoka'  => ['810-0001', 'kyushu', 1100],
            'naha'     => ['900-0001', 'okinawa', 1650],
        ];
    }

    #[DataProvider('regions')]
    public function testRegionAndBaseFee(string $zip, string $region, int $fee): void
    {
        $calc = new ShippingCalculator(5000);
        self::assertSame($region, $calc->regionFor($zip));
        self::assertSame($fee, $calc->fee($zip, 1000));
    }

    public function testFreeShippingOverThreshold(): void
    {
        $calc = new ShippingCalculator(5000);
        self::assertSame(660, $calc->fee('150-0001', 4999));
        self::assertSame(0, $calc->fee('150-0001', 5000));
    }

    public function testOkinawaIsNeverFree(): void
    {
        self::assertSame(1650, (new ShippingCalculator(5000))->fee('900-0001', 20000));
    }

    public function testZipNormalization(): void
    {
        self::assertSame('150-0001', ShippingCalculator::normalizeZip('1500001'));
        self::assertSame('150-0001', ShippingCalculator::normalizeZip('１５０－０００１'));
        self::assertSame('150-0001', ShippingCalculator::normalizeZip(' 150-0001 '));
    }

    public function testInvalidZipIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ShippingCalculator::normalizeZip('15-0001');
    }
}
