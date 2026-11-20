#!/usr/bin/env bash
# One-time migration of the existing, healthy release; does not publish new code.
set -Eeuo pipefail
umask 027
base=${1:?deployment base required}
web_root=${2:?application directory required}
site_url=${3:?HTTPS URL required}
[[ "$base" =~ ^/home/[a-zA-Z0-9_./-]+$ && "$base" != *..* ]]
[[ "$web_root" =~ ^/home/[a-zA-Z0-9_./-]+$ && "$web_root" != *..* ]]
[[ "$site_url" =~ ^https://[a-zA-Z0-9.-]+$ ]]
exec 9>"$base/deploy.lock"
flock -n 9 || { echo 'Another deployment is running.'; exit 1; }
test -L "$web_root" && test -L "$base/current"
previous=$(readlink -f "$web_root")
[[ "$previous" == "$base/releases/"* && -f "$previous/artisan" ]]
test "$(readlink -f "$base/current")" = "$previous"
test -d "$base/shared/storage" && test ! -L "$base/shared/storage"
stamp=$(date -u +%Y%m%dT%H%M%S)-$$
stage="$base/incoming/hpanel-root-$stamp"
saved_link="$base/backups/hpanel-root-link-$stamp"
storage_backup="$base/backups/storage-before-hpanel-$stamp"
mkdir -m 755 "$stage"
rsync -a --exclude=/.env --exclude=/storage --exclude=/public/storage --exclude=/public/build/assets --exclude=/bootstrap/cache "$previous/" "$stage/"
install -m 600 "$base/shared/.env" "$stage/.env"
mkdir -p "$stage/storage" "$stage/bootstrap/cache" "$stage/public/build/assets"
rsync -a "$base/shared/storage/" "$stage/storage/"
cp -al "$base/shared/assets/." "$stage/public/build/assets/"
ln -s ../storage/app/public "$stage/public/storage"
maintenance=false
switched=false
recover() {
    code=$?
    trap - ERR
    if $switched; then
        if test -L "$base/shared/storage"; then unlink "$base/shared/storage"; fi
        if test ! -d "$base/shared/storage" && test -d "$storage_backup"; then
            mv "$storage_backup" "$base/shared/storage"
        fi
        # Preserve any runtime writes made while checking the new directory.
        rsync -a "$web_root/storage/" "$base/shared/storage/" || true
        mv "$web_root" "$stage-failed"
        mv "$saved_link" "$web_root"
        ln -s "$previous" "$base/current.recover"
        mv -Tf "$base/current.recover" "$base/current"
    fi
    if $maintenance; then (cd "$previous" && php artisan up) || true; fi
    echo 'Layout migration failed; original layout restored.' >&2
    exit "$code"
}
trap recover ERR
(cd "$previous" && php artisan down --retry=30)
maintenance=true
# Capture runtime changes after maintenance started.
rsync -a --delete "$base/shared/storage/" "$stage/storage/"
mv "$web_root" "$saved_link"
if ! mv "$stage" "$web_root"; then mv "$saved_link" "$web_root"; false; fi
switched=true
cd "$web_root"
php artisan package:discover --ansi
php artisan config:cache
php artisan view:cache
php artisan up
curl --fail --silent --show-error --max-time 30 "$site_url/up" >/dev/null
curl --fail --silent --show-error --max-time 30 "$site_url/" >/dev/null
curl --fail --silent --show-error --max-time 30 "$site_url/deployment.json" | cmp - public/deployment.json
test -d "$web_root" && test ! -L "$web_root"
ln -s "$web_root" "$base/current.next"
mv -Tf "$base/current.next" "$base/current"
# Existing private releases continue to use the same runtime data.
mv "$base/shared/storage" "$storage_backup"
ln -s "$web_root/storage" "$base/shared/storage"
maintenance=false
trap - ERR
echo 'Real application directory is active; original release and data snapshot retained.'
