<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\NotificationsService;
use Throwable;

/**
 * User Notifications Controller
 *
 * Routes:
 *   GET  /user/notifications               — Notification center (tabbed, paginated)
 *   GET  /user/notifications/preferences   — Channel preference settings per category
 *   POST /user/notifications/preferences   — Save preferences
 *   GET  /user/notifications/history       — Full history with filters
 *   POST /user/notifications/read          — Mark single notification read (AJAX)
 *   POST /user/notifications/read-all      — Mark all read (AJAX)
 *   POST /user/notifications/read-type     — Mark all of a type read (AJAX)
 *   POST /user/notifications/delete        — Delete single notification (AJAX)
 *   POST /user/notifications/delete-read   — Delete all read notifications
 *   GET  /user/notifications/poll          — Unread count polling endpoint (AJAX/JSON)
 *   POST /user/notifications/announcement-read — Mark announcement read (AJAX)
 *   POST /user/notifications/push-register — Register push device token
 *   POST /user/notifications/push-deregister — Deregister push device token
 */
final class NotificationsController extends BaseController
{
    private function svc(): NotificationsService
    {
        return new NotificationsService();
    }

    // =========================================================================
    // NOTIFICATION CENTER
    // =========================================================================

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $svc    = $this->svc();

        $tab        = trim((string)$request->input('tab', 'all'));
        $readFilter = trim((string)$request->input('read', 'all'));
        $page       = max(1, (int)$request->input('page', 1));

        // Map tab to type filter
        $typeFilter = match($tab) {
            'trading'  => 'order_filled',
            'finance'  => 'deposit',
            'security' => 'security',
            'system'   => 'announcement',
            default    => '',
        };

        try {
            $center  = $svc->centerData($userId);
            $history = $svc->history($userId, $page, 20, $typeFilter, $readFilter);
        } catch (Throwable) {
            $center  = ['stats30d' => [], 'unreadByType' => [], 'unreadCount' => 0,
                        'announcements' => [], 'unreadAnnounce' => 0, 'recentByDay' => []];
            $history = ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 0];
        }

        $this->userView('user/notifications/index', array_merge($center, [
            'title'       => 'Notification Center',
            'userSection' => 'notifications',
            'tab'         => $tab,
            'readFilter'  => $readFilter,
            'history'     => $history,
            'breadcrumb'  => [['label' => 'Notifications']],
        ]));
    }

    // =========================================================================
    // PREFERENCES
    // =========================================================================

    public function preferences(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $preferences = $this->svc()->getPreferences($userId);
        } catch (Throwable) {
            $preferences = [];
        }

        $this->userView('user/notifications/preferences', [
            'title'       => 'Notification Preferences',
            'userSection' => 'notifications',
            'preferences' => $preferences,
            'breadcrumb'  => [
                ['label' => 'Notifications', 'url' => '/user/notifications'],
                ['label' => 'Preferences'],
            ],
        ]);
    }

    public function savePreferences(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
            return;
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        try {
            $this->svc()->savePreferences($userId, $request->all());
            Response::redirect('/user/notifications/preferences?saved=1');
        } catch (Throwable $e) {
            Response::redirect('/user/notifications/preferences?error=' . urlencode($e->getMessage()));
        }
    }

    // =========================================================================
    // HISTORY
    // =========================================================================

    public function history(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        $typeFilter = trim((string)$request->input('type', ''));
        $readFilter = trim((string)$request->input('read', ''));
        $page       = max(1, (int)$request->input('page', 1));

        try {
            $history     = $this->svc()->history($userId, $page, 50, $typeFilter, $readFilter);
            $unreadCount = $this->svc()->unreadCount($userId);
        } catch (Throwable) {
            $history     = ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'total_pages' => 0];
            $unreadCount = 0;
        }

        $this->userView('user/notifications/history', [
            'title'       => 'Notification History',
            'userSection' => 'notifications',
            'history'     => $history,
            'unreadCount' => $unreadCount,
            'typeFilter'  => $typeFilter,
            'readFilter'  => $readFilter,
            'breadcrumb'  => [
                ['label' => 'Notifications', 'url' => '/user/notifications'],
                ['label' => 'History'],
            ],
        ]);
    }

    // =========================================================================
    // AJAX ACTIONS
    // =========================================================================

    public function markRead(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
            return;
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $id     = (int)$request->input('id');
        $this->svc()->markRead($userId, $id);
        Response::json(['ok' => true, 'unread_count' => $this->svc()->unreadCount($userId)]);
    }

    public function markAllRead(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
            return;
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $this->svc()->markAllRead($userId);
        Response::json(['ok' => true, 'message' => 'All notifications marked as read', 'unread_count' => 0]);
    }

    public function markTypeRead(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
            return;
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $type   = trim((string)$request->input('type', ''));
        if ($type !== '') {
            $this->svc()->markTypeRead($userId, $type);
        }
        Response::json(['ok' => true, 'unread_count' => $this->svc()->unreadCount($userId)]);
    }

    public function delete(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
            return;
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $id     = (int)$request->input('id');
        $this->svc()->delete($userId, $id);
        Response::json(['ok' => true, 'message' => 'Notification deleted']);
    }

    public function deleteAllRead(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
            return;
        }

        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $deleted = $this->svc()->deleteAllRead($userId);
        Response::json(['ok' => true, 'message' => "Deleted {$deleted} read notification(s)."]);
    }

    /** AJAX unread count poll — returns JSON for badge refresh */
    public function poll(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);
        try {
            $count = $this->svc()->unreadCount($userId);
        } catch (Throwable) {
            $count = 0;
        }
        Response::json(['ok' => true, 'unread_count' => $count]);
    }

    public function announcementRead(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
            return;
        }

        $userId         = (int)(Session::get('auth.user_id') ?? 0);
        $announcementId = (int)$request->input('id');
        if ($announcementId > 0) {
            $this->svc()->markAnnouncementRead($userId, $announcementId);
        }
        Response::json(['ok' => true]);
    }

    public function pushRegister(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
            return;
        }

        $userId   = (int)(Session::get('auth.user_id') ?? 0);
        $token    = trim((string)$request->input('token', ''));
        $platform = trim((string)$request->input('platform', 'web'));

        if ($token === '') {
            Response::json(['ok' => false, 'message' => 'Device token required.'], 422);
            return;
        }

        $this->svc()->registerPushDevice($userId, $token, $platform);
        Response::json(['ok' => true, 'message' => 'Push device registered.']);
    }

    public function pushDeregister(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
            return;
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $token  = trim((string)$request->input('token', ''));
        if ($token !== '') {
            $this->svc()->deregisterPushDevice($userId, $token);
        }
        Response::json(['ok' => true, 'message' => 'Push device removed.']);
    }
}
