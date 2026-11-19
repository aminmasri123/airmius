set -Eeuo pipefail
cd /home/u270834523/.airmius-deploy/current
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(['php'=>PHP_VERSION,'maintenance_driver'=>config('app.maintenance.driver'),'storage_is_shared'=>is_link('storage'),'cron_exists'=>is_file('cron-schedule.php'),'database_reachable'=>DB::selectOne('SELECT 1 AS ok')->ok===1],JSON_THROW_ON_ERROR)."\n";
PHP
find /home/u270834523/.airmius-deploy/backups -maxdepth 1 -name '*.sql.gz' -exec gzip -t {} \;
printf 'Compressed database backup verified.\n'
