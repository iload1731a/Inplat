<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminNotificationsService;
use Throwable;

/**
 * Admin Notifications Controller
 *
 * Routes:
 *   GET  /admin/notifications               — Dashboard: KPIs, charts, recent activity
 *   GET  /admin/notifications/history       — Full dispatch log with filters / export
 *   GET  /admin/notifications/export        — CSV export of dispatch log
 *   GET  /admin/notifications/broadcast     — Broadcast UI (page) + recent broadcasts list
 *   POST /admin/notifications/broadcast     — Execute broadcast send
 *   GET  /admin/notifications/announcements — Announcement list
 *   POST /admin/notifications/announcements/create  — Create announcement
 *   POST /admin/notifications/announcements/update  — Update announcement
 *   POST /admin/notifications/announcements/delete  — Delete announcement
 *   POST /admin/notifications/announcements/toggle  — Toggle publish status
 *   GET  /admin/notifications/templates     — Email template list
 *   POST /admin/notifications/templates/save    — Create or update email template
 *   POST /admin/notifications/templates/delete  — Delete email template
 */
final class NotificationsController extends AdminBaseController
{
    private function svc(): AdminNotificationsService
    {
        return new AdminNotificationsService();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index(Request $request): void
    {
        $this->bootAdmin();

        try {
            $data = $this->svc()->dashboard();
        } catch (Throwable) {
            $data = [
                'kpis' => [], 'daily30' => [], 'channelBreakdown' => [],
                'typeBreakdown' => [], 'topRecipients' => [], 'recent' => [],
                'broadcasts' => ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 5, 'total_pages' => 0],
            ];
        }

        $this->view('admin/notifications/index', array_merge($data, [
            'title'        => 'Admin · Notification Center',
            'username'     => $this->adminUsername(),
            'adminSection' => 'notifications',
        ]));
    }

    // =========================================================================
    // NOTIFICATION LOG
    // =========================================================================

    public function history(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'user_id'   => trim((string)$request->input('user_id',   '')),
            'channel'   => trim((string)$request->input('channel',   '')),
            'status'    => trim((string)$request->input('status',    '')),
            'type'      => trim((string)$request->input('type',      '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to',   '')),
        ];
        $page = max(1, (int)$request->input('page', 1));

        try {
            $list = $this->svc()->historyList($filters, $page);
        } catch (Throwable) {
            $list = ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'total_pages' => 0];
        }

        $this->view('admin/notifications/history', [
            'title'        => 'Admin · Notification Log',
            'username'     => $this->adminUsername(),
            'adminSection' => 'notifications',
            'list'         => $list,
            'filters'      => $filters,
        ]);
    }

    public function export(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'channel'   => trim((string)$request->input('channel',   '')),
            'status'    => trim((string)$request->input('status',    '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to',   '')),
        ];

        $this->svc()->exportCsv($filters);
        exit;
    }

    // =========================================================================
    // BROADCAST
    // =========================================================================

    public function broadcast(Request $request): void
    {
        $this->bootAdmin();

        if ($request->method() === 'POST') {
            $this->requireCsrf($request);

            try {
                $result = $this->svc()->broadcast($this->adminId(), $request->all());
                Response::json([
                    'ok'      => true,
                    'message' => "Broadcast sent to {$result['recipients']} recipient(s). "
                                 . "Delivered: {$result['sent']}, Failed: {$result['failed']}.",
                    'redirect' => '/admin/notifications/broadcast',
                ]);
            } catch (Throwable $e) {
                Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            return;
        }

        // GET — show broadcast UI
        try {
            $page       = max(1, (int)$request->input('page', 1));
            $broadcasts = $this->svc()->broadcastList($page);
        } catch (Throwable) {
            $broadcasts = ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 30, 'total_pages' => 0];
        }

        $this->view('admin/notifications/broadcast', [
            'title'        => 'Admin · Broadcast Notifications',
            'username'     => $this->adminUsername(),
            'adminSection' => 'notifications',
            'broadcasts'   => $broadcasts,
        ]);
    }

    // =========================================================================
    // ANNOUNCEMENTS
    // =========================================================================

    public function announcements(Request $request): void
    {
        $this->bootAdmin();

        $page = max(1, (int)$request->input('page', 1));

        try {
            $list = $this->svc()->announcementList($page);
        } catch (Throwable) {
            $list = ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 30, 'total_pages' => 0];
        }

        $this->view('admin/notifications/announcements', [
            'title'        => 'Admin · Announcements',
            'username'     => $this->adminUsername(),
            'adminSection' => 'notifications',
            'list'         => $list,
        ]);
    }

    public function createAnnouncement(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $id = $this->svc()->createAnnouncement($this->adminId(), $request->all());
            Response::json(['ok' => true, 'message' => 'Announcement created.', 'id' => $id, 'redirect' => '/admin/notifications/announcements']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateAnnouncement(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $id = (int)$request->input('id');
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid announcement ID.'], 422);
            return;
        }

        try {
            $this->svc()->updateAnnouncement($this->adminId(), $id, $request->all());
            Response::json(['ok' => true, 'message' => 'Announcement updated.', 'redirect' => '/admin/notifications/announcements']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteAnnouncement(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $id = (int)$request->input('id');
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid announcement ID.'], 422);
            return;
        }

        try {
            $this->svc()->deleteAnnouncement($id);
            Response::json(['ok' => true, 'message' => 'Announcement deleted.', 'redirect' => '/admin/notifications/announcements']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function toggleAnnouncement(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $id = (int)$request->input('id');
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid announcement ID.'], 422);
            return;
        }

        try {
            $published = $this->svc()->toggleAnnouncementPublish($id);
            Response::json([
                'ok'        => true,
                'published' => $published,
                'message'   => $published ? 'Announcement published.' : 'Announcement unpublished.',
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // EMAIL TEMPLATES
    // =========================================================================

    public function templates(Request $request): void
    {
        $this->bootAdmin();

        try {
            $templates = $this->svc()->templateList();
        } catch (Throwable) {
            $templates = [];
        }

        // Load single template for editing if ?id= provided
        $editTemplate = null;
        $editId = (int)$request->input('id', 0);
        if ($editId > 0) {
            try {
                $editTemplate = $this->svc()->templateById($editId);
            } catch (Throwable) {}
        }

        $this->view('admin/notifications/templates', [
            'title'        => 'Admin · Email Templates',
            'username'     => $this->adminUsername(),
            'adminSection' => 'notifications',
            'templates'    => $templates,
            'editTemplate' => $editTemplate,
        ]);
    }

    public function saveTemplate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $id = $this->svc()->saveTemplate($this->adminId(), $request->all());
            Response::json(['ok' => true, 'message' => 'Template saved.', 'id' => $id, 'redirect' => '/admin/notifications/templates']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteTemplate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $id = (int)$request->input('id');
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid template ID.'], 422);
            return;
        }

        try {
            $this->svc()->deleteTemplate($id);
            Response::json(['ok' => true, 'message' => 'Template deleted.', 'redirect' => '/admin/notifications/templates']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
