#!/usr/bin/env bash
# Finalize a direct rsync update. GitHub is the code history; Hostinger keeps no
# duplicate application releases or code backups.
set -Eeuo pipefail
umask 027

base=${1:?deployment base required}
web_root=${2:?application document root required}
site_url=${3:?HTTPS site URL required}
sha=${4:?commit required}
[[ "$base" =~ ^/home/[a-zA-Z0-9_./-]+$ && "$base" != *..* ]]
[[ "$web_root" =~ ^/home/[a-zA-Z0-9_./-]+$ && "$web_root" != *..* ]]
[[ "$site_url" =~ ^https://[a-zA-Z0-9.-]+$ ]]
[[ "$sha" =~ ^[a-f0-9]{40}$ ]]
for tool in php curl flock readlink; do command -v "$tool" >/dev/null; done
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'
test -d "$base" && test -d "$web_root" && test ! -L "$web_root"
test "$(readlink -f "$web_root")" = "$web_root"
test -f "$web_root/.env" && test -d "$web_root/storage"
test -f "$web_root/artisan" && test -f "$web_root/vendor/autoload.php"
test -f "$web_root/public/build/manifest.json"

exec 9>"$base/deploy.lock"
flock -n 9 || { echo 'Another deployment is running.'; exit 1; }

cd "$web_root"
deployed_sha=$(php -r '$v=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $v["commit"]??"";' public/deployment.json)
test "$deployed_sha" = "$sha"

maintenance=true
finish() {
    code=$?
    trap - EXIT
    if $maintenance; then php artisan up || true; fi
    exit "$code"
}
trap finish EXIT

find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete
php -r 'require "vendor/autoload.php";'
php artisan package:discover --ansi
php artisan config:cache
php artisan view:cache
php artisan migrate --force
php artisan up
maintenance=false

curl --fail --silent --show-error --retry 3 --retry-all-errors --max-time 30 \
    -H 'Cache-Control: no-cache' "$site_url/deployment.json?release=$sha" \
    | php -r '$v=json_decode(stream_get_contents(STDIN),true); exit(($v["commit"]??null)===$argv[1]?0:1);' "$sha"
curl --fail --silent --show-error --retry 3 --retry-all-errors --max-time 30 "$site_url/up" >/dev/null
curl --fail --silent --show-error --retry 3 --retry-all-errors --max-time 30 "$site_url/" >/dev/null

php -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true,512,JSON_THROW_ON_ERROR); $files=[]; foreach($m as $e){foreach(array_merge([$e["file"]],$e["css"]??[],$e["assets"]??[]) as $f){$files[$f]=true;}} foreach(array_keys($files) as $f){echo $f,PHP_EOL;}' \
    | while IFS= read -r asset; do
        curl --head --fail --silent --show-error --retry 2 --max-time 30 "$site_url/build/$asset" >/dev/null
    done

php artisan queue:restart

# One-time and idempotent cleanup of the former release deployment. Remove its
# directories completely so later commits cannot fill them again. In backups,
# remove only code-* trees: runtime snapshots may contain uploads absent in Git.
for obsolete in "$base/releases" "$base/incoming" "$base/shared/assets"; do
    test -d "$obsolete" || continue
    find "$obsolete" -mindepth 1 -delete
    rmdir "$obsolete"
done
if test -d "$base/backups"; then
    find "$base/backups" -mindepth 1 -maxdepth 1 -name 'code-*' -exec rm -rf -- {} +
    rmdir "$base/backups" 2>/dev/null || true
fi

echo "Published and verified direct update $sha"
