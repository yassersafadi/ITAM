/**
 * Searchable pick lists on the asset form: the manufacturer from the managed list, then the model from the chosen
 * manufacturer's models. The inputs stay plain text fields, so the label scan and the server read them as before.
 * Users who may edit the catalog can keep a value that is not listed (the server adds it on save); others pick from
 * the lists, except for the value the asset already had.
 */
import { $, t } from './util.js';

interface ModelEntry { m: string; name: string; category: number | null }
interface CatalogData { manufacturers: string[]; models: ModelEntry[]; canAdd: boolean }

const MAX = 60;
let seq = 0;

class Combo {
    readonly list: HTMLUListElement;
    private items: string[] = [];
    private active = -1;
    private readonly initial: string;

    constructor(readonly input: HTMLInputElement, private readonly source: () => string[], private readonly canAdd: boolean, private readonly onPick: (v: string) => void) {
        this.initial = input.value.trim().toLowerCase();
        const id = 'combo-' + ++seq;
        this.list = document.createElement('ul');
        this.list.className = 'combo-list';
        this.list.id = id;
        this.list.setAttribute('role', 'listbox');
        this.list.hidden = true;
        input.after(this.list);
        input.closest('.field')?.classList.add('combo');
        input.removeAttribute('list');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', id);
        input.autocomplete = 'off';

        input.addEventListener('focus', () => this.open());
        input.addEventListener('click', () => this.open());
        input.addEventListener('input', () => { this.open(); this.validate(); });
        input.addEventListener('blur', () => { this.close(); this.snap(); });
        input.addEventListener('change', () => this.validate());
        input.addEventListener('keydown', (e) => this.key(e));
        // pointerdown, not click: picking must happen before the input loses focus.
        this.list.addEventListener('pointerdown', (e) => {
            const li = (e.target as HTMLElement).closest<HTMLLIElement>('li[data-value]');
            if (!li) return;
            e.preventDefault();
            this.pick(li.dataset.value ?? '');
        });
        this.validate();
    }

    private matches(): string[] {
        const q = this.input.value.trim().toLowerCase();
        const all = this.source();
        const starts = all.filter((v) => v.toLowerCase().startsWith(q));
        const contains = all.filter((v) => !v.toLowerCase().startsWith(q) && v.toLowerCase().includes(q));
        return [...starts, ...contains].slice(0, MAX);
    }

    open(): void {
        const typed = this.input.value.trim();
        this.items = this.matches();
        const exact = this.items.some((v) => v.toLowerCase() === typed.toLowerCase());
        this.list.innerHTML = '';
        for (const v of this.items) {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.dataset.value = v;
            li.textContent = v;
            li.dir = 'auto';
            this.list.append(li);
        }
        if (this.canAdd && typed !== '' && !exact) {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.className = 'combo-add';
            li.dataset.value = typed;
            li.textContent = '+ ' + t('combo.add', { name: typed });
            this.list.append(li);
            this.items.push(typed);
        }
        if (!this.list.children.length) {
            const li = document.createElement('li');
            li.className = 'combo-empty';
            li.textContent = t(this.source().length ? 'combo.nothing' : 'combo.empty');
            this.list.append(li);
        }
        this.active = -1;
        this.list.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
    }

    close(): void {
        this.list.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
    }

    private key(e: KeyboardEvent): void {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (this.list.hidden) this.open();
            const n = this.items.length;
            if (!n) return;
            this.active = (this.active + (e.key === 'ArrowDown' ? 1 : n - 1)) % n;
            Array.from(this.list.children).forEach((li, i) => {
                li.classList.toggle('active', i === this.active);
                if (i === this.active) {
                    li.id = this.list.id + '-opt';
                    this.input.setAttribute('aria-activedescendant', li.id);
                    li.scrollIntoView({ block: 'nearest' });
                } else li.removeAttribute('id');
            });
        } else if (e.key === 'Enter' && !this.list.hidden && this.active >= 0) {
            e.preventDefault();
            this.pick(this.items[this.active]);
        } else if (e.key === 'Escape' && !this.list.hidden) {
            e.preventDefault();
            this.close();
        }
    }

    private pick(v: string): void {
        this.input.value = v;
        this.close();
        this.validate();
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
        this.onPick(v);
    }

    /** Typed with another case than the list: take the list's spelling. */
    private snap(): void {
        const typed = this.input.value.trim();
        const hit = this.source().find((v) => v.toLowerCase() === typed.toLowerCase());
        if (hit && hit !== this.input.value) {
            this.input.value = hit;
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
            this.onPick(hit);
        }
        this.validate();
    }

    known(): boolean {
        const typed = this.input.value.trim().toLowerCase();
        return typed === '' || this.source().some((v) => v.toLowerCase() === typed);
    }

    validate(): void {
        const typed = this.input.value.trim().toLowerCase();
        const ok = this.canAdd || this.known() || typed === this.initial;
        this.input.setCustomValidity(ok ? '' : t('combo.unknown'));
    }
}

export function initCatalogPickers(): void {
    const form = $<HTMLFormElement>('form[data-asset-form]');
    const holder = form ? $<HTMLElement>('[data-catalog]', form) : null;
    const man = form ? $<HTMLInputElement>('input[name="manufacturer"]', form) : null;
    const model = form ? $<HTMLInputElement>('input[name="model"]', form) : null;
    if (!form || !holder || !man || !model) return;
    let data: CatalogData;
    try {
        data = JSON.parse(holder.dataset.catalog ?? '');
    } catch {
        return;
    }
    const category = $<HTMLSelectElement>('select[data-category]', form);
    const modelsOf = (): ModelEntry[] => {
        const m = man.value.trim().toLowerCase();
        return m === '' ? [] : data.models.filter((x) => x.m.toLowerCase() === m);
    };
    let modelCombo: Combo | null = null;
    const syncModel = (): void => {
        model.placeholder = man.value.trim() === '' ? t('combo.pick_manufacturer') : '';
        modelCombo?.validate();
    };
    new Combo(man, () => data.manufacturers, data.canAdd, () => {
        // A model of another manufacturer no longer fits.
        const v = model.value.trim().toLowerCase();
        if (v !== '' && data.models.some((x) => x.name.toLowerCase() === v) && !modelsOf().some((x) => x.name.toLowerCase() === v)) {
            model.value = '';
        }
        syncModel();
    });
    modelCombo = new Combo(model, () => modelsOf().map((x) => x.name), data.canAdd, (v) => {
        // The model's category fills an empty category.
        const entry = modelsOf().find((x) => x.name === v);
        if (entry?.category && category && category.value === '') {
            category.value = String(entry.category);
            category.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    man.addEventListener('change', syncModel);
    syncModel();
}
