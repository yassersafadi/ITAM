// Copies browser-ready vendor libraries from node_modules into public/assets/vendor.
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const out = resolve(root, 'public/assets/vendor');
mkdirSync(out, { recursive: true });
copyFileSync(resolve(root, 'node_modules/qrcode-generator/qrcode.js'), resolve(out, 'qrcode.js'));
console.log('vendor assets copied to public/assets/vendor');
