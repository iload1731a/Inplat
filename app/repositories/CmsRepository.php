<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class CmsRepository
{
    // =========================================================================
    // MEDIA LIBRARY
    // =========================================================================

    public function listMedia(array $filters = [], int $page = 1, int $perPage = 40): array
    {
        $offset = ($page - 1) * $perPage;
        $where  = [];
        $params = [];

        $folder = trim((string)($filters['folder'] ?? ''));
        if ($folder !== '') {
            $where[] = 'm.folder = :folder';
            $params[':folder'] = $folder;
        }

        $mime = trim((string)($filters['mime'] ?? ''));
        if ($mime !== '') {
            $where[] = 'm.mime_type LIKE :mime';
            $params[':mime'] = $mime . '%';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(m.original_name LIKE :search OR m.alt_text LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $pdo = Database::connection();
        $s = $pdo->prepare("SELECT COUNT(*) FROM media_library m $whereClause");
        $s->execute($params);
        $total = (int)$s->fetchColumn();

        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;
        $stmt = $pdo->prepare(
            "SELECT m.*, au.username AS uploader_name
             FROM media_library m
             LEFT JOIN admin_users au ON au.id = m.uploaded_by
             $whereClause
             ORDER BY m.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            if ($k === ':limit' || $k === ':offset') {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();

        return ['items' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    public function findMediaById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM media_library WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function insertMedia(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO media_library
                (filename, original_name, file_path, file_url, mime_type, file_size, width, height, alt_text, caption, folder, uploaded_by, created_at)
             VALUES
                (:filename, :original_name, :file_path, :file_url, :mime_type, :file_size, :width, :height, :alt_text, :caption, :folder, :uploaded_by, NOW())"
        );
        $stmt->execute([
            ':filename'      => (string)($data['filename'] ?? ''),
            ':original_name' => (string)($data['original_name'] ?? ''),
            ':file_path'     => (string)($data['file_path'] ?? ''),
            ':file_url'      => (string)($data['file_url'] ?? ''),
            ':mime_type'     => (string)($data['mime_type'] ?? ''),
            ':file_size'     => (int)($data['file_size'] ?? 0),
            ':width'         => isset($data['width']) ? (int)$data['width'] : null,
            ':height'        => isset($data['height']) ? (int)$data['height'] : null,
            ':alt_text'      => (string)($data['alt_text'] ?? ''),
            ':caption'       => (string)($data['caption'] ?? ''),
            ':folder'        => (string)($data['folder'] ?? 'general'),
            ':uploaded_by'   => (int)($data['uploaded_by'] ?? 0),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateMedia(int $id, array $data): void
    {
        Database::connection()->prepare(
            "UPDATE media_library SET alt_text = :alt_text, caption = :caption, folder = :folder WHERE id = :id"
        )->execute([
            ':alt_text' => (string)($data['alt_text'] ?? ''),
            ':caption'  => (string)($data['caption'] ?? ''),
            ':folder'   => (string)($data['folder'] ?? 'general'),
            ':id'       => $id,
        ]);
    }

    public function deleteMedia(int $id): ?string
    {
        $row = $this->findMediaById($id);
        if ($row === null) {
            return null;
        }
        Database::connection()->prepare('DELETE FROM media_library WHERE id = :id')
            ->execute([':id' => $id]);
        return (string)$row['file_path'];
    }

    public function mediaFolders(): array
    {
        $stmt = Database::connection()->query(
            "SELECT folder, COUNT(*) AS cnt FROM media_library GROUP BY folder ORDER BY folder ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // CMS PAGES
    // =========================================================================

    public function listPages(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['p.deleted_at IS NULL'];
        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }

        $type = trim((string)($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'p.page_type = :type';
            $params[':type'] = $type;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.title LIKE :search OR p.slug LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $pdo         = Database::connection();

        $s = $pdo->prepare("SELECT COUNT(*) FROM cms_pages p $whereClause");
        $s->execute($params);
        $total = (int)$s->fetchColumn();

        $offset            = ($page - 1) * $perPage;
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $pdo->prepare(
            "SELECT p.id, p.title, p.slug, p.page_type, p.status, p.layout, p.template,
                    p.sort_order, p.published_at, p.created_at, p.updated_at,
                    COALESCE(au.full_name, au.username) AS author_name
             FROM cms_pages p
             LEFT JOIN admin_users au ON au.id = p.created_by
             $whereClause
             ORDER BY p.sort_order ASC, p.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            if ($k === ':limit' || $k === ':offset') {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();

        return ['items' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    public function findPageById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM cms_pages WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findPageBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM cms_pages WHERE slug = :slug AND status = 'published' AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createPage(array $data, int $adminId): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO cms_pages
                (title, slug, page_type, status, content, excerpt, featured_image,
                 meta_title, meta_description, meta_keywords, og_title, og_description, og_image,
                 canonical_url, no_index, layout, template, sort_order, created_by,
                 published_at, created_at, updated_at)
             VALUES
                (:title, :slug, :page_type, :status, :content, :excerpt, :featured_image,
                 :meta_title, :meta_description, :meta_keywords, :og_title, :og_description, :og_image,
                 :canonical_url, :no_index, :layout, :template, :sort_order, :created_by,
                 :published_at, NOW(), NOW())"
        );
        $stmt->execute($this->pageBinds($data, $adminId));
        return (int)$pdo->lastInsertId();
    }

    public function updatePage(int $id, array $data, int $adminId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE cms_pages SET
                title = :title, slug = :slug, page_type = :page_type, status = :status,
                content = :content, excerpt = :excerpt, featured_image = :featured_image,
                meta_title = :meta_title, meta_description = :meta_description,
                meta_keywords = :meta_keywords, og_title = :og_title, og_description = :og_description,
                og_image = :og_image, canonical_url = :canonical_url, no_index = :no_index,
                layout = :layout, template = :template, sort_order = :sort_order,
                updated_by = :updated_by, published_at = :published_at, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        $binds         = $this->pageBinds($data, $adminId);
        $binds[':updated_by'] = $adminId;
        $binds[':id']         = $id;
        $stmt->execute($binds);
    }

    public function deletePage(int $id): void
    {
        Database::connection()->prepare(
            'UPDATE cms_pages SET deleted_at = NOW() WHERE id = :id'
        )->execute([':id' => $id]);
    }

    private function pageBinds(array $data, int $adminId): array
    {
        $status = trim((string)($data['status'] ?? 'draft'));
        return [
            ':title'            => trim((string)($data['title'] ?? '')),
            ':slug'             => trim((string)($data['slug'] ?? '')),
            ':page_type'        => trim((string)($data['page_type'] ?? 'static')),
            ':status'           => $status,
            ':content'          => (string)($data['content'] ?? ''),
            ':excerpt'          => (string)($data['excerpt'] ?? ''),
            ':featured_image'   => trim((string)($data['featured_image'] ?? '')),
            ':meta_title'       => trim((string)($data['meta_title'] ?? '')),
            ':meta_description' => trim((string)($data['meta_description'] ?? '')),
            ':meta_keywords'    => trim((string)($data['meta_keywords'] ?? '')),
            ':og_title'         => trim((string)($data['og_title'] ?? '')),
            ':og_description'   => trim((string)($data['og_description'] ?? '')),
            ':og_image'         => trim((string)($data['og_image'] ?? '')),
            ':canonical_url'    => trim((string)($data['canonical_url'] ?? '')),
            ':no_index'         => (int)(bool)($data['no_index'] ?? 0),
            ':layout'           => trim((string)($data['layout'] ?? 'default')),
            ':template'         => trim((string)($data['template'] ?? 'page')),
            ':sort_order'       => max(0, (int)($data['sort_order'] ?? 0)),
            ':created_by'       => $adminId,
            ':published_at'     => $status === 'published' ? date('Y-m-d H:i:s') : null,
        ];
    }

    // Page builder sections
    public function pageSections(int $pageId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM page_builder_sections WHERE page_id = :page_id ORDER BY sort_order ASC"
        );
        $stmt->bindValue(':page_id', $pageId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function savePageSections(int $pageId, array $sections): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM page_builder_sections WHERE page_id = :page_id')
            ->execute([':page_id' => $pageId]);

        if ($sections === []) {
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO page_builder_sections (page_id, section_type, section_data, sort_order, is_visible)
             VALUES (:page_id, :section_type, :section_data, :sort_order, :is_visible)"
        );
        foreach ($sections as $i => $sec) {
            $stmt->execute([
                ':page_id'      => $pageId,
                ':section_type' => trim((string)($sec['section_type'] ?? 'text')),
                ':section_data' => json_encode($sec['section_data'] ?? []),
                ':sort_order'   => (int)($sec['sort_order'] ?? $i),
                ':is_visible'   => (int)(bool)($sec['is_visible'] ?? 1),
            ]);
        }
    }

    // =========================================================================
    // BLOG CATEGORIES
    // =========================================================================

    public function listBlogCategories(): array
    {
        $stmt = Database::connection()->query(
            "SELECT * FROM blog_categories ORDER BY sort_order ASC, name ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findBlogCategoryById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM blog_categories WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findBlogCategoryBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM blog_categories WHERE slug = :slug AND is_active = 1 LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createBlogCategory(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO blog_categories (name, slug, description, featured_image, sort_order, is_active, meta_title, meta_description)
             VALUES (:name, :slug, :description, :featured_image, :sort_order, :is_active, :meta_title, :meta_description)"
        );
        $stmt->execute($this->blogCatBinds($data));
        return (int)$pdo->lastInsertId();
    }

    public function updateBlogCategory(int $id, array $data): void
    {
        $binds = $this->blogCatBinds($data);
        $binds[':id'] = $id;
        Database::connection()->prepare(
            "UPDATE blog_categories SET name = :name, slug = :slug, description = :description,
             featured_image = :featured_image, sort_order = :sort_order, is_active = :is_active,
             meta_title = :meta_title, meta_description = :meta_description WHERE id = :id"
        )->execute($binds);
    }

    public function deleteBlogCategory(int $id): void
    {
        Database::connection()->prepare('DELETE FROM blog_categories WHERE id = :id')
            ->execute([':id' => $id]);
    }

    private function blogCatBinds(array $data): array
    {
        return [
            ':name'             => trim((string)($data['name'] ?? '')),
            ':slug'             => trim((string)($data['slug'] ?? '')),
            ':description'      => trim((string)($data['description'] ?? '')),
            ':featured_image'   => trim((string)($data['featured_image'] ?? '')),
            ':sort_order'       => max(0, (int)($data['sort_order'] ?? 0)),
            ':is_active'        => (int)(bool)($data['is_active'] ?? 1),
            ':meta_title'       => trim((string)($data['meta_title'] ?? '')),
            ':meta_description' => trim((string)($data['meta_description'] ?? '')),
        ];
    }

    // =========================================================================
    // BLOG POSTS
    // =========================================================================

    public function listPosts(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['p.deleted_at IS NULL'];
        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }

        $type = trim((string)($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'p.post_type = :type';
            $params[':type'] = $type;
        }

        $cat = (int)($filters['category_id'] ?? 0);
        if ($cat > 0) {
            $where[] = 'p.category_id = :cat';
            $params[':cat'] = $cat;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.title LIKE :search OR p.excerpt LIKE :search OR p.tags LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $pdo         = Database::connection();

        $s = $pdo->prepare("SELECT COUNT(*) FROM blog_posts p $whereClause");
        $s->execute($params);
        $total = (int)$s->fetchColumn();

        $offset            = ($page - 1) * $perPage;
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $pdo->prepare(
            "SELECT p.id, p.title, p.slug, p.post_type, p.status, p.is_featured, p.is_pinned,
                    p.view_count, p.reading_time, p.published_at, p.created_at,
                    bc.name AS category_name,
                    COALESCE(au.full_name, au.username) AS author_name
             FROM blog_posts p
             LEFT JOIN blog_categories bc ON bc.id = p.category_id
             LEFT JOIN admin_users au ON au.id = p.author_id
             $whereClause
             ORDER BY p.is_pinned DESC, p.published_at DESC, p.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            if ($k === ':limit' || $k === ':offset') {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();

        return ['items' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    public function listPublishedPosts(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $where  = ["p.status = 'published'", 'p.deleted_at IS NULL'];
        $params = [];

        $type = trim((string)($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'p.post_type = :type';
            $params[':type'] = $type;
        }

        $cat = trim((string)($filters['category'] ?? ''));
        if ($cat !== '') {
            $where[] = 'bc.slug = :cat';
            $params[':cat'] = $cat;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.title LIKE :search OR p.excerpt LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $pdo         = Database::connection();

        $s = $pdo->prepare("SELECT COUNT(*) FROM blog_posts p LEFT JOIN blog_categories bc ON bc.id = p.category_id $whereClause");
        $s->execute($params);
        $total = (int)$s->fetchColumn();

        $offset            = ($page - 1) * $perPage;
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $pdo->prepare(
            "SELECT p.id, p.title, p.slug, p.post_type, p.excerpt, p.featured_image,
                    p.reading_time, p.view_count, p.tags, p.published_at,
                    bc.name AS category_name, bc.slug AS category_slug,
                    COALESCE(au.full_name, au.username) AS author_name
             FROM blog_posts p
             LEFT JOIN blog_categories bc ON bc.id = p.category_id
             LEFT JOIN admin_users au ON au.id = p.author_id
             $whereClause
             ORDER BY p.is_pinned DESC, p.published_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            if ($k === ':limit' || $k === ':offset') {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();

        return ['items' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    public function findPostById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.*, bc.name AS category_name, bc.slug AS category_slug,
                    COALESCE(au.full_name, au.username) AS author_name
             FROM blog_posts p
             LEFT JOIN blog_categories bc ON bc.id = p.category_id
             LEFT JOIN admin_users au ON au.id = p.author_id
             WHERE p.id = :id AND p.deleted_at IS NULL LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findPostBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.*, bc.name AS category_name, bc.slug AS category_slug,
                    COALESCE(au.full_name, au.username) AS author_name
             FROM blog_posts p
             LEFT JOIN blog_categories bc ON bc.id = p.category_id
             LEFT JOIN admin_users au ON au.id = p.author_id
             WHERE p.slug = :slug AND p.status = 'published' AND p.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createPost(array $data, int $adminId): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO blog_posts
                (title, slug, post_type, category_id, author_id, status, content, excerpt,
                 featured_image, tags, reading_time, is_featured, is_pinned, allow_comments,
                 meta_title, meta_description, meta_keywords, og_title, og_description, og_image,
                 canonical_url, scheduled_at, published_at, created_at, updated_at)
             VALUES
                (:title, :slug, :post_type, :category_id, :author_id, :status, :content, :excerpt,
                 :featured_image, :tags, :reading_time, :is_featured, :is_pinned, :allow_comments,
                 :meta_title, :meta_description, :meta_keywords, :og_title, :og_description, :og_image,
                 :canonical_url, :scheduled_at, :published_at, NOW(), NOW())"
        );
        $stmt->execute($this->postBinds($data, $adminId));
        return (int)$pdo->lastInsertId();
    }

    public function updatePost(int $id, array $data, int $adminId): void
    {
        $binds       = $this->postBinds($data, $adminId);
        $binds[':id'] = $id;
        Database::connection()->prepare(
            "UPDATE blog_posts SET
                title = :title, slug = :slug, post_type = :post_type, category_id = :category_id,
                status = :status, content = :content, excerpt = :excerpt, featured_image = :featured_image,
                tags = :tags, reading_time = :reading_time, is_featured = :is_featured, is_pinned = :is_pinned,
                allow_comments = :allow_comments, meta_title = :meta_title, meta_description = :meta_description,
                meta_keywords = :meta_keywords, og_title = :og_title, og_description = :og_description,
                og_image = :og_image, canonical_url = :canonical_url, scheduled_at = :scheduled_at,
                published_at = :published_at, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        )->execute($binds);
    }

    public function deletePost(int $id): void
    {
        Database::connection()->prepare(
            'UPDATE blog_posts SET deleted_at = NOW() WHERE id = :id'
        )->execute([':id' => $id]);
    }

    public function incrementPostViews(int $id): void
    {
        Database::connection()->prepare(
            'UPDATE blog_posts SET view_count = view_count + 1 WHERE id = :id'
        )->execute([':id' => $id]);
    }

    public function recentPosts(int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, title, slug, featured_image, published_at, view_count
             FROM blog_posts
             WHERE status = 'published' AND deleted_at IS NULL
             ORDER BY published_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    private function postBinds(array $data, int $adminId): array
    {
        $status    = trim((string)($data['status'] ?? 'draft'));
        $catId     = (int)($data['category_id'] ?? 0);
        $wordCount = str_word_count(strip_tags((string)($data['content'] ?? '')));
        $readTime  = (int)($data['reading_time'] ?? max(1, (int)ceil($wordCount / 200)));

        return [
            ':title'            => trim((string)($data['title'] ?? '')),
            ':slug'             => trim((string)($data['slug'] ?? '')),
            ':post_type'        => trim((string)($data['post_type'] ?? 'blog')),
            ':category_id'      => $catId > 0 ? $catId : null,
            ':author_id'        => $adminId,
            ':status'           => $status,
            ':content'          => (string)($data['content'] ?? ''),
            ':excerpt'          => trim((string)($data['excerpt'] ?? '')),
            ':featured_image'   => trim((string)($data['featured_image'] ?? '')),
            ':tags'             => trim((string)($data['tags'] ?? '')),
            ':reading_time'     => $readTime,
            ':is_featured'      => (int)(bool)($data['is_featured'] ?? 0),
            ':is_pinned'        => (int)(bool)($data['is_pinned'] ?? 0),
            ':allow_comments'   => (int)(bool)($data['allow_comments'] ?? 1),
            ':meta_title'       => trim((string)($data['meta_title'] ?? '')),
            ':meta_description' => trim((string)($data['meta_description'] ?? '')),
            ':meta_keywords'    => trim((string)($data['meta_keywords'] ?? '')),
            ':og_title'         => trim((string)($data['og_title'] ?? '')),
            ':og_description'   => trim((string)($data['og_description'] ?? '')),
            ':og_image'         => trim((string)($data['og_image'] ?? '')),
            ':canonical_url'    => trim((string)($data['canonical_url'] ?? '')),
            ':scheduled_at'     => ($data['scheduled_at'] ?? '') !== '' ? $data['scheduled_at'] : null,
            ':published_at'     => $status === 'published' ? date('Y-m-d H:i:s') : null,
        ];
    }

    // =========================================================================
    // FAQ CATEGORIES
    // =========================================================================

    public function listFaqCategories(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $stmt  = Database::connection()->query(
            "SELECT fc.*, COUNT(f.id) AS faq_count
             FROM faq_categories fc
             LEFT JOIN faqs f ON f.category_id = fc.id AND f.is_active = 1
             $where
             GROUP BY fc.id ORDER BY fc.sort_order ASC, fc.name ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findFaqCategoryById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM faq_categories WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function saveFaqCategory(array $data): int
    {
        $pdo = Database::connection();
        $id  = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare(
                "UPDATE faq_categories SET name=:name, slug=:slug, description=:description, icon=:icon, sort_order=:sort_order, is_active=:active WHERE id=:id"
            )->execute([
                ':name' => trim((string)($data['name'] ?? '')), ':slug' => trim((string)($data['slug'] ?? '')),
                ':description' => trim((string)($data['description'] ?? '')), ':icon' => trim((string)($data['icon'] ?? 'fa-question-circle')),
                ':sort_order' => (int)($data['sort_order'] ?? 0), ':active' => (int)(bool)($data['is_active'] ?? 1), ':id' => $id,
            ]);
            return $id;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO faq_categories (name,slug,description,icon,sort_order,is_active) VALUES (:name,:slug,:description,:icon,:sort_order,:active)"
        );
        $stmt->execute([
            ':name' => trim((string)($data['name'] ?? '')), ':slug' => trim((string)($data['slug'] ?? '')),
            ':description' => trim((string)($data['description'] ?? '')), ':icon' => trim((string)($data['icon'] ?? 'fa-question-circle')),
            ':sort_order' => (int)($data['sort_order'] ?? 0), ':active' => (int)(bool)($data['is_active'] ?? 1),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function deleteFaqCategory(int $id): void
    {
        Database::connection()->prepare('DELETE FROM faq_categories WHERE id=:id')->execute([':id' => $id]);
    }

    // =========================================================================
    // FAQs
    // =========================================================================

    public function listFaqs(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $where  = [];
        $params = [];

        $cat = (int)($filters['category_id'] ?? 0);
        if ($cat > 0) {
            $where[] = 'f.category_id = :cat';
            $params[':cat'] = $cat;
        }

        $active = $filters['active_only'] ?? false;
        if ($active) {
            $where[] = 'f.is_active = 1';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(f.question LIKE :search OR f.answer LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $pdo         = Database::connection();

        $s = $pdo->prepare("SELECT COUNT(*) FROM faqs f $whereClause");
        $s->execute($params);
        $total = (int)$s->fetchColumn();

        $offset            = ($page - 1) * $perPage;
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $pdo->prepare(
            "SELECT f.*, fc.name AS category_name
             FROM faqs f
             LEFT JOIN faq_categories fc ON fc.id = f.category_id
             $whereClause
             ORDER BY f.category_id ASC, f.sort_order ASC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            if ($k === ':limit' || $k === ':offset') {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();

        return ['items' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    public function findFaqById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM faqs WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function saveFaq(array $data, int $adminId): int
    {
        $pdo = Database::connection();
        $id  = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare(
                "UPDATE faqs SET category_id=:cat, question=:question, answer=:answer, sort_order=:sort_order, is_active=:active, updated_at=NOW() WHERE id=:id"
            )->execute([
                ':cat' => ((int)($data['category_id'] ?? 0)) ?: null,
                ':question' => trim((string)($data['question'] ?? '')),
                ':answer' => (string)($data['answer'] ?? ''),
                ':sort_order' => (int)($data['sort_order'] ?? 0),
                ':active' => (int)(bool)($data['is_active'] ?? 1),
                ':id' => $id,
            ]);
            return $id;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO faqs (category_id, question, answer, sort_order, is_active, created_by, created_at, updated_at)
             VALUES (:cat, :question, :answer, :sort_order, :active, :created_by, NOW(), NOW())"
        );
        $stmt->execute([
            ':cat' => ((int)($data['category_id'] ?? 0)) ?: null,
            ':question' => trim((string)($data['question'] ?? '')),
            ':answer' => (string)($data['answer'] ?? ''),
            ':sort_order' => (int)($data['sort_order'] ?? 0),
            ':active' => (int)(bool)($data['is_active'] ?? 1),
            ':created_by' => $adminId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function deleteFaq(int $id): void
    {
        Database::connection()->prepare('DELETE FROM faqs WHERE id=:id')->execute([':id' => $id]);
    }

    public function faqVote(int $id, string $type): void
    {
        $col = $type === 'yes' ? 'helpful_yes' : 'helpful_no';
        Database::connection()->prepare("UPDATE faqs SET $col = $col + 1 WHERE id = :id")
            ->execute([':id' => $id]);
    }

    // =========================================================================
    // TESTIMONIALS
    // =========================================================================

    public function listTestimonials(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if ($filters['active_only'] ?? false) {
            $where[] = 'is_active = 1';
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = Database::connection()->prepare(
            "SELECT * FROM testimonials $whereClause ORDER BY sort_order ASC, created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findTestimonialById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM testimonials WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function saveTestimonial(array $data, int $adminId): int
    {
        $pdo = Database::connection();
        $id  = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare(
                "UPDATE testimonials SET name=:name, title=:title, company=:company, avatar=:avatar,
                 content=:content, rating=:rating, platform=:platform, is_featured=:featured, is_active=:active,
                 sort_order=:sort_order, updated_at=NOW() WHERE id=:id"
            )->execute($this->testimonialBinds($data, $adminId) + [':id' => $id]);
            return $id;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO testimonials (name, title, company, avatar, content, rating, platform, is_featured, is_active, sort_order, created_by, created_at, updated_at)
             VALUES (:name,:title,:company,:avatar,:content,:rating,:platform,:featured,:active,:sort_order,:created_by,NOW(),NOW())"
        );
        $stmt->execute($this->testimonialBinds($data, $adminId));
        return (int)$pdo->lastInsertId();
    }

    public function deleteTestimonial(int $id): void
    {
        Database::connection()->prepare('DELETE FROM testimonials WHERE id=:id')->execute([':id' => $id]);
    }

    private function testimonialBinds(array $data, int $adminId): array
    {
        return [
            ':name'       => trim((string)($data['name'] ?? '')),
            ':title'      => trim((string)($data['title'] ?? '')),
            ':company'    => trim((string)($data['company'] ?? '')),
            ':avatar'     => trim((string)($data['avatar'] ?? '')),
            ':content'    => trim((string)($data['content'] ?? '')),
            ':rating'     => min(5, max(1, (int)($data['rating'] ?? 5))),
            ':platform'   => trim((string)($data['platform'] ?? '')),
            ':featured'   => (int)(bool)($data['is_featured'] ?? 0),
            ':active'     => (int)(bool)($data['is_active'] ?? 1),
            ':sort_order' => (int)($data['sort_order'] ?? 0),
            ':created_by' => $adminId,
        ];
    }

    // =========================================================================
    // PLATFORM FEATURES
    // =========================================================================

    public function listFeatures(string $section = ''): array
    {
        $where  = [];
        $params = [];
        if ($section !== '') {
            $where[] = 'section = :section';
            $params[':section'] = $section;
        }
        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = Database::connection()->prepare(
            "SELECT * FROM platform_features $whereClause ORDER BY sort_order ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findFeatureById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM platform_features WHERE id=:id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function saveFeature(array $data, int $adminId): int
    {
        $pdo = Database::connection();
        $id  = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare(
                "UPDATE platform_features SET section=:section, icon=:icon, title=:title, description=:description,
                 badge=:badge, badge_color=:badge_color, sort_order=:sort_order, is_active=:active, updated_at=NOW() WHERE id=:id"
            )->execute($this->featureBinds($data, $adminId) + [':id' => $id]);
            return $id;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO platform_features (section, icon, title, description, badge, badge_color, sort_order, is_active, created_by, created_at, updated_at)
             VALUES (:section,:icon,:title,:description,:badge,:badge_color,:sort_order,:active,:created_by,NOW(),NOW())"
        );
        $stmt->execute($this->featureBinds($data, $adminId));
        return (int)$pdo->lastInsertId();
    }

    public function deleteFeature(int $id): void
    {
        Database::connection()->prepare('DELETE FROM platform_features WHERE id=:id')->execute([':id' => $id]);
    }

    private function featureBinds(array $data, int $adminId): array
    {
        return [
            ':section'     => trim((string)($data['section'] ?? 'home')),
            ':icon'        => trim((string)($data['icon'] ?? 'fa-star')),
            ':title'       => trim((string)($data['title'] ?? '')),
            ':description' => trim((string)($data['description'] ?? '')),
            ':badge'       => trim((string)($data['badge'] ?? '')),
            ':badge_color' => trim((string)($data['badge_color'] ?? 'primary')),
            ':sort_order'  => (int)($data['sort_order'] ?? 0),
            ':active'      => (int)(bool)($data['is_active'] ?? 1),
            ':created_by'  => $adminId,
        ];
    }

    // =========================================================================
    // PRICING PLANS
    // =========================================================================

    public function listPricingPlans(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $stmt  = Database::connection()->query(
            "SELECT * FROM pricing_plans $where ORDER BY sort_order ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findPricingPlanById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pricing_plans WHERE id=:id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function savePricingPlan(array $data): int
    {
        $pdo      = Database::connection();
        $id       = (int)($data['id'] ?? 0);
        $features = is_array($data['features'] ?? null)
            ? json_encode($data['features'])
            : (string)($data['features'] ?? '[]');

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE pricing_plans SET name=:name, slug=:slug, description=:description, price_monthly=:monthly,
                 price_yearly=:yearly, currency=:currency, features=:features, badge=:badge, badge_color=:badge_color,
                 is_featured=:featured, is_active=:active, cta_text=:cta_text, cta_url=:cta_url,
                 sort_order=:sort_order, updated_at=NOW() WHERE id=:id"
            )->execute($this->planBinds($data, $features) + [':id' => $id]);
            return $id;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO pricing_plans (name, slug, description, price_monthly, price_yearly, currency, features, badge, badge_color, is_featured, is_active, cta_text, cta_url, sort_order)
             VALUES (:name,:slug,:description,:monthly,:yearly,:currency,:features,:badge,:badge_color,:featured,:active,:cta_text,:cta_url,:sort_order)"
        );
        $stmt->execute($this->planBinds($data, $features));
        return (int)$pdo->lastInsertId();
    }

    public function deletePricingPlan(int $id): void
    {
        Database::connection()->prepare('DELETE FROM pricing_plans WHERE id=:id')->execute([':id' => $id]);
    }

    private function planBinds(array $data, string $features): array
    {
        return [
            ':name'        => trim((string)($data['name'] ?? '')),
            ':slug'        => trim((string)($data['slug'] ?? '')),
            ':description' => trim((string)($data['description'] ?? '')),
            ':monthly'     => (float)($data['price_monthly'] ?? 0),
            ':yearly'      => (float)($data['price_yearly'] ?? 0),
            ':currency'    => trim((string)($data['currency'] ?? 'USD')),
            ':features'    => $features,
            ':badge'       => trim((string)($data['badge'] ?? '')),
            ':badge_color' => trim((string)($data['badge_color'] ?? 'warning')),
            ':featured'    => (int)(bool)($data['is_featured'] ?? 0),
            ':active'      => (int)(bool)($data['is_active'] ?? 1),
            ':cta_text'    => trim((string)($data['cta_text'] ?? 'Get Started')),
            ':cta_url'     => trim((string)($data['cta_url'] ?? '/register')),
            ':sort_order'  => (int)($data['sort_order'] ?? 0),
        ];
    }

    // =========================================================================
    // CONTACT MESSAGES
    // =========================================================================

    public function listContactMessages(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = [];
        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(name LIKE :search OR email LIKE :search OR subject LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $pdo         = Database::connection();

        $s = $pdo->prepare("SELECT COUNT(*) FROM contact_messages $whereClause");
        $s->execute($params);
        $total = (int)$s->fetchColumn();

        $offset            = ($page - 1) * $perPage;
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $pdo->prepare(
            "SELECT * FROM contact_messages $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            if ($k === ':limit' || $k === ':offset') {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();

        return ['items' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    public function findContactMessageById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM contact_messages WHERE id=:id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createContactMessage(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO contact_messages (name, email, subject, department, message, ip_address, user_agent, created_at)
             VALUES (:name, :email, :subject, :department, :message, :ip, :ua, NOW())"
        );
        $stmt->execute([
            ':name'       => trim((string)($data['name'] ?? '')),
            ':email'      => trim((string)($data['email'] ?? '')),
            ':subject'    => trim((string)($data['subject'] ?? '')),
            ':department' => trim((string)($data['department'] ?? 'general')),
            ':message'    => trim((string)($data['message'] ?? '')),
            ':ip'         => trim((string)($data['ip_address'] ?? '')),
            ':ua'         => substr((string)($data['user_agent'] ?? ''), 0, 255),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateContactStatus(int $id, string $status): void
    {
        Database::connection()->prepare(
            "UPDATE contact_messages SET status=:status WHERE id=:id"
        )->execute([':status' => $status, ':id' => $id]);
    }

    public function replyContactMessage(int $id, int $adminId, string $message): void
    {
        Database::connection()->prepare(
            "UPDATE contact_messages SET status='replied', replied_by=:admin, replied_at=NOW(), reply_message=:msg WHERE id=:id"
        )->execute([':admin' => $adminId, ':msg' => $message, ':id' => $id]);
    }

    public function deleteContactMessage(int $id): void
    {
        Database::connection()->prepare('DELETE FROM contact_messages WHERE id=:id')->execute([':id' => $id]);
    }

    public function contactKpis(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status='unread') AS unread,
                SUM(status='read') AS read_cnt,
                SUM(status='replied') AS replied,
                SUM(status='spam') AS spam,
                SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS last7d
             FROM contact_messages"
        );
        return $stmt->fetch() ?: [];
    }

    // =========================================================================
    // SEO SETTINGS
    // =========================================================================

    public function listSeoSettings(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM seo_settings ORDER BY context ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function findSeoByContext(string $context): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM seo_settings WHERE context=:ctx LIMIT 1');
        $stmt->execute([':ctx' => $context]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function saveSeoSettings(string $context, array $data): void
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO seo_settings (context, meta_title, meta_description, meta_keywords, og_title, og_description, og_image, twitter_card, robots, canonical_base, schema_markup, custom_head)
             VALUES (:ctx, :mt, :md, :mk, :ot, :od, :oi, :tc, :robots, :base, :schema, :head)
             ON DUPLICATE KEY UPDATE
                meta_title=:mt, meta_description=:md, meta_keywords=:mk, og_title=:ot, og_description=:od,
                og_image=:oi, twitter_card=:tc, robots=:robots, canonical_base=:base, schema_markup=:schema,
                custom_head=:head, updated_at=NOW()"
        );
        $stmt->execute([
            ':ctx'    => $context,
            ':mt'     => trim((string)($data['meta_title'] ?? '')),
            ':md'     => trim((string)($data['meta_description'] ?? '')),
            ':mk'     => trim((string)($data['meta_keywords'] ?? '')),
            ':ot'     => trim((string)($data['og_title'] ?? '')),
            ':od'     => trim((string)($data['og_description'] ?? '')),
            ':oi'     => trim((string)($data['og_image'] ?? '')),
            ':tc'     => trim((string)($data['twitter_card'] ?? 'summary_large_image')),
            ':robots' => trim((string)($data['robots'] ?? 'index,follow')),
            ':base'   => trim((string)($data['canonical_base'] ?? '')),
            ':schema' => trim((string)($data['schema_markup'] ?? '')),
            ':head'   => trim((string)($data['custom_head'] ?? '')),
        ]);
    }

    // =========================================================================
    // HOMEPAGE SECTIONS
    // =========================================================================

    public function listHomepageSections(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM homepage_sections ORDER BY sort_order ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function findHomepageSectionByKey(string $key): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM homepage_sections WHERE section_key=:key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function saveHomepageSection(string $key, array $data): void
    {
        Database::connection()->prepare(
            "UPDATE homepage_sections SET section_title=:title, section_data=:data, is_enabled=:enabled, sort_order=:sort WHERE section_key=:key"
        )->execute([
            ':title'   => trim((string)($data['section_title'] ?? $key)),
            ':data'    => json_encode($data['section_data'] ?? []),
            ':enabled' => (int)(bool)($data['is_enabled'] ?? 1),
            ':sort'    => (int)($data['sort_order'] ?? 0),
            ':key'     => $key,
        ]);
    }

    public function updateHomepageSectionOrder(array $order): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE homepage_sections SET sort_order=:sort WHERE section_key=:key'
        );
        foreach ($order as $i => $key) {
            $stmt->execute([':sort' => $i + 1, ':key' => (string)$key]);
        }
    }

    // =========================================================================
    // SITEMAP
    // =========================================================================

    public function sitemapPages(): array
    {
        $stmt = Database::connection()->query(
            "SELECT slug, updated_at FROM cms_pages WHERE status='published' AND deleted_at IS NULL ORDER BY updated_at DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function sitemapPosts(): array
    {
        $stmt = Database::connection()->query(
            "SELECT slug, post_type, published_at, updated_at FROM blog_posts WHERE status='published' AND deleted_at IS NULL ORDER BY published_at DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // DASHBOARD KPIs
    // =========================================================================

    public function cmsDashboardKpis(): array
    {
        $pdo = Database::connection();

        $pages = (int)$pdo->query("SELECT COUNT(*) FROM cms_pages WHERE deleted_at IS NULL")->fetchColumn();
        $pubPages = (int)$pdo->query("SELECT COUNT(*) FROM cms_pages WHERE status='published' AND deleted_at IS NULL")->fetchColumn();
        $posts = (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE deleted_at IS NULL")->fetchColumn();
        $pubPosts = (int)$pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status='published' AND deleted_at IS NULL")->fetchColumn();
        $faqs = (int)$pdo->query("SELECT COUNT(*) FROM faqs WHERE is_active=1")->fetchColumn();
        $testimonials = (int)$pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_active=1")->fetchColumn();
        $media = (int)$pdo->query("SELECT COUNT(*) FROM media_library")->fetchColumn();
        $mediaSize = (int)$pdo->query("SELECT COALESCE(SUM(file_size),0) FROM media_library")->fetchColumn();
        $contacts = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='unread'")->fetchColumn();

        return [
            'total_pages'       => $pages,
            'published_pages'   => $pubPages,
            'total_posts'       => $posts,
            'published_posts'   => $pubPosts,
            'total_faqs'        => $faqs,
            'total_testimonials'=> $testimonials,
            'total_media'       => $media,
            'media_size_bytes'  => $mediaSize,
            'unread_contacts'   => $contacts,
        ];
    }
}
