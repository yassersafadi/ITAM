import { $$ } from './util.js';

interface QrCode {
    addData(data: string): void;
    make(): void;
    createSvgTag(opts: { cellSize?: number; margin?: number; scalable?: boolean }): string;
}
type QrFactory = (typeNumber: number, errorCorrectionLevel: 'L' | 'M' | 'Q' | 'H') => QrCode;

/** Renders QR codes for printable asset labels using the bundled qrcode-generator library. */
export function initQrCodes(): void {
    const factory = (window as unknown as { qrcode?: QrFactory }).qrcode;
    const targets = $$<HTMLElement>('[data-qr]');
    if (!factory || !targets.length) return;
    for (const el of targets) {
        const qr = factory(0, 'M');
        qr.addData(el.dataset.qr!);
        qr.make();
        el.innerHTML = qr.createSvgTag({ margin: 0, scalable: true });
    }
}
