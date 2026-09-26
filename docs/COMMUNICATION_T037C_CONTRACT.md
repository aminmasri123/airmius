# T037c Kommunikationsvertrag

Scope: Einzelchat, Gruppenchat, Anhaenge, Umfragen, Terminabstimmung und Pflichtbestaetigungen mit Moderations- und Aufbewahrungsregeln. Die Hauptcheckliste bleibt absichtlich unveraendert; dieser Nachweis ist ein lokaler, versionierter Arbeitsstand.

## Vertrag

- Contract: `communication-interaction-readiness.v1`
- Quelle: `App\Support\CommunicationInteractionReadinessRegistry`
- Entscheidung: `local-contract-ready-external-gates-open`
- Offene Gates: externe Zustellung, rechtliche Aufbewahrungsfreigabe, produktiver Loeschlauf und Human-Moderation-SLA.

## Abdeckung

| Bereich | Implementierungsnachweis | Moderation | Aufbewahrung |
| --- | --- | --- | --- |
| Einzelchat | Web/API Chat-Controller, Direct-Message-Privacy, Mobile-Deep-Link | automatische Message-Flags, entfernte Nachrichten nicht ausliefern | eigene ungelesene Nachricht loeschbar, Erasure ersetzt persoenliche Inhalte |
| Gruppenchat | Einladungen, Ownerwechsel, Mitgliederentfernung, joined_at-Grenze | Owner-Management, Systemnachrichten, sichtbare Reports | historische Nachrichten vor Beitritt verborgen, entfernte Mitglieder gesperrt |
| Anhaenge | ChatService, MessageAttachment, Preview-Policy | Attachment-Kontext nur fuer sichtbare Chat-Mitglieder | Loeschen eigener ungelesener Nachricht entfernt Datei und Preview-Zugriff |
| Umfragen | ClubSurvey API und Mobile-Screen | Vereinsrechte fuer Erstellen, Aendern, Schliessen, Loeschen | abgegebene Stimmen blockieren nachtraegliche Manipulation |
| Terminabstimmung | Event-Decision API und Event-Detail-Mobile | Event-Sichtbarkeit und Managerrechte vor Erstellen/Schliessen | geschlossene Entscheidungen bleiben als Event-Audit lesbar |
| Pflichtbestaetigung | Policy-Dokumente, Event-Antwortpflicht, Guardian Consent | Minderjaehrige ohne geloesten Consent sind fail-closed | Dokumentversionen und Antwortfristen werden als Nachweis gefuehrt |

## Regression

Fokussierter Check:

```bash
php artisan test tests/Feature/CommunicationInteractionReadinessContractTest.php
```

Der Test prueft den Registry-Vertrag, die referenzierten Routen/Artefakte, Datenminimierung sowie eine echte Minor-/Scope-Regression fuer Chat-Erstellung.
