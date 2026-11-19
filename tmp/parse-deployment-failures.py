from pathlib import Path
import re,json
s=Path('tmp/deployment-first-run.log').read_text(encoding='utf-8-sig')
s=re.sub(r'^\S+Z\s?', '',s,flags=re.M)
blocks=re.split(r'\s*─{8,}\s*',s)
failures=[]
for b in blocks:
 if 'FAILED  ' not in b: continue
 lines=b[b.index('FAILED  '):].splitlines()
 where=[x.strip() for x in lines if x.strip().startswith('at ') or re.match(r'^\d+\s+tests/',x.strip())]
 failures.append({'title':lines[0].strip(),'details':'\n'.join(lines[1:12]),'where':where})
Path('tmp/deployment-failures.json').write_text(json.dumps(failures,ensure_ascii=False,indent=2),encoding='utf-8')
for i,f in enumerate(failures):
 print(str(i)+': '+f['title']+'\n'+f['details'][:440]+'\n'+'; '.join(f['where'])+'\n')
