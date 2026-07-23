<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminTicketsService;
use Throwable;

/**
 * Admin Tickets Controller
 *
 * Routes:
 *   GET  /admin/tickets                    — Dashboard: KPI cards, 30-day chart, recent tickets
 *   GET  /admin/tickets/list               — Paginated, filterable ticket list
 *   GET  /admin/tickets/detail             — Ticket detail (conversation + notes + assignment)
 *   POST /admin/tickets/update             — Update status / priority / assignment / category
 *   POST /admin/tickets/reply              — Admin reply to ticket (with optional attachment)
 *   POST /admin/tickets/note               — Add internal note
 *   POST /admin/tickets/bulk               — Bulk status update or bulk assign
 *   GET  /admin/tickets/categories         — Category management
 *   POST /admin/tickets/categories/create  — Create category
 *   POST /admin/tickets/categories/update  — Update category
 *   POST /admin/tickets/categories/delete  — Delete category
 *   GET  /admin/tickets/analytics          — Analytics: resolution time, CSAT, SLA breaches
 *   GET  /admin/tickets/export             — CSV export
 *   POST /admin/tickets/tags/create        — Create tag
 *   POST /admin/tickets/tags/update        — Update ticket tags
 */
final class TicketsController extends AdminBaseController
{
    private function svc(): AdminTicketsService
    {
        return new AdminTicketsService();
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
            $data = ['kpi' => [], 'daily' => [], 'categories' => [], 'priorities' => [],
                     'agents' => [], 'csat' => [], 'recent' => []];
        }

        $this->view('admin/tickets/index', array_merge($data, [
            'title'        => 'Admin · Support Tickets',
            'username'     => $this->adminUsername(),
            'adminSection' => 'tickets',
        ]));
    }

    // =========================================================================
    // TICKET LIST
    // =========================================================================

    public function list(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status'    => trim((string)$request->input('status', '')),
            'priority'  => trim((string)$request->input('priority', '')),
            'category'  => trim((string)$request->input('category', '')),
            'assigned'  => trim((string)$request->input('assigned', '')),
            'search'    => trim((string)$request->input('search', '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];
        $page = max(1, (int)$request->input('page', 1));

        try {
            $data = $this->svc()->ticketList($filters, $page);
        } catch (Throwable) {
            $data = ['rows' => [], 'total' => 0, 'page' => 1, 'perPage' => 30, 'totalPages' => 1,
                     'categories' => [], 'admins' => [], 'filters' => $filters];
        }

        $this->view('admin/tickets/list', array_merge($data, [
            'title'        => 'Admin · Ticket List',
            'username'     => $this->adminUsername(),
            'adminSection' => 'tickets',
        ]));
    }

    // =========================================================================
    // TICKET DETAIL
    // =========================================================================

    public function detail(Request $request): void
    {
        $this->bootAdmin();

        $ticketId = (int)$request->input('id');
        if ($ticketId <= 0) {
            Response::redirect('/admin/tickets');
        }

        try {
            $data = $this->svc()->ticketDetail($ticketId);
        } catch (Throwable $e) {
            Response::redirect('/admin/tickets?error=' . urlencode($e->getMessage()));
        }

        $this->view('admin/tickets/detail', array_merge($data, [
            'title'        => 'Admin · Ticket #' . $ticketId,
            'username'     => $this->adminUsername(),
            'adminSection' => 'tickets',
        ]));
    }

    // =========================================================================
    // UPDATE TICKET (status, priority, assignment, category)
    // =========================================================================

    public function update(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ticketId = (int)$request->input('ticket_id');
        if ($ticketId <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid ticket ID'], 422);
        }

        $input = [];
        foreach (['status', 'priority', 'category', 'assigned_to'] as $field) {
            $val = $request->input($field);
            if ($val !== null) {
                $input[$field] = $val;
            }
        }

        try {
            $this->svc()->updateTicket($ticketId, $input);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Ticket updated',
                        'redirect' => '/admin/tickets/detail?id=' . $ticketId]);
    }

    // =========================================================================
    // ADMIN REPLY
    // =========================================================================

    public function reply(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ticketId  = (int)$request->input('ticket_id');
        $message   = trim((string)$request->input('message', ''));
        $newStatus = trim((string)$request->input('status', ''));
        $file      = $_FILES['attachment'] ?? null;

        if ($ticketId <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid ticket ID'], 422);
        }

        try {
            $this->svc()->replyTicket($this->adminId(), $ticketId, $message, $newStatus,
                ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ? $file : null);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Reply sent',
                        'redirect' => '/admin/tickets/detail?id=' . $ticketId]);
    }

    // =========================================================================
    // INTERNAL NOTE
    // =========================================================================

    public function note(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ticketId = (int)$request->input('ticket_id');
        $note     = trim((string)$request->input('note', ''));

        try {
            $this->svc()->addNote($this->adminId(), $ticketId, $note);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Note added',
                        'redirect' => '/admin/tickets/detail?id=' . $ticketId]);
    }

    // =========================================================================
    // BULK
    // =========================================================================

    public function bulk(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $action   = trim((string)$request->input('action', ''));
        $ids      = (array)$request->input('ids', []);
        $assignTo = (int)$request->input('assign_to', 0);

        try {
            $count = $this->svc()->bulkAction($action, $ids, $assignTo > 0 ? $assignTo : null);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => $count . ' ticket(s) updated', 'redirect' => '/admin/tickets/list']);
    }

    // =========================================================================
    // CATEGORIES
    // =========================================================================

    public function categories(Request $request): void
    {
        $this->bootAdmin();

        try {
            $data = $this->svc()->categoriesIndex();
        } catch (Throwable) {
            $data = ['categories' => []];
        }

        $this->view('admin/tickets/categories', array_merge($data, [
            'title'        => 'Admin · Ticket Categories',
            'username'     => $this->adminUsername(),
            'adminSection' => 'tickets',
        ]));
    }

    public function createCategory(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $this->svc()->createCategory($request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Category created', 'redirect' => '/admin/tickets/categories']);
    }

    public function updateCategory(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $id = (int)$request->input('id');
        try {
            $this->svc()->updateCategory($id, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Category updated', 'redirect' => '/admin/tickets/categories']);
    }

    public function deleteCategory(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $id = (int)$request->input('id');
        try {
            $this->svc()->deleteCategory($id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Category deleted', 'redirect' => '/admin/tickets/categories']);
    }

    // =========================================================================
    // TAGS
    // =========================================================================

    public function createTag(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $name  = trim((string)$request->input('name', ''));
        $color = trim((string)$request->input('color', 'secondary'));
        try {
            $id = $this->svc()->createTag($name, $color);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Tag created', 'id' => $id ?? 0]);
    }

    public function updateTags(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ticketId = (int)$request->input('ticket_id');
        $tagIds   = array_filter(array_map('intval', (array)$request->input('tag_ids', [])));
        try {
            $this->svc()->updateTags($ticketId, array_values($tagIds));
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Tags updated']);
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function analytics(Request $request): void
    {
        $this->bootAdmin();

        $days = max(7, min(90, (int)$request->input('days', 30)));

        try {
            $data = $this->svc()->analytics($days);
        } catch (Throwable) {
            $data = ['daily' => [], 'resolution_dist' => [], 'csat_trend' => [],
                     'category_volume' => [], 'sla_breaches' => []];
        }

        $this->view('admin/tickets/analytics', array_merge($data, [
            'title'        => 'Admin · Ticket Analytics',
            'username'     => $this->adminUsername(),
            'adminSection' => 'tickets',
            'days'         => $days,
        ]));
    }

    // =========================================================================
    // CSV EXPORT
    // =========================================================================

    public function export(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status'    => trim((string)$request->input('status', '')),
            'priority'  => trim((string)$request->input('priority', '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];

        $this->svc()->exportCsv($filters);
    }
}
