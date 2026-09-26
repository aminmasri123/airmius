# Bestehende Vereinsrechnungen gegen Zahlungsdatensätze prüfen

Der administrative Artisan-Befehl `airmius:audit-club-invoice-payments` vergleicht den gespeicherten Rechnungsstatus mit zugeordneten Zahlungen. Er liest ausschließlich Daten. Er ändert weder Status noch Beträge, erstellt keine Zahlungen und löst keine Mahnungen, E-Mails oder Einzüge aus.

Eine Auffälligkeit ist ein Prüfhinweis, kein Beweis für einen Fehler. Insbesondere ältere, ausdrücklich als bezahlt markierte Rechnungen können außerhalb von Airmius bezahlt worden sein. Vor jeder Korrektur sind Bankunterlagen und Altbelege zu prüfen. Der Bericht berechnet keine neue Forderung gegenüber einem Mitglied.

## Aufruf

Der Befehl benötigt administrativen Serverzugang. Die Vereins-ID ist ein Pflichtargument; es gibt keinen impliziten Lauf über alle Vereine. In den Beispielen ist `123` durch die tatsächlich zu prüfende Vereins-ID zu ersetzen.

```bash
# Nur Anzahlen und Erläuterungen:
php artisan airmius:audit-club-invoice-payments 123

# Maschinenlesbare Zusammenfassung:
php artisan airmius:audit-club-invoice-payments 123 --json

# Bis zu 50 auffällige Rechnungen mit interner ID und Centbeträgen:
php artisan airmius:audit-club-invoice-payments 123 --json --details --limit=50

# Nächste Detailseite: ID aus next_after_id des vorherigen Berichts übernehmen:
php artisan airmius:audit-club-invoice-payments 123 --json --details --limit=50 --after-id=456

# Für einen bewussten Prüf-Gate: Exitcode 1 bei offenen Prüfhinweisen:
php artisan airmius:audit-club-invoice-payments 123 --json --strict
```

Exitcode `0` bedeutet, dass der Bericht erstellt wurde; mit `--strict` zusätzlich, dass keine der definierten Auffälligkeiten gefunden wurde. Exitcode `1` mit `--strict` bedeutet fachlichen Prüfbedarf. Ungültige Vereins-ID oder Optionen führen zu Exitcode `2`. Datenbank-/Betriebsfehler sind keine erfolgreiche Prüfung.

`--limit` akzeptiert 1 bis 200 Detailzeilen. Es begrenzt nicht die Prüfung oder Zählung: Rechnungen werden in Blöcken von 250 gelesen. `--after-id` beeinflusst ebenfalls nur die Details; die Gesamtzahlen und der Strict-Status gelten weiterhin für den ganzen Verein. `next_after_id = null` bedeutet, dass keine weitere Detailseite vorhanden ist. `details_remaining` zählt die folgenden Detailzeilen; `details_omitted` alle im aktuellen Bericht nicht enthaltenen Auffälligkeiten, einschließlich vorheriger Seiten.

## Bedeutung der Hinweise

| Code | Prüfung |
|---|---|
| `paid_without_payment_records` | Positive Rechnung ist bezahlt markiert, hat aber keine Zahlungsdatensätze. Altbelege erforderlich. |
| `paid_without_settled_receipts` | Positive Rechnung ist bezahlt markiert; vorhandene Zahlungen sind nicht erfolgreich verbucht, beispielsweise fehlgeschlagen oder zurückgegeben. |
| `paid_below_invoice_amount` | Bezahlt markiert, obwohl erfolgreich verbuchte Zahlungen nicht ausreichen. |
| `open_with_covered_amount` | Offen/überfällig, obwohl erfolgreich verbuchte Zahlungen den positiven Rechnungsbetrag decken. |
| `overpaid` | Erfolgreich verbuchter Betrag übersteigt den Rechnungsbetrag. Eine berechtigte Überzahlung bleibt möglich. |
| `cancelled_with_receipts` | Stornierte Rechnung hat erfolgreich verbuchte Zahlungen. Abwicklung gesondert prüfen. |
| `payment_club_mismatch` | Mindestens eine verknüpfte Zahlung hat eine andere oder fehlende Vereinszuordnung. |
| `invalid_invoice_amount` | Negativer Rechnungsbetrag; fachliche Einordnung erforderlich. |
| `negative_settled_payment` | Mindestens eine erfolgreich verbuchte Zahlung ist negativ; fachliche Einordnung erforderlich. |

Eine Rechnung kann mehrere Hinweise erhalten. Deshalb kann die Summe von `reason_counts` größer als `invoices_flagged` sein. Nur Zahlungen mit Status `paid` zählen als verbuchte Eingänge, entsprechend der bestehenden Rechnungslogik. Bei einer falschen Vereinszuordnung darf der ausgewiesene Betrag nicht als geprüfter Zahlungsnachweis interpretiert werden.

Alle Detailbeträge werden als ganze Cent ausgegeben. `uncovered_amount_cents` zeigt lediglich die rechnerische Differenz. Die operative Behandlung historisch bezahlter Rechnungen bleibt unverändert; `outstanding_amount` wird nicht angepasst.

## Grenzen und Umgang mit Ergebnissen

- Die Standardausgabe enthält keine Rechnungs- oder Personenliste. Details enthalten interne Rechnungs-IDs und Beträge, aber keine Namen, E-Mail-Adressen, Rechnungstitel, Bankverbindungen, Zahlungsreferenzen oder Notizen. Auch Detailberichte nur im berechtigten Verwaltungskreis weitergeben.
- Jede Rechnung wird mit ihren Zahlungsaggregaten in einer lesenden Abfrage geladen. Der gesamte Lauf und mehrere Detailseiten bilden keinen gemeinsamen Datenbanksnapshot. Bei parallelen Buchungen können sich Ergebnisse verändern. Für eine abschließende Abstimmung einen ruhigen Zeitraum oder einen dafür vorgesehenen Datenbankstand verwenden und erneut prüfen.
- Die Prüfung bestätigt keine tatsächliche Bankzahlung, keine vollständige Buchführung und keine Richtigkeit sämtlicher Datensätze. Nicht zu Rechnungen zugeordnete Zahlungen und externe Belege werden hier nicht abgeglichen.
- Der Befehl wird nicht automatisch eingeplant. Es gibt keine Reparaturoption. Ein späterer Korrekturprozess muss Belege, Berechtigungen und nachvollziehbare Änderungen gesondert berücksichtigen.

Die Funktion wurde mit künstlichen Daten in der isolierten SQLite-Testdatenbank geprüft. Ein produktiver Rechnungsbestand wurde bei der Implementierung nicht ausgelesen oder verändert.
