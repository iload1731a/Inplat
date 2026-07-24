<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Repositories\AdminLogsRepository;

final class LogsController extends AdminBaseController
{
    private function repo(): AdminLogsRepository
    {
        return new AdminLogsRepository();
    }

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $tab = trim((string)$request->input('tab', 'admin'));

        $filters = [
            'search'     => trim((string)$request->input('search', '')),
            'action'     => trim((string)$request->input('action', '')),
            'actor_type' => trim((string)$request->input('actor_type', '')),
            'admin_id'   => (int)$request->input('admin_id', 0),
            'status'     => trim((string)$request->input('status', '')),
            'date_from'  => trim((string)$request->input('date_from', '')),
            'date_to'    => trim((string)$request->input('date_to', '')),
        ];

        $repo = $this->repo();

        $this->view('admin/logs/index', [
            'title'         => 'Admin · Activity & Audit Logs',
            'username'      => $this->adminUsername(),
            'adminSection'  => 'logs',
            'activeTab'     => $tab,
            'logStats'      => $repo->getLogStats(),
            'adminLogs'     => $tab === 'admin'  ? $repo->listAdminActivityLogs($filters) : [],
            'auditLogs'     => $tab === 'audit'  ? $repo->listAuditLogs($filters)         : [],
            'loginHistory'  => $tab === 'logins' ? $repo->listLoginHistory($filters)      : [],
            'filters'       => $filters,
        ]);
    }
}
