import json, subprocess, urllib.request, urllib.error
r=subprocess.run(['git','credential','fill'],input='protocol=https\nhost=github.com\n\n',capture_output=True,text=True,check=True)
c=dict(line.split('=',1) for line in r.stdout.splitlines() if '=' in line)
headers={'Authorization':'Bearer '+c['password'],'Accept':'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28','User-Agent':'airmius-deployment-setup'}
for path in ['','/actions/secrets','/actions/variables','/actions/workflows/hostinger-deploy.yml/runs?per_page=2']:
 req=urllib.request.Request('https://api.github.com/repos/aminmasri123/airmius'+path,headers=headers)
 try:
  with urllib.request.urlopen(req,timeout=30) as response: body=json.load(response)
  if not path: print(json.dumps({'permissions':body.get('permissions'),'default_branch':body.get('default_branch')}))
  elif 'secrets' in path: print(json.dumps({'secrets':[x['name'] for x in body.get('secrets',[])]}))
  elif 'variables' in path: print(json.dumps({'variables':[{'name':x['name'],'value':x['value']} for x in body.get('variables',[]) if x['name'].startswith('HOSTINGER_')]}))
  else: print(json.dumps({'runs':[{'id':x['id'],'status':x['status'],'conclusion':x['conclusion'],'sha':x['head_sha']} for x in body.get('workflow_runs',[])]}))
 except urllib.error.HTTPError as error: print(json.dumps({'path':path,'status':error.code}))
