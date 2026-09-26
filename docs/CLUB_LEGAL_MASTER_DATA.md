# Geschützte Vereinsregister- und Steuerstammdaten

## Umfang

Airmius speichert pro Verein folgende rechtliche Stammdaten:

- Registerbehörde beziehungsweise Registergericht und Registernummer
- bis zu 20 Verbandszugehörigkeiten mit Mitgliedsnummer und optionalem Gültigkeitszeitraum
- Steuerbehörde, Steuernummer und Umsatzsteuer-ID
- Steuerstatus (`unknown`, `nonprofit`, `taxable`, `mixed`)
- optionales Ende der bescheinigten Steuerbefreiung

Die Migration `2026_09_24_000011_add_legal_master_data_to_clubs.php` ergänzt die Vereinsdaten additiv. Sie wurde nicht auf einem produktiven System ausgeführt.

## Schutzmodell

Nur Personen, die den Verein gemäß bestehender `update`-Policy verwalten dürfen, erhalten diese Felder über Web oder API und können sie ändern. `ClubResource` und das Web-Profil fügen die Daten nur bei dieser Berechtigung ein. Öffentliche Besucher und normale Mitglieder erhalten die Schlüssel weder im normalen Vereinsobjekt noch im eingebetteten Profil.

Jede tatsächliche Änderung erzeugt `club.legal_master_data.updated` im bestehenden Vereins-Auditprotokoll. Das Ereignis enthält den handelnden Benutzer und die Namen der geänderten Felder. Steuer- und Registerwerte werden nicht in das Auditereignis kopiert.

## Eingabe und Grenzen

Web und Flutter verwenden strukturierte Felder. Leere Werte werden als `null` gespeichert, die Umsatzsteuer-ID wird ohne Leerzeichen und in Großbuchstaben normalisiert. Verbandszeiträume verwenden `YYYY-MM-DD`; das Ende darf nicht vor dem Beginn liegen. Feldlängen, zulässige Steuerstatus und maximal 20 Verbände werden serverseitig geprüft.

Die Felder dokumentieren die vom Verein eingegebenen Angaben. Airmius bestätigt damit weder Registereintragung noch Gemeinnützigkeit und ersetzt keine rechtliche oder steuerliche Prüfung.

## Automatisierte Nachweise

- `tests/Feature/ClubLegalMasterDataTest.php`: Berechtigung, Nichtoffenlegung, Normalisierung, Validierung und Audit ohne sensible Werte
- `tests/Frontend/clubLegalMasterDataRender.test.mjs`: strukturierte Web-Eingaben und vollständige DE/EN/FR/AR-Texte im seitenspezifischen Sprachmodul
- `mobile/airmius_mobile/test/club_legal_master_data_test.dart`: mobile Modellabbildung und Übersetzungen

Eine Browser-/Realgeräteabnahme, Migrationsprobe mit einer anonymisierten Bestandskopie sowie die fachliche Prüfung der verwendeten Steuerstatus bleiben vor der produktiven Einführung erforderlich.
