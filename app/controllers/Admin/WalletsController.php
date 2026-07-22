<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminWalletsService;
use Throwable;

final class WalletsController extends AdminBaseController
{
    private function svc(): AdminWalletsService
    {
        return new AdminWalletsService();
    }

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search'    => trim((string)$request->input('search', '')),
            'currency'  => trim((string)$request->input('currency', '')),
            'is_frozen' => $request->input('is_frozen', ''),
        ];

        $data = $this->svc()->walletsIndex($filters);

        $this->view('admin/wallets/index', [
            'title'        => 'Admin · Wallets Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'wallets',
            ...$data,
        ]);
    }

    public function ledger(Request $request): void
    {
        $this->bootAdmin();
        $walletId = (int)$request->input('id', 0);

        try {
            $data = $this->svc()->walletLedger($walletId);
        } catch (Throwable $e) {
            Response::redirect('/admin/wallets');
        }

        $this->view('admin/wallets/ledger', [
            'title'        => 'Admin · Wallet Ledger',
            'username'     => $this->adminUsername(),
            'adminSection' => 'wallets',
            ...$data,
        ]);
    }

    public function freeze(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $walletId = (int)$request->input('wallet_id', 0);
        $reason   = trim((string)$request->input('reason', ''));

        try {
            $this->svc()->freezeWallet($this->adminId(), $walletId, $reason);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Wallet frozen.', 'redirect' => '/admin/wallets']);
    }

    public function unfreeze(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $walletId = (int)$request->input('wallet_id', 0);

        try {
            $this->svc()->unfreezeWallet($this->adminId(), $walletId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Wallet unfrozen.', 'redirect' => '/admin/wallets']);
    }
}
