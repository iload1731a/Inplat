<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\ReferralRepository;
use InvalidArgumentException;
use Throwable;

/**
 * ReferralService
 *
 * Business logic for the user-facing Referral System,
 * Affiliate Program and Commission Engine.
 */
final class ReferralService
{
    private readonly ReferralRepository $repo;

    public function __construct(?ReferralRepository $repo = null)
    {
        $this->repo = $repo ?? new ReferralRepository();
    }

    // =========================================================================
    // DASHBOARD DATA BUNDLE
    // =========================================================================

    public function dashboard(int $userId): array
    {
        try {
            $settings = $this->repo->programSettings();
            $code     = $this->repo->referralCode($userId);

            if ($code === '' && ($settings['program_enabled'] ?? '1') === '1') {
                $code = $this->repo->generateCode($userId);
            }

            return [
                'referralCode'    => $code,
                'stats'           => $this->repo->stats($userId),
                'tierStats'       => $this->repo->tierStats($userId),
                'earningsSeries'  => $this->repo->earningsSeries($userId, 30),
                'earningsByCurrency' => $this->repo->earningsByCurrency($userId),
                'recentReferrals' => $this->repo->referrals($userId, 1, 10),
                'recentCommissions' => $this->repo->commissions($userId, 1, 10),
                'tiers'           => $this->repo->tiers(),
                'settings'        => $settings,
                'programEnabled'  => ($settings['program_enabled'] ?? '1') === '1',
            ];
        } catch (Throwable) {
            return [
                'referralCode'       => '',
                'stats'              => [],
                'tierStats'          => [],
                'earningsSeries'     => [],
                'earningsByCurrency' => [],
                'recentReferrals'    => [],
                'recentCommissions'  => [],
                'tiers'              => [],
                'settings'           => [],
                'programEnabled'     => false,
            ];
        }
    }

    // =========================================================================
    // REFERRALS
    // =========================================================================

    public function referralsList(int $userId, int $page, int $perPage, string $status): array
    {
        try {
            $rows  = $this->repo->referrals($userId, $page, $perPage, $status);
            $total = $this->repo->referralsCount($userId, $status);
            return [
                'rows'        => $rows,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ];
        } catch (Throwable) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }

    // =========================================================================
    // NETWORK TREE
    // =========================================================================

    public function networkTree(int $userId): array
    {
        try {
            return $this->repo->networkTree($userId);
        } catch (Throwable) {
            return [];
        }
    }

    // =========================================================================
    // COMMISSIONS
    // =========================================================================

    public function commissionsList(int $userId, int $page, int $perPage, string $status, string $type): array
    {
        try {
            $rows  = $this->repo->commissions($userId, $page, $perPage, $status, $type);
            $total = $this->repo->commissionsCount($userId, $status, $type);
            return [
                'rows'        => $rows,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ];
        } catch (Throwable) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }

    // =========================================================================
    // REWARDS
    // =========================================================================

    public function rewardsPage(int $userId): array
    {
        try {
            return [
                'rewards'     => $this->repo->rewards($userId),
                'rewardStats' => $this->repo->rewardStats($userId),
            ];
        } catch (Throwable) {
            return ['rewards' => [], 'rewardStats' => []];
        }
    }

    // =========================================================================
    // PAYOUTS
    // =========================================================================

    public function withdrawPage(int $userId): array
    {
        try {
            return [
                'payouts'    => $this->repo->payouts($userId),
                'balances'   => $this->repo->pendingPayoutBalance($userId),
                'settings'   => $this->repo->programSettings(),
                'hasPending' => $this->repo->hasPendingPayout($userId),
            ];
        } catch (Throwable) {
            return ['payouts' => [], 'balances' => [], 'settings' => [], 'hasPending' => false];
        }
    }

    public function requestPayout(int $userId, array $input): array
    {
        $settings = $this->repo->programSettings();

        if (($settings['program_enabled'] ?? '1') !== '1') {
            throw new InvalidArgumentException('Affiliate program is currently disabled.');
        }

        if ($this->repo->hasPendingPayout($userId)) {
            throw new InvalidArgumentException('You already have a pending payout request. Please wait for it to be processed.');
        }

        $amount  = (float)($input['amount'] ?? 0);
        $minPay  = (float)($settings['min_payout_amount'] ?? 10);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payout amount must be greater than zero.');
        }
        if ($amount < $minPay) {
            throw new InvalidArgumentException("Minimum payout amount is {$minPay}.");
        }

        $currencyId    = (int)($input['currency_id'] ?? 0);
        $walletAddress = trim((string)($input['wallet_address'] ?? ''));
        $network       = trim((string)($input['network'] ?? ''));
        $notes         = substr(trim((string)($input['notes'] ?? '')), 0, 500);

        if ($currencyId <= 0) {
            throw new InvalidArgumentException('Please select a currency.');
        }
        if ($walletAddress === '') {
            throw new InvalidArgumentException('Wallet address is required.');
        }

        $balances    = $this->repo->pendingPayoutBalance($userId);
        $available   = 0.0;
        foreach ($balances as $bal) {
            if ((int)$bal['currency_id'] === $currencyId) {
                $available = (float)$bal['available_balance'];
                break;
            }
        }

        if ($amount > $available) {
            throw new InvalidArgumentException('Requested amount exceeds your available commission balance.');
        }

        $id = $this->repo->requestPayout($userId, $currencyId, number_format($amount, 18, '.', ''), $walletAddress, $network, $notes);
        return ['payout_id' => $id];
    }
}
