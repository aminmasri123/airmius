import xml.etree.ElementTree as E
r=E.parse('tmp/repair-batch-one.xml')
for t in r.iter('testcase'):
 for e in list(t):
  if e.tag in ['error','failure']: print(t.attrib['class']+'::'+t.attrib['name']+'\n'+(e.text or '')[:450]+'\n')
