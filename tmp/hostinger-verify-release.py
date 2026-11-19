import subprocess,hashlib,json,pathlib,base64
paths=['app','bootstrap','config','database','lang','resources','routes','public','artisan','cron-schedule.php','composer.json','composer.lock']
rows=subprocess.check_output(['git','ls-tree','-r','-z','HEAD','--',*paths]).split(b'\0')
entries=[(x.split(b'\t',1)[1].decode(),x.split(b'\t',1)[0].split()[2].decode()) for x in rows if x and not x.split(b'\t',1)[1].startswith(b'public/build/')]
blobs=subprocess.check_output(['git','cat-file','--batch'],input='\n'.join(sha for _,sha in entries).encode()+b'\n')
offset=0; expected={}
for name,sha in entries:
 end=blobs.index(b'\n',offset); size=int(blobs[offset:end].split()[2]); offset=end+1
 expected[name]=hashlib.sha256(blobs[offset:offset+size]).hexdigest(); offset+=size+1
payload=base64.b64encode(json.dumps(expected).encode()).decode()
php='''<?php
$expected=json_decode(base64_decode('''+repr(payload)+'''),true,512,JSON_THROW_ON_ERROR);
$bad=[];
foreach($expected as $file=>$hash) { if(!is_file($file)||hash_file('sha256',$file)!==$hash) $bad[]=$file; }
$manifest=json_decode(file_get_contents('public/build/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$assets=[];
foreach($manifest as $entry) foreach(array_merge([$entry['file']??''],$entry['css']??[],$entry['assets']??[]) as $file) { if(!$file||!is_file('public/build/'.$file)) $assets[]=$file; }
require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$pending=[]; foreach(glob('database/migrations/*.php') as $file) { $name=basename($file,'.php'); if(!DB::table('migrations')->where('migration',$name)->exists()) $pending[]=$name; }
$pdf=(new App\\Services\\AirmiusPdfDocument)->certificateHeader('ZERTIFIKAT','Airmius Lernplattform')->certificateFooter('Erfolgreich abgeschlossen.')->render();
$certificateOk=str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'/Subtype /Image')&&str_contains($pdf,'Airmius Lernplattform')&&!str_contains($pdf,'RECHNUNG');
echo json_encode(['verified_source_files'=>count($expected),'source_mismatches'=>$bad,'manifest_entries'=>count($manifest),'missing_assets'=>array_unique($assets),'pending_migrations'=>$pending,'certificate_pdf_with_logo'=>$certificateOk,'database_ok'=>DB::selectOne('SELECT 1 AS ok')->ok==1,'cron_exists'=>is_file('cron-schedule.php')],JSON_THROW_ON_ERROR)."\\n";
exit(($bad||$assets||$pending||!$certificateOk)?1:0);
'''
args=['ssh','-p','65002','-i',str(pathlib.Path.home()/'.ssh/airmius_github_deploy_ed25519'),'-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','u270834523@46.202.158.65','cd /home/u270834523/.airmius-deploy/current && php']
r=subprocess.run(args,input=php.encode(),capture_output=True)
print(r.stdout.decode()); print(r.stderr.decode()); pathlib.Path('tmp/hostinger-online-verification.json').write_bytes(r.stdout); raise SystemExit(r.returncode)
