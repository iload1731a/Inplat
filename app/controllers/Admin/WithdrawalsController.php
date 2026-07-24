<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Services\WithdrawalService;
use Throwable;

/**
 * Admin Withdrawals Controller
 * Routes: /admin/withdrawals/*
 */
final class WithdrawalsController extends AdminBaseController
{
    private function svc(): WithdrawalService
    {
        return new WithdrawalService();
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status'         => trim((string)$request->input('status',       '')),
            'search'         => trim((string)$request->input('search',       '')),
            'currency'       => trim((string)$request->input('currency',     '')),
            'type'           => trim((string)$request->input('type',         '')),
            'manual_review'  => trim((string)$request->input('manual_review','')),
            'date_from'      => trim((string)$request->input('date_from',    '')),
            'date_to'        => trim((string)$request->input('date_to',      '')),
            'amount_min'     => trim((string)$request->input('amount_min',   '')),
            'amount_max'     => trim((string)$request->input('amount_max',   '')),
        ];

        $data = $this->svc()->adminWithdrawalList($filters);

        $this->view('admin/withdrawals/index', [
            'title'        => 'Admin · Withdrawal Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'withdrawals',
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
            $data = $this->svc()->adminWithdrawalDetail($id);
        } catch (Throwable $e) {
            Session::put('flash.error', $e->getMessage());
            Response::redirect('/admin/withdrawals');
        }

        $this->view('admin/withdrawals/detail', [
            'title'        => 'Admin · Withdrawal #' . $id,
            'username'     => $this->adminUsername(),
            'adminSection' => 'withdrawals',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // Single review (approve / reject / process / complete)
    // -------------------------------------------------------------------------

    public function review(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $withdrawalId    = (int)$request->input('withdrawal_id',   0);
        $status          = trim((string)$request->input('status',           'pending'));
        $txHash          = trim((string)$request->input('tx_hash',          ''))  ?: null;
        $rejectionReason = trim((string)$request->input('rejection_reason', ''))  ?: null;

        try {
            $this->svc()->reviewWithdrawal(
                $this->adminId(),
                $withdrawalId,
                $status,
                $txHash,
                $rejectionReason
            );
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Withdrawal updated successfully']);
    }

    // -------------------------------------------------------------------------
    // Bulk operations
    // -------------------------------------------------------------------------

    public function bulkApprove(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ids = array_map('intval', (array)$request->input('ids', []));

        try {
            $updated = $this->svc()->bulkApprove($this->adminId(), $ids);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json([
            'ok'      => true,
            'message' => count($updated ?? []) . ' withdrawal(s) approved',
            'updated' => $updated ?? [],
        ]);
    }

    public function bulkReject(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ids    = array_map('intval', (array)$request->input('ids', []));
        $reason = trim((string)$request->input('reason', ''));

        try {
            $updated = $this->svc()->bulkReject($this->adminId(), $ids, $reason);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json([
            'ok'      => true,
            'message' => count($updated ?? []) . ' withdrawal(s) rejected and refunded',
            'updated' => $updated ?? [],
        ]);
    }

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------

    public function reports(Request $request): void
    {
        $this->bootAdmin();

        $data = $this->svc()->adminReports();

        $this->view('admin/withdrawals/reports', [
            'title'        => 'Admin · Withdrawal Reports',
            'username'     => $this->adminUsername(),
            'adminSection' => 'withdrawals',
            ...$data,
        ]);
    }

    // -------------------------------------------------------------------------
    // CSV Export
    // -------------------------------------------------------------------------

    public function export(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status'    => trim((string)$request->input('status',    '')),
            'currency'  => trim((string)$request->input('currency',  '')),
            'type'      => trim((string)$request->input('type',      '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to',   '')),
        ];

        $csv = $this->svc()->exportCsv($filters);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="withdrawals-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-cache, no-store');
        echo $csv;
        exit;
    }

    // -------------------------------------------------------------------------
    // Payment Gateway / Currency Settings
    // -------------------------------------------------------------------------

    public function gateways(Request $request): void
    {
        $this->bootAdmin();

        $data = $this->svc()->gatewaySettings();

        $this->view('admin/withdrawals/gateways', [
            'title'        => 'Admin · Withdrawal Gateway Settings',
            'username'     => $this->adminUsername(),
            'adminSection' => 'withdrawals',
            ...$data,
        ]);
    }

    public function updateGateway(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $currencyId = (int)$request->input('currency_id', 0);

        try {
            $this->svc()->updateGatewaySettings($this->adminId(), $currencyId, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Gateway settings updated successfully']);
    }
}
