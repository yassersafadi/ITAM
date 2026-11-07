/**
 * ITAM — front-end entry point.
 * Progressive enhancement only: every page works without JavaScript; these modules add the
 * quick search, the live asset list, charts, the camera scanner, signature pads and QR labels.
 */
import { initAssetGrid } from './assets.js';
import { initCharts } from './charts.js';
import { initDashboard } from './dashboard.js';
import { initCatalogPickers } from './combo.js';
import { initQrCodes } from './labels.js';
import { initPalette } from './palette.js';
import { initScanner } from './scanner.js';
import { initSignaturePads } from './signature.js';
import { initThemes } from './themes.js';
import { initOrgTree } from './org.js';
import { initPortalApp } from './pwa.js';
import { initUi } from './ui.js';
import { $ } from './util.js';
function boot() {
    initUi();
    initAssetGrid();
    initDashboard();
    initPalette();
    initCharts();
    initScanner();
    initQrCodes();
    initSignaturePads();
    initCatalogPickers();
    initOrgTree();
    initPortalApp();
    initThemes();
    // Deep link "#new" opens the page's create dialog (used by the palette quick actions).
    if (location.hash === '#new') {
        $('dialog.modal[id^="modal-new"]')?.showModal();
    }
}
if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', boot);
else
    boot();
