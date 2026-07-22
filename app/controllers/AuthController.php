<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AuthService;
use App\Validators\AuthValidator;

final class AuthController extends BaseController
{
    private const USER_DASHBOARD_ROUTE = '/dashboard';

    public function loginForm(Request $request): void
    {
        $this->view('auth/login', ['title' => 'Login']);
    }

    public function login(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $identity = (string)$request->input('identity', '');
        $password = (string)$request->input('password', '');

        $auth = new AuthService();

        if (!$auth->attempt($identity, $password)) {
            Response::json(['ok' => false, 'message' => 'Invalid credentials'], 422);
        }

        Response::json(['ok' => true, 'redirect' => self::USER_DASHBOARD_ROUTE]);
    }

    public function registerForm(Request $request): void
    {
        $this->view('auth/register', ['title' => 'Register']);
    }

    public function register(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $input = $request->all();
        $validator = new AuthValidator();
        $errors = $validator->validateRegistration($input);

        if ($errors !== []) {
            Response::json(['ok' => false, 'errors' => $errors], 422);
        }

        $auth = new AuthService();
        $auth->register((string)$input['username'], (string)$input['email'], (string)$input['password']);

        Response::json(['ok' => true, 'redirect' => '/login']);
    }

    public function logout(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::redirect('/login');
        }

        (new AuthService())->logout();
        Response::redirect('/login');
    }

    public function forgotPasswordForm(Request $request): void
    {
        $this->view('auth/forgot-password', ['title' => 'Forgot Password']);
    }
}
