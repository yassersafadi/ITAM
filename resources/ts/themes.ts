/**
 * Identity page: theme builder with a live preview and readability checks, theme import, logo previews,
 * the sign-in page mock-up and the e-mail header preview. The colour maths mirror the server's Themes class
 * so what you see in the preview is what the platform will use.
 */
import { $, $$, t } from './util.js';

type Mode = 'light' | 'dark';
type Colors = Record<'primary' | 'accent' | 'sidebar' | 'bg' | 'surface' | 'text', string>;
interface Theme { name: string; light: Colors; dark: Colors; font: string; shape: string; density: string }

const ROLES = ['primary', 'accent', 'sidebar', 'bg', 'surface', 'text'] as const;
const FONTS: Record<string, [string | null, string]> = {
    'alexandria': ['Alexandria', '"Alexandria", system-ui, sans-serif'],
    'ibm-plex': ['IBM Plex Sans Arabic', '"IBM Plex Sans Arabic", system-ui, sans-serif'],
    'tajawal': ['Tajawal', '"Tajawal", system-ui, sans-serif'],
    'cairo': ['Cairo', '"Cairo", system-ui, sans-serif'],
    'almarai': ['Almarai', '"Almarai", system-ui, sans-serif'],
    'readex': ['Readex Pro', '"Readex Pro", system-ui, sans-serif'],
    'noto-kufi': ['Noto Kufi Arabic', '"Noto Kufi Arabic", system-ui, sans-serif'],
    'system': [null, 'system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif'],
};
const SHAPES: Record<string, [string, string, string]> = { sharp: ['6px', '4px', '5px'], medium: ['14px', '9px', '10px'], round: ['22px', '14px', '999px'] };
const DENSITIES: Record<string, [string, string]> = { comfortable: ['38px', '18px'], compact: ['32px', '13px'] };

function luminance(hex: string): number {
    const lin = (i: number) => { const v = parseInt(hex.slice(i, i + 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
    return 0.2126 * lin(1) + 0.7152 * lin(3) + 0.0722 * lin(5);
}

function contrast(a: string, b: string): number {
    const [x, y] = [luminance(a), luminance(b)];
    return Math.round(((Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)) * 100) / 100;
}

/** Text colour on a filled button: whichever of white or dark ink reads better (same rule as Themes::ink()). */
function ink(bg: string): string {
    return contrast('#ffffff', bg) >= contrast('#0f1b33', bg) ? '#ffffff' : '#0f1b33';
}

function sidebarVars(bg: string): Record<string, string> {
    const light = luminance(bg) > 0.4;
    const ink = light ? '15, 27, 51' : '255, 255, 255';
    return {
        '--sidebar-bg': bg, '--sidebar-fg': light ? '#0f1b33' : '#ffffff', '--sidebar-text': light ? '#3d4b63' : `rgba(${ink}, .72)`,
        '--sidebar-muted': light ? '#7a8aa3' : `rgba(${ink}, .45)`, '--sidebar-active': `rgba(${ink}, ${light ? '.07' : '.09'})`,
        '--sidebar-line': `rgba(${ink}, .08)`,
    };
}

/** Same derivation as Themes::vars() + shapeVars() on the server. */
function themeVars(theme: Theme, mode: Mode): Record<string, string> {
    const c = theme[mode];
    const dark = mode === 'dark';
    const mix = (a: string, b: string, pct: number) => `color-mix(in srgb, ${a}, ${b} ${pct}%)`;
    const [r, rs, rc] = SHAPES[theme.shape] ?? SHAPES.medium;
    const [h, pad] = DENSITIES[theme.density] ?? DENSITIES.comfortable;
    return {
        '--primary': c.primary, '--brand-navy': c.sidebar, '--primary-ink': ink(c.primary),
        '--primary-soft': mix(c.surface, c.primary, dark ? 18 : 12), '--grad': `linear-gradient(135deg, ${c.primary} 0%, ${c.accent} 100%)`,
        '--bg': c.bg, '--surface': c.surface, '--surface-2': mix(c.surface, c.bg, 60), '--surface-3': mix(c.bg, c.text, dark ? 7 : 4),
        '--border': mix(c.bg, c.text, dark ? 13 : 9), '--border-strong': mix(c.bg, c.text, dark ? 20 : 16),
        '--text': c.text, '--text-2': mix(c.text, c.surface, 30), '--muted': mix(c.text, c.surface, 48),
        '--radius': r, '--radius-sm': rs, '--radius-ctl': rc, '--ctl-h': h, '--card-pad': pad,
        '--font': (FONTS[theme.font] ?? FONTS.alexandria)[1],
        ...sidebarVars(c.sidebar),
    };
}

/** Load a Google font on demand so the preview shows it before the theme is saved. */
function loadFont(key: string): void {
    const family = FONTS[key]?.[0];
    if (!family || document.querySelector(`link[data-font="${key}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.dataset.font = key;
    link.href = `https://fonts.googleapis.com/css2?family=${family.replace(/ /g, '+')}:wght@400;500;600;700&display=swap`;
    document.head.appendChild(link);
}

function initBuilder(): void {
    const box = $<HTMLElement>('[data-theme-builder]');
    if (!box) return;
    const preview = $<HTMLElement>('[data-tb-preview]', box)!;
    let mode: Mode = 'light';
    let theme: Theme;

    const read = (): Theme => {
        const pick = (m: Mode) => Object.fromEntries(ROLES.map((r) => [r, $<HTMLInputElement>(`[data-tb-color="${m}.${r}"]`, box)!.value])) as Colors;
        return {
            name: $<HTMLInputElement>('[data-tb-name]', box)!.value, light: pick('light'), dark: pick('dark'),
            font: $<HTMLSelectElement>('[data-tb-font]', box)!.value, shape: $<HTMLSelectElement>('[data-tb-shape]', box)!.value,
            density: $<HTMLSelectElement>('[data-tb-density]', box)!.value,
        };
    };

    const render = (): void => {
        theme = read();
        Object.entries(themeVars(theme, mode)).forEach(([k, v]) => preview.style.setProperty(k, v));
        preview.classList.toggle('is-dark', mode === 'dark');
        ROLES.forEach((r) => (['light', 'dark'] as Mode[]).forEach((m) => {
            const hex = $(`[data-tb-hex="${m}.${r}"]`, box);
            if (hex) hex.textContent = theme[m][r];
        }));
        loadFont(theme.font);
        // Readability: body text 4.5:1, buttons and links 3:1 (WCAG AA).
        const checks = $('[data-tb-checks]', box)!;
        checks.innerHTML = '';
        (['light', 'dark'] as Mode[]).forEach((m) => {
            const c = theme[m];
                        ([['text', contrast(c.text, c.surface), 4.5], ['button', contrast(ink(c.primary), c.primary), 3], ['link', contrast(c.primary, c.surface), 3]] as [string, number, number][])
                .forEach(([key, ratio, min]) => {
                    const ok = ratio >= min;
                    const b = document.createElement('span');
                    b.className = `badge badge-${ok ? 'success' : 'warning'}`;
                    b.textContent = `${t('theme.' + m)} · ${t('theme.check_' + key)} ${ratio}:1 ${ok ? '✓' : '⚠'}`;
                    b.title = ok ? t('theme.check_ok') : t('theme.check_low', { min: String(min) });
                    checks.appendChild(b);
                });
        });
    };

    const open = (data: Theme, id: string, title: string): void => {
        $<HTMLInputElement>('[data-tb-id]', box)!.value = id;
        $<HTMLInputElement>('[data-tb-name]', box)!.value = data.name || '';
        (['light', 'dark'] as Mode[]).forEach((m) => ROLES.forEach((r) => { $<HTMLInputElement>(`[data-tb-color="${m}.${r}"]`, box)!.value = data[m][r]; }));
        $<HTMLSelectElement>('[data-tb-font]', box)!.value = data.font;
        $<HTMLSelectElement>('[data-tb-shape]', box)!.value = data.shape;
        $<HTMLSelectElement>('[data-tb-density]', box)!.value = data.density;
        $('[data-tb-title]', box)!.textContent = title;
        box.hidden = false;
        render();
        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
        $<HTMLInputElement>('[data-tb-name]', box)!.focus({ preventScroll: true });
    };

    $$<HTMLButtonElement>('[data-theme-new], [data-theme-edit]').forEach((btn) => btn.addEventListener('click', () => {
        const data = JSON.parse(btn.dataset.theme || '{}') as Theme;
        const id = btn.dataset.themeEdit ?? '';
        if (btn.hasAttribute('data-theme-new')) data.name = '';
        open(data, id, t(id ? 'theme.edit' : 'theme.new'));
    }));
    $('[data-tb-cancel]', box)?.addEventListener('click', () => { box.hidden = true; });

    $$<HTMLButtonElement>('[data-tb-mode]', box).forEach((b) => b.addEventListener('click', () => {
        mode = b.dataset.tbMode as Mode;
        $$<HTMLButtonElement>('[data-tb-mode]', box).forEach((x) => x.classList.toggle('active', x === b));
        $$<HTMLElement>('[data-tb-colors]', box).forEach((el) => { el.hidden = el.dataset.tbColors !== mode; });
        const label = $('[data-tb-preview-mode]', box);
        if (label) label.textContent = b.textContent?.trim() ?? '';
        render();
    }));
    box.addEventListener('input', render);
    box.addEventListener('change', render);
}

function initLoginMock(): void {
    const mini = $<HTMLElement>('[data-login-mini]');
    if (!mini) return;
    const layout = $<HTMLSelectElement>('[name="login__layout"]');
    const bg = $<HTMLSelectElement>('[name="login__background"]');
    const overlay = $<HTMLInputElement>('[name="login__overlay"]');
    const update = () => {
        if (layout) mini.dataset.layout = layout.value;
        if (bg) mini.dataset.bg = bg.value;
        if (overlay) mini.style.setProperty('--mini-overlay', String(Number(overlay.value || 0) / 100));
    };
    [layout, bg, overlay].forEach((el) => el?.addEventListener('input', update));
    [layout, bg].forEach((el) => el?.addEventListener('change', update));
    $<HTMLInputElement>('[data-logo-input="login_bg"]')?.addEventListener('change', (e) => {
        const file = (e.target as HTMLInputElement).files?.[0];
        if (file) mini.style.setProperty('--mini-image', `url("${URL.createObjectURL(file)}")`);
    });
}

export function initThemes(): void {
    initBuilder();
    initLoginMock();

    // Importing a theme file submits straight away.
    $<HTMLInputElement>('[data-theme-import] input[type="file"]')?.addEventListener('change', (e) => {
        if ((e.target as HTMLInputElement).files?.length) (e.target as HTMLInputElement).form?.submit();
    });

    // Plain or branded e-mail header.
    const branded = $<HTMLInputElement>('[name="mail__branded"]');
    const mail = $('.mail-preview');
    const syncMail = () => mail?.classList.toggle('plain', !!branded && !branded.checked);
    branded?.addEventListener('change', syncMail);
    syncMail();

    // Show chosen logo files before saving.
    $$<HTMLInputElement>('[data-logo-input]').forEach((input) => input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;
        const src = URL.createObjectURL(file);
        $$<HTMLImageElement>(`[data-logo-preview="${input.dataset.logoInput}"]`).forEach((img) => { img.src = src; });
    }));
}
