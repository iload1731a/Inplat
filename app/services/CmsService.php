<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminManagementRepository;
use App\Repositories\CmsRepository;
use InvalidArgumentException;
use RuntimeException;

final class CmsService
{
    public function __construct(
        private readonly CmsRepository            $repo    = new CmsRepository(),
        private readonly AdminManagementRepository $mgmt    = new AdminManagementRepository(),
    ) {}

    // =========================================================================
    // ADMIN DASHBOARD
    // =========================================================================

    public function adminDashboard(): array
    {
        return [
            'kpis'             => $this->repo->cmsDashboardKpis(),
            'recentPosts'      => $this->repo->recentPosts(5),
            'homepageSections' => $this->repo->listHomepageSections(),
        ];
    }

    // =========================================================================
    // MEDIA LIBRARY
    // =========================================================================

    public function mediaList(array $filters, int $page, int $perPage): array
    {
        $result  = $this->repo->listMedia($filters, $page, $perPage);
        $folders = $this->repo->mediaFolders();
        return [
            'items'       => $result['items'],
            'total'       => $result['total'],
            'page'        => $page,
            'perPage'     => $perPage,
            'totalPages'  => $perPage > 0 ? (int)ceil($result['total'] / $perPage) : 1,
            'folders'     => $folders,
        ];
    }

    public function uploadMedia(array $file, array $meta, int $adminId): array
    {
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf', 'video/mp4', 'video/webm',
        ];

        $mime = (string)($file['type'] ?? '');
        if (!in_array($mime, $allowedMimes, true)) {
            throw new InvalidArgumentException('File type not allowed: ' . $mime);
        }

        $maxSize = 20 * 1024 * 1024; // 20 MB
        if ((int)($file['size'] ?? 0) > $maxSize) {
            throw new InvalidArgumentException('File too large. Maximum 20 MB.');
        }

        $uploadDir = app_path('storage/uploads/media/');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new RuntimeException('Cannot create upload directory.');
        }

        $ext      = strtolower((string)pathinfo((string)($file['name'] ?? 'file'), PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(12)) . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $destPath)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        $width = $height = null;
        if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml' && function_exists('getimagesize')) {
            $imgSize = @getimagesize($destPath);
            if ($imgSize !== false) {
                $width  = (int)$imgSize[0];
                $height = (int)$imgSize[1];
            }
        }

        $id = $this->repo->insertMedia([
            'filename'      => $filename,
            'original_name' => (string)($file['name'] ?? $filename),
            'file_path'     => $destPath,
            'file_url'      => '/uploads/media/' . $filename,
            'mime_type'     => $mime,
            'file_size'     => (int)($file['size'] ?? 0),
            'width'         => $width,
            'height'        => $height,
            'alt_text'      => trim((string)($meta['alt_text'] ?? '')),
            'caption'       => trim((string)($meta['caption'] ?? '')),
            'folder'        => trim((string)($meta['folder'] ?? 'general')),
            'uploaded_by'   => $adminId,
        ]);

        return $this->repo->findMediaById($id) ?? [];
    }

    public function updateMedia(int $adminId, int $id, array $data): void
    {
        $row = $this->repo->findMediaById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Media not found.');
        }
        $this->repo->updateMedia($id, $data);
        $this->mgmt->logAdminAction($adminId, 'update_media', 'media_library', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function deleteMedia(int $adminId, int $id): void
    {
        $filePath = $this->repo->deleteMedia($id);
        if ($filePath === null) {
            throw new InvalidArgumentException('Media not found.');
        }
        if ($filePath !== '' && is_file($filePath)) {
            @unlink($filePath);
        }
        $this->mgmt->logAdminAction($adminId, 'delete_media', 'media_library', (string)$id, null, [], RequestContext::ipAddress());
    }

    // =========================================================================
    // CMS PAGES
    // =========================================================================

    public function pagesList(array $filters, int $page, int $perPage): array
    {
        $result = $this->repo->listPages($filters, $page, $perPage);
        return [
            'items'      => $result['items'],
            'total'      => $result['total'],
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $perPage > 0 ? (int)ceil($result['total'] / $perPage) : 1,
        ];
    }

    public function pageDetail(int $id): array
    {
        $page = $this->repo->findPageById($id);
        if ($page === null) {
            throw new InvalidArgumentException('Page not found.');
        }
        $sections = $this->repo->pageSections($id);
        return ['page' => $page, 'sections' => $sections];
    }

    public function createPage(int $adminId, array $payload): int
    {
        $title = trim((string)($payload['title'] ?? ''));
        $slug  = $this->normalizeSlug((string)($payload['slug'] ?? ''), $title);

        if ($title === '') {
            throw new InvalidArgumentException('Page title is required.');
        }

        $payload['slug']  = $slug;
        $id = $this->repo->createPage($payload, $adminId);
        $this->mgmt->logAdminAction($adminId, 'create_cms_page', 'cms_pages', (string)$id, null, ['title' => $title], RequestContext::ipAddress());
        return $id;
    }

    public function updatePage(int $adminId, int $id, array $payload): void
    {
        $existing = $this->repo->findPageById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Page not found.');
        }

        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Page title is required.');
        }

        $payload['slug'] = $this->normalizeSlug((string)($payload['slug'] ?? ''), $title);
        $this->repo->updatePage($id, $payload, $adminId);

        if (isset($payload['sections']) && is_array($payload['sections'])) {
            $this->repo->savePageSections($id, $payload['sections']);
        }

        $this->mgmt->logAdminAction($adminId, 'update_cms_page', 'cms_pages', (string)$id, null, ['title' => $title], RequestContext::ipAddress());
    }

    public function deletePage(int $adminId, int $id): void
    {
        $existing = $this->repo->findPageById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Page not found.');
        }
        $this->repo->deletePage($id);
        $this->mgmt->logAdminAction($adminId, 'delete_cms_page', 'cms_pages', (string)$id, null, [], RequestContext::ipAddress());
    }

    // =========================================================================
    // BLOG
    // =========================================================================

    public function blogDashboard(): array
    {
        $categories = $this->repo->listBlogCategories();
        $recent     = $this->repo->recentPosts(10);
        return ['categories' => $categories, 'recentPosts' => $recent];
    }

    public function blogPostsList(array $filters, int $page, int $perPage): array
    {
        $result     = $this->repo->listPosts($filters, $page, $perPage);
        $categories = $this->repo->listBlogCategories();
        return [
            'items'      => $result['items'],
            'total'      => $result['total'],
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $perPage > 0 ? (int)ceil($result['total'] / $perPage) : 1,
            'categories' => $categories,
        ];
    }

    public function blogPostDetail(int $id): array
    {
        $post = $this->repo->findPostById($id);
        if ($post === null) {
            throw new InvalidArgumentException('Post not found.');
        }
        $categories = $this->repo->listBlogCategories();
        return ['post' => $post, 'categories' => $categories];
    }

    public function createPost(int $adminId, array $payload): int
    {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Post title is required.');
        }
        $payload['slug'] = $this->normalizeSlug((string)($payload['slug'] ?? ''), $title);
        $id = $this->repo->createPost($payload, $adminId);
        $this->mgmt->logAdminAction($adminId, 'create_blog_post', 'blog_posts', (string)$id, null, ['title' => $title], RequestContext::ipAddress());
        return $id;
    }

    public function updatePost(int $adminId, int $id, array $payload): void
    {
        $existing = $this->repo->findPostById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Post not found.');
        }
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Post title is required.');
        }
        $payload['slug'] = $this->normalizeSlug((string)($payload['slug'] ?? ''), $title);
        $this->repo->updatePost($id, $payload, $adminId);
        $this->mgmt->logAdminAction($adminId, 'update_blog_post', 'blog_posts', (string)$id, null, ['title' => $title], RequestContext::ipAddress());
    }

    public function deletePost(int $adminId, int $id): void
    {
        $existing = $this->repo->findPostById($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Post not found.');
        }
        $this->repo->deletePost($id);
        $this->mgmt->logAdminAction($adminId, 'delete_blog_post', 'blog_posts', (string)$id, null, [], RequestContext::ipAddress());
    }

    // =========================================================================
    // FAQ
    // =========================================================================

    public function faqAdminPage(): array
    {
        return [
            'categories' => $this->repo->listFaqCategories(),
            'faqs'       => $this->repo->listFaqs([], 1, 200)['items'],
        ];
    }

    public function saveFaqCategory(int $adminId, array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Category name is required.');
        }
        $data['slug'] = $this->normalizeSlug((string)($data['slug'] ?? ''), $name);
        $id = $this->repo->saveFaqCategory($data);
        $this->mgmt->logAdminAction($adminId, 'save_faq_category', 'faq_categories', (string)$id, null, ['name' => $name], RequestContext::ipAddress());
        return $id;
    }

    public function deleteFaqCategory(int $adminId, int $id): void
    {
        $this->repo->deleteFaqCategory($id);
        $this->mgmt->logAdminAction($adminId, 'delete_faq_category', 'faq_categories', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function saveFaq(int $adminId, array $data): int
    {
        if (trim((string)($data['question'] ?? '')) === '') {
            throw new InvalidArgumentException('Question is required.');
        }
        if (trim((string)($data['answer'] ?? '')) === '') {
            throw new InvalidArgumentException('Answer is required.');
        }
        $id = $this->repo->saveFaq($data, $adminId);
        $this->mgmt->logAdminAction($adminId, 'save_faq', 'faqs', (string)$id, null, [], RequestContext::ipAddress());
        return $id;
    }

    public function deleteFaq(int $adminId, int $id): void
    {
        $this->repo->deleteFaq($id);
        $this->mgmt->logAdminAction($adminId, 'delete_faq', 'faqs', (string)$id, null, [], RequestContext::ipAddress());
    }

    // =========================================================================
    // TESTIMONIALS
    // =========================================================================

    public function testimonialsPage(): array
    {
        return ['testimonials' => $this->repo->listTestimonials()];
    }

    public function saveTestimonial(int $adminId, array $data): int
    {
        if (trim((string)($data['name'] ?? '')) === '') {
            throw new InvalidArgumentException('Name is required.');
        }
        if (trim((string)($data['content'] ?? '')) === '') {
            throw new InvalidArgumentException('Testimonial content is required.');
        }
        $id = $this->repo->saveTestimonial($data, $adminId);
        $this->mgmt->logAdminAction($adminId, 'save_testimonial', 'testimonials', (string)$id, null, [], RequestContext::ipAddress());
        return $id;
    }

    public function deleteTestimonial(int $adminId, int $id): void
    {
        $this->repo->deleteTestimonial($id);
        $this->mgmt->logAdminAction($adminId, 'delete_testimonial', 'testimonials', (string)$id, null, [], RequestContext::ipAddress());
    }

    // =========================================================================
    // FEATURES
    // =========================================================================

    public function featuresPage(): array
    {
        return ['features' => $this->repo->listFeatures()];
    }

    public function saveFeature(int $adminId, array $data): int
    {
        if (trim((string)($data['title'] ?? '')) === '') {
            throw new InvalidArgumentException('Feature title is required.');
        }
        $id = $this->repo->saveFeature($data, $adminId);
        $this->mgmt->logAdminAction($adminId, 'save_feature', 'platform_features', (string)$id, null, [], RequestContext::ipAddress());
        return $id;
    }

    public function deleteFeature(int $adminId, int $id): void
    {
        $this->repo->deleteFeature($id);
        $this->mgmt->logAdminAction($adminId, 'delete_feature', 'platform_features', (string)$id, null, [], RequestContext::ipAddress());
    }

    // =========================================================================
    // PRICING
    // =========================================================================

    public function pricingPage(): array
    {
        return ['plans' => $this->repo->listPricingPlans()];
    }

    public function savePricingPlan(int $adminId, array $data): int
    {
        if (trim((string)($data['name'] ?? '')) === '') {
            throw new InvalidArgumentException('Plan name is required.');
        }
        $data['slug'] = $this->normalizeSlug((string)($data['slug'] ?? ''), (string)($data['name'] ?? ''));

        // Parse features from textarea lines if provided as string
        if (isset($data['features_text']) && !is_array($data['features'] ?? null)) {
            $lines = array_filter(array_map('trim', explode("\n", (string)$data['features_text'])));
            $data['features'] = array_values($lines);
        }

        $id = $this->repo->savePricingPlan($data);
        $this->mgmt->logAdminAction($adminId, 'save_pricing_plan', 'pricing_plans', (string)$id, null, [], RequestContext::ipAddress());
        return $id;
    }

    public function deletePricingPlan(int $adminId, int $id): void
    {
        $this->repo->deletePricingPlan($id);
        $this->mgmt->logAdminAction($adminId, 'delete_pricing_plan', 'pricing_plans', (string)$id, null, [], RequestContext::ipAddress());
    }

    // =========================================================================
    // CONTACT
    // =========================================================================

    public function contactAdminPage(array $filters, int $page): array
    {
        $result = $this->repo->listContactMessages($filters, $page, 20);
        $kpis   = $this->repo->contactKpis();
        return [
            'items'      => $result['items'],
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => (int)ceil($result['total'] / 20),
            'kpis'       => $kpis,
        ];
    }

    public function viewContactMessage(int $id): array
    {
        $msg = $this->repo->findContactMessageById($id);
        if ($msg === null) {
            throw new InvalidArgumentException('Message not found.');
        }
        if ((string)$msg['status'] === 'unread') {
            $this->repo->updateContactStatus($id, 'read');
        }
        return ['message' => $msg];
    }

    public function replyContact(int $adminId, int $id, string $replyText): void
    {
        if (trim($replyText) === '') {
            throw new InvalidArgumentException('Reply message is required.');
        }
        $msg = $this->repo->findContactMessageById($id);
        if ($msg === null) {
            throw new InvalidArgumentException('Message not found.');
        }
        $this->repo->replyContactMessage($id, $adminId, $replyText);
        $this->mgmt->logAdminAction($adminId, 'reply_contact', 'contact_messages', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function markContactSpam(int $adminId, int $id): void
    {
        $this->repo->updateContactStatus($id, 'spam');
        $this->mgmt->logAdminAction($adminId, 'spam_contact', 'contact_messages', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function deleteContactMessage(int $adminId, int $id): void
    {
        $this->repo->deleteContactMessage($id);
        $this->mgmt->logAdminAction($adminId, 'delete_contact', 'contact_messages', (string)$id, null, [], RequestContext::ipAddress());
    }

    public function submitContactForm(array $data, string $ip, string $ua): int
    {
        $name    = trim((string)($data['name'] ?? ''));
        $email   = trim((string)($data['email'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));

        if ($name === '' || $email === '' || $message === '') {
            throw new InvalidArgumentException('Name, email and message are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }
        if (strlen($message) < 10) {
            throw new InvalidArgumentException('Message is too short.');
        }

        return $this->repo->createContactMessage([
            'name'       => $name,
            'email'      => $email,
            'subject'    => trim((string)($data['subject'] ?? '')),
            'department' => trim((string)($data['department'] ?? 'general')),
            'message'    => $message,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }

    // =========================================================================
    // SEO SETTINGS
    // =========================================================================

    public function seoAdminPage(): array
    {
        $rows    = $this->repo->listSeoSettings();
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string)$row['context']] = $row;
        }
        return ['settings' => $indexed, 'rows' => $rows];
    }

    public function saveSeoSettings(int $adminId, string $context, array $data): void
    {
        if (trim($context) === '') {
            throw new InvalidArgumentException('SEO context is required.');
        }
        $this->repo->saveSeoSettings($context, $data);
        $this->mgmt->logAdminAction($adminId, 'save_seo_settings', 'seo_settings', $context, null, [], RequestContext::ipAddress());
    }

    // =========================================================================
    // HOMEPAGE BUILDER
    // =========================================================================

    public function homepageBuilderPage(): array
    {
        return [
            'sections'     => $this->repo->listHomepageSections(),
            'testimonials' => $this->repo->listTestimonials(['active_only' => true]),
            'features'     => $this->repo->listFeatures('home'),
            'plans'        => $this->repo->listPricingPlans(true),
            'recentPosts'  => $this->repo->recentPosts(3),
        ];
    }

    public function saveHomepageSection(int $adminId, string $key, array $data): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Section key is required.');
        }
        $this->repo->saveHomepageSection($key, $data);
        $this->mgmt->logAdminAction($adminId, 'save_homepage_section', 'homepage_sections', $key, null, [], RequestContext::ipAddress());
    }

    public function reorderHomepageSections(int $adminId, array $order): void
    {
        $this->repo->updateHomepageSectionOrder($order);
        $this->mgmt->logAdminAction($adminId, 'reorder_homepage_sections', 'homepage_sections', '', null, [], RequestContext::ipAddress());
    }

    // =========================================================================
    // SITEMAP
    // =========================================================================

    public function generateSitemap(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $lines   = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        $staticUrls = ['/', '/blog', '/faq', '/contact', '/markets', '/register', '/login'];
        foreach ($staticUrls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . htmlspecialchars($baseUrl . $url, ENT_XML1) . '</loc>';
            $lines[] = '    <changefreq>weekly</changefreq>';
            $lines[] = '    <priority>' . ($url === '/' ? '1.0' : '0.8') . '</priority>';
            $lines[] = '  </url>';
        }

        foreach ($this->repo->sitemapPages() as $page) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . htmlspecialchars($baseUrl . '/page/' . $page['slug'], ENT_XML1) . '</loc>';
            $lines[] = '    <lastmod>' . date('Y-m-d', strtotime((string)$page['updated_at'])) . '</lastmod>';
            $lines[] = '    <changefreq>monthly</changefreq>';
            $lines[] = '    <priority>0.6</priority>';
            $lines[] = '  </url>';
        }

        foreach ($this->repo->sitemapPosts() as $post) {
            $prefix = (string)$post['post_type'] === 'news' ? '/news/' : '/blog/';
            $date   = $post['published_at'] ?? $post['updated_at'];
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . htmlspecialchars($baseUrl . $prefix . $post['slug'], ENT_XML1) . '</loc>';
            $lines[] = '    <lastmod>' . date('Y-m-d', strtotime((string)$date)) . '</lastmod>';
            $lines[] = '    <changefreq>monthly</changefreq>';
            $lines[] = '    <priority>0.7</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';
        return implode("\n", $lines);
    }

    // =========================================================================
    // PUBLIC WEBSITE DATA
    // =========================================================================

    public function publicBlogPage(array $filters, int $page): array
    {
        $result     = $this->repo->listPublishedPosts($filters, $page, 10);
        $categories = $this->repo->listBlogCategories();
        $recent     = $this->repo->recentPosts(5);
        return [
            'posts'      => $result['items'],
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => (int)ceil($result['total'] / 10),
            'categories' => $categories,
            'recentPosts'=> $recent,
        ];
    }

    public function publicPostPage(string $slug): array
    {
        $post = $this->repo->findPostBySlug($slug);
        if ($post === null) {
            throw new InvalidArgumentException('Post not found.');
        }
        $this->repo->incrementPostViews((int)$post['id']);
        $related = $this->repo->listPublishedPosts(
            ['category_id' => (int)($post['category_id'] ?? 0)], 1, 4
        )['items'];
        return ['post' => $post, 'related' => $related];
    }

    public function publicFaqPage(): array
    {
        $categories = $this->repo->listFaqCategories(true);
        $faqs       = $this->repo->listFaqs(['active_only' => true], 1, 500)['items'];

        $grouped = [];
        foreach ($faqs as $faq) {
            $grouped[(int)($faq['category_id'] ?? 0)][] = $faq;
        }
        return ['categories' => $categories, 'grouped' => $grouped];
    }

    public function publicContactPage(): array
    {
        return ['seo' => $this->repo->findSeoByContext('contact')];
    }

    public function publicCmsPage(string $slug): array
    {
        $page = $this->repo->findPageBySlug($slug);
        if ($page === null) {
            throw new InvalidArgumentException('Page not found.');
        }
        $sections = $this->repo->pageSections((int)$page['id']);
        return ['page' => $page, 'sections' => $sections];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function normalizeSlug(string $slug, string $fallback = ''): string
    {
        if ($slug === '') {
            $slug = $fallback;
        }
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug) ?? $slug;
        $slug = preg_replace('/-{2,}/', '-', $slug) ?? $slug;
        return trim($slug, '-');
    }
}
