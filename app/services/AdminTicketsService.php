<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\AdminTicketsRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * AdminTicketsService
 *
 * Business logic for the admin Support Ticket & Customer Service system.
 */
final class AdminTicketsService
{
    private readonly AdminTicketsRepository $repo;

    private const ALLOWED_STATUSES   = ['open', 'in_progress', 'waiting_on_user', 'resolved', 'closed'];
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    private const ATTACH_MAX_BYTES   = 10 * 1024 * 1024; // 10 MB
    private const ATTACH_MIMES       = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf',
                                        'text/plain', 'application/zip'];

    public function __construct(?AdminTicketsRepository $repo = null)
    {
        $this->repo = $repo ?? new AdminTicketsRepository();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function dashboard(): array
    {
        $kpi         = $this->repo->kpiStats();
        $daily       = $this->repo->dailyVolume(30);
        $categories  = $this->repo->categoryBreakdown();
        $priorities  = $this->repo->priorityBreakdown();
        $agents      = $this->repo->agentPerformance();
        $csat        = $this->repo->csatAverage();
        $recent      = $this->repo->recentTickets(10);

        return compact('kpi', 'daily', 'categories', 'priorities', 'agents', 'csat', 'recent');
    }

    // =========================================================================
    // TICKET LIST
    // =========================================================================

    public function ticketList(array $filters, int $page): array
    {
        $page = max(1, $page);
        $result     = $this->repo->listTickets($filters, $page);
        $categories = $this->repo->listCategories();
        $admins     = $this->repo->listAdminUsers();

        return array_merge($result, compact('categories', 'admins', 'filters'));
    }

    // =========================================================================
    // TICKET DETAIL
    // =========================================================================

    public function ticketDetail(int $ticketId): array
    {
        $ticket = $this->repo->findTicket($ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket not found');
        }

        $messages  = $this->repo->getMessages($ticketId);
        $notes     = $this->repo->getInternalNotes($ticketId);
        $tags      = $this->repo->getTags($ticketId);
        $history   = $this->repo->getUserTicketHistory((int)$ticket['user_id'], $ticketId);
        $admins    = $this->repo->listAdminUsers();
        $allTags   = $this->repo->listTags();
        $attachments = $this->repo->getAttachments($ticketId);

        return compact('ticket', 'messages', 'notes', 'tags', 'history', 'admins', 'allTags', 'attachments');
    }

    // =========================================================================
    // UPDATE STATUS / ASSIGN
    // =========================================================================

    public function updateTicket(int $ticketId, array $input): void
    {
        $data = [];

        if (isset($input['status'])) {
            $s = (string)$input['status'];
            if (!in_array($s, self::ALLOWED_STATUSES, true)) {
                throw new InvalidArgumentException('Invalid status: ' . $s);
            }
            $data['status'] = $s;
        }

        if (isset($input['priority'])) {
            $p = (string)$input['priority'];
            if (!in_array($p, self::ALLOWED_PRIORITIES, true)) {
                throw new InvalidArgumentException('Invalid priority: ' . $p);
            }
            $data['priority'] = $p;
        }

        if (array_key_exists('assigned_to', $input)) {
            $aid = (int)$input['assigned_to'];
            $data['assigned_to'] = $aid > 0 ? $aid : null;
        }

        if (isset($input['category'])) {
            $data['category'] = (string)$input['category'];
        }

        if ($data !== []) {
            $this->repo->updateTicket($ticketId, $data);
        }
    }

    // =========================================================================
    // REPLY
    // =========================================================================

    public function replyTicket(int $adminId, int $ticketId, string $message, string $newStatus, ?array $file = null): void
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Reply message cannot be empty');
        }

        $ticket = $this->repo->findTicket($ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket not found');
        }

        $attachUrl = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $attachUrl = $this->saveAttachment($ticketId, $adminId, 'admin', $file, null);
        }

        $msgId = $this->repo->addMessage($ticketId, $adminId, 'admin', $message, $attachUrl);

        // Save attachment row with message reference
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && $attachUrl !== null) {
            $storedName = basename($attachUrl);
            $this->repo->addAttachment([
                'ticket_id'     => $ticketId,
                'message_id'    => $msgId,
                'uploader_type' => 'admin',
                'uploader_id'   => $adminId,
                'original_name' => (string)($file['name'] ?? $storedName),
                'stored_name'   => $storedName,
                'file_url'      => $attachUrl,
                'mime_type'     => (string)($file['type'] ?? 'application/octet-stream'),
                'file_size'     => (int)($file['size'] ?? 0),
            ]);
        }

        if ($newStatus !== '' && in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            $this->repo->updateTicket($ticketId, ['status' => $newStatus]);
        }
    }

    // =========================================================================
    // INTERNAL NOTE
    // =========================================================================

    public function addNote(int $adminId, int $ticketId, string $note): void
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('Note cannot be empty');
        }
        $ticket = $this->repo->findTicket($ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket not found');
        }
        $this->repo->addInternalNote($ticketId, $adminId, $note);
    }

    // =========================================================================
    // FILE UPLOAD
    // =========================================================================

    private function saveAttachment(int $ticketId, int $uploaderId, string $uploaderType, array $file, ?int $msgId): string
    {
        $mimeType = mime_content_type((string)($file['tmp_name'] ?? ''));
        if ($mimeType === false) {
            $mimeType = (string)($file['type'] ?? 'application/octet-stream');
        }
        if (!in_array($mimeType, self::ATTACH_MIMES, true)) {
            throw new InvalidArgumentException('Unsupported file type for attachment');
        }
        if (((int)($file['size'] ?? 0)) > self::ATTACH_MAX_BYTES) {
            throw new InvalidArgumentException('Attachment must be smaller than 10 MB');
        }

        $uploadDir = app_path('public/uploads/tickets/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext        = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $storedName = 'ticket_' . $ticketId . '_' . $uploaderId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target     = $uploadDir . $storedName;

        if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $target)) {
            throw new RuntimeException('Failed to save attachment');
        }

        return '/uploads/tickets/' . $storedName;
    }

    public function uploadAttachment(int $adminId, int $ticketId, array $file): string
    {
        $ticket = $this->repo->findTicket($ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket not found');
        }

        $fileUrl = $this->saveAttachment($ticketId, $adminId, 'admin', $file, null);
        $this->repo->addAttachment([
            'ticket_id'     => $ticketId,
            'message_id'    => null,
            'uploader_type' => 'admin',
            'uploader_id'   => $adminId,
            'original_name' => (string)($file['name'] ?? basename($fileUrl)),
            'stored_name'   => basename($fileUrl),
            'file_url'      => $fileUrl,
            'mime_type'     => (string)($file['type'] ?? 'application/octet-stream'),
            'file_size'     => (int)($file['size'] ?? 0),
        ]);

        return $fileUrl;
    }

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    public function bulkAction(string $action, array $ids, ?int $assignTo = null): int
    {
        $ids = array_filter(array_map('intval', $ids));
        if ($ids === []) {
            throw new InvalidArgumentException('No ticket IDs provided');
        }

        return match ($action) {
            'close'    => $this->repo->bulkUpdateStatus($ids, 'closed'),
            'resolve'  => $this->repo->bulkUpdateStatus($ids, 'resolved'),
            'reopen'   => $this->repo->bulkUpdateStatus($ids, 'open'),
            'assign'   => $this->repo->bulkAssign($ids, (int)$assignTo),
            default    => throw new InvalidArgumentException('Unknown bulk action: ' . $action),
        };
    }

    // =========================================================================
    // TAGS
    // =========================================================================

    public function updateTags(int $ticketId, array $tagIds): void
    {
        $ticket = $this->repo->findTicket($ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket not found');
        }
        $this->repo->syncTags($ticketId, $tagIds);
    }

    public function createTag(string $name, string $color): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Tag name is required');
        }
        return $this->repo->createTag($name, $color);
    }

    // =========================================================================
    // CATEGORIES
    // =========================================================================

    public function categoriesIndex(): array
    {
        return ['categories' => $this->repo->listCategories()];
    }

    public function createCategory(array $input): int
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Category name is required');
        }
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
        return $this->repo->createCategory([
            'name'        => $name,
            'slug'        => $slug,
            'description' => trim((string)($input['description'] ?? '')),
            'icon'        => trim((string)($input['icon'] ?? 'fa-tag')),
            'color'       => trim((string)($input['color'] ?? 'secondary')),
            'sla_hours'   => max(1, (int)($input['sla_hours'] ?? 24)),
            'is_active'   => (bool)($input['is_active'] ?? true),
            'sort_order'  => (int)($input['sort_order'] ?? 0),
        ]);
    }

    public function updateCategory(int $id, array $input): void
    {
        $cat = $this->repo->findCategory($id);
        if ($cat === null) {
            throw new RuntimeException('Category not found');
        }
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Category name is required');
        }
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
        $this->repo->updateCategory($id, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => trim((string)($input['description'] ?? '')),
            'icon'        => trim((string)($input['icon'] ?? 'fa-tag')),
            'color'       => trim((string)($input['color'] ?? 'secondary')),
            'sla_hours'   => max(1, (int)($input['sla_hours'] ?? 24)),
            'is_active'   => (bool)($input['is_active'] ?? true),
            'sort_order'  => (int)($input['sort_order'] ?? 0),
        ]);
    }

    public function deleteCategory(int $id): void
    {
        $cat = $this->repo->findCategory($id);
        if ($cat === null) {
            throw new RuntimeException('Category not found');
        }
        $this->repo->deleteCategory($id);
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function analytics(int $days): array
    {
        $days = max(7, min(90, $days));
        return $this->repo->analyticsBundle($days);
    }

    // =========================================================================
    // CSV EXPORT
    // =========================================================================

    public function exportCsv(array $filters): void
    {
        $rows = $this->repo->exportRows($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="tickets_export_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-cache, no-store');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            return;
        }

        fputcsv($out, ['Ticket #', 'Username', 'Email', 'Subject', 'Category', 'Priority', 'Status',
                        'Assigned To', 'Replies', 'Created At', 'Updated At', 'Closed At']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['ticket_number'] ?? '',
                $row['username']      ?? '',
                $row['email']         ?? '',
                $row['subject']       ?? '',
                $row['category']      ?? '',
                $row['priority']      ?? '',
                $row['status']        ?? '',
                $row['assigned_to']   ?? '',
                $row['replies']       ?? 0,
                $row['created_at']    ?? '',
                $row['updated_at']    ?? '',
                $row['closed_at']     ?? '',
            ]);
        }

        fclose($out);
        exit;
    }
}
