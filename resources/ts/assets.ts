/**
 * Asset list: live search and filtering without page reloads, filter and preview side panels,
 * bulk selection that survives paging, display preferences and keyboard navigation.
 * The server renders every part; this module only fetches and swaps them, so the page also works without JavaScript.
 */
import { $, $$, cfg, debounce, getJson, postForm, t, url } from './util.js';

interface Parts { toolbar: string; results: string; drawer: string; count: string; show: string; url: string }

export function initAssetGrid(): void {
    const root = $('[data-assets]');
    if (!root) return;
    const part = (name: string) => $(`[data-part="${name}"]`, root)!;
    const listPath = new URL($<HTMLFormElement>('[data-grid-form]', root)!.action, location.href).pathname;
    const selected = new Set<string>();
    let query = new URLSearchParams(location.search);
    let inflight: AbortController | null = null;
    let active: HTMLElement | null = null;
    /** Layout forced for this visit only (cards on phones), never saved as a preference. */
    let transientView: string | null = null;

    const clean = (p: URLSearchParams): URLSearchParams => {
        const out = new URLSearchParams();
        p.forEach((v, k) => { if (v !== '' && k !== 'partial') out.append(k, v); });
        return out;
    };

    /** Fetch the parts for `params`; `swap` says which ones to replace. */
    async function load(params: URLSearchParams, swap: { toolbar?: boolean; drawer?: boolean } = {}): Promise<Parts | null> {
        inflight?.abort();
        inflight = new AbortController();
        const p = clean(params);
        if (transientView && !p.has('view')) {
            p.set('view', transientView);
            p.set('transient', '1');
        }
        if (p.get('view') && p.get('view') !== transientView && !p.has('transient')) transientView = null;
        p.set('partial', '1');
        root!.classList.add('is-loading');
        try {
            const res = await fetch(listPath + '?' + p.toString(), { headers: { Accept: 'application/json' }, signal: inflight.signal, credentials: 'same-origin' });
            if (!res.ok) throw new Error(String(res.status));
            const data = (await res.json()) as Parts;
            const openMenu = !!$('.dropdown.open [data-cols]', root!);
            if (swap.toolbar) part('toolbar').innerHTML = data.toolbar;
            part('results').innerHTML = data.results;
            if (swap.drawer !== false && $('[data-drawer="filters"]', root!)!.hidden) part('drawer').innerHTML = data.drawer;
            if (openMenu) $('[data-cols]', root!)?.closest('.dropdown')?.classList.add('open');
            const count = $('[data-assets-count]');
            if (count) count.textContent = data.count;
            const url = new URL(data.url, location.href);
            query = new URLSearchParams(url.search);
            history.replaceState(null, '', url.pathname + url.search);
            syncQueryLinks();
            restoreSelection();
            active = null;
            return data;
        } catch (e) {
            if ((e as Error).name !== 'AbortError') location.href = listPath + '?' + clean(params).toString();
            return null;
        } finally {
            root!.classList.remove('is-loading');
        }
    }

    /** Export / labels links and the "save view" form follow the current filters. */
    function syncQueryLinks(): void {
        const q = query.toString();
        $$<HTMLAnchorElement>('[data-keep-query]').forEach((a) => {
            const u = new URL(a.href, location.href);
            const format = u.searchParams.get('format');   // export menu entries keep their file type
            u.search = q;
            if (format) u.searchParams.set('format', format);
            a.href = u.toString();
        });
        const saved = $<HTMLInputElement>('[data-current-query]');
        if (saved) saved.value = q;
    }

    const toolbarParams = (): URLSearchParams => new URLSearchParams(new FormData($<HTMLFormElement>('[data-grid-form]', root)!) as unknown as Record<string, string>);

    // ---------- toolbar ----------
    const liveSearch = debounce(() => { void load(toolbarParams()); }, 280);
    root.addEventListener('input', (e) => {
        const el = e.target as HTMLElement;
        if (el.matches('[data-live-search]')) liveSearch();
    });
    root.addEventListener('change', (e) => {
        const el = e.target as HTMLElement;
        if (el.matches('[data-grid-form] select:not([data-pref])')) void load(toolbarParams());
        else if (el.matches('select[data-pref]')) {
            const p = new URLSearchParams(query);
            p.set((el as HTMLSelectElement).dataset.pref!, (el as HTMLSelectElement).value);
            p.delete('page');
            void load(p, { toolbar: true });
        } else if (el.matches('[data-cols] input')) {
            const p = new URLSearchParams(query);
            p.set('cols', $$<HTMLInputElement>('[data-cols] input:checked', root).map((c) => c.value).join(','));
            void load(p, { toolbar: true });
        }
    });
    root.addEventListener('submit', (e) => {
        const form = e.target as HTMLFormElement;
        if (form.matches('[data-grid-form]')) {
            e.preventDefault();
            void load(toolbarParams());
        } else if (form.matches('[data-drawer-form]')) {
            e.preventDefault();
            const params = new URLSearchParams(new FormData(form) as unknown as Record<string, string>);
            closeDrawers();
            void load(params, { toolbar: true });
        } else if (form.matches('[data-grid-bulk]')) {
            const submitter = (e as SubmitEvent).submitter as HTMLButtonElement | null;
            // Read-only actions open their own pages directly: the bulk route is for people who may edit.
            const ids = Array.from(selected).join(',');
            const go = (path: string, params: Record<string, string>): void => {
                e.preventDefault();
                const u = new URL(path, form.action);
                Object.entries(params).forEach(([k, v]) => u.searchParams.set(k, v));
                if (path === 'labels') window.open(u.toString(), '_blank');
                else location.href = u.toString();
            };
            if (ids && submitter?.value === 'labels') return go('labels', { ids });
            if (ids && submitter?.value === 'export') return go('export', { ids, format: $<HTMLSelectElement>('[name=bulk_format]', form)?.value ?? 'csv' });
            if (ids && submitter?.value === 'window') return go('../windows/create', { assets: ids });
            form.target = '';
            // Selections made on other pages travel with the form.
            $$('input[data-carried]', form).forEach((i) => i.remove());
            const onPage = new Set($$<HTMLInputElement>('[data-check]', form).map((c) => c.value));
            selected.forEach((id) => {
                if (onPage.has(id)) return;
                const h = document.createElement('input');
                Object.assign(h, { type: 'hidden', name: 'ids[]', value: id });
                h.dataset.carried = '1';
                form.appendChild(h);
            });
        }
    });

    // Links that only change filters, sorting, paging or layout are fetched in place.
    root.addEventListener('click', (e) => {
        const me = e as MouseEvent;
        const a = (e.target as Element).closest<HTMLAnchorElement>('a[href]');
        if (!a || a.target || me.ctrlKey || me.metaKey || me.shiftKey || me.button !== 0) return;
        const u = new URL(a.href, location.href);
        if (u.origin !== location.origin || u.pathname !== listPath) return;
        e.preventDefault();
        if (a.closest('[data-drawer="filters"]')) closeDrawers();
        void load(new URLSearchParams(u.search), { toolbar: true });
        if (a.closest('.pagination')) root.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });

    // ---------- drawers ----------
    const backdrop = $('.drawer-backdrop', root)!;
    function openDrawer(name: string): HTMLElement {
        closeDrawers(name);
        const d = $(`[data-drawer="${name}"]`, root!)!;
        d.hidden = false;
        backdrop.hidden = false;
        requestAnimationFrame(() => d.classList.add('open'));
        return d;
    }
    function closeDrawers(except?: string): void {
        $$('[data-drawer]', root!).forEach((d) => {
            if (d.dataset.drawer === except) return;
            d.classList.remove('open');
            d.hidden = true;
        });
        if (!except) backdrop.hidden = true;
        $$('.is-previewed', root!).forEach((r) => r.classList.remove('is-previewed'));
    }
    root.addEventListener('click', (e) => {
        const el = e.target as Element;
        const opener = el.closest<HTMLElement>('[data-drawer-open]');
        if (opener) {
            const d = openDrawer(opener.dataset.drawerOpen!);
            if (!part('drawer').innerHTML.trim()) void load(query);
            $<HTMLElement>('input, select', d)?.focus();
        }
        if (el.closest('[data-drawer-close]')) closeDrawers();
    });

    // Facet checkboxes write their combined value into one hidden field; the button shows the result count.
    const previewCount = debounce(async (form: HTMLFormElement) => {
        const p = clean(new URLSearchParams(new FormData(form) as unknown as Record<string, string>));
        p.set('partial', '1');
        try {
            const res = await fetch(listPath + '?' + p.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = (await res.json()) as Parts;
            const btn = $('[type=submit]', form);
            if (btn) btn.lastChild!.textContent = ' ' + data.show;
        } catch { /* the submit still works */ }
    }, 350);
    root.addEventListener('change', (e) => {
        const el = e.target as HTMLInputElement;
        const form = el.closest<HTMLFormElement>('[data-drawer-form]');
        if (!form) return;
        if (el.dataset.multi) {
            const key = el.dataset.multi;
            $<HTMLInputElement>(`[data-multi-target="${key}"]`, form)!.value = $$<HTMLInputElement>(`[data-multi="${key}"]:checked`, form).map((c) => c.value).join(',');
            el.closest('.facet')?.classList.toggle('on', el.checked);
        }
        previewCount(form);
    });

    // ---------- preview ----------
    async function preview(row: HTMLElement): Promise<void> {
        const d = openDrawer('preview');
        const body = $('[data-preview-body]', d)!;
        body.innerHTML = '<div class="drawer-loading">' + t('grid.loading') + '</div>';
        $$('.is-previewed', root!).forEach((r) => r.classList.remove('is-previewed'));
        row.classList.add('is-previewed');
        setActive(row, false);
        try {
            const res = await fetch(row.dataset.previewUrl!, { credentials: 'same-origin' });
            if (!res.ok) throw new Error(String(res.status));
            body.innerHTML = await res.text();
            $<HTMLElement>('.btn', body)?.focus({ preventScroll: true });
        } catch {
            body.innerHTML = '<div class="drawer-loading">' + t('grid.load_failed') + '</div>';
        }
    }
    root.addEventListener('click', (e) => {
        const me = e as MouseEvent;
        const el = e.target as Element;
        const btn = el.closest<HTMLElement>('[data-preview]');
        const row = el.closest<HTMLElement>('[data-row]');
        if (btn && row) {
            void preview(row);
            return;
        }
        if (!row || el.closest('a, button, input, select, label, textarea')) return;
        if (me.ctrlKey || me.metaKey) window.open(row.dataset.url, '_blank');
        else later(() => void preview(row));
    });
    // ---------- quick edit ----------
    type Choice = { v: string; l: string };
    type Choices = Record<'category' | 'location' | 'department' | 'assigned' | 'status' | 'criticality', Choice[]> & { manufacturer: string[]; model: { m: string; name: string }[] };
    type Saved = { ok?: boolean; confirm?: string; message?: string; link?: string };
    const NULLABLE = ['location', 'department', 'assigned'];
    const LATIN = ['tag', 'serial', 'ip', 'po', 'value'];
    let choices: Promise<Choices> | null = null;
    let editor: HTMLFormElement | null = null;
    let waiting = 0;
    /** A single click waits a moment, so that the second click of a double-click can cancel it. */
    const later = (fn: () => void) => {
        window.clearTimeout(waiting);
        waiting = window.setTimeout(fn, 260);
    };
    const closeEditor = () => {
        editor?.remove();
        editor = null;
    };
    document.addEventListener('mousedown', (e) => {
        if (editor && !editor.contains(e.target as Node)) closeEditor();
    });
    /** Refresh one row from the server: a change can touch other cells of it. */
    async function refreshRow(id: string, col: string): Promise<void> {
        const p = clean(query);
        p.set('partial', '1');
        const res = await fetch(listPath + '?' + p.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) return;
        const tpl = document.createElement('template');
        tpl.innerHTML = ((await res.json()) as Parts).results;
        const fresh = tpl.content.querySelector<HTMLElement>(`tr[data-id="${id}"]`);
        if (!fresh) return;
        $(`tr[data-id="${id}"]`, root!)?.replaceWith(fresh);
        restoreSelection();
        const cell = $(`[data-edit="${col}"]`, fresh);
        cell?.classList.add('is-saved');
        window.setTimeout(() => cell?.classList.remove('is-saved'), 1500);
    }
    function field(col: string, value: string, maker: string, list: Choices, box: HTMLElement): HTMLInputElement | HTMLSelectElement {
        const options = (list as unknown as Record<string, Choice[]>)[col];
        if (col in list && col !== 'manufacturer' && col !== 'model') {
            const select = document.createElement('select');
            if (NULLABLE.includes(col)) select.add(new Option('—', ''));
            options.forEach((o) => select.add(new Option(o.l, o.v)));
            select.value = value;
            return select;
        }
        const input = document.createElement('input');
        input.value = value;
        input.type = col === 'warranty' ? 'date' : 'text';
        if (LATIN.includes(col)) input.dir = 'ltr';
        if (col === 'value') input.inputMode = 'decimal';
        if (col === 'manufacturer' || col === 'model') {
            const known = document.createElement('datalist');
            known.id = 'quick-edit-list';
            const names = col === 'manufacturer' ? list.manufacturer : list.model.filter((m) => m.m === maker).map((m) => m.name);
            names.forEach((n) => { const o = document.createElement('option'); o.value = n; known.append(o); });
            box.append(known);
            input.setAttribute('list', known.id);
        }
        return input;
    }
    async function openEditor(cell: HTMLElement): Promise<void> {
        closeEditor();
        const id = cell.closest<HTMLElement>('[data-row]')!.dataset.id!;
        const col = cell.dataset.edit!;
        const was = cell.dataset.value ?? '';
        choices ??= getJson<Choices>('assets/quick-options');
        let list: Choices;
        try {
            list = await choices;
        } catch {
            choices = null;
            return;
        }
        const box = document.createElement('form');
        box.className = 'quick-edit';
        box.dir = cfg().dir;
        const input = field(col, was, cell.closest<HTMLElement>('[data-row]')!.dataset.maker ?? '', list, box);
        input.setAttribute('aria-label', root!.querySelector(`th.col-${col}`)?.textContent?.trim() ?? col);
        const ok = document.createElement('button');
        ok.className = 'quick-ok';
        ok.textContent = '✓';
        ok.setAttribute('aria-label', t('quick.save'));
        const no = document.createElement('button');
        no.type = 'button';
        no.className = 'quick-no';
        no.textContent = '✕';
        no.setAttribute('aria-label', t('quick.cancel'));
        const note = document.createElement('p');
        note.className = 'quick-note';
        note.hidden = true;
        note.setAttribute('role', 'status');
        box.prepend(input, ok, no);
        box.append(note);
        document.body.append(box);
        editor = box;
        const r = cell.getBoundingClientRect();
        const width = box.offsetWidth;
        const left = cfg().dir === 'rtl' ? r.right - width : r.left;
        box.style.left = Math.max(8, Math.min(left, document.documentElement.clientWidth - width - 8)) + window.scrollX + 'px';
        box.style.top = r.top + window.scrollY - 4 + 'px';
        input.focus();
        if (input instanceof HTMLInputElement) input.select();
        let sure = false;
        const say = (text: string, bad: boolean) => {
            note.textContent = text;
            note.hidden = false;
            note.classList.toggle('is-bad', bad);
        };
        no.addEventListener('click', closeEditor);
        input.addEventListener('input', () => { sure = false; note.hidden = true; });
        box.addEventListener('keydown', (e) => {
            e.stopPropagation();
            if (e.key === 'Escape') closeEditor();
        });
        box.addEventListener('submit', async (e) => {
            e.preventDefault();
            ok.disabled = true;
            try {
                const res = await postForm<Saved>(url(`assets/${id}/quick`), { col, value: input.value, sure: sure ? '1' : '' });
                if (res.confirm) {
                    sure = true;
                    say(res.confirm, true);
                    return;
                }
                await refreshRow(id, col);
                if (!res.message) {
                    closeEditor();
                    return;
                }
                input.remove();
                ok.remove();
                say(res.message, false);
                if (res.link) {
                    const a = document.createElement('a');
                    a.href = res.link;
                    a.className = 'btn btn-ghost btn-xs';
                    a.textContent = t('quick.open_receipt');
                    box.append(a);
                }
            } catch (err) {
                say((err as { data?: { error?: string } }).data?.error ?? t('quick.failed'), true);
            } finally {
                ok.disabled = false;
            }
        });
    }
    // A link in an editable cell also waits, or the first click of a double-click would leave the page.
    root.addEventListener('click', (e) => {
        const me = e as MouseEvent;
        const a = (e.target as Element).closest<HTMLAnchorElement>('td[data-edit] a[href]');
        if (!a || me.ctrlKey || me.metaKey || me.shiftKey || me.button !== 0) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        later(() => { location.href = a.href; });
    }, true);
    root.addEventListener('dblclick', (e) => {
        window.clearTimeout(waiting);
        const cell = (e.target as Element).closest<HTMLElement>('td[data-edit]');
        if (cell) {
            window.getSelection()?.removeAllRanges();
            void openEditor(cell);
            return;
        }
        const row = (e.target as Element).closest<HTMLElement>('[data-row]');
        if (row && !(e.target as Element).closest('a, button, input')) location.href = row.dataset.url!;
    });

    /** Where the pinned table header sits: under the top bar, and under the bulk bar while it shows. */
    function syncSticky(): void {
        // A table wider than its card scrolls sideways inside the card (the header is then not pinned).
        $$('.grid-scroll', root!).forEach((w) => {
            w.classList.remove('is-wide');
            const t = w.querySelector('table');
            if (t && t.scrollWidth > w.clientWidth + 1) w.classList.add('is-wide');
        });
        const top = document.querySelector<HTMLElement>('.topbar')?.offsetHeight ?? 0;
        const bar = $('[data-grid-bulk] [data-bulkbar]', root!);
        const extra = bar && !bar.hidden ? bar.offsetHeight + 12 : 0;
        root!.style.setProperty('--grid-topbar', top + 'px');
        root!.style.setProperty('--grid-sticky', top + extra + 'px');
    }
    window.addEventListener('resize', debounce(syncSticky, 150));

    // ---------- selection & bulk ----------
    function syncBulk(): void {
        const form = $('[data-grid-bulk]', root!);
        if (!form) return;
        const bar = $('[data-bulkbar]', form)!;
        bar.hidden = selected.size === 0;
        syncSticky();
        $('[data-bulk-count]', form)!.textContent = String(selected.size);
        $$<HTMLInputElement>('[data-check-all]', form).forEach((all) => {
            const boxes = $$<HTMLInputElement>('[data-check]', all.closest('table') ?? form);
            all.checked = boxes.length > 0 && boxes.every((b) => b.checked);
            all.indeterminate = !all.checked && boxes.some((b) => b.checked);
        });
    }
    function restoreSelection(): void {
        $$<HTMLInputElement>('[data-check]', root!).forEach((c) => {
            c.checked = selected.has(c.value);
            c.closest('[data-row]')?.classList.toggle('is-selected', c.checked);
        });
        syncBulk();
    }
    root.addEventListener('change', (e) => {
        const el = e.target as HTMLInputElement;
        if (el.matches('[data-check]')) {
            el.checked ? selected.add(el.value) : selected.delete(el.value);
            el.closest('[data-row]')?.classList.toggle('is-selected', el.checked);
            syncBulk();
        } else if (el.matches('[data-check-all]')) {
            $$<HTMLInputElement>('[data-check]', el.closest('table') ?? root!).forEach((c) => {
                c.checked = el.checked;
                el.checked ? selected.add(c.value) : selected.delete(c.value);
                c.closest('[data-row]')?.classList.toggle('is-selected', el.checked);
            });
            syncBulk();
        } else if (el.matches('[data-bulk-action]')) {
            const action = (el as unknown as HTMLSelectElement).value;
            const form = el.closest('form')!;
            $$('[data-bulk-for]', form).forEach((f) => (f.hidden = f.dataset.bulkFor !== action));
            const apply = $<HTMLButtonElement>('[data-bulk-apply]', form)!;
            apply.hidden = action === '';
            apply.value = action;
            $<HTMLElement>(`[data-bulk-for="${action}"] input, [data-bulk-for="${action}"] select`, form)?.focus();
        }
    });
    root.addEventListener('click', (e) => {
        if (!(e.target as Element).closest('[data-bulk-clear]')) return;
        selected.clear();
        restoreSelection();
    });

    // ---------- keyboard ----------
    const rows = (): HTMLElement[] => $$('[data-row]', root!);
    function setActive(row: HTMLElement | null, scroll = true): void {
        active?.classList.remove('kb-active');
        active = row;
        if (row) {
            row.classList.add('kb-active');
            if (scroll) row.scrollIntoView({ block: 'nearest' });
        }
    }
    document.addEventListener('keydown', (e) => {
        const tgt = e.target instanceof Element ? e.target : document.body;
        if (e.key === 'Escape' && !backdrop.hidden) { closeDrawers(); return; }
        if (e.ctrlKey || e.metaKey || e.altKey || tgt.closest('input, textarea, select, [contenteditable], dialog[open]')) return;
        const list = rows();
        const i = active ? list.indexOf(active) : -1;
        switch (e.key) {
            case '/': e.preventDefault(); $<HTMLInputElement>('[data-live-search]', root!)?.focus(); break;
            case 'j': case 'ArrowDown': if (e.key === 'ArrowDown' && i < 0) return; e.preventDefault(); setActive(list[Math.min(list.length - 1, i + 1)] ?? null); break;
            case 'k': case 'ArrowUp': if (e.key === 'ArrowUp' && i < 0) return; e.preventDefault(); setActive(list[Math.max(0, i - 1)] ?? null); break;
            case 'Enter': if (active) location.href = active.dataset.url!; break;
            case 'p': case ' ': if (active) { e.preventDefault(); void preview(active); } break;
            case 'x': if (active) { const c = $<HTMLInputElement>('[data-check]', active); if (c) { c.click(); } } break;
        }
    });

    // Phones get cards: wide tables are hard to read there (not saved as a preference).
    const view = $<HTMLElement>('[data-grid-bulk]', root)?.dataset.view;
    if (view === 'table' && window.matchMedia('(max-width: 640px)').matches) {
        transientView = 'cards';
        void load(new URLSearchParams(query), { toolbar: true });
    }
    syncQueryLinks();
    syncSticky();
}
