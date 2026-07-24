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

    // -----------------------------------------------------------------------
    // Bulk Pair Import
    // -----------------------------------------------------------------------

    /**
     * Accept a JSON body (or form field `pairs_json`) containing an array of
     * pair descriptors and bulk-import them.
     *
     * Alternatively accepts a plain-text textarea `pairs_text` with one
     * "SYMBOL BASE QUOTE [market_type]" entry per line (space or comma separated).
     */
    public function importPairs(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $rows = [];

        // Try textarea input: one pair per line  "BTCUSDT BTC USDT spot"
        $rawText = trim((string)$request->input('pairs_text', ''));
        if ($rawText !== '') {
            foreach (preg_split('/\r?\n/', $rawText) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                // Accept comma or space separated
                $parts = preg_split('/[\s,]+/', $line) ?: [];
                if (count($parts) < 3) {
                    continue;
                }
                $rows[] = [
                    'symbol'      => strtoupper($parts[0]),
                    'base_code'   => strtoupper($parts[1]),
                    'quote_code'  => strtoupper($parts[2]),
                    'market_type' => isset($parts[3]) ? strtolower($parts[3]) : 'spot',
                ];
            }
        }

        // Try JSON field
        if ($rows === []) {
            $jsonRaw = trim((string)$request->input('pairs_json', ''));
            if ($jsonRaw !== '') {
                $decoded = json_decode($jsonRaw, true);
                if (is_array($decoded)) {
                    $rows = $decoded;
                }
            }
        }

        if ($rows === []) {
            Response::json(['ok' => false, 'message' => 'No pair data provided.'], 422);
            return;
        }

        // Shared defaults from form
        $defaults = [
            'market_type'        => trim((string)$request->input('market_type', 'spot')),
            'maker_fee_percent'  => $request->input('maker_fee_percent', '0.1'),
            'taker_fee_percent'  => $request->input('taker_fee_percent', '0.1'),
            'is_active'          => 1,
            'trading_enabled'    => 1,
            'is_visible'         => 1,
        ];

        try {
            $result = $this->svc()->bulkImportPairs($this->adminId(), $rows, $defaults);
            Response::json([
                'ok'      => true,
                'message' => "Import complete: {$result['created']} created, {$result['skipped']} skipped.",
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'errors'  => $result['errors'],
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
