<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminContentRepository
{
    // -----------------------------------------------------------------------
    // Announcements
    // -----------------------------------------------------------------------

    public function listAnnouncements(array $filters = []): array
    {
        $sql = "SELECT a.id, a.title, a.body, a.category, a.is_pinned, a.is_published,
                       a.published_at, a.created_at, a.updated_at,
                       COALESCE(au.full_name, au.username) AS created_by_name
                FROM announcements a
                LEFT JOIN admin_users au ON au.id = a.created_by
                WHERE 1=1";
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (a.title LIKE :search OR a.body LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $type = trim((string)($filters['type'] ?? ''));
        if ($type !== '') {
            $sql .= ' AND a.category = :category';
            $params['category'] = $type;
        }

        $sql .= ' ORDER BY a.created_at DESC LIMIT 100';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findAnnouncementById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM announcements WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createAnnouncement(array $data, int $adminId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO announcements (title, body, category, is_pinned, is_published, published_at, created_by, created_at, updated_at)
             VALUES (:title, :body, :category, :is_pinned, :is_published, :published_at, :created_by, NOW(), NOW())"
        );
        $stmt->execute([
            ':title'        => trim((string)($data['title'] ?? '')),
            ':body'         => (string)($data['body'] ?? $data['content'] ?? ''),
            ':category'     => trim((string)($data['category'] ?? $data['type'] ?? 'general')),
            ':is_pinned'    => (int)(bool)($data['is_pinned'] ?? 0),
            ':is_published' => (int)(bool)($data['is_published'] ?? $data['is_active'] ?? 1),
            ':published_at' => ($data['published_at'] ?? '') !== '' ? $data['published_at'] : null,
            ':created_by'   => $adminId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateAnnouncement(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE announcements SET title = :title, body = :body, category = :category,
                is_pinned = :is_pinned, is_published = :is_published, published_at = :published_at,
                updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            ':title'        => trim((string)($data['title'] ?? '')),
            ':body'         => (string)($data['body'] ?? $data['content'] ?? ''),
            ':category'     => trim((string)($data['category'] ?? $data['type'] ?? 'general')),
            ':is_pinned'    => (int)(bool)($data['is_pinned'] ?? 0),
            ':is_published' => (int)(bool)($data['is_published'] ?? $data['is_active'] ?? 1),
            ':published_at' => ($data['published_at'] ?? '') !== '' ? $data['published_at'] : null,
            ':id'           => $id,
        ]);
    }

    public function deleteAnnouncement(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM announcements WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Banners
    // -----------------------------------------------------------------------

    public function listBanners(): array
    {
        $stmt = Database::connection()->query(
            "SELECT b.id, b.title, b.image_url, b.link_url,
                    b.placement, b.is_active, b.display_order, b.starts_at, b.ends_at, b.created_at
             FROM banners b
             ORDER BY b.display_order ASC, b.created_at DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findBannerById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM banners WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createBanner(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO banners (title, image_url, link_url, placement, is_active, display_order, starts_at, ends_at, created_at)
             VALUES (:title, :image_url, :link_url, :placement, :is_active, :display_order, :starts_at, :ends_at, NOW())"
        );
        $stmt->execute([
            ':title'         => trim((string)($data['title'] ?? '')),
            ':image_url'     => trim((string)($data['image_url'] ?? '')),
            ':link_url'      => trim((string)($data['link_url'] ?? '')),
            ':placement'     => trim((string)($data['placement'] ?? $data['position'] ?? 'homepage')),
            ':is_active'     => (int)(bool)($data['is_active'] ?? 1),
            ':display_order' => max(0, (int)($data['display_order'] ?? 0)),
            ':starts_at'     => ($data['starts_at'] ?? '') !== '' ? $data['starts_at'] : null,
            ':ends_at'       => ($data['ends_at'] ?? $data['expires_at'] ?? '') !== '' ? ($data['ends_at'] ?? $data['expires_at']) : null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateBanner(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE banners SET title = :title, image_url = :image_url,
                link_url = :link_url, placement = :placement,
                is_active = :is_active, display_order = :display_order,
                starts_at = :starts_at, ends_at = :ends_at
             WHERE id = :id"
        );
        $stmt->execute([
            ':title'         => trim((string)($data['title'] ?? '')),
            ':image_url'     => trim((string)($data['image_url'] ?? '')),
            ':link_url'      => trim((string)($data['link_url'] ?? '')),
            ':placement'     => trim((string)($data['placement'] ?? $data['position'] ?? 'homepage')),
            ':is_active'     => (int)(bool)($data['is_active'] ?? 1),
            ':display_order' => max(0, (int)($data['display_order'] ?? 0)),
            ':starts_at'     => ($data['starts_at'] ?? '') !== '' ? $data['starts_at'] : null,
            ':ends_at'       => ($data['ends_at'] ?? $data['expires_at'] ?? '') !== '' ? ($data['ends_at'] ?? $data['expires_at']) : null,
            ':id'            => $id,
        ]);
    }

    public function deleteBanner(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM banners WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Email Templates
    // -----------------------------------------------------------------------

    public function listEmailTemplates(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, template_key, subject, is_active, updated_at
             FROM email_templates
             ORDER BY template_key ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findEmailTemplateById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM email_templates WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function saveEmailTemplate(array $data): int
    {
        $pdo = Database::connection();
        $id  = (int)($data['id'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE email_templates SET template_key = :key, subject = :subject, body_html = :body,
                    is_active = :is_active, updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                ':key'       => trim((string)($data['template_key'] ?? '')),
                ':subject'   => trim((string)($data['subject'] ?? '')),
                ':body'      => (string)($data['body_html'] ?? ''),
                ':is_active' => (int)(bool)($data['is_active'] ?? 1),
                ':id'        => $id,
            ]);
            return $id;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO email_templates (template_key, subject, body_html, is_active)
             VALUES (:key, :subject, :body, :is_active)"
        );
        $stmt->execute([
            ':key'       => trim((string)($data['template_key'] ?? '')),
            ':subject'   => trim((string)($data['subject'] ?? '')),
            ':body'      => (string)($data['body_html'] ?? ''),
            ':is_active' => (int)(bool)($data['is_active'] ?? 1),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function deleteEmailTemplate(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM email_templates WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Legal Documents (FAQ, Pages, Terms, Privacy)
    // -----------------------------------------------------------------------

    public function listLegalDocuments(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, document_type, version, title, is_current, published_at, created_at
             FROM legal_documents
             ORDER BY document_type ASC, created_at DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findLegalDocumentById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM legal_documents WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function saveLegalDocument(array $data): int
    {
        $pdo = Database::connection();
        $id  = (int)($data['id'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE legal_documents SET document_type = :type, version = :version, title = :title,
                    body = :body, is_current = :is_current,
                    published_at = :published_at
                 WHERE id = :id"
            );
            $stmt->execute([
                ':type'         => trim((string)($data['document_type'] ?? $data['doc_type'] ?? 'terms_of_service')),
                ':version'      => trim((string)($data['version'] ?? '1.0')),
                ':title'        => trim((string)($data['title'] ?? '')),
                ':body'         => (string)($data['body'] ?? $data['content'] ?? ''),
                ':is_current'   => (int)(bool)($data['is_current'] ?? $data['is_active'] ?? 0),
                ':published_at' => ($data['published_at'] ?? $data['effective_date'] ?? '') !== '' ? ($data['published_at'] ?? $data['effective_date']) : null,
                ':id'           => $id,
            ]);
            return $id;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO legal_documents (document_type, version, title, body, is_current, published_at, created_at)
             VALUES (:type, :version, :title, :body, :is_current, :published_at, NOW())"
        );
        $stmt->execute([
            ':type'         => trim((string)($data['document_type'] ?? $data['doc_type'] ?? 'terms_of_service')),
            ':version'      => trim((string)($data['version'] ?? '1.0')),
            ':title'        => trim((string)($data['title'] ?? '')),
            ':body'         => (string)($data['body'] ?? $data['content'] ?? ''),
            ':is_current'   => (int)(bool)($data['is_current'] ?? $data['is_active'] ?? 0),
            ':published_at' => ($data['published_at'] ?? $data['effective_date'] ?? '') !== '' ? ($data['published_at'] ?? $data['effective_date']) : null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function deleteLegalDocument(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM legal_documents WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
