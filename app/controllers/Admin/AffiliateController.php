<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\AdminAffiliateService;
use InvalidArgumentException;
use Throwable;

final class AffiliateController extends AdminBaseController
{
    private function service(): AdminAffiliateService
    {
        return new AdminAffiliateService();
    }

    private function adminId(): int
    {
        return (int)(Session::get('auth.user_id') ?? 0);
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $data = $this->service()->dashboard();

        $this->adminView('admin/affiliate/index', array_merge($data, [
            'title'        => 'Affiliate Dashboard',
            'adminSection' => 'affiliate',
        ]));
    }

    // =========================================================================
    // AFFILIATES LIST
    // =========================================================================

    public function affiliates(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $f = [
            'search'    => (string)($request->query('search', '')),
            'date_from' => (string)($request->query('date_from', '')),
            'date_to'   => (string)($request->query('date_to', '')),
        ];
        $page    = max(1, (int)($request->query('page', '1')));
        $perPage = 20;

        $data = $this->service()->affiliatesList($f, $page, $perPage);

        $this->adminView('admin/affiliate/affiliates', array_merge($data, [
            'title'        => 'Affiliates',
            'adminSection' => 'affiliate',
        ]));
    }

    // =========================================================================
    // COMMISSIONS
    // =========================================================================

    public function commissions(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $f = [
            'status'      => (string)($request->query('status', '')),
            'type'        => (string)($request->query('type', '')),
            'referrer_id' => (string)($request->query('referrer_id', '')),
            'currency'    => (string)($request->query('currency', '')),
            'date_from'   => (string)($request->query('date_from', '')),
            'date_to'     => (string)($request->query('date_to', '')),
        ];
        $page    = max(1, (int)($request->query('page', '1')));
        $perPage = 25;

        $data = $this->service()->commissionsList($f, $page, $perPage);

        $this->adminView('admin/affiliate/commissions', array_merge($data, [
            'title'        => 'Commission Management',
            'adminSection' => 'affiliate',
        ]));
    }

    public function markPaid(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        Csrf::verify();

        $ids = (array)($request->input('ids', []));
        try {
            $count = $this->service()->markCommissionsPaid($ids);
            Session::flash('success', "{$count} commission(s) marked as paid.");
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable) {
            Session::flash('error', 'Failed to update commissions.');
        }
        Response::redirect('/admin/affiliate/commissions');
    }

    public function exportCommissions(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $f = [
            'status'    => (string)($request->query('status', '')),
            'type'      => (string)($request->query('type', '')),
            'date_from' => (string)($request->query('date_from', '')),
            'date_to'   => (string)($request->query('date_to', '')),
        ];
        $this->service()->exportCommissions($f);
    }

    // =========================================================================
    // PAYOUTS
    // =========================================================================

    public function payouts(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $f = [
            'status'    => (string)($request->query('status', '')),
            'search'    => (string)($request->query('search', '')),
            'currency'  => (string)($request->query('currency', '')),
            'date_from' => (string)($request->query('date_from', '')),
            'date_to'   => (string)($request->query('date_to', '')),
        ];
        $page    = max(1, (int)($request->query('page', '1')));
        $perPage = 20;

        $data = $this->service()->payoutsList($f, $page, $perPage);

        $this->adminView('admin/affiliate/payouts', array_merge($data, [
            'title'        => 'Payout Management',
            'adminSection' => 'affiliate',
        ]));
    }

    public function processPayout(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        Csrf::verify();

        $payoutId   = (int)($request->input('payout_id', 0));
        $status     = (string)($request->input('status', ''));
        $adminNotes = substr(trim((string)($request->input('admin_notes', ''))), 0, 500);

        try {
            $this->service()->processPayout($payoutId, $status, $this->adminId(), $adminNotes);
            Session::flash('success', 'Payout #' . $payoutId . ' status updated to ' . $status . '.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable) {
            Session::flash('error', 'Failed to process payout.');
        }
        Response::redirect('/admin/affiliate/payouts');
    }

    public function bulkPayouts(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        Csrf::verify();

        $ids    = (array)($request->input('ids', []));
        $status = (string)($request->input('status', ''));

        try {
            $count = $this->service()->bulkPayouts($ids, $status, $this->adminId());
            Session::flash('success', "{$count} payout(s) updated to {$status}.");
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable) {
            Session::flash('error', 'Bulk payout action failed.');
        }
        Response::redirect('/admin/affiliate/payouts');
    }

    public function exportPayouts(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $f = [
            'status'    => (string)($request->query('status', '')),
            'date_from' => (string)($request->query('date_from', '')),
            'date_to'   => (string)($request->query('date_to', '')),
        ];
        $this->service()->exportPayouts($f);
    }

    // =========================================================================
    // TIERS
    // =========================================================================

    public function tiers(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $data = $this->service()->getSettings();

        $this->adminView('admin/affiliate/tiers', [
            'title'        => 'Commission Tiers',
            'adminSection' => 'affiliate',
            'tiers'        => $data['tiers'],
        ]);
    }

    public function saveTiers(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        Csrf::verify();

        $tiersInput = (array)($request->input('tiers', []));
        try {
            $this->service()->saveTiers($tiersInput);
            Session::flash('success', 'Commission tiers saved.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable) {
            Session::flash('error', 'Failed to save tiers.');
        }
        Response::redirect('/admin/affiliate/tiers');
    }

    // =========================================================================
    // SETTINGS
    // =========================================================================

    public function settings(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $data = $this->service()->getSettings();

        $this->adminView('admin/affiliate/settings', array_merge($data, [
            'title'        => 'Affiliate Program Settings',
            'adminSection' => 'affiliate',
        ]));
    }

    public function saveSettings(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        Csrf::verify();

        try {
            $this->service()->saveSettings($request->all());
            Session::flash('success', 'Affiliate program settings saved.');
        } catch (Throwable) {
            Session::flash('error', 'Failed to save settings.');
        }
        Response::redirect('/admin/affiliate/settings');
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    public function reports(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $data = $this->service()->reports();

        $this->adminView('admin/affiliate/reports', array_merge($data, [
            'title'        => 'Affiliate Reports & Analytics',
            'adminSection' => 'affiliate',
        ]));
    }
}
