<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminOrdersService;
use Throwable;

final class OrdersController extends AdminBaseController
{
    private function svc(): AdminOrdersService
    {
        return new AdminOrdersService();
    }

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search'     => trim((string)$request->input('search', '')),
            'status'     => trim((string)$request->input('status', '')),
            'side'       => trim((string)$request->input('side', '')),
            'order_type' => trim((string)$request->input('order_type', '')),
            'user_id'    => (int)$request->input('user_id', 0),
            'date_from'  => trim((string)$request->input('date_from', '')),
            'date_to'    => trim((string)$request->input('date_to', '')),
        ];

        $data = $this->svc()->ordersIndex($filters);

        $this->view('admin/orders/index', [
            'title'        => 'Admin · Orders Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'orders',
            ...$data,
        ]);
    }

    public function detail(Request $request): void
    {
        $this->bootAdmin();
        $orderId = (int)$request->input('id', 0);

        try {
            $data = $this->svc()->orderDetail($orderId);
        } catch (Throwable $e) {
            Response::redirect('/admin/orders');
        }

        $this->view('admin/orders/detail', [
            'title'        => 'Admin · Order Detail',
            'username'     => $this->adminUsername(),
            'adminSection' => 'orders',
            ...$data,
        ]);
    }

    public function cancelOrder(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $orderId = (int)$request->input('order_id', 0);
        $reason  = trim((string)$request->input('reason', ''));

        try {
            $this->svc()->cancelOrder($this->adminId(), $orderId, $reason);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Order cancelled.', 'redirect' => '/admin/orders']);
    }

    public function bulkCancel(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $orderIds = array_filter(array_map('intval', (array)$request->input('order_ids', [])));
        $reason   = trim((string)$request->input('reason', 'Bulk cancel by admin'));

        try {
            $count = $this->svc()->bulkCancelOrders($this->adminId(), $orderIds, $reason);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => $count . ' order(s) cancelled.', 'redirect' => '/admin/orders']);
    }
}
