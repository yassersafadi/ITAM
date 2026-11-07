import { $, escapeHtml, postForm, t } from './util.js';
const TONE = { found: 'success', misplaced: 'warning', unexpected: 'danger', duplicate: 'neutral' };
function beep(ok) {
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.frequency.value = ok ? 880 : 320;
        gain.gain.value = 0.08;
        osc.connect(gain).connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + (ok ? 0.09 : 0.25));
        navigator.vibrate?.(ok ? 40 : [60, 40, 60]);
    }
    catch { /* audio unavailable */ }
}
/**
 * Live audit scanner: camera QR/barcode detection (BarcodeDetector API) with a manual / handheld-scanner
 * fallback. Each scan is reconciled on the server and reflected instantly in the page.
 */
export function initScanner() {
    const root = $('[data-scanner]');
    if (!root)
        return;
    const endpoint = root.dataset.scanner;
    const toast = $('[data-toast]', root);
    const manual = $('[data-manual]', root);
    const codeInput = $('[data-code]', root);
    const video = $('[data-video]', root);
    const list = $('[data-scan-list]');
    const recent = new Map();
    const bump = (key, delta) => {
        const el = $(`[data-count="${key}"]`);
        if (el)
            el.textContent = String(Math.max(0, Number(el.textContent) + delta));
    };
    const submit = async (code) => {
        code = code.trim();
        if (!code)
            return;
        const last = recent.get(code);
        if (last && Date.now() - last < 4000)
            return; // camera sees the same label repeatedly
        recent.set(code, Date.now());
        try {
            const res = await postForm(endpoint, { code });
            toast.hidden = false;
            toast.className = 'scan-toast ' + res.result;
            toast.textContent = res.message;
            beep(res.result === 'found');
            if (res.duplicate)
                return;
            bump(res.result, 1);
            if (res.asset) {
                const chip = document.querySelector(`[data-pending] [data-tag="${CSS.escape(res.asset.tag)}"]`);
                if (chip) {
                    chip.classList.add('found');
                    bump('pending', -1);
                }
            }
            if (list) {
                const tr = document.createElement('tr');
                tr.className = 'flash-row';
                tr.innerHTML = `<td>${res.asset ? `<a href="${escapeHtml(res.asset.url)}"><span class="mono">${escapeHtml(res.asset.tag)}</span> ${escapeHtml(res.asset.name)}</a>` : `<span class="mono">${escapeHtml(code)}</span>`}</td>
                    <td><span class="badge badge-${TONE[res.result]}">${escapeHtml(res.label ?? res.result)}</span></td><td>—</td><td>${escapeHtml(t('scanner.now'))}</td>`;
                list.prepend(tr);
            }
        }
        catch (err) {
            if (err instanceof TypeError) {
                recent.delete(code); // no connection: let the same label be scanned again
                toast.hidden = false;
                toast.className = 'scan-toast error';
                toast.textContent = t('scanner.online_only');
                beep(false);
                return;
            }
            const data = err.data;
            toast.hidden = false;
            toast.className = 'scan-toast error';
            toast.textContent = data?.message ?? t('scanner.error');
            beep(false);
        }
    };
    manual.addEventListener('submit', (e) => {
        e.preventDefault();
        submit(codeInput.value);
        codeInput.value = '';
        codeInput.focus();
    });
    $('[data-start-camera]', root)?.addEventListener('click', async () => {
        const Detector = window.BarcodeDetector;
        if (!Detector || !navigator.mediaDevices?.getUserMedia) {
            toast.hidden = false;
            toast.className = 'scan-toast error';
            toast.textContent = t('scanner.unsupported');
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = stream;
            await video.play();
            root.classList.add('live');
            const detector = new Detector({ formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'data_matrix'] });
            const loop = async () => {
                if (!video.srcObject)
                    return;
                try {
                    const codes = await detector.detect(video);
                    for (const c of codes)
                        await submit(c.rawValue);
                }
                catch { /* frame not ready */ }
                setTimeout(loop, 350);
            };
            loop();
        }
        catch {
            toast.hidden = false;
            toast.className = 'scan-toast error';
            toast.textContent = t('scanner.denied');
        }
    });
}
