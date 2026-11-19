import xml.etree.ElementTree as E
r=E.parse('tmp/repair-batch-two.xml')
for t in r.iter('testcase'):
 for e in list(t):
  if e.tag in ['error','failure']: print(t.attrib['name']+'\n'+(e.text or '')[:360]+'\n')
