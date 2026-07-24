<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Candle;
use App\Models\TradingPair;
use App\Repositories\MarketsRepository;

final class CandleSyncService
{
    public function __construct(
        private readonly MarketsRepository $repo = new MarketsRepository(),
        private readonly BinanceService $binance = new BinanceService(),
    ) {}

    public function syncProviderCandles(int $providerId, string $interval = '1h', int $limit = 300): array
    {
        $pairs = $this->repo->listActiveFeedPairsForProvider($providerId, 250);
        if ($pairs === []) {
            return ['pairs' => 0, 'candles' => 0, 'errors' => 0];
        }

        $pairsCount = 0;
        $candlesCount = 0;
        $errors = 0;

        foreach ($pairs as $pair) {
            $pairsCount++;
            try {
                $externalSymbol = TradingPair::toExternalSymbol((string)$pair['symbol']);
                $klines = $this->binance->klines($externalSymbol, $interval, $limit);
                foreach ($klines as $kline) {
                    if (!is_array($kline)) {
                        continue;
                    }
                    $candle = Candle::fromBinanceKline($kline);
                    $this->repo->upsertCandlestick(
                        (int)$pair['id'],
                        $interval,
                        (string)$candle['open_time'],
                        $candle
                    );
                    $candlesCount++;
                }
            } catch (\Throwable) {
                $errors++;
                continue;
            }
        }

        return ['pairs' => $pairsCount, 'candles' => $candlesCount, 'errors' => $errors];
    }
}
