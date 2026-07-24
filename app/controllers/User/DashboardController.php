<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserDashboardService;
use Throwable;

final class DashboardController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $data = [
            'overview' => [],
            'walletAllocation' => [],
            'recentOrders' => [],
            'recentTrades' => [],
            'pnlSeries' => [],
            'dashboardError' => null,
        ];

        try {
            $data = array_merge($data, (new UserDashboardService())->data($userId));
        } catch (Throwable $e) {
            $safeMessageRaw = preg_replace('/[\r\n\t]+/', ' ', $e->getMessage());
            $safeMessage = is_string($safeMessageRaw) ? $safeMessageRaw : 'unknown error';
            $logLine = '[' . date('c') . '] User dashboard metrics error: ' . $e::class . ' - ' . $safeMessage . PHP_EOL;
            $written = file_put_contents((string)config('app.log_file'), $logLine, FILE_APPEND | LOCK_EX);
            if ($written === false) {
                error_log($logLine);
            }
            $data['dashboardError'] = 'Dashboard data is temporarily unavailable. Please check the application log for details.';
        }

        $this->render('user/dashboard', [
            'title' => 'Dashboard',
            'userSection' => 'dashboard',
            'username' => (string)(Session::get('auth.username') ?? 'Trader'),
            ...$data,
        ]);
    }

    public function metrics(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $payload = (new UserDashboardService())->data($userId);
            Response::json(['ok' => true, 'data' => $payload]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => 'Dashboard metrics unavailable'], 422);
        }
    }
}
