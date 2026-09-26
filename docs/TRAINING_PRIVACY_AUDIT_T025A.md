# T025a Training privacy audit

Date: 2026-09-26
Scope: exercise library, training plans, training logs, performance data, role boundaries, and data-minimization regressions.
Main checklist status: not modified.

## Gap matrix

| Area | Current evidence | Privacy and role boundary | Gap / risk | Regression coverage |
| --- | --- | --- | --- | --- |
| Exercise library | `TrainingExerciseController` scopes personal, club, and team exercises; `TrainingExerciseLibraryTest` covers personal isolation, independent view/edit/delete rights, scoped department delegation, explicit deny precedence, filters, and protected media minimization. | Personal exercises are owner-only; club/team exercises require scoped club permissions or team membership/staff permissions. Protected media returns `file_id`, `kind`, `caption`, and `protected`, without path or URL. | Low residual risk: media download remains delegated to the file APIs, so this controller depends on those APIs to reauthorize file reads. | Existing `TrainingExerciseLibraryTest::test_exercise_filters_and_protected_media_contract_are_scoped_to_visible_exercises`. |
| Training plans | `TrainingController::visiblePlans` limits plans to creator, eligible team/training-group audiences, and explicit assignments. `TrainingResourceService::normalizePlanAudience` blocks non-trainers from sharing self-created plans to teams or other users. | Athletes can create self plans; team/private sharing requires trainer-level plan management. Write/delete operations use `canWritePlan`/`canDeletePlan`. | Medium residual risk: `TrainingPlanResource` exposes plan settings/history/handovers to anyone who can see the plan; keep history free of medical/wellness notes and continue to validate assignment expansion. | Existing plan CRUD and workflow tests cover audience normalization and management. Recommended follow-up: assert non-assigned team members cannot see `team_mode=individual` plans. |
| Training logs | `TrainingLogAccessService` centralizes list/detail visibility. `private` logs are visible only to owner/creator/full-access; `trainer` logs are visible to manageable athlete staff; `team` logs are visible to team members. | Log list and detail endpoints both call `visibleLogs`; feedback role derives from owner/trainer/admin/manageable-athlete status. | Medium residual risk: visible logs include notes, metrics, wellness, entries, and feedback, so any scope mistake leaks sensitive performance/health context. | Added `TrainingPrivacyBoundaryRegressionTest` for private-vs-team-vs-trainer visibility and detail 404s. |
| Performance data | Log resource includes duration, distance, calories, intensity, notes, metrics, entries, and route references. Route references are summarized through `TrainingResourceService`/route link service rather than raw geometry in the log resource. | Performance data follows log visibility. Exercise library filters expose metadata only for visible exercises. | Medium residual risk: analytics endpoints were not changed in this task; keep analytics on aggregate/minimized outputs and avoid exposing raw notes or wellness fields. | Existing `TrainingAnalyticsApiTest`; this audit adds log-boundary tests where raw performance values are highest sensitivity. |
| Minors/guardians | Guardian controllers and services have their own consent and child-management gates; training logs do not grant guardian access by default through `TrainingLogAccessService`. | Guardian access should be explicit, consent-aware, and separate from coach/team visibility. | Open product decision: whether guardians may see child training summaries, and if so which minimized fields. Avoid implicitly granting raw logs through team membership. | Existing guardian tests; no new guardian training expansion added. |

## Data-minimization checklist

- Exercise protected media payloads must never include storage paths, signed URLs, or raw file URLs.
- Training log visibility must be tested at list and detail level; list filtering alone is insufficient.
- `private` log scope must remain stronger than team membership and trainer/manageable-athlete relationships.
- Team-visible logs may include notes/wellness by current contract; changing that requires a separate product/privacy decision and tests.
- Plan history and handover notes should remain operational and must not become a store for medical, wellness, or confidential athlete notes.

## Recommended next tests

- Individual team plan assignment: a team member not individually assigned must not see a `team_mode=individual` plan even when they belong to the same team.
- Training analytics: non-owner/team viewers should receive only allowed aggregate fields and never raw notes, wellness metrics, or entry notes.
- Guardian training summary: if enabled, assert a minimized summary contract and explicit denial for raw log detail.
