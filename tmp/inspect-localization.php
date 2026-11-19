<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['server','organization','file_manager'] as $group) {
 $de=Illuminate\Support\Arr::dot(require 'lang/de/'.$group.'.php');
 foreach(['en','fr','ar'] as $locale){ $t=Illuminate\Support\Arr::dot(require 'lang/'.$locale.'/'.$group.'.php'); $missing=array_diff_key($de,$t); echo $group.' '.$locale.' '.count($missing)."\n";file_put_contents('tmp/missing-'.$group.'-'.$locale.'.json',json_encode($missing,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
}
$r=App\Support\LocalizationReadinessReport::make();
foreach(['vue','php','translation_integrity','legal_content_integrity'] as $k) file_put_contents('tmp/localization-'.$k.'.json',json_encode($r[$k],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['vue'=>$r['vue']['total_candidates'],'php'=>$r['php']['total_candidates'],'vue_files'=>array_slice($r['vue']['top_files'],0,4),'php_files'=>array_slice($r['php']['top_files'],0,3)],JSON_UNESCAPED_UNICODE)."\n";
