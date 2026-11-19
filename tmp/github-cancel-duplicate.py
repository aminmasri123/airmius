import sys
sys.argv=['github-deploy-admin.py','noop']
exec(open('tmp/github-deploy-admin.py').read())
api('/actions/runs/44609912088/cancel','POST',{})
print('Cancelled duplicate trigger for the same commit')
