<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminPositionsService;
use App\Services\AdminManagementService;
use App\Libraries\RequestContext;
use Throwable;

final class PositionsController extends AdminBaseController
{
    private function svc(): AdminPositionsService
    {
        return new AdminPositionsService();
    }

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search'    => trim((string)$request->input('search', '')),
            'status'    => trim((string)$request->input('status', '')),
            'side'      => trim((string)$request->input('side', '')),
            'symbol'    => trim((string)$request->input('symbol', '')),
            'user_id'   => (int)$request->input('user_id', 0),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];

        $data = $this->svc()->positionsIndex($filters);

        $this->view('admin/positions/index', [
            'title'        => 'Admin · Positions Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'positions',
            ...$data,
        ]);
    }

    public function risk(Request $request): void
    {
        $this->bootAdmin();

        $data = $this->svc()->riskData();

        $this->view('admin/positions/risk', [
            'title'        => 'Admin · Risk & Exposure',
            'username'     => $this->adminUsername(),
            'adminSection' => 'positions',
            ...$data,
        ]);
    }

    public function forceClose(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $positionId = (int)$request->input('position_id', 0);

        try {
            $this->svc()->forceClose($positionId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Position force-closed.']);
    }

    public function export(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search'    => trim((string)$request->input('search', '')),
            'status'    => trim((string)$request->input('status', '')),
            'side'      => trim((string)$request->input('side', '')),
            'symbol'    => trim((string)$request->input('symbol', '')),
            'user_id'   => (int)$request->input('user_id', 0),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];

        $positions = $this->svc()->exportCsv($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="positions-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Symbol', 'Market', 'User', 'Side', 'Status', 'Entry Price', 'Quantity', 'Leverage', 'Margin', 'Liq Price', 'Unrealized PnL', 'Realized PnL', 'Opened At', 'Closed At']);
        foreach ($positions as $p) {
            fputcsv($out, [
                $p['id'],             $p['symbol'] ?? '',       $p['market_type'] ?? '',
                $p['username'] ?? '', $p['side'] ?? '',         $p['status'] ?? '',
                $p['entry_price'] ?? 0, $p['quantity'] ?? 0,   $p['leverage'] ?? 1,
                $p['margin_used'] ?? 0, $p['liquidation_price'] ?? '',
                $p['unrealized_pnl'] ?? 0, $p['realized_pnl'] ?? 0,
                $p['opened_at'] ?? '', $p['closed_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }
}
