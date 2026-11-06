<?php

declare(strict_types=1);

namespace Usagi;

final class ProductForm
{
    public const TAGS = ['' => 'なし', 'new' => '新商品', 'sale' => 'セール'];
    public const MAX_PRICE = 9999999;
    public const MAX_STOCK = 99999;

    private const DEFAULTS = [
        'sku' => '', 'name' => '', 'category' => '', 'summary' => '', 'description' => '',
        'price' => '', 'list_price' => '', 'stock' => '0', 'tag' => '',
    ];

    /** @var array<string,string> */
    private array $input;

    /** @var list<string> */
    private array $errors = [];

    public function __construct(array $input = [])
    {
        $this->input = self::DEFAULTS;
        foreach (self::DEFAULTS as $key => $_) {
            if (isset($input[$key]) && is_scalar($input[$key])) {
                $this->input[$key] = trim((string) $input[$key]);
            }
        }
        $this->input['sku'] = strtoupper(mb_convert_kana($this->input['sku'], 'as', 'UTF-8'));
        foreach (['price', 'list_price', 'stock'] as $key) {
            $this->input[$key] = str_replace(',', '', mb_convert_kana($this->input[$key], 'n', 'UTF-8'));
        }
    }

    /** @return array<string,string> raw (trimmed) values for re-displaying the form */
    public function input(): array
    {
        return $this->input;
    }

    /** @return list<string> */
    public function validate(): array
    {
        $in = $this->input;
        $this->errors = [];

        if (!preg_match('/\A[A-Z0-9][A-Z0-9-]{0,19}\z/', $in['sku'])) {
            $this->errors[] = '商品コード（SKU）は半角英数字とハイフンで20文字以内で入力してください。';
        }
        $this->requireLength('name', '商品名', 150);
        $this->requireLength('category', 'カテゴリ', 50);
        $this->requireLength('summary', '概要', 255);
        $this->requireLength('description', '商品説明', 5000);

        $price = self::toInt($in['price']);
        if ($price === null || $price < 1 || $price > self::MAX_PRICE) {
            $this->errors[] = '販売価格は1〜' . number_format(self::MAX_PRICE) . '円の整数で入力してください。';
        }
        if ($in['list_price'] !== '') {
            $listPrice = self::toInt($in['list_price']);
            if ($listPrice === null || $listPrice < 1 || $listPrice > self::MAX_PRICE) {
                $this->errors[] = '通常価格は1〜' . number_format(self::MAX_PRICE) . '円の整数で入力してください。';
            } elseif ($price !== null && $listPrice <= $price) {
                $this->errors[] = '通常価格は販売価格より高い金額を入力してください。';
            }
        }
        $stock = self::toInt($in['stock']);
        if ($stock === null || $stock < 0 || $stock > self::MAX_STOCK) {
            $this->errors[] = '在庫数は0〜' . number_format(self::MAX_STOCK) . 'の整数で入力してください。';
        }
        if (!array_key_exists($in['tag'], self::TAGS)) {
            $this->errors[] = 'タグの指定が正しくありません。';
        }

        return $this->errors;
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }

    /** @return array{sku:string,name:string,category:string,summary:string,description:string,price:int,list_price:?int,stock:int,tag:?string} */
    public function values(): array
    {
        $in = $this->input;
        return [
            'sku'         => $in['sku'],
            'name'        => $in['name'],
            'category'    => $in['category'],
            'summary'     => $in['summary'],
            'description' => str_replace(["\r\n", "\r"], "\n", $in['description']),
            'price'       => (int) self::toInt($in['price']),
            'list_price'  => $in['list_price'] === '' ? null : self::toInt($in['list_price']),
            'stock'       => (int) self::toInt($in['stock']),
            'tag'         => $in['tag'] === '' ? null : $in['tag'],
        ];
    }

    private function requireLength(string $key, string $label, int $max): void
    {
        $value = $this->input[$key];
        if ($value === '' || mb_strlen($value) > $max) {
            $this->errors[] = $label . 'は' . $max . '文字以内で入力してください。';
        }
    }

    private static function toInt(string $value): ?int
    {
        return preg_match('/\A\d{1,9}\z/', $value) ? (int) $value : null;
    }
}
