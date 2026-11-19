from pathlib import Path
p=Path('tmp/github-deploy-admin.py')
s=p.read_text()
s=s.replace("with urllib.request.urlopen(req,timeout=60) as response: pathlib.Path('tmp/deploy-job.log').write_bytes(response.read())",'''class NoRedirect(urllib.request.HTTPRedirectHandler):
  def redirect_request(self,req,fp,code,msg,hdrs,newurl): return None
 try:
  response=urllib.request.build_opener(NoRedirect).open(req,timeout=60)
 except urllib.error.HTTPError as error:
  if error.code!=302: raise
  response=urllib.request.urlopen(error.headers['Location'],timeout=60)
 with response: pathlib.Path('tmp/deploy-job.log').write_bytes(response.read())''')
p.write_text(s)
