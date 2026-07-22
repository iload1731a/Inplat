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
    private function svc(): UserOrdersService
    {
        return new UserOrdersService();
    }

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $service = $this->svc();

        try {
            $openOrders  = $service->openOrders($userId);
            $stats       = $service->stats($userId);
            $ordersByPair = $service->ordersByPair($userId);
            $monthlyStats = $service->monthlyStats($userId);
        } catch (Throwable) {
            $openOrders   = [];
            $stats        = [];
            $ordersByPair = [];
            $monthlyStats = [];
        }

        $this->userView('user/orders/index', [
            'title'        => 'Open Orders',
            'userSection'  => 'orders',
            'openOrders'   => $openOrders,
            'stats'        => $stats,
            'ordersByPair' => $ordersByPair,
            'monthlyStats' => $monthlyStats,
        ]);
    }

    public function history(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        $filters = [
            'pair'       => trim((string)$request->input('pair', '')),
            'status'     => trim((string)$request->input('status', '')),
            'side'       => trim((string)$request->input('side', '')),
            'order_type' => trim((string)$request->input('order_type', '')),
            'date_from'  => trim((string)$request->input('date_from', '')),
            'date_to'    => trim((string)$request->input('date_to', '')),
        ];

        try {
            $orders = $this->svc()->orderHistory($userId, $filters);
        } catch (Throwable) {
            $orders = [];
        }

        $this->userView('user/orders/history', [
            'title'       => 'Order History',
            'userSection' => 'orders-history',
            'orders'      => $orders,
            'filters'     => $filters,
        ]);
    }

    public function detail(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $orderId = (int)$request->input('id', 0);

        try {
            $data = $this->svc()->orderDetail($userId, $orderId);
        } catch (Throwable $e) {
            Session::put('flash.error', 'Order not found.');
            Response::redirect('/user/orders/history');
            return;
        }

        $this->userView('user/orders/detail', [
            'title'       => 'Order #' . $orderId,
            'userSection' => 'orders-history',
            'order'       => $data['order'],
            'trades'      => $data['trades'],
            'breadcrumb'  => [
                ['label' => 'Order History', 'url' => '/user/orders/history'],
                ['label' => 'Order #' . $orderId],
            ],
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
            $this->svc()->cancel($userId, $orderId);
            Response::json(['ok' => true, 'message' => 'Order cancelled successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function export(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        $filters = [
            'pair'       => trim((string)$request->input('pair', '')),
            'status'     => trim((string)$request->input('status', '')),
            'side'       => trim((string)$request->input('side', '')),
            'order_type' => trim((string)$request->input('order_type', '')),
            'date_from'  => trim((string)$request->input('date_from', '')),
            'date_to'    => trim((string)$request->input('date_to', '')),
        ];

        $orders = $this->svc()->exportData($userId, $filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="orders-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'UUID', 'Pair', 'Type', 'Side', 'Status', 'Quantity', 'Price', 'Filled', 'Avg Fill', 'Source', 'Created']);
        foreach ($orders as $o) {
            fputcsv($out, [
                $o['id'],         $o['order_uuid'] ?? '',  $o['pair_symbol'] ?? '',
                $o['order_type'] ?? '', $o['side'] ?? '', $o['status'] ?? '',
                $o['quantity'] ?? 0,  $o['price'] ?? 0,
                $o['filled_quantity'] ?? 0, $o['average_fill_price'] ?? 0,
                $o['source'] ?? '', $o['created_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }
}
