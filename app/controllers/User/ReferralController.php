<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\ReferralService;
use InvalidArgumentException;
use Throwable;

final class ReferralController extends BaseController
{
    private function userId(): int
    {
        return (int)(Session::get('auth.user_id') ?? 0);
    }

    private function service(): ReferralService
    {
        return new ReferralService();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $data = $this->service()->dashboard($this->userId());

        $this->userView('user/referral/index', array_merge($data, [
            'title'       => 'Referral Program',
            'userSection' => 'referral',
            'breadcrumb'  => [['label' => 'Referral']],
        ]));
    }

    // =========================================================================
    // REFERRALS LIST
    // =========================================================================

    public function referrals(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $page    = max(1, (int)($request->query('page', '1')));
        $status  = (string)($request->query('status', ''));
        $perPage = 25;

        $data = $this->service()->referralsList($this->userId(), $page, $perPage, $status);

        $this->userView('user/referral/referrals', array_merge($data, [
            'title'       => 'My Referrals',
            'userSection' => 'referral',
            'breadcrumb'  => [
                ['label' => 'Referral', 'url' => '/user/referral'],
                ['label' => 'Referrals'],
            ],
            'statusFilter' => $status,
        ]));
    }

    // =========================================================================
    // NETWORK TREE
    // =========================================================================

    public function network(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $service = $this->service();
        $userId  = $this->userId();
        $dash    = $service->dashboard($userId);
        $network = $service->networkTree($userId);

        $this->userView('user/referral/network', [
            'title'          => 'Referral Network',
            'userSection'    => 'referral',
            'breadcrumb'     => [
                ['label' => 'Referral', 'url' => '/user/referral'],
                ['label' => 'Network'],
            ],
            'network'        => $network,
            'referralCode'   => $dash['referralCode'] ?? '',
            'stats'          => $dash['stats'] ?? [],
        ]);
    }

    // =========================================================================
    // COMMISSIONS
    // =========================================================================

    public function commissions(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $page    = max(1, (int)($request->query('page', '1')));
        $status  = (string)($request->query('status', ''));
        $type    = (string)($request->query('type', ''));
        $perPage = 25;

        $data = $this->service()->commissionsList($this->userId(), $page, $perPage, $status, $type);

        $this->userView('user/referral/commissions', array_merge($data, [
            'title'        => 'Commission History',
            'userSection'  => 'referral',
            'breadcrumb'   => [
                ['label' => 'Referral', 'url' => '/user/referral'],
                ['label' => 'Commissions'],
            ],
            'statusFilter' => $status,
            'typeFilter'   => $type,
        ]));
    }

    // =========================================================================
    // REWARDS
    // =========================================================================

    public function rewards(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $data = $this->service()->rewardsPage($this->userId());

        $this->userView('user/referral/rewards', array_merge($data, [
            'title'       => 'Referral Rewards',
            'userSection' => 'referral',
            'breadcrumb'  => [
                ['label' => 'Referral', 'url' => '/user/referral'],
                ['label' => 'Rewards'],
            ],
        ]));
    }

    // =========================================================================
    // PAYOUT REQUEST
    // =========================================================================

    public function withdraw(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $data = $this->service()->withdrawPage($this->userId());

        $this->userView('user/referral/withdraw', array_merge($data, [
            'title'       => 'Commission Withdrawal',
            'userSection' => 'referral',
            'breadcrumb'  => [
                ['label' => 'Referral', 'url' => '/user/referral'],
                ['label' => 'Withdraw'],
            ],
        ]));
    }

    public function requestWithdraw(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        Csrf::verify();

        try {
            $result = $this->service()->requestPayout($this->userId(), $request->all());
            Session::flash('success', 'Payout request #' . $result['payout_id'] . ' submitted. It will be reviewed within 1-3 business days.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable) {
            Session::flash('error', 'Failed to submit payout request. Please try again.');
        }

        Response::redirect('/user/referral/withdraw');
    }
}
