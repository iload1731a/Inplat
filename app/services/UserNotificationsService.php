<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserNotificationsRepository;

final class UserNotificationsService
{
    private readonly UserNotificationsRepository $repo;

    public function __construct(?UserNotificationsRepository $repo = null)
    {
        $this->repo = $repo ?? new UserNotificationsRepository();
    }

    public function all(int $userId): array
    {
        return $this->repo->all($userId);
    }

    public function unreadCount(int $userId): int
    {
        return $this->repo->unreadCount($userId);
    }

    public function markRead(int $userId, int $notificationId): void
    {
        $this->repo->markRead($userId, $notificationId);
    }

    public function markAllRead(int $userId): void
    {
        $this->repo->markAllRead($userId);
    }

    public function delete(int $userId, int $notificationId): void
    {
        $this->repo->delete($userId, $notificationId);
    }
}
