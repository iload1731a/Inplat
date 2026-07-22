<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminSystemService;
use Throwable;

final class SystemController extends AdminBaseController
{
    private function svc(): AdminSystemService
    {
        return new AdminSystemService();
    }

    public function index(Request $request): void
    {
        $this->bootAdmin();
        $tab  = trim((string)$request->input('tab', 'flags'));
        $data = $this->svc()->systemIndex($tab);

        $this->view('admin/system/index', [
            'title'        => 'Admin · System Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'system',
            ...$data,
        ]);
    }

    // Feature Flags
    public function createFlag(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->createFeatureFlag($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Feature flag created.', 'redirect' => '/admin/system?tab=flags']);
    }

    public function updateFlag(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('flag_id', 0);
        try {
            $this->svc()->updateFeatureFlag($this->adminId(), $id, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Feature flag updated.', 'redirect' => '/admin/system?tab=flags']);
    }

    public function deleteFlag(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('flag_id', 0);
        try {
            $this->svc()->deleteFeatureFlag($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Feature flag deleted.', 'redirect' => '/admin/system?tab=flags']);
    }

    // Maintenance
    public function createMaintenance(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->createMaintenanceWindow($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Maintenance window created.', 'redirect' => '/admin/system?tab=maintenance']);
    }

    public function updateMaintenanceStatus(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id     = (int)$request->input('window_id', 0);
        $status = trim((string)$request->input('status', ''));
        try {
            $this->svc()->updateMaintenanceStatus($this->adminId(), $id, $status);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Maintenance status updated.', 'redirect' => '/admin/system?tab=maintenance']);
    }

    public function deleteMaintenance(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('window_id', 0);
        try {
            $this->svc()->deleteMaintenanceWindow($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Maintenance window deleted.', 'redirect' => '/admin/system?tab=maintenance']);
    }

    // Settings
    public function updateSetting(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id    = (int)$request->input('setting_id', 0);
        $type  = trim((string)$request->input('value_type', 'string'));
        $value = $request->input('setting_value') !== null ? (string)$request->input('setting_value') : null;
        try {
            $this->svc()->updateSetting($this->adminId(), $id, $type, $value);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Setting saved.', 'redirect' => '/admin/system?tab=settings']);
    }

    public function createSetting(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->createSetting($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Setting created.', 'redirect' => '/admin/system?tab=settings']);
    }

    // Price Providers
    public function toggleProvider(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id     = (int)$request->input('provider_id', 0);
        $active = (bool)(int)$request->input('is_active', '0');
        try {
            $this->svc()->toggleProvider($this->adminId(), $id, $active);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Provider status updated.']);
    }

    // Webhooks
    public function createWebhook(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $url    = trim((string)$request->input('url', ''));
        $events = trim((string)$request->input('event_types', ''));
        try {
            $this->svc()->createWebhook($this->adminId(), $url, $events);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Webhook created.', 'redirect' => '/admin/system?tab=webhooks']);
    }

    public function deleteWebhook(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('webhook_id', 0);
        try {
            $this->svc()->deleteWebhook($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Webhook deleted.', 'redirect' => '/admin/system?tab=webhooks']);
    }
}
