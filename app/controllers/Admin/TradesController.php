<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Services\AdminTradesService;
use Throwable;

final class TradesController extends AdminBaseController
{
    private function svc(): AdminTradesService
    {
        return new AdminTradesService();
    }

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search'    => trim((string)$request->input('search', '')),
            'symbol'    => trim((string)$request->input('symbol', '')),
            'user_id'   => (int)$request->input('user_id', 0),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];

        $data = $this->svc()->tradesIndex($filters);

        $this->view('admin/trades/index', [
            'title'        => 'Admin · Trades Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'trades',
            ...$data,
        ]);
    }

    public function reports(Request $request): void
    {
        $this->bootAdmin();

        $data = $this->svc()->reportsData();

        $this->view('admin/trades/reports', [
            'title'        => 'Admin · Trade Reports',
            'username'     => $this->adminUsername(),
            'adminSection' => 'trades',
            ...$data,
        ]);
    }

    public function export(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search'    => trim((string)$request->input('search', '')),
            'symbol'    => trim((string)$request->input('symbol', '')),
            'user_id'   => (int)$request->input('user_id', 0),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];

        $trades = $this->svc()->exportCsv($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="trades-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'UUID', 'Symbol', 'Buyer', 'Seller', 'Price', 'Quantity', 'Quote Amount', 'Buyer Fee', 'Seller Fee', 'Maker Side', 'Executed At']);
        foreach ($trades as $t) {
            fputcsv($out, [
                $t['id'], $t['trade_uuid'] ?? '', $t['symbol'] ?? '',
                $t['buyer_username'] ?? '', $t['seller_username'] ?? '',
                $t['price'] ?? 0, $t['quantity'] ?? 0, $t['quote_amount'] ?? 0,
                $t['buyer_fee'] ?? 0, $t['seller_fee'] ?? 0,
                $t['maker_side'] ?? '', $t['executed_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }
}
