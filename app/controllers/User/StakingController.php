<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\StakingService;
use Throwable;

final class StakingController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $data = [
            'summary' => [],
            'pools' => [],
            'userStakes' => [],
            'recentRewards' => [],
            'stakingError' => null,
        ];

        try {
            $data = array_merge($data, (new StakingService())->snapshot($userId));
        } catch (Throwable $e) {
            $safeMessageRaw = preg_replace('/[\r\n\t]+/', ' ', $e->getMessage());
            $safeMessage = is_string($safeMessageRaw) ? $safeMessageRaw : 'unknown error';
            $logLine = '[' . date('c') . '] Staking page error: ' . $e::class . ' - ' . $safeMessage . PHP_EOL;
            $written = file_put_contents((string)config('app.log_file'), $logLine, FILE_APPEND | LOCK_EX);
            if ($written === false) {
                error_log($logLine);
            }
            $data['stakingError'] = 'Staking data is temporarily unavailable.';
        }

        $this->view('user/staking', [
            'title' => 'Staking',
            'username' => (string)(Session::get('auth.username') ?? 'Trader'),
            ...$data,
        ]);
    }

    public function snapshot(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $payload = (new StakingService())->snapshot($userId);
            Response::json(['ok' => true, 'data' => $payload]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => 'Staking data unavailable'], 422);
        }
    }
}
