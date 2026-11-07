/**
 * Employee portal as an installed app: registers the portal's own service worker and offers "Install the app".
 * Chrome, Edge and Android raise beforeinstallprompt; iPhone and iPad have no prompt, so they get the steps instead.
 */
import { $, $$ } from './util.js';

interface InstallPrompt extends Event { prompt(): Promise<void>; userChoice: Promise<{ outcome: string }> }

export function initPortalApp(): void {
    const manifest = $<HTMLLinkElement>('link[rel=manifest][data-portal-manifest]');
    if (!manifest) return;
    // A chosen photo shows its name on the button that picked it.
    $$<HTMLInputElement>('input[type=file][data-file-label]').forEach((input) => input.addEventListener('change', () => {
        const name = input.closest('label')?.querySelector('[data-file-name]');
        if (name && input.files?.[0]) name.textContent = input.files[0].name;
    }));
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(manifest.dataset.sw!, { scope: manifest.dataset.scope }).catch(() => { /* http on a LAN address, or not supported */ });
    }
    const standalone = matchMedia('(display-mode: standalone)').matches || (navigator as unknown as { standalone?: boolean }).standalone === true;
    if (standalone) return;
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const buttons = $$<HTMLElement>('[data-install]');
    let deferred: InstallPrompt | null = null;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferred = e as InstallPrompt;
        buttons.forEach((b) => { b.hidden = false; });
    });
    if (ios) buttons.forEach((b) => { b.hidden = false; });

    buttons.forEach((b) => b.addEventListener('click', async () => {
        if (deferred) {
            await deferred.prompt();
            if ((await deferred.userChoice).outcome === 'accepted') buttons.forEach((x) => { x.hidden = true; });
            deferred = null;
        } else if (ios) {
            $<HTMLDialogElement>('#modal-install-ios')?.showModal();
        }
    }));
    window.addEventListener('appinstalled', () => buttons.forEach((x) => { x.hidden = true; }));
}
