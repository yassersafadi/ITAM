<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;

/**
 * The About page: what ITAM is, its licence, and the AIM platform it comes from. Also the small AIM card under the
 * side menu (hidden per user for 90 days) and the welcome card shown once after installation.
 */
final class AboutController extends Controller
{
    private const HIDE_DAYS = 90;

    public function index(): void
    {
        $this->view('about/index', ['promo' => config('promo')]);
    }

    public function hidePromo(): void
    {
        db()->setSetting('promo.hidden_until.' . (int) Auth::id(), date('Y-m-d', strtotime('+' . self::HIDE_DAYS . ' days')));
        back();
    }

    public function dismissWelcome(): void
    {
        db()->setSetting('welcome.pending', '0');
        back();
    }

    public static function promoVisible(): bool
    {
        $until = (string) db()->setting('promo.hidden_until.' . (int) Auth::id(), '');
        return $until === '' || $until < today();
    }

    public static function welcomePending(): bool
    {
        return (string) db()->setting('welcome.pending', '0') === '1' && can('settings.manage');
    }
}
