<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\MarketDataService;
use Throwable;

final class AdminExchangeController extends AdminBaseController
{
    public function binanceInfo(Request $request): void
    {
        $this->bootAdmin();
        try {
            $rows = (new MarketDataService())->rawExchangeInfoSample(50);
            Response::json(['ok' => true, 'data' => $rows]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}

