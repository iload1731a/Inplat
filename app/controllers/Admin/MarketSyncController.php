<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\MarketDataService;
use Throwable;

final class MarketSyncController extends AdminBaseController
{
    public function syncExchangeInfo(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $data = (new MarketDataService())->syncExchangeInfo($this->adminId());
            Response::json(['ok' => true, 'message' => 'Exchange symbols synchronized.', 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function syncTickers(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $data = (new MarketDataService())->syncTickers($this->adminId());
            Response::json(['ok' => true, 'message' => 'Ticker synchronization completed.', 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function syncCandles(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $interval = trim((string)$request->input('interval', '1h'));
        $limit = max(50, min(1000, (int)$request->input('limit', 300)));
        try {
            $data = (new MarketDataService())->syncCandles($this->adminId(), $interval, $limit);
            Response::json(['ok' => true, 'message' => 'Candlestick synchronization completed.', 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}

