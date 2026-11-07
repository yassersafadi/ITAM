/**
 * Dashboard: per-user layout (drag to reorder, hide/show, saved on the server) and a quiet refresh
 * of the live parts every minute while the tab is visible.
 */
import { $, $$, postForm } from './util.js';

export function initDashboard(): void {
    const root = $('[data-dash]');
    if (!root) return;
    const bar = $('[data-dash-editbar]')!;
    let editing = false;
    let dragged: HTMLElement | null = null;

    const widgets = () => $$<HTMLElement>('[data-widget]', root);
    const setEditing = (on: boolean) => {
        editing = on;
        root.classList.toggle('dash-editing', on);
        bar.hidden = !on;
        widgets().forEach((w) => {
            w.draggable = on;
            // Hidden widgets are shown dimmed while editing so they can be brought back.
            w.hidden = !on && w.hasAttribute('data-hidden');
            const toggle = $('[data-dash-toggle]', w);
            if (toggle) {
                $('[data-on]', toggle)!.hidden = w.hasAttribute('data-hidden');
                $('[data-off]', toggle)!.hidden = !w.hasAttribute('data-hidden');
            }
        });
    };

    $('[data-dash-customize]')?.addEventListener('click', () => setEditing(!editing));
    root.addEventListener('click', (e) => {
        const btn = (e.target as Element).closest('[data-dash-toggle]');
        if (!btn) return;
        const w = btn.closest<HTMLElement>('[data-widget]')!;
        w.toggleAttribute('data-hidden');
        setEditing(true);
    });

    root.addEventListener('dragstart', (e) => {
        if (!editing) return;
        dragged = (e.target as Element).closest<HTMLElement>('[data-widget]');
        dragged?.classList.add('dragging');
        e.dataTransfer?.setData('text/plain', dragged?.dataset.widget ?? '');
    });
    root.addEventListener('dragover', (e) => {
        if (!editing || !dragged) return;
        e.preventDefault();
        const over = (e.target as Element).closest<HTMLElement>('[data-widget]');
        if (!over || over === dragged) return;
        const box = over.getBoundingClientRect();
        over.parentElement!.insertBefore(dragged, e.clientY > box.top + box.height / 2 ? over.nextSibling : over);
    });
    root.addEventListener('dragend', () => { dragged?.classList.remove('dragging'); dragged = null; });

    // Keyboard alternative: Alt+↑ / Alt+↓ moves the focused widget while editing.
    root.addEventListener('keydown', (e) => {
        if (!editing || !e.altKey || !['ArrowUp', 'ArrowDown'].includes(e.key)) return;
        const w = (e.target as Element).closest<HTMLElement>('[data-widget]');
        if (!w) return;
        e.preventDefault();
        if (e.key === 'ArrowUp' && w.previousElementSibling) w.parentElement!.insertBefore(w, w.previousElementSibling);
        if (e.key === 'ArrowDown' && w.nextElementSibling) w.parentElement!.insertBefore(w.nextElementSibling, w);
        (w.querySelector('[data-dash-toggle]') as HTMLElement | null)?.focus();
    });

    const base = (document.querySelector('meta[name=base]')?.getAttribute('content') ?? '');
    $('[data-dash-save]')?.addEventListener('click', async () => {
        const order = widgets().map((w) => w.dataset.widget!).join(',');
        const hidden = widgets().filter((w) => w.hasAttribute('data-hidden')).map((w) => w.dataset.widget!).join(',');
        try { await postForm(root.dataset.layoutUrl ?? base + '/dashboard/layout', { order, hidden }); } catch { /* kept locally until reload */ }
        setEditing(false);
    });
    $('[data-dash-reset]')?.addEventListener('click', async () => {
        try { await postForm(root.dataset.layoutUrl ?? base + '/dashboard/layout', { reset: '1' }); } catch { /* ignore */ }
        location.reload();
    });

    // "Needs your attention" filtered by module; the choice survives the minute refresh.
    let module = '';
    let all = false;
    const filterAttention = () => {
        const box = $('[data-live-part="attention"]', root);
        if (!box) return;
        $$<HTMLElement>('[data-att-filter]', box).forEach((b) => {
            const on = (b.dataset.attFilter ?? '') === module;
            b.classList.toggle('active', on);
            b.setAttribute('aria-pressed', String(on));
        });
        let shown = 0;
        $$<HTMLElement>('.attention-list li', box).forEach((li) => {
            const match = !module || li.dataset.module === module;
            li.hidden = !match || (!module && !all && shown >= 8);
            if (match) shown++;
        });
        const more = $<HTMLElement>('[data-att-more]', box);
        if (more) more.hidden = Boolean(module) || all;
    };
    root.addEventListener('click', (e) => {
        const target = e.target as Element;
        const chip = target.closest<HTMLElement>('[data-att-filter]');
        if (chip) {
            const picked = chip.dataset.attFilter ?? '';
            module = module === picked ? '' : picked;
        } else if (target.closest('[data-att-more]')) {
            all = true;
        } else {
            return;
        }
        filterAttention();
    });

    // Live parts refresh every minute while the page is visible.
    const refresh = async () => {
        if (document.hidden || editing || !root.dataset.liveUrl) return;
        try {
            const res = await fetch(root.dataset.liveUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const data = (await res.json()) as Record<string, string>;
            for (const key of ['attention', 'live']) {
                const el = $(`[data-live-part="${key}"]`, root);
                if (el && data[key]) el.outerHTML = data[key];
            }
            if (module && !$(`[data-att-filter="${module}"]`, root)) module = '';
            filterAttention();
        } catch { /* offline: keep what is shown */ }
    };
    setInterval(refresh, 60_000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) void refresh(); });
}
