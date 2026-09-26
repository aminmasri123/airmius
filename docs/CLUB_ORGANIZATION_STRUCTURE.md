# Mehrstufige Vereinsorganisation

## Datenmodell

Die Organisationsebene ergänzt die bestehenden Vereine und Mannschaften additiv:

- `club_departments`: Abteilungen mit optionaler Sportart und Beschreibung,
- `club_locations`: wiederverwendbare Vereinsstandorte mit strukturierter Anschrift,
- `club_training_groups`: Trainingsgruppen mit optionaler Abteilungs- und Standortzuordnung,
- `teams`: bestehende Mannschaften erhalten optionale Verweise auf Abteilung, Standort und Trainingsgruppe.

Alle Einheiten sind direkt an genau einen Verein gebunden. Die Migration `2026_09_24_000014_create_club_organization_structure.php` lässt sämtliche neuen Mannschaftsverweise leer. Bestehende Mannschaften, Mitglieder, Termine und Trainingsdaten werden dadurch nicht verändert.

## API und Berechtigungen

`GET /api/v1/clubs/{club}/organization` liefert die Organisationsstruktur. Vereinsverwaltung und Vereinsmitglieder sehen interne und öffentliche Einheiten. Andere angemeldete Personen sehen bei einem gelisteten Verein ausschließlich Einträge mit `is_public=true`. Nur Personen mit der bestehenden Vereinsberechtigung `update` dürfen Einheiten anlegen, ändern oder löschen.

Schreibend stehen getrennte Endpunkte unter diesen Pfaden bereit:

- `/organization/departments`
- `/organization/locations`
- `/organization/training-groups`

Der bestehende Mannschafts-Endpunkt akzeptiert optional `club_department_id`, `club_location_id` und `club_training_group_id`. Sämtliche Referenzen müssen zum Verein der Mannschaft gehören. Wenn eine Trainingsgruppe eine Abteilung oder einen Standort vorgibt, werden fehlende Werte übernommen und widersprüchliche Werte abgewiesen. Ein normales Mannschaftsupdate ohne Organisationsfelder behält eine bestehende Zuordnung bei.

## Lösch- und Auditschutz

Eine Abteilung kann nicht gelöscht werden, solange eine Mannschaft oder Trainingsgruppe darauf verweist. Dasselbe gilt für verwendete Standorte; eine Trainingsgruppe kann bei zugeordneten Mannschaften nicht gelöscht werden. Erst nach ausdrücklicher Umordnung oder Entfernung der Verweise ist das Löschen möglich.

Anlegen, Ändern, Löschen und Mannschaftszuordnung erzeugen eigene `club.organization.*`-Auditeinträge. Das Audit speichert Einheitenart beziehungsweise geänderte Feldnamen, aber keine Beschreibungen, Anschriften oder sonstigen Freitexte.

## Web-Oberfläche

Das Vereinsprofil lädt die serverseitig gefilterte Organisation über den eigenen API-Endpunkt. Öffentliche beziehungsweise interne Abteilungen, Standorte und Trainingsgruppen werden gemeinsam mit ihrem Sichtbarkeitsstatus dargestellt. Interne Standortnotizen werden außerhalb der Vereinsmitgliedschaft auch bei einem öffentlichen Standort nicht übertragen.

Vereinsverwaltung kann alle drei Einheitstypen anlegen, bearbeiten und nach erfolgreicher Löschschutzprüfung entfernen. Vorhandene Mannschaften lassen sich Abteilung, Standort und Trainingsgruppe zuordnen. Die Auswahl einer Trainingsgruppe übernimmt deren vorbelegte Abteilung und Standort; der Server prüft die Zuordnung erneut. Lade-, Validierungs- und Übertragungsfehler bleiben sichtbar und führen nicht zu einem automatischen Schreibwiederholungsversuch.

Alle neuen Bedienelemente besitzen programmatische Namen und Texte in DE/EN/FR/AR. Das seitenspezifische Sprachmodul wird mit dem Vereinsprofil geladen und vergrößert die globalen Kernsprachpakete nicht.

## Native App

Im Struktur-Tab des Vereinsprofils führt ein eigener Eintrag zur nativen Organisationsansicht. Sie zeigt ausschließlich die vom Server für die aktuelle Person freigegebenen Abteilungen, Standorte und Trainingsgruppen. Vereinsverwaltung kann Einheiten in nativen Dialogen anlegen, bearbeiten und nach Bestätigung löschen sowie bestehende Mannschaften zuordnen.

Bei Auswahl einer Trainingsgruppe übernimmt die App deren Abteilung und Standort; der Server bleibt für Vereinsgrenze und Konsistenz maßgeblich. Pflichtname und zweistelliger Ländercode werden vor dem Versand geprüft. Serverseitige Validierungs- und Löschschutzfehler erscheinen in der Ansicht.

Schreibaktionen der Organisationsverwaltung werden weder in die Offline-Warteschlange aufgenommen noch nach einem Verbindungsabbruch automatisch wiederholt. Ein unklarer Ausgang wird deshalb durch erneutes Laden geklärt, bevor eine Person die Aktion bewusst erneut ausführt. Die Oberfläche und ihre programmatischen Feldnamen stehen in DE/EN/FR/AR bereit.

## Noch folgende Einführungsarbeit

T012a bis T012c bilden Backend, Web und native App ab. Die Gesamtanforderung T012 bleibt bis zur Bestandsprobe, realen Browser-/Geräteabnahme und kontrollierten Einführung in T012d offen.

Vor einer produktiven Migration sind eine Sicherung, eine anonymisierte Bestandsprobe und die Prüfung der Fremdschlüssel auf der eingesetzten MySQL-/MariaDB-Version erforderlich. Im Implementierungsschritt wurde keine produktive Migration ausgeführt.
