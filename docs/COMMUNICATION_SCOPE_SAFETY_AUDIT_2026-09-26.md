# Kommunikations-, Scope- und Minderjaehrigenschutz-Audit

Stand: 2026-09-26

Dieses Audit prueft Nachrichten, Chat, Vereinsankuendigungen und Benachrichtigungen auf Vereins-/Abteilungs-/Teamscope, Moderation und Minderjaehrigenschutz. Die Hauptcheckliste bleibt unveraendert; diese Datei ist ein eigenstaendiges Arbeitsartefakt fuer Folge-Tasks.

## Gepruefter Bestand

| Bereich | Bestand | Absicherung |
| --- | --- | --- |
| Web-Chat | `ConversationController` erzwingt Teilnehmerzugriff, Freundschaft/DM-Privacy bei Direkt- und Gruppenchats, `joined_at`-Sichtgrenzen, Mute, Owner-Aktionen und Message-Moderation beim Senden. | `tests/Feature/ChatSecurityTest.php` deckt Besitzerrechte, sichtbare Historie, Datei-Preview, entfernte Mitglieder und Web-Read-State ab. |
| Mobile Chat API | `Api/V1/ChatController` spiegelt die Web-Regeln, filtert Nachrichten nach `joined_at`, schuetzt Deep Links, erzeugt Receipts und sendet Chat-Notifications dedupliziert. | `tests/Feature/MobileChatMessageApiTest.php` deckt Einladungen, Rejoin-Historie, Deep Links, Hide/Delete/Read/Reaction-Schutz ab. |
| Vereinsankuendigungen | `ClubAnnouncementController` trennt Draft/Schedule/Publish, nutzt `CommunicationRecipientSegment`, speichert vertrauliche Segment-Snapshots und prueft Team-Scoped Permissions. | `tests/Feature/ClubAnnouncementApiTest.php` deckt all_members/team, aktive Mitgliedschaft, Deduplication, Edit/Publish/Delete-Scope ab. |
| Notifications | `NotificationController` ist user-scoped, blendet `chat.message` im allgemeinen Center aus und markiert nur eigene Non-Chat-Notifications. | `tests/Feature/NotificationCenterFeatureTest.php` deckt Owner-Scope, Chat-Ausnahme, Web/Mobile Action Links und Delete/Read ab. |
| Moderation | `ContentReportController`, `ModerationDsaProcessTest` und Admin-Moderation decken Reports, Flags, DSA-Entscheidung, Appeal und Logs ab; Messages sind reportable. | `tests/Feature/ModerationDsaProcessTest.php`, `tests/Feature/FeedTest.php`, `docs/DSA_MODERATION_PROCESS.md`. |
| Minderjaehrige/Guardian | `MinorSafety`, `EnsureGuardianConsentResolved`, `GuardianController` und Club-Guardian-Beziehungen erzwingen Consent, Privacy Defaults und sichere Guardian-Sichten. | `docs/MINOR_SAFETY_CONCEPT.md`, `tests/Feature/GuardianAccessFlowTest.php`, `tests/Feature/ClubGuardianRelationshipManagementTest.php`, `tests/Feature/GuardianChildRelationshipServiceTest.php`. |
| Safety-Tickets | `SupportTicketController::storeSafetyReport` legt vertrauliche Safety-Faelle mit Club/Department/Team-Kontext, anonymem Modus und confidential audit an. | Noch kein dedizierter Kommunikations-Scope-Regressionsanker im Auditbestand gefunden. |

## Lueckenmatrix

| ID | Risiko | Beobachtung | Impact | Prioritaet | Empfohlene Regression |
| --- | --- | --- | --- | --- | --- |
| COM-01 | Club-/Team-Scope bei Direct/Group Chat bleibt semantisch weich. | Direct und Group Conversations koennen `club_id` tragen, aber Teilnehmer werden primar ueber Freundschaft/DM-Privacy autorisiert; es gibt keinen Test, der verhindert, dass ein frei gesetzter `club_id` irrefuehrend fremde Vereinskontexte an Chat-Dateien oder Notifications haengt. | Falscher Vereinskontext in Attachments, Audits oder Deep Links kann spaeter Datenklassifikation und Support-Auswertung verfaelschen. | Hoch | Test: Group/Direct Chat mit fremdem `club_id` darf entweder abgelehnt werden oder `club_id` nur setzen, wenn alle Teilnehmer aktive Mitglieder dieses Clubs sind. |
| COM-02 | Team-Chat synchronisiert Teammitglieder, aber Minderjaehrigenregeln sind nicht als Teamchat-Sonderfall belegt. | Teamchat-Erstellung fuegt Team-User automatisch hinzu. `MinorSafety::canDirectMessage` schuetzt Direct/Group ueber `allowsDirectMessagesFrom`, der Teamchat-Pfad prueft aber Teammitgliedschaft statt Guardian/Friendship. | Minderjaehrige koennen in Teamchats korrekt erwartet sein, brauchen aber explizite Consent-Gates und Tests fuer Pending/Revoked Guardian Consent. | Hoch | Test: Minderjaehriger mit `minor_pending_consent` darf Teamchat nicht nutzen; nach Consent darf er nur eigene Teamchats sehen/senden. |
| COM-03 | Department-Scope fehlt fuer Ankuendigungen und Chat. | Announcements kennen `all_members` und `team`; Department-Struktur existiert, aber keine Department-Audience. Support-Tickets speichern Department-Scope, Kommunikation nicht. | Vereine mit Jugend-/Abteilungstrennung muessen auf Team-Workarounds ausweichen; falsche Zielgruppen sind wahrscheinlicher. | Mittel | Test/Feature: `audience_type=department` mit aktiver Mitgliedschaft und Department-Team-Schnittmenge oder bewusste Produktentscheidung dokumentieren. |
| COM-04 | Announcement-Inhalte laufen nicht durch automatische Moderation. | Chat-Initial- und Chat-Nachrichten rufen `ModerationService::flagIfNeeded`; Announcements publizieren Titel/Body ohne Moderationflag. | Missbrauch durch Personen mit Announcement-Rechten erzeugt breite, sofortige Push/In-App-Verteilung ohne Pre-/Post-Flag. | Hoch | Test: toxischer Announcement-Body erzeugt `ModerationFlag` und verhindert oder markiert Publish, je nach Policy. |
| COM-05 | Notifications sind user-scoped, aber Action-URLs verlassen sich auf nachgelagerte Zielautorisierung. | Notification API gibt safe defaults und Legacy-Rewrites aus; fuer beliebige `data.url` bleibt die Sicherheit beim Zielcontroller. | Niedrig bis mittel, solange Zielrouten korrekt autorisieren; Regressionen entstehen bei neuen Notification-Typen. | Mittel | Contract-Test: neue Club/Team/Message Notification-Typen muessen `club_id/team_id/message_id` enthalten und auf safe mobile/web route normalisiert werden. |
| COM-06 | Message-Reports pruefen aktuelle Conversation-Mitgliedschaft, nicht historische Sichtbarkeit. | `ContentReportController::authorizeReport` prueft bei Messages nur aktuelle Conversation-Teilnahme. Die Mobile Deep-Link-Logik beruecksichtigt `joined_at`, der Report-Pfad nicht explizit. | Spaet hinzugefuegte Gruppenmitglieder koennten alte Message-IDs reporten, falls sie IDs erraten/erhalten. | Hoch | Test: neues Gruppenmitglied darf Message vor `joined_at` nicht melden; versteckte Message sollte nicht vom versteckenden User reportbar sein oder bewusst erlaubt dokumentiert werden. |
| COM-07 | Confidential Safety Reports sind gut modelliert, aber Scope-Regressionen fehlen. | Public Safety Reports validieren Club/Department/Team ueber `SupportAccessService::publicClubContext`; Audit fand keine gezielte Testabdeckung fuer fremde Department/Team-Kombinationen. | Falscher Fallkontext kann Safeguarding-Zustaendigkeiten fehlleiten. | Hoch | Test: Team muss zum Club und optional zur Department gehoeren; fremde Kombinationen werden 422/404, anonyme Reports leaken keine Kontaktdaten. |
| COM-08 | Moderationsentscheidung fuer entfernte Messages ist nicht als Web/Mobile-Sichtbarkeitstest gekoppelt. | DSA-Test entfernt Posts; Message-Deletion/Moderation existiert, Chat-Listen filtern `moderation_status != removed` im Web. Mobile Message-Listen zeigen keinen expliziten `moderation_status`-Filter im gelesenen Abschnitt. | Entfernte Messages koennten mobil sichtbar bleiben, wenn sie nicht hart geloescht werden. | Hoch | Test: Admin actioned report fuer Message entfernt/verbirgt sie in Web-Liste, Mobile-Liste, Deep Link, Notification-Open. |
| COM-09 | Notification-Read-State ist bewusst vom Chat getrennt, aber Cross-Surface-Ack braucht Guard. | Chat read markiert `chat.message`; Notification Center mark-all ignoriert Chat. | Regressionsrisiko: ein zukuenftiger Refactor markiert Chat im Notification Center als gelesen. | Niedrig | Bestehender Test behalten; zusaetzlich API+Web Chat-Read pruefen, dass nur Conversation-Notifications dieser Conversation gelesen werden. |
| COM-10 | Guardian/Minor Safety ist dokumentiert, aber Kommunikationsmatrix fehlt. | Es gibt ein Minderjaehrigen-Konzept, aber keine Matrix fuer Direct, Group, Teamchat, Announcement, Notification, Safety Report. | Unklare Produktentscheidungen bei Vereinskommunikation mit U16. | Mittel | Ergaenze `docs/MINOR_SAFETY_CONCEPT.md` oder ein Folge-Dokument mit Kanal x Actor x Consent-State. |

## Sicherheitsregressionen fuer den naechsten Implementierungsblock

1. `CommunicationScopeSafetyRegressionTest::test_direct_and_group_chat_reject_foreign_club_context`
   - Actor, Friend, Stranger, zwei Clubs.
   - Direct/Group mit `club_id` eines Clubs, in dem nicht alle Teilnehmer aktiv sind, darf keinen Conversation-Kontext oder File-Kontext auf diesen Club setzen.

2. `CommunicationScopeSafetyRegressionTest::test_pending_minor_cannot_send_or_receive_team_chat_until_guardian_consent`
   - U16 mit `minor_pending_consent` im Team.
   - API `GET /chat/conversations`, `POST /chat/conversations/{team}/messages` und Web-Index duerfen keine normale Chatnutzung erlauben; nach Consent nur eigenes Team.

3. `CommunicationScopeSafetyRegressionTest::test_new_group_member_cannot_report_pre_join_message`
   - Message vor `joined_at`, neuer User kennt Message-ID.
   - `POST /api/v1/reports` mit `type=message` muss forbidden sein.

4. `CommunicationScopeSafetyRegressionTest::test_message_moderation_removal_hides_content_on_web_mobile_and_deep_link`
   - Report Message, Admin `remove_content=true`.
   - Web-Conversation, Mobile list und `/api/v1/chat/messages/{id}` duerfen entfernten Inhalt nicht liefern.

5. `ClubAnnouncementSafetyRegressionTest::test_announcement_publish_runs_moderation_or_blocks_flagged_content`
   - Announcement mit moderationspflichtigem Body.
   - Erwartung je nach Policy: Draft bleibt unveroeffentlicht oder `ModerationFlag` wird erstellt und Publisher sieht Status.

6. `SupportSafetyScopeRegressionTest::test_public_safety_report_rejects_cross_club_department_team_context`
   - Club A mit Department A/Team A, Club B mit Team B.
   - Safety Report fuer Club A + Team B oder Department B muss abgelehnt werden; anonymer Payload darf keine Kontaktfelder enthalten.

7. `NotificationRoutingSafetyRegressionTest::test_message_and_announcement_notifications_only_open_authorized_targets`
   - Notification fuer Message/Announcement wird an berechtigte und unberechtigte User simuliert.
   - Detailroute bleibt owner-scoped; Zielroute liefert 403/404 fuer unberechtigte User.

## Produktentscheidungen, die vor Implementierung geklaert werden sollten

- Sollen Direct/Group Chats ueberhaupt einen `club_id` tragen duerfen, wenn sie nicht team- oder eventgebunden sind?
- Sind Teamchats fuer U16 ohne Guardian Consent komplett gesperrt oder duerfen sie als Vereinsbetrieb mit eingeschraenkter Sicht benutzt werden?
- Sollen Ankuendigungen vor Publish automatisch blockiert werden oder nur als Moderationsfall erscheinen?
- Wird Department-Kommunikation im MVP benoetigt, oder bleibt Team/all_members bewusst die einzige Zielgruppenauswahl?

## Kurzfazit

Der Bestand hat solide Grundlagen fuer Owner-Scope, Teilnehmerzugriff, Announcement-Segmente, Notification-Ownership, DSA-Logs und Guardian Consent. Die groessten Release-Risiken liegen an den Uebergaengen zwischen diesen Systemen: frei gesetzter Chat-Kontext, Minderjaehrige in Team-/Gruppenchats, Message-Reporting ohne historische Sichtbarkeitspruefung, Announcement-Moderation und Safety-Report-Scope.
