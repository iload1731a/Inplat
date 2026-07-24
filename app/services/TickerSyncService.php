<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticker;
use App\Models\TradingPair;
use App\Repositories\MarketsRepository;

final class TickerSyncService
{
    public function __construct(
        private readonly MarketsRepository $repo = new MarketsRepository(),
        private readonly BinanceService $binance = new BinanceService(),
    ) {}

    public function syncProviderPairs(int $providerId): array
    {
        $pairs = $this->repo->listActiveFeedPairsForProvider($providerId, 1000);
        if ($pairs === []) {
            return ['processed' => 0, 'updated' => 0, 'missing' => 0];
        }

        $remoteTickers = $this->binance->ticker24h();
        $remoteMap = [];
        foreach ($remoteTickers as $ticker) {
            $symbol = strtoupper((string)($ticker['symbol'] ?? ''));
            if ($symbol !== '') {
                $remoteMap[$symbol] = $ticker;
            }
        }

        $processed = 0;
        $updated = 0;
        $missing = 0;

        foreach ($pairs as $pair) {
            $processed++;
            $externalSymbol = TradingPair::toExternalSymbol((string)$pair['symbol']);
            $remote = $remoteMap[$externalSymbol] ?? null;
            if (!is_array($remote)) {
                $missing++;
                continue;
            }

            $tickerData = Ticker::fromBinance($remote);
            $this->repo->upsertTicker((int)$pair['id'], $tickerData);
            $this->repo->insertExternalTick((int)$pair['id'], $providerId, [
                'price' => $tickerData['last_price'],
                'volume_24h' => $tickerData['volume_24h'],
                'bid_price' => $tickerData['best_bid'],
                'ask_price' => $tickerData['best_ask'],
            ]);
            $updated++;
        }

        return ['processed' => $processed, 'updated' => $updated, 'missing' => $missing];
    }
}
