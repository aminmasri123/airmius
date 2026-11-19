import sys
sys.argv=['github-deploy-admin.py','noop']
exec(open('tmp/github-deploy-admin.py').read())
api('/hooks/616606951','PATCH',{'active':False})
print('Disabled legacy Hostinger webhook to prevent competing deployments')
