set -Eeuo pipefail
base=/home/u270834523/.airmius-deploy
for folder in "$base/current/build/assets" "$base/current/public/build/assets"; do
 if test -d "$folder"; then rsync -a --ignore-existing "$folder/" "$base/shared/assets/"; fi
done
chmod 755 "$base/shared/assets"
printf 'Existing hashed web assets preserved for release transitions.\n'
