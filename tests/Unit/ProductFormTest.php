<?php

declare(strict_types=1);

namespace Usagi\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Usagi\ProductForm;

final class ProductFormTest extends TestCase
{
    private static function valid(array $override = []): array
    {
        return $override + [
            'sku'         => 'US-SN-003',
            'name'        => '小松菜クッキー 50g',
            'category'    => 'おやつ',
            'summary'     => '国産小松菜を練り込んだクッキー。',
            'description' => "1行目\r\n2行目",
            'price'       => '780',
            'list_price'  => '',
            'stock'       => '25',
            'tag'         => 'new',
        ];
    }

    public function testValidInputIsNormalized(): void
    {
        $form = new ProductForm(self::valid(['sku' => ' us-sn-003 ', 'price' => '１,２８０', 'list_price' => '1480']));
        self::assertSame([], $form->validate());
        self::assertSame([
            'sku'         => 'US-SN-003',
            'name'        => '小松菜クッキー 50g',
            'category'    => 'おやつ',
            'summary'     => '国産小松菜を練り込んだクッキー。',
            'description' => "1行目\n2行目",
            'price'       => 1280,
            'list_price'  => 1480,
            'stock'       => 25,
            'tag'         => 'new',
        ], $form->values());
    }

    public function testOptionalFieldsBecomeNull(): void
    {
        $values = (new ProductForm(self::valid(['tag' => '', 'list_price' => ''])))->values();
        self::assertNull($values['tag']);
        self::assertNull($values['list_price']);
    }

    public function testEmptyInputReportsEveryRequiredField(): void
    {
        $errors = (new ProductForm(['stock' => '']))->validate();
        self::assertCount(7, $errors);
    }

    public static function invalidInputs(): array
    {
        return [
            'sku with symbols'      => [['sku' => 'US_SN;003'], '商品コード'],
            'sku too long'          => [['sku' => str_repeat('A', 21)], '商品コード'],
            'name too long'         => [['name' => str_repeat('あ', 151)], '商品名'],
            'zero price'            => [['price' => '0'], '販売価格'],
            'negative price'        => [['price' => '-100'], '販売価格'],
            'decimal price'         => [['price' => '99.5'], '販売価格'],
            'list price not higher' => [['list_price' => '780'], '通常価格'],
            'negative stock'        => [['stock' => '-1'], '在庫数'],
            'unknown tag'           => [['tag' => 'hot'], 'タグ'],
            'array injected'        => [['name' => ['x']], '商品名'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputIsRejected(array $override, string $expected): void
    {
        $errors = (new ProductForm(self::valid($override)))->validate();
        self::assertCount(1, $errors);
        self::assertStringContainsString($expected, $errors[0]);
    }
}
