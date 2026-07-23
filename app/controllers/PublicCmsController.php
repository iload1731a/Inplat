<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\CmsService;
use InvalidArgumentException;
use App\Libraries\RequestContext;
use Throwable;

final class PublicCmsController extends BaseController
{
    private function svc(): CmsService
    {
        return new CmsService();
    }

    // =========================================================================
    // BLOG
    // =========================================================================

    public function blog(Request $request): void
    {
        $filters = [
            'type'     => (string)$request->query('type', ''),
            'category' => (string)$request->query('category', ''),
            'search'   => (string)$request->query('search', ''),
        ];
        $page = max(1, (int)$request->query('page', '1'));
        $data = $this->svc()->publicBlogPage($filters, $page);
        $this->view('cms/blog', array_merge($data, [
            'title'   => 'Blog',
            'filters' => $filters,
        ]), 'layouts/main');
    }

    public function blogPost(Request $request): void
    {
        $slug = trim((string)$request->query('slug', ''));
        try {
            $data = $this->svc()->publicPostPage($slug);
        } catch (InvalidArgumentException $e) {
            http_response_code(404);
            $this->view('cms/404', ['title' => 'Post Not Found'], 'layouts/main');
            return;
        }
        $this->view('cms/blog-post', array_merge($data, [
            'title' => (string)($data['post']['title'] ?? 'Blog Post'),
        ]), 'layouts/main');
    }

    public function news(Request $request): void
    {
        $filters = ['type' => 'news', 'search' => (string)$request->query('search', '')];
        $page    = max(1, (int)$request->query('page', '1'));
        $data    = $this->svc()->publicBlogPage($filters, $page);
        $this->view('cms/blog', array_merge($data, [
            'title'   => 'News',
            'filters' => $filters,
            'isNews'  => true,
        ]), 'layouts/main');
    }

    // =========================================================================
    // FAQ
    // =========================================================================

    public function faq(Request $request): void
    {
        $data = $this->svc()->publicFaqPage();
        $this->view('cms/faq', array_merge($data, ['title' => 'FAQ - Help Center']), 'layouts/main');
    }

    public function faqVote(Request $request): void
    {
        $id   = (int)$request->input('id', 0);
        $type = (string)$request->input('type', 'yes');
        if (!in_array($type, ['yes', 'no'], true) || $id <= 0) {
            Response::json(['ok' => false], 422);
        }
        try {
            (new \App\Repositories\CmsRepository())->faqVote($id, $type);
            Response::json(['ok' => true]);
        } catch (Throwable $e) {
            Response::json(['ok' => false], 500);
        }
    }

    // =========================================================================
    // CONTACT
    // =========================================================================

    public function contact(Request $request): void
    {
        if ($request->method() === 'POST') {
            $ip = RequestContext::ipAddress();
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            try {
                $this->svc()->submitContactForm($request->all(), $ip, $ua);
                if ($request->isAjax()) {
                    Response::json(['ok' => true, 'message' => 'Message sent successfully. We\'ll get back to you shortly.']);
                    return;
                }
                Response::redirect('/contact?sent=1');
            } catch (InvalidArgumentException $e) {
                if ($request->isAjax()) {
                    Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
                    return;
                }
                Response::redirect('/contact?error=' . urlencode($e->getMessage()));
            }
            return;
        }

        $data = $this->svc()->publicContactPage();
        $this->view('cms/contact', array_merge($data, [
            'title'    => 'Contact Us',
            'sent'     => (string)$request->query('sent', '') === '1',
            'errorMsg' => urldecode((string)$request->query('error', '')),
        ]), 'layouts/main');
    }

    // =========================================================================
    // CMS PAGE (dynamic)
    // =========================================================================

    public function page(Request $request): void
    {
        $slug = trim((string)$request->query('slug', ''));
        try {
            $data = $this->svc()->publicCmsPage($slug);
        } catch (InvalidArgumentException $e) {
            http_response_code(404);
            $this->view('cms/404', ['title' => 'Page Not Found'], 'layouts/main');
            return;
        }
        $this->view('cms/page', array_merge($data, [
            'title' => (string)($data['page']['title'] ?? 'Page'),
        ]), 'layouts/main');
    }

    // =========================================================================
    // SITEMAP (public XML)
    // =========================================================================

    public function sitemap(Request $request): void
    {
        $siteUrl = rtrim((string)(config('app.url') ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))), '/');
        $xml     = $this->svc()->generateSitemap($siteUrl);
        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml;
        exit;
    }
}
