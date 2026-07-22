<?php
declare(strict_types=1);
namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\DepositRepository;
use App\Repositories\WalletBalanceRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Deposit Service — complete business logic for the deposit management
 * system: crypto & fiat submission flows, admin review/credit/flag/reject,
 * bulk operations, CSV export, and notifications.
 */
final class DepositService
{
    public function __construct(
        private readonly DepositRepository       $repo        = new DepositRepository(),
        private readonly WalletBalanceRepository $balanceRepo = new WalletBalanceRepository(),
        private readonly AdminManagementRepository $mgmtRepo  = new AdminManagementRepository(),
    ) {}

    // -------------------------------------------------------------------------
    // User-facing: submit crypto deposit
    // -------------------------------------------------------------------------

    public function submitCrypto(int $userId, array $input): int
    {
        $currencyId = (int)($input['currency_id'] ?? 0);
        $currency   = $this->guardCurrency($currencyId);

        $amount = trim((string)($input['amount'] ?? ''));
        if ($amount === '' || bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $txHash      = trim((string)($input['tx_hash']      ?? ''));
        $fromAddress = trim((string)($input['from_address'] ?? ''));

        // Ensure wallet exists (spot)
        $wallet = $this->balanceRepo->findOrCreate($userId, $currencyId, 'spot');

        $depositId = $this->repo->create([
            'user_id'      => $userId,
            'wallet_id'    => $wallet['id'],
            'currency_id'  => $currencyId,
            'amount'       => $amount,
            'tx_hash'      => $txHash      !== '' ? $txHash      : null,
            'from_address' => $fromAddress !== '' ? $fromAddress : null,
            'status'       => 'pending',
        ]);

        // Notify user
        $this->repo->createNotification(
            $userId,
            'deposit',
            'Deposit Request Received',
            "Your deposit of {$amount} {$currency['code']} has been received and is pending review.",
            "/user/deposit"
        );

        return $depositId;
    }

    // -------------------------------------------------------------------------
    // User-facing: submit fiat deposit
    // -------------------------------------------------------------------------

    public function submitFiat(int $userId, array $input): int
    {
        $currencyId = (int)($input['currency_id'] ?? 0);
        $currency   = $this->guardCurrency($currencyId);

        if ($currency['type'] !== 'fiat') {
            throw new InvalidArgumentException('Selected currency is not a fiat currency.');
        }

        $amount = trim((string)($input['amount'] ?? ''));
        if ($amount === '' || bccomp($amount, '0', 18) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $paymentRef  = trim((string)($input['payment_ref']  ?? ''));
        $bankName    = trim((string)($input['bank_name']    ?? ''));
        $accountName = trim((string)($input['account_name'] ?? ''));

        if ($paymentRef === '') {
            throw new InvalidArgumentException('Payment reference is required for fiat deposits.');
        }

        // Ensure wallet exists
        $wallet = $this->balanceRepo->findOrCreate($userId, $currencyId, 'spot');

        // Store fiat reference in tx_hash field, bank info in from_address (JSON)
        $fiatMeta = json_encode([
            'bank_name'    => $bankName,
            'account_name' => $accountName,
            'payment_ref'  => $paymentRef,
        ]);

        $depositId = $this->repo->create([
            'user_id'      => $userId,
            'wallet_id'    => $wallet['id'],
            'currency_id'  => $currencyId,
            'amount'       => $amount,
            'tx_hash'      => $paymentRef,
            'from_address' => $fiatMeta,
            'status'       => 'pending',
        ]);

        $this->repo->createNotification(
            $userId,
            'deposit',
            'Fiat Deposit Submitted',
            "Your fiat deposit of {$amount} {$currency['code']} has been submitted and is pending verification.",
            "/user/deposit"
        );

        return $depositId;
    }

    // -------------------------------------------------------------------------
    // User-facing: cancel a pending deposit
    // -------------------------------------------------------------------------

    public function cancelDeposit(int $userId, int $depositId): void
    {
        $deposit = $this->repo->findById($depositId, $userId);
        if ($deposit === null) {
            throw new InvalidArgumentException('Deposit not found.');
        }
        if ($deposit['status'] !== 'pending') {
            throw new InvalidArgumentException('Only pending deposits can be cancelled.');
        }
        $this->repo->updateStatus($depositId, 'failed', null, 'Cancelled by user');
    }

    // -------------------------------------------------------------------------
    // User-facing: list + stats
    // -------------------------------------------------------------------------

    public function userDepositIndex(int $userId, array $filters = []): array
    {
        return [
            'currencies' => $this->repo->depositCurrencies(),
            'deposits'   => $this->repo->userDeposits($userId, $filters),
            'stats'      => $this->repo->userStats($userId),
            'filters'    => $filters,
        ];
    }

    public function userDepositReport(int $userId): array
    {
        return [
            'deposits' => $this->repo->userDeposits($userId, [], 500),
            'stats'    => $this->repo->userStats($userId),
            'monthly'  => $this->repo->userMonthlyReport($userId),
        ];
    }

    // -------------------------------------------------------------------------
    // Admin-facing: deposit list + stats
    // -------------------------------------------------------------------------

    public function adminDepositList(array $filters): array
    {
        return [
            'deposits' => $this->repo->adminList($filters),
            'stats'    => $this->repo->adminStats(),
            'filters'  => $filters,
        ];
    }

    // -------------------------------------------------------------------------
    // Admin-facing: deposit detail
    // -------------------------------------------------------------------------

    public function adminDepositDetail(int $depositId): array
    {
        $deposit = $this->repo->findById($depositId);
        if ($deposit === null) {
            throw new InvalidArgumentException("Deposit #{$depositId} not found.");
        }
        return ['deposit' => $deposit];
    }

    // -------------------------------------------------------------------------
    // Admin-facing: review (credit / confirm / fail / flag)
    // -------------------------------------------------------------------------

    public function reviewDeposit(int $adminId, int $depositId, string $status, ?string $flagReason): void
    {
        $allowed = ['confirmed', 'credited', 'failed', 'flagged'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Invalid deposit status: {$status}");
        }

        $deposit = $this->repo->findById($depositId);
        if ($deposit === null) {
            throw new InvalidArgumentException("Deposit #{$depositId} not found.");
        }
        if ($deposit['status'] === 'credited') {
            throw new InvalidArgumentException('Deposit is already credited.');
        }

        // If crediting, use atomic credit + status update in one transaction
        if ($status === 'credited') {
            $this->repo->creditAndMarkDeposit(
                $depositId,
                (int)$deposit['wallet_id'],
                (string)$deposit['amount'],
                $adminId
            );
        } else {
            $this->repo->updateStatus($depositId, $status, $adminId, $flagReason ?: null);
        }

        // Notify the user
        $notifTitle   = match($status) {
            'credited'  => 'Deposit Credited',
            'confirmed' => 'Deposit Confirmed',
            'failed'    => 'Deposit Failed',
            'flagged'   => 'Deposit Flagged',
            default     => 'Deposit Updated',
        };
        $notifMsg = match($status) {
            'credited'  => "Your deposit of {$deposit['amount']} {$deposit['currency_code']} has been credited to your wallet.",
            'confirmed' => "Your deposit of {$deposit['amount']} {$deposit['currency_code']} has been confirmed and is being processed.",
            'failed'    => "Your deposit of {$deposit['amount']} {$deposit['currency_code']} could not be processed.",
            'flagged'   => "Your deposit has been flagged for review. Reason: " . ($flagReason ?? 'See support.'),
            default     => "Your deposit status has been updated to {$status}.",
        };

        $this->repo->createNotification(
            (int)$deposit['user_id'],
            'deposit',
            $notifTitle,
            $notifMsg,
            '/user/deposit'
        );

        // Audit log
        $this->mgmtRepo->logAdminAction(
            $adminId,
            'review_deposit',
            'deposits',
            (string)$depositId,
            ['status' => $deposit['status']],
            ['status' => $status, 'flag_reason' => $flagReason, 'user' => $deposit['username']],
            RequestContext::ipAddress()
        );
    }

    // -------------------------------------------------------------------------
    // Admin-facing: bulk credit
    // -------------------------------------------------------------------------

    public function bulkCredit(int $adminId, array $depositIds): array
    {
        $ids = array_filter(array_map('intval', $depositIds));
        if (empty($ids)) {
            throw new InvalidArgumentException('No deposit IDs provided.');
        }

        $credited = 0;
        $errors   = [];

        foreach ($ids as $depositId) {
            try {
                $this->reviewDeposit($adminId, $depositId, 'credited', null);
                $credited++;
            } catch (\Throwable $e) {
                $errors[] = "Deposit #{$depositId}: " . $e->getMessage();
            }
        }

        return ['credited' => $credited, 'errors' => $errors];
    }

    // -------------------------------------------------------------------------
    // Admin-facing: bulk flag
    // -------------------------------------------------------------------------

    public function bulkFlag(int $adminId, array $depositIds, string $reason): int
    {
        $ids = array_filter(array_map('intval', $depositIds));
        if (empty($ids)) {
            throw new InvalidArgumentException('No deposit IDs provided.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Flag reason is required.');
        }

        $flagged = 0;
        foreach ($ids as $depositId) {
            try {
                $deposit = $this->repo->findById($depositId);
                if ($deposit !== null && $deposit['status'] !== 'credited') {
                    $this->repo->updateStatus($depositId, 'flagged', $adminId, $reason);
                    $flagged++;
                }
            } catch (\Throwable) {
                // Skip individual errors in bulk
            }
        }

        $this->mgmtRepo->logAdminAction(
            $adminId,
            'bulk_flag_deposits',
            'deposits',
            implode(',', $ids),
            null,
            ['flagged' => $flagged, 'reason' => $reason],
            RequestContext::ipAddress()
        );

        return $flagged;
    }

    // -------------------------------------------------------------------------
    // Admin-facing: reports
    // -------------------------------------------------------------------------

    public function adminReports(): array
    {
        return [
            'daily_volume'       => $this->repo->dailyVolumeReport(30),
            'currency_breakdown' => $this->repo->currencyBreakdown(),
            'monthly_report'     => $this->repo->monthlyReport(12),
            'top_depositors'     => $this->repo->topDepositors(10),
            'stats'              => $this->repo->adminStats(),
        ];
    }

    // -------------------------------------------------------------------------
    // Admin-facing: CSV export
    // -------------------------------------------------------------------------

    public function exportCsv(array $filters): void
    {
        $deposits = $this->repo->exportData($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="deposits_export_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'ID', 'Username', 'Email', 'Currency', 'Type',
            'Amount', 'TX Hash', 'From Address', 'Confirmations',
            'Status', 'Flagged Reason', 'Reviewed By', 'Created At', 'Credited At',
        ]);
        foreach ($deposits as $row) {
            fputcsv($out, [
                $row['id'],
                $row['username'],
                $row['email'],
                $row['currency_code'],
                $row['currency_type'],
                $row['amount'],
                $row['tx_hash']      ?? '',
                $row['from_address'] ?? '',
                $row['confirmations'],
                $row['status'],
                $row['flagged_reason'] ?? '',
                $row['reviewed_by']    ?? '',
                $row['created_at'],
                $row['credited_at']    ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------
    // Admin-facing: gateway settings
    // -------------------------------------------------------------------------

    public function adminGateways(): array
    {
        return [
            'currencies' => $this->repo->allDepositCurrencySettings(),
        ];
    }

    public function updateGateway(int $adminId, int $currencyId, array $data): void
    {
        $this->repo->updateDepositCurrencySetting($currencyId, $data);
        $this->mgmtRepo->logAdminAction(
            $adminId,
            'update_deposit_gateway',
            'currencies',
            (string)$currencyId,
            null,
            $data,
            RequestContext::ipAddress()
        );
    }

    // -------------------------------------------------------------------------
    // AJAX helpers
    // -------------------------------------------------------------------------

    /** Return wallet addresses for a user + currency (for the deposit form). */
    public function addressInfo(int $userId, int $currencyId): array
    {
        $currency  = $this->repo->currencyById($currencyId);
        if ($currency === null) {
            throw new InvalidArgumentException('Currency not found.');
        }
        $addresses = $this->repo->walletAddresses($userId, $currencyId);
        return [
            'currency'  => $currency,
            'addresses' => $addresses,
        ];
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    private function guardCurrency(int $currencyId): array
    {
        if ($currencyId <= 0) {
            throw new InvalidArgumentException('Invalid currency selected.');
        }
        $currency = $this->repo->currencyById($currencyId);
        if ($currency === null) {
            throw new InvalidArgumentException('Currency not found.');
        }
        if (!(bool)($currency['is_deposit_enabled'] ?? false)) {
            throw new RuntimeException("Deposits are currently disabled for {$currency['code']}.");
        }
        return $currency;
    }
}
