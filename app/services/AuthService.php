<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Session;
use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    public function attempt(string $identity, string $password): bool
    {
        $user = $this->users->findByEmailOrUsername($identity);

        if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
            return false;
        }

        if (($user['status'] ?? '') !== 'active') {
            return false;
        }

        Session::regenerate();
        Session::put('auth.user_id', (int)$user['id']);
        Session::put('auth.username', (string)$user['username']);

        return true;
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

    public function logout(): void
    {
        Session::destroy();
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
