<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;

final class AdminManagementService
{
    public function __construct(private readonly AdminManagementRepository $repository = new AdminManagementRepository())
    {
    }

    public function adminOverview(int $adminId): array
    {
        return [
            'admin' => $this->repository->findAdminById($adminId),
            'admins' => $this->repository->listActiveAdmins(),
        ];
    }

    public function userIndex(array $filters): array
    {
        return ['users' => $this->repository->listUsers($filters)];
    }

    public function userShow(int $userId): array
    {
        return [
            'user' => $this->repository->getUserDetails($userId),
            'wallets' => $this->repository->getUserWallets($userId),
            'kycDocuments' => $this->repository->getUserKycDocuments($userId),
            'loginHistory' => $this->repository->getUserLoginHistory($userId),
            'notifications' => $this->repository->getUserNotifications($userId),
        ];
    }

    public function updateUser(int $adminId, int $userId, array $payload): void
    {
        if (!filter_var($payload['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $allowedStatuses = ['active', 'suspended', 'banned', 'pending', 'closed'];
        $allowedKycStatuses = ['unverified', 'pending', 'approved', 'rejected'];
        $allowedAccountTypes = ['individual', 'corporate'];
        $allowedGenders = ['', 'male', 'female', 'other', 'undisclosed'];

        if (!in_array((string)$payload['status'], $allowedStatuses, true)) {
            throw new InvalidArgumentException('Invalid user status.');
        }
        if (!in_array((string)$payload['kyc_status'], $allowedKycStatuses, true)) {
            throw new InvalidArgumentException('Invalid KYC status.');
        }
        if (!in_array((string)$payload['account_type'], $allowedAccountTypes, true)) {
            throw new InvalidArgumentException('Invalid account type.');
        }
        if (!in_array((string)$payload['gender'], $allowedGenders, true)) {
            throw new InvalidArgumentException('Invalid profile gender.');
        }

        $this->repository->updateUserProfile($userId, $payload);
        $this->repository->logAdminAction(
            $adminId,
            'update_user',
            'users',
            (string)$userId,
            null,
            [
                'status' => $payload['status'],
                'kyc_status' => $payload['kyc_status'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
            ],
            RequestContext::ipAddress()
        );
    }

    public function reviewKyc(int $adminId, int $documentId, string $status, string $notes): int
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Invalid KYC review status.');
        }

        $userId = $this->repository->reviewKycDocument($documentId, $adminId, $status, trim($notes));
        $summary = $this->repository->refreshUserKycSummary($userId);
        $this->repository->logAdminAction(
            $adminId,
            'review_kyc',
            'kyc_documents',
            (string)$documentId,
            null,
            ['status' => $status, 'user_id' => $userId, 'summary' => $summary],
            RequestContext::ipAddress()
        );

        return $userId;
    }

    public function adjustBalance(int $adminId, int $walletId, string $direction, string $amount, string $reason): array
    {
        if (!in_array($direction, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException('Invalid adjustment direction.');
        }
        if (!is_numeric($amount) || (float)$amount <= 0) {
            throw new InvalidArgumentException('Enter a valid adjustment amount.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Adjustment reason is required.');
        }

        $result = $this->repository->adjustWalletBalance($walletId, $adminId, $direction, $amount, $reason);
        $this->repository->logAdminAction(
            $adminId,
            'adjust_wallet_balance',
            'wallets',
            (string)$walletId,
            ['previous_balance' => $result['previous_balance']],
            ['new_balance' => $result['new_balance'], 'direction' => $direction, 'amount' => (float)$amount, 'reason' => $reason],
            RequestContext::ipAddress()
        );

        return $result;
    }

    public function financeIndex(array $filters): array
    {
        return [
            'deposits' => $this->repository->listDeposits($filters),
            'withdrawals' => $this->repository->listWithdrawals($filters),
        ];
    }

    public function reviewDeposit(int $adminId, int $depositId, string $status, string $notes): array
    {
        if (!in_array($status, ['pending', 'confirmed', 'credited', 'failed', 'flagged'], true)) {
            throw new InvalidArgumentException('Invalid deposit status.');
        }

        $result = $this->repository->reviewDeposit($depositId, $adminId, $status, trim($notes));
        $this->repository->logAdminAction(
            $adminId,
            'review_deposit',
            'deposits',
            (string)$depositId,
            null,
            ['status' => $status, 'notes' => trim($notes)],
            RequestContext::ipAddress()
        );

        return $result;
    }

    public function reviewWithdrawal(int $adminId, int $withdrawalId, string $status, string $reason): array
    {
        if (!in_array($status, ['pending', 'approved', 'processing', 'completed', 'rejected', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid withdrawal status.');
        }

        $result = $this->repository->reviewWithdrawal($withdrawalId, $adminId, $status, trim($reason));
        $this->repository->logAdminAction(
            $adminId,
            'review_withdrawal',
            'withdrawals',
            (string)$withdrawalId,
            null,
            ['status' => $status, 'reason' => trim($reason)],
            RequestContext::ipAddress()
        );

        return $result;
    }

    public function communicationsIndex(int $adminId): array
    {
        return [
            'notifications' => $this->repository->listRecentNotifications(),
            'emailTemplates' => $this->repository->listEmailTemplates(),
            ...$this->adminOverview($adminId),
        ];
    }

    public function sendNotifications(int $adminId, array $payload): array
    {
        $audience = (string)($payload['audience'] ?? 'single');
        $channel = (string)($payload['channel'] ?? 'in_app');
        $type = trim((string)($payload['type'] ?? 'admin_notice'));
        $title = trim((string)($payload['title'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));
        $statusFilter = trim((string)($payload['status_filter'] ?? ''));
        $kycFilter = trim((string)($payload['kyc_filter'] ?? ''));
        $userId = (int)($payload['user_id'] ?? 0);

        if (!in_array($audience, ['single', 'active', 'kyc_pending', 'all'], true)) {
            throw new InvalidArgumentException('Invalid notification audience.');
        }
        if (!in_array($channel, ['in_app', 'email', 'sms', 'push'], true)) {
            throw new InvalidArgumentException('Invalid notification channel.');
        }
        if ($title === '' || $message === '') {
            throw new InvalidArgumentException('Notification title and message are required.');
        }
        if ($audience === 'single' && $userId <= 0) {
            throw new InvalidArgumentException('Select a user to message.');
        }

        if ($audience === 'active' && $statusFilter === '') {
            $statusFilter = 'active';
        }
        if ($audience === 'kyc_pending' && $kycFilter === '') {
            $kycFilter = 'pending';
        }

        $recipients = $this->repository->resolveNotificationRecipients($audience === 'all' ? '' : $audience, $userId, $statusFilter, $kycFilter);
        if ($recipients === []) {
            throw new InvalidArgumentException('No matching recipients found.');
        }

        $created = $this->repository->createNotifications($recipients, $type, $title, $message, $channel);
        $emailAttempts = 0;
        $emailSuccess = 0;
        if ($channel === 'email') {
            foreach ($recipients as $recipient) {
                $emailAttempts++;
                if ($this->sendEmail((string)$recipient['email'], $title, $message)) {
                    $emailSuccess++;
                }
            }
        }

        $this->repository->logAdminAction(
            $adminId,
            'send_notifications',
            'notifications',
            (string)$created,
            null,
            ['audience' => $audience, 'channel' => $channel, 'count' => $created, 'type' => $type],
            RequestContext::ipAddress()
        );

        return [
            'created' => $created,
            'email_attempts' => $emailAttempts,
            'email_success' => $emailSuccess,
        ];
    }

    public function saveEmailTemplate(int $adminId, array $payload): int
    {
        $templateId = (int)($payload['template_id'] ?? 0);
        $templateKey = trim((string)($payload['template_key'] ?? ''));
        $subject = trim((string)($payload['subject'] ?? ''));
        $bodyHtml = trim((string)($payload['body_html'] ?? ''));
        $isActive = ((string)($payload['is_active'] ?? '0')) === '1' ? 1 : 0;

        if ($subject === '' || $bodyHtml === '') {
            throw new InvalidArgumentException('Template subject and body are required.');
        }
        if ($templateId <= 0 && $templateKey === '') {
            throw new InvalidArgumentException('Template key is required when creating a new template.');
        }

        $savedId = $this->repository->saveEmailTemplate([
            'template_id' => $templateId,
            'template_key' => $templateKey,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'is_active' => $isActive,
        ], $adminId);

        $this->repository->logAdminAction(
            $adminId,
            'save_email_template',
            'email_templates',
            (string)$savedId,
            null,
            ['subject' => $subject, 'is_active' => $isActive],
            RequestContext::ipAddress()
        );

        return $savedId;
    }

    public function supportIndex(int $adminId, array $filters, int $ticketId): array
    {
        return [
            'tickets' => $this->repository->listSupportTickets($filters),
            'ticket' => $ticketId > 0 ? $this->repository->getSupportTicket($ticketId) : null,
            'messages' => $ticketId > 0 ? $this->repository->getSupportMessages($ticketId) : [],
            ...$this->adminOverview($adminId),
        ];
    }

    public function updateSupportTicket(int $adminId, int $ticketId, int $assignedTo, string $status): void
    {
        if (!in_array($status, ['open', 'in_progress', 'waiting_on_user', 'resolved', 'closed'], true)) {
            throw new InvalidArgumentException('Invalid support status.');
        }

        $this->repository->updateSupportTicket($ticketId, $assignedTo, $status);
        $this->repository->logAdminAction(
            $adminId,
            'update_support_ticket',
            'support_tickets',
            (string)$ticketId,
            null,
            ['assigned_to' => $assignedTo, 'status' => $status],
            RequestContext::ipAddress()
        );
    }

    public function replySupportTicket(int $adminId, int $ticketId, string $message, string $status): void
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Ticket reply message is required.');
        }
        if (!in_array($status, ['open', 'in_progress', 'waiting_on_user', 'resolved', 'closed'], true)) {
            throw new InvalidArgumentException('Invalid support status.');
        }

        $this->repository->replySupportTicket($ticketId, $adminId, $message, $status);
        $this->repository->logAdminAction(
            $adminId,
            'reply_support_ticket',
            'ticket_messages',
            (string)$ticketId,
            null,
            ['status' => $status],
            RequestContext::ipAddress()
        );
    }

    public function settingsIndex(string $category): array
    {
        return [
            'categories' => $this->repository->listSettingCategories(),
            'settings' => $this->repository->listSystemSettings($category),
        ];
    }

    public function updateSetting(int $adminId, int $settingId, string $valueType, ?string $value): void
    {
        $normalized = $this->normalizeSettingValue($valueType, $value);
        $previous = $this->repository->updateSystemSetting($settingId, $adminId, $normalized);
        $this->repository->logAdminAction(
            $adminId,
            'update_system_setting',
            'system_settings',
            (string)$settingId,
            ['setting_value' => $previous['setting_value'] ?? null],
            ['setting_value' => $normalized],
            RequestContext::ipAddress()
        );
    }

    private function normalizeSettingValue(string $valueType, ?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return match ($valueType) {
            'boolean' => $this->normalizeBoolean($value),
            'number' => $this->normalizeNumber($value),
            'json' => $this->normalizeJson($value),
            default => $value,
        };
    }

    private function normalizeBoolean(?string $value): string
    {
        $truthy = ['1', 'true', 'yes', 'on'];
        $falsy = ['0', 'false', 'no', 'off', ''];
        $normalized = strtolower((string)$value);
        if (in_array($normalized, $truthy, true)) {
            return '1';
        }
        if (in_array($normalized, $falsy, true)) {
            return '0';
        }

        throw new InvalidArgumentException('Boolean setting values must be true/false or 1/0.');
    }

    private function normalizeNumber(?string $value): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw new InvalidArgumentException('Number setting values must be numeric.');
        }

        return $value;
    }

    private function normalizeJson(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('JSON setting value is invalid.');
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sendEmail(string $email, string $title, string $message): bool
    {
        $fromHost = parse_url((string)config('app.url', 'http://localhost'), PHP_URL_HOST);
        $fromHost = is_string($fromHost) && $fromHost !== '' ? $fromHost : 'localhost';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: no-reply@' . $fromHost,
        ];

        $sent = @mail($email, $title, $message, implode("\r\n", $headers));
        $logLine = sprintf(
            "[%s] Admin notification email %s for %s title=%s%s",
            date('c'),
            $sent ? 'sent' : 'failed',
            $email,
            $title,
            PHP_EOL
        );
        file_put_contents((string)config('app.log_file'), $logLine, FILE_APPEND | LOCK_EX);

        return $sent;
    }

    // -------------------------------------------------------------------------
    // Trading Management
    // -------------------------------------------------------------------------

    public function tradingIndex(array $filters): array
    {
        return [
            'pairs' => $this->repository->listTradingPairs($filters),
            'feeTiers' => $this->repository->listFeeTiers(),
            'activeHalts' => $this->repository->listActiveTradingHalts(),
            'recentHalts' => $this->repository->listRecentTradingHalts(20),
            'recentOrders' => $this->repository->listAdminRecentOrders($filters),
        ];
    }

    public function updateTradingPair(int $adminId, int $pairId, array $payload): void
    {
        if ($pairId <= 0) {
            throw new \InvalidArgumentException('Invalid trading pair ID.');
        }

        $this->repository->updateTradingPair($pairId, $adminId, $payload);
    }

    public function haltTrading(int $adminId, int $pairId, string $reason): int
    {
        if ($pairId <= 0) {
            throw new \InvalidArgumentException('Invalid trading pair ID.');
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Halt reason is required.');
        }

        return $this->repository->createTradingHalt($pairId, $adminId, trim($reason));
    }

    public function resolveHalt(int $adminId, int $haltId): void
    {
        if ($haltId <= 0) {
            throw new \InvalidArgumentException('Invalid halt ID.');
        }

        $this->repository->resolveTradingHalt($haltId, $adminId);
    }

    // -------------------------------------------------------------------------
    // Risk & Compliance
    // -------------------------------------------------------------------------

    public function riskIndex(array $filters): array
    {
        return [
            'summary' => $this->repository->getRiskSummary(),
            'riskFlags' => $this->repository->listRiskFlags($filters),
            'ipBlacklist' => $this->repository->listIPBlacklist(),
            'sarCases' => $this->repository->listSARCases($filters),
            'sanctionedCountries' => $this->repository->listSanctionedCountries(),
        ];
    }

    public function updateRiskFlag(int $adminId, int $flagId, string $status, int $assignedTo): void
    {
        $allowed = ['open', 'investigating', 'resolved', 'false_positive'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid risk flag status.');
        }

        $this->repository->updateRiskFlag($flagId, $adminId, $status, $assignedTo);
    }

    public function blockIP(int $adminId, string $ip, string $reason): void
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('Invalid IP address format.');
        }

        $this->repository->blockIP($ip, $adminId, trim($reason));
    }

    public function unblockIP(int $adminId, int $entryId): void
    {
        if ($entryId <= 0) {
            throw new \InvalidArgumentException('Invalid entry ID.');
        }

        $this->repository->unblockIP($entryId, $adminId);
    }

    // -----------------------------------------------------------------------
    // Enhanced User Actions
    // -----------------------------------------------------------------------

    public function banUser(int $adminId, int $userId, string $reason): void
    {
        $user = $this->repository->getUserBasic($userId);
        if ($user === null) {
            throw new \InvalidArgumentException('User not found.');
        }

        if ($reason === '') {
            throw new \InvalidArgumentException('Ban reason is required.');
        }

        $this->repository->banUser($userId, $reason);
        $this->repository->logAdminAction($adminId, 'ban_user', 'users', (string)$userId, null, ['reason' => $reason], RequestContext::ipAddress());
    }

    public function unbanUser(int $adminId, int $userId): void
    {
        $user = $this->repository->getUserBasic($userId);
        if ($user === null) {
            throw new \InvalidArgumentException('User not found.');
        }

        $this->repository->unbanUser($userId);
        $this->repository->logAdminAction($adminId, 'unban_user', 'users', (string)$userId, null, [], RequestContext::ipAddress());
    }

    public function resetUserTwoFactor(int $adminId, int $userId): void
    {
        $user = $this->repository->getUserBasic($userId);
        if ($user === null) {
            throw new \InvalidArgumentException('User not found.');
        }

        $this->repository->resetUserTwoFactor($userId);
        $this->repository->logAdminAction($adminId, 'reset_2fa', 'users', (string)$userId, null, [], RequestContext::ipAddress());
    }

    public function revokeUserSessions(int $adminId, int $userId): void
    {
        $user = $this->repository->getUserBasic($userId);
        if ($user === null) {
            throw new \InvalidArgumentException('User not found.');
        }

        $this->repository->revokeAllUserSessions($userId);
        $this->repository->logAdminAction($adminId, 'revoke_user_sessions', 'users', (string)$userId, null, [], RequestContext::ipAddress());
    }
}
