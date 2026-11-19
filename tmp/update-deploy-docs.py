from pathlib import Path
p=Path('docs/HOSTINGER_AUTODEPLOY.md')
s=p.read_text(encoding='utf-8')
s=s.replace('`resources`, `routes`, `public`, `vendor`, `artisan` und Composer-Metadaten.','`resources`, `routes`, `public`, `vendor`, `artisan`, `cron-schedule.php` und Composer-Metadaten.')
a=s.index('Vor dem ersten Lauf:')
b=s.index('SSH-Port laut',a)
s=s[:a]+'''Die bestehende Domain bleibt auf dem bisherigen Anwendungsverzeichnis. Dieses
ist ein Symlink auf `current`; `.htaccess` leitet HTTP ausschließlich in `public/`.
Dadurch bleiben bestehende Cron-Pfade zu `artisan` und `cron-schedule.php` gültig.
Der Runner prüft vor jedem Wechsel, dass der Domain-Pfad auf das aktuelle Release zeigt.

Am 19.11.2026 wurden die Produktionsdatenbank und das bisherige Verzeichnis privat
unter `.airmius-deploy/backups` gesichert und ein unverändertes Ausgangsrelease
angelegt. Die Symlink-Erreichbarkeit, Startseite und `/up` wurden danach geprüft.
Der alte Hostinger-GitHub-Webhook ist deaktiviert, damit nur dieser Workflow deployt.

'''+s[b:]
s=s.replace('bestätigter öffentlicher Pfad, der auf `current/public` zeigt','bestehender Domain-Pfad, der auf `current` zeigt')
a=s.index('Die Workflow-Datei allein')
s=s[:a]+'''Der dedizierte Deployment-Schlüssel ist auf Hostinger mit `restrict` autorisiert.
Die fünf Repository-Secrets wurden verschlüsselt hinterlegt; private Schlüssel und
Produktionskonfiguration bleiben außerhalb des Repositories. Die Freigabe erfolgt
nach Einrichtung über `HOSTINGER_DEPLOY_ENABLED=true`.

Lokale Vorprüfung: Workflow-YAML, Bash-Syntax, Vite-Manifest und vier Tests für
vollständige/fehlende Assets sowie ungültige Imports/Pfade.
'''
p.write_text(s,encoding='utf-8')
Path('.hostinger').mkdir(exist_ok=True)
Path('.hostinger/site.json').write_text('{\n  "domain": "airmius.com",\n  "username": "u270834523",\n  "type": "php"\n}\n')
