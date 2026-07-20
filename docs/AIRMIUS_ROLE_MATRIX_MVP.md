# AIRMIUS MVP-Rollenmatrix

Stand: 2026-07-08

Diese Matrix ist die fachliche MVP-Sicht auf Rollen. Technisch bleiben Plattformrollen, Vereinsrollen und Teamrollen getrennt; die Matrix buendelt sie fuer Web, Flutter und Produktentscheidungen.

| Persona | Scope | Plattformrollen | Vereinsrollen | Teamrollen | MVP-Fokus |
| --- | --- | --- | --- | --- | --- |
| Sportler | person | `player`, `youth_player`, `minor_player`, `guest_player` | `member` | `Player`, `Captain` | Profil, Feed, Chat, Events, Training, Dateien, Mitgliedschaftsanfrage |
| Trainer | team | `coach`, `assistant_coach`, `performance_coach`, `fitness_coach`, `team_manager`, `captain` | `trainer`, `member` | `Coach`, `Captain` | Teamkoordination, Trainingsplanung, Anwesenheit, Events, Team-Feed |
| Verein-Admin | club | `club_owner`, `club_admin`, `club_manager`, `academy_manager`, `financial_controller`, `media_manager` | `owner`, `admin`, `manager`, `academy_manager`, `financial_controller` | `ClubPresident`, `Treasurer`, `Coach` | Verein, Mitglieder, Teams, Billing, Dateien, Events, Audit |
| Elternteil | guardian | `parent`, `guardian` | `member` | `ParentContact` | Kind-Verknuepfung, Einwilligung, Benachrichtigungen, Event-Rueckmeldungen |
| Plattform-Admin | platform | `super_admin`, `admin`, `system_admin`, `support`, `redaktor` | - | - | Nutzer, Rollen, Moderation, Billing, Content, Support, Audit |

Technische Quelle: `App\Support\AirmiusRoleMatrix`.

API-Sichtbarkeit: `/api/v1/meta` liefert `data.role_matrix`.
