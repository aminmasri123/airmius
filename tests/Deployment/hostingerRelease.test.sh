#!/usr/bin/env bash
# Exercise the real rsync publication/recovery against disposable fixtures only.
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
if test -f "$FAIL_HTTP"; then exit 22; fi
for arg in "$@"; do
    case "$arg" in
        https://example.com/deployment.json*) cat "$LIVE_ROOT/public/deployment.json" ;;
        https://example.com/build/*)
            file="$LIVE_ROOT/public/${arg#https://example.com/}"
            test -f "$file" && test "$(stat -c %a "$file")" = 644 ;;
    esac
done
SH
chmod 755 "$fixture/bin/"*
printf 'production-secret' > "$LIVE_ROOT/.env"
printf 'persistent-upload' > "$LIVE_ROOT/storage/upload.txt"
printf 'old-code' > "$LIVE_ROOT/old-only.php"
printf '<?php' > "$LIVE_ROOT/artisan"
printf '<?php' > "$LIVE_ROOT/vendor/autoload.php"
printf '{"commit":"%040d"}' 1 > "$LIVE_ROOT/public/deployment.json"
printf 'old-asset' > "$LIVE_ROOT/public/build/assets/old.js"
chmod 644 "$LIVE_ROOT/public/build/assets/old.js"
printf 'old-asset' > "$fixture/deploy/shared/assets/old.js"
make_release() {
    local sha=$1
    local payload="$fixture/payload-$sha"
    mkdir -p "$payload/"{vendor,public/build/assets,bootstrap/cache}
    printf '<?php' > "$payload/vendor/autoload.php"
    printf '<?php' > "$payload/artisan"
    printf '%s' "$sha" > "$payload/new-code.php"
    printf '{"commit":"%s"}' "$sha" > "$payload/public/deployment.json"
    printf '{"resources/js/app.js":{"file":"assets/app.js"}}' > "$payload/public/build/manifest.json"
    printf 'new-asset' > "$payload/public/build/assets/app.js"
    tar -czf "$fixture/deploy/incoming/$sha.tar.gz" -C "$payload" .
}
publish() {
    local sha=$1
    bash ${DEPLOY_TEST_TRACE:+-x} "$root/scripts/deploy/hostinger-release.sh" "$fixture/deploy" "$LIVE_ROOT" https://example.com "$sha" "$(sha256sum "$fixture/deploy/incoming/$sha.tar.gz" | cut -d ' ' -f 1)"
}
sha=$(printf '%040d' 2)
make_release "$sha"
publish "$sha"
test -d "$LIVE_ROOT" && test ! -L "$LIVE_ROOT"
test "$(cat "$LIVE_ROOT/.env")" = production-secret
test "$(cat "$LIVE_ROOT/storage/upload.txt")" = persistent-upload
test ! -e "$LIVE_ROOT/old-only.php"
test -f "$LIVE_ROOT/public/build/assets/old.js"
test ! -L "$LIVE_ROOT/public/build/assets"
test ! -e "$LIVE_ROOT/storage/maintenance-test"
publish "$sha" # Retry of the active commit is harmless.
sha=$(printf '%040d' 3)
make_release "$sha"
touch "$FAIL_HTTP"
if publish "$sha"; then echo 'Expected HTTP verification to fail' >&2; exit 1; fi
test "$(cat "$LIVE_ROOT/new-code.php")" = "$(printf '%040d' 2)"
test "$(cat "$LIVE_ROOT/.env")" = production-secret
test "$(cat "$LIVE_ROOT/storage/upload.txt")" = persistent-upload
test ! -e "$LIVE_ROOT/storage/maintenance-test"
rm "$FAIL_HTTP"
legacy="$fixture/legacy"
old_live="$LIVE_ROOT"
export LIVE_ROOT="$legacy/site"
mkdir -p "$legacy/deploy/"{incoming,releases/initial,backups,shared/assets}
rsync -a --exclude=/.env --exclude=/storage --exclude=/public/storage --exclude=/public/build/assets "$old_live/" "$legacy/deploy/releases/initial/"
cp "$old_live/.env" "$legacy/deploy/shared/.env"
cp -a "$old_live/storage" "$legacy/deploy/shared/storage"
cp -a "$old_live/public/build/assets/." "$legacy/deploy/shared/assets/"
ln -s "$legacy/deploy/shared/.env" "$legacy/deploy/releases/initial/.env"
ln -s "$legacy/deploy/shared/storage" "$legacy/deploy/releases/initial/storage"
ln -s "$legacy/deploy/shared/assets" "$legacy/deploy/releases/initial/public/build/assets"
ln -s "$legacy/deploy/releases/initial" "$LIVE_ROOT"
ln -s "$legacy/deploy/releases/initial" "$legacy/deploy/current"
touch "$FAIL_HTTP"
if bash "$root/scripts/deploy/hostinger-file-manager-layout.sh" "$legacy/deploy" "$LIVE_ROOT" https://example.com; then
    echo 'Expected layout verification to fail' >&2; exit 1
fi
test -L "$LIVE_ROOT"
test "$(cat "$LIVE_ROOT/storage/upload.txt")" = persistent-upload
test ! -e "$LIVE_ROOT/storage/maintenance-test"
rm "$FAIL_HTTP"
bash "$root/scripts/deploy/hostinger-file-manager-layout.sh" "$legacy/deploy" "$LIVE_ROOT" https://example.com
test -d "$LIVE_ROOT" && test ! -L "$LIVE_ROOT"
test -f "$LIVE_ROOT/.env" && test ! -L "$LIVE_ROOT/.env"
test -d "$LIVE_ROOT/storage" && test ! -L "$LIVE_ROOT/storage"
test -d "$LIVE_ROOT/public/build/assets" && test ! -L "$LIVE_ROOT/public/build/assets"
test "$(cat "$LIVE_ROOT/storage/upload.txt")" = persistent-upload
test "$(readlink -f "$legacy/deploy/current")" = "$LIVE_ROOT"
echo 'PASS: real directory, preserved environment/uploads/assets, idempotence and failed-release recovery.'
