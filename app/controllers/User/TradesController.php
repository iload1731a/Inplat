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
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $service = new UserTradesService();

        try {
            $trades       = $service->trades($userId);
            $stats        = $service->stats($userId);
            $volumeByPair = $service->volumeByPair($userId);
        } catch (Throwable) {
            $trades       = [];
            $stats        = [];
            $volumeByPair = [];
        }

        $this->userView('user/trades/index', [
            'title'        => 'Trade History',
            'userSection'  => 'trades',
            'trades'       => $trades,
            'stats'        => $stats,
            'volumeByPair' => $volumeByPair,
        ]);
    }
}
