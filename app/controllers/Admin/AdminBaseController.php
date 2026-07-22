<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;

abstract class AdminBaseController extends BaseController
{
    protected function bootAdmin(): void
    {
        AuthMiddleware::ensureAdmin();
    }

    protected function requireCsrf(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }
    }

    protected function adminId(): int
    {
        return (int)(Session::get('auth.admin_id') ?? 0);
    }

    protected function adminUsername(): string
    {
        return (string)(Session::get('auth.username') ?? 'Admin');
    }
}
