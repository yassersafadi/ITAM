import { $, $$, cfg, getJson } from './util.js';

/** Theme: system → light → dark cycle, remembered per browser. */
function initTheme(): void {
    $$('[data-action="toggle-theme"]').forEach((btn) =>
        btn.addEventListener('click', () => {
            const root = document.documentElement;
            const current = root.getAttribute('data-theme')
                ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('itam-theme', next); } catch { /* storage unavailable */ }
            document.dispatchEvent(new CustomEvent('itam:theme'));
        }),
    );
    const select = $<HTMLSelectElement>('[data-theme-select]');
    select?.addEventListener('change', () => {
        try {
            if (select.value === 'system') localStorage.removeItem('itam-theme');
            else localStorage.setItem('itam-theme', select.value);
        } catch { /* ignore */ }
    });
}

function initSidebar(): void {
    $$('[data-action="toggle-sidebar"]').forEach((btn) =>
        btn.addEventListener('click', () => {
            const root = document.documentElement;
            if (window.innerWidth <= 860) {
                root.classList.toggle('sidebar-open');
                return;
            }
            root.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem('itam-sidebar', root.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open'); } catch { /* ignore */ }
        }),
    );
    document.addEventListener('click', (e) => {
        const root = document.documentElement;
        if (root.classList.contains('sidebar-open') && !(e.target as Element).closest('.sidebar, [data-action="toggle-sidebar"]')) {
            root.classList.remove('sidebar-open');
        }
    });
}

function initDropdowns(): void {
    document.addEventListener('click', (e) => {
        // Menus with controls (e.g. column choice) stay open while they are used.
        if ((e.target as Element).closest('.dropdown-menu[data-cols]')) return;
        const trigger = (e.target as Element).closest('[data-dropdown]');
        $$('.dropdown.open').forEach((d) => { if (!trigger || d !== trigger.parentElement) d.classList.remove('open'); });
        if (trigger) trigger.parentElement?.classList.toggle('open');
    });
}

function initModals(): void {
    document.addEventListener('click', (e) => {
        const target = e.target as Element;
        const opener = target.closest<HTMLElement>('[data-modal-open]');
        if (opener) {
            const dlg = $<HTMLDialogElement>('#modal-' + opener.dataset.modalOpen);
            // One shared dialog can serve many rows: the opener supplies the form action and a caption.
            const form = dlg?.querySelector('form');
            if (form && opener.dataset.formAction) form.action = opener.dataset.formAction;
            const assetField = form?.querySelector<HTMLInputElement>('[name=asset_id][type=hidden]');
            if (assetField && opener.dataset.asset) assetField.value = opener.dataset.asset;
            const caption = dlg?.querySelector('[data-modal-caption]');
            if (caption && opener.dataset.caption) caption.textContent = opener.dataset.caption;
            // data-fill presets named fields, e.g. the device a report is about or the catalogue item being ordered.
            if (form && opener.dataset.fill) {
                Object.entries(JSON.parse(opener.dataset.fill) as Record<string, string>).forEach(([name, value]) => {
                    const field = form.elements.namedItem(name);
                    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) field.value = value;
                });
            }
            dlg?.showModal();
            dlg?.querySelector<HTMLElement>('input:not([type=hidden]), select')?.focus();
        }
        if (target.closest('[data-modal-close]')) target.closest('dialog')?.close();
        if (target instanceof HTMLDialogElement) target.close(); // backdrop click
    });
}

function initTabs(): void {
    $$('[data-tabs]').forEach((root) => {
        const activate = (name: string) => {
            $$('[data-tab]', root).forEach((b) => b.classList.toggle('active', b.dataset.tab === name));
            $$('[data-panel]', root).forEach((p) => p.classList.toggle('active', p.dataset.panel === name));
            document.dispatchEvent(new CustomEvent('itam:tab', { detail: name }));
        };
        $$('[data-tab]', root).forEach((b) => b.addEventListener('click', () => {
            activate(b.dataset.tab!);
            history.replaceState(null, '', '#' + b.dataset.tab);
        }));
        const hash = location.hash.slice(1);
        if (hash && $(`[data-tab="${hash}"]`, root)) {
            activate(hash);
            // The panel was hidden when the browser tried to jump to the anchor, so bring the tabs into view.
            setTimeout(() => root.scrollIntoView({ block: 'start' }), 60);
        }
        // A link to "#name" elsewhere on the page opens that tab.
        window.addEventListener('hashchange', () => {
            const name = location.hash.slice(1);
            if (name && $(`[data-tab="${name}"]`, root)) {
                activate(name);
                root.scrollIntoView({ block: 'start' });
            }
        });
    });
}

/** Settings › Notifications: show the SMTP fields or the Microsoft 365 (Graph) fields, after the chosen sending method. */
function initMailTransport(): void {
    const select = $<HTMLSelectElement>('select[name="mail__transport"]');
    if (!select) return;
    const groups: Record<string, string[]> = {
        smtp: ['host', 'port', 'encryption', 'username', 'password'],
        graph: ['graph_tenant', 'graph_client_id', 'graph_secret'],
    };
    const apply = () => Object.entries(groups).forEach(([mode, keys]) => keys.forEach((k) => {
        const field = $(`[name="mail__${k}"]`)?.closest<HTMLElement>('.field');
        if (field) field.hidden = select.value !== mode;
    }));
    select.addEventListener('change', apply);
    apply();
}

function initForms(): void {
    initMailTransport();
    // Confirmation on forms and individual submit buttons
    document.addEventListener('submit', (e) => {
        const form = e.target as HTMLFormElement;
        const submitter = (e as SubmitEvent).submitter as HTMLElement | null;
        const msg = submitter?.dataset.confirmClick ?? form.dataset.confirm;
        if (msg && !confirm(msg)) e.preventDefault();
    });

    // Clickable table rows
    document.addEventListener('click', (e) => {
        const target = e.target as Element;
        if (target.closest('a, button, input, select, label, textarea, form')) return;
        const row = target.closest<HTMLElement>('tr[data-href]');
        if (row?.dataset.href) location.href = row.dataset.href;
    });

    // Messages: close on demand; success and notes fade out by themselves (3 s, 5 s), errors stay until closed.
    const leave = (f: Element) => { f.classList.add('leaving'); setTimeout(() => f.remove(), 300); };
    document.addEventListener('click', (e) => {
        const btn = (e.target as Element).closest('[data-action="dismiss"]');
        const f = btn?.closest('.flash');
        if (f) leave(f);
    });
    $$('.toasts .flash-success, .toasts .flash-info').forEach((f) => setTimeout(() => leave(f), 3000));
    $$('.toasts .flash-warning').forEach((f) => setTimeout(() => leave(f), 5000));

    // Bulk selection
    const bulk = $<HTMLFormElement>('[data-bulk]');
    if (bulk) {
        const bar = $('[data-bulkbar]', bulk)!;
        const count = $('[data-bulk-count]', bulk)!;
        const boxes = () => $$<HTMLInputElement>('[data-check]', bulk);
        const sync = () => {
            const n = boxes().filter((b) => b.checked).length;
            bar.hidden = n === 0;
            count.textContent = String(n);
        };
        $<HTMLInputElement>('[data-check-all]', bulk)?.addEventListener('change', (e) => {
            const on = (e.target as HTMLInputElement).checked;
            boxes().forEach((b) => (b.checked = on));
            sync();
        });
        bulk.addEventListener('change', (e) => { if ((e.target as Element).matches('[data-check]')) sync(); });
        bulk.addEventListener('submit', (e) => {
            if ((e.submitter as HTMLButtonElement | null)?.value === 'labels') bulk.target = '_blank';
            else bulk.removeAttribute('target');
        });
    }

    // Long-running actions (e.g. a network scan): show progress and prevent double submits.
    $$<HTMLButtonElement>('button[data-busy-text]').forEach((btn) =>
        btn.form?.addEventListener('submit', () => {
            btn.disabled = true;
            btn.textContent = btn.dataset.busyText ?? '…';
        }),
    );

    // "Select all" in tables outside the asset bulk bar (e.g. discovered devices).
    $$<HTMLInputElement>('[data-check-all]').forEach((all) => {
        if (all.closest('[data-bulk]')) return;
        all.addEventListener('change', () => {
            $$<HTMLInputElement>('[data-check]', all.closest('table') ?? document).forEach((b) => (b.checked = all.checked));
        });
    });

    // Client-side table filter
    $$<HTMLInputElement>('[data-table-filter]').forEach((input) => {
        const table = $(input.dataset.tableFilter!);
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            $$('tbody tr', table!).forEach((tr) => { tr.hidden = q !== '' && !tr.textContent!.toLowerCase().includes(q); });
        });
    });

    // Section pickers shown on narrow screens instead of a side menu (e.g. Settings): go to the chosen page.
    $$<HTMLSelectElement>('[data-nav-select]').forEach((select) => select.addEventListener('change', () => {
        if (select.value) location.href = select.value;
    }));
    // Settings home: filter the section cards as you type (groups with no match are hidden).
    const settingsFilter = $<HTMLInputElement>('[data-settings-filter]');
    settingsFilter?.addEventListener('input', () => {
        const q = settingsFilter.value.trim().toLowerCase();
        let shown = 0;
        $$<HTMLElement>('[data-settings-group]').forEach((group) => {
            let inGroup = 0;
            $$<HTMLElement>('[data-settings-card]', group).forEach((card) => {
                const match = q === '' || (card.dataset.search ?? '').includes(q);
                card.hidden = !match;
                inGroup += match ? 1 : 0;
            });
            group.hidden = inGroup === 0;
            shown += inGroup;
        });
        const empty = $('[data-settings-empty]');
        if (empty) empty.hidden = shown > 0;
    });

    // Warranty shortcuts: acquired date + N months
    $$<HTMLButtonElement>('[data-warranty-plus]').forEach((btn) =>
        btn.addEventListener('click', () => {
            const acquired = $<HTMLInputElement>('[data-acquired]');
            const warranty = $<HTMLInputElement>('[data-warranty]');
            if (!warranty) return;
            const base = acquired?.value ? new Date(acquired.value) : new Date();
            base.setMonth(base.getMonth() + Number(btn.dataset.warrantyPlus));
            warranty.value = base.toISOString().slice(0, 10);
        }),
    );

    // Book value follows the asset value, in-service date and category lifespan (straight line, never below the residual share).
    const book = $<HTMLInputElement>('[data-book-value]');
    if (book) {
        const lifespans: Record<string, number | null> = JSON.parse(book.dataset.lifespans || '{}');
        const residual = Number(book.dataset.residual || 0.1);
        const method = book.dataset.method || 'straight_line';
        const rate = Number(book.dataset.rate || 0);
        const fmt = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const update = (): void => {
            const value = Number($<HTMLInputElement>('[data-asset-value]')?.value || 0);
            if (!(value > 0)) { book.value = ''; return; }
            const acquired = $<HTMLInputElement>('[data-acquired]')?.value;
            const months = Number(lifespans[$<HTMLSelectElement>('[data-category]')?.value || ''] || 0);
            // Same rules as Depreciation::forAsset(): straight line, or declining balance (0 = double the straight-line rate).
            const elapsed = acquired && months ? Math.max(0, Math.floor((Date.now() - new Date(acquired).getTime()) / 86400000)) / 30.44 : 0;
            const left = method === 'declining_balance' && months
                ? Math.pow(1 - Math.min(0.99, rate > 0 ? rate : 24 / months), elapsed / 12)
                : 1 - (months ? elapsed / months : 0);
            book.value = fmt.format(Math.round(value * Math.max(residual, left) * 100) / 100);
        };
        ['[data-asset-value]', '[data-acquired]', '[data-category]'].forEach((sel) => {
            $(sel)?.addEventListener('input', update);
            $(sel)?.addEventListener('change', update);
        });
    }

    // Reveal license key / copy to clipboard
    $$<HTMLElement>('[data-reveal]').forEach((el) => el.addEventListener('click', () => {
        if (el.dataset.reveal) el.textContent = el.dataset.reveal;
    }));
    $$<HTMLElement>('[data-copy]').forEach((el) => el.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(el.textContent!.trim());
            el.classList.add('copied');
        } catch { /* clipboard not permitted */ }
    }));

    // Drop zone file name
    $$('[data-dropzone]').forEach((zone) => {
        const input = $<HTMLInputElement>('input[type=file]', zone)!;
        const label = $('[data-filename]', zone);
        input.addEventListener('change', () => { if (label && input.files?.[0]) label.textContent = input.files[0].name; });
        ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, () => zone.classList.add('drag')));
        ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, () => zone.classList.remove('drag')));
    });
}

/** Poll the unread notification count every 2 minutes. */
function initNotifications(): void {
    if (!cfg().csrf) return;
    const bell = $('.bell');
    if (!bell) return;
    const refresh = async () => {
        try {
            const { unread } = await getJson<{ unread: number }>('api/notifications');
            let dot = $('[data-unread]', bell);
            if (!unread) { dot?.remove(); return; }
            if (!dot) {
                dot = document.createElement('span');
                dot.className = 'dot';
                dot.setAttribute('data-unread', '');
                bell.appendChild(dot);
            }
            dot.textContent = unread > 9 ? '9+' : String(unread);
        } catch { /* offline or session expired */ }
    };
    setInterval(refresh, 120_000);
}

/** Reject files over the server limit before uploading, since PHP would drop the whole request. */
function initUploadLimits(): void {
    $$<HTMLFormElement>('[data-upload-form]').forEach((form) => form.addEventListener('submit', (e) => {
        const file = $<HTMLInputElement>('input[type=file]', form)?.files?.[0];
        if (file && file.size > Number(form.dataset.maxBytes)) {
            e.preventDefault();
            alert(form.dataset.tooBig);
        }
    }));
}

/** Disposal form: sanitisation evidence fields only matter when a sanitisation level is chosen. */
function initDisposeForm(): void {
    const form = $('[data-dispose-form]');
    if (!form) return;
    const sync = () => {
        const level = $<HTMLInputElement>('[data-sanitization]:checked', form)?.value ?? 'not_applicable';
        $$('[data-needs-sanitization]', form).forEach((el) => {
            el.hidden = level === 'not_applicable';
            const input = $<HTMLInputElement>('input', el);
            if (input && input.name === 'verified_by') input.required = level !== 'not_applicable';
        });
    };
    $$('[data-sanitization]', form).forEach((r) => r.addEventListener('change', sync));
    sync();
}

/** Asset form: show only the custom fields of the selected category (hidden ones are disabled so they are not sent). */
function initCustomFields(): void {
    const section = $('[data-cf-section]');
    const select = $<HTMLSelectElement>('select[data-category]');
    if (!section || !select) return;
    const sync = () => {
        let any = false;
        $$('[data-cf-category]', section).forEach((group) => {
            const on = group.dataset.cfCategory === select.value;
            any ||= on;
            group.hidden = !on;
            $$<HTMLInputElement | HTMLSelectElement>('input, select', group).forEach((el) => { el.disabled = !on; });
        });
        const empty = $('[data-cf-empty]', section);
        if (empty) empty.hidden = any;
    };
    select.addEventListener('change', sync);
    sync();
}

export function initUi(): void {
    initCustomFields();
    initUploadLimits();
    initDisposeForm();
    initTheme();
    initSidebar();
    initDropdowns();
    initModals();
    initTabs();
    initForms();
    initNotifications();
}
