<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\PlatformService;

final class PlatformController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAdmin();

        $snapshot = (new PlatformService())->adminOperationsSnapshot();

        $this->view('admin/platform', [
            'title' => 'Admin Operations',
            'username' => (string)(Session::get('auth.username') ?? 'Admin'),
            ...$snapshot,
        ]);
    }

    public function snapshot(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        Response::json(['ok' => true, 'data' => (new PlatformService())->adminOperationsSnapshot()]);
    }
}
