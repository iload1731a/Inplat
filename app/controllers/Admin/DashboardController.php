<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Response;
use App\Libraries\Request;
use App\Middleware\AuthMiddleware;
use App\Services\AdminDashboardService;
use Throwable;

final class DashboardController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $data = [
            'overview' => [],
            'recentTrades' => [],
            'recentDeposits' => [],
            'recentWithdrawals' => [],
            'tradeVolumeSeries' => [],
            'userGrowthSeries' => [],
            'dashboardError' => null,
        ];

        try {
            $data = array_merge($data, (new AdminDashboardService())->data());
        } catch (Throwable $e) {
            $logLine = '[' . date('c') . '] Dashboard metrics error: ' . $e::class . PHP_EOL;
            $written = file_put_contents((string)config('app.log_file'), $logLine, FILE_APPEND | LOCK_EX);
            if ($written === false) {
                error_log($logLine);
            }
            $data['dashboardError'] = 'Dashboard metrics are temporarily unavailable. Please check the application log for details.';
        }

        $this->view('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'username' => (string)(\App\Libraries\Session::get('auth.username') ?? 'Admin'),
            ...$data,
        ]);
    }

    public function metrics(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        try {
            $payload = (new AdminDashboardService())->data();
            Response::json(['ok' => true, 'data' => $payload]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => 'Dashboard metrics unavailable'], 422);
        }
    }
}
