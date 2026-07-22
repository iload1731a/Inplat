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
        $sql = "SELECT a.id, a.title, a.content, a.type, a.target_audience, a.is_active,
                       a.starts_at, a.expires_at, a.created_at, a.updated_at,
                       COALESCE(au.full_name, au.username) AS created_by_name
                FROM announcements a
                LEFT JOIN admin_users au ON au.id = a.created_by
                WHERE a.deleted_at IS NULL";
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (a.title LIKE :search OR a.content LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $type = trim((string)($filters['type'] ?? ''));
        if ($type !== '') {
            $sql .= ' AND a.type = :type';
            $params['type'] = $type;
        }

        $sql .= ' ORDER BY a.created_at DESC LIMIT 100';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findAnnouncementById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM announcements WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createAnnouncement(array $data, int $adminId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO announcements (title, content, type, target_audience, is_active, starts_at, expires_at, created_by, created_at, updated_at)
             VALUES (:title, :content, :type, :target, :is_active, :starts_at, :expires_at, :created_by, NOW(), NOW())"
        );
        $stmt->execute([
            ':title'      => trim((string)($data['title'] ?? '')),
            ':content'    => (string)($data['content'] ?? ''),
            ':type'       => trim((string)($data['type'] ?? 'info')),
            ':target'     => trim((string)($data['target_audience'] ?? 'all')),
            ':is_active'  => (int)(bool)($data['is_active'] ?? 1),
            ':starts_at'  => ($data['starts_at'] ?? '') !== '' ? $data['starts_at'] : null,
            ':expires_at' => ($data['expires_at'] ?? '') !== '' ? $data['expires_at'] : null,
            ':created_by' => $adminId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateAnnouncement(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE announcements SET title = :title, content = :content, type = :type,
                target_audience = :target, is_active = :is_active, starts_at = :starts_at,
                expires_at = :expires_at, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([
            ':title'      => trim((string)($data['title'] ?? '')),
            ':content'    => (string)($data['content'] ?? ''),
            ':type'       => trim((string)($data['type'] ?? 'info')),
            ':target'     => trim((string)($data['target_audience'] ?? 'all')),
            ':is_active'  => (int)(bool)($data['is_active'] ?? 1),
            ':starts_at'  => ($data['starts_at'] ?? '') !== '' ? $data['starts_at'] : null,
            ':expires_at' => ($data['expires_at'] ?? '') !== '' ? $data['expires_at'] : null,
            ':id'         => $id,
        ]);
    }

    public function deleteAnnouncement(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE announcements SET deleted_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Banners
    // -----------------------------------------------------------------------

    public function listBanners(): array
    {
        $stmt = Database::connection()->query(
            "SELECT b.id, b.title, b.subtitle, b.image_url, b.link_url, b.button_text,
                    b.position, b.is_active, b.display_order, b.starts_at, b.expires_at, b.created_at
             FROM banners b
             WHERE b.deleted_at IS NULL
             ORDER BY b.display_order ASC, b.created_at DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findBannerById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM banners WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createBanner(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO banners (title, subtitle, image_url, link_url, button_text, position, is_active, display_order, starts_at, expires_at, created_at, updated_at)
             VALUES (:title, :subtitle, :image_url, :link_url, :button_text, :position, :is_active, :display_order, :starts_at, :expires_at, NOW(), NOW())"
        );
        $stmt->execute([
            ':title'         => trim((string)($data['title'] ?? '')),
            ':subtitle'      => trim((string)($data['subtitle'] ?? '')),
            ':image_url'     => trim((string)($data['image_url'] ?? '')),
            ':link_url'      => trim((string)($data['link_url'] ?? '')),
            ':button_text'   => trim((string)($data['button_text'] ?? '')),
            ':position'      => trim((string)($data['position'] ?? 'home')),
            ':is_active'     => (int)(bool)($data['is_active'] ?? 1),
            ':display_order' => max(0, (int)($data['display_order'] ?? 0)),
            ':starts_at'     => ($data['starts_at'] ?? '') !== '' ? $data['starts_at'] : null,
            ':expires_at'    => ($data['expires_at'] ?? '') !== '' ? $data['expires_at'] : null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateBanner(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE banners SET title = :title, subtitle = :subtitle, image_url = :image_url,
                link_url = :link_url, button_text = :button_text, position = :position,
                is_active = :is_active, display_order = :display_order,
                starts_at = :starts_at, expires_at = :expires_at, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([
            ':title'         => trim((string)($data['title'] ?? '')),
            ':subtitle'      => trim((string)($data['subtitle'] ?? '')),
            ':image_url'     => trim((string)($data['image_url'] ?? '')),
            ':link_url'      => trim((string)($data['link_url'] ?? '')),
            ':button_text'   => trim((string)($data['button_text'] ?? '')),
            ':position'      => trim((string)($data['position'] ?? 'home')),
            ':is_active'     => (int)(bool)($data['is_active'] ?? 1),
            ':display_order' => max(0, (int)($data['display_order'] ?? 0)),
            ':starts_at'     => ($data['starts_at'] ?? '') !== '' ? $data['starts_at'] : null,
            ':expires_at'    => ($data['expires_at'] ?? '') !== '' ? $data['expires_at'] : null,
            ':id'            => $id,
        ]);
    }

    public function deleteBanner(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE banners SET deleted_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Email Templates
    // -----------------------------------------------------------------------

    public function listEmailTemplates(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, template_key, subject, is_active, created_at, updated_at
             FROM email_templates
             WHERE deleted_at IS NULL
             ORDER BY template_key ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findEmailTemplateById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM email_templates WHERE id = :id AND deleted_at IS NULL LIMIT 1'
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
                 WHERE id = :id AND deleted_at IS NULL"
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
            "INSERT INTO email_templates (template_key, subject, body_html, is_active, created_at, updated_at)
             VALUES (:key, :subject, :body, :is_active, NOW(), NOW())"
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
        $stmt = Database::connection()->prepare('UPDATE email_templates SET deleted_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -----------------------------------------------------------------------
    // Legal Documents (FAQ, Pages, Terms, Privacy)
    // -----------------------------------------------------------------------

    public function listLegalDocuments(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, doc_type, title, slug, version, is_active, effective_date, created_at, updated_at
             FROM legal_documents
             WHERE deleted_at IS NULL
             ORDER BY doc_type ASC, created_at DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findLegalDocumentById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM legal_documents WHERE id = :id AND deleted_at IS NULL LIMIT 1'
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
                "UPDATE legal_documents SET doc_type = :type, title = :title, slug = :slug,
                    content = :content, version = :version, is_active = :is_active,
                    effective_date = :effective_date, updated_at = NOW()
                 WHERE id = :id AND deleted_at IS NULL"
            );
            $stmt->execute([
                ':type'           => trim((string)($data['doc_type'] ?? 'page')),
                ':title'          => trim((string)($data['title'] ?? '')),
                ':slug'           => trim((string)($data['slug'] ?? '')),
                ':content'        => (string)($data['content'] ?? ''),
                ':version'        => trim((string)($data['version'] ?? '1.0')),
                ':is_active'      => (int)(bool)($data['is_active'] ?? 1),
                ':effective_date' => ($data['effective_date'] ?? '') !== '' ? $data['effective_date'] : null,
                ':id'             => $id,
            ]);
            return $id;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO legal_documents (doc_type, title, slug, content, version, is_active, effective_date, created_at, updated_at)
             VALUES (:type, :title, :slug, :content, :version, :is_active, :effective_date, NOW(), NOW())"
        );
        $stmt->execute([
            ':type'           => trim((string)($data['doc_type'] ?? 'page')),
            ':title'          => trim((string)($data['title'] ?? '')),
            ':slug'           => trim((string)($data['slug'] ?? '')),
            ':content'        => (string)($data['content'] ?? ''),
            ':version'        => trim((string)($data['version'] ?? '1.0')),
            ':is_active'      => (int)(bool)($data['is_active'] ?? 1),
            ':effective_date' => ($data['effective_date'] ?? '') !== '' ? $data['effective_date'] : null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function deleteLegalDocument(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE legal_documents SET deleted_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
