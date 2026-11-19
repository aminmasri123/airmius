import sys,urllib.request,urllib.error,pathlib,zipfile,io,xml.etree.ElementTree as E,json,re
sys.argv=['github-deploy-admin.py','noop'];exec(open('tmp/github-deploy-admin.py').read())
a=api('/actions/runs/44611320761/artifacts')['artifacts'][0]
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs): return None
req=urllib.request.Request(base+'/actions/artifacts/'+str(a['id'])+'/zip',headers=headers)
try: response=urllib.request.build_opener(NoRedirect).open(req)
except urllib.error.HTTPError as error:
 if error.code!=302: raise
 response=urllib.request.urlopen(error.headers['Location'])
with response: payload=response.read()
z=zipfile.ZipFile(io.BytesIO(payload)); xml=z.read('backend-results.xml');pathlib.Path('tmp/ci-backend-results.xml').write_bytes(xml)
r=E.fromstring(xml);failed=[]
for t in r.iter('testcase'):
 if any(x.tag in ['failure','error'] for x in t): failed.append(t.attrib['class']+'::'+t.attrib['name'])
pathlib.Path('tmp/ci-failing-test-names.json').write_text(json.dumps(failed,indent=2))
print('Downloaded exact JUnit failures:',len(failed))
