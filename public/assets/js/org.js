/**
 * Organisation structure (Directory › Departments): each unit folds its sub-units. The executive office and the
 * sectors show first; what you open is remembered in this browser. While the filter has text every match shows.
 */
import { $, $$ } from './util.js';
const KEY = 'itam.org.open';
export function initOrgTree() {
    const table = $('table[data-org-tree]');
    if (!table)
        return;
    const rows = $$('tbody tr[data-org]', table);
    const kids = new Map();
    rows.forEach((tr) => {
        const p = tr.dataset.orgParent ?? '0';
        if (!kids.has(p))
            kids.set(p, []);
        kids.get(p).push(tr);
    });
    let open;
    try {
        const saved = localStorage.getItem(KEY);
        open = new Set(saved ? JSON.parse(saved) : []);
        if (!saved)
            rows.filter((tr) => Number(tr.dataset.orgDepth) === 0).forEach((tr) => open.add(tr.dataset.org));
    }
    catch {
        open = new Set(rows.filter((tr) => Number(tr.dataset.orgDepth) === 0).map((tr) => tr.dataset.org));
    }
    const save = () => {
        try {
            localStorage.setItem(KEY, JSON.stringify([...open]));
        }
        catch { /* private mode: not remembered */ }
    };
    // A row shows when every unit above it is open.
    const paint = () => {
        const walk = (parent, visible) => {
            (kids.get(parent) ?? []).forEach((tr) => {
                tr.classList.toggle('org-folded', !visible);
                const id = tr.dataset.org;
                const btn = $('[data-org-toggle]', tr);
                btn?.setAttribute('aria-expanded', String(open.has(id)));
                walk(id, visible && open.has(id));
            });
        };
        walk('0', true);
        // Units whose parent is not listed (filtered out by branch) start a tree of their own.
        rows.filter((tr) => tr.dataset.orgParent !== '0' && !rows.some((r) => r.dataset.org === tr.dataset.orgParent))
            .forEach((tr) => { tr.classList.remove('org-folded'); walk(tr.dataset.org, open.has(tr.dataset.org)); });
    };
    table.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-org-toggle]');
        if (!btn)
            return;
        e.preventDefault();
        const id = btn.closest('tr').dataset.org;
        open.has(id) ? open.delete(id) : open.add(id);
        save();
        paint();
    });
    $$('[data-org-all]').forEach((b) => b.addEventListener('click', () => {
        open = b.dataset.orgAll === 'open' ? new Set(rows.filter((tr) => kids.has(tr.dataset.org)).map((tr) => tr.dataset.org)) : new Set();
        save();
        paint();
    }));
    const filter = $('[data-table-filter="#' + table.id + '"]');
    filter?.addEventListener('input', () => table.classList.toggle('org-searching', filter.value.trim() !== ''));
    paint();
}
