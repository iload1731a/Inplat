<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Request;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserTradesService;
use Throwable;

final class TradesController extends BaseController
{
    private function svc(): UserTradesService
    {
        return new UserTradesService();
    }

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $service = $this->svc();

        $filters = [
            'pair'      => trim((string)$request->input('pair', '')),
            'side'      => trim((string)$request->input('side', '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];

        try {
            $trades       = $service->trades($userId, $filters);
            $stats        = $service->stats($userId);
            $volumeByPair = $service->volumeByPair($userId);
            $dailyVolume  = $service->dailyVolume($userId, 30);
            $monthlyStats = $service->monthlyStats($userId);
        } catch (Throwable) {
            $trades       = [];
            $stats        = [];
            $volumeByPair = [];
            $dailyVolume  = [];
            $monthlyStats = [];
        }

        $this->userView('user/trades/index', [
            'title'        => 'Trade History',
            'userSection'  => 'trades',
            'trades'       => $trades,
            'stats'        => $stats,
            'volumeByPair' => $volumeByPair,
            'dailyVolume'  => $dailyVolume,
            'monthlyStats' => $monthlyStats,
            'filters'      => $filters,
        ]);
    }

    public function export(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        $filters = [
            'pair'      => trim((string)$request->input('pair', '')),
            'side'      => trim((string)$request->input('side', '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];

        $trades = $this->svc()->exportData($userId, $filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="trades-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'UUID', 'Pair', 'Side', 'Price', 'Quantity', 'Quote Amount', 'Fee', 'Executed At']);
        foreach ($trades as $t) {
            fputcsv($out, [
                $t['id'],         $t['trade_uuid'] ?? '', $t['pair_symbol'] ?? '',
                $t['side'] ?? '', $t['price'] ?? 0,       $t['quantity'] ?? 0,
                $t['quote_amount'] ?? 0, $t['fee'] ?? 0, $t['executed_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }
}
