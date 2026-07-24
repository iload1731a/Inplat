<?php

declare(strict_types=1);

namespace App\Models;

final class Ticker
{
    public static function fromBinance(array $ticker): array
    {
        return [
            'last_price' => (string)($ticker['lastPrice'] ?? '0'),
            'best_bid' => isset($ticker['bidPrice']) ? (string)$ticker['bidPrice'] : null,
            'best_ask' => isset($ticker['askPrice']) ? (string)$ticker['askPrice'] : null,
            'change_24h_percent' => (string)($ticker['priceChangePercent'] ?? '0'),
            'high_24h' => isset($ticker['highPrice']) ? (string)$ticker['highPrice'] : null,
            'low_24h' => isset($ticker['lowPrice']) ? (string)$ticker['lowPrice'] : null,
            'volume_24h' => (string)($ticker['volume'] ?? '0'),
        ];
    }
}

