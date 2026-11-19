from pathlib import Path
import json,html,re
missing=json.loads(Path('tmp/missing-visible-sources.json').read_text(encoding='utf-8'))
core={l:json.loads(Path('resources/js/lang/'+l+'.json').read_text(encoding='utf-8-sig')) for l in ['de','en','fr','ar']}
reverse={str(v):k for k,v in core['de'].items() if isinstance(v,str)}
reuse={x:reverse[html.unescape(x)] for x in missing if html.unescape(x) in reverse}
print('Existing semantic translations reusable:',len(reuse))
Path('tmp/reusable-ui-translations.json').write_text(json.dumps(reuse,ensure_ascii=False,indent=2),encoding='utf-8')
