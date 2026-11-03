<?php

declare(strict_types=1);

namespace Usagi\Tests;

use PHPUnit\Framework\TestCase;
use Usagi\Cart;

final class CartTest extends TestCase
{
    private const PRODUCTS = [
        1 => ['id' => 1, 'name' => 'にんじん', 'price' => 680],
        2 => ['id' => 2, 'name' => 'チモシー', 'price' => 1480],
        3 => ['id' => 3, 'name' => 'ペレット', 'price' => 2380],
    ];

    public function testAddAccumulatesQuantity(): void
    {
        $cart = new Cart();
        $cart->add(1);
        $cart->add(1, 2);
        $cart->add(2);

        self::assertSame([1 => 3, 2 => 1], $cart->items());
        self::assertSame(4, $cart->count());
    }

    public function testSetZeroRemovesItemAndQuantityIsCapped(): void
    {
        $cart = new Cart([1 => 2, 2 => 1]);
        $cart->set(1, 0);
        $cart->set(2, 500);

        self::assertSame([2 => Cart::MAX_QTY], $cart->items());
    }

    public function testInvalidProductIdsAreIgnored(): void
    {
        $cart = new Cart([0 => 1, -3 => 2]);
        self::assertTrue($cart->isEmpty());
    }

    public function testLinesAndSubtotal(): void
    {
        $cart = new Cart([1 => 2, 3 => 1]);
        $lines = $cart->lines(self::PRODUCTS);

        self::assertCount(2, $lines);
        self::assertSame(1360, $lines[0]['line_total']);
        self::assertSame(2380, $lines[1]['line_total']);
        self::assertSame(3740, Cart::subtotal($lines));
    }

    public function testLinesSkipUnknownProducts(): void
    {
        $cart = new Cart([1 => 1, 99 => 4]);
        self::assertSame(680, Cart::subtotal($cart->lines(self::PRODUCTS)));
    }

    public function testIncludedTax(): void
    {
        self::assertSame(0, Cart::includedTax(0));
        self::assertSame(100, Cart::includedTax(1100));
        self::assertSame(340, Cart::includedTax(3740));
    }

    public function testRemoveAndClear(): void
    {
        $cart = new Cart([1 => 1, 2 => 2]);
        $cart->remove(1);
        self::assertSame([2 => 2], $cart->items());
        $cart->clear();
        self::assertSame(0, $cart->count());
    }
}
