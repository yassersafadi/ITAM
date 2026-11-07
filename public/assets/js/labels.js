import { $$ } from './util.js';
/** Renders QR codes for printable asset labels using the bundled qrcode-generator library. */
export function initQrCodes() {
    const factory = window.qrcode;
    const targets = $$('[data-qr]');
    if (!factory || !targets.length)
        return;
    for (const el of targets) {
        const qr = factory(0, 'M');
        qr.addData(el.dataset.qr);
        qr.make();
        el.innerHTML = qr.createSvgTag({ margin: 0, scalable: true });
    }
}
