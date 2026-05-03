<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\ArrayInput;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$status = $kernel->call('schedule:run');

echo $kernel->output();

$kernel->terminate(new ArrayInput([]), $status);

exit($status);
