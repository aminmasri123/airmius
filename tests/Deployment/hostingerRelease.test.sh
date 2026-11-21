#!/usr/bin/env bash
# Exercise direct publication/finalization against disposable fixtures only.
set -Eeuo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
fixture=$(mktemp -d "$HOME/airmius-deployment-test-XXXXXXXX")
[[ "$fixture" == /home/*/airmius-deployment-test-* ]]
trap 'rm -rf -- "$fixture"' EXIT
export REAL_PHP=$(command -v php)
export LIVE_ROOT="$fixture/live"
export FAIL_HTTP="$fixture/fail-http"
mkdir -p "$fixture/bin" "$fixture/deploy/"{incoming,releases,backups,shared/assets} "$LIVE_ROOT/"{storage,bootstrap/cache,public/build/assets,vendor}
export PATH="$fixture/bin:$PATH"
cat > "$fixture/bin/php" <<'SH'
#!/usr/bin/env bash
set -e
if test "$1" = artisan; then
    case "$2" in
        down) touch storage/maintenance-test ;;
        up) rm -f storage/maintenance-test ;;
    esac
    exit 0
fi
exec "$REAL_PHP" "$@"
SH
cat > "$fixture/bin/curl" <<'SH'
#!/usr/bin/env bash
set -e
test ! -f "$FAIL_HTTP"
for arg in "$@"; do
    case "$arg" in
        https://example.com/deployment.json*) cat "$LIVE_ROOT/public/deployment.json" ;;
        https://example.com/build/*) test -f "$LIVE_ROOT/public/${arg#https://example.com/}" ;;
    esac
done
SH
chmod 755 "$fixture/bin/"*
printf 'production-secret' > "$LIVE_ROOT/.env"
printf 'persistent-upload' > "$LIVE_ROOT/storage/upload.txt"
printf 'old-code' > "$LIVE_ROOT/old-only.php"
printf '<?php' > "$LIVE_ROOT/artisan"
printf '<?php' > "$LIVE_ROOT/vendor/autoload.php"
printf 'stale' > "$fixture/deploy/releases/stale"
printf 'stale' > "$fixture/deploy/backups/code-stale"
printf 'stale' > "$fixture/deploy/incoming/stale"
printf 'stale' > "$fixture/deploy/shared/assets/stale.js"

sha=$(printf '%040d' 2)
payload="$fixture/payload"
mkdir -p "$payload/"{vendor,public/build/assets,bootstrap/cache}
printf '<?php' > "$payload/vendor/autoload.php"
printf '<?php' > "$payload/artisan"
printf '%s' "$sha" > "$payload/new-code.php"
printf '{"commit":"%s"}' "$sha" > "$payload/public/deployment.json"
printf '{"resources/js/app.js":{"file":"assets/app.js"}}' > "$payload/public/build/manifest.json"
printf 'new-asset' > "$payload/public/build/assets/app.js"

(cd "$LIVE_ROOT" && php artisan down --retry=30)
rsync -a --checksum --delete --delay-updates \
    --exclude=/.env --exclude=/storage --exclude=/public/storage --exclude=/bootstrap/cache \
    "$payload/" "$LIVE_ROOT/"
bash "$root/scripts/deploy/hostinger-release.sh" "$fixture/deploy" "$LIVE_ROOT" https://example.com "$sha"
test "$(cat "$LIVE_ROOT/.env")" = production-secret
test "$(cat "$LIVE_ROOT/storage/upload.txt")" = persistent-upload
test ! -e "$LIVE_ROOT/old-only.php"
test ! -e "$LIVE_ROOT/storage/maintenance-test"
test -f "$LIVE_ROOT/public/build/assets/app.js"
test ! -e "$fixture/deploy/releases"
test ! -e "$fixture/deploy/backups"
test ! -e "$fixture/deploy/incoming"
test ! -e "$fixture/deploy/shared/assets"

# Failed public verification cannot roll code back without a server-side copy,
# but the application must still leave maintenance mode.
touch "$FAIL_HTTP"
(cd "$LIVE_ROOT" && php artisan down --retry=30)
if bash "$root/scripts/deploy/hostinger-release.sh" "$fixture/deploy" "$LIVE_ROOT" https://example.com "$sha"; then
    echo 'Expected HTTP verification to fail' >&2
    exit 1
fi
test ! -e "$LIVE_ROOT/storage/maintenance-test"
echo 'PASS: direct update preserves environment/uploads and creates no code copies.'
