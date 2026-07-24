<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserReferralRepository;

final class UserReferralService
{
    private readonly UserReferralRepository $repo;

    public function __construct(?UserReferralRepository $repo = null)
    {
        $this->repo = $repo ?? new UserReferralRepository();
    }

    public function data(int $userId): array
    {
        return [
            'referralCode'  => $this->repo->referralCode($userId),
            'stats'         => $this->repo->stats($userId),
            'referrals'     => $this->repo->referrals($userId),
            'commissions'   => $this->repo->commissions($userId),
            'earningsSeries'=> $this->repo->earningsSeries($userId),
        ];
    }
}
