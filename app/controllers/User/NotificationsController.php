<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserNotificationsService;
use Throwable;

final class NotificationsController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $service = new UserNotificationsService();

        try {
            $notifications = $service->all($userId);
            $unreadCount   = $service->unreadCount($userId);
        } catch (Throwable) {
            $notifications = [];
            $unreadCount   = 0;
        }

        $this->userView('user/notifications/index', [
            'title'         => 'Notifications',
            'userSection'   => 'notifications',
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
        ]);
    }

    public function markRead(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $id     = (int)$request->input('id');

        (new UserNotificationsService())->markRead($userId, $id);
        Response::json(['ok' => true]);
    }

    public function markAllRead(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        (new UserNotificationsService())->markAllRead($userId);
        Response::json(['ok' => true, 'message' => 'All notifications marked as read']);
    }

    public function delete(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $id     = (int)$request->input('id');

        (new UserNotificationsService())->delete($userId, $id);
        Response::json(['ok' => true, 'message' => 'Notification deleted']);
    }
}
