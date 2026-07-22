<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\RequestContext;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Services\AuthService;
use App\Validators\AuthValidator;

final class AuthController extends BaseController
{
    public function loginForm(Request $request): void
    {
        $this->view('auth/login', ['title' => 'Login']);
    }

    public function login(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $identity = trim((string)$request->input('identity', ''));
        $password = (string)$request->input('password', '');
        $rememberMe = ((string)$request->input('remember_me', '0')) === '1';

        $auth = new AuthService();
        $result = $auth->attempt($identity, $password, $rememberMe, RequestContext::ipAddress(), RequestContext::userAgent());

        if (!($result['ok'] ?? false)) {
            Response::json(['ok' => false, 'message' => $result['message'] ?? 'Invalid credentials'], 422);
        }

        Response::json(['ok' => true, 'redirect' => $result['redirect'] ?? '/dashboard']);
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
        $userId = $auth->register((string)$input['username'], (string)$input['email'], (string)$input['password']);
        $token = $auth->generateEmailVerificationToken($userId, (string)$input['email']);

        Response::json(['ok' => true, 'redirect' => '/email/verify/notice?token=' . urlencode($token)]);
    }

    public function forgotPasswordForm(Request $request): void
    {
        $this->view('auth/forgot-password', ['title' => 'Forgot Password']);
    }

    public function forgotPassword(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $email = trim((string)$request->input('email', ''));
        $validator = new AuthValidator();
        $errors = $validator->validateForgotPassword(['email' => $email]);

        if ($errors !== []) {
            Response::json(['ok' => false, 'errors' => $errors], 422);
        }

        $lastResetRequestAt = (int)(Session::get('auth.reset_request_at') ?? 0);
        if ($lastResetRequestAt > 0 && (time() - $lastResetRequestAt) < 60) {
            Response::json(['ok' => false, 'message' => 'Please wait before requesting another reset link'], 429);
        }

        $payload = (new AuthService())->createPasswordReset($email);
        Session::put('auth.reset_request_at', time());

        $response = ['ok' => true, 'message' => (string)$payload['message']];
        if (!empty($payload['token'])) {
            $token = (string)$payload['token'];
            if (preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
                $response['reset_link'] = '/reset-password?token=' . urlencode($token);
            }
        }

        Response::json($response);
    }

    public function resetPasswordForm(Request $request): void
    {
        $token = trim((string)$request->input('token', ''));
        $this->view('auth/reset-password', [
            'title' => 'Reset Password',
            'token' => $token,
        ]);
    }

    public function resetPassword(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $input = $request->all();
        $validator = new AuthValidator();
        $errors = $validator->validateResetPassword($input);

        if ($errors !== []) {
            Response::json(['ok' => false, 'errors' => $errors], 422);
        }

        $ok = (new AuthService())->resetPassword((string)$input['token'], (string)$input['password']);
        if (!$ok) {
            Response::json(['ok' => false, 'message' => 'Reset token is invalid or expired'], 422);
        }

        Response::json(['ok' => true, 'redirect' => '/login']);
    }

    public function verifyNotice(Request $request): void
    {
        $this->view('auth/email-verify-notice', [
            'title' => 'Verify Email',
            'token' => trim((string)$request->input('token', '')),
        ]);
    }

    public function verifyEmail(Request $request): void
    {
        $token = trim((string)$request->input('token', ''));
        $verified = (new AuthService())->verifyEmailToken($token);

        if (!$verified) {
            Response::redirect('/email/verify/notice?invalid=1');
        }

        Response::redirect('/login?verified=1');
    }

    public function twoFactorChallengeForm(Request $request): void
    {
        $this->view('auth/two-factor-challenge', ['title' => 'Two Factor Authentication']);
    }

    public function verifyTwoFactorChallenge(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $code = trim((string)$request->input('code', ''));
        if ($code === '' || strlen($code) !== 6) {
            Response::json(['ok' => false, 'message' => 'Enter a valid 6-digit code'], 422);
        }

        $result = (new AuthService())->verifyTwoFactorCode($code);
        if (!($result['ok'] ?? false)) {
            Response::json(['ok' => false, 'message' => $result['message'] ?? 'Invalid code'], 422);
        }

        Response::json(['ok' => true, 'redirect' => $result['redirect'] ?? '/dashboard']);
    }

    public function sessions(Request $request): void
    {
        $userId = (int)(Session::get('auth.user_id') ?? 0);
        if ($userId <= 0) {
            Response::redirect('/login');
        }

        $this->view('auth/sessions', [
            'title' => 'Session Management',
            'sessions' => (new AuthService())->userSessions($userId),
        ]);
    }

    public function revokeSession(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $sessionId = (int)$request->input('session_id', 0);

        if ($userId <= 0 || $sessionId <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid request'], 422);
        }

        (new AuthService())->revokeSession($userId, $sessionId);
        Response::json(['ok' => true, 'message' => 'Session revoked']);
    }

    public function logout(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::redirect('/login');
        }

        (new AuthService())->logout();
        Response::redirect('/login');
    }

}
