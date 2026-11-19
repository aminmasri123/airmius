#!/usr/bin/env bash
# Server-side publication of an already built, immutable release.
set -Eeuo pipefail
umask 027

base=${1:?deployment base required}
web_root=${2:?application document root required}
site_url=${3:?HTTPS site URL required}
sha=${4:?commit required}
expected_hash=${5:?archive checksum required}
[[ "$base" =~ ^/home/[a-zA-Z0-9_./-]+$ && "$base" != *..* ]]
[[ "$web_root" =~ ^/home/[a-zA-Z0-9_./-]+$ && "$web_root" != *..* ]]
[[ "$site_url" =~ ^https://[a-zA-Z0-9.-]+$ ]]
[[ "$sha" =~ ^[a-f0-9]{40}$ && "$expected_hash" =~ ^[a-f0-9]{64}$ ]]
for tool in php curl flock tar sha256sum readlink; do command -v "$tool" >/dev/null; done
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'
test -d "$base/incoming" && test -d "$base/releases"
test -f "$base/shared/.env" && test -d "$base/shared/storage"
# Provisioning is a separate operation: never replace an existing production directory.
exec 9>"$base/deploy.lock"
flock -n 9 || { echo 'Another deployment is running.'; exit 1; }
test -L "$base/current"
previous=$(readlink -f "$base/current")
[[ "$previous" == "$base/releases/"* && -f "$previous/artisan" ]]
test "$(readlink -f "$web_root")" = "$previous"

archive="$base/incoming/$sha.tar.gz"
test -f "$archive"
printf '%s  %s\n' "$expected_hash" "$archive" | sha256sum -c -
release="$base/releases/$sha"
test ! -e "$release" || { echo 'Release already exists; refusing to modify it.'; exit 1; }
mkdir "$release"
tar -xzf "$archive" -C "$release"
chmod -R u+rwX,go+rX "$release"
test -f "$release/vendor/autoload.php"
test -f "$release/public/build/manifest.json"
test ! -e "$release/.env" && test ! -e "$release/storage"
ln -s "$base/shared/.env" "$release/.env"
ln -s "$base/shared/storage" "$release/storage"
mkdir -p "$release/bootstrap/cache" "$base/shared/assets"
# Keep old hashed assets available for tabs opened before the release switch.
for asset in "$release/public/build/assets/"*; do
    test -f "$asset" || continue
    target="$base/shared/assets/$(basename "$asset")"
    if test -e "$target"; then cmp -s "$asset" "$target"; else cp "$asset" "$target"; fi
done
rm -rf -- "$release/public/build/assets"
ln -s "$base/shared/assets" "$release/public/build/assets"
ln -s "$base/shared/storage/app/public" "$release/public/storage"
cd "$release"
php -r 'require "vendor/autoload.php";'
php artisan package:discover --ansi
php artisan config:cache
php artisan view:cache
php artisan route:list --json > "$base/incoming/$sha-routes.json"

switched=false
maintenance=false
recover() {
    code=$?
    trap - ERR
    if $switched; then
        ln -s "$previous" "$base/current.rollback"
        mv -Tf "$base/current.rollback" "$base/current"
        echo 'Application symlink restored to previous release.' >&2
    fi
    if $maintenance; then (cd "$previous" && php artisan up) || true; fi
    echo 'Deployment failed. Database migrations are not reversed automatically.' >&2
    exit "$code"
}
trap recover ERR
# Shared storage makes maintenance visible to both old and new releases.
(cd "$previous" && php artisan down --retry=30)
maintenance=true
php artisan migrate --force
ln -s "$release" "$base/current.next"
mv -Tf "$base/current.next" "$base/current"
switched=true
php artisan up
# Query the actual public site and match the complete commit, not merely HTTP 200.
curl --fail --silent --show-error --retry 3 --retry-all-errors --max-time 30 \
    -H 'Cache-Control: no-cache' "$site_url/deployment.json?release=$sha" \
    | php -r '$v=json_decode(stream_get_contents(STDIN),true); exit(($v["commit"]??null)===$argv[1]?0:1);' "$sha"
curl --fail --silent --show-error --retry 3 --retry-all-errors --max-time 30 "$site_url/up" >/dev/null
curl --fail --silent --show-error --retry 3 --retry-all-errors --max-time 30 "$site_url/" >/dev/null
php artisan queue:restart
maintenance=false
trap - ERR
echo "Published and verified commit $sha"
