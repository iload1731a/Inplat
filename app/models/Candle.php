<?php

declare(strict_types=1);

namespace App\Models;

final class Candle
{
    public static function fromBinanceKline(array $kline): array
    {
        return [
            'open_time' => gmdate('Y-m-d H:i:s', (int)floor(((int)($kline[0] ?? 0)) / 1000)),
            'open_price' => (string)($kline[1] ?? '0'),
            'high_price' => (string)($kline[2] ?? '0'),
            'low_price' => (string)($kline[3] ?? '0'),
            'close_price' => (string)($kline[4] ?? '0'),
            'volume' => (string)($kline[5] ?? '0'),
            'quote_volume' => (string)($kline[7] ?? '0'),
            'trade_count' => (int)($kline[8] ?? 0),
        ];
    }
}
