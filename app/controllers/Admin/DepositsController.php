<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Services\DepositService;
use Throwable;

/**
 * Admin Deposits Controller
 * Routes: /admin/deposits/*
 */
final class DepositsController extends AdminBaseController
{
    private function svc(): DepositService
    {
        return new DepositService();
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status'     => trim((string)$request->input('status',     '')),
            'search'     => trim((string)$request->input('search',     '')),
            'currency'   => trim((string)$request->input('currency',   '')),
            'type'       => trim((string)$request->input('type',       '')),
            'date_from'  => trim((string)$request->input('date_from',  '')),
            'date_to'    => trim((string)$request->input('date_to',    '')),
            'amount_min' => trim((string)$request->input('amount_min', '')),
            'amount_max' => trim((string)$request->input('amount_max', '')),
        ];

        $data = $this->svc()->adminDepositList($filters);

        $this->view('admin/deposits/index', [
            'title'        => 'Admin · Deposit Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'deposits',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // Detail
    // -------------------------------------------------------------------------

    public function detail(Request $request): void
    {
        $this->bootAdmin();
        $id = (int)$request->input('id', 0);

        try {
            $data = $this->svc()->adminDepositDetail($id);
        } catch (Throwable $e) {
            Session::put('flash.error', $e->getMessage());
            Response::redirect('/admin/deposits');
        }

        $this->view('admin/deposits/detail', [
            'title'        => 'Admin · Deposit #' . $id,
            'username'     => $this->adminUsername(),
            'adminSection' => 'deposits',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // Single review (credit / confirm / fail / flag)
    // -------------------------------------------------------------------------

    public function review(Request $request): void
    {
        $this->bootAdmin();

        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $depositId  = (int)$request->input('deposit_id', 0);
        $status     = trim((string)$request->input('status', ''));
        $flagReason = trim((string)$request->input('flag_reason', ''));

        try {
            $this->svc()->reviewDeposit($this->adminId(), $depositId, $status, $flagReason ?: null);
            Response::json(['ok' => true, 'message' => 'Deposit updated successfully.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Bulk credit
    // -------------------------------------------------------------------------

    public function bulkCredit(Request $request): void
    {
        $this->bootAdmin();

        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            $ids = [(int)$ids];
        }

        try {
            $result = $this->svc()->bulkCredit($this->adminId(), $ids);
            $msg    = "Credited {$result['credited']} deposit(s).";
            if (!empty($result['errors'])) {
                $msg .= ' Errors: ' . implode('; ', $result['errors']);
            }
            Response::json(['ok' => true, 'message' => $msg, 'credited' => $result['credited']]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Bulk flag
    // -------------------------------------------------------------------------

    public function bulkFlag(Request $request): void
    {
        $this->bootAdmin();

        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $ids    = $request->input('ids', []);
        $reason = trim((string)$request->input('reason', ''));

        if (!is_array($ids)) {
            $ids = [(int)$ids];
        }

        try {
            $flagged = $this->svc()->bulkFlag($this->adminId(), $ids, $reason);
            Response::json(['ok' => true, 'message' => "Flagged {$flagged} deposit(s).", 'flagged' => $flagged]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------

    public function reports(Request $request): void
    {
        $this->bootAdmin();

        $data = $this->svc()->adminReports();

        $this->view('admin/deposits/reports', [
            'title'        => 'Admin · Deposit Reports',
            'username'     => $this->adminUsername(),
            'adminSection' => 'deposits',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // CSV export
    // -------------------------------------------------------------------------

    public function export(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status'    => trim((string)$request->input('status',    '')),
            'search'    => trim((string)$request->input('search',    '')),
            'currency'  => trim((string)$request->input('currency',  '')),
            'type'      => trim((string)$request->input('type',      '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to',   '')),
        ];

        $this->svc()->exportCsv($filters);
    }

    // -------------------------------------------------------------------------
    // Gateway settings
    // -------------------------------------------------------------------------

    public function gateways(Request $request): void
    {
        $this->bootAdmin();

        $data = $this->svc()->adminGateways();

        $this->view('admin/deposits/gateways', [
            'title'        => 'Admin · Deposit Gateways',
            'username'     => $this->adminUsername(),
            'adminSection' => 'deposits',
            ...$data,
        ]);
    }

    public function updateGateway(Request $request): void
    {
        $this->bootAdmin();

        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $currencyId = (int)$request->input('currency_id', 0);
        $data       = [
            'is_deposit_enabled'     => (int)(bool)$request->input('is_deposit_enabled', 0),
            'network'                => trim((string)$request->input('network', '')),
            'confirmations_required' => max(1, (int)$request->input('confirmations_required', 1)),
        ];

        try {
            $this->svc()->updateGateway($this->adminId(), $currencyId, $data);
            Response::json(['ok' => true, 'message' => 'Deposit settings updated.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
