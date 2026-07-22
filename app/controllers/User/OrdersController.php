<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserOrdersService;
use Throwable;

final class OrdersController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $service = new UserOrdersService();

        try {
            $openOrders = $service->openOrders($userId);
            $stats      = $service->stats($userId);
        } catch (Throwable) {
            $openOrders = [];
            $stats      = [];
        }

        $this->userView('user/orders/index', [
            'title'       => 'Open Orders',
            'userSection' => 'orders',
            'openOrders'  => $openOrders,
            'stats'       => $stats,
        ]);
    }

    public function history(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $orders = (new UserOrdersService())->orderHistory($userId);
        } catch (Throwable) {
            $orders = [];
        }

        $this->userView('user/orders/history', [
            'title'       => 'Order History',
            'userSection' => 'orders-history',
            'orders'      => $orders,
        ]);
    }

    public function cancel(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $orderId = (int)$request->input('order_id');

        try {
            (new UserOrdersService())->cancel($userId, $orderId);
            Response::json(['ok' => true, 'message' => 'Order cancelled successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
