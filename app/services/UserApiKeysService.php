<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserApiKeysRepository;

final class UserApiKeysService
{
    private const MAX_KEYS = 10;

    private readonly UserApiKeysRepository $repo;

    public function __construct(?UserApiKeysRepository $repo = null)
    {
        $this->repo = $repo ?? new UserApiKeysRepository();
    }

    public function keys(int $userId): array
    {
        return $this->repo->keys($userId);
    }

    public function create(int $userId, array $input): array
    {
        if ($this->repo->countActive($userId) >= self::MAX_KEYS) {
            throw new \RuntimeException('Maximum of ' . self::MAX_KEYS . ' active API keys allowed');
        }

        $label = trim((string)($input['label'] ?? ''));
        if ($label === '' || strlen($label) > 100) {
            throw new \InvalidArgumentException('Label must be 1-100 characters');
        }

        $permissionsInput = (array)($input['permissions'] ?? []);
        $allowedPerms     = ['read', 'trade', 'withdraw'];
        $permissionsInput = array_filter($permissionsInput, static fn($p) => in_array($p, $allowedPerms, true));
        if ($permissionsInput === []) {
            throw new \InvalidArgumentException('At least one permission must be selected');
        }
        $permissions = implode(',', $permissionsInput);

        $ipWhitelist = trim((string)($input['ip_whitelist'] ?? '')) ?: null;
        if ($ipWhitelist !== null) {
            $ips = array_map('trim', explode(',', $ipWhitelist));
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    throw new \InvalidArgumentException('One or more IP addresses in the whitelist are invalid. Please check your entries.');
                }
            }
            $ipWhitelist = implode(',', $ips);
        }

        $expiresAt = null;
        if (!empty($input['expires_at'])) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', (string)$input['expires_at']);
            if ($dt === false) {
                throw new \InvalidArgumentException('Invalid expiry date format');
            }
            $expiresAt = $dt->format('Y-m-d 23:59:59');
        }

        $apiKey     = bin2hex(random_bytes(16));
        $apiSecret  = bin2hex(random_bytes(32));
        $secretHash = hash('sha256', $apiSecret);

        $this->repo->create($userId, $label, $apiKey, $secretHash, $permissions, $ipWhitelist, $expiresAt);

        return [
            'api_key'    => $apiKey,
            'api_secret' => $apiSecret,
        ];
    }

    public function revoke(int $userId, int $keyId): void
    {
        if (!$this->repo->revoke($userId, $keyId)) {
            throw new \RuntimeException('API key not found or already revoked');
        }
    }
}
