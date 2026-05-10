# Airmius Ads-System

Diese Doku beschreibt die aktuelle professionelle Ads-Grundlage in Airmius: Kampagnen, Creatives, Freigabe, Preise, Budgetverbrauch, Algorithmus, Reporting und Conversion-Grundlage.

## 1. Kampagnenstatus

User-Kampagnen werden nicht direkt aktiv geschaltet.

Status:

- `draft`: Entwurf, noch nicht zur Ausspielung bereit.
- `pending_review`: vom User eingereicht und wartet auf Admin-Freigabe.
- `active`: wird ausgespielt, solange Laufzeit und Budget passen.
- `paused`: manuell pausiert.
- `completed`: beendet.
- `rejected`: abgelehnt, idealerweise mit `review_note`.

Empfohlener Workflow:

1. User erstellt Kampagne im Commerce-Bereich.
2. System speichert sie als `pending_review`.
3. Admin prueft Text, Bild, Ziel-URL, Budget, Zielgruppe und Rechtskonformitaet.
4. Admin setzt Status auf `active` oder `rejected`.
5. Nur `active` Kampagnen werden ueber `/ads/active` ausgespielt.

## 2. Creative-Formate

Beim Erstellen wird dem User das passende Bildformat gezeigt.

| Format | Masse | Seitenverhaeltnis | Einsatz |
|---|---:|---:|---|
| Feed Quadrat | 1080 x 1080 px | 1:1 | Marketplace-Karten, Feed |
| Feed Portrait | 1080 x 1350 px | 4:5 | Mobile Feed-Flaeche |
| Story/Reel | 1080 x 1920 px | 9:16 | Vollbild mobil |
| Wide Banner | 1200 x 628 px | 1.91:1 | Sponsor-Bereich, breite Banner |

Bildquellen:

- Upload: JPG, PNG oder WebP bis 8 MB.
- Externe Bild-URL: optional.
- Wenn Upload vorhanden ist, gewinnt Upload vor URL.

## 3. Ziele

Aktuelle Ziele:

- `traffic`: Optimierung auf Klicks.
- `awareness`: Optimierung auf Reichweite/Impressionen.
- `leads`: Grundlage fuer Lead-Conversions.
- `sales`: Grundlage fuer Sales-Conversions.

Wichtig: Leads und Sales brauchen echte Conversion-Events. Die technische Basis ist vorhanden ueber:

`POST /ads/{campaign}/conversion`

Payload:

```json
{
  "event_type": "lead",
  "value_cents": 0,
  "metadata": {
    "source": "contact_form"
  }
}
```

Oder fuer Sales:

```json
{
  "event_type": "sale",
  "value_cents": 60000,
  "metadata": {
    "order_id": 123
  }
}
```

## 4. Preislogik

Preise werden in den Admin-Commerce-Einstellungen gepflegt.

Standardwerte:

| Kennzahl | Setting | Standard |
|---|---|---:|
| CPM | `ads_cpm_cents` | 500 Cent |
| CPC | `ads_cpc_cents` | 30 Cent |
| CPL | `ads_cpl_cents` | 200 Cent |
| CPA | `ads_cpa_percent` | 10 % |
| Mindestbudget | `ads_min_budget_cents` | 1000 Cent |

### CPM

CPM bedeutet Kosten pro 1.000 Impressionen.

Formel:

```text
Kosten pro Impression = CPM / 1000
```

Beispiel:

```text
CPM = 500 Cent
500 / 1000 = 0,5 Cent pro Impression
```

Da Budget in ganzen Cent gespeichert wird, rechnet Airmius mit einem Cent-Intervall:

```text
Intervall = floor(1000 / CPM_Cent)
```

Bei `CPM = 500`:

```text
Intervall = floor(1000 / 500) = 2
```

Das heisst: Jede zweite Impression kostet 1 Cent. Nach 1.000 Impressionen ergibt das 500 Cent.

### CPC

CPC bedeutet Kosten pro Klick.

Formel:

```text
Kosten = Klicks * CPC
```

Beispiel:

```text
CPC = 30 Cent
10 Klicks * 30 Cent = 300 Cent
```

CPC wird aktuell nur berechnet, wenn das Kampagnenziel `traffic` ist.

### CPL

CPL bedeutet Kosten pro Lead.

Formel:

```text
Kosten = Leads * CPL
```

Beispiel:

```text
CPL = 200 Cent
3 Leads * 200 Cent = 600 Cent
```

CPL wird aktuell nur berechnet, wenn das Kampagnenziel `leads` ist.

### CPA

CPA bedeutet Kosten pro Verkauf oder pro Conversion-Wert.

Formel:

```text
Kosten = Conversion-Wert * CPA-Prozent / 100
```

Beispiel:

```text
Warenkorbwert = 60000 Cent
CPA = 10 %
60000 * 10 / 100 = 6000 Cent
```

CPA wird aktuell nur berechnet, wenn das Kampagnenziel `sales` ist.

## 5. Budgetlogik

Jede Kampagne hat:

- `budget_cents`: Gesamtbudget.
- `daily_budget_cents`: Tagesbudget, optional.
- `spent_cents`: bisher verbrauchtes Budget.

Eine Kampagne wird nur ausgespielt, wenn:

```text
status = active
starts_at <= jetzt oder starts_at leer
ends_at >= jetzt oder ends_at leer
spent_cents < budget_cents
heutige Kosten + naechste Kosten <= daily_budget_cents, falls Tagesbudget gesetzt ist
```

Restbudget:

```text
Restbudget = budget_cents - spent_cents
```

Budgetquote:

```text
Budgetquote = Restbudget / budget_cents
```

Wenn `spent_cents >= budget_cents`, wird die Kampagne nicht mehr ausgeliefert.

## 6. Ausspielungsalgorithmus

Der alte Zufall wurde durch gewichtete Auswahl ersetzt.

Grundidee:

1. Lade maximal 50 aktive, laufende und budgetfaehige Kampagnen.
2. Filter optional nach `placement` und `objective`.
3. Berechne fuer jede Kampagne ein Gewicht.
4. Waehle zufaellig, aber gewichtet nach Score.

Gewicht:

```text
Gewicht = 100 * Zielgewicht * Budgetquote * FrischeBoost
```

### Zielgewicht

Traffic:

```text
CTR = Klicks / Impressionen
Zielgewicht = 1 + min(3, CTR * 100)
```

Beispiel:

```text
Klicks = 20
Impressionen = 1000
CTR = 20 / 1000 = 0,02 = 2 %
Zielgewicht = 1 + min(3, 0,02 * 100)
Zielgewicht = 3
```

Awareness:

```text
Zielgewicht = 1 + min(2, 100 / Impressionen)
```

Neue oder wenig ausgelieferte Reichweitenkampagnen bekommen dadurch anfangs mehr Chancen.

Leads/Sales:

```text
Zielgewicht = 1 + min(2, CTR * 60)
```

Das ist nur eine Startlogik. Sobald genug Lead-/Sales-Daten existieren, sollte man hier Conversion-Rate statt CTR verwenden.

### FrischeBoost

Neue Kampagnen brauchen Daten.

```text
wenn Impressionen < 100:
    FrischeBoost = 1,5
sonst:
    FrischeBoost = 1
```

## 7. A/B-Tests pro Kampagne

Airmius unterstützt jetzt mehrere Creatives pro Kampagne. Ein Creative ist eine einzelne Anzeigenvariante mit eigenem Namen, eigener Headline, eigenem Text, eigener Ziel-URL, eigener CTA, eigener Bild-URL und eigener Gewichtung.

Gespeichert wird das in `ad_creatives`. Jedes Ad-Event kann über `ad_creative_id` einer Variante zugeordnet werden. Dadurch sind Impressionen, Klicks, CTR, Kosten und später Conversions pro Variante auswertbar.

### Varianten-Gewichtung

Die Kampagne wird zuerst über den Kampagnenalgorithmus ausgewählt. Danach wählt Airmius innerhalb der Kampagne eine aktive Variante.

Formel:

```text
Creative-Gewicht = manuelles Gewicht * CTR-Boost * FrischeBoost
```

CTR:

```text
CTR = Klicks / Impressionen
```

CTR-Boost:

```text
CTR-Boost = 1 + min(2, CTR * 80)
```

FrischeBoost:

```text
wenn Impressionen < 50:
    FrischeBoost = 1,3
sonst:
    FrischeBoost = 1
```

Beispiel:

```text
Variante A:
Gewicht = 100
Impressionen = 1000
Klicks = 20
CTR = 20 / 1000 = 0,02
CTR-Boost = 1 + min(2, 0,02 * 80) = 2,6
Creative-Gewicht = 100 * 2,6 * 1 = 260

Variante B:
Gewicht = 100
Impressionen = 20
Klicks = 0
CTR-Boost = 1
FrischeBoost = 1,3
Creative-Gewicht = 100 * 1 * 1,3 = 130
```

In diesem Beispiel bekommt Variante A ungefähr doppelt so viele Chancen wie Variante B, weil sie eine bessere Klickrate hat. Variante B wird trotzdem weiter getestet, weil neue Varianten durch den FrischeBoost nicht sofort verdrängt werden.

### Gewinner bewerten

Eine Variante ist erst aussagekräftig, wenn genügend Daten vorhanden sind. Als Startregel:

```text
Mindest-Impressionen pro Variante = 100
Mindest-Klicks pro Variante = 5
```

Danach kann man Varianten vergleichen:

```text
CTR in Prozent = Klicks / Impressionen * 100
Kosten pro Klick = Kosten / Klicks
Kosten pro 1000 Impressionen = Kosten / Impressionen * 1000
```

Für Traffic-Kampagnen gewinnt meist die Variante mit hoher CTR und niedrigem CPC. Für Sales-Kampagnen sollte später zusätzlich Conversion-Rate und Umsatz pro Kosten-Euro genutzt werden.

## 8. Fraud-Grundschutz

Airmius speichert Ad-Events mit Hashes:

- Session-Hash
- IP-Hash
- User-Agent-Hash
- optional User-ID

Direkte Duplikate werden blockiert:

| Event | Sperrfenster |
|---|---:|
| Impression | 10 Minuten |
| Klick | 30 Minuten |
| Lead/Sale | 60 Minuten |

Das ist kein vollstaendiger Fraud-Schutz, aber ein sinnvoller Start gegen Reloads und Mehrfachklicks derselben Session.

## 9. Reporting

Aktuell gespeichert:

- Impressionen
- Klicks
- Kosten
- Tageswerte in `ad_campaign_stats`
- Einzelereignisse in `ad_events`

CTR:

```text
CTR = Klicks / Impressionen * 100
```

Beispiel:

```text
Klicks = 25
Impressionen = 1000
CTR = 25 / 1000 * 100 = 2,5 %
```

CPC effektiv:

```text
Effektiver CPC = spent_cents / Klicks
```

CPM effektiv:

```text
Effektiver CPM = spent_cents / Impressionen * 1000
```

Conversion-Rate:

```text
Conversion-Rate = Conversions / Klicks * 100
```

ROAS:

```text
ROAS = Umsatzwert / Werbekosten
```

Beispiel:

```text
Umsatz = 60000 Cent
Kosten = 6000 Cent
ROAS = 60000 / 6000 = 10
```

## 10. Was noch fehlt fuer Plattform-Niveau

Wichtige naechste Ausbaustufen:

- Admin-Maske zum Bearbeiten bestehender Kampagnen, nicht nur Erstellen.
- Vollstaendiger Review-Prozess mit Vorschau, Ablehnungsgrund und Benachrichtigung.
- Frequency-Capping pro User ueber mehrere Sessions.
- Zielgruppen-Matching gegen echte Profildaten, Rollen, Sportarten und Regionen.
- Conversion-Pixel/SDK fuer externe Seiten.
- Upload direkt pro A/B-Variante, aktuell ist pro Variante eine Bild-URL möglich und der Kampagnen-Upload dient als Fallback.
- Abrechnung/Rechnung pro Werbekunde.
- Fraud-Scoring mit Klickgeschwindigkeit, IP-Clustern und auffaelligen User-Agents.
- Algorithmus fuer Leads/Sales auf Basis echter Conversion-Rate statt CTR.

## 11. Testanleitung

1. Als User `/commerce` oeffnen.
2. Tab `Ads` waehlen.
3. Kampagne mit Bildformat, Budget, Zielgruppe und Bild hochladen.
4. Mindestens zwei A/B-Varianten mit unterschiedlichen Headlines, Texten oder Bild-URLs anlegen.
5. Speichern.
6. Als Admin `/admin/commerce` oeffnen.
7. Kampagne pruefen und Status auf `active` setzen.
8. `/ads/active` aufrufen.
9. Pruefen, ob Kampagne als JSON mit `creative_id` geliefert wird.
10. `click_url` oeffnen.
11. Pruefen, ob Klicks und Kosten bei Kampagne und Variante steigen.
12. Fuer Leads/Sales `POST /ads/{campaign}/conversion` senden.
