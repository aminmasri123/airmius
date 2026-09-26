# Datei-/Ordnerrechte: Lueckenmatrix

Stand: 2026-09-26

## Bewertete Schutzflaeche

| Objektkontext | Direkter File-Scope | Direkter Folder-Scope | Rollenmodell | Download-Schutz | Luecke | Regression |
| --- | --- | --- | --- | --- | --- | --- |
| Mitglied/User | `files.user_id` | `folders.user_id` | Besitzerrechte, globale `file.*` Rechte | Besitzer darf herunterladen; fremde Dateien nur ueber sichtbare Chat-/Antragskontexte | Kein dedizierter Mitgliederakten-Scope ausser User/Club | `FileFolderPermissionAuditTest::test_file_and_folder_scope_columns_cover_members_teams_and_events_but_not_project_contract_or_knowledge_domains` |
| Team | `files.team_id` | `folders.team_id` | Club-Rollen mit Team- oder Department-Scope ueber `ClubPermissions::FILES_*` | `FILES_VIEW` erlaubt Vorschau, nicht Download; `FILES_EXPORT` erlaubt Download; `FILES_SHARE` erlaubt Teilen, nicht Download | Download ist sauber getrennt, muss gegen Regressionen gehalten werden | `FileFolderPermissionAuditTest::test_team_file_roles_keep_view_export_and_share_download_boundaries_separate` |
| Projekt/Job | Nicht vorhanden | Nicht vorhanden | Jobs/Rekrutierung haben eigene Rechte (`JOBS_*`, `RECRUITING_*`) | Kein generischer Datei-/Ordnerdownload an `OrganizationJob` gebunden | Projektanhaenge koennen nicht objektgenau auf Job-/Projektrollen abgebildet werden | Scope-Spalten-Test markiert Luecke |
| Veranstaltung | `files.event_id` | `folders.event_id` | Sichtbarkeit ueber `Event::visibleTo()` sowie Club-/Team-Scope-Rollen | Teilnehmende/sichtbare Events koennen Dateien herunterladen, sofern Event sichtbar ist; Rollen koennen ueber Club/Team wirken | Event-Sichtbarkeit ist breiter als explizites Exportrecht und bleibt bewusst zu pruefen | `FileFolderPermissionAuditTest::test_event_file_download_follows_event_visibility_and_blocks_unrelated_members` |
| Vertrag | Nicht vorhanden fuer Operating Contracts; ClubPolicyDocument nutzt `file_id` | Nicht vorhanden | Operating Contracts: Admin-Routen; ClubPolicyDocument: `POLICY_DOCUMENTS_*` | ClubPolicyDocument-Download ist ueber `POLICY_DOCUMENTS_DOWNLOAD` oder public Flag geschuetzt; Operating Contracts haben keinen File-Scope | Vertragsdateien ausser ClubPolicyDocument koennen nicht generisch objektgenau gescoped werden | `FileFolderPermissionAuditTest::test_policy_document_download_requires_document_download_permission` und Scope-Spalten-Test |
| Wissensartikel | Nicht vorhanden fuer `LearningCourse`; Kursmedien nutzen eigene Kurs-/Marketplace-Logik | Nicht vorhanden | Learning/Studio- und Marketplace-Workflows | Zertifikate und Kurszugriffe sind eigene Endpunkte, nicht File-/Folder-Policy | Wissensartikelanhaenge koennen nicht ueber generische File-/Folder-Rollen objektgenau abgebildet werden | Scope-Spalten-Test markiert Luecke |

## Ausfuehrbare Sicherheitsannahmen

- Anzeigen, Export/Download, Teilen, Bearbeiten und Loeschen bleiben getrennte Aktionen.
- `FILES_VIEW` darf keine Download-Freigabe implizieren.
- `FILES_SHARE` darf keine Download-Freigabe implizieren.
- `FILES_EXPORT` darf Download erlauben, aber Teilen nicht automatisch freischalten.
- ClubPolicyDocument-Downloads bleiben getrennt von normalen File-Downloads und benoetigen `POLICY_DOCUMENTS_DOWNLOAD`, sofern das Dokument nicht public ist.
- Projekt/Job-, Vertrags- und Wissensartikelanhaenge bleiben als Luecke sichtbar, bis direkte Scopes oder explizite Objektanhaenge modelliert werden.

## Empfohlene Folgemassnahmen

1. Einen gemeinsamen Attachment-Scope fuer `organization_job_id`, `operating_contract_id` und `learning_course_id` einfuehren oder bewusst separate Attachment-Tabellen mit Policies nutzen.
2. Downloadrechte fuer Event-Dateien fachlich bestaetigen: aktuelle Logik folgt Event-Sichtbarkeit, nicht zwingend `FILES_EXPORT`.
3. Die Lueckenmatrix aktualisieren, sobald neue Scope-Spalten, Polymorphic Attachments oder Objekt-Policies eingefuehrt werden.
