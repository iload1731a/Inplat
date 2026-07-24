<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserSecurityRepository;

final class UserSecurityService
{
    private readonly UserSecurityRepository $repo;

    public function __construct(?UserSecurityRepository $repo = null)
    {
        $this->repo = $repo ?? new UserSecurityRepository();
    }

    public function data(int $userId): array
    {
        return [
            'loginHistory'    => $this->repo->loginHistory($userId),
            'activeSessions'  => $this->repo->activeSessions($userId),
            'twoFactorData'   => $this->repo->getTwoFactorData($userId),
            'securitySettings'=> $this->repo->getSecuritySettings($userId),
        ];
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): void
    {
        if ($newPassword !== $confirmPassword) {
            throw new \InvalidArgumentException('New passwords do not match');
        }
        if (strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters');
        }

        $currentHash = $this->repo->getPasswordHash($userId);
        if ($currentHash === null || !password_verify($currentPassword, $currentHash)) {
            throw new \InvalidArgumentException('Current password is incorrect');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->repo->updatePassword($userId, $newHash);
    }

    public function generateTwoFactorSecret(): array
    {
        $secret = strtoupper(bin2hex(random_bytes(10)));
        $qrData = 'otpauth://totp/' . urlencode((string)config('app.name')) . '?secret=' . $secret;
        return ['secret' => $secret, 'qr_data' => $qrData];
    }

    public function enableTwoFactor(int $userId, string $secret, string $code): void
    {
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            throw new \InvalidArgumentException('Invalid verification code format');
        }
        $this->repo->enableTwoFactor($userId, $secret);
    }

    public function disableTwoFactor(int $userId, string $password): void
    {
        $currentHash = $this->repo->getPasswordHash($userId);
        if ($currentHash === null || !password_verify($password, $currentHash)) {
            throw new \InvalidArgumentException('Password verification failed');
        }
        $this->repo->disableTwoFactor($userId);
    }

    public function revokeSession(int $userId, int $sessionId): void
    {
        $this->repo->revokeSession($userId, $sessionId);
    }

    public function revokeAllSessions(int $userId): void
    {
        $this->repo->revokeAllSessions($userId);
    }

    public function updateSecuritySettings(int $userId, array $input): void
    {
        $this->repo->upsertSecuritySettings($userId, [
            'login_notification_enabled'      => (int)!empty($input['login_notification_enabled']),
            'withdrawal_notification_enabled' => (int)!empty($input['withdrawal_notification_enabled']),
            'trade_notification_enabled'      => (int)!empty($input['trade_notification_enabled']),
        ]);
    }
}
