<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminLogsRepository
{
    public function listAdminActivityLogs(array $filters = []): array
    {
        $sql = "SELECT aal.id, aal.admin_id, aal.action, aal.resource_type, aal.resource_id,
                       aal.ip_address, aal.created_at, aal.changes_json,
                       COALESCE(au.full_name, au.username) AS admin_name
                FROM admin_activity_logs aal
                LEFT JOIN admin_users au ON au.id = aal.admin_id
                WHERE 1=1";

        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (aal.action LIKE :search OR aal.resource_type LIKE :search OR au.username LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $action = trim((string)($filters['action'] ?? ''));
        if ($action !== '') {
            $sql .= ' AND aal.action = :action';
            $params['action'] = $action;
        }

        $adminId = (int)($filters['admin_id'] ?? 0);
        if ($adminId > 0) {
            $sql .= ' AND aal.admin_id = :admin_id';
            $params['admin_id'] = $adminId;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(aal.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(aal.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY aal.id DESC LIMIT 200';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function listAuditLogs(array $filters = []): array
    {
        $sql = "SELECT al.id, al.actor_type, al.actor_id, al.action, al.resource_type,
                       al.resource_id, al.ip_address, al.user_agent, al.created_at
                FROM audit_logs al
                WHERE 1=1";

        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (al.action LIKE :search OR al.resource_type LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $actorType = trim((string)($filters['actor_type'] ?? ''));
        if ($actorType !== '') {
            $sql .= ' AND al.actor_type = :actor_type';
            $params['actor_type'] = $actorType;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(al.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(al.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY al.id DESC LIMIT 200';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function listLoginHistory(array $filters = []): array
    {
        $sql = "SELECT lh.id, lh.user_id, u.username, u.email,
                       lh.ip_address, lh.user_agent, lh.status, lh.failure_reason,
                       lh.country_code, lh.created_at
                FROM login_history lh
                INNER JOIN users u ON u.id = lh.user_id
                WHERE 1=1";

        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :search OR u.email LIKE :search OR lh.ip_address LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND lh.status = :status';
            $params['status'] = $status;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(lh.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $sql .= ' ORDER BY lh.id DESC LIMIT 200';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function getLogStats(): array
    {
        $pdo = Database::connection();

        $adminCount = $pdo->query('SELECT COUNT(*) FROM admin_activity_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn();
        $auditCount = $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn();
        $loginFailed = $pdo->query("SELECT COUNT(*) FROM login_history WHERE status = 'failed' AND DATE(created_at) = CURDATE()")->fetchColumn();

        return [
            'admin_actions_today'    => (int)$adminCount,
            'audit_entries_today'    => (int)$auditCount,
            'failed_logins_today'    => (int)$loginFailed,
        ];
    }
}
