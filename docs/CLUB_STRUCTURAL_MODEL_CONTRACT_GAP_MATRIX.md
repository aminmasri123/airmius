# Vereinsstruktur-Modelle: Contract-Lueckenmatrix T023a

Auditdatum: 2026-09-26

Scope: Mannschaften, Trainingsgruppen, Rollen-/Rollenzuweisungen, Sportjahre/Saisons und Organisationsobjekte gegen T012 sowie T017. Die Hauptcheckliste bleibt unveraendert; T012/T017-Status wird hier nur nachvollziehbar gespiegelt.

## Acht Contract-Anforderungen

| Key | Anforderung |
| --- | --- |
| R1 | Jedes Objekt ist mandantengebunden und darf keine Fremdvereinsreferenzen speichern oder ausliefern. |
| R2 | Abteilungen, Standorte, Trainingsgruppen und Mannschaften bilden eine konsistente Hierarchie; eine Trainingsgruppe darf vorbelegte Abteilung/Standort nicht widerspruechlich ueberschreiben. |
| R3 | Schreib-, Loesch- und Zuordnungsaktionen laufen ueber getrennte T017-Rechte statt pauschaler Managerrollen. |
| R4 | Bereichsrollen wirken nur im zugewiesenen Verein-, Abteilungs- oder Mannschaftsscope und bleiben fail-closed ausserhalb dieses Scopes. |
| R5 | Interne Struktur- und Standortdaten sind fuer Oeffentlichkeit/ehemalige Mitglieder minimiert; aktive berechtigte Mitglieder sehen nur benoetigte Arbeitsdaten. |
| R6 | Sportjahre/Saisons werden nur fuer Saisonplanung verwendet und deuten historische/unzugeordnete Termine nicht rueckwirkend um. |
| R7 | Genutzte Organisations- und Saisonobjekte sind loeschgeschuetzt, bis Zuordnungen bewusst geloest wurden. |
| R8 | Audit- und Evidenzdaten sind nachvollziehbar, aber frei von Freitexten, Adressen, Notizen und personenbezogenen Trainingsdetails. |

## Matrix

| Modell | R1 | R2 | R3 | R4 | R5 | R6 | R7 | R8 | Luecke / Nachweis |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Teams | OK | OK | OK | OK | OK | OK | OK | OK | `TeamSportYearPlanningTest`, `ClubRoleDefinitionTest`, `ClubOrganizationStructureTest` und `ClubStructuralModelContractRegressionTest` decken Vereinsgrenze, Bereichsrechte, Saisonbezug und Loeschschutz ab. |
| Trainingsgruppen | OK | OK | OK | OK | OK | OK | OK | OK | API validiert Vereins-, Abteilungs-, Standort- und Sportjahrbezug. Neue Regression prueft Fremd-Sportjahr, Bereichssichtbarkeit, Department-Scope und Audit-Minimierung gemeinsam. |
| Rollen/Rollenzuweisungen | OK | OK | OK | OK | n/a | n/a | OK | OK | T017-Vertrag wird ueber `ClubPermissions` und scope_key/assignments abgesichert; neue Regression prueft ausdruecklich, dass ein Department-Recht weder global noch in fremder Abteilung wirkt. |
| Sportjahre/Saisons | OK | n/a | OK | OK | n/a | OK | OK | OK | Sportperioden sind typisiert (`sport`) und werden in Mannschafts-/Trainingsgruppenplanung referenziert. Offen bleibt gemaess Hauptcheckliste nur die reale Staging-/Browser-/Geraeteabnahme T014e/T012d2. |
| Organisation | OK | OK | OK | OK | OK | n/a | OK | OK | T012d1-Evidenzvertrag vorhanden; Gesamtanforderung T012 bleibt wegen Bestandsprobe und Realabnahme offen. Keine neue Checklisten-Abhakung in diesem Schritt. |

## Offene Einfuehrungsrisiken

- T012 bleibt bis zur Staging-Bestandsprobe, Web/Android/iOS-Realabnahme und kontrollierten Einfuehrung offen.
- T017 ist funktional breit durchgesetzt; das Restrisiko liegt in Altpfaden, die zukuenftige Aufgaben weiterhin gezielt gegen `ClubPermissions` regressionspruefen muessen.
- Die Matrix ist ein Code-/Contract-Audit. Sie ersetzt keine produktive Migration, keinen echten MySQL-Konkurrenzlauf und keine manuelle Browser-/Geraeteabnahme.
