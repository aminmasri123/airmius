set -Eeuo pipefail
umask 077
root=/home/u270834523/domains/airmius.com/public_html/airmius
base=/home/u270834523/.airmius-deploy
test -d "$root" && test ! -L "$root"
test ! -e "$base/current"
mkdir -p "$base" "$base/backups" "$base/incoming" "$base/releases" "$base/shared"
chmod 711 "$base" "$base/releases" "$base/shared"
chmod 700 "$base/backups" "$base/incoming"
cd "$root"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=config('database.connections.'.config('database.default'));
if (($c['driver']??'')!=='mysql') throw new RuntimeException('Expected MySQL');
$dir='/home/u270834523/.airmius-deploy/backups';
$options=tempnam($dir,'mysql-'); chmod($options,0600);
$quote=fn($s)=>'"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','\\n','\\r'],(string)$s).'"';
file_put_contents($options,"[client]\nhost=".$quote($c['host'])."\nuser=".$quote($c['username'])."\npassword=".$quote($c['password'])."\nport=".(int)($c['port']??3306)."\n");
$path=$dir.'/before-autodeploy-'.gmdate('Ymd-His').'.sql';
try {
 $p=proc_open(['mysqldump','--defaults-extra-file='.$options,'--single-transaction','--skip-lock-tables','--no-tablespaces',$c['database']],[0=>['file','/dev/null','r'],1=>['file',$path,'w'],2=>['file',$path.'.error','w']],$pipes);
 if (!is_resource($p)||proc_close($p)!==0) throw new RuntimeException('Database backup failed; see private error file');
 chmod($path,0600); echo 'Database backup created: '.basename($path).' ('.filesize($path)." bytes)\n";
} finally { unlink($options); }
PHP
find "$base/backups" -maxdepth 1 -type f -name '*.sql' -exec gzip {} \;
cp -p .env "$base/shared/.env"
chmod 600 "$base/shared/.env"
mkdir -p "$base/shared/storage" "$base/shared/assets" "$base/releases/initial-20261119"
rsync -a storage/ "$base/shared/storage/"
rsync -a --exclude='/.git/' --exclude='/node_modules/' --exclude='/mobile/' --exclude='/storage/' --exclude='/.env' --exclude='/tmp/' --exclude='*.sql*' ./ "$base/releases/initial-20261119/"
ln -s "$base/shared/.env" "$base/releases/initial-20261119/.env"
ln -s "$base/shared/storage" "$base/releases/initial-20261119/storage"
# Test only a harmless public file through a private release symlink.
printf 'airmius-release-symlink-ok\n' > "$base/releases/initial-20261119/symlink-probe.txt"
chmod 644 "$base/releases/initial-20261119/symlink-probe.txt"
chmod 755 "$base/releases/initial-20261119"
ln -s "$base/releases/initial-20261119/symlink-probe.txt" "$root/airmius-symlink-probe-20261119.txt"
cd "$base/releases/initial-20261119"
php artisan config:clear
php artisan config:cache
php artisan view:cache
printf 'Prepared initial release and private backups.\n'
