<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminWalletsRepository;
use App\Repositories\AdminManagementRepository;
use App\Repositories\WalletBalanceRepository;
use InvalidArgumentException;

final class AdminWalletsService
{
    public function __construct(
        private readonly AdminWalletsRepository  $walletsRepo  = new AdminWalletsRepository(),
        private readonly AdminManagementRepository $mgmtRepo   = new AdminManagementRepository(),
        private readonly WalletBalanceRepository $balanceRepo  = new WalletBalanceRepository(),
    ) {}

    // -------------------------------------------------------------------------
    // Wallets index
    // -------------------------------------------------------------------------

    public function walletsIndex(array $filters): array
    {
        return [
            'wallets'     => $this->walletsRepo->listWallets($filters),
            'walletStats' => $this->walletsRepo->getWalletStats(),
            'topWallets'  => $this->walletsRepo->getTopWallets(),
            'filters'     => $filters,
        ];
    }

    public function walletLedger(int $walletId): array
    {
        $wallet = $this->walletsRepo->findWalletById($walletId);
        if ($wallet === null) {
            throw new InvalidArgumentException('No wallet exists with the provided ID.');
        }
        return [
            'wallet' => $wallet,
            'ledger' => $this->walletsRepo->getLedger($walletId, 100),
        ];
    }

    public function walletLookup(int $walletId): array
    {
        $wallet = $this->walletsRepo->findWalletById($walletId);
        if ($wallet === null) {
            throw new InvalidArgumentException('No wallet exists with the provided ID.');
        }

        return [
            'id' => (int)($wallet['id'] ?? 0),
            'username' => (string)($wallet['username'] ?? ''),
            'email' => (string)($wallet['email'] ?? ''),
            'currency_code' => (string)($wallet['currency_code'] ?? ''),
            'wallet_type' => (string)($wallet['wallet_type'] ?? ''),
            'available_balance' => (string)($wallet['available_balance'] ?? '0'),
            'locked_balance' => (string)($wallet['locked_balance'] ?? '0'),
            'is_frozen' => (int)($wallet['is_frozen'] ?? 0),
        ];
    }

    public function freezeWallet(int $adminId, int $walletId, string $reason): void
    {
        $wallet = $this->walletsRepo->findWalletById($walletId);
        if ($wallet === null) {
            throw new InvalidArgumentException('No wallet exists with the provided ID.');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('Freeze reason is required.');
        }
        $this->walletsRepo->freezeWallet($walletId, $reason);
        $this->mgmtRepo->logAdminAction($adminId, 'freeze_wallet', 'wallets', (string)$walletId,
            null, ['reason' => $reason, 'user' => $wallet['username']], RequestContext::ipAddress());
    }

    public function unfreezeWallet(int $adminId, int $walletId): void
    {
        $wallet = $this->walletsRepo->findWalletById($walletId);
        if ($wallet === null) {
            throw new InvalidArgumentException('No wallet exists with the provided ID.');
        }
        $this->walletsRepo->unfreezeWallet($walletId);
        $this->mgmtRepo->logAdminAction($adminId, 'unfreeze_wallet', 'wallets', (string)$walletId,
            null, ['user' => $wallet['username']], RequestContext::ipAddress());
    }

    // -------------------------------------------------------------------------
    // Deposits management
    // -------------------------------------------------------------------------

    public function depositsIndex(array $filters): array
    {
        return [
            'deposits'      => $this->walletsRepo->listDeposits($filters),
            'depositStats'  => $this->walletsRepo->depositStats(),
            'filters'       => $filters,
        ];
    }

    public function reviewDeposit(int $adminId, int $depositId, string $status, ?string $flagReason): void
    {
        $allowedStatuses = ['pending', 'confirmed', 'credited', 'failed', 'flagged'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Invalid deposit status');
        }

        $deposit = $this->walletsRepo->findDepositById($depositId);
        if ($deposit === null) {
            throw new InvalidArgumentException('Deposit not found');
        }
        if ($deposit['status'] === 'credited') {
            throw new InvalidArgumentException('Deposit is already credited');
        }

        // If crediting, apply balance atomically
        if ($status === 'credited' && $deposit['status'] !== 'credited') {
            $this->balanceRepo->credit(
                (int)$deposit['wallet_id'],
                (string)$deposit['amount'],
                'deposit',
                $depositId,
                "Admin credit for deposit #{$depositId}",
                $adminId
            );
        }

        $this->walletsRepo->updateDepositStatus($depositId, $status, $adminId, $flagReason ?: null);
        $this->mgmtRepo->logAdminAction($adminId, 'review_deposit', 'deposits', (string)$depositId,
            null, ['status' => $status, 'user' => $deposit['username']], RequestContext::ipAddress());
    }

    // -------------------------------------------------------------------------
    // Withdrawals management
    // -------------------------------------------------------------------------

    public function withdrawalsIndex(array $filters): array
    {
        return [
            'withdrawals'     => $this->walletsRepo->listWithdrawals($filters),
            'withdrawalStats' => $this->walletsRepo->withdrawalStats(),
            'filters'         => $filters,
        ];
    }

    public function reviewWithdrawal(
        int     $adminId,
        int     $withdrawalId,
        string  $status,
        ?string $txHash,
        ?string $rejectionReason
    ): void {
        $allowedStatuses = ['pending', 'approved', 'processing', 'completed', 'rejected', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Invalid withdrawal status');
        }

        $withdrawal = $this->walletsRepo->findWithdrawalById($withdrawalId);
        if ($withdrawal === null) {
            throw new InvalidArgumentException('Withdrawal not found');
        }

        // If rejecting, refund the balance
        if ($status === 'rejected' && $withdrawal['status'] === 'pending') {
            $this->balanceRepo->credit(
                (int)$withdrawal['wallet_id'],
                (string)$withdrawal['amount'],
                'refund',
                $withdrawalId,
                "Refund for rejected withdrawal #{$withdrawalId}",
                $adminId
            );
        }

        $this->walletsRepo->updateWithdrawalStatus($withdrawalId, $status, $adminId, $txHash ?: null, $rejectionReason ?: null);
        $this->mgmtRepo->logAdminAction($adminId, 'review_withdrawal', 'withdrawals', (string)$withdrawalId,
            null, ['status' => $status, 'user' => $withdrawal['username']], RequestContext::ipAddress());
    }

    // -------------------------------------------------------------------------
    // Manual balance adjustment
    // -------------------------------------------------------------------------

    public function manualAdjustment(
        int    $adminId,
        int    $walletId,
        string $direction,
        string $amount,
        string $notes
    ): void {
        if (!in_array($direction, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException('Direction must be credit or debit');
        }
        if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number');
        }
        if (trim($notes) === '') {
            throw new InvalidArgumentException('Notes are required for manual adjustments');
        }

        $wallet = $this->walletsRepo->findWalletById($walletId);
        if ($wallet === null) {
            throw new InvalidArgumentException('Wallet not found');
        }

        if ($direction === 'credit') {
            $this->balanceRepo->credit($walletId, $amount, 'adjustment', 0, $notes, $adminId);
        } else {
            $this->balanceRepo->debit($walletId, $amount, 'adjustment', 0, $notes, $adminId);
        }

        $this->mgmtRepo->logAdminAction($adminId, "manual_{$direction}", 'wallets', (string)$walletId,
            null, ['amount' => $amount, 'notes' => $notes, 'user' => $wallet['username']], RequestContext::ipAddress());
    }

    public function adjustmentHistory(): array
    {
        return $this->walletsRepo->adjustmentHistory(200);
    }

    // -------------------------------------------------------------------------
    // Transaction monitoring rules
    // -------------------------------------------------------------------------

    public function monitoringRules(): array
    {
        return $this->walletsRepo->listMonitoringRules();
    }

    public function createMonitoringRule(int $adminId, array $input): int
    {
        $name = trim((string)($input['rule_name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Rule name is required');
        }

        $validTypes = ['deposit_velocity', 'withdrawal_velocity', 'volume_threshold',
                       'structuring_pattern', 'new_account_high_value', 'geo_risk', 'custom'];
        $type = trim((string)($input['rule_type'] ?? ''));
        if (!in_array($type, $validTypes, true)) {
            throw new InvalidArgumentException('Invalid rule type');
        }

        $validActions = ['flag_only', 'freeze_account', 'open_sar_case', 'notify_compliance'];
        $action = trim((string)($input['action'] ?? ''));
        if (!in_array($action, $validActions, true)) {
            throw new InvalidArgumentException('Invalid action');
        }

        $conditions = trim((string)($input['conditions'] ?? '{}'));
        if (json_decode($conditions) === null) {
            throw new InvalidArgumentException('Conditions must be valid JSON');
        }

        $id = $this->walletsRepo->createMonitoringRule([
            'rule_name'  => $name,
            'rule_type'  => $type,
            'conditions' => $conditions,
            'action'     => $action,
        ], $adminId);

        $this->mgmtRepo->logAdminAction($adminId, 'create_monitoring_rule', 'transaction_monitoring_rules',
            (string)$id, null, ['name' => $name, 'type' => $type], RequestContext::ipAddress());

        return $id;
    }

    public function toggleMonitoringRule(int $adminId, int $ruleId, bool $active): void
    {
        $rule = $this->walletsRepo->findMonitoringRule($ruleId);
        if ($rule === null) {
            throw new InvalidArgumentException('Rule not found');
        }
        $this->walletsRepo->toggleMonitoringRule($ruleId, $active ? 1 : 0);
        $this->mgmtRepo->logAdminAction($adminId, $active ? 'enable_rule' : 'disable_rule',
            'transaction_monitoring_rules', (string)$ruleId, null, [], RequestContext::ipAddress());
    }

    public function deleteMonitoringRule(int $adminId, int $ruleId): void
    {
        $rule = $this->walletsRepo->findMonitoringRule($ruleId);
        if ($rule === null) {
            throw new InvalidArgumentException('Rule not found');
        }
        $this->walletsRepo->deleteMonitoringRule($ruleId);
        $this->mgmtRepo->logAdminAction($adminId, 'delete_monitoring_rule', 'transaction_monitoring_rules',
            (string)$ruleId, null, ['name' => $rule['rule_name']], RequestContext::ipAddress());
    }
}
