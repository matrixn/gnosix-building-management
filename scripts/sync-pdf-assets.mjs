import {cpSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
const target=resolve('public/vendor/pdfjs');mkdirSync(target,{recursive:true});
for(const dir of ['cmaps','standard_fonts','wasm','iccs'])cpSync(resolve('node_modules/pdfjs-dist',dir),resolve(target,dir),{recursive:true});
cpSync(resolve('node_modules/pdfjs-dist/LICENSE'),resolve(target,'LICENSE'));
