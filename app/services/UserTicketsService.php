<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserTicketsRepository;
use InvalidArgumentException;
use RuntimeException;

final class UserTicketsService
{
    private readonly UserTicketsRepository $repo;

    private const ATTACH_MAX_BYTES = 10 * 1024 * 1024;
    private const ATTACH_MIMES     = ['image/jpeg','image/png','image/gif','image/webp',
                                       'application/pdf','text/plain','application/zip'];

    public function __construct(?UserTicketsRepository $repo = null)
    {
        $this->repo = $repo ?? new UserTicketsRepository();
    }

    public function tickets(int $userId): array
    {
        return $this->repo->tickets($userId);
    }

    public function categories(): array
    {
        return $this->repo->categories();
    }

    public function getTicket(int $userId, int $ticketId): array
    {
        $ticket = $this->repo->findTicket($userId, $ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket not found');
        }
        return [
            'ticket'   => $ticket,
            'messages' => $this->repo->messages($ticketId),
        ];
    }

    public function create(int $userId, array $input, ?array $file = null): int
    {
        $subject = trim((string)($input['subject'] ?? ''));
        if ($subject === '' || strlen($subject) < 5) {
            throw new InvalidArgumentException('Subject must be at least 5 characters');
        }

        $category = trim((string)($input['category'] ?? 'general'));
        $priority = trim((string)($input['priority'] ?? 'medium'));

        $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'medium';
        }

        $message = trim((string)($input['message'] ?? ''));
        if ($message === '' || strlen($message) < 10) {
            throw new InvalidArgumentException('Message must be at least 10 characters');
        }

        $ticketId = $this->repo->createTicket($userId, [
            'subject'  => $subject,
            'category' => $category,
            'priority' => $priority,
        ]);

        // Save initial message (optionally with attachment)
        $attachUrl = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $attachUrl = $this->storeFile($ticketId, $userId, $file);
        }

        $msgId = $this->repo->addMessage($ticketId, $userId, 'user', $message, $attachUrl);

        if ($attachUrl !== null) {
            $this->repo->addAttachment([
                'ticket_id'     => $ticketId,
                'message_id'    => $msgId,
                'uploader_type' => 'user',
                'uploader_id'   => $userId,
                'original_name' => (string)($file['name'] ?? basename($attachUrl)),
                'stored_name'   => basename($attachUrl),
                'file_url'      => $attachUrl,
                'mime_type'     => (string)($file['type'] ?? 'application/octet-stream'),
                'file_size'     => (int)($file['size'] ?? 0),
            ]);
        }

        return $ticketId;
    }

    public function reply(int $userId, int $ticketId, string $message, ?array $file = null): void
    {
        $message = trim($message);
        if ($message === '' || strlen($message) < 2) {
            throw new InvalidArgumentException('Reply message is too short');
        }
        $ticket = $this->repo->findTicket($userId, $ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket not found');
        }
        if (in_array((string)($ticket['status'] ?? 'open'), ['resolved', 'closed'], true)) {
            throw new RuntimeException('Cannot reply to a closed ticket');
        }

        $attachUrl = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $attachUrl = $this->storeFile($ticketId, $userId, $file);
        }

        $msgId = $this->repo->addMessage($ticketId, $userId, 'user', $message, $attachUrl);

        if ($attachUrl !== null) {
            $this->repo->addAttachment([
                'ticket_id'     => $ticketId,
                'message_id'    => $msgId,
                'uploader_type' => 'user',
                'uploader_id'   => $userId,
                'original_name' => (string)($file['name'] ?? basename($attachUrl)),
                'stored_name'   => basename($attachUrl),
                'file_url'      => $attachUrl,
                'mime_type'     => (string)($file['type'] ?? 'application/octet-stream'),
                'file_size'     => (int)($file['size'] ?? 0),
            ]);
        }
    }

    public function closeTicket(int $userId, int $ticketId): void
    {
        $ticket = $this->repo->findTicket($userId, $ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket not found');
        }
        if (in_array((string)($ticket['status'] ?? 'open'), ['resolved', 'closed'], true)) {
            throw new RuntimeException('Ticket is already closed');
        }
        $this->repo->closeTicket($userId, $ticketId);
    }

    public function rateTicket(int $userId, int $ticketId, int $rating, ?string $comment): void
    {
        $ticket = $this->repo->findTicket($userId, $ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket not found');
        }
        if (!in_array((string)($ticket['status'] ?? 'open'), ['resolved', 'closed'], true)) {
            throw new RuntimeException('You can only rate resolved or closed tickets');
        }
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Rating must be between 1 and 5');
        }
        $comment = ($comment !== null) ? trim($comment) : null;
        if ($comment === '') {
            $comment = null;
        }
        $this->repo->saveRating($userId, $ticketId, $rating, $comment);
    }

    private function storeFile(int $ticketId, int $userId, array $file): string
    {
        $mimeType = mime_content_type((string)($file['tmp_name'] ?? ''));
        if ($mimeType === false) {
            $mimeType = (string)($file['type'] ?? 'application/octet-stream');
        }
        if (!in_array($mimeType, self::ATTACH_MIMES, true)) {
            throw new InvalidArgumentException('Unsupported file type for attachment');
        }
        if (((int)($file['size'] ?? 0)) > self::ATTACH_MAX_BYTES) {
            throw new InvalidArgumentException('Attachment must be smaller than 10 MB');
        }

        $uploadDir = app_path('public/uploads/tickets/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext        = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $storedName = 'ticket_' . $ticketId . '_u' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target     = $uploadDir . $storedName;

        if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $target)) {
            throw new RuntimeException('Failed to save attachment');
        }

        return '/uploads/tickets/' . $storedName;
    }
}
