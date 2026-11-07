import { $, $$, cfg, debounce, escapeHtml, getJson, icon, t, url } from './util.js';

interface NlqResponse {
    filters: Record<string, string | number>;
    chips: string[];
    count: number;
    url?: string;
    preview: { id: number; tag: string; name: string; status: string; health: number | null; url: string; meta: string }[];
}
interface SearchResponse {
    results: { type: string; icon: string; title: string; sub: string | null; url: string }[];
}
interface Item { url: string; html: string }

/**
 * Command palette (Ctrl/⌘+K). Combines navigation commands, instant search and the multilingual
 * natural-language asset query engine, e.g. "laptops in Jeddah with expired warranty".
 */
export function initPalette(): void {
    const root = $('#palette');
    const input = $<HTMLInputElement>('#palette-input');
    const body = $('#palette-body');
    if (!root || !input || !body) return;

    let items: Item[] = [];
    let active = 0;
    let ctrl: AbortController | null = null;

    // Quick actions only for roles allowed to do them (window.ITAM.perms lists the user's relevant permissions;
    // when it is missing nothing is offered rather than offering what the server would refuse).
    const perms = (cfg() as unknown as { perms?: unknown }).perms;
    const allowed = (p: string): boolean => Array.isArray(perms) ? perms.includes(p) : !!(perms && (perms as Record<string, boolean>)[p]);
    const quick = [
        { url: url('assets/create'), icon: 'plus', label: t('palette.new_asset'), perm: 'assets.edit' },
        { url: url('maintenance#new'), icon: 'wrench', label: t('palette.new_work_order'), perm: 'maintenance.edit' },
        { url: url('audits'), icon: 'clipboard', label: t('palette.start_audit'), perm: 'audits.run' },
    ].filter((q) => allowed(q.perm));

    const open = () => {
        root.hidden = false;
        input.value = '';
        lastValue = '';
        render(null, null);
        setTimeout(() => input.focus(), 10);
    };
    const close = () => {
        root.hidden = true;
        ctrl?.abort();
    };

    const commandItems = (q: string): Item[] => {
        const needle = q.toLowerCase();
        return [...cfg().commands, ...quick]
            .filter((c) => !needle || c.label.toLowerCase().includes(needle))
            .slice(0, needle ? 5 : 20)
            .map((c) => ({ url: c.url, html: `<span class="list-icon">${icon(c.icon)}</span><span><strong>${escapeHtml(c.label)}</strong></span>${icon('arrow', 'icon icon-xs rtl-flip')}` }));
    };

    function render(nlq: NlqResponse | null, search: SearchResponse | null, loading = false): void {
        const q = input!.value.trim();
        items = [];
        let html = '';

        if (nlq && nlq.chips.length && nlq.url) {
            items.push({ url: nlq.url, html: '' });
            html += `<a class="pal-nlq pal-item" data-idx="0" href="${escapeHtml(nlq.url)}">
                <span class="list-icon">${icon('sparkles')}</span>
                <span><strong>${escapeHtml(t('palette.understood'))}</strong>
                <span class="chips">${nlq.chips.map((c) => `<span class="chip">${escapeHtml(c)}</span>`).join('')}</span>
                <small>${escapeHtml(t('palette.matches', { n: nlq.count }))}</small></span>${icon('arrow', 'icon rtl-flip')}</a>`;
            if (nlq.preview.length) {
                html += `<div class="pal-section">${escapeHtml(t('palette.assets'))}</div>`;
                for (const a of nlq.preview) {
                    const idx = items.push({ url: a.url, html: '' }) - 1;
                    html += `<a class="pal-item" data-idx="${idx}" href="${escapeHtml(a.url)}"><span class="list-icon">${icon('box')}</span>
                        <span><strong>${escapeHtml(a.tag)} — ${escapeHtml(a.name)}</strong><small>${escapeHtml(a.status)}${a.meta ? ' · ' + escapeHtml(a.meta) : ''}</small></span>
                        ${a.health !== null ? `<span class="badge badge-${a.health >= 75 ? 'success' : a.health >= 50 ? 'warning' : 'danger'}">${a.health}</span>` : ''}</a>`;
                }
            }
        }

        const results = (search?.results ?? []).filter((r) => !nlq?.preview.some((p) => p.url === r.url));
        if (results.length) {
            html += `<div class="pal-section">${escapeHtml(t('palette.results'))}</div>`;
            for (const r of results) {
                const idx = items.push({ url: r.url, html: '' }) - 1;
                html += `<a class="pal-item" data-idx="${idx}" href="${escapeHtml(r.url)}"><span class="list-icon">${icon(r.icon)}</span>
                    <span><strong>${escapeHtml(r.title)}</strong><small>${escapeHtml(r.sub ?? '')}</small></span></a>`;
            }
        }

        const cmds = commandItems(q);
        if (cmds.length) {
            html += `<div class="pal-section">${escapeHtml(t(q ? 'palette.go_to' : 'palette.navigate'))}</div>`;
            for (const c of cmds) {
                const idx = items.push(c) - 1;
                html += `<a class="pal-item" data-idx="${idx}" href="${escapeHtml(c.url)}">${c.html}</a>`;
            }
        }

        if (!items.length) {
            html = `<div class="pal-empty">${escapeHtml(loading ? t('palette.thinking') : t('palette.nothing'))}</div>`;
        }
        body!.innerHTML = html;
        active = 0;
        highlight();
    }

    const highlight = () => {
        $$('.pal-item', body!).forEach((el) => el.classList.toggle('active', Number(el.dataset.idx) === active));
        $(`.pal-item[data-idx="${active}"]`, body!)?.scrollIntoView({ block: 'nearest' });
    };

    const query = debounce(async (q: string) => {
        ctrl?.abort();
        if (q.length < 2) { render(null, null); return; }
        ctrl = new AbortController();
        render(null, null, true);
        try {
            const search = await getJson<SearchResponse>('api/search?q=' + encodeURIComponent(q), ctrl.signal);
            if (input.value.trim() === q) render(null, search);
        } catch (err) {
            if ((err as Error).name !== 'AbortError') render(null, null);
        }
    }, 220);

    // IME input (Arabic keyboards, virtual keyboards) may only surface through composition/keyup events.
    let lastValue = '';
    const onChange = () => {
        const v = input.value.trim();
        if (v === lastValue) return;
        lastValue = v;
        query(v);
    };
    ['input', 'compositionend', 'keyup', 'change'].forEach((ev) => input.addEventListener(ev, onChange));
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); highlight(); e.preventDefault(); }
        else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); highlight(); e.preventDefault(); }
        else if (e.key === 'Enter') {
            const target = items[active];
            if (target) location.href = target.url;
            else if (input.value.trim()) location.href = url('assets') + '?q=' + encodeURIComponent(input.value.trim());
            e.preventDefault();
        } else if (e.key === 'Escape') close();
    });

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            root.hidden ? open() : close();
        } else if (e.key === '/' && root.hidden && !(e.target as Element).closest('input, textarea, select, [contenteditable]')
            && !$('[data-live-search], [data-grid-search]')) {
            // Pages with their own list search (e.g. the asset list) use '/' to focus it instead.
            e.preventDefault();
            open();
        }
    });
    $$('[data-action="open-palette"]').forEach((b) => b.addEventListener('click', open));
    $$('[data-action="close-palette"]').forEach((b) => b.addEventListener('click', close));
}
