<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserTicketsService;
use Throwable;

final class TicketsController extends BaseController
{
    private function svc(): UserTicketsService
    {
        return new UserTicketsService();
    }

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $tickets = $this->svc()->tickets($userId);
        } catch (Throwable) {
            $tickets = [];
        }

        $this->userView('user/tickets/index', [
            'title'       => 'Support Tickets',
            'userSection' => 'tickets',
            'tickets'     => $tickets,
        ]);
    }

    public function create(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        try {
            $categories = $this->svc()->categories();
        } catch (Throwable) {
            $categories = [];
        }

        $this->userView('user/tickets/create', [
            'title'       => 'New Support Ticket',
            'userSection' => 'tickets',
            'categories'  => $categories,
        ]);
    }

    public function store(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $file   = $_FILES['attachment'] ?? null;

        try {
            $ticketId = $this->svc()->create(
                $userId,
                $request->all(),
                ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ? $file : null
            );
            Response::json(['ok' => true, 'message' => 'Ticket created', 'redirect' => '/user/tickets/view?id=' . $ticketId]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId   = (int)(Session::get('auth.user_id') ?? 0);
        $ticketId = (int)$request->input('id');

        try {
            $data = $this->svc()->getTicket($userId, $ticketId);
        } catch (Throwable $e) {
            $this->userView('user/tickets/index', [
                'title'       => 'Ticket Not Found',
                'userSection' => 'tickets',
                'tickets'     => [],
                'error'       => $e->getMessage(),
            ]);
            return;
        }

        $this->userView('user/tickets/view', array_merge($data, [
            'title'       => 'Ticket #' . $ticketId,
            'userSection' => 'tickets',
        ]));
    }

    public function reply(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId   = (int)(Session::get('auth.user_id') ?? 0);
        $ticketId = (int)$request->input('ticket_id');
        $message  = (string)$request->input('message');
        $file     = $_FILES['attachment'] ?? null;

        try {
            $this->svc()->reply(
                $userId,
                $ticketId,
                $message,
                ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ? $file : null
            );
            Response::json(['ok' => true, 'message' => 'Reply sent']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function close(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId   = (int)(Session::get('auth.user_id') ?? 0);
        $ticketId = (int)$request->input('ticket_id');

        try {
            $this->svc()->closeTicket($userId, $ticketId);
            Response::json(['ok' => true, 'message' => 'Ticket closed', 'redirect' => '/user/tickets']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function rate(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId   = (int)(Session::get('auth.user_id') ?? 0);
        $ticketId = (int)$request->input('ticket_id');
        $rating   = (int)$request->input('rating');
        $comment  = (string)($request->input('comment') ?? '');

        try {
            $this->svc()->rateTicket($userId, $ticketId, $rating, $comment !== '' ? $comment : null);
            Response::json(['ok' => true, 'message' => 'Thank you for your feedback!']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
