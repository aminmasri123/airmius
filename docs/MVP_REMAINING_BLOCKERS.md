# AIRMIUS MVP Remaining Blockers

Stand: 2026-08-09

## Grün verifiziert

- `php artisan test`: 970 bestanden, 4 bewusst übersprungen, 0 fehlgeschlagen, 25.174 Assertions.
- `npm run build`: 1.047 Module und 183 Manifest-Einträge erfolgreich erzeugt; Blog-Lokalisierung liegt als eigener lazy 2,70-KB-Chunk und Kurslokalisierung als gemeinsamer lazy 2,23-KB-Chunk vor. Der kritische arabische Sprachkern bleibt unverändert bei 310,99 KB.
- Composer- und npm-Audit: 0 bekannte Schwachstellen nach gezielten Sicherheitsupdates.
- `flutter test`: 251 bestanden; `flutter analyze`: keine Befunde.
- Release-Preflight: 10 technische Checks bestanden, 0 Fehler und 206 erforderliche Artefakte vorhanden.
- `staging-http-delivery.v1`: produktionsnaher TLS-/Proxy-/Cache-/CORS-/Gastseiten-Audit mit 19 begrenzten Requests und geheimnisfreier Ausgabe ist implementiert und durch 4 Tests mit 39 Assertions abgesichert.
- `query-plan-readiness.v1`: 25 begrenzte, ausschließlich lesende Abfragefamilien, exakte Indexverträge, MySQL-/MariaDB-Analysemodus und geheimnisfreie Evidenz sind implementiert; öffentliche E-Learning-Ergebnisse sind auf 24 pro Seite begrenzt und Marketplace/E-Learning besitzen vier neue Katalogindizes.
- `provider-smoke-readiness.v1`: stagingbegrenzte SMTP-/Firebase-Zustellung sowie Stripe-Test-/PayPal-Sandbox-Connectivity ohne Zahlungsanlage, geschützte Zielübergabe und geheimnisfreie Evidenz sind implementiert; 6 neue Orchestrierungs-/Resilience-Tests mit 62 Assertions sind grün.
- `observability-slo-readiness.v1`: neun verantwortete Signale mit festen SLOs und Alert-Fristen für Verfügbarkeit, API-Fehler/Latenz, Gast-Core-Web-Vitals, Queue, Webhooks, Mail, Push und Offsite-Backup sind implementiert; verbotene Rohdaten, Consent/Mindestgruppe und Runtime-Guards sind mit 5 neuen Tests und 63 Assertions grün.
- `cross-device-experience.v1`: Web-Mobile/-Desktop, Android/iOS, DE/EN/FR/AR/RTL, acht Kernreisen und sieben Accessibility-Gruppen sind mit bestehenden Mobile-/Plattformgates verbunden. Der alte fünfteilige Gerätesmoke kann nicht mehr als vollständig gelten; beide Plattformen benötigen exakt 19 releasegebundene Workflow-, Sprach-, Assistive-Technology-, Scaling- und Privacy-Punkte. 5 neue Tests mit 77 Assertions sind grün.
- `governance-assurance.v1`: Die bestehenden Legal-, DPIA- und unabhängigen Pentest-Gates sind ohne neue Freigabequelle über exakte Releasebindung, getrennte Reviewer-Rollen und ein No-Waiver-Prinzip verbunden. 12 Legal-, 10 Verarbeitungs-, 9 DPIA-, 16 Pentest-Scope- und 8 Abnahmepunkte sind versioniert; lokale Evidenz kann keinen Gate freigeben und lehnt Namen, Rohberichte, Findings, URLs/Pfade, Identifikatoren sowie Secrets ab. 7 neue Tests mit 134 Assertions sind grün.
- `staged-rollout.v1`: 0/5/25/100-Stufen, Kill-Switches, autorisierter Pilot-Override und Gastseitenausschluss sind implementiert.
- Sport-App-Bridge: zentrale Web-/Mobile-Registry, Mi Fitness über normalisierten Health-Connect-/Dateiimport, elf allowlist-validierte Summary-Felder, sichtbarkeits-/mitgliedschaftsgebundene Route-/Teamreferenzen und idempotente Track-Quell-IDs sind implementiert. 6 gezielte Backendtests mit 399 Assertions sowie die vollständigen 251 Flutter-Tests sind grün; die Analyse der drei geänderten Mobile-Dateien meldet keine Befunde.
- Blog-Inhaltssprachen: Datenmodell, Revisionen, gemeinsamer Web-/Mobile-Editorvertrag, DE-Fallback, RTL, echte Canonical-/Hreflang-Ziele, Sitemap, RSS und öffentliche API sind für DE/EN/FR/AR integriert. Eine Unique-Regel verhindert doppelte Varianten; die Abdeckung wird ohne N+1-Abfragen geladen. 8 neue Tests mit 117 Assertions sind grün.
- E-Learning-Inhaltssprachen: eigentümergebundene Kursfamilien, Unique-Regel, Web-/Mobile-Tutorvertrag, exakte Sprache mit deutschem Fallback, RTL, echte Canonical-/Hreflang-Ziele, Sitemap, öffentliche/Mobile-/Commerce-Kataloge und lokalisierte Zertifikatdaten sind für DE/EN/FR/AR integriert. Curriculum, Kaufprodukt, Einschreibung und Fortschritt bleiben sicher je Variante getrennt. 7 neue Tests mit 84 Assertions sind grün.
- Checkout-Idempotenz: Gast-Marketplace, Einzelprodukt, Warenkorb, Add-on, Konto- und Outfit-Abo teilen einen requestgebundenen JSON-/AJAX-Vertrag. Route und konkrete Produkt-/Planparameter sind getrennt, Gast-Session/IP werden nur gehasht verwendet und Bestellung, Positionen sowie Warenkorbverbrauch sind atomar. 6 neue Sicherheitstests mit 44 Assertions und 93 fokussierte Regressionstests mit 2.609 Assertions sind grün.
- Kritische Checkout-UX: Commerce-Bestätigung, Warenkorb und Outfit-Abo nutzen die gemeinsame native, fokusgesicherte Dialogbasis. Kaufentscheidende Commerce-Texte liegen direkt im lazy DE/EN/FR/AR-Seitenchunk; Gast-Versand und Provider-Hinweise sind direkt lokalisiert. 3 neue Vertragsprüfungen sowie 39 fokussierte Tests mit 1.553 Assertions sind grün, ohne das 311-KB-Budget des arabischen Kerns zu erhöhen.
- Gast-Blog-Discovery: RSS wird in DE/EN/FR/AR mit getrennten Caches/ETags, lokalisierten Links, Atom-Sprachalternativen, `Last-Modified`/304 und maximal 30 Beiträgen ausgeliefert. Blogseiten bewerben den passenden Feed bereits im initialen HTML. RSS, Sitemap und robots.txt setzen keine Session-/CSRF-Cookies mehr. 4 neue Tests mit 115 Assertions sowie 46 fokussierte Regressionstests mit 854 Assertions sind grün; es entstand kein neuer Browser-Chunk.
- PWA-Installation: Das Web-App-Manifest ist in DE/EN/FR/AR und RTL lokalisiert, startet sprachstabil, bietet öffentliche Schnellaktionen und unterstützt Smartphone, Tablet sowie Desktop ohne Hochformatzwang. Sprachgetrennte ETags/304 und 24-Stunden-Cache funktionieren ohne Gast-Cookies. Fünf deterministisch erzeugte Icons besitzen echte 180/192/512-Pixel-Abmessungen; Maskable-Varianten erfüllen die sichere Kreiszone. 4 neue Tests mit 187 Assertions sowie 34 fokussierte Regressionstests mit 931 Assertions sind grün, ohne neuen Browser-Chunk.
- Aktueller Preflight nach der E-Learning-Inhaltslokalisierung: 10 technische Checks bestanden, 13 externe Evidenzgates ausstehend, 0 Fehler; Release-Entscheid weiterhin korrekt `no_go`.

## Keine offenen Repository-Featureblocker

Backend, Web-Build, Mobile-Verträge, DE/EN/FR/AR, RTL, technische WCAG-Baseline, Gastseiten, Security-/Privacy-Baseline, Retention, Pilotvorbereitung und Stufenrollout sind automatisiert grün. Offene Nachweise werden nicht als fehlender Anwendungscode dargestellt.

## Extern weiterhin blockierend

- Release-identischen `staging-http-delivery.v1`-Lauf mit aktueller tokenisierter Gastbestellung durchführen und durch DevOps/Platform reviewen.
- `query-plan-readiness.v1` im release-identischen MySQL-/MariaDB-Staging mit `--analyze --strict` ausführen; anschließend spezialisierte Join-, Rollen-, Locking- und Chunking-Pfade sowie das Online-DDL-Vorgehen durch DBA bestätigen.
- `provider-smoke-readiness.v1 --live --strict` mit dediziertem Testpostfach und zustimmendem Testgerät ausführen; anschließend nicht-sensitive Review-Referenzen für Empfang, Checkout, signierte Webhooks, Fehler/Retry, Refund/Gutschrift, Payout und Reconciliation hinterlegen.
- Garmin-Partnerfreigabe sowie reale Mi-Fitness-/Health-Connect- und Google-Fit-/OAuth-Smokes auf freigegebenen Android-/iOS-Testgeräten durchführen; nur nicht-sensitive Ergebnisreferenzen dokumentieren.
- `observability-slo-readiness.v1 --with-runtime --strict` nach mindestens 24 Stunden Staging-Beobachtung ausführen und nicht-sensitive Review-Referenzen für alle neun Dashboards, Alarmproben und Verifikationen hinterlegen; Gastmessungen synthetisch oder nur eingewilligt/aggregiert ab fünf Personen.
- `cross-device-experience.v1 --strict` mit dem vollständigen 19-Punkte-Vertrag auf Android und iOS durchführen; iOS benötigt macOS/Xcode/TestFlight. Danach Mobile-Manifest und Plattformgate mit kurzen Artefaktreferenzen reviewen.
- Muttersprachliche DE/EN/FR/AR-/RTL- und menschliche WCAG-2.2-AA-Abnahme über dieselbe Cross-Device-Matrix für Web-Mobile, Web-Desktop, Android und iOS durchführen; TalkBack, VoiceOver, NVDA/JAWS, Keyboard, Zoom/Reflow und Gastseiten einschließen.
- `governance-assurance.v1 --strict` vorbereiten und anschließend die drei getrennten Freigaben abschließen: Legal über alle 12 Bereiche, DPO-DPIA über 10 Verarbeitungsfamilien/9 Pflichtabschnitte und unabhängiger Penetrationstest über alle 16 Scope-Gruppen; kritische und hohe Befunde beheben und nachtesten.
- 3–5-Vereins-Pilot sechs bis acht Wochen durchführen und anschließend 5/25/100-Rollout mit den definierten Beobachtungsfenstern freigeben.

Die autoritative Liste mit Ownern und Evidenzanforderungen steht in `resources/release/platform_release_gates.json`; der verbindliche Gesamtentscheid kommt aus `php artisan airmius:release-preflight --strict`.
