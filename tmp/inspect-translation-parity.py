from pathlib import Path
import json
for lang in ['en','fr','ar']:
 d=json.loads(Path('resources/js/lang/de.json').read_text(encoding='utf-8-sig')); t=json.loads(Path('resources/js/lang/'+lang+'.json').read_text(encoding='utf-8-sig')); missing=sorted(set(d)-set(t)); print(lang,'missing semantic',len(missing)); Path('tmp/missing-'+lang+'.json').write_text(json.dumps({k:d[k] for k in missing},ensure_ascii=False,indent=2),encoding='utf-8')
p=Path('tests/Feature/MobileClubMembershipParityApiTest.php'); s=p.read_text(encoding='utf-8'); s=s.replace('Storage::fake(\\App\\Support\\UploadStorage::disk());','Storage::fake(\\App\\Support\\UploadStorage::disk());\n        Storage::fake(\\App\\Support\\UploadStorage::privateDisk());'); p.write_text(s,encoding='utf-8')
