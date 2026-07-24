<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Services\AdminWalletsService;
use Throwable;

final class WalletsController extends AdminBaseController
{
    private function svc(): AdminWalletsService
    {
        return new AdminWalletsService();
    }

    // -------------------------------------------------------------------------
    // Wallets list
    // -------------------------------------------------------------------------

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search'      => trim((string)$request->input('search', '')),
            'currency'    => trim((string)$request->input('currency', '')),
            'is_frozen'   => $request->input('is_frozen', ''),
            'wallet_type' => trim((string)$request->input('wallet_type', '')),
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
            Session::put('flash.error', 'Wallet not found or could not be loaded.');
            Response::redirect('/admin/wallets');
        }

        $this->view('admin/wallets/ledger', [
            'title'        => 'Admin · Wallet Ledger',
            'username'     => $this->adminUsername(),
            'adminSection' => 'wallets',
            ...$data,
        ]);
    }

    public function lookup(Request $request): void
    {
        $this->bootAdmin();
        $walletId = (int)$request->input('id', 0);

        try {
            $wallet = $this->svc()->walletLookup($walletId);
            Response::json(['ok' => true, 'wallet' => $wallet]);
            return;
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        }
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

    // -------------------------------------------------------------------------
    // Deposits management
    // -------------------------------------------------------------------------

    public function deposits(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status' => trim((string)$request->input('status', '')),
            'search' => trim((string)$request->input('search', '')),
        ];

        $data = $this->svc()->depositsIndex($filters);

        $this->view('admin/wallets/deposits', [
            'title'        => 'Admin · Deposits Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'deposits',
            ...$data,
        ]);
    }

    public function reviewDeposit(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $depositId  = (int)$request->input('deposit_id', 0);
        $status     = trim((string)$request->input('status', 'pending'));
        $flagReason = trim((string)$request->input('flag_reason', '')) ?: null;

        try {
            $this->svc()->reviewDeposit($this->adminId(), $depositId, $status, $flagReason);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Deposit updated successfully']);
    }

    // -------------------------------------------------------------------------
    // Withdrawals management
    // -------------------------------------------------------------------------

    public function withdrawals(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status' => trim((string)$request->input('status', '')),
            'search' => trim((string)$request->input('search', '')),
        ];

        $data = $this->svc()->withdrawalsIndex($filters);

        $this->view('admin/wallets/withdrawals', [
            'title'        => 'Admin · Withdrawals Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'withdrawals',
            ...$data,
        ]);
    }

    public function reviewWithdrawal(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $withdrawalId     = (int)$request->input('withdrawal_id', 0);
        $status           = trim((string)$request->input('status', 'pending'));
        $txHash           = trim((string)$request->input('tx_hash', ''))           ?: null;
        $rejectionReason  = trim((string)$request->input('rejection_reason', ''))  ?: null;

        try {
            $this->svc()->reviewWithdrawal($this->adminId(), $withdrawalId, $status, $txHash, $rejectionReason);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Withdrawal updated successfully']);
    }

    // -------------------------------------------------------------------------
    // Manual balance adjustment
    // -------------------------------------------------------------------------

    public function adjustment(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->adjustmentHistory();

        $this->view('admin/wallets/adjustment', [
            'title'        => 'Admin · Manual Adjustments',
            'username'     => $this->adminUsername(),
            'adminSection' => 'wallets',
            'history'      => $data,
        ]);
    }

    public function doAdjustment(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $walletId  = (int)$request->input('wallet_id', 0);
        $direction = trim((string)$request->input('direction', ''));
        $amount    = trim((string)$request->input('amount', '0'));
        $notes     = trim((string)$request->input('notes', ''));

        try {
            $this->svc()->manualAdjustment($this->adminId(), $walletId, $direction, $amount, $notes);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Balance adjustment applied successfully']);
    }

    // -------------------------------------------------------------------------
    // Transaction monitoring rules
    // -------------------------------------------------------------------------

    public function monitoring(Request $request): void
    {
        $this->bootAdmin();
        $rules = $this->svc()->monitoringRules();

        $this->view('admin/wallets/monitoring', [
            'title'        => 'Admin · Transaction Monitoring',
            'username'     => $this->adminUsername(),
            'adminSection' => 'wallets',
            'rules'        => $rules,
        ]);
    }

    public function createRule(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $id = $this->svc()->createMonitoringRule($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Monitoring rule created', 'id' => $id ?? 0]);
    }

    public function toggleRule(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ruleId = (int)$request->input('rule_id', 0);
        $active = (bool)$request->input('active', false);

        try {
            $this->svc()->toggleMonitoringRule($this->adminId(), $ruleId, $active);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Rule updated']);
    }

    public function deleteRule(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ruleId = (int)$request->input('rule_id', 0);

        try {
            $this->svc()->deleteMonitoringRule($this->adminId(), $ruleId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Rule deleted']);
    }
}
