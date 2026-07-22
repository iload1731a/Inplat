<?php
declare(strict_types=1);
namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\WithdrawalRepository;
use App\Repositories\WalletBalanceRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Withdrawal Service — complete business logic for the withdrawal management
 * system: fee calculation, daily-limit enforcement, KYC guard, notifications,
 * crypto & fiat flows, approval workflow, bulk operations, reports.
 */
final class WithdrawalService
{
    public function __construct(
        private readonly WithdrawalRepository    $repo        = new WithdrawalRepository(),
        private readonly WalletBalanceRepository $balanceRepo = new WalletBalanceRepository(),
        private readonly AdminManagementRepository $mgmtRepo  = new AdminManagementRepository(),
    ) {}

    // -------------------------------------------------------------------------
    // Fee calculation
    // -------------------------------------------------------------------------

    /**
     * Calculate the fee for a withdrawal amount given a currency config row.
     * Returns ['fee' => string, 'net' => string, 'gross' => string].
     */
    public function calculateFee(array $currency, string $amount): array
    {
        $feeFixed   = (string)($currency['withdrawal_fee_fixed']   ?? '0');
        $feePct     = (string)($currency['withdrawal_fee_percent'] ?? '0');
        $percentFee = bcmul($amount, bcdiv($feePct, '100', 18), 18);
        $totalFee   = bcadd($feeFixed, $percentFee, 18);
        $net        = bcsub($amount, $totalFee, 18);
        if (bccomp($net, '0', 18) <= 0) {
            throw new InvalidArgumentException(
                "Amount {$amount} is too small to cover the withdrawal fee of {$totalFee}"
            );
        }
        return [
            'fee'   => rtrim(rtrim($totalFee, '0'), '.') ?: '0',
            'net'   => rtrim(rtrim($net,      '0'), '.') ?: '0',
            'gross' => rtrim(rtrim($amount,   '0'), '.') ?: '0',
        ];
    }

    // -------------------------------------------------------------------------
    // User-facing: submit withdrawal
    // -------------------------------------------------------------------------

    public function submitCrypto(int $userId, array $input): int
    {
        $currencyId = (int)($input['currency_id'] ?? 0);
        $currency   = $this->guardCurrency($currencyId);

        $amount  = $this->parseAmount($input['amount'] ?? '');
        $address = trim((string)($input['destination_address'] ?? ''));
        if ($address === '') {
            throw new InvalidArgumentException('Destination address is required');
        }
        if (strlen($address) < 10 || strlen($address) > 191) {
            throw new InvalidArgumentException('Invalid destination address format');
        }

        $fees = $this->calculateFee($currency, $amount);

        $this->guardMinimum($currency, $amount);
        $wallet = $this->guardWallet($userId, $currencyId, $amount);
        $this->guardDailyLimit($userId, $currencyId, $currency, $amount);
        $manualRequired = $this->requiresManualReview($currency, $amount);

        // Atomically debit the user's wallet
        $this->balanceRepo->debit(
            (int)$wallet['id'],
            $amount,
            'withdrawal',
            0,
            "Withdrawal request – {$amount} {$currency['code']}",
            null
        );

        $id = $this->repo->create([
            'user_id'              => $userId,
            'wallet_id'            => (int)$wallet['id'],
            'currency_id'          => $currencyId,
            'amount'               => $fees['net'],
            'fee'                  => $fees['fee'],
            'destination_address'  => $address,
            'destination_tag'      => trim((string)($input['destination_tag'] ?? '')) ?: null,
            'requires_manual_review' => $manualRequired,
            'status'               => 'pending',
        ]);

        $this->repo->createNotification(
            $userId,
            'withdrawal',
            'Withdrawal Request Submitted',
            "Your withdrawal of {$amount} {$currency['code']} has been submitted and is pending review.",
            '/user/wallet/withdraw'
        );

        return $id;
    }

    public function submitFiat(int $userId, array $input): int
    {
        $currencyId = (int)($input['currency_id'] ?? 0);
        $currency   = $this->guardCurrency($currencyId);

        if ($currency['type'] !== 'fiat') {
            throw new InvalidArgumentException('Selected currency is not a fiat currency');
        }

        $amount = $this->parseAmount($input['amount'] ?? '');
        $fees   = $this->calculateFee($currency, $amount);

        $this->guardMinimum($currency, $amount);
        $wallet = $this->guardWallet($userId, $currencyId, $amount);
        $this->guardDailyLimit($userId, $currencyId, $currency, $amount);

        // Build bank details as destination_address (bank name | account | IBAN | SWIFT)
        $bankName    = trim((string)($input['bank_name']       ?? ''));
        $accountNo   = trim((string)($input['account_number']  ?? ''));
        $iban        = trim((string)($input['iban']            ?? ''));
        $swift       = trim((string)($input['swift']           ?? ''));
        $accountName = trim((string)($input['account_name']    ?? ''));

        if ($bankName === '' || ($accountNo === '' && $iban === '')) {
            throw new InvalidArgumentException('Bank name and account number or IBAN are required for fiat withdrawal');
        }

        $destAddress = json_encode([
            'bank_name'      => $bankName,
            'account_name'   => $accountName,
            'account_number' => $accountNo,
            'iban'           => $iban,
            'swift_bic'      => $swift,
            'currency'       => $currency['code'],
        ]);

        // Fiat withdrawals always require manual review
        $this->balanceRepo->debit(
            (int)$wallet['id'],
            $amount,
            'withdrawal',
            0,
            "Fiat withdrawal request – {$amount} {$currency['code']}",
            null
        );

        $id = $this->repo->create([
            'user_id'                => $userId,
            'wallet_id'              => (int)$wallet['id'],
            'currency_id'            => $currencyId,
            'amount'                 => $fees['net'],
            'fee'                    => $fees['fee'],
            'destination_address'    => $destAddress,
            'destination_tag'        => null,
            'requires_manual_review' => 1,
            'status'                 => 'pending',
        ]);

        $this->repo->createNotification(
            $userId,
            'withdrawal',
            'Fiat Withdrawal Request Submitted',
            "Your fiat withdrawal of {$amount} {$currency['code']} has been submitted and is pending bank processing.",
            '/user/wallet/withdraw'
        );

        return $id;
    }

    public function cancelWithdrawal(int $userId, int $withdrawalId): void
    {
        $withdrawal = $this->repo->findById($withdrawalId, $userId);
        if ($withdrawal === null) {
            throw new InvalidArgumentException('Withdrawal not found');
        }
        if ($withdrawal['status'] !== 'pending') {
            throw new InvalidArgumentException('Only pending withdrawals can be cancelled');
        }

        if (!$this->repo->cancelByUser($withdrawalId, $userId)) {
            throw new RuntimeException('Could not cancel withdrawal. It may have been processed already.');
        }

        // Refund the balance
        $this->balanceRepo->credit(
            (int)$withdrawal['wallet_id'],
            bcadd((string)$withdrawal['amount'], (string)$withdrawal['fee'], 18),
            'refund',
            $withdrawalId,
            "Refund for cancelled withdrawal #{$withdrawalId}",
            null
        );

        $this->repo->createNotification(
            $userId,
            'withdrawal',
            'Withdrawal Cancelled',
            "Your withdrawal #{$withdrawalId} has been cancelled and the amount refunded to your wallet.",
            '/user/wallet/withdraw'
        );
    }

    // -------------------------------------------------------------------------
    // User dashboard data
    // -------------------------------------------------------------------------

    public function userWithdrawalCenter(int $userId, array $filters = []): array
    {
        return [
            'currencies'     => $this->repo->withdrawalCurrencies(),
            'withdrawals'    => $this->repo->userWithdrawals($userId, $filters),
            'stats'          => $this->repo->userWithdrawalStats($userId),
            'monthly_totals' => $this->repo->userMonthlyTotals($userId),
            'filters'        => $filters,
        ];
    }

    public function userDailyLimitInfo(int $userId, int $currencyId): array
    {
        $currency = $this->repo->currencyById($currencyId);
        if ($currency === null) {
            return ['error' => 'Currency not found'];
        }
        $usedToday   = $this->repo->dailyWithdrawalTotal($userId, $currencyId, date('Y-m-d'));
        $maxDaily    = (string)($currency['max_withdrawal_daily'] ?? '0');
        $hasLimit    = bccomp($maxDaily, '0', 18) > 0;
        $remaining   = $hasLimit ? bcsub($maxDaily, $usedToday, 18) : null;
        if ($hasLimit && bccomp($remaining, '0', 18) < 0) {
            $remaining = '0';
        }
        return [
            'currency_code' => $currency['code'],
            'fee_fixed'     => $currency['withdrawal_fee_fixed'],
            'fee_percent'   => $currency['withdrawal_fee_percent'],
            'min_amount'    => $currency['min_withdrawal'],
            'max_daily'     => $maxDaily,
            'used_today'    => $usedToday,
            'remaining'     => $remaining,
        ];
    }

    // -------------------------------------------------------------------------
    // Admin: list, detail, review, bulk, reports, gateways
    // -------------------------------------------------------------------------

    public function adminWithdrawalList(array $filters): array
    {
        return [
            'withdrawals' => $this->repo->adminList($filters),
            'stats'       => $this->repo->adminStats(),
            'filters'     => $filters,
        ];
    }

    public function adminWithdrawalDetail(int $withdrawalId): array
    {
        $withdrawal = $this->repo->findById($withdrawalId);
        if ($withdrawal === null) {
            throw new InvalidArgumentException('Withdrawal not found');
        }
        // Try to decode fiat bank details
        $bankDetails = null;
        if (($withdrawal['currency_type'] ?? '') === 'fiat' && !empty($withdrawal['destination_address'])) {
            $decoded = json_decode((string)$withdrawal['destination_address'], true);
            if (is_array($decoded)) {
                $bankDetails = $decoded;
            }
        }
        return ['withdrawal' => $withdrawal, 'bank_details' => $bankDetails];
    }

    public function reviewWithdrawal(
        int     $adminId,
        int     $withdrawalId,
        string  $status,
        ?string $txHash,
        ?string $rejectionReason
    ): void {
        $allowed = ['pending', 'approved', 'processing', 'completed', 'rejected', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid withdrawal status');
        }

        $withdrawal = $this->repo->findById($withdrawalId);
        if ($withdrawal === null) {
            throw new InvalidArgumentException('Withdrawal not found');
        }
        if (in_array($withdrawal['status'], ['completed', 'rejected', 'cancelled'], true)) {
            throw new InvalidArgumentException('Withdrawal is already in a terminal state');
        }

        // Refund on rejection of pending or approved withdrawal
        if ($status === 'rejected' && in_array($withdrawal['status'], ['pending', 'approved'], true)) {
            $refundAmount = bcadd((string)$withdrawal['amount'], (string)$withdrawal['fee'], 18);
            $this->balanceRepo->credit(
                (int)$withdrawal['wallet_id'],
                $refundAmount,
                'refund',
                $withdrawalId,
                "Refund for rejected withdrawal #{$withdrawalId}",
                $adminId
            );
        }

        $this->repo->updateStatus($withdrawalId, $status, $adminId, $txHash ?: null, $rejectionReason ?: null);

        $this->mgmtRepo->logAdminAction(
            $adminId,
            'review_withdrawal',
            'withdrawals',
            (string)$withdrawalId,
            null,
            ['status' => $status, 'user' => $withdrawal['username']],
            RequestContext::ipAddress()
        );

        // Notify the user
        $title   = match ($status) {
            'approved'   => 'Withdrawal Approved',
            'processing' => 'Withdrawal Processing',
            'completed'  => 'Withdrawal Completed',
            'rejected'   => 'Withdrawal Rejected – Refund Issued',
            'cancelled'  => 'Withdrawal Cancelled',
            default      => 'Withdrawal Status Updated',
        };
        $message = match ($status) {
            'approved'   => "Your withdrawal #{$withdrawalId} has been approved and will be processed shortly.",
            'processing' => "Your withdrawal #{$withdrawalId} is now being processed on the network.",
            'completed'  => "Your withdrawal #{$withdrawalId} has been completed." . ($txHash ? " TxHash: {$txHash}" : ''),
            'rejected'   => "Your withdrawal #{$withdrawalId} was rejected" . ($rejectionReason ? ": {$rejectionReason}" : '.') . ' Your balance has been refunded.',
            'cancelled'  => "Your withdrawal #{$withdrawalId} has been cancelled.",
            default      => "Your withdrawal #{$withdrawalId} status has been updated to {$status}.",
        };
        $this->repo->createNotification(
            (int)$withdrawal['user_id'],
            'withdrawal',
            $title,
            $message,
            '/user/wallet/withdraw'
        );
    }

    public function bulkApprove(int $adminId, array $ids): array
    {
        if (empty($ids)) {
            throw new InvalidArgumentException('No withdrawal IDs provided');
        }
        $updated = $this->repo->bulkApprove($ids, $adminId);

        foreach ($updated as $wdId) {
            $this->mgmtRepo->logAdminAction(
                $adminId,
                'bulk_approve_withdrawal',
                'withdrawals',
                (string)$wdId,
                null,
                [],
                RequestContext::ipAddress()
            );
        }
        return $updated;
    }

    public function bulkReject(int $adminId, array $ids, string $reason): array
    {
        if (empty($ids)) {
            throw new InvalidArgumentException('No withdrawal IDs provided');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Rejection reason is required for bulk reject');
        }

        // Get pending rows first so we can refund balances
        $pending = $this->repo->pendingWithdrawalsByIds($ids);

        $updated = $this->repo->bulkReject($ids, $adminId, $reason);

        // Refund balances for actually rejected withdrawals
        foreach ($pending as $row) {
            if (in_array((int)$row['id'], $updated, true)) {
                $refundAmount = bcadd((string)$row['amount'], (string)$row['fee'], 18);
                $this->balanceRepo->credit(
                    (int)$row['wallet_id'],
                    $refundAmount,
                    'refund',
                    (int)$row['id'],
                    "Refund for bulk-rejected withdrawal #{$row['id']}",
                    $adminId
                );
                $this->repo->createNotification(
                    (int)$row['user_id'],
                    'withdrawal',
                    'Withdrawal Rejected – Refund Issued',
                    "Your withdrawal #{$row['id']} was rejected by the admin team: {$reason}. Your balance has been refunded.",
                    '/user/wallet/withdraw'
                );
                $this->mgmtRepo->logAdminAction(
                    $adminId,
                    'bulk_reject_withdrawal',
                    'withdrawals',
                    (string)$row['id'],
                    null,
                    ['reason' => $reason],
                    RequestContext::ipAddress()
                );
            }
        }
        return $updated;
    }

    public function adminReports(): array
    {
        return [
            'stats'               => $this->repo->adminStats(),
            'daily_volume'        => $this->repo->dailyVolume(30),
            'monthly_report'      => $this->repo->monthlyReport(12),
            'currency_breakdown'  => $this->repo->currencyBreakdown(),
        ];
    }

    public function exportCsv(array $filters): string
    {
        $rows = $this->repo->exportData($filters);
        $lines   = [];
        $lines[] = '"ID","User","Email","Currency","Type","Amount","Fee","Net","Destination","TxHash","Status","Manual Review","Requested At","Processed At"';
        foreach ($rows as $row) {
            $lines[] = implode(',', [
                (int)$row['id'],
                '"' . addslashes((string)($row['username']  ?? '')) . '"',
                '"' . addslashes((string)($row['email']     ?? '')) . '"',
                '"' . addslashes((string)($row['currency_code'] ?? '')) . '"',
                '"' . addslashes((string)($row['currency_type'] ?? '')) . '"',
                '"' . number_format((float)($row['amount']  ?? 0), 8, '.', '') . '"',
                '"' . number_format((float)($row['fee']     ?? 0), 8, '.', '') . '"',
                '"' . number_format((float)bcsub((string)($row['amount'] ?? '0'), (string)($row['fee'] ?? '0'), 8), 8, '.', '') . '"',
                '"' . addslashes((string)($row['destination_address'] ?? '')) . '"',
                '"' . addslashes((string)($row['tx_hash'] ?? '')) . '"',
                '"' . addslashes((string)($row['status']  ?? '')) . '"',
                (int)($row['requires_manual_review'] ?? 0),
                '"' . addslashes((string)($row['requested_at'] ?? '')) . '"',
                '"' . addslashes((string)($row['processed_at'] ?? '')) . '"',
            ]);
        }
        return implode("\n", $lines);
    }

    // -------------------------------------------------------------------------
    // Payment gateway / currency settings
    // -------------------------------------------------------------------------

    public function gatewaySettings(): array
    {
        return [
            'currencies' => $this->repo->allWithdrawalCurrencySettings(),
        ];
    }

    public function updateGatewaySettings(int $adminId, int $currencyId, array $data): void
    {
        $feeFixed = trim((string)($data['withdrawal_fee_fixed'] ?? '0'));
        if (!is_numeric($feeFixed) || bccomp($feeFixed, '0', 18) < 0) {
            throw new InvalidArgumentException('Fixed fee must be a non-negative number');
        }
        $feePct = trim((string)($data['withdrawal_fee_percent'] ?? '0'));
        if (!is_numeric($feePct) || bccomp($feePct, '0', 4) < 0 || bccomp($feePct, '100', 4) > 0) {
            throw new InvalidArgumentException('Fee percentage must be between 0 and 100');
        }
        $minWd = trim((string)($data['min_withdrawal'] ?? '0'));
        if (!is_numeric($minWd) || bccomp($minWd, '0', 18) < 0) {
            throw new InvalidArgumentException('Minimum withdrawal must be a non-negative number');
        }

        $this->repo->updateWithdrawalSettings($currencyId, [
            'is_withdrawal_enabled'  => (int)(bool)($data['is_withdrawal_enabled'] ?? 0),
            'withdrawal_fee_fixed'   => $feeFixed,
            'withdrawal_fee_percent' => $feePct,
            'min_withdrawal'         => $minWd,
            'max_withdrawal_daily'   => !empty($data['max_withdrawal_daily']) ? $data['max_withdrawal_daily'] : null,
        ]);

        $this->mgmtRepo->logAdminAction(
            $adminId,
            'update_withdrawal_gateway',
            'currencies',
            (string)$currencyId,
            null,
            ['fee_fixed' => $feeFixed, 'fee_pct' => $feePct],
            RequestContext::ipAddress()
        );
    }

    // -------------------------------------------------------------------------
    // Internal guards
    // -------------------------------------------------------------------------

    private function guardCurrency(int $currencyId): array
    {
        if ($currencyId <= 0) {
            throw new InvalidArgumentException('Please select a currency');
        }
        $currency = $this->repo->currencyById($currencyId);
        if ($currency === null) {
            throw new InvalidArgumentException('Currency not found');
        }
        if (!(bool)$currency['is_withdrawal_enabled']) {
            throw new RuntimeException("Withdrawals for {$currency['code']} are currently disabled");
        }
        return $currency;
    }

    private function parseAmount(string $raw): string
    {
        $amount = trim($raw);
        if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero');
        }
        return $amount;
    }

    private function guardMinimum(array $currency, string $amount): void
    {
        $minWd = (string)($currency['min_withdrawal'] ?? '0');
        if (bccomp($minWd, '0', 18) > 0 && bccomp($amount, $minWd, 18) < 0) {
            throw new InvalidArgumentException(
                "Minimum withdrawal amount is {$minWd} {$currency['code']}"
            );
        }
    }

    private function guardWallet(int $userId, int $currencyId, string $amount): array
    {
        $wallet = $this->balanceRepo->findOrCreate($userId, $currencyId, 'spot');
        if ((int)$wallet['is_frozen'] === 1) {
            throw new RuntimeException('Your wallet is frozen. Please contact support.');
        }
        if (bccomp((string)$wallet['available_balance'], $amount, 18) < 0) {
            throw new InvalidArgumentException('Insufficient available balance');
        }
        return $wallet;
    }

    private function guardDailyLimit(int $userId, int $currencyId, array $currency, string $amount): void
    {
        $maxDaily = (string)($currency['max_withdrawal_daily'] ?? '0');
        if (bccomp($maxDaily, '0', 18) <= 0) {
            return; // No daily limit
        }
        $usedToday = $this->repo->dailyWithdrawalTotal($userId, $currencyId, date('Y-m-d'));
        $newTotal  = bcadd($usedToday, $amount, 18);
        if (bccomp($newTotal, $maxDaily, 18) > 0) {
            $remaining = bcsub($maxDaily, $usedToday, 18);
            if (bccomp($remaining, '0', 18) < 0) {
                $remaining = '0';
            }
            throw new InvalidArgumentException(
                "Daily withdrawal limit of {$maxDaily} {$currency['code']} exceeded. " .
                "Remaining today: {$remaining} {$currency['code']}"
            );
        }
    }

    private function requiresManualReview(array $currency, string $amount): int
    {
        // Fiat always requires manual review; crypto above a threshold does too
        if ($currency['type'] === 'fiat') {
            return 1;
        }
        // Heuristic: > 10,000 units requires manual review
        return bccomp($amount, '10000', 18) >= 0 ? 1 : 0;
    }
}
