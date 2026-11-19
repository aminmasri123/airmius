<?php
require 'vendor/autoload.php'; $app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (Illuminate\Support\Facades\Route::getRoutes() as $r) { if(in_array(App\Http\Middleware\EnsureApiProcessingPurpose::class,$r->gatherMiddleware(),true) && !App\Support\PlatformModuleRegistry::forApiPath(preg_replace('/\{[^}]+\??\}/','1',str_replace('{slash}','',$r->uri())))) echo $r->uri()."\n"; }
$r=app(App\Support\StagedRolloutReadinessReport::class)->make(); foreach($r['checks'] as $c) if($c['status']==='fail') echo json_encode($c)."\n";
