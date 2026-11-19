from pathlib import Path
import json,subprocess
p=Path('tests/Feature/RoleApplicationTest.php'); s=p.read_text(); s=s.replace('$this->actingAs($admin)', "$this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])"); p.write_text(s)
paths=subprocess.check_output(['rg','-l','-F','1.0.71+157','app','config','resources','tests','mobile/airmius_mobile','--glob','!**/build/**','--glob','!**/.dart_tool/**'],text=True).splitlines()
for name in paths:
 p=Path(name); s=p.read_text(encoding='utf-8-sig'); p.write_text(s.replace('1.0.71+157','1.0.73+159'),encoding='utf-8')
p=Path('mobile/airmius_mobile/store_listing/release/release_evidence_manifest.json'); d=json.loads(p.read_text()); print('Release gate statuses:',{x['id']:x.get('status') for x in d['gates']})
for lang in ['en','fr','ar']:
 d=json.loads(Path('resources/js/lang/de.json').read_text()); t=json.loads(Path('resources/js/lang/'+lang+'.json').read_text()); missing=sorted(set(d)-set(t)); print(lang,'missing semantic',len(missing),missing[:18])
