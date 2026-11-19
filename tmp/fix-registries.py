from pathlib import Path
import json,re
p=Path('app/Support/PlatformModuleRegistry.php'); lines=p.read_text(encoding='utf-8').splitlines(True)
add={'account':['portal','saved-views','trainer-resignation'],'support':['safety/reports'],'chat':['ai/communication-drafts'],'members':['governance','tasks','external/clubs','member-removals','member-removal-notifications','work-automation-clubs'],'teams':['team-transfer-requests'],'sponsors':['sponsor-inquiries']}
for key,prefixes in add.items():
 i=next(i for i,line in enumerate(lines) if "'"+key+"' => self::module(" in line)
 assert '], [' in lines[i]; lines[i]=lines[i].replace('], [',", "+', '.join(repr(x) for x in prefixes)+'], [',1)
p.write_text(''.join(lines),encoding='utf-8')
p=Path('app/Support/RolloutAcceptanceRegistry.php'); s=p.read_text(encoding='utf-8')
for feature,names in json.loads(Path('tmp/missing-rollout-routes.json').read_text()).items():
 pattern=r"('"+re.escape(feature)+r"' => \[\s*'routes' => \[)(.*?)(\],)"
 m=re.search(pattern,s,re.S); assert m,feature
 content=m.group(2).rstrip(); content=content+('' if content.endswith(',') else ',')+'\n'+''.join('                        '+repr(x)+',\n' for x in names)+'                    '
 s=s[:m.start(2)]+content+s[m.end(2):]
p.write_text(s,encoding='utf-8')
# Keep existing legal translations and restore the German source inventory.
p=Path('resources/legal/de.json'); d=json.loads(p.read_text(encoding='utf-8-sig')); e=json.loads(Path('resources/legal/en.json').read_text(encoding='utf-8-sig'))
for key in set(e['messages'])-set(d['messages']): d['messages'][key]=key
p.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print('Registered new module and rollout routes; restored German legal source keys')
