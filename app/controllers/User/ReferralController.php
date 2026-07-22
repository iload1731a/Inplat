<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Request;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserReferralService;
use Throwable;

final class ReferralController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $data = (new UserReferralService())->data($userId);
        } catch (Throwable) {
            $data = ['referralCode' => '', 'stats' => [], 'referrals' => [], 'commissions' => [], 'earningsSeries' => []];
        }

        $this->userView('user/referral/index', array_merge($data, [
            'title'       => 'Referral Program',
            'userSection' => 'referral',
        ]));
    }
}
