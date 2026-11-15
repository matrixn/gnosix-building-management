"""Package source + compiled frontend for Forge; no secrets or runtime data."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from hashlib import sha256
import json

ROOT = Path(__file__).resolve().parents[1]
manifest = ROOT / 'public/build/manifest.json'
if not manifest.is_file():
    raise SystemExit('Run npm ci and npm run build first.')
for entry in json.loads(manifest.read_text()).values():
    if not (ROOT / 'public/build' / entry['file']).is_file():
        raise SystemExit('Compiled asset missing; rebuild before packaging.')

directories = ['app', 'bootstrap', 'config', 'database', 'deploy', 'lang', 'resources', 'routes', 'public']
files = ['artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', '.env.production.example', '.env.example', 'README.md', 'AGENTS.md', 'docs/DEPLOY-FORGE.md', 'docs/ADMIN.md', 'docs/CONTACT-AND-SEO.md', 'docs/SYSTEM-SETTINGS.md', 'docs/LANDING-EXPERIENCE.md']
selected = [ROOT / f for f in files]
for name in directories:
    selected.extend(f for f in (ROOT / name).rglob('*') if f.is_file())
selected.extend(f for f in (ROOT / 'storage').rglob('.gitignore'))
selected = sorted({p for p in selected if p.name != 'hot' and p.suffix not in ['.sqlite', '.log'] and not p.name.endswith(('-wal', '-shm')) and not ('bootstrap/cache' in p.as_posix() and p.name != '.gitignore')})

out = ROOT / 'build/gnosix-atlas-forge-0.5.0.zip'
out.parent.mkdir(exist_ok=True)
with ZipFile(out, 'w', ZIP_DEFLATED) as archive:
    for p in selected:
        archive.write(p, p.relative_to(ROOT).as_posix())
digest = sha256(out.read_bytes()).hexdigest()
out.with_suffix('.zip.sha256').write_text(f'{digest}  {out.name}\n', encoding='utf-8')
print(f'{out}\nSHA256 {digest}\n{len(selected)} files; no vendor, .env, runtime data or design studies.')
