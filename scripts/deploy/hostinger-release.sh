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
for tool in php curl flock tar sha256sum readlink rsync; do command -v "$tool" >/dev/null; done
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'
test -d "$base/incoming" && test -d "$base/releases"
test -f "$web_root/.env" && test -d "$web_root/storage"
# Provisioning is a separate operation: never replace an existing production directory.
exec 9>"$base/deploy.lock"
flock -n 9 || { echo 'Another deployment is running.'; exit 1; }
test -d "$web_root" && test ! -L "$web_root"
test "$(readlink -f "$web_root")" = "$web_root"
test -f "$web_root/artisan"
previous_sha=$(php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $v["commit"]??"";' "$web_root/public/deployment.json")
[[ "$previous_sha" =~ ^[a-f0-9]{40}$ ]]

# A previous deployment may have stopped before its final cleanup. Retain the
# newest rollback tree of each kind and free every older copy before extracting
# or backing up another release. This bounds inode use without removing the last
# server-side recovery path.
prune_older_directories() {
    local root=$1
    local pattern=$2
    local newest
    newest=$(find "$root" -mindepth 1 -maxdepth 1 -type d -name "$pattern" -printf '%T@ %p\n' \
        | sort -nr | head -n 1 | cut -d ' ' -f 2-)
    while IFS= read -r -d '' candidate; do
        test "$candidate" = "$newest" || rm -rf -- "$candidate"
    done < <(find "$root" -mindepth 1 -maxdepth 1 -type d -name "$pattern" -print0)
}
prune_older_directories "$base/releases" '*'
prune_older_directories "$base/backups" 'code-*'
find "$base/incoming" -mindepth 1 -maxdepth 1 -type f \
    \( -name '*.tar.gz' -o -name '*-routes.json' -o -name '*-assets.txt' -o -name '*-asset-basenames.txt' \) \
    ! -name "$sha.tar.gz" -delete

if test "$previous_sha" = "$sha"; then
    curl --fail --silent --show-error --max-time 30 "$site_url/deployment.json?release=$sha" \
        | php -r '$v=json_decode(stream_get_contents(STDIN),true); exit(($v["commit"]??null)===$argv[1]?0:1);' "$sha"
    curl --fail --silent --show-error --max-time 30 "$site_url/up" >/dev/null
    echo "Commit $sha is already active and healthy."
    exit 0
fi

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
ln -s "$web_root/.env" "$release/.env"
ln -s "$web_root/storage" "$release/storage"
mkdir -p "$release/bootstrap/cache" "$base/shared/assets"
# Keep old hashed assets available for tabs opened before the release switch.
for asset in "$release/public/build/assets/"*; do
    test -f "$asset" || continue
    target="$base/shared/assets/$(basename "$asset")"
    if test -e "$target"; then cmp -s "$asset" "$target"; else cp "$asset" "$target"; fi
    # The restrictive deployment umask must not prevent Apache reading public files.
    chmod 644 "$target"
done
chmod 755 "$base/shared/assets"
rm -rf -- "$release/public/build/assets"
ln -s "$base/shared/assets" "$release/public/build/assets"
ln -s "$base/shared/storage/app/public" "$release/public/storage"
cd "$release"
php -r 'require "vendor/autoload.php";'
php artisan package:discover --ansi
php artisan config:cache
php artisan view:cache
php artisan route:list --json > "$base/incoming/$sha-routes.json"

# Keep hPanel's application directory real. Never sync production/runtime files.
sync_options=(-a --checksum --delete --delay-updates --exclude=/.env --exclude=/storage --exclude=/public/storage --exclude=/public/build/assets --exclude=/bootstrap/cache)
backup="$base/backups/code-$sha-$(date -u +%Y%m%dT%H%M%S)-$$"
mkdir -m 700 "$backup"
rsync "${sync_options[@]}" "$web_root/" "$backup/"
refresh_live() {
    (
        cd "$web_root"
        # Rebuild caches with live paths, not the private staging directory.
        find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete
        php artisan package:discover --ansi || return $?
        php artisan config:cache || return $?
        php artisan view:cache || return $?
    )
}
switched=false
maintenance=false
recover() {
    code=$?
    trap - ERR
    if $switched; then
        if rsync "${sync_options[@]}" "$backup/" "$web_root/" && refresh_live; then
            echo 'Previous application code restored from backup.' >&2
        else
            echo "Code recovery failed; maintenance retained. Backup: $backup" >&2
            exit "$code"
        fi
    fi
    if $maintenance; then (cd "$web_root" && php artisan up) || true; fi
    echo 'Deployment failed. Database migrations are not reversed automatically.' >&2
    exit "$code"
}
trap recover ERR
(cd "$web_root" && php artisan down --retry=30)
maintenance=true
php artisan migrate --force
# Recovery must run even if the copy is interrupted part way through.
switched=true
rsync "${sync_options[@]}" "$release/" "$web_root/"
# Hard links are real files for hPanel; retain old hashes for already-open tabs.
rsync -a --ignore-existing --link-dest="$base/shared/assets" "$base/shared/assets/" "$web_root/public/build/assets/"
refresh_live
cd "$web_root"
php artisan up
# Query the actual public site and match the complete commit, not merely HTTP 200.
curl --fail --silent --show-error --retry 3 --retry-all-errors --max-time 30 \
    -H 'Cache-Control: no-cache' "$site_url/deployment.json?release=$sha" \
    | php -r '$v=json_decode(stream_get_contents(STDIN),true); exit(($v["commit"]??null)===$argv[1]?0:1);' "$sha"
curl --fail --silent --show-error --retry 3 --retry-all-errors --max-time 30 "$site_url/up" >/dev/null
curl --fail --silent --show-error --retry 3 --retry-all-errors --max-time 30 "$site_url/" >/dev/null
# A healthy HTML response alone cannot detect unreadable shared build files.
php -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true,512,JSON_THROW_ON_ERROR); $files=[]; foreach($m as $e){foreach(array_merge([$e["file"]],$e["css"]??[],$e["assets"]??[]) as $f){$files[$f]=true;}} foreach(array_keys($files) as $f){echo $f,PHP_EOL;}' > "$base/incoming/$sha-assets.txt"
while IFS= read -r asset; do
    curl --head --fail --silent --show-error --retry 2 --max-time 30 "$site_url/build/$asset" >/dev/null
done < "$base/incoming/$sha-assets.txt"
php artisan queue:restart
maintenance=false
trap - ERR

# The live application is a real directory, so private release trees and code
# backups are needed only while a deployment is running. Keeping them after a
# verified publication duplicates vendor/ and quickly exhausts hosting inodes.
asset_keep_list="$base/incoming/$sha-asset-basenames.txt"
while IFS= read -r asset; do basename "$asset"; done < "$base/incoming/$sha-assets.txt" \
    | sort -u > "$asset_keep_list"
for asset_root in "$base/shared/assets" "$web_root/public/build/assets"; do
    test -d "$asset_root"
    while IFS= read -r -d '' asset; do
        grep -Fqx "$(basename "$asset")" "$asset_keep_list" || rm -f -- "$asset"
    done < <(find "$asset_root" -maxdepth 1 -type f -print0)
done

# Retain runtime data snapshots such as storage-before-hpanel-* because GitHub
# contains application code, not production uploads. Keep exactly the current
# release and its previous-code rollback while deleting every older code tree.
find "$base/releases" -mindepth 1 -maxdepth 1 -type d ! -path "$release" -exec rm -rf -- {} +
find "$base/backups" -mindepth 1 -maxdepth 1 -type d -name 'code-*' ! -path "$backup" -exec rm -rf -- {} +
find "$base/incoming" -mindepth 1 -maxdepth 1 -type f \
    \( -name '*.tar.gz' -o -name '*-routes.json' -o -name '*-assets.txt' -o -name '*-asset-basenames.txt' \) \
    -delete
echo "Published and verified commit $sha"
