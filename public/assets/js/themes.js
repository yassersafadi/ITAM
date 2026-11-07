/**
 * Identity page: theme builder with a live preview and readability checks, theme import, logo previews,
 * the sign-in page mock-up and the e-mail header preview. The colour maths mirror the server's Themes class
 * so what you see in the preview is what the platform will use.
 */
import { $, $$, t } from './util.js';
const ROLES = ['primary', 'accent', 'sidebar', 'bg', 'surface', 'text'];
const FONTS = {
    'alexandria': ['Alexandria', '"Alexandria", system-ui, sans-serif'],
    'ibm-plex': ['IBM Plex Sans Arabic', '"IBM Plex Sans Arabic", system-ui, sans-serif'],
    'tajawal': ['Tajawal', '"Tajawal", system-ui, sans-serif'],
    'cairo': ['Cairo', '"Cairo", system-ui, sans-serif'],
    'almarai': ['Almarai', '"Almarai", system-ui, sans-serif'],
    'readex': ['Readex Pro', '"Readex Pro", system-ui, sans-serif'],
    'noto-kufi': ['Noto Kufi Arabic', '"Noto Kufi Arabic", system-ui, sans-serif'],
    'system': [null, 'system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif'],
};
const SHAPES = { sharp: ['6px', '4px', '5px'], medium: ['14px', '9px', '10px'], round: ['22px', '14px', '999px'] };
const DENSITIES = { comfortable: ['38px', '18px'], compact: ['32px', '13px'] };
function luminance(hex) {
    const lin = (i) => { const v = parseInt(hex.slice(i, i + 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
    return 0.2126 * lin(1) + 0.7152 * lin(3) + 0.0722 * lin(5);
}
function contrast(a, b) {
    const [x, y] = [luminance(a), luminance(b)];
    return Math.round(((Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)) * 100) / 100;
}
/** Text colour on a filled button: whichever of white or dark ink reads better (same rule as Themes::ink()). */
function ink(bg) {
    return contrast('#ffffff', bg) >= contrast('#0f1b33', bg) ? '#ffffff' : '#0f1b33';
}
function sidebarVars(bg) {
    const light = luminance(bg) > 0.4;
    const ink = light ? '15, 27, 51' : '255, 255, 255';
    return {
        '--sidebar-bg': bg, '--sidebar-fg': light ? '#0f1b33' : '#ffffff', '--sidebar-text': light ? '#3d4b63' : `rgba(${ink}, .72)`,
        '--sidebar-muted': light ? '#7a8aa3' : `rgba(${ink}, .45)`, '--sidebar-active': `rgba(${ink}, ${light ? '.07' : '.09'})`,
        '--sidebar-line': `rgba(${ink}, .08)`,
    };
}
/** Same derivation as Themes::vars() + shapeVars() on the server. */
function themeVars(theme, mode) {
    const c = theme[mode];
    const dark = mode === 'dark';
    const mix = (a, b, pct) => `color-mix(in srgb, ${a}, ${b} ${pct}%)`;
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
function loadFont(key) {
    const family = FONTS[key]?.[0];
    if (!family || document.querySelector(`link[data-font="${key}"]`))
        return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.dataset.font = key;
    link.href = `https://fonts.googleapis.com/css2?family=${family.replace(/ /g, '+')}:wght@400;500;600;700&display=swap`;
    document.head.appendChild(link);
}
function initBuilder() {
    const box = $('[data-theme-builder]');
    if (!box)
        return;
    const preview = $('[data-tb-preview]', box);
    let mode = 'light';
    let theme;
    const read = () => {
        const pick = (m) => Object.fromEntries(ROLES.map((r) => [r, $(`[data-tb-color="${m}.${r}"]`, box).value]));
        return {
            name: $('[data-tb-name]', box).value, light: pick('light'), dark: pick('dark'),
            font: $('[data-tb-font]', box).value, shape: $('[data-tb-shape]', box).value,
            density: $('[data-tb-density]', box).value,
        };
    };
    const render = () => {
        theme = read();
        Object.entries(themeVars(theme, mode)).forEach(([k, v]) => preview.style.setProperty(k, v));
        preview.classList.toggle('is-dark', mode === 'dark');
        ROLES.forEach((r) => ['light', 'dark'].forEach((m) => {
            const hex = $(`[data-tb-hex="${m}.${r}"]`, box);
            if (hex)
                hex.textContent = theme[m][r];
        }));
        loadFont(theme.font);
        // Readability: body text 4.5:1, buttons and links 3:1 (WCAG AA).
        const checks = $('[data-tb-checks]', box);
        checks.innerHTML = '';
        ['light', 'dark'].forEach((m) => {
            const c = theme[m];
            [['text', contrast(c.text, c.surface), 4.5], ['button', contrast(ink(c.primary), c.primary), 3], ['link', contrast(c.primary, c.surface), 3]]
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
    const open = (data, id, title) => {
        $('[data-tb-id]', box).value = id;
        $('[data-tb-name]', box).value = data.name || '';
        ['light', 'dark'].forEach((m) => ROLES.forEach((r) => { $(`[data-tb-color="${m}.${r}"]`, box).value = data[m][r]; }));
        $('[data-tb-font]', box).value = data.font;
        $('[data-tb-shape]', box).value = data.shape;
        $('[data-tb-density]', box).value = data.density;
        $('[data-tb-title]', box).textContent = title;
        box.hidden = false;
        render();
        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
        $('[data-tb-name]', box).focus({ preventScroll: true });
    };
    $$('[data-theme-new], [data-theme-edit]').forEach((btn) => btn.addEventListener('click', () => {
        const data = JSON.parse(btn.dataset.theme || '{}');
        const id = btn.dataset.themeEdit ?? '';
        if (btn.hasAttribute('data-theme-new'))
            data.name = '';
        open(data, id, t(id ? 'theme.edit' : 'theme.new'));
    }));
    $('[data-tb-cancel]', box)?.addEventListener('click', () => { box.hidden = true; });
    $$('[data-tb-mode]', box).forEach((b) => b.addEventListener('click', () => {
        mode = b.dataset.tbMode;
        $$('[data-tb-mode]', box).forEach((x) => x.classList.toggle('active', x === b));
        $$('[data-tb-colors]', box).forEach((el) => { el.hidden = el.dataset.tbColors !== mode; });
        const label = $('[data-tb-preview-mode]', box);
        if (label)
            label.textContent = b.textContent?.trim() ?? '';
        render();
    }));
    box.addEventListener('input', render);
    box.addEventListener('change', render);
}
function initLoginMock() {
    const mini = $('[data-login-mini]');
    if (!mini)
        return;
    const layout = $('[name="login__layout"]');
    const bg = $('[name="login__background"]');
    const overlay = $('[name="login__overlay"]');
    const update = () => {
        if (layout)
            mini.dataset.layout = layout.value;
        if (bg)
            mini.dataset.bg = bg.value;
        if (overlay)
            mini.style.setProperty('--mini-overlay', String(Number(overlay.value || 0) / 100));
    };
    [layout, bg, overlay].forEach((el) => el?.addEventListener('input', update));
    [layout, bg].forEach((el) => el?.addEventListener('change', update));
    $('[data-logo-input="login_bg"]')?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (file)
            mini.style.setProperty('--mini-image', `url("${URL.createObjectURL(file)}")`);
    });
}
export function initThemes() {
    initBuilder();
    initLoginMock();
    // Importing a theme file submits straight away.
    $('[data-theme-import] input[type="file"]')?.addEventListener('change', (e) => {
        if (e.target.files?.length)
            e.target.form?.submit();
    });
    // Plain or branded e-mail header.
    const branded = $('[name="mail__branded"]');
    const mail = $('.mail-preview');
    const syncMail = () => mail?.classList.toggle('plain', !!branded && !branded.checked);
    branded?.addEventListener('change', syncMail);
    syncMail();
    // Show chosen logo files before saving.
    $$('[data-logo-input]').forEach((input) => input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file)
            return;
        const src = URL.createObjectURL(file);
        $$(`[data-logo-preview="${input.dataset.logoInput}"]`).forEach((img) => { img.src = src; });
    }));
}
