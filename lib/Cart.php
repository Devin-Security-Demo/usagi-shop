<?php

declare(strict_types=1);

namespace Usagi;

final class Cart
{
    public const MAX_QTY = 20;
    public const TAX_RATE = 10;

    /** @var array<int,int> product id => quantity */
    private array $items = [];

    public function __construct(array $items = [])
    {
        foreach ($items as $productId => $qty) {
            $this->set((int) $productId, (int) $qty);
        }
    }

    public function add(int $productId, int $qty = 1): void
    {
        $this->set($productId, ($this->items[$productId] ?? 0) + $qty);
    }

    public function set(int $productId, int $qty): void
    {
        if ($productId <= 0) {
            return;
        }
        if ($qty <= 0) {
            unset($this->items[$productId]);
            return;
        }
        $this->items[$productId] = min($qty, self::MAX_QTY);
    }

    public function remove(int $productId): void
    {
        unset($this->items[$productId]);
    }

    public function clear(): void
    {
        $this->items = [];
    }

    /** @return array<int,int> */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return array_sum($this->items);
    }

    /**
     * @param array<int,array{id:int|string,name:string,price:int|string}> $products keyed by product id
     * @return list<array{product_id:int,name:string,price:int,qty:int,line_total:int,product:array}>
     */
    public function lines(array $products): array
    {
        $lines = [];
        foreach ($this->items as $productId => $qty) {
            if (!isset($products[$productId])) {
                continue;
            }
            $product = $products[$productId];
            $price = (int) $product['price'];
            $lines[] = [
                'product_id' => $productId,
                'name'       => (string) $product['name'],
                'price'      => $price,
                'qty'        => $qty,
                'line_total' => $price * $qty,
                'product'    => $product,
            ];
        }
        return $lines;
    }

    public static function subtotal(array $lines): int
    {
        return array_sum(array_column($lines, 'line_total'));
    }

    public static function includedTax(int $amount): int
    {
        return intdiv($amount * self::TAX_RATE, 100 + self::TAX_RATE);
    }
}
