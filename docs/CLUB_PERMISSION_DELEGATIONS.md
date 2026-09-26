# Konfigurierbare Vereinsrollen und zeitlich begrenzte Vertretungen

## Konfigurierbare Rollen

Ein Verein kann bis zu 100 eigene Rollendefinitionen mit stabilem Schlüssel, Namen, aktivem Zustand und einer Liste vorhandener Vereinsrechte anlegen. Elf Vorlagen bilden die in der fachlichen Anforderung genannten Standardrollen ab. Eine Rollenverwaltung kann nur Rechte aufnehmen oder neu zuweisen, die sie selbst wirksam besitzt.

Mehrere Definitionen lassen sich einem aktiven Mitglied zuordnen. Diese Zuordnungen liegen getrennt von den bisherigen `club_user.role`- und `club_user.roles`-Werten. Bestehende Rollen und alte Zugriffswege werden deshalb nicht umgeschrieben. Die zentrale Rechteberechnung vereinigt die Rechte aller aktiven Definitionen. Ein ausdrücklich auf `false` gesetztes personenbezogenes Einzelrecht hat weiterhin Vorrang. Wird eine Definition deaktiviert oder die Mitgliedschaft beendet, trägt sie keine wirksamen Rechte mehr bei.

Zugeordnete Definitionen können nicht gelöscht werden. Eine unveränderte Wiederholung der Zuweisung erzeugt weder doppelte Datensätze noch einen zusätzlichen Auditeintrag.

Eine Zuweisung besitzt zusätzlich einen Bereich: den gesamten Verein, genau eine Abteilung oder genau eine Mannschaft. Vereinsweite Zuweisungen bleiben über das bisherige Feld `role_definition_ids` kompatibel. Für Bereichszuweisungen nimmt die API strukturierte `assignments` mit `role_definition_id`, `scope_type` und `scope_id` entgegen. Abteilungen und Mannschaften müssen demselben Verein angehören; dieselbe Rolle darf in einem Bereich nur einmal zugewiesen werden.

Die globale Rechteprüfung wertet ausschließlich vereinsweite Rollenzuweisungen aus. Eine Abteilungs- oder Mannschaftsrolle wird nur durch `ClubPermissions::allowsInScope(...)` im exakt passenden Kontext berücksichtigt. Noch nicht umgestellte Fachpfade erhalten dadurch keine zusätzlichen Rechte. Die vollständige Durchsetzung dieser Kontextprüfung in den betroffenen Abteilungs- und Mannschaftspfaden bleibt ein eigener Umsetzungsschritt.

Die Verwaltungs-API umfasst:

- `GET|POST /api/v1/clubs/{club}/role-definitions`
- `PUT|DELETE /api/v1/clubs/{club}/role-definitions/{roleDefinition}`
- `GET|PUT /api/v1/clubs/{club}/members/{user}/role-definitions`

## Getrennte Aktionsrechte

Der zentrale Katalog führt für Mitglieder und Finanzen getrennte Rechte zum Bearbeiten, Exportieren, Freigeben und Löschen beziehungsweise Stornieren. Die bisherigen Rechte `members.manage` und `finance.manage` bleiben als kompatible Sammelrechte erhalten. Bei ihrer Auswertung werden die vier Aktionsrechte erteilt, solange kein personenbezogener Ausschluss für die konkrete Aktion hinterlegt ist.

Für Termine sind Bearbeiten und Löschen ebenfalls getrennt; `events.manage` bildet beide Aktionen kompatibel ab. Die gemeinsame Event-Policy wertet globale Vereinsrechte und bei Mannschaftsterminen den exakten Mannschaftsbereich sowie die zugehörige Abteilung aus. Private Mannschaftstermine werden berechtigten Bereichsrollen gezielt zugänglich, bleiben nach Rechteentzug und in fremden Bereichen jedoch verborgen.

Bei der Mannschafts-Mitgliederverwaltung werden Aufnahme beziehungsweise Beitrittsfreigabe, Änderung der Teamrolle und Entfernung getrennt mit `members.approve`, `members.roles` und `members.delete` geprüft. Eine Mannschaftszuweisung gilt nur dort; eine Abteilungszuweisung gilt für die Teams dieser Abteilung. Web und API verwenden dafür dieselbe Team-Policy. Bestehende Vereinsverwaltung, Teamleitung und globale Teamrechte behalten ihre bisherigen Möglichkeiten.

Mannschaftsdaten bearbeiten und Mannschaften löschen sind ebenfalls getrennte Rechte. Die Team-Policy berücksichtigt Vereins-, Team- und Abteilungszuweisungen. Eine Abteilungsrolle darf die organisatorische Zuordnung einer Mannschaft nur in einen ebenfalls berechtigten Zielbereich ändern; damit kann sie eine Mannschaft nicht in eine fremde Abteilung verschieben oder unkontrolliert aus dem eigenen Zuständigkeitsbereich lösen. Die Vorlagen für Geschäftsstelle und Abteilungsleitung enthalten das Bearbeitungsrecht, während das Löschrecht ausdrücklich vergeben werden muss.

Für Dateien und Ordner sind Ansicht, Anlegen/Bearbeiten, Löschen, Download/Export und Freigabe getrennt. `files.manage` bleibt als kompatibles Sammelrecht bestehen. Datei- und Ordner-Policies werten Vereins-, Mannschafts- und über die Mannschaft auch Abteilungsrollen aus; dasselbe gilt für Dateien eines Mannschaftstermins. Der konkrete Bereich wird bereits vor Upload oder Ordneranlage geprüft. Persönliche Eigentümerrechte, sichtbare Chat- und Antragsdokumente sowie die bisherigen globalen Dateiberechtigungen bleiben erhalten. Die API weist Export und Freigabe als getrennte Aktionsrechte aus.

Die erste Durchsetzungsstufe trennt außerdem Mitgliedslöschung von sonstiger Mitgliederbearbeitung und bei gespeicherten SEPA-Läufen die zweite Freigabe vom XML-Export. Weitere Datenarten und Altpfade mit festen Rollenlisten werden in T017c2 umgestellt.

## Ende der Mitgliedschaft

Bei direkter Entfernung, eigenem Austritt und geplanter Beendigung entfernt Airmius alle konfigurierbaren Rollenzuweisungen der Person. Noch aktive Vertretungen werden widerrufen, unabhängig davon, ob die austretende Person Rechte erteilt oder erhalten hatte. Ein datensparsamer Auditdatensatz enthält ausschließlich die Anzahl entfernter Rollen und widerrufener Vertretungen. Bestehende statische Rollenwerte werden beim geplanten Ende weiterhin als Mitgliedschaftshistorie bewahrt, sind im Status `former` aber nicht Grundlage für konfigurierbare Rollen oder Vertretungen.

## Zweck und Grenzen

Vereinsadministration kann vorhandene Vereinsrechte für einen festgelegten Zeitraum an ein anderes aktives Vereinsmitglied delegieren. Eine Vertretung ergänzt die dauerhaft aus Rollen und Einzelrechten berechneten Rechte nur zwischen `starts_at` und `ends_at`. Widerruf, Zeitablauf oder das Ende der aktiven Mitgliedschaft entziehen den delegierten Anteil unmittelbar.

Das Recht `members.roles` ist nicht delegierbar. Dadurch kann eine Vertretung weder dauerhafte Rollen oder Einzelrechte verändern noch weitere Vertretungen verwalten. Eine Person kann ausschließlich Rechte weitergeben, die sie im Verein selbst wirksam besitzt. Vertretungen sind auf 90 Tage begrenzt und können nicht an die eigene Person oder an Personen außerhalb des Vereins vergeben werden.

## API

- `GET /api/v1/clubs/{club}/permission-delegations` listet höchstens 200 Vertretungen des Vereins sowie den für die handelnde Person delegierbaren Rechtekatalog.
- `POST /api/v1/clubs/{club}/permission-delegations` legt eine sofortige oder zukünftige Vertretung an.
- `POST /api/v1/clubs/{club}/permission-delegations/{delegation}/revoke` widerruft sie idempotent.

Alle Endpunkte verlangen die bestehende Berechtigung zur Rollenverwaltung. Vereinsgrenzen werden sowohl beim Zielmitglied als auch beim Widerruf geprüft. Die wirksame Rechteberechnung ignoriert abgelaufene, widerrufene und zukünftig beginnende Vertretungen.

## Nachvollziehbarkeit

Anlage und Widerruf erzeugen Vereins-Auditeinträge. Gespeichert werden Rechte, Gültigkeitszeitraum und die beteiligten internen Benutzerkennungen. Es werden keine Kontaktdaten oder freien Begründungstexte in die Vertretung aufgenommen.

Die additiven Migrationen wurden nicht produktiv ausgeführt. Web- und App-Verwaltung, die fachpfadweite Durchsetzung des Abteilungs-/Mannschaftsbereichs, weitere getrennte Aktionen und die automatische Prüfwiedervorlage beim Tätigkeitsende folgen in den weiteren T017-Schritten.
