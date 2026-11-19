import xml.etree.ElementTree as E
r=E.parse('tmp/current-failures.xml')
for i,t in enumerate(r.iter('testcase')):
 for e in list(t):
  if e.tag in ['error','failure']:
   lines=(e.text or '').splitlines(); lines=[x for x in lines if x.strip()]
   print(t.attrib['class'].split('\\')[-1]+'::'+t.attrib['name']+'\n'+'\n'.join(lines[1:4])[:330]+'\n')
