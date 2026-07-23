<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\CmsService;
use InvalidArgumentException;
use Throwable;

final class CmsController extends AdminBaseController
{
    private function svc(): CmsService
    {
        return new CmsService();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->adminDashboard();
        $this->view('admin/cms/index', array_merge($data, [
            'title'        => 'CMS Dashboard',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    // =========================================================================
    // MEDIA LIBRARY
    // =========================================================================

    public function media(Request $request): void
    {
        $this->bootAdmin();
        $filters = [
            'folder' => (string)$request->query('folder', ''),
            'mime'   => (string)$request->query('mime', ''),
            'search' => (string)$request->query('search', ''),
        ];
        $page = max(1, (int)$request->query('page', '1'));
        $data = $this->svc()->mediaList($filters, $page, 40);
        $this->view('admin/cms/media', array_merge($data, [
            'title'        => 'Media Library',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
            'filters'      => $filters,
        ]));
    }

    public function mediaUpload(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $file = $_FILES['file'] ?? null;
        if ($file === null || (int)($file['error'] ?? 1) !== 0) {
            Response::json(['ok' => false, 'message' => 'No file uploaded or upload error.'], 422);
        }

        try {
            $meta   = $request->all();
            $result = $this->svc()->uploadMedia($file, $meta, $this->adminId());
            Response::json(['ok' => true, 'file' => $result]);
        } catch (InvalidArgumentException $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => 'Upload failed.'], 500);
        }
    }

    public function mediaUpdate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->updateMedia($this->adminId(), $id, $request->all());
            Response::json(['ok' => true, 'message' => 'Media updated.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function mediaDelete(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->deleteMedia($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'Media deleted.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // CMS PAGES
    // =========================================================================

    public function pages(Request $request): void
    {
        $this->bootAdmin();
        $filters = [
            'status' => (string)$request->query('status', ''),
            'type'   => (string)$request->query('type', ''),
            'search' => (string)$request->query('search', ''),
        ];
        $page = max(1, (int)$request->query('page', '1'));
        $data = $this->svc()->pagesList($filters, $page, 20);
        $this->view('admin/cms/pages', array_merge($data, [
            'title'        => 'CMS Pages',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
            'filters'      => $filters,
        ]));
    }

    public function pageCreate(Request $request): void
    {
        $this->bootAdmin();
        if ($request->method() === 'GET') {
            $this->view('admin/cms/page-edit', [
                'title'        => 'Create Page',
                'adminSection' => 'cms',
                'username'     => $this->adminUsername(),
                'page'         => null,
                'sections'     => [],
            ]);
            return;
        }
        $this->requireCsrf($request);
        try {
            $id = $this->svc()->createPage($this->adminId(), $request->all());
            Response::json(['ok' => true, 'message' => 'Page created.', 'redirect' => '/admin/cms/pages/edit?id=' . $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function pageEdit(Request $request): void
    {
        $this->bootAdmin();
        $id = (int)$request->query('id', '0');
        try {
            $data = $this->svc()->pageDetail($id);
        } catch (Throwable $e) {
            Response::redirect('/admin/cms/pages');
        }
        $this->view('admin/cms/page-edit', array_merge($data ?? [], [
            'title'        => 'Edit Page',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function pageUpdate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->updatePage($this->adminId(), $id, $request->all());
            Response::json(['ok' => true, 'message' => 'Page updated.', 'redirect' => '/admin/cms/pages']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function pageDelete(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->deletePage($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'Page deleted.', 'redirect' => '/admin/cms/pages']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // BLOG & NEWS
    // =========================================================================

    public function blog(Request $request): void
    {
        $this->bootAdmin();
        $filters = [
            'status'      => (string)$request->query('status', ''),
            'type'        => (string)$request->query('type', ''),
            'category_id' => (int)$request->query('category_id', 0),
            'search'      => (string)$request->query('search', ''),
        ];
        $page = max(1, (int)$request->query('page', '1'));
        $data = $this->svc()->blogPostsList($filters, $page, 20);
        $this->view('admin/cms/blog', array_merge($data, [
            'title'        => 'Blog & News',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
            'filters'      => $filters,
        ]));
    }

    public function postCreate(Request $request): void
    {
        $this->bootAdmin();
        if ($request->method() === 'GET') {
            $categories = $this->svc()->blogDashboard()['categories'];
            $this->view('admin/cms/post-edit', [
                'title'        => 'New Post',
                'adminSection' => 'cms',
                'username'     => $this->adminUsername(),
                'post'         => null,
                'categories'   => $categories,
            ]);
            return;
        }
        $this->requireCsrf($request);
        try {
            $id = $this->svc()->createPost($this->adminId(), $request->all());
            Response::json(['ok' => true, 'message' => 'Post created.', 'redirect' => '/admin/cms/blog/edit?id=' . $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function postEdit(Request $request): void
    {
        $this->bootAdmin();
        $id = (int)$request->query('id', '0');
        try {
            $data = $this->svc()->blogPostDetail($id);
        } catch (Throwable $e) {
            Response::redirect('/admin/cms/blog');
        }
        $this->view('admin/cms/post-edit', array_merge($data ?? [], [
            'title'        => 'Edit Post',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function postUpdate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->updatePost($this->adminId(), $id, $request->all());
            Response::json(['ok' => true, 'message' => 'Post updated.', 'redirect' => '/admin/cms/blog']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function postDelete(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->deletePost($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'Post deleted.', 'redirect' => '/admin/cms/blog']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function blogCategories(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->blogDashboard();
        $this->view('admin/cms/blog-categories', array_merge($data, [
            'title'        => 'Blog Categories',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function saveBlogCategory(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            // Delegate to CmsService via repo
            $svc  = $this->svc();
            $data = $request->all();
            $repo = new \App\Repositories\CmsRepository();
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('Category name is required.');
            }
            $data['slug'] = $data['slug'] !== '' ? $data['slug'] : $svc->normalizeSlug($name);
            $id   = (int)($data['id'] ?? 0);
            if ($id > 0) {
                $repo->updateBlogCategory($id, $data);
            } else {
                $id = $repo->createBlogCategory($data);
            }
            Response::json(['ok' => true, 'message' => 'Category saved.', 'redirect' => '/admin/cms/blog/categories']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteBlogCategory(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            (new \App\Repositories\CmsRepository())->deleteBlogCategory($id);
            Response::json(['ok' => true, 'message' => 'Category deleted.', 'redirect' => '/admin/cms/blog/categories']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // FAQ
    // =========================================================================

    public function faq(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->faqAdminPage();
        $this->view('admin/cms/faq', array_merge($data, [
            'title'        => 'FAQ Management',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function saveFaqCategory(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveFaqCategory($this->adminId(), $request->all());
            Response::json(['ok' => true, 'message' => 'Category saved.', 'redirect' => '/admin/cms/faq']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteFaqCategory(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->deleteFaqCategory($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'Category deleted.', 'redirect' => '/admin/cms/faq']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveFaq(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveFaq($this->adminId(), $request->all());
            Response::json(['ok' => true, 'message' => 'FAQ saved.', 'redirect' => '/admin/cms/faq']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteFaq(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->deleteFaq($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'FAQ deleted.', 'redirect' => '/admin/cms/faq']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // TESTIMONIALS
    // =========================================================================

    public function testimonials(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->testimonialsPage();
        $this->view('admin/cms/testimonials', array_merge($data, [
            'title'        => 'Testimonials',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function saveTestimonial(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveTestimonial($this->adminId(), $request->all());
            Response::json(['ok' => true, 'message' => 'Testimonial saved.', 'redirect' => '/admin/cms/testimonials']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteTestimonial(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->deleteTestimonial($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'Testimonial deleted.', 'redirect' => '/admin/cms/testimonials']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // PLATFORM FEATURES
    // =========================================================================

    public function features(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->featuresPage();
        $this->view('admin/cms/features', array_merge($data, [
            'title'        => 'Platform Features',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function saveFeature(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->saveFeature($this->adminId(), $request->all());
            Response::json(['ok' => true, 'message' => 'Feature saved.', 'redirect' => '/admin/cms/features']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteFeature(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->deleteFeature($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'Feature deleted.', 'redirect' => '/admin/cms/features']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // PRICING PLANS
    // =========================================================================

    public function pricing(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->pricingPage();
        $this->view('admin/cms/pricing', array_merge($data, [
            'title'        => 'Pricing Plans',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function savePricing(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $this->svc()->savePricingPlan($this->adminId(), $request->all());
            Response::json(['ok' => true, 'message' => 'Plan saved.', 'redirect' => '/admin/cms/pricing']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deletePricing(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->deletePricingPlan($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'Plan deleted.', 'redirect' => '/admin/cms/pricing']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // CONTACT MESSAGES
    // =========================================================================

    public function contact(Request $request): void
    {
        $this->bootAdmin();
        $filters = [
            'status' => (string)$request->query('status', ''),
            'search' => (string)$request->query('search', ''),
        ];
        $page = max(1, (int)$request->query('page', '1'));
        $data = $this->svc()->contactAdminPage($filters, $page);
        $this->view('admin/cms/contact', array_merge($data, [
            'title'        => 'Contact Messages',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
            'filters'      => $filters,
        ]));
    }

    public function contactView(Request $request): void
    {
        $this->bootAdmin();
        $id = (int)$request->query('id', '0');
        try {
            $data = $this->svc()->viewContactMessage($id);
        } catch (Throwable $e) {
            Response::redirect('/admin/cms/contact');
        }
        $this->view('admin/cms/contact-detail', array_merge($data ?? [], [
            'title'        => 'View Message',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function contactReply(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id    = (int)$request->input('id', 0);
        $reply = trim((string)$request->input('reply_message', ''));
        try {
            $this->svc()->replyContact($this->adminId(), $id, $reply);
            Response::json(['ok' => true, 'message' => 'Reply sent.', 'redirect' => '/admin/cms/contact']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function contactSpam(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->markContactSpam($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'Marked as spam.', 'redirect' => '/admin/cms/contact']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function contactDelete(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $id = (int)$request->input('id', 0);
        try {
            $this->svc()->deleteContactMessage($this->adminId(), $id);
            Response::json(['ok' => true, 'message' => 'Message deleted.', 'redirect' => '/admin/cms/contact']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // SEO MANAGEMENT
    // =========================================================================

    public function seo(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->seoAdminPage();
        $this->view('admin/cms/seo', array_merge($data, [
            'title'        => 'SEO Management',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function saveSeo(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $context = trim((string)$request->input('context', ''));
        try {
            $this->svc()->saveSeoSettings($this->adminId(), $context, $request->all());
            Response::json(['ok' => true, 'message' => 'SEO settings saved.', 'redirect' => '/admin/cms/seo']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // HOMEPAGE BUILDER
    // =========================================================================

    public function homepage(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->homepageBuilderPage();
        $this->view('admin/cms/homepage', array_merge($data, [
            'title'        => 'Homepage Builder',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function saveHomepageSection(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $key = trim((string)$request->input('section_key', ''));
        try {
            $this->svc()->saveHomepageSection($this->adminId(), $key, $request->all());
            Response::json(['ok' => true, 'message' => 'Section saved.', 'redirect' => '/admin/cms/homepage']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function reorderHomepage(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $order = (array)$request->input('order', []);
        try {
            $this->svc()->reorderHomepageSections($this->adminId(), $order);
            Response::json(['ok' => true, 'message' => 'Order saved.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // SITEMAP
    // =========================================================================

    public function sitemap(Request $request): void
    {
        $this->bootAdmin();
        $siteUrl = rtrim((string)(config('app.url') ?? 'https://example.com'), '/');
        $xml     = $this->svc()->generateSitemap($siteUrl);
        $this->view('admin/cms/sitemap', [
            'title'        => 'Sitemap Generator',
            'adminSection' => 'cms',
            'username'     => $this->adminUsername(),
            'xml'          => $xml,
            'siteUrl'      => $siteUrl,
        ]);
    }

    public function sitemapDownload(Request $request): void
    {
        $this->bootAdmin();
        $siteUrl = rtrim((string)(config('app.url') ?? 'https://example.com'), '/');
        $xml     = $this->svc()->generateSitemap($siteUrl);
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sitemap.xml"');
        header('Content-Length: ' . strlen($xml));
        echo $xml;
        exit;
    }
}
