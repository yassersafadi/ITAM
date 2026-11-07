import { $$, escapeHtml, isRtl } from './util.js';
const PALETTE = ['#1677e6', '#22c3a6', '#0ea5e9', '#6366f1', '#f59e0b', '#ec4899', '#14b8a6', '#8b5cf6', '#64748b', '#ef4444'];
const STATUS = {
    deployed: '#1677e6', in_stock: '#22c3a6', reserved: '#6366f1', in_repair: '#f59e0b',
    discovered: '#0ea5e9', retired: '#94a3b8', lost: '#ef4444',
};
const HEALTH = { h0: '#e0394a', h25: '#f06f2b', h50: '#d48806', h75: '#16a991', h90: '#12a37c' };
const color = (d, i, spec) => (spec.palette === 'health' && d.key ? HEALTH[d.key] : undefined) ?? (d.key ? STATUS[d.key] : undefined) ?? PALETTE[i % PALETTE.length];
const fmt = (n) => new Intl.NumberFormat(document.documentElement.lang === 'ar' ? 'ar-SA-u-nu-latn' : document.documentElement.lang).format(n);
function tooltip(el) {
    const tip = document.createElement('div');
    tip.className = 'chart-tip';
    tip.hidden = true;
    el.appendChild(tip);
    return (text, x = 0, y = 0) => {
        if (!text) {
            tip.hidden = true;
            return;
        }
        tip.textContent = text;
        tip.style.left = x + 'px';
        tip.style.top = y + 'px';
        tip.hidden = false;
    };
}
function bindTips(el) {
    const show = tooltip(el);
    el.addEventListener('mousemove', (e) => {
        const target = e.target.closest('[data-tip]');
        if (!target) {
            show(null);
            return;
        }
        const box = el.getBoundingClientRect();
        show(target.dataset.tip ?? '', e.clientX - box.left, e.clientY - box.top);
    });
    el.addEventListener('mouseleave', () => show(null));
}
function donut(el, spec) {
    const data = (spec.data ?? []).filter((d) => d.value > 0);
    const total = data.reduce((s, d) => s + d.value, 0) || 1;
    const R = 70, r = 48, C = 90;
    let angle = -Math.PI / 2;
    const arcs = data.map((d, i) => {
        const a = (d.value / total) * Math.PI * 2;
        const a0 = angle, a1 = angle + Math.max(a - 0.015, 0.001);
        angle += a;
        const large = a1 - a0 > Math.PI ? 1 : 0;
        const p = (rad, rr) => `${C + rr * Math.cos(rad)} ${C + rr * Math.sin(rad)}`;
        const path = data.length === 1
            ? `M ${C} ${C - R} A ${R} ${R} 0 1 1 ${C - 0.01} ${C - R} L ${C - 0.01} ${C - r} A ${r} ${r} 0 1 0 ${C} ${C - r} Z`
            : `M ${p(a0, R)} A ${R} ${R} 0 ${large} 1 ${p(a1, R)} L ${p(a1, r)} A ${r} ${r} 0 ${large} 0 ${p(a0, r)} Z`;
        return `<path class="bar" d="${path}" fill="${color(d, i, spec)}" data-tip="${escapeHtml(d.label)}: ${fmt(d.value)} (${Math.round((d.value / total) * 100)}%)"/>`;
    }).join('');
    el.innerHTML = `<div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
        <svg viewBox="0 0 180 180" style="max-width:180px;flex:none">${arcs}
            <text x="${C}" y="${C}" text-anchor="middle" dominant-baseline="central" font-size="22" font-weight="700" style="fill:var(--text)">${escapeHtml(spec.center ?? fmt(total))}</text>
        </svg>
        <div class="legend" style="flex-direction:column;flex:1;min-width:140px">${data.map((d, i) => {
        const label = `<i style="background:${color(d, i, spec)}"></i>${escapeHtml(d.label)} <b style="margin-inline-start:auto">${fmt(d.value)}</b>`;
        return spec.link && d.key ? `<a href="${escapeHtml(spec.link + d.key)}">${label}</a>` : `<span style="display:flex;gap:6px;align-items:center">${label}</span>`;
    }).join('')}</div></div>`;
}
function bar(el, spec) {
    let data = spec.data ?? [];
    if (isRtl())
        data = [...data].reverse();
    // Drawn at the width it is shown at, so text keeps its size instead of being stretched with the chart.
    const W = Math.max(360, Math.round(el.clientWidth || 360)), H = 200, pad = 12, base = H - 26;
    const max = Math.max(1, ...data.map((d) => d.value));
    const step = (W - pad) / Math.max(1, data.length);
    const bw = Math.min(40, step * 0.6);
    const narrow = step < 56;
    // Short form (12K) on narrow bars: the localised long form ("12 ألف") does not fit; the tooltip keeps the full value.
    const compact = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 });
    // Each chart gets its own gradient id: a shared id would resolve to a chart hidden in another tab.
    const gid = 'g-bar-' + Math.random().toString(36).slice(2, 8);
    const grid = [0.25, 0.5, 0.75, 1].map((f) => `<line x1="${pad}" x2="${W}" y1="${base - (base - 10) * f}" y2="${base - (base - 10) * f}" stroke="var(--border)" stroke-dasharray="3 4"/>`).join('');
    const bars = data.map((d, i) => {
        const h = ((base - 10) * d.value) / max;
        const x = pad + i * step + (step - bw) / 2;
        return `<rect class="bar" x="${x}" y="${base - h}" width="${bw}" height="${Math.max(h, 1)}" rx="5" fill="${spec.palette === 'health' ? color(d, i, spec) : `url(#${gid})`}" data-tip="${escapeHtml(d.label)}: ${fmt(d.value)}"/>
            ${d.value ? `<text x="${x + bw / 2}" y="${base - h - 6}" text-anchor="middle" font-size="11" font-weight="700" fill="var(--text)">${narrow ? compact.format(d.value) : fmt(d.value)}</text>` : ''}
            <text x="${x + bw / 2}" y="${H - 8}" text-anchor="middle" font-size="11" fill="var(--muted)">${escapeHtml(d.short ?? d.label)}</text>`;
    }).join('');
    el.innerHTML = `<svg viewBox="0 0 ${W} ${H}"><defs><linearGradient id="${gid}" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#1677e6"/><stop offset="1" stop-color="#22c3a6"/></linearGradient></defs>${grid}${bars}</svg>`;
}
function hbar(el, spec) {
    const data = (spec.data ?? []).slice(0, 10);
    const max = Math.max(1, ...data.map((d) => d.value));
    el.innerHTML = `<div style="display:flex;flex-direction:column;gap:9px">${data.map((d, i) => `
        <div data-tip="${escapeHtml(d.label)}: ${fmt(d.value)}" style="display:grid;grid-template-columns:minmax(80px,38%) 1fr 36px;gap:10px;align-items:center;font-size:10.5px">
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-2)">${escapeHtml(d.label)}</span>
            <span class="meter" style="margin:0;height:9px"><span style="width:${(d.value / max) * 100}%;background:${PALETTE[i % PALETTE.length]}"></span></span>
            <b style="text-align:end">${fmt(d.value)}${spec.unit ?? ''}</b>
        </div>`).join('')}</div>`;
}
function gauge(el, spec) {
    const v = Math.max(0, Math.min(100, spec.value ?? 0));
    const tone = v >= 75 ? 'var(--success)' : v >= 50 ? 'var(--warning)' : 'var(--danger)';
    const R = 64, C = 80, len = Math.PI * R;
    el.innerHTML = `<svg viewBox="0 0 160 100" style="max-width:220px;margin-inline:auto">
        <path d="M ${C - R} 86 A ${R} ${R} 0 0 1 ${C + R} 86" fill="none" stroke="var(--surface-3)" stroke-width="13" stroke-linecap="round"/>
        <path d="M ${C - R} 86 A ${R} ${R} 0 0 1 ${C + R} 86" fill="none" stroke="${tone}" stroke-width="13" stroke-linecap="round"
              stroke-dasharray="${len}" stroke-dashoffset="${len}" style="transition:stroke-dashoffset 1s cubic-bezier(.2,.8,.2,1)" data-arc/>
        <text x="${C}" y="78" text-anchor="middle" font-size="30" font-weight="800" style="fill:var(--text)">${Math.round(v)}</text>
        <text x="${C}" y="96" text-anchor="middle" font-size="9.5">${escapeHtml(spec.label ?? '')}</text></svg>`;
    requestAnimationFrame(() => {
        const arc = el.querySelector('[data-arc]');
        if (arc)
            arc.style.strokeDashoffset = String(len * (1 - v / 100));
    });
}
/** Multi-series line chart with points, a light area under the first series and a legend. */
function line(el, spec) {
    const labels = spec.labels ?? [];
    const series = spec.series ?? [];
    const rtl = isRtl();
    // Drawn at the width it is shown at, so text and dots keep their size instead of being stretched with the chart.
    const W = Math.max(320, Math.round(el.clientWidth || 420)), H = 220, padX = 34, top = 14, base = H - 28;
    const n = Math.max(1, labels.length - 1);
    const raw = Math.max(1, ...series.flatMap((s) => s.values.filter((v) => v !== null)));
    // Whole, evenly spaced steps (1, 2, 5, 10…): no repeated axis numbers on small counts.
    const step = [1, 2, 5].map((m) => m * 10 ** Math.floor(Math.log10(raw / 4 || 1))).concat([10 * 10 ** Math.floor(Math.log10(raw / 4 || 1))]).find((s) => raw / s <= 4) ?? 1;
    const max = Math.max(step, Math.ceil(raw / Math.max(1, step)) * Math.max(1, step));
    const x = (i) => { const p = padX + ((W - padX - 10) * i) / n; return rtl ? W - p + padX - 10 : p; };
    const y = (v) => base - ((base - top) * v) / max;
    const ticksY = [];
    for (let v = Math.max(1, step); v <= max; v += Math.max(1, step))
        ticksY.push(v);
    const grid = ticksY.map((v) => `<line x1="${rtl ? 10 : padX}" x2="${rtl ? W - padX : W - 10}" y1="${y(v)}" y2="${y(v)}" stroke="var(--border)" stroke-dasharray="3 4"/><text x="${rtl ? W - 4 : 4}" y="${y(v) + 4}" font-size="11" fill="var(--muted)" text-anchor="${rtl ? 'end' : 'start'}">${fmt(v)}</text>`).join('')
        + `<line x1="${rtl ? 10 : padX}" x2="${rtl ? W - padX : W - 10}" y1="${base}" y2="${base}" stroke="var(--border)"/>`;
    // As many date labels as fit (about one per 90 px), always including the last.
    const every = Math.max(1, Math.ceil(labels.length / Math.max(2, Math.floor(W / 90))));
    const ticks = labels.map((l, i) => (i % every === 0 || i === labels.length - 1) && !(i !== labels.length - 1 && labels.length - 1 - i < every) ? `<text x="${x(i)}" y="${H - 8}" text-anchor="${x(i) < 60 ? (rtl ? 'end' : 'start') : (x(i) > W - 60 ? (rtl ? 'start' : 'end') : 'middle')}" font-size="11" fill="var(--muted)">${escapeHtml(l)}</text>` : '').join('');
    const dense = labels.length > 20;
    const paths = series.map((s, si) => {
        const c = PALETTE[si % PALETTE.length];
        // A missing value (null) breaks the line: each run of values is drawn on its own.
        const runs = [[]];
        s.values.forEach((v, i) => { if (v === null)
            runs.push([]);
        else
            runs[runs.length - 1].push(`${x(i)},${y(v)}`); });
        const lines = runs.filter((r) => r.length).map((r) => `<polyline points="${r.join(' ')}" fill="none" stroke="${c}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>`).join('');
        const whole = !s.values.includes(null);
        const area = si === 0 && whole ? `<polygon points="${x(0)},${base} ${runs[0].join(' ')} ${x(s.values.length - 1)},${base}" fill="${c}" opacity=".08"/>` : '';
        // Dense series: no dots on the line, but an invisible hit area per point keeps the tooltip.
        const dots = s.values.map((v, i) => v === null ? '' : `<circle cx="${x(i)}" cy="${y(v)}" r="${dense ? 6 : 3}" fill="${dense ? 'transparent' : 'var(--surface)'}" stroke="${dense ? 'none' : c}" stroke-width="2" data-tip="${escapeHtml(s.label)} · ${escapeHtml(labels[i] ?? '')}: ${fmt(v)}"/>`).join('');
        return `${area}${lines}${dots}`;
    }).join('');
    const legend = series.map((s, si) => `<span style="display:inline-flex;gap:6px;align-items:center"><i style="background:${PALETTE[si % PALETTE.length]}"></i>${escapeHtml(s.label)}</span>`).join('');
    el.innerHTML = `<svg viewBox="0 0 ${W} ${H}" width="100%" style="max-height:${H}px">${grid}${paths}${ticks}</svg><div class="legend" style="gap:14px;margin-top:4px">${legend}</div>`;
}
export function initCharts() {
    $$('[data-chart]').forEach((el) => {
        try {
            const spec = JSON.parse(el.dataset.chart);
            ({ donut, bar, hbar, gauge, line })[spec.type]?.(el, spec);
            bindTips(el);
            // Bar and line charts are drawn at their width: draw again when it changes (a tab opened, the window resized).
            if ((spec.type === 'bar' || spec.type === 'line') && 'ResizeObserver' in window) {
                let drawn = el.clientWidth;
                new ResizeObserver(() => {
                    const w = el.clientWidth;
                    if (w > 0 && Math.abs(w - drawn) > 24) {
                        drawn = w;
                        ({ bar, line })[spec.type](el, spec);
                    }
                }).observe(el);
            }
        }
        catch (err) {
            console.error('chart', err);
        }
    });
}
