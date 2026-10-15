# Phase 40: Oeffentliche Inhalte

Stand: 2026-10-15

| ID | Abdeckung | Ergebnis / Restpruefung |
| --- | --- | --- |
| T40-01 | Blogliste, Kategorien, Detailseiten und RSS verwenden ausschliesslich veroeffentlichte Beitraege. Sprachvarianten, Bilder, Links, Canonical und RSS-Verknuepfung sind vorhanden. | DE/EN/FR/AR und zwei echte Bildformate im Browser visuell gegenpruefen. |
| T40-02 | Oeffentliche Vereins-, Sponsor-, Sportarten-, Staedte- und Eventseiten besitzen Suche beziehungsweise Filter und datensparsame Detailziele. | Mit einem privaten Testverein und einem internen Event als Gegenprobe pruefen. |
| T40-03 | Der oeffentliche Aufnahmeantrag bindet den Zielverein und schuetzt den Status durch ein nicht erratbares Token. | Gueltiges, veraendertes und abgelaufenes Token in einer isolierten Testumgebung ausfuehren. |
| T40-04 | Oeffentliche Kurs- und Commerce-Wege trennen Gastdaten, Buchungsstatus und spaetere Kontozuordnung. | Eine Gastbuchung mit Test-Zahlungsanbieter Ende-zu-Ende pruefen. |
| T40-05 | Impressum, Datenschutz, AGB, Widerruf, Community, Jugendschutz, Cookies sowie Meldewege sind oeffentlich und lokalisiert erreichbar. | Mobile Darstellung aller vier Sprachen visuell pruefen; juristische Endabnahme bleibt extern. |
| T40-06 | Eine globale Cookie-Auswahl erscheint beim Erstbesuch. `Nur notwendige`, `Alle akzeptieren` und eine getrennte Analyse-/Marketingauswahl werden ein Jahr lokal gespeichert. Auswahl und Widerruf sind ueber Footer und Schild-Symbol jederzeit erreichbar. Kontoeinstellungen haben fuer angemeldete Personen Vorrang. | In neuer privater Browsersitzung Akzeptieren, Ablehnen, Neuladen und Widerruf manuell pruefen. |
| T40-07 | Oeffentliche Ziele bleiben ohne Anmeldung erreichbar. Geschuetzte Folgeaktionen laufen ueber den vorhandenen Login-/Intended-URL-Mechanismus. | Geteilten Link auf Android/iOS und im Desktopbrowser mit anschliessender Anmeldung testen. |
| T40-08 | Blog, Lernkurse und Marketplace-Sitemap verwenden ausschliesslich veroeffentlichte Datensaetze; Entwuerfe sind nicht Bestandteil der maschinenlesbaren Ausgabe. | Alte URL eines zurueckgezogenen Testinhalts sowie Gast-Suche erneut pruefen. |
| T40-09 | `/robots.txt`, `/sitemap.xml`, `/blog/rss.xml` und `/site.webmanifest` sind zustandslos, cachebar, ETag-faehig und setzen keine Sitzungscookies. Sitemap/RSS filtern veroeffentlichte Inhalte und liefern lokalisierte URLs. | Ausgabe zusaetzlich mit einem externen RSS-, Sitemap- und Manifest-Validator pruefen. |

## Behobene Luecken

1. Es gab eine Cookie-Informationsseite und serverseitige Einwilligungsgrenzen, aber keine sichtbare Erstentscheidung fuer ausgeloggte Besucher.
2. Besucher konnten eine lokale Auswahl nicht spaeter zentral oeffnen oder widerrufen. Das ist nun ueber Footer und ein kompaktes Schild-Symbol moeglich.
3. Die Landingpage blockierte optionale Messung standardmaessig, konnte aber keine ausdrueckliche Browserentscheidung eines Gastes beruecksichtigen. Die Consent-Logik verbindet beides jetzt, ohne strengere Kontoeinstellungen zu ueberschreiben.
4. Die vorhandenen RSS-, Sitemap-, Robots- und Manifest-Vertraege waren bereits umfangreich; ein Phase-40-Vertragstest sichert ihre zustandslose und veroeffentlichungsgebundene Auslieferung zusaetzlich ab.

## Manueller Ablauf

1. Ein privates Browserfenster oeffnen und die Startseite aufrufen. Es darf vor einer Entscheidung keine Analyse- oder Marketingfreigabe geben.
2. `Nur notwendige` waehlen und neu laden. Der Dialog bleibt geschlossen; Anmeldung, Sprache, Theme und Navigation funktionieren weiterhin.
3. Das Schild-Symbol unten links oder `Cookie-Einstellungen oeffnen` im Footer waehlen. Analyse aktivieren, Marketing deaktiviert lassen und speichern.
4. Neu laden und die Auswahl erneut oeffnen. Analyse muss aktiv und Marketing inaktiv sein. Danach beide deaktivieren und speichern.
5. Mit einem Konto anmelden, dessen Werbe-/Mess-Einwilligung deaktiviert ist. Eine lokale Gastfreigabe darf die Kontoeinstellung nicht ueberschreiben.
6. `/robots.txt`, `/sitemap.xml`, `/blog/rss.xml` und `/site.webmanifest` jeweils direkt und in DE/EN/FR/AR oeffnen. Inhaltstyp, Sprache und gueltige Struktur pruefen.
7. Je einen veroeffentlichten und unveroeffentlichten Blogbeitrag, Kurs und Marketplace-Artikel vorbereiten. Nur veroeffentlichte Inhalte duerfen in Suche, Sitemap oder RSS erscheinen.
8. Eine oeffentliche Detailseite teilen, ausgeloggt oeffnen und danach eine geschuetzte Aktion starten. Nach der Anmeldung muss das fachlich richtige Ziel wieder erscheinen.
9. Oeffentliche Seiten auf schmalem Handy, grosser Schrift sowie mit Tastatur und Screenreader pruefen. Cookie-Dialog, Schliessen, Auswahl und Rechtstext-Link muessen erreichbar bleiben.
