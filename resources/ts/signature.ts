import { $, $$, t } from './util.js';

/**
 * Handwritten signature pad: mouse, finger or stylus (pointer events), smoothed strokes,
 * sharp on high-DPI screens. The drawing is sent as a transparent PNG in a hidden field.
 */
class SignaturePad {
    private ctx: CanvasRenderingContext2D;
    private drawing = false;
    private points: { x: number; y: number }[] = [];
    private strokes = 0;

    constructor(private canvas: HTMLCanvasElement, private onChange: (empty: boolean) => void) {
        this.ctx = canvas.getContext('2d')!;
        this.resize();
        canvas.addEventListener('pointerdown', (e) => this.start(e));
        canvas.addEventListener('pointermove', (e) => this.move(e));
        ['pointerup', 'pointercancel', 'pointerleave'].forEach((ev) => canvas.addEventListener(ev, () => this.end()));
        // Resizing would distort the drawing, so only reset an empty pad.
        window.addEventListener('resize', () => { if (this.isEmpty()) this.resize(); });
    }

    isEmpty(): boolean {
        return this.strokes === 0;
    }

    clear(): void {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.strokes = 0;
        this.onChange(true);
    }

    toDataURL(): string {
        return this.canvas.toDataURL('image/png');
    }

    private resize(): void {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = this.canvas.getBoundingClientRect();
        this.canvas.width = Math.round(rect.width * ratio);
        this.canvas.height = Math.round(rect.height * ratio);
        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.strokeStyle = '#0b1b3f';
        this.ctx.lineWidth = 2.4;
    }

    private pos(e: PointerEvent) {
        const r = this.canvas.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    private start(e: PointerEvent): void {
        e.preventDefault();
        this.canvas.setPointerCapture(e.pointerId);
        this.drawing = true;
        this.points = [this.pos(e)];
        const p = this.points[0];
        this.ctx.beginPath();
        this.ctx.arc(p.x, p.y, 1.1, 0, Math.PI * 2);
        this.ctx.fillStyle = '#0b1b3f';
        this.ctx.fill();
    }

    private move(e: PointerEvent): void {
        if (!this.drawing) return;
        e.preventDefault();
        // Coalesced events give the full pen path between frames.
        const events = (e as PointerEvent & { getCoalescedEvents?: () => PointerEvent[] }).getCoalescedEvents?.() ?? [e];
        for (const ev of events) {
            this.points.push(this.pos(ev));
            const n = this.points.length;
            if (n === 2) {
                // Lead-in from the pen-down point to the first midpoint.
                const [a, b] = this.points;
                this.line(a, { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
                continue;
            }
            // Quadratic curve through midpoints keeps the line smooth.
            const [a, b, c] = [this.points[n - 3], this.points[n - 2], this.points[n - 1]];
            this.ctx.beginPath();
            this.ctx.moveTo((a.x + b.x) / 2, (a.y + b.y) / 2);
            this.ctx.quadraticCurveTo(b.x, b.y, (b.x + c.x) / 2, (b.y + c.y) / 2);
            this.ctx.stroke();
        }
    }

    private line(from: { x: number; y: number }, to: { x: number; y: number }): void {
        this.ctx.beginPath();
        this.ctx.moveTo(from.x, from.y);
        this.ctx.lineTo(to.x, to.y);
        this.ctx.stroke();
    }

    private end(): void {
        if (!this.drawing) return;
        this.drawing = false;
        // Lead-out from the last midpoint to where the pen lifted.
        const n = this.points.length;
        if (n >= 2) {
            const [a, b] = [this.points[n - 2], this.points[n - 1]];
            this.line({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }, b);
        }
        this.strokes++;
        this.onChange(false);
    }
}

export function initSignaturePads(): void {
    $$<HTMLFormElement>('form[data-sign-form]').forEach(initForm);
}

function initForm(form: HTMLFormElement): void {
    const pads: { pad: SignaturePad; input: HTMLInputElement; required: boolean }[] = [];
    $$<HTMLElement>('[data-signature]', form).forEach((box) => {
        const canvas = $<HTMLCanvasElement>('canvas', box)!;
        const input = $<HTMLInputElement>('input[type=hidden]', box)!;
        const pad = new SignaturePad(canvas, (empty) => box.classList.toggle('signed', !empty));
        $('[data-signature-clear]', box)?.addEventListener('click', () => pad.clear());
        pads.push({ pad, input, required: box.dataset.signature === 'required' });
    });
    form.addEventListener('submit', (e) => {
        for (const { pad, input, required } of pads) {
            if (required && pad.isEmpty()) {
                e.preventDefault();
                alert(t('custody.sign_first'));
                return;
            }
            input.value = pad.isEmpty() ? '' : pad.toDataURL();
        }
    });
}
