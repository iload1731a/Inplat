<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Services\MarketDataService;

final class AdminMarketController extends AdminBaseController
{
    public function index(Request $request): void
    {
        $this->bootAdmin();
        $data = (new MarketDataService())->dashboard();
        $this->view('admin/markets/sync', [
            'title' => 'Admin · Market Data Sync',
            'username' => $this->adminUsername(),
            'adminSection' => 'markets',
            ...$data,
        ]);
    }
}
