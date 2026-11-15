# Paste into the Forge deploy-script editor. These macros are expanded by Forge,
# so this file is not an ordinary standalone Bash executable.
set -e

$CREATE_RELEASE()

cd "$FORGE_RELEASE_DIRECTORY"

"$FORGE_COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build

"$FORGE_PHP" artisan config:cache
"$FORGE_PHP" artisan route:cache
"$FORGE_PHP" artisan view:cache
"$FORGE_PHP" artisan gnosix:check --launch

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
