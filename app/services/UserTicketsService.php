<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserTicketsRepository;

final class UserTicketsService
{
    private readonly UserTicketsRepository $repo;

    public function __construct(?UserTicketsRepository $repo = null)
    {
        $this->repo = $repo ?? new UserTicketsRepository();
    }

    public function tickets(int $userId): array
    {
        return $this->repo->tickets($userId);
    }

    public function getTicket(int $userId, int $ticketId): array
    {
        $ticket = $this->repo->findTicket($userId, $ticketId);
        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found');
        }
        return [
            'ticket'   => $ticket,
            'messages' => $this->repo->messages($ticketId),
        ];
    }

    public function create(int $userId, array $input): int
    {
        $subject = trim((string)($input['subject'] ?? ''));
        if ($subject === '' || strlen($subject) < 5) {
            throw new \InvalidArgumentException('Subject must be at least 5 characters');
        }

        $allowedCategories = ['general', 'trading', 'kyc', 'payment', 'technical', 'other'];
        $category = trim((string)($input['category'] ?? 'general'));
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'general';
        }

        $allowedPriorities = ['low', 'normal', 'high', 'urgent'];
        $priority = trim((string)($input['priority'] ?? 'normal'));
        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'normal';
        }

        $message = trim((string)($input['message'] ?? ''));
        if ($message === '' || strlen($message) < 10) {
            throw new \InvalidArgumentException('Message must be at least 10 characters');
        }

        $ticketId = $this->repo->createTicket($userId, [
            'subject'  => $subject,
            'category' => $category,
            'priority' => $priority,
        ]);

        $this->repo->addMessage($ticketId, $userId, 'user', $message);
        return $ticketId;
    }

    public function reply(int $userId, int $ticketId, string $message): void
    {
        $message = trim($message);
        if ($message === '' || strlen($message) < 2) {
            throw new \InvalidArgumentException('Reply message is too short');
        }
        $ticket = $this->repo->findTicket($userId, $ticketId);
        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found');
        }
        if (in_array($ticket['status'], ['resolved', 'closed'], true)) {
            throw new \RuntimeException('Cannot reply to a closed ticket');
        }
        $this->repo->addMessage($ticketId, $userId, 'user', $message);
    }
}
