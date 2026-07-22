<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Request;
use App\Middleware\AuthMiddleware;

final class DashboardController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $this->view('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'username' => (string)(\App\Libraries\Session::get('auth.username') ?? 'Admin'),
        ]);
    }
}
