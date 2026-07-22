<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminContentRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;

final class AdminContentService
{
    public function __construct(
        private readonly AdminContentRepository   $contentRepo = new AdminContentRepository(),
        private readonly AdminManagementRepository $mgmtRepo   = new AdminManagementRepository(),
    ) {}

    public function contentIndex(): array
    {
        return [
            'announcements'  => $this->contentRepo->listAnnouncements([]),
            'banners'        => $this->contentRepo->listBanners(),
            'emailTemplates' => $this->contentRepo->listEmailTemplates(),
            'legalDocuments' => $this->contentRepo->listLegalDocuments(),
        ];
    }

    // -----------------------------------------------------------------------
    // Announcements
    // -----------------------------------------------------------------------

    public function createAnnouncement(int $adminId, array $payload): int
    {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Announcement title is required.');
        }

        $allowedTypes = ['info', 'warning', 'success', 'danger', 'maintenance'];
        if (!in_array(trim((string)($payload['type'] ?? '')), $allowedTypes, true)) {
            throw new InvalidArgumentException('Invalid announcement type.');
        }

        $id = $this->contentRepo->createAnnouncement($payload, $adminId);
        $this->mgmtRepo->logAdminAction($adminId, 'create_announcement', 'announcements', (string)$id, null, ['title' => $title], RequestContext::ipAddress());
        return $id;
    }

    public function updateAnnouncement(int $adminId, int $id, array $payload): void
    {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Announcement title is required.');
        }

        $record = $this->contentRepo->findAnnouncementById($id);
        if ($record === null) {
            throw new InvalidArgumentException('Announcement not found.');
        }

        $this->contentRepo->updateAnnouncement($id, $payload);
        $this->mgmtRepo->logAdminAction($adminId, 'update_announcement', 'announcements', (string)$id, null, ['title' => $title], RequestContext::ipAddress());
    }

    public function deleteAnnouncement(int $adminId, int $id): void
    {
        $record = $this->contentRepo->findAnnouncementById($id);
        if ($record === null) {
            throw new InvalidArgumentException('Announcement not found.');
        }
        $this->contentRepo->deleteAnnouncement($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_announcement', 'announcements', (string)$id, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Banners
    // -----------------------------------------------------------------------

    public function createBanner(int $adminId, array $payload): int
    {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Banner title is required.');
        }

        $id = $this->contentRepo->createBanner($payload);
        $this->mgmtRepo->logAdminAction($adminId, 'create_banner', 'banners', (string)$id, null, ['title' => $title], RequestContext::ipAddress());
        return $id;
    }

    public function updateBanner(int $adminId, int $id, array $payload): void
    {
        $record = $this->contentRepo->findBannerById($id);
        if ($record === null) {
            throw new InvalidArgumentException('Banner not found.');
        }

        $this->contentRepo->updateBanner($id, $payload);
        $this->mgmtRepo->logAdminAction($adminId, 'update_banner', 'banners', (string)$id, null, ['title' => $payload['title'] ?? ''], RequestContext::ipAddress());
    }

    public function deleteBanner(int $adminId, int $id): void
    {
        $record = $this->contentRepo->findBannerById($id);
        if ($record === null) {
            throw new InvalidArgumentException('Banner not found.');
        }
        $this->contentRepo->deleteBanner($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_banner', 'banners', (string)$id, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Email Templates
    // -----------------------------------------------------------------------

    public function saveEmailTemplate(int $adminId, array $payload): int
    {
        $key = trim((string)($payload['template_key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Template key is required.');
        }

        $id = $this->contentRepo->saveEmailTemplate($payload);
        $this->mgmtRepo->logAdminAction($adminId, 'save_email_template', 'email_templates', (string)$id, null, ['key' => $key], RequestContext::ipAddress());
        return $id;
    }

    public function deleteEmailTemplate(int $adminId, int $id): void
    {
        $record = $this->contentRepo->findEmailTemplateById($id);
        if ($record === null) {
            throw new InvalidArgumentException('Email template not found.');
        }
        $this->contentRepo->deleteEmailTemplate($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_email_template', 'email_templates', (string)$id, null, [], RequestContext::ipAddress());
    }

    // -----------------------------------------------------------------------
    // Legal Documents / Pages
    // -----------------------------------------------------------------------

    public function saveLegalDocument(int $adminId, array $payload): int
    {
        $title = trim((string)($payload['title'] ?? ''));
        $slug  = trim((string)($payload['slug'] ?? ''));

        if ($title === '' || $slug === '') {
            throw new InvalidArgumentException('Title and slug are required.');
        }

        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
        $payload['slug'] = $slug;

        $id = $this->contentRepo->saveLegalDocument($payload);
        $this->mgmtRepo->logAdminAction($adminId, 'save_legal_document', 'legal_documents', (string)$id, null, ['title' => $title, 'slug' => $slug], RequestContext::ipAddress());
        return $id;
    }

    public function deleteLegalDocument(int $adminId, int $id): void
    {
        $record = $this->contentRepo->findLegalDocumentById($id);
        if ($record === null) {
            throw new InvalidArgumentException('Document not found.');
        }
        $this->contentRepo->deleteLegalDocument($id);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_legal_document', 'legal_documents', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function getLegalDocumentForEdit(int $id): array
    {
        $record = $this->contentRepo->findLegalDocumentById($id);
        if ($record === null) {
            throw new InvalidArgumentException('Document not found.');
        }
        return ['document' => $record];
    }
}
