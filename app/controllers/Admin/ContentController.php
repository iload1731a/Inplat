<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminContentService;
use Throwable;

final class ContentController extends AdminBaseController
{
    private function svc(): AdminContentService
    {
        return new AdminContentService();
    }

    public function index(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->contentIndex();
        $this->view('admin/content/index', [
            'title'        => 'Admin · Content Management',
            'username'     => $this->adminUsername(),
            'adminSection' => 'content',
            ...$data,
        ]);
    }

    // -----------------------------------------------------------------------
    // Announcements
    // -----------------------------------------------------------------------

    public function createAnnouncement(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->createAnnouncement($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Announcement created.', 'redirect' => '/admin/content']);
    }

    public function updateAnnouncement(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('announcement_id', 0);
        try {
            $this->svc()->updateAnnouncement($this->adminId(), $id, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Announcement updated.', 'redirect' => '/admin/content']);
    }

    public function deleteAnnouncement(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('announcement_id', 0);
        try {
            $this->svc()->deleteAnnouncement($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Announcement deleted.', 'redirect' => '/admin/content']);
    }

    // -----------------------------------------------------------------------
    // Banners
    // -----------------------------------------------------------------------

    public function createBanner(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->createBanner($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Banner created.', 'redirect' => '/admin/content']);
    }

    public function updateBanner(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('banner_id', 0);
        try {
            $this->svc()->updateBanner($this->adminId(), $id, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Banner updated.', 'redirect' => '/admin/content']);
    }

    public function deleteBanner(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('banner_id', 0);
        try {
            $this->svc()->deleteBanner($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Banner deleted.', 'redirect' => '/admin/content']);
    }

    // -----------------------------------------------------------------------
    // Email Templates
    // -----------------------------------------------------------------------

    public function saveEmailTemplate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveEmailTemplate($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Email template saved.', 'redirect' => '/admin/content']);
    }

    public function deleteEmailTemplate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('template_id', 0);
        try {
            $this->svc()->deleteEmailTemplate($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Email template deleted.', 'redirect' => '/admin/content']);
    }

    // -----------------------------------------------------------------------
    // Legal Documents / Pages
    // -----------------------------------------------------------------------

    public function saveLegalDocument(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveLegalDocument($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Document saved.', 'redirect' => '/admin/content']);
    }

    public function deleteLegalDocument(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('document_id', 0);
        try {
            $this->svc()->deleteLegalDocument($this->adminId(), $id);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        Response::json(['ok' => true, 'message' => 'Document deleted.', 'redirect' => '/admin/content']);
    }
}
