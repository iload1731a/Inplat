<?php

declare(strict_types=1);

namespace App\Models;

final class TradingPair
{
    public static function toExternalSymbol(string $internalSymbol): string
    {
        return strtoupper(str_replace(['/', '-', '_'], '', trim($internalSymbol)));
    }

    public static function toInternalSymbol(string $base, string $quote): string
    {
        return strtoupper(trim($base)) . '/' . strtoupper(trim($quote));
    }
}
