<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Session;
use App\Repositories\UserRepository;
use DateInterval;
use DateTimeImmutable;

final class AuthService
{
    private const REMEMBER_COOKIE = 'inplat_remember';

    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    public function attempt(string $identity, string $password, bool $rememberMe, string $ipAddress, string $userAgent): array
    {
        $user = $this->users->findByEmailOrUsername($identity);

        if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
            return ['ok' => false, 'message' => 'Invalid credentials'];
        }

        if (($user['status'] ?? '') !== 'active') {
            return ['ok' => false, 'message' => 'Account is not active'];
        }

        if ((int)($user['two_factor_enabled'] ?? 0) === 1) {
            $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Session::put('auth.pending_user_id', (int)$user['id']);
            Session::put('auth.pending_identity', (string)$identity);
            Session::put('auth.pending_2fa_code', $otpCode);
            Session::put('auth.pending_2fa_expires_at', (new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s'));
            Session::put('auth.pending_remember_me', $rememberMe);
            Session::put('auth.pending_ip', $ipAddress);
            Session::put('auth.pending_agent', $userAgent);

            return ['ok' => true, 'requires_2fa' => true, 'redirect' => '/two-factor-challenge'];
        }

        $this->completeLogin((int)$user['id'], (string)$identity, (string)$user['username'], $rememberMe, $ipAddress, $userAgent);

        return ['ok' => true, 'redirect' => $this->redirectPathForIdentity($identity)];
    }

    public function verifyTwoFactorCode(string $code): array
    {
        $pendingUserId = (int)(Session::get('auth.pending_user_id') ?? 0);
        $pendingCode = (string)(Session::get('auth.pending_2fa_code') ?? '');
        $pendingExpiresAt = (string)(Session::get('auth.pending_2fa_expires_at') ?? '');

        if ($pendingUserId <= 0 || $pendingCode === '' || $pendingExpiresAt === '') {
            return ['ok' => false, 'message' => '2FA session expired'];
        }

        if (new DateTimeImmutable($pendingExpiresAt) < new DateTimeImmutable('now')) {
            $this->clearPendingTwoFactor();
            return ['ok' => false, 'message' => '2FA code has expired'];
        }

        if (!hash_equals($pendingCode, trim($code))) {
            $identity = (string)(Session::get('auth.pending_identity') ?? '');
            if ($identity !== '') {
                $this->users->logLoginAttempt($pendingUserId, (string)(Session::get('auth.pending_ip') ?? '0.0.0.0'), (string)(Session::get('auth.pending_agent') ?? 'unknown'), 'failed_2fa');
            }

            return ['ok' => false, 'message' => 'Invalid authentication code'];
        }

        $identity = (string)(Session::get('auth.pending_identity') ?? '');
        $username = (string)(($this->users->findById($pendingUserId)['username'] ?? 'Trader'));
        $rememberMe = (bool)(Session::get('auth.pending_remember_me') ?? false);
        $ipAddress = (string)(Session::get('auth.pending_ip') ?? '0.0.0.0');
        $userAgent = (string)(Session::get('auth.pending_agent') ?? 'unknown');

        $this->completeLogin($pendingUserId, $identity, $username, $rememberMe, $ipAddress, $userAgent);
        $this->clearPendingTwoFactor();

        return ['ok' => true, 'redirect' => $this->redirectPathForIdentity($identity)];
    }

    public function register(string $username, string $email, string $password): int
    {
        $passwordHash = password_hash($password, password_algo());
        if ($passwordHash === false) {
            throw new \RuntimeException('Password hashing failed.');
        }

        return $this->users->create([
            'uuid' => $this->uuidV4(),
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);
    }

    public function createPasswordReset(string $email): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return ['ok' => true, 'message' => 'If this email exists, a reset link was generated.'];
        }

        $token = bin2hex(random_bytes(32));
        $this->users->createPasswordReset((int)$user['id'], hash('sha256', $token), new DateTimeImmutable('+1 hour'));

        return [
            'ok' => true,
            'message' => 'Reset link generated. Connect SMTP/email templates in next phase for delivery.',
            'token' => $token,
        ];
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $reset = $this->users->findValidPasswordResetByTokenHash(hash('sha256', $token));

        if ($reset === null) {
            return false;
        }

        $passwordHash = password_hash($newPassword, password_algo());
        if ($passwordHash === false) {
            throw new \RuntimeException('Password hashing failed.');
        }

        $this->users->updatePassword((int)$reset['user_id'], $passwordHash);
        $this->users->markPasswordResetUsed((int)$reset['id']);

        return true;
    }

    public function generateEmailVerificationToken(int $userId, string $email): string
    {
        $expires = (new DateTimeImmutable('+24 hours'))->getTimestamp();
        $payload = $userId . '|' . $email . '|' . $expires;
        $signature = hash_hmac('sha256', $payload, $this->tokenKey());

        return rtrim(strtr(base64_encode($payload . '|' . $signature), '+/', '-_'), '=');
    }

    public function verifyEmailToken(string $token): bool
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        if (!is_string($decoded)) {
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 4) {
            return false;
        }

        [$userIdRaw, $email, $expiresRaw, $signature] = $parts;
        $payload = $userIdRaw . '|' . $email . '|' . $expiresRaw;
        $expected = hash_hmac('sha256', $payload, $this->tokenKey());

        if (!hash_equals($expected, $signature)) {
            return false;
        }

        if ((int)$expiresRaw < time()) {
            return false;
        }

        $userId = (int)$userIdRaw;
        $user = $this->users->findById($userId);

        if ($user === null || !hash_equals((string)$user['email'], $email)) {
            return false;
        }

        $this->users->markEmailVerified($userId);

        return true;
    }

    public function hydrateFromRememberCookie(string $ipAddress, string $userAgent): void
    {
        if (Session::get('auth.user_id') !== null) {
            return;
        }

        $cookieToken = (string)($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($cookieToken === '') {
            return;
        }

        $session = $this->users->findActiveSessionByTokenHash(hash('sha256', $cookieToken));
        if ($session === null) {
            $this->clearRememberCookie();
            return;
        }

        $identity = (string)($session['email'] ?? $session['username'] ?? '');
        $this->completeLogin((int)$session['user_id'], $identity, (string)$session['username'], true, $ipAddress, $userAgent, false);
    }

    public function userSessions(int $userId): array
    {
        return $this->users->sessionsForUser($userId);
    }

    public function revokeSession(int $userId, string $tokenHash): void
    {
        $this->users->revokeSession($userId, $tokenHash);
    }

    public function logout(): void
    {
        $this->clearRememberCookie();
        Session::destroy();
    }

    private function completeLogin(int $userId, string $identity, string $username, bool $rememberMe, string $ipAddress, string $userAgent, bool $storeRemember = true): void
    {
        Session::regenerate();
        Session::put('auth.user_id', $userId);
        Session::put('auth.username', $username);
        Session::put('auth.identity', $identity);
        Session::put('auth.is_admin', $this->users->isAdminIdentity($identity));

        $this->users->logLoginAttempt($userId, $ipAddress, $userAgent, 'success');

        if ($rememberMe && $storeRemember) {
            $rememberToken = bin2hex(random_bytes(32));
            $this->users->createSession($userId, hash('sha256', $rememberToken), $ipAddress, $userAgent, new DateTimeImmutable('+30 days'));
            setcookie(self::REMEMBER_COOKIE, $rememberToken, [
                'expires' => time() + (60 * 60 * 24 * 30),
                'path' => '/',
                'secure' => $this->isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    private function redirectPathForIdentity(string $identity): string
    {
        return $this->users->isAdminIdentity($identity) ? '/admin/dashboard' : '/dashboard';
    }

    private function clearPendingTwoFactor(): void
    {
        Session::forget('auth.pending_user_id');
        Session::forget('auth.pending_identity');
        Session::forget('auth.pending_2fa_code');
        Session::forget('auth.pending_2fa_expires_at');
        Session::forget('auth.pending_remember_me');
        Session::forget('auth.pending_ip');
        Session::forget('auth.pending_agent');
    }

    private function clearRememberCookie(): void
    {
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function tokenKey(): string
    {
        return (string)config('app.session_name', 'inplat_session') . '|email-verify';
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
