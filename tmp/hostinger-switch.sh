set -Eeuo pipefail
base=/home/u270834523/.airmius-deploy
root=/home/u270834523/domains/airmius.com/public_html/airmius
previous="$base/releases/initial-20261119"
backup="$base/backups/legacy-root-20261119"
test -d "$root" && test ! -L "$root"
test -f "$previous/artisan" && test ! -e "$backup"
rm "$root/airmius-symlink-probe-20261119.txt"
ln -s "$previous" "$base/current"
cd "$root"
php artisan down --retry=30
recover() {
 trap - ERR
 if test -L "$root"; then rm "$root"; fi
 if test -d "$backup"; then mv "$backup" "$root"; fi
 (cd "$root" && php artisan up) || true
 exit 1
}
trap recover ERR
rsync -a storage/ "$base/shared/storage/"
mv "$root" "$backup"
ln -s "$base/current" "$root"
(cd "$root" && php artisan up)
curl --fail --silent --show-error --max-time 30 https://airmius.com/up >/dev/null
curl --fail --silent --show-error --max-time 30 https://airmius.com/ > "$base/backups/baseline-home.html"
trap - ERR
printf 'Initial release live; old application retained privately.\n'
