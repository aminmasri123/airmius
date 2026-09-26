# Satzungen, Ordnungen und Beitragsmodelle

Vereine können freigegebene Fassungen ihrer Satzungen, Ordnungen und formalen Beitragsmodelle mit einem eigenen Gültigkeitszeitraum hinterlegen. Die Erweiterung verwendet vorhandene Vereinsdateien und legt keine zweite Upload- oder Speicherstrecke an.

## Datenmodell

Jede Fassung besitzt:

- genau einen Verein und eine bereits hochgeladene Datei dieses Vereins,
- den Typ `statutes`, `regulation` oder `contribution_model`,
- Titel und Versionsbezeichnung,
- einen verpflichtenden Beginn und ein optionales Ende der Gültigkeit,
- interne oder öffentliche Sichtbarkeit sowie optionale Notizen.

Fassungen derselben Dokumentreihe, definiert durch Verein, Typ und Titel, dürfen sich zeitlich nicht überschneiden. Ein Ende am 31. Dezember und ein Folgebeginn am 1. Januar sind zulässig. Dieselbe Versionsbezeichnung darf innerhalb einer Dokumentreihe nur einmal vorkommen.

Operative `club_contribution_rules` besitzen eigene Gültigkeitszeiträume und können optional auf eine Fassung vom Typ `contribution_model` verweisen. Die Fassung muss zum selben Verein gehören und ihr Gültigkeitszeitraum muss die Beitragsregel vollständig abdecken. Bestehende Regeln bleiben ohne automatische Zuordnung unverändert. Eine bereits verknüpfte Fassung kann nicht gelöscht werden, damit historische Zuordnungen erhalten bleiben.

## Zugriff und Dateien

Vereinsmitglieder sehen interne und öffentliche Fassungen. Bei einem gelisteten Verein sehen andere angemeldete Personen ausschließlich ausdrücklich öffentliche Fassungen. Nur Personen mit der bestehenden Berechtigung zum Aktualisieren des Vereins dürfen Fassungen anlegen, ändern oder löschen.

Downloads laufen über einen eigenen geschützten Endpunkt und prüfen Sichtbarkeit, Verein und tatsächliche Dateizugehörigkeit erneut. Eine Datei, die noch von mindestens einer Fassung verwendet wird, kann über die Dateiverwaltung nicht gelöscht werden. Das Löschen einer Fassung löscht die wiederverwendbare Vereinsdatei nicht.

## Audit

Anlegen, Ändern und Löschen werden als `club.policy_document.*` protokolliert. Der Auditdatensatz enthält ausschließlich den Fachobjekttyp. Titel, Version, Zeitraum, Notizen und Dateiname werden nicht in die Audit-Nutzdaten kopiert.

## API

- `GET /api/v1/clubs/{club}/policy-documents`
- `POST /api/v1/clubs/{club}/policy-documents`
- `PUT /api/v1/clubs/{club}/policy-documents/{policyDocument}`
- `DELETE /api/v1/clubs/{club}/policy-documents/{policyDocument}`
- `GET /api/v1/clubs/{club}/policy-documents/{policyDocument}/download`

Vor dem Anlegen wird die Datei über die vorhandene Vereinsdateiverwaltung hochgeladen. Danach wird ihre `file_id` mit der Dokumentfassung verknüpft.

## Web und native App

Das Vereinsprofil im Web und die Vereinsorganisation in der nativen App zeigen die drei Dokumentarten getrennt mit Fassung, Gültigkeit, Status, Sichtbarkeit und Hinweisen. Vereinsmitglieder können die für sie freigegebenen Fassungen lesen. Die Vereinsverwaltung kann vorhandene Vereinsdateien auswählen und Fassungen anlegen, bearbeiten oder nach ausdrücklicher Bestätigung löschen.

Die native App behandelt diese Verwaltungsaufrufe als sensible Schreibvorgänge: Sie werden bei Verbindungsfehlern weder automatisch wiederholt noch in die Offline-Warteschlange aufgenommen. Der Dateizugriff verwendet die bestehende authentifizierte Vereinsdateiverwaltung. Oberflächen und Validierungstexte stehen in Deutsch, Englisch, Französisch und Arabisch bereit.

Die Web- und App-Verwaltung der Beitragsregeln bietet formale Beitragsmodellfassungen desselben Vereins zur optionalen Auswahl an. Eine gespeicherte Zuordnung wird bei historischen Regeln als Beschlussgrundlage angezeigt und beim Bearbeiten wieder geladen. Die operative Beitragsberechnung verwendet weiterhin die Beitragsregel selbst; die Dokumentfassung dient als unveränderbar geschützte fachliche Grundlage.
