<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\View;
use ITAM\Services\DashboardData;

final class DashboardController extends Controller
{
    public function index(): void
    {
        [$d, $location, $locations] = $this->data();
        $this->view('dashboard/index', [
            'd' => $d, 'summary' => $d->summary(), 'attention' => $d->attention(),
            'location' => $location, 'locations' => $locations, 'layout' => DashboardData::layout(),
        ]);
    }

    /** The attention list, refreshed every minute without reloading the page. */
    public function live(): void
    {
        [$d] = $this->data();
        header('Cache-Control: no-store');
        json_response([
            'attention' => View::partial('dashboard/_attention', ['attention' => $d->attention()]),
            'updated' => fmt_date(now(), true),
        ]);
    }

    public function layout(): void
    {
        $reset = (bool) input('reset');
        DashboardData::saveLayout($reset ? null : array_filter(explode(',', (string) input('order'))), array_filter(explode(',', (string) input('hidden'))));
        wants_json() ? json_response(['ok' => true]) : redirect('/');
    }

    /** Data source for the chosen location (only locations the user may see). */
    private function data(): array
    {
        $locations = db()->all('SELECT id, name FROM locations l WHERE ' . (scope_ids() ? 'l.id IN (' . implode(',', scope_ids()) . ')' : '1=1') . ' ORDER BY name');
        $location = (int) ($_GET['location'] ?? 0);
        if (!in_array($location, array_map('intval', array_column($locations, 'id')), true)) {
            $location = null;
        }
        return [new DashboardData($location), $location, $locations];
    }
}
