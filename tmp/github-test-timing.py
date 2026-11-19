import sys
sys.argv=['github-deploy-admin.py','noop']
exec(open('tmp/github-deploy-admin.py').read())
x=api('/actions/jobs/135863765590')
print(json.dumps({'started_at':x['started_at'],'current_step':[s for s in x['steps'] if s['status']=='in_progress']}))
r=api('/actions/workflows/airmius-mvp-ci.yml/runs?per_page=2')
print(json.dumps([{'id':x['id'],'status':x['status'],'conclusion':x['conclusion'],'created_at':x['created_at'],'updated_at':x['updated_at']} for x in r['workflow_runs']]))
