<?php

declare(strict_types=1);

namespace Usagi;

use InvalidArgumentException;

final class ShippingCalculator
{
    private const REGIONS = [
        'hokkaido' => ['label' => '北海道',     'fee' => 1200],
        'tohoku'   => ['label' => '東北',       'fee' => 880],
        'kanto'    => ['label' => '関東',       'fee' => 660],
        'chubu'    => ['label' => '中部・北陸', 'fee' => 770],
        'kansai'   => ['label' => '関西',       'fee' => 880],
        'chugoku'  => ['label' => '中国',       'fee' => 990],
        'shikoku'  => ['label' => '四国',       'fee' => 990],
        'kyushu'   => ['label' => '九州',       'fee' => 1100],
        'okinawa'  => ['label' => '沖縄',       'fee' => 1650],
    ];

    /** first two digits of the postal code => region */
    private const PREFIX_RANGES = [
        [0, 0, 'hokkaido'], [1, 3, 'tohoku'], [4, 9, 'hokkaido'],
        [10, 37, 'kanto'], [38, 50, 'chubu'], [51, 67, 'kansai'],
        [68, 75, 'chugoku'], [76, 79, 'shikoku'], [80, 89, 'kyushu'],
        [90, 90, 'okinawa'], [91, 95, 'chubu'], [96, 99, 'tohoku'],
    ];

    public function __construct(private int $freeThreshold = 5000)
    {
    }

    public static function normalizeZip(string $zip): string
    {
        $zip = mb_convert_kana(trim($zip), 'as', 'UTF-8');
        $zip = str_replace(['-', 'ー', '−', ' '], '', $zip);
        if (!preg_match('/\A\d{7}\z/', $zip)) {
            throw new InvalidArgumentException('郵便番号は7桁の数字で入力してください。');
        }
        return substr($zip, 0, 3) . '-' . substr($zip, 3);
    }

    public function regionFor(string $zip): string
    {
        $prefix = (int) substr(self::normalizeZip($zip), 0, 2);
        foreach (self::PREFIX_RANGES as [$from, $to, $region]) {
            if ($prefix >= $from && $prefix <= $to) {
                return $region;
            }
        }
        throw new InvalidArgumentException('配送対象外の郵便番号です。');
    }

    public function regionLabel(string $region): string
    {
        return self::REGIONS[$region]['label'];
    }

    public function baseFee(string $region): int
    {
        return self::REGIONS[$region]['fee'];
    }

    public function fee(string $zip, int $subtotal): int
    {
        $region = $this->regionFor($zip);
        if ($subtotal >= $this->freeThreshold && $region !== 'okinawa') {
            return 0;
        }
        return $this->baseFee($region);
    }

    public function freeThreshold(): int
    {
        return $this->freeThreshold;
    }
}
