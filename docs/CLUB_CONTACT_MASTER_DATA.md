# Vereinskontakte und zuständige Ansprechpartner

## Umfang

Der bestehende Vereinsdatensatz enthält bereits Vereinsname, Anschrift und die in den Beitrags-/SEPA-Einstellungen verwaltete Bankverbindung. Die Kontaktstammdaten ergänzen:

- allgemeine Vereins-E-Mail
- Telefonnummer
- Website mit `http` oder `https`
- bis zu 20 zuständige Ansprechpartner mit Name, Funktion, E-Mail und Telefon
- getrennte Freigabe der allgemeinen Kontaktdaten und jedes einzelnen Ansprechpartners für das öffentliche Vereinsprofil

Die additive Migration `2026_09_24_000012_add_contact_master_data_to_clubs.php` ergänzt diese Felder. Sie wurde nicht auf einem produktiven System ausgeführt.

## Sichtbarkeit und Berechtigungen

Die öffentliche Sichtbarkeit ist standardmäßig deaktiviert. Solange sie deaktiviert ist, erhalten ausschließlich Personen mit bestehender Vereins-`update`-Berechtigung die Kontaktfelder. Bei aktivierter Sichtbarkeit werden allgemeine Kontaktdaten und ausschließlich Ansprechpartner mit `is_public=true` ausgeliefert. Interne Ansprechpartner werden weder im normalen API-Vereinsobjekt noch im eingebetteten Profil übertragen.

Änderungen erzeugen `club.contact_master_data.updated` im bestehenden Vereins-Auditprotokoll. Das Ereignis enthält die handelnde Person und nur die Namen geänderter Felder. E-Mail-Adressen, Telefonnummern und Namen werden nicht in den Auditdaten dupliziert.

## Eingabe und Oberflächen

Web und Flutter bieten strukturierte Eingaben und eine ausdrückliche öffentliche Freigabe. E-Mail-Adressen werden kleingeschrieben, Leerwerte als `null` behandelt und Telefonnummern auf übliche internationale Zeichen begrenzt. Website-Adressen müssen `http` oder `https` verwenden. Web und App zeigen nur die bereits serverseitig gefilterten öffentlichen Kontakte an.

Die Bankverbindung bleibt im bestehenden Beitrags-/SEPA-Modul. Kontaktänderungen verändern Kontoinhaber, IBAN oder BIC nicht.

## Automatisierte Nachweise

- `tests/Feature/ClubContactMasterDataTest.php`: Speichern, Normalisierung, Rechte, öffentliche Filterung, Validierung, Audit ohne sensible Werte und unveränderte Bankdaten
- `tests/Frontend/clubContactMasterDataRender.test.mjs`: Web-Eingaben, Freigabesteuerung und DE/EN/FR/AR-Texte
- `mobile/airmius_mobile/test/club_contact_master_data_test.dart`: mobile Modell-/Profilabbildung und Übersetzungen

Vor einer produktiven Einführung bleiben Browser-/Realgeräteabnahme und eine Migrationsprobe mit anonymisierten Bestandsdaten erforderlich.
