# Kontrollierte Einführung von Vereinsnummernkreisen

Stand: 24.09.2026 – T015b2

## Grundsatz

Vorhandene Nummern sind fachliche Bestandsdaten. Der Audit liest sie ausschließlich aggregiert und verändert weder Nummern noch Zähler. Eine Standardkreis-Zuordnung bedeutet nur, welcher Nummernkreis nach der späteren Aktivierung für neue Vorgänge verwendet werden soll. Sie übernimmt keine alte Nummer und aktiviert keinen bisherigen Erzeugungspfad.

## Erkannte Nummernwege

| Geltungsbereich | Bestehende Speicherung | Stand |
| --- | --- | --- |
| Mitglied | `club_user.member_number`, `club_external_members.member_number` | Bestehend, teils manuell/importiert |
| Vereinsrechnung | `invoices.number` | Bestehend, mehrere Erzeugungspfade |
| Beleg | `payments.receipt_number` | Additiv und unveränderlich; `reference` bleibt Fremd-/Bankreferenz |
| Spende | `payments.donation_number` | Additiv und unveränderlich; Spenden können zusätzlich eine Belegnummer tragen |
| Inventar | `club_inventory_items.sku` | Manuelle Werte bleiben erhalten; leere Werte verwenden optional den Standardkreis |
| Shoprechnung | `commerce_orders.invoice_number` | Vereinsbezogene Abschlüsse verwenden optional den Standardkreis |
| Shopgutschrift | `commerce_orders.credit_note_number` | Vereinsbezogene Stornos/Erstattungen verwenden optional den Standardkreis |
| Shopartikel | `marketplace_products.sku` | Manuelle Werte bleiben erhalten; leere Vereinsartikel verwenden optional den Standardkreis |

Bank- und Zahlungsreferenzen werden nicht als Beleg- oder Spendennummer umgedeutet. Die neuen kanonischen Felder bleiben bei historischen Zahlungen leer; eine automatische Rückdatierung findet nicht statt.

## Standardnummernkreis

Eine Vereinsverwaltung kann je Verein und Geltungsbereich genau einen aktiven Nummernkreis als Standard festlegen. Der Server prüft, dass Nummernkreis, Verein und Geltungsbereich zusammenpassen. Ein zugeordneter Standardkreis kann nicht gelöscht oder in einen anderen Geltungsbereich verschoben werden. Das Setzen oder Entfernen wird ohne Namen, Präfixe oder Nummern protokolliert.

Die Zuordnung allein ändert keine Bestandsdaten. Alle vorgesehenen Erzeugungswege sind in T015b2 kontrolliert angebunden: Ohne Standard bleibt das bisherige Verhalten erhalten, mit Standard werden Nummernvergabe und Fachdatensatz gemeinsam geschrieben. Manuell vorgegebene Mitglieds-, Rechnungs-, Inventar- und Shopnummern werden nicht überschrieben.

## Read-only-Audit

```bash
php artisan airmius:audit-club-number-ranges --with-data
php artisan airmius:audit-club-number-ranges --with-data --json
php artisan airmius:audit-club-number-ranges --with-data --strict
```

Der Audit meldet ausschließlich Summen:

- bestehende nummerierte Datensätze je Geltungsbereich,
- doppelte Nummerngruppen innerhalb eines Vereins,
- Vereins-/Geltungsbereichspaare ohne expliziten Standard,
- Kollisionen bereits vergebener Nummernkreiswerte mit Bestandsnummern,
- ungültige Vereins- oder Geltungsbereichsreferenzen.

Datensatz-IDs, Namen, Nummernwerte, Präfixe und freie Referenzen werden nicht ausgegeben. Der strenge Modus bleibt bis zur freigegebenen Bestandsprüfung, MySQL-Konkurrenzprüfung und Einführung in T015e auf `no-go`.

## Abbruchkriterien für die spätere Aktivierung

- doppelte Bestandsnummern im betroffenen Verein,
- Kollision zwischen einer bereits reservierten Nummer und einer Bestandsnummer,
- fehlender oder inaktiver Standardnummernkreis,
- falsche Vereins- oder Geltungsbereichsreferenz,
- fehlende kanonische Speicherung im Zielvorgang,
- nicht reproduzierbarer Idempotenzschlüssel oder fehlender Transaktionsschutz.

Eine Korrektur von Bestandsnummern ist eine eigene fachlich freizugebende Datenänderung und kein Bestandteil des Audits.
