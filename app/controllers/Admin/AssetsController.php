<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminAssetsService;
use Throwable;

final class AssetsController extends AdminBaseController
{
    private function svc(): AdminAssetsService
    {
        return new AdminAssetsService();
    }

    // -----------------------------------------------------------------------
    // Assets / Currencies Page
    // -----------------------------------------------------------------------

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search'    => trim((string)$request->input('search', '')),
            'type'      => trim((string)$request->input('type', '')),
            'is_active' => $request->input('is_active', ''),
        ];

        $data = $this->svc()->assetsIndex($filters);

        $this->view('admin/assets/index', [
            'title'        => 'Admin · Markets & Assets',
            'username'     => $this->adminUsername(),
            'adminSection' => 'assets',
            ...$data,
        ]);
    }

    // -----------------------------------------------------------------------
    // Trading Pairs Page
    // -----------------------------------------------------------------------

    public function pairs(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search'      => trim((string)$request->input('search', '')),
            'market_type' => trim((string)$request->input('market_type', '')),
            'is_active'   => $request->input('is_active', ''),
        ];

        $data = $this->svc()->tradingPairsIndex($filters);

        $this->view('admin/assets/pairs', [
            'title'        => 'Admin · Trading Pairs',
            'username'     => $this->adminUsername(),
            'adminSection' => 'assets',
            ...$data,
        ]);
    }

    // -----------------------------------------------------------------------
    // Currency Actions
    // -----------------------------------------------------------------------

    public function createCurrency(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $id = $this->svc()->createCurrency($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Currency created.', 'redirect' => '/admin/assets']);
    }

    public function updateCurrency(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $currencyId = (int)$request->input('currency_id', 0);
        try {
            $this->svc()->updateCurrency($this->adminId(), $currencyId, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Currency updated.', 'redirect' => '/admin/assets']);
    }

    public function deleteCurrency(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $currencyId = (int)$request->input('currency_id', 0);
        try {
            $this->svc()->deleteCurrency($this->adminId(), $currencyId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Currency deleted.', 'redirect' => '/admin/assets']);
    }

    public function toggleCurrency(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $currencyId = (int)$request->input('currency_id', 0);
        $active     = (bool)(int)$request->input('is_active', '0');
        try {
            $this->svc()->toggleCurrency($this->adminId(), $currencyId, $active);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Currency status updated.']);
    }

    // -----------------------------------------------------------------------
    // Trading Pair Actions
    // -----------------------------------------------------------------------

    public function createPair(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->createTradingPair($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Trading pair created.', 'redirect' => '/admin/assets/pairs']);
    }

    public function updatePair(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $pairId = (int)$request->input('pair_id', 0);
        try {
            $this->svc()->updateTradingPair($this->adminId(), $pairId, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Trading pair updated.', 'redirect' => '/admin/assets/pairs']);
    }

    public function deletePair(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $pairId = (int)$request->input('pair_id', 0);
        try {
            $this->svc()->deleteTradingPair($this->adminId(), $pairId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Trading pair deleted.', 'redirect' => '/admin/assets/pairs']);
    }
}
