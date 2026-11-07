<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\Help;

final class HelpController extends Controller
{
    public function index(): void
    {
        $this->view('help/index', ['groups' => $this->groups(), 'index' => $this->searchIndex()]);
    }

    public function show(string $slug): void
    {
        $section = Help::load($slug) ?? abort(404);
        $meta = Help::visible()[$slug] ?? abort(403, t('errors.forbidden'));
        $order = array_keys(Help::visible());
        $i = array_search($slug, $order, true);
        $this->view('help/show', [
            'slug' => $slug, 'section' => $section, 'meta' => $meta, 'groups' => $this->groups(),
            'prev' => $i > 0 ? $order[$i - 1] : null, 'next' => $order[$i + 1] ?? null,
        ]);
    }

    /** The employee guide (My IT), readable without a staff account. Every other section stays staff-only. */
    public function portal(): void
    {
        Help::$public = true;
        $section = Help::load('portal') ?? abort(404);
        $this->view('help/portal', ['section' => $section], 'layouts/guest');
    }

    /** The whole guide on one page, for printing or saving as PDF. */
    public function print(): void
    {
        $sections = [];
        foreach (array_keys(Help::visible()) as $slug) {
            if ($s = Help::load($slug)) {
                $sections[$slug] = $s;
            }
        }
        $this->view('help/print', ['sections' => $sections, 'groups' => $this->groups()], 'layouts/print');
    }

    /** @return array<string, list<array{slug: string, title: string, summary: string, icon: string}>> */
    private function groups(): array
    {
        $out = [];
        foreach (Help::visible() as $slug => [$group, $icon]) {
            if ($s = Help::load($slug)) {
                $out[$group][] = ['slug' => $slug, 'title' => $s['title'], 'summary' => $s['summary'], 'icon' => $icon];
            }
        }
        return array_replace(array_fill_keys(array_intersect(Help::GROUPS, array_keys($out)), []), $out);
    }

    private function searchIndex(): array
    {
        $out = [];
        foreach (array_keys(Help::visible()) as $slug) {
            if ($s = Help::load($slug)) {
                $out[] = ['slug' => $slug, 'title' => $s['title'], 'summary' => $s['summary'], 'text' => mb_strtolower(Help::plain($s))];
            }
        }
        return $out;
    }
}
