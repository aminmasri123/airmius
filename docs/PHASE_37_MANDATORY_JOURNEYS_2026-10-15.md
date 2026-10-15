# Phase 37: Pflichtregressionen und vollstaendige Nutzerreisen

Stand: 2026-10-15

Diese Matrix trennt automatisierte Teilnachweise von echten Ende-zu-Ende-Freigaben. Eine Reise ist erst bestanden, wenn ihre manuellen Gates mit den genannten Rollen und Geraeten in einer isolierten Testumgebung durchlaufen wurden.

| ID | Nutzerreise | Automatisierter Nachweis | Verbleibendes Freigabe-Gate |
| --- | --- | --- | --- |
| T37-01 | Neuer Sportler | Registrierung, Verifikation und Mitgliedsantrag | Zwei Konten, echte Mail/Push, Teambeitritt in App |
| T37-02 | Rechnung bis Zahlung | Rechnungszugriff, PDF, Teilzahlung, Notification-Routing und Deep-Link | Reale Push-Zustellung und PDF auf Geraet |
| T37-03 | Umgekehrte Plattformen | App-API fuer Rechnung und Web-Zahlungsstatus | Rechnung nativ anlegen und im Browser abschliessen |
| T37-04 | Teamisolation | Vereins- und Teamgrenzen serverseitig | Direkte Links mit Konten aus Verein A und B |
| T37-05 | Zusammenarbeit | Aufgaben-API, Rechte und Status | Zwei Geraete, Kommentar, Anhang und Kalender gleichzeitig |
| T37-06 | Kalendernavigation | Event- und Aufgabenfristen-API | Browser-Zurueck, native Navigation und erhaltene Ansicht |
| T37-07 | Mobile Lesbarkeit | Phase-35-Vertraege und Widgetabdeckung | Grosse Systemschrift auf schmalem realem Geraet |
| T37-08 | Zweiter Verein | Tenant-, Cache- und Datenschutzvertraege | Offline/Zurueck nach Wechsel von Konto und Verein |
| T37-09 | Import bis Nutzung | Import, Einladung, Verknuepfung und Mitgliedskarte | Echte Einladung und historische Rechnung pruefen |
| T37-10 | Trainerablauf | Plan-, Log- und Workflow-Integration | Getrenntes Trainer- und Sportlerkonto |
| T37-11 | Sponsorablauf | Profil-, Workspace- und Paritaetsnachweise | Kontrollierte Freigabe, Impression und Klick |
| T37-12 | Elternablauf | Zugriff, Zustimmung, Widerruf und Familienisolation | Getrennte Eltern-/Kinderkonten und alte Links |
| T37-13 | Vereinsloeschung zuruecknehmen | 30-Tage-Zustand, Benachrichtigung, Audit und Abbruch | Zeitreise bis nach geplantem Termin |
| T37-14 | Administrator nativ | Native Adminparitaet und Adminbereichsschutz | Super-Admin und eingeschraenkter Fachadmin auf Geraet |
| T37-15 | Kauf bis Erstattung | Zahlung und Refund-Abstimmung | Zahlungsanbieter im Testmodus und Dokumentpruefung |

## Behobene Luecke

Rechnungsbenachrichtigungen erzeugen `airmius://billing/invoices/{id}`. Die Flutter-App konnte diesen Link bereits lokal oeffnen, der serverseitige Mobile-Resolver stufte ihn jedoch als unbekannt ein. Der Resolver liefert nun `billing_invoice`, den geschuetzten API-Endpunkt der Rechnung und als Web-Fallback den Abrechnungstab. Die Rechnungsart aus `?kind=club_invoice` oder `?kind=subscription_invoice` bleibt erhalten, damit gleiche IDs nicht zur falschen Rechnung fuehren. Ungueltige Billing-Pfade bleiben abgewiesen.

## Ausfuehrungsregel

1. Jede Reise ohne direkte Datenbankaenderung am Anfangszustand starten.
2. Web und App mit getrennten Testkonten benutzen; Verein A/B nie im selben Browserprofil vermischen.
3. Nach jedem Plattformwechsel Seite beziehungsweise App neu laden und Serverzustand vergleichen.
4. IDs, Uhrzeit, Rolle, Geraet, Build und Ergebnis protokollieren.
5. Nur `bestanden` eintragen, wenn automatisierter Nachweis und manuelles Gate erfolgreich sind. Teilnachweise bleiben `offen` oder `blockiert`.
