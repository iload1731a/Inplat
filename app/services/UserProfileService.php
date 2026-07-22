<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserProfileRepository;

final class UserProfileService
{
    private readonly UserProfileRepository $repo;

    public function __construct(?UserProfileRepository $repo = null)
    {
        $this->repo = $repo ?? new UserProfileRepository();
    }

    public function get(int $userId): array
    {
        return $this->repo->findByUserId($userId) ?? [];
    }

    public function update(int $userId, array $input): void
    {
        $userFields = [
            'first_name'         => trim((string)($input['first_name']         ?? '')),
            'last_name'          => trim((string)($input['last_name']          ?? '')),
            'phone'              => trim((string)($input['phone']              ?? '')),
            'timezone'           => trim((string)($input['timezone']           ?? '')),
            'preferred_language' => trim((string)($input['preferred_language'] ?? '')),
        ];
        $userFields = array_filter($userFields, static fn($v) => $v !== '');
        if ($userFields !== []) {
            $this->repo->updateUserFields($userId, $userFields);
        }

        $profileFields = [
            'date_of_birth'       => $input['date_of_birth']       ?? null,
            'gender'              => $input['gender']              ?? null,
            'address_line1'       => $input['address_line1']       ?? null,
            'address_line2'       => $input['address_line2']       ?? null,
            'city'                => $input['city']                ?? null,
            'state_province'      => $input['state_province']      ?? null,
            'postal_code'         => $input['postal_code']         ?? null,
            'country_code'        => $input['country_code']        ?? null,
            'occupation'          => $input['occupation']          ?? null,
            'source_of_funds'     => $input['source_of_funds']     ?? null,
            'annual_income_range' => $input['annual_income_range'] ?? null,
            'company_name'        => $input['company_name']        ?? null,
            'tax_id'              => $input['tax_id']              ?? null,
        ];
        $this->repo->upsertProfile($userId, $profileFields);
    }

    public function updateAvatar(int $userId, array $file): string
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedMimes, true)) {
            throw new \InvalidArgumentException('Invalid image type. Allowed: JPG, PNG, GIF, WEBP');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Image must be smaller than 2MB');
        }

        $uploadDir = app_path('public/uploads/avatars/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = pathinfo((string)$file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . strtolower($ext);
        $target   = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Failed to save avatar');
        }

        $url = '/uploads/avatars/' . $filename;
        $this->repo->updateAvatar($userId, $url);
        return $url;
    }
}
