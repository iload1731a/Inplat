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
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $tickets = (new UserTicketsService())->tickets($userId);
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
        $this->userView('user/tickets/create', [
            'title'       => 'New Support Ticket',
            'userSection' => 'tickets',
        ]);
    }

    public function store(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $ticketId = (new UserTicketsService())->create($userId, $request->all());
            Response::json(['ok' => true, 'message' => 'Ticket created', 'redirect' => '/user/tickets/' . $ticketId]);
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
            $data = (new UserTicketsService())->getTicket($userId, $ticketId);
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

        try {
            (new UserTicketsService())->reply($userId, $ticketId, $message);
            Response::json(['ok' => true, 'message' => 'Reply sent']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
