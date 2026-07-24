<?php

declare(strict_types=1);

namespace App\Models;

final class MarketVolume
{
    public static function fromTicker(array $ticker): array
    {
        return [
            'base_volume_24h' => (string)($ticker['volume'] ?? '0'),
            'quote_volume_24h' => (string)($ticker['quoteVolume'] ?? '0'),
        ];
    }
}

