from pathlib import Path
import xml.etree.ElementTree as E
r=E.parse('tmp/fixture-results.xml')
for t in r.iter('testcase'):
 for e in list(t):
  if e.tag in ['error','failure']: print(t.attrib['name'],e.attrib.get('message','')[:180])
for p in [*Path('app').rglob('*.php'),*Path('tests').rglob('*.php')]:
 s=p.read_text(encoding='utf-8-sig')
 if r'/^version:\s*(\S+)$/m' in s:
  p.write_text(s.replace(r'/^version:\s*(\S+)$/m',r'/^version:\s*(\S+)\s*$/m'),encoding='utf-8'); print('Fixed CRLF version parsing:',p)
