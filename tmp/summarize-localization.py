from pathlib import Path
import json
missing=json.loads(Path('tmp/missing-visible-sources.json').read_text(encoding='utf-8'))
core=json.loads(Path('resources/js/lang/de.json').read_text(encoding='utf-8'))
print('Already covered by runtime semantic fallback:',sum(x in core for x in missing))
for locale in ['en','fr','ar']:
 data=json.loads(Path('tmp/localization-legal_content_integrity.json').read_text(encoding='utf-8'))['locales'][locale]
 print(locale, {k:len(v) if isinstance(v,list) else v for k,v in data.items()})
