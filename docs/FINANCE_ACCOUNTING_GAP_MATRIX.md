# Finance Accounting Gap Matrix

T034a audited the current club finance stock for bookkeeping-facing contracts without closing the main MVP checklist. The regression added in `tests/Feature/FinanceAccountingContractRegressionTest.php` freezes the API payload that mobile and web clients consume for money movement, invoices, receipts and open items.

| Scope | Current Artifact | Covered Contract | Remaining Gap |
| --- | --- | --- | --- |
| Einnahme | `payments.purpose=membership_invoice`, `club_finance_entries.type=income` | Paid invoice receipts and free cash income are exposed separately and included in `income_total`. | Revenue account mapping is still coarse and category-driven, not a full chart of accounts. |
| Ausgabe | `club_finance_entries.type=expense` | Bank and cash expenses reduce account balances and are included in `expense_total`. | Supplier master data and payable approval workflow are not modeled. |
| Bank | `payments.method=bank_transfer`, `bank_transactions.status=matched` | Bank payment, imported transaction, invoice and payment ids remain linked in one payload. | Bank exports and reconciliation statements are not immutable ledger documents yet. |
| Kasse | `club_finance_entries.account=cash`, `payments.method=cash` | Cash income/expense affects `cash_balance` independently from bank balance. | Cash count protocol and period-end cashbook lock are missing. |
| Ausgangsrechnung | `invoices.source=membership_contribution` | Partial receipts keep outgoing invoices open with stable `received_amount`, `outstanding_amount` and `is_partially_paid`. | Number range hardening is covered elsewhere; tax/account export is still follow-up. |
| Eingangsrechnung | `club_finance_entries.category=supplier_invoice` | Supplier-style expense receipts can be represented and audited without member invoice context. | No dedicated purchase invoice table, supplier lifecycle, due date or approval status. |
| Offene Posten | `invoice_summary`, `summary.open_invoice_amount`, `summary.open_invoices_count` | Open item totals match invoice balances after partial payment. | Cross-module dunning, dispute handling and payable open-item reporting need dedicated flows. |

Regression invariant:

- A 120.00 EUR outgoing invoice with a 70.00 EUR bank receipt remains open for 50.00 EUR.
- A 70.00 EUR matched bank payment, a 30.00 EUR cash income, a 45.00 EUR bank expense and a 5.00 EUR cash expense produce `bank_balance=25.0`, `cash_balance=25.0`, `total_balance=50.0`, `income_total=100.0` and `expense_total=50.0`.
- `payments`, `bank_transactions`, `finance_entries`, `invoice_summary` and `summary` must stay mutually consistent in the management payload.
