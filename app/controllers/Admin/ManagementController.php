<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminManagementService;
use Throwable;

final class ManagementController extends AdminBaseController
{
    public function users(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search' => trim((string)$request->input('search', '')),
            'status' => trim((string)$request->input('status', '')),
            'kyc_status' => trim((string)$request->input('kyc_status', '')),
        ];

        $service = new AdminManagementService();
        $data = $service->userIndex($filters);

        $this->view('admin/management/users', [
            'title' => 'Admin · User Management',
            'username' => $this->adminUsername(),
            'adminSection' => 'users',
            'filters' => $filters,
            ...$data,
        ]);
    }

    public function user(Request $request): void
    {
        $this->bootAdmin();

        $userId = (int)$request->input('id', 0);
        if ($userId <= 0) {
            Response::redirect('/admin/users');
        }

        $service = new AdminManagementService();
        $data = $service->userShow($userId);
        if (($data['user'] ?? null) === null) {
            Response::redirect('/admin/users');
        }

        $this->view('admin/management/user', [
            'title' => 'Admin · User Detail',
            'username' => $this->adminUsername(),
            'adminSection' => 'users',
            ...$data,
        ]);
    }

    public function updateUser(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $userId = (int)$request->input('user_id', 0);
        if ($userId <= 0) {
            Response::json(['ok' => false, 'message' => 'User not found.'], 422);
        }

        try {
            (new AdminManagementService())->updateUser($this->adminId(), $userId, [
                'email' => trim((string)$request->input('email', '')),
                'phone' => trim((string)$request->input('phone', '')),
                'first_name' => trim((string)$request->input('first_name', '')),
                'last_name' => trim((string)$request->input('last_name', '')),
                'country_code' => strtoupper(trim((string)$request->input('country_code', ''))),
                'timezone' => trim((string)$request->input('timezone', 'UTC')),
                'preferred_language' => trim((string)$request->input('preferred_language', 'en')),
                'account_type' => trim((string)$request->input('account_type', 'individual')),
                'status' => trim((string)$request->input('status', 'pending')),
                'kyc_status' => trim((string)$request->input('kyc_status', 'unverified')),
                'kyc_level' => max(0, (int)$request->input('kyc_level', 0)),
                'date_of_birth' => trim((string)$request->input('date_of_birth', '')),
                'gender' => trim((string)$request->input('gender', '')),
                'address_line1' => trim((string)$request->input('address_line1', '')),
                'address_line2' => trim((string)$request->input('address_line2', '')),
                'city' => trim((string)$request->input('city', '')),
                'state_province' => trim((string)$request->input('state_province', '')),
                'postal_code' => trim((string)$request->input('postal_code', '')),
                'profile_country_code' => strtoupper(trim((string)$request->input('profile_country_code', ''))),
                'occupation' => trim((string)$request->input('occupation', '')),
                'source_of_funds' => trim((string)$request->input('source_of_funds', '')),
                'annual_income_range' => trim((string)$request->input('annual_income_range', '')),
                'company_name' => trim((string)$request->input('company_name', '')),
                'company_registration_no' => trim((string)$request->input('company_registration_no', '')),
                'tax_id' => trim((string)$request->input('tax_id', '')),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'User profile updated.', 'redirect' => '/admin/user?id=' . $userId]);
    }

    public function reviewKyc(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $documentId = (int)$request->input('document_id', 0);
        $status = trim((string)$request->input('status', ''));
        $notes = trim((string)$request->input('notes', ''));

        try {
            $userId = (new AdminManagementService())->reviewKyc($this->adminId(), $documentId, $status, $notes);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'KYC document reviewed.', 'redirect' => '/admin/user?id=' . $userId]);
    }

    public function adjustBalance(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $result = (new AdminManagementService())->adjustBalance(
                $this->adminId(),
                (int)$request->input('wallet_id', 0),
                trim((string)$request->input('direction', 'credit')),
                trim((string)$request->input('amount', '0')),
                trim((string)$request->input('reason', '')),
            );
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json([
            'ok' => true,
            'message' => sprintf('Wallet adjusted to %s %0.8f.', $result['currency_code'], $result['new_balance']),
            'redirect' => '/admin/user?id=' . $result['user_id'],
        ]);
    }

    public function finance(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'deposit_status' => trim((string)$request->input('deposit_status', '')),
            'deposit_search' => trim((string)$request->input('deposit_search', '')),
            'withdrawal_status' => trim((string)$request->input('withdrawal_status', '')),
            'withdrawal_search' => trim((string)$request->input('withdrawal_search', '')),
        ];

        $data = (new AdminManagementService())->financeIndex($filters);

        $this->view('admin/management/finance', [
            'title' => 'Admin · Finance Operations',
            'username' => $this->adminUsername(),
            'adminSection' => 'finance',
            'filters' => $filters,
            ...$data,
        ]);
    }

    public function reviewDeposit(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $result = (new AdminManagementService())->reviewDeposit(
                $this->adminId(),
                (int)$request->input('deposit_id', 0),
                trim((string)$request->input('status', 'pending')),
                trim((string)$request->input('notes', '')),
            );
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Deposit updated.', 'redirect' => '/admin/finance']);
    }

    public function reviewWithdrawal(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $result = (new AdminManagementService())->reviewWithdrawal(
                $this->adminId(),
                (int)$request->input('withdrawal_id', 0),
                trim((string)$request->input('status', 'pending')),
                trim((string)$request->input('reason', '')),
            );
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Withdrawal updated.', 'redirect' => '/admin/finance']);
    }

    public function communications(Request $request): void
    {
        $this->bootAdmin();

        $prefillUserId = max(0, (int)$request->input('user_id', 0));
        $prefillChannel = trim((string)$request->input('channel', ''));

        $data = (new AdminManagementService())->communicationsIndex($this->adminId());
        $this->view('admin/management/communications', [
            'title' => 'Admin · Communications',
            'username' => $this->adminUsername(),
            'adminSection' => 'communications',
            'prefillUserId' => $prefillUserId,
            'prefillChannel' => in_array($prefillChannel, ['in_app', 'email', 'sms', 'push'], true) ? $prefillChannel : '',
            ...$data,
        ]);
    }

    public function sendNotification(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $result = (new AdminManagementService())->sendNotifications($this->adminId(), [
                'audience' => trim((string)$request->input('audience', 'single')),
                'user_id' => (int)$request->input('user_id', 0),
                'status_filter' => trim((string)$request->input('status_filter', '')),
                'kyc_filter' => trim((string)$request->input('kyc_filter', '')),
                'channel' => trim((string)$request->input('channel', 'in_app')),
                'type' => trim((string)$request->input('type', 'admin_notice')),
                'title' => trim((string)$request->input('title', '')),
                'message' => trim((string)$request->input('message', '')),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $message = 'Notifications queued for ' . $result['created'] . ' user(s).';
        if ($result['email_attempts'] > 0) {
            $message .= ' Email transport success ' . $result['email_success'] . '/' . $result['email_attempts'] . '.';
        }

        Response::json(['ok' => true, 'message' => $message, 'redirect' => '/admin/communications']);
    }

    public function saveEmailTemplate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $templateId = (new AdminManagementService())->saveEmailTemplate($this->adminId(), [
                'template_id' => (int)$request->input('template_id', 0),
                'template_key' => trim((string)$request->input('template_key', '')),
                'subject' => trim((string)$request->input('subject', '')),
                'body_html' => (string)$request->input('body_html', ''),
                'is_active' => (string)$request->input('is_active', '0'),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Email template saved.', 'redirect' => '/admin/communications']);
    }

    public function support(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'ticket_status' => trim((string)$request->input('ticket_status', '')),
            'ticket_priority' => trim((string)$request->input('ticket_priority', '')),
            'ticket_search' => trim((string)$request->input('ticket_search', '')),
        ];
        $ticketId = (int)$request->input('ticket_id', 0);

        $data = (new AdminManagementService())->supportIndex($this->adminId(), $filters, $ticketId);
        $this->view('admin/management/support', [
            'title' => 'Admin · Support Desk',
            'username' => $this->adminUsername(),
            'adminSection' => 'support',
            'filters' => $filters,
            ...$data,
        ]);
    }

    public function updateSupportTicket(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ticketId = (int)$request->input('ticket_id', 0);
        try {
            (new AdminManagementService())->updateSupportTicket(
                $this->adminId(),
                $ticketId,
                (int)$request->input('assigned_to', 0),
                trim((string)$request->input('status', 'open')),
            );
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Support ticket updated.', 'redirect' => '/admin/support?ticket_id=' . $ticketId]);
    }

    public function replySupportTicket(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ticketId = (int)$request->input('ticket_id', 0);
        try {
            (new AdminManagementService())->replySupportTicket(
                $this->adminId(),
                $ticketId,
                (string)$request->input('message', ''),
                trim((string)$request->input('status', 'in_progress')),
            );
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Support reply sent.', 'redirect' => '/admin/support?ticket_id=' . $ticketId]);
    }

    public function settings(Request $request): void
    {
        $this->bootAdmin();

        $category = trim((string)$request->input('category', ''));
        $data = (new AdminManagementService())->settingsIndex($category);

        $this->view('admin/management/settings', [
            'title' => 'Admin · Settings',
            'username' => $this->adminUsername(),
            'adminSection' => 'settings',
            'selectedCategory' => $category,
            ...$data,
        ]);
    }

    public function updateSetting(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $settingId = (int)$request->input('setting_id', 0);
        try {
            (new AdminManagementService())->updateSetting(
                $this->adminId(),
                $settingId,
                trim((string)$request->input('value_type', 'string')),
                $request->input('setting_value') !== null ? (string)$request->input('setting_value') : null,
            );
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $category = trim((string)$request->input('category', ''));
        $redirect = '/admin/settings' . ($category !== '' ? '?category=' . urlencode($category) : '');
        Response::json(['ok' => true, 'message' => 'Setting updated.', 'redirect' => $redirect]);
    }

    public function trading(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'search' => trim((string)$request->input('search', '')),
            'market_type' => trim((string)$request->input('market_type', '')),
            'is_active' => $request->input('is_active', ''),
            'status' => trim((string)$request->input('status', '')),
        ];

        $data = (new AdminManagementService())->tradingIndex($filters);

        $this->view('admin/management/trading', [
            'title' => 'Admin · Trading Management',
            'username' => $this->adminUsername(),
            'adminSection' => 'trading',
            'filters' => $filters,
            ...$data,
        ]);
    }

    public function updateTradingPair(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $pairId = (int)$request->input('pair_id', 0);

        try {
            (new AdminManagementService())->updateTradingPair($this->adminId(), $pairId, [
                'maker_fee_percent' => $request->input('maker_fee_percent', '0'),
                'taker_fee_percent' => $request->input('taker_fee_percent', '0'),
                'min_order_size' => $request->input('min_order_size', '0'),
                'max_order_size' => trim((string)$request->input('max_order_size', '')),
                'min_notional' => $request->input('min_notional', '0'),
                'max_leverage' => $request->input('max_leverage', '1'),
                'price_precision' => $request->input('price_precision', '2'),
                'quantity_precision' => $request->input('quantity_precision', '6'),
                'is_active' => $request->input('is_active', '0'),
                'trading_enabled' => $request->input('trading_enabled', '0'),
                'is_visible' => $request->input('is_visible', '0'),
                'display_order' => $request->input('display_order', '0'),
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Trading pair updated.']);
    }

    public function haltTrading(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $pairId = (int)$request->input('pair_id', 0);
        $reason = trim((string)$request->input('reason', ''));

        try {
            (new AdminManagementService())->haltTrading($this->adminId(), $pairId, $reason);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Trading halt created.', 'redirect' => '/admin/trading']);
    }

    public function resolveHalt(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $haltId = (int)$request->input('halt_id', 0);

        try {
            (new AdminManagementService())->resolveHalt($this->adminId(), $haltId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Trading halt resolved.', 'redirect' => '/admin/trading']);
    }

    public function risk(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status' => trim((string)$request->input('status', '')),
            'severity' => trim((string)$request->input('severity', '')),
        ];

        $data = (new AdminManagementService())->riskIndex($filters);

        $this->view('admin/management/risk', [
            'title' => 'Admin · Risk & Compliance',
            'username' => $this->adminUsername(),
            'adminSection' => 'risk',
            'filters' => $filters,
            ...$data,
        ]);
    }

    public function updateRiskFlag(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $flagId = (int)$request->input('flag_id', 0);
        $status = trim((string)$request->input('status', ''));
        $assignedTo = (int)$request->input('assigned_to', 0);

        try {
            (new AdminManagementService())->updateRiskFlag($this->adminId(), $flagId, $status, $assignedTo);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Risk flag updated.']);
    }

    public function blockIP(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $ip = trim((string)$request->input('ip_address', ''));
        $reason = trim((string)$request->input('reason', ''));

        try {
            (new AdminManagementService())->blockIP($this->adminId(), $ip, $reason);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'IP address blocked.', 'redirect' => '/admin/risk']);
    }

    public function unblockIP(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $entryId = (int)$request->input('entry_id', 0);

        try {
            (new AdminManagementService())->unblockIP($this->adminId(), $entryId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'IP unblocked.', 'redirect' => '/admin/risk']);
    }

    // -----------------------------------------------------------------------
    // Enhanced User Actions
    // -----------------------------------------------------------------------

    public function banUser(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $userId = (int)$request->input('user_id', 0);
        $reason = trim((string)$request->input('reason', ''));

        try {
            (new AdminManagementService())->banUser($this->adminId(), $userId, $reason);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'User banned.', 'redirect' => '/admin/user?id=' . $userId]);
    }

    public function unbanUser(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $userId = (int)$request->input('user_id', 0);

        try {
            (new AdminManagementService())->unbanUser($this->adminId(), $userId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'User unbanned.', 'redirect' => '/admin/user?id=' . $userId]);
    }

    public function resetUserTwoFactor(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $userId = (int)$request->input('user_id', 0);

        try {
            (new AdminManagementService())->resetUserTwoFactor($this->adminId(), $userId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Two-factor authentication reset.', 'redirect' => '/admin/user?id=' . $userId]);
    }

    public function revokeUserSessions(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $userId = (int)$request->input('user_id', 0);

        try {
            (new AdminManagementService())->revokeUserSessions($this->adminId(), $userId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'All user sessions revoked.', 'redirect' => '/admin/user?id=' . $userId]);
    }

    public function changeUserPassword(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $userId = (int)$request->input('user_id', 0);

        try {
            (new AdminManagementService())->changeUserPassword(
                $this->adminId(),
                $userId,
                (string)$request->input('new_password', ''),
                (string)$request->input('confirm_password', '')
            );
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'User password changed.', 'redirect' => '/admin/user?id=' . $userId]);
    }

    public function loginAsUser(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $userId = (int)$request->input('user_id', 0);

        try {
            (new AdminManagementService())->loginAsUser($this->adminId(), $userId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'Impersonation enabled.', 'redirect' => '/dashboard']);
    }

    public function stopImpersonation(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        try {
            $lastUserId = (new AdminManagementService())->stopImpersonation($this->adminId());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $redirectUserId = $lastUserId > 0 ? $lastUserId : 0;
        Response::json(['ok' => true, 'message' => 'Returned to admin session.', 'redirect' => '/admin/user?id=' . $redirectUserId]);
    }
}
