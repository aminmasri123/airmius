# Airmius Mobile Manual Gate Sign-off Template

Use this template for manual release gates that require human, store-console, API, legal or visual approval.

Do not include passwords, raw tokens, private member data or private payment data in this file.

## Gate metadata

- Gate ID:
- Gate title:
- Owner:
- Reviewer:
- Review date:
- Environment:
- App version:
- Build artifact:

## Evidence summary

- Evidence files:
- Screenshots:
- Logs:
- Store console references:
- External approvals:

## Checks completed

- Scope reviewed:
- Expected result:
- Actual result:
- Privacy check:
- Localization/layout check:
- Logo/theme check:
- Real data exposure check:
- Known exceptions:

## Decision

- Status:
  - Pending
- Decision:
  - No-Go
- Owner note:

## Sign-off

- Owner name:
- Owner role:
- Date:
- Approval note:

## Example status values

- `passed`
- `partial_evidence`
- `blocked`
- `failed`

## Update command

After evidence is attached and the owner approves, update the manifest:

```powershell
.\scripts\update_release_evidence_gate.ps1 -GateId "real_api_qa" -Status "passed" -Note "Owner approved real API QA evidence package."
```
