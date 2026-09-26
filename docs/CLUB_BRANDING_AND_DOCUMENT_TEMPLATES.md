# Vereinsauftritt, Briefpapier und Dokumentvorlagen

## Umfang

Die Vereinsverwaltung kann den bestehenden Vereinsauftritt an einer Stelle pflegen:

- Vereinslogo und Titelbild als Bilddateien,
- Primär-, Sekundär- und Akzentfarbe im Format `#RRGGBB`,
- Briefpapier mit optionalem Logo, Kopfzeile, Anschriftzeile und Fußzeile,
- bis zu 20 benannte Dokumentvorlagen für Brief, Rechnung, Beleg, Urkunde oder einen eigenen Typ,
- je Vorlagentyp höchstens eine Standardvorlage.

Web und native App verwenden denselben API-Vertrag. Die App kann nun neben dem Titelbild auch das Vereinslogo hochladen. Eine öffentliche Farbpalette wird im Vereinsprofil angezeigt, sobald mindestens eine Vereinsfarbe gepflegt ist.

## Sichtbarkeit und Berechtigungen

Die drei Vereinsfarben sind Teil des öffentlichen Vereinsauftritts. Briefpapier und Dokumentvorlagen bleiben serverseitig auf Personen mit der bestehenden Vereinsberechtigung `update` begrenzt. Normale Mitglieder und Gäste erhalten diese Konfiguration nicht über Vereinsressource oder eingebettetes Profil und können sie nicht ändern.

Logo- und Titelbild-Uploads verwenden weiterhin die bestehende Bildprüfung und Speicherlogik. Änderungen an Logo, Titelbild, Farben, Briefpapier oder Vorlagen erzeugen den Audit-Eintrag `club.branding.updated`. Das Audit enthält nur die geänderten Feldnamen und keine Texte, Dateipfade oder Vorlageninhalte.

## Daten und Validierung

Die additive Migration `2026_09_24_000013_add_branding_and_document_templates_to_clubs.php` ergänzt die drei Farbfelder sowie zwei JSON-Felder. Bestehende Vereine behalten leere Werte; bestehende Logo- und Titelbilddaten werden nicht verändert.

Farben werden als großgeschriebene Hexwerte normalisiert. Kopf- und Anschriftzeile sind auf jeweils 300 Zeichen, die Fußzeile auf 500 Zeichen und Vorlagennamen auf 160 Zeichen begrenzt. Leere Eingaben werden konsistent normalisiert. Doppelte Vorlagennamen und mehrere Standardvorlagen desselben Typs werden abgewiesen.

Die Vorlagen speichern die vereinsweite Konfiguration. Ihre automatische Anwendung auf jede bestehende oder künftige PDF-Art ist bewusst Sache des jeweiligen Dokumentablaufs; ein Finanz- oder Urkundenmodul muss vor der Verwendung weiterhin seine fachlichen Pflichtangaben und unveränderlichen Belege wahren.

## Einführung

Vor der produktiven Einführung:

1. Datenbank sichern und die Migration in einer release-identischen Umgebung mit anonymisierten Bestandsdaten testen.
2. Web und App mit Manager-, Mitglieds- und Gastrolle prüfen, insbesondere die Nichtoffenlegung von Briefpapier und Vorlagen.
3. Logo- und Titelbildupload, Farbkontrast, Tastaturbedienung, RTL-Darstellung und reale Android-/iOS-Geräte abnehmen.
4. Falls ein Dokumentablauf eine Vorlage verwendet, dessen Pflichtangaben und Druckbild gesondert fachlich freigeben.
5. Migration und Anwendung kontrolliert ausrollen und Auditereignisse beobachten.

Im Rahmen der Implementierung wurde keine produktive Migration und keine Veröffentlichung ausgeführt.
