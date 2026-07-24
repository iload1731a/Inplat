<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\AdminAffiliateRepository;
use InvalidArgumentException;
use Throwable;

/**
 * AdminAffiliateService
 *
 * Business logic for the admin-facing Affiliate Program management:
 * dashboard, affiliate management, commission management,
 * payout processing, tier configuration, program settings, reporting.
 */
final class AdminAffiliateService
{
    private readonly AdminAffiliateRepository $repo;

    public function __construct(?AdminAffiliateRepository $repo = null)
    {
        $this->repo = $repo ?? new AdminAffiliateRepository();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function dashboard(): array
    {
        try {
            return [
                'kpis'          => $this->repo->kpis(),
                'daily30'       => $this->repo->dailyCommissions(30),
                'byType'        => $this->repo->commissionByType(),
                'topAffiliates' => $this->repo->topAffiliates(10),
                'tiers'         => $this->repo->getTiers(),
            ];
        } catch (Throwable) {
            return ['kpis' => [], 'daily30' => [], 'byType' => [], 'topAffiliates' => [], 'tiers' => []];
        }
    }

    // =========================================================================
    // AFFILIATES
    // =========================================================================

    public function affiliatesList(array $f, int $page, int $perPage): array
    {
        try {
            $rows  = $this->repo->affiliatesList($f, $page, $perPage);
            $total = $this->repo->affiliatesCount($f);
            return [
                'rows'        => $rows,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
                'filters'     => $f,
            ];
        } catch (Throwable) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0, 'filters' => $f];
        }
    }

    public function affiliateDetail(int $userId): array
    {
        try {
            return $this->repo->affiliateDetail($userId);
        } catch (Throwable) {
            return [];
        }
    }

    // =========================================================================
    // COMMISSIONS
    // =========================================================================

    public function commissionsList(array $f, int $page, int $perPage): array
    {
        try {
            $rows  = $this->repo->commissionsList($f, $page, $perPage);
            $total = $this->repo->commissionsCount($f);
            return [
                'rows'        => $rows,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
                'filters'     => $f,
            ];
        } catch (Throwable) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0, 'filters' => $f];
        }
    }

    public function markCommissionsPaid(array $ids): int
    {
        $ids = array_filter(array_map('intval', $ids));
        if ($ids === []) {
            throw new InvalidArgumentException('No commissions selected.');
        }
        return $this->repo->markCommissionsPaid($ids);
    }

    public function exportCommissions(array $f): void
    {
        $rows = $this->repo->exportCommissions($f, 5000);

        $filename = 'commissions_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: no-cache, no-store');
        $out = fopen('php://output', 'w');
        if ($out !== false) {
            fputcsv($out, ['ID', 'Referrer', 'From User', 'Amount', 'Currency', 'Type', 'Level', 'Status', 'Paid At', 'Created At']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['id'], $r['referrer'] ?? '', $r['from_user'] ?? '',
                    $r['amount'], $r['currency_code'], $r['commission_type'],
                    $r['level'], $r['status'],
                    $r['paid_at'] ?? '', $r['created_at'],
                ]);
            }
            fclose($out);
        }
        exit;
    }

    // =========================================================================
    // PAYOUTS
    // =========================================================================

    public function payoutsList(array $f, int $page, int $perPage): array
    {
        try {
            $rows  = $this->repo->payoutsList($f, $page, $perPage);
            $total = $this->repo->payoutsCount($f);
            return [
                'rows'        => $rows,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
                'filters'     => $f,
            ];
        } catch (Throwable) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0, 'filters' => $f];
        }
    }

    public function processPayout(int $payoutId, string $status, int $adminId, string $adminNotes): bool
    {
        $allowed = ['approved', 'rejected', 'paid'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Invalid payout status: {$status}");
        }
        return $this->repo->processPayout($payoutId, $status, $adminId, $adminNotes);
    }

    public function bulkPayouts(array $ids, string $status, int $adminId): int
    {
        $allowed = ['approved', 'rejected', 'paid'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }
        $ids = array_filter(array_map('intval', $ids));
        if ($ids === []) {
            throw new InvalidArgumentException('No payouts selected.');
        }
        return $this->repo->bulkPayouts($ids, $status, $adminId);
    }

    public function exportPayouts(array $f): void
    {
        $rows = $this->repo->exportPayouts($f, 5000);

        $filename = 'payouts_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: no-cache, no-store');
        $out = fopen('php://output', 'w');
        if ($out !== false) {
            fputcsv($out, ['ID', 'Username', 'Email', 'Amount', 'Currency', 'Status', 'Wallet', 'Network', 'Created At', 'Paid At']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['id'], $r['username'], $r['email'],
                    $r['amount'], $r['currency_code'], $r['status'],
                    $r['wallet_address'] ?? '', $r['network'] ?? '',
                    $r['created_at'], $r['paid_at'] ?? '',
                ]);
            }
            fclose($out);
        }
        exit;
    }

    // =========================================================================
    // TIERS
    // =========================================================================

    public function saveTiers(array $tiersInput): bool
    {
        foreach ($tiersInput as $tier) {
            $level    = (int)($tier['level']           ?? 0);
            $rate     = (float)($tier['commission_rate'] ?? 0);
            $label    = substr(trim((string)($tier['label'] ?? '')), 0, 50);
            $active   = (int)(bool)($tier['is_active'] ?? true);
            $minRef   = (int)($tier['min_referred']    ?? 0);

            if ($level < 1 || $level > 10) {
                continue;
            }
            if ($rate < 0 || $rate > 100) {
                throw new InvalidArgumentException("Commission rate must be between 0 and 100 for level {$level}.");
            }

            $this->repo->upsertTier($level, $rate, $label, $active, $minRef);
        }
        return true;
    }

    // =========================================================================
    // PROGRAM SETTINGS
    // =========================================================================

    public function getSettings(): array
    {
        try {
            return [
                'settings' => $this->repo->getProgramSettings(),
                'tiers'    => $this->repo->getTiers(),
            ];
        } catch (Throwable) {
            return ['settings' => [], 'tiers' => []];
        }
    }

    public function saveSettings(array $input): bool
    {
        $allowed = [
            'program_enabled', 'signup_bonus_enabled', 'signup_bonus_amount',
            'signup_bonus_currency', 'min_payout_amount', 'payout_auto_approve',
            'payout_auto_threshold', 'cookie_days', 'qualification_trades',
            'qualification_volume', 'max_levels', 'commission_on', 'terms_url',
        ];
        $toSave = [];
        foreach ($allowed as $key) {
            if (isset($input[$key])) {
                $toSave[$key] = (string)$input[$key];
            }
        }
        return $this->repo->saveProgramSettings($toSave);
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    public function reports(): array
    {
        try {
            return [
                'monthly'      => $this->repo->monthlyCommissions(12),
                'growth'       => $this->repo->referralGrowth(90),
                'byCurrency'   => $this->repo->commissionsByCurrency(),
                'byType'       => $this->repo->commissionByType(),
                'topAffiliates'=> $this->repo->topAffiliates(20),
            ];
        } catch (Throwable) {
            return ['monthly' => [], 'growth' => [], 'byCurrency' => [], 'byType' => [], 'topAffiliates' => []];
        }
    }
}
