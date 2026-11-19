import pathlib, subprocess, sys
args=['ssh','-p','65002','-i',str(pathlib.Path.home()/'.ssh/airmius_github_deploy_ed25519'),'-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','u270834523@46.202.158.65','bash -s']
p=subprocess.run(args,input=pathlib.Path(sys.argv[1]).read_bytes().replace(b'\r\n',b'\n'))
sys.exit(p.returncode)
