import json,pathlib,re,subprocess,os
names=json.loads(pathlib.Path('tmp/ci-failing-test-names.json').read_text())
methods=sorted(set(n.split('::',1)[1].split(' with data set')[0] for n in names))
pattern='/(?:^|::)(?:'+'|'.join(re.escape(x) for x in methods)+')(?:$| with data set)/'
env=dict(os.environ);env['PATH']='C:/Program Files/Git/bin;C:/Users/pcc/Documents/Codex/airmius-tools/php84;'+env['PATH']
with open('tmp/current-failures.log','wb') as log:
 p=subprocess.run(['C:/Users/pcc/Documents/Codex/airmius-tools/php84/php.exe','-d','memory_limit=1G','vendor/phpunit/phpunit/phpunit','--filter',pattern,'--log-junit','tmp/current-failures.xml'],stdout=log,stderr=subprocess.STDOUT,env=env)
print('Targeted regression run finished:',p.returncode)
