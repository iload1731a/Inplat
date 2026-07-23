<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * AdminTicketsRepository
 *
 * Full DB layer for the admin Support Ticket & Customer Service system.
 */
final class AdminTicketsRepository
{
    // =========================================================================
    // KPI / DASHBOARD
    // =========================================================================

    public function kpiStats(): array
    {
        $pdo = Database::connection();

        $row = $pdo->query(
            "SELECT
                COUNT(*)                                                              AS total_tickets,
                SUM(status IN ('open','in_progress'))                                AS open_tickets,
                SUM(status = 'open')                                                 AS unassigned_open,
                SUM(status IN ('resolved','closed'))                                 AS resolved_tickets,
                SUM(status = 'waiting_on_user')                                      AS waiting_on_user,
                SUM(assigned_to IS NULL AND status NOT IN ('resolved','closed'))     AS unassigned_count,
                SUM(priority = 'urgent' AND status NOT IN ('resolved','closed'))     AS urgent_open,
                AVG(CASE WHEN closed_at IS NOT NULL
                         THEN TIMESTAMPDIFF(HOUR, created_at, closed_at)
                         ELSE NULL END)                                              AS avg_resolution_hrs,
                SUM(created_at >= CURDATE() - INTERVAL 1 DAY)                        AS today_new,
                SUM(created_at >= CURDATE() - INTERVAL 7 DAY)                        AS week_new
             FROM support_tickets"
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function dailyVolume(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) AS date_label,
                    COUNT(*) AS new_tickets,
                    SUM(status IN ('resolved','closed')) AS closed_same_day
             FROM support_tickets
             WHERE created_at >= CURDATE() - INTERVAL :days DAY
             GROUP BY DATE(created_at)
             ORDER BY date_label ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function categoryBreakdown(): array
    {
        return Database::connection()->query(
            "SELECT tc.name AS category_name, tc.color,
                    COUNT(st.id) AS total,
                    SUM(st.status NOT IN ('resolved','closed')) AS open_count
             FROM support_tickets st
             LEFT JOIN ticket_categories tc ON tc.slug = st.category
             GROUP BY st.category, tc.name, tc.color
             ORDER BY total DESC"
        )->fetchAll() ?: [];
    }

    public function priorityBreakdown(): array
    {
        return Database::connection()->query(
            "SELECT priority,
                    COUNT(*) AS total,
                    SUM(status NOT IN ('resolved','closed')) AS open_count
             FROM support_tickets
             GROUP BY priority
             ORDER BY FIELD(priority,'urgent','high','medium','low')"
        )->fetchAll() ?: [];
    }

    public function agentPerformance(): array
    {
        return Database::connection()->query(
            "SELECT au.display_name AS agent_name,
                    COUNT(st.id) AS total_handled,
                    SUM(st.status IN ('resolved','closed')) AS resolved,
                    AVG(CASE WHEN st.closed_at IS NOT NULL
                             THEN TIMESTAMPDIFF(HOUR, st.created_at, st.closed_at)
                             ELSE NULL END) AS avg_hrs
             FROM support_tickets st
             JOIN admin_users au ON au.id = st.assigned_to
             WHERE st.assigned_to IS NOT NULL
             GROUP BY st.assigned_to, au.display_name
             ORDER BY resolved DESC
             LIMIT 10"
        )->fetchAll() ?: [];
    }

    public function csatAverage(): array
    {
        $row = Database::connection()->query(
            "SELECT AVG(rating) AS avg_rating,
                    COUNT(*) AS total_ratings,
                    SUM(rating >= 4) AS positive,
                    SUM(rating <= 2) AS negative
             FROM ticket_csat_ratings"
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public function recentTickets(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT st.id, st.ticket_number, u.username, st.subject,
                    st.category, st.priority, st.status,
                    st.assigned_to, au.display_name AS assigned_name,
                    st.created_at, st.updated_at
             FROM support_tickets st
             JOIN users u ON u.id = st.user_id
             LEFT JOIN admin_users au ON au.id = st.assigned_to
             ORDER BY st.updated_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // TICKET LIST (PAGINATED)
    // =========================================================================

    public function listTickets(array $filters, int $page, int $perPage = 30): array
    {
        $params = [];
        $where  = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'st.status = :status';
            $params['status'] = $status;
        }

        $priority = trim((string)($filters['priority'] ?? ''));
        if ($priority !== '') {
            $where[] = 'st.priority = :priority';
            $params['priority'] = $priority;
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '') {
            $where[] = 'st.category = :category';
            $params['category'] = $category;
        }

        $assigned = trim((string)($filters['assigned'] ?? ''));
        if ($assigned === 'unassigned') {
            $where[] = 'st.assigned_to IS NULL';
        } elseif ($assigned !== '' && ctype_digit($assigned)) {
            $where[] = 'st.assigned_to = :assigned';
            $params['assigned'] = (int)$assigned;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(st.ticket_number LIKE :search OR st.subject LIKE :search OR u.username LIKE :search OR u.email LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where[] = 'DATE(st.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $where[] = 'DATE(st.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset      = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) FROM support_tickets st JOIN users u ON u.id = st.user_id $whereClause";
        $cStmt    = Database::connection()->prepare($countSql);
        foreach ($params as $k => $v) {
            $cStmt->bindValue(':' . $k, $v);
        }
        $cStmt->execute();
        $total = (int)$cStmt->fetchColumn();

        $dataSql = "SELECT st.id, st.ticket_number, u.username, u.email, u.id AS user_id,
                           st.subject, st.category, st.priority, st.status,
                           st.assigned_to, au.display_name AS assigned_name,
                           (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = st.id) AS message_count,
                           st.created_at, st.updated_at, st.closed_at
                    FROM support_tickets st
                    JOIN users u ON u.id = st.user_id
                    LEFT JOIN admin_users au ON au.id = st.assigned_to
                    $whereClause
                    ORDER BY st.updated_at DESC
                    LIMIT :lim OFFSET :off";

        $dStmt = Database::connection()->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dStmt->bindValue(':' . $k, $v);
        }
        $dStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $dStmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $dStmt->execute();
        $rows = $dStmt->fetchAll() ?: [];

        return [
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    // =========================================================================
    // TICKET DETAIL
    // =========================================================================

    public function findTicket(int $ticketId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT st.*, u.username, u.email, u.first_name, u.last_name,
                    u.kyc_status, u.account_type,
                    au.display_name AS assigned_name,
                    au.email AS assigned_email,
                    (SELECT AVG(cr.rating) FROM ticket_csat_ratings cr WHERE cr.ticket_id = st.id) AS csat_rating
             FROM support_tickets st
             JOIN users u ON u.id = st.user_id
             LEFT JOIN admin_users au ON au.id = st.assigned_to
             WHERE st.id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getMessages(int $ticketId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tm.*,
                    CASE tm.sender_type
                        WHEN 'user'  THEN (SELECT username FROM users WHERE id = tm.sender_id)
                        WHEN 'admin' THEN (SELECT display_name FROM admin_users WHERE id = tm.sender_id)
                        ELSE 'System'
                    END AS sender_name
             FROM ticket_messages tm
             WHERE tm.ticket_id = :tid
             ORDER BY tm.id ASC"
        );
        $stmt->bindValue(':tid', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getInternalNotes(int $ticketId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT n.*, au.display_name AS admin_name
             FROM ticket_internal_notes n
             JOIN admin_users au ON au.id = n.admin_id
             WHERE n.ticket_id = :tid
             ORDER BY n.id ASC"
        );
        $stmt->bindValue(':tid', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getAttachments(int $ticketId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM ticket_attachments WHERE ticket_id = :tid ORDER BY id ASC"
        );
        $stmt->bindValue(':tid', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getTags(int $ticketId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tt.id, tt.name, tt.color
             FROM ticket_tag_map tm
             JOIN ticket_tags tt ON tt.id = tm.tag_id
             WHERE tm.ticket_id = :tid"
        );
        $stmt->bindValue(':tid', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getUserTicketHistory(int $userId, int $excludeId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, ticket_number, subject, status, priority, created_at
             FROM support_tickets
             WHERE user_id = :uid AND id <> :eid
             ORDER BY id DESC
             LIMIT 5"
        );
        $stmt->bindValue(':uid', $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':eid', $excludeId,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // TICKET OPERATIONS
    // =========================================================================

    public function updateTicket(int $ticketId, array $data): void
    {
        $set    = [];
        $params = ['id' => $ticketId];

        foreach (['status', 'priority', 'category', 'assigned_to'] as $field) {
            if (array_key_exists($field, $data)) {
                $set[]          = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if ($set === []) {
            return;
        }

        // If closing, set closed_at
        $statusVal = $data['status'] ?? null;
        if (in_array($statusVal, ['resolved', 'closed'], true)) {
            $set[] = 'closed_at = IF(closed_at IS NULL, NOW(), closed_at)';
        } elseif ($statusVal !== null) {
            $set[] = 'closed_at = NULL';
        }

        $sql  = 'UPDATE support_tickets SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k => $v) {
            if ($v === null) {
                $stmt->bindValue(':' . $k, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':' . $k, $v);
            }
        }
        $stmt->execute();
    }

    public function addMessage(int $ticketId, int $senderId, string $senderType, string $message, ?string $attachmentUrl = null): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, attachment_url, created_at)
             VALUES (:tid, :type, :sid, :msg, :attach, NOW())"
        );
        $stmt->bindValue(':tid',    $ticketId,     PDO::PARAM_INT);
        $stmt->bindValue(':type',   $senderType);
        $stmt->bindValue(':sid',    $senderId,     PDO::PARAM_INT);
        $stmt->bindValue(':msg',    $message);
        $stmt->bindValue(':attach', $attachmentUrl);
        $stmt->execute();
        $msgId = (int)$pdo->lastInsertId();

        // Bump updated_at on ticket
        $pdo->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = :tid')
            ->execute([':tid' => $ticketId]);

        return $msgId;
    }

    public function addInternalNote(int $ticketId, int $adminId, string $note): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO ticket_internal_notes (ticket_id, admin_id, note, created_at)
             VALUES (:tid, :aid, :note, NOW())"
        );
        $stmt->bindValue(':tid',  $ticketId, PDO::PARAM_INT);
        $stmt->bindValue(':aid',  $adminId,  PDO::PARAM_INT);
        $stmt->bindValue(':note', $note);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function addAttachment(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO ticket_attachments
                (ticket_id, message_id, uploader_type, uploader_id,
                 original_name, stored_name, file_url, mime_type, file_size, created_at)
             VALUES (:tid, :mid, :utype, :uid, :oname, :sname, :url, :mime, :size, NOW())"
        );
        $stmt->bindValue(':tid',   $data['ticket_id'],      PDO::PARAM_INT);
        $stmt->bindValue(':mid',   $data['message_id'] ?? null, $data['message_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':utype', $data['uploader_type']);
        $stmt->bindValue(':uid',   $data['uploader_id'],    PDO::PARAM_INT);
        $stmt->bindValue(':oname', $data['original_name']);
        $stmt->bindValue(':sname', $data['stored_name']);
        $stmt->bindValue(':url',   $data['file_url']);
        $stmt->bindValue(':mime',  $data['mime_type']);
        $stmt->bindValue(':size',  $data['file_size'],      PDO::PARAM_INT);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if ($ids === []) {
            return 0;
        }
        $ids      = array_map('intval', $ids);
        $in       = implode(',', array_fill(0, count($ids), '?'));
        $extra    = in_array($status, ['resolved', 'closed'], true)
            ? ', closed_at = IF(closed_at IS NULL, NOW(), closed_at)'
            : ', closed_at = NULL';
        $stmt = Database::connection()->prepare(
            "UPDATE support_tickets SET status = ?, updated_at = NOW() $extra WHERE id IN ($in)"
        );
        $stmt->execute(array_merge([$status], $ids));
        return $stmt->rowCount();
    }

    public function bulkAssign(array $ids, int $adminId): int
    {
        if ($ids === []) {
            return 0;
        }
        $ids  = array_map('intval', $ids);
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE support_tickets SET assigned_to = ?, updated_at = NOW() WHERE id IN ($in)"
        );
        $stmt->execute(array_merge([$adminId === 0 ? null : $adminId], $ids));
        return $stmt->rowCount();
    }

    // =========================================================================
    // CATEGORIES CRUD
    // =========================================================================

    public function listCategories(): array
    {
        return Database::connection()->query(
            "SELECT tc.*,
                    (SELECT COUNT(*) FROM support_tickets st WHERE st.category = tc.slug) AS total_tickets
             FROM ticket_categories tc
             ORDER BY tc.sort_order ASC, tc.name ASC"
        )->fetchAll() ?: [];
    }

    public function findCategory(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM ticket_categories WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createCategory(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO ticket_categories (name, slug, description, icon, color, sla_hours, is_active, sort_order)
             VALUES (:name, :slug, :desc, :icon, :color, :sla, :active, :sort)"
        );
        $stmt->execute([
            ':name'   => $data['name'],
            ':slug'   => $data['slug'],
            ':desc'   => $data['description'] ?? null,
            ':icon'   => $data['icon'] ?? 'fa-tag',
            ':color'  => $data['color'] ?? 'secondary',
            ':sla'    => (int)($data['sla_hours'] ?? 24),
            ':active' => (int)(bool)($data['is_active'] ?? 1),
            ':sort'   => (int)($data['sort_order'] ?? 0),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateCategory(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE ticket_categories
             SET name = :name, slug = :slug, description = :desc, icon = :icon,
                 color = :color, sla_hours = :sla, is_active = :active,
                 sort_order = :sort, updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            ':name'   => $data['name'],
            ':slug'   => $data['slug'],
            ':desc'   => $data['description'] ?? null,
            ':icon'   => $data['icon'] ?? 'fa-tag',
            ':color'  => $data['color'] ?? 'secondary',
            ':sla'    => (int)($data['sla_hours'] ?? 24),
            ':active' => (int)(bool)($data['is_active'] ?? 1),
            ':sort'   => (int)($data['sort_order'] ?? 0),
            ':id'     => $id,
        ]);
    }

    public function deleteCategory(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM ticket_categories WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // TAGS
    // =========================================================================

    public function listTags(): array
    {
        return Database::connection()->query(
            "SELECT tt.*,
                    (SELECT COUNT(*) FROM ticket_tag_map tm WHERE tm.tag_id = tt.id) AS usage_count
             FROM ticket_tags tt
             ORDER BY usage_count DESC"
        )->fetchAll() ?: [];
    }

    public function createTag(string $name, string $color): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO ticket_tags (name, color, created_at) VALUES (:name, :color, NOW())
             ON DUPLICATE KEY UPDATE color = VALUES(color)"
        );
        $stmt->execute([':name' => $name, ':color' => $color]);
        return (int)$pdo->lastInsertId();
    }

    public function syncTags(int $ticketId, array $tagIds): void
    {
        $pdo  = Database::connection();
        $pdo->prepare('DELETE FROM ticket_tag_map WHERE ticket_id = :tid')
            ->execute([':tid' => $ticketId]);

        if ($tagIds === []) {
            return;
        }

        $tagIds = array_unique(array_map('intval', $tagIds));
        $stmt   = $pdo->prepare(
            'INSERT IGNORE INTO ticket_tag_map (ticket_id, tag_id) VALUES (:tid, :tag)'
        );
        foreach ($tagIds as $tagId) {
            $stmt->execute([':tid' => $ticketId, ':tag' => $tagId]);
        }
    }

    // =========================================================================
    // ADMIN USERS (for assignment)
    // =========================================================================

    public function listAdminUsers(): array
    {
        return Database::connection()->query(
            "SELECT id, display_name, email
             FROM admin_users
             WHERE is_active = 1
             ORDER BY display_name ASC"
        )->fetchAll() ?: [];
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function analyticsBundle(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                DATE(created_at)                              AS day,
                COUNT(*)                                      AS new_tickets,
                SUM(status IN ('resolved','closed'))          AS closed_same_day,
                SUM(priority = 'urgent')                      AS urgent_count
             FROM support_tickets
             WHERE created_at >= CURDATE() - INTERVAL :days DAY
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $daily = $stmt->fetchAll() ?: [];

        // Resolution time distribution (buckets)
        $rtRows = Database::connection()->query(
            "SELECT
                SUM(TIMESTAMPDIFF(HOUR,created_at,closed_at) < 4)    AS lt4h,
                SUM(TIMESTAMPDIFF(HOUR,created_at,closed_at) BETWEEN 4  AND 23)  AS h4_24,
                SUM(TIMESTAMPDIFF(HOUR,created_at,closed_at) BETWEEN 24 AND 71)  AS h24_72,
                SUM(TIMESTAMPDIFF(HOUR,created_at,closed_at) >= 72)   AS gt72h
             FROM support_tickets
             WHERE closed_at IS NOT NULL"
        )->fetch(PDO::FETCH_ASSOC);

        // CSAT trend
        $stmt2 = Database::connection()->prepare(
            "SELECT DATE(created_at) AS day, AVG(rating) AS avg_rating, COUNT(*) AS cnt
             FROM ticket_csat_ratings
             WHERE created_at >= CURDATE() - INTERVAL :days DAY
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt2->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt2->execute();
        $csatTrend = $stmt2->fetchAll() ?: [];

        // Top categories by volume
        $cats = Database::connection()->query(
            "SELECT COALESCE(tc.name, st.category) AS label,
                    COUNT(*) AS cnt
             FROM support_tickets st
             LEFT JOIN ticket_categories tc ON tc.slug = st.category
             GROUP BY st.category, tc.name
             ORDER BY cnt DESC
             LIMIT 8"
        )->fetchAll() ?: [];

        // SLA breach (tickets older than SLA hours and not resolved)
        $slaRows = Database::connection()->query(
            "SELECT st.id, st.ticket_number, u.username, st.subject, st.priority,
                    TIMESTAMPDIFF(HOUR, st.created_at, NOW()) AS age_hrs,
                    COALESCE(tc.sla_hours, 24) AS sla_hours
             FROM support_tickets st
             JOIN users u ON u.id = st.user_id
             LEFT JOIN ticket_categories tc ON tc.slug = st.category
             WHERE st.status NOT IN ('resolved','closed')
               AND TIMESTAMPDIFF(HOUR, st.created_at, NOW()) > COALESCE(tc.sla_hours, 24)
             ORDER BY age_hrs DESC
             LIMIT 20"
        )->fetchAll() ?: [];

        return [
            'daily'           => $daily,
            'resolution_dist' => $rtRows ?: [],
            'csat_trend'      => $csatTrend,
            'category_volume' => $cats,
            'sla_breaches'    => $slaRows,
        ];
    }

    // =========================================================================
    // CSV EXPORT
    // =========================================================================

    public function exportRows(array $filters): array
    {
        $params = [];
        $where  = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'st.status = :status';
            $params['status'] = $status;
        }

        $priority = trim((string)($filters['priority'] ?? ''));
        if ($priority !== '') {
            $where[] = 'st.priority = :priority';
            $params['priority'] = $priority;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where[] = 'DATE(st.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $where[] = 'DATE(st.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = Database::connection()->prepare(
            "SELECT st.ticket_number, u.username, u.email,
                    st.subject, st.category, st.priority, st.status,
                    au.display_name AS assigned_to,
                    (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = st.id) AS replies,
                    st.created_at, st.updated_at, st.closed_at
             FROM support_tickets st
             JOIN users u ON u.id = st.user_id
             LEFT JOIN admin_users au ON au.id = st.assigned_to
             $whereClause
             ORDER BY st.id DESC
             LIMIT 5000"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
