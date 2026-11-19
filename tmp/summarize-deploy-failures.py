from pathlib import Path
import re
s=Path('tmp/deployment-first-run.log').read_text(encoding='utf-8-sig')
lines=[re.sub(r'^\S+Z\s*','',x) for x in s.splitlines() if 'FAILED  ' in x or 'Tests:    ' in x]
Path('tmp/hostinger-audit/deployment-testfehler-2026-11-19.txt').write_text('Erster automatischer Deployment-Lauf: eb5b6efa\nVeröffentlichung durch Testfehler blockiert.\n\n'+'\n'.join(lines)+'\n',encoding='utf-8')
p=Path('tmp/hostinger-verify-release.py'); t=p.read_text(); t=t.replace("for x in rows if x]", "for x in rows if x and not x.split(b'\\t',1)[1].startswith(b'public/build/')]"); p.write_text(t)
print('Failure summary saved; generated build excluded from source-byte comparison')
