# Club Tariff Audit 2026-09-26

Scope: membership types, contribution rules, billing intervals and recurring contribution invoice runs were audited against the MVP tariff requirements without changing the main checklist.

## Requirement Gap Matrix

| Requirement | Evidence | Regression | Status | Gap / Risk |
| --- | --- | --- | --- | --- |
| Membership types can be configured, sorted, activated and hidden publicly. | `ClubMembershipType` stores `is_active`, `is_public`, `sort_order`, `application_fields`; web click guide covers type creation. | Existing membership-change and render tests cover tariff references. | Covered | No dedicated snapshot in this audit for public ordering. |
| Type-specific contribution rule wins over club fallback. | `ClubContributionCalculator::resolve()` orders non-null `club_membership_type_id` before fallback. | `ClubContributionRulesTest::test_type_specific_rule_wins_over_fallback_and_preserves_discount_snapshot()` | Covered | None. |
| Standard, family, discount and special contribution rule types are supported. | `ClubContributionRule::RULE_TYPES`; web/API validations in `ClubContributionRulesTest`. | Existing `ClubContributionRulesTest` plus new type-priority regression. | Covered | None. |
| Family contribution applies only from two active/pending/paused people in the same normalized family key, including external members. | `familyMemberCount()` counts `club_user` and `club_external_members`; API tests cover registered and external updates. | Existing `test_family_group_assignment_recalculates_all_active_members()` and `test_external_member_can_join_family_group_through_protected_api()`. | Covered | Status set includes `pending` and `paused`, which is product-intentional but should stay visible to finance reviewers. |
| Percentage and fixed discounts reduce one base rule without stacking or going negative. | `discountAmount()` caps percent at 100 and fixed discount at base amount; calculator selects one discount rule. | Existing one-discount test plus new fixed-discount cap in type-priority regression. | Covered | Multiple matching discounts are ordered by recency/id; only one applies by design. |
| Special contributions are one-time only. | Controller/API validation rejects `factor_key=special` unless `billing_interval=once`. | Existing `test_web_contribution_rules_support_family_discount_and_special_types()`. | Covered | Recurring invoice command accepts `once` only for stored member contributions; no automatic rule creation happens there. |
| Monthly, quarterly, yearly and once invoice intervals generate correct periods and next due dates. | `GenerateRecurringContributionInvoices::periodEnd()` and `advanceMembership()`. | `ClubTariffAuditRegressionTest::test_recurring_invoice_run_snapshots_supported_intervals_and_advances_due_dates()` | Covered | Four-monthly/semi-yearly import aliases are normalized elsewhere and are not recurring scheduler intervals. |
| Recurring invoice run is idempotent per member and billing period. | Existing invoice lookup checks source, period start and membership user. | Existing `test_under_year_entry_generates_prorated_snapshot_once()`. | Covered | Legacy fallback by `user_id` remains for old data. |
| Invoice snapshot freezes payer, member, membership type, full amount, billed amount, period and rounding. | `contributionSnapshot()` writes versioned array to `invoices.contribution_snapshot`. | Existing proration test and new interval snapshot test. | Covered | Snapshot captures stored pivot amount, not a live rule re-resolution; tariff changes must update membership pivot before the next run. |
| Existing invoices are not rewritten by later tariff changes. | Membership change test creates September invoice before approving October tariff. | Existing `test_membership_change_updates_future_tariff_without_rewriting_existing_invoice()`. | Covered | None. |
| Legal/policy basis for contribution model versions is preserved separately from calculation. | `club_policy_document_id` links contribution rules to contribution model documents. | Policy document rollout/readiness tests outside this audit. | Partial | This audit does not re-run legal sign-off; production use still depends on external legal/finance approval. |

## Executable Regression Set

Run:

```bash
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan test tests/Feature/ClubContributionRulesTest.php tests/Feature/ClubTariffAuditRegressionTest.php
```

The added regressions focus on:

- calculator snapshots for fallback vs. type-specific rules and fixed discount caps;
- recurring invoice snapshots for monthly, quarterly and once intervals, including payer separation and due-date advancement.

## Open Risks

- The scheduler bills from persisted membership pivot amounts. That is correct for invoice immutability, but operational tariff changes must continue to update the pivot through the approved membership-change or member-edit flows.
- Productive SEPA, DATEV, legal contribution model approval and real pilot finance data remain external release gates and cannot be proven by local tests.
