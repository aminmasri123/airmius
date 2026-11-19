import sys,pathlib,subprocess,json,urllib.request,urllib.error,base64
r=subprocess.run(['git','credential','fill'],input='protocol=https\nhost=github.com\n\n',capture_output=True,text=True,check=True)
c=dict(x.split('=',1) for x in r.stdout.splitlines() if '=' in x)
headers={'Authorization':'Bearer '+c['password'],'Accept':'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28','User-Agent':'airmius-deployment-setup'}
base='https://api.github.com/repos/aminmasri123/airmius'
def api(path,method='GET',data=None):
 req=urllib.request.Request(base+path,headers=headers,method=method,data=None if data is None else json.dumps(data).encode())
 with urllib.request.urlopen(req,timeout=60) as response:
  b=response.read(); return json.loads(b) if b else None
def variable(name,value):
 existing=api('/actions/variables')['variables']
 if any(v['name']==name for v in existing): api('/actions/variables/'+name,'PATCH',{'name':name,'value':value})
 else: api('/actions/variables','POST',{'name':name,'value':value})
mode=sys.argv[1]
if mode=='configure':
 sys.path.insert(0,str(pathlib.Path('tmp/deploy-test-deps').resolve()))
 from nacl.public import PublicKey,SealedBox
 key=api('/actions/secrets/public-key')
 box=SealedBox(PublicKey(base64.b64decode(key['key'])))
 hostkey=subprocess.run(['ssh-keygen','-F','[46.202.158.65]:65002','-f',str(pathlib.Path.home()/'.ssh/known_hosts')],capture_output=True,text=True,check=True)
 known='\n'.join(x for x in hostkey.stdout.splitlines() if x and not x.startswith('#'))
 assert known
 secrets={'HOSTINGER_SSH_HOST':'46.202.158.65','HOSTINGER_SSH_PORT':'65002','HOSTINGER_SSH_USER':'u270834523','HOSTINGER_SSH_KEY':(pathlib.Path.home()/'.ssh/airmius_github_deploy_ed25519').read_text(),'HOSTINGER_SSH_KNOWN_HOSTS':known}
 for name,value in secrets.items():
  api('/actions/secrets/'+name,'PUT',{'key_id':key['key_id'],'encrypted_value':base64.b64encode(box.encrypt(value.encode())).decode()})
  print('Stored encrypted secret '+name)
 for name,value in {'HOSTINGER_DEPLOY_BASE':'/home/u270834523/.airmius-deploy','HOSTINGER_WEB_ROOT':'/home/u270834523/domains/airmius.com/public_html/airmius','HOSTINGER_SITE_URL':'https://airmius.com','HOSTINGER_DEPLOY_ENABLED':'false'}.items(): variable(name,value)
 print('Deployment variables configured; activation pending server verification')
 print('Webhook services:',[{'id':h['id'],'active':h['active'],'host':urllib.parse.urlparse(h['config'].get('url','')).hostname} for h in api('/hooks')])
elif mode=='enable':
 variable('HOSTINGER_DEPLOY_ENABLED','true')
 api('/actions/workflows/hostinger-deploy.yml/dispatches','POST',{'ref':'main'})
 print('Automatic deployment enabled and first run dispatched')
elif mode=='status':
 for x in api('/actions/workflows/hostinger-deploy.yml/runs?per_page=3')['workflow_runs']:
  print(json.dumps({k:x.get(k) for k in ['id','head_sha','status','conclusion','html_url']}))
elif mode=='jobs':
 for x in api('/actions/runs/'+sys.argv[2]+'/jobs')['jobs']:
  print(json.dumps({'id':x['id'],'status':x['status'],'conclusion':x['conclusion'],'steps':[{k:s.get(k) for k in ['name','status','conclusion']} for s in x['steps']]}))
elif mode=='logs':
 req=urllib.request.Request(base+'/actions/jobs/'+sys.argv[2]+'/logs',headers=headers)
 class NoRedirect(urllib.request.HTTPRedirectHandler):
  def redirect_request(self,req,fp,code,msg,hdrs,newurl): return None
 try:
  response=urllib.request.build_opener(NoRedirect).open(req,timeout=60)
 except urllib.error.HTTPError as error:
  if error.code!=302: raise
  response=urllib.request.urlopen(error.headers['Location'],timeout=60)
 with response: pathlib.Path('tmp/deploy-job.log').write_bytes(response.read())
 print('Saved job log privately in tmp/deploy-job.log')
