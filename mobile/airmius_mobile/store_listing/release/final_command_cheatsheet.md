# Airmius Mobile Final Command Cheatsheet

Use this short command list when the operator is ready to collect real release evidence.

## Current state

- Product preparation: 99%.
- Remaining product work: 1% real evidence execution.
- Do not claim 100% until final Go/No-Go passes with direct evidence.

## 1. Check local readiness

If Android `cmdline-tools`, `adb`, Java or licenses are missing, first follow:

- `store_listing/release/windows_android_setup_runbook.md`

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\assert_local_release_prerequisites.ps1 -FlutterCommand "C:\flutter\bin\flutter.bat"
```

## 2. Run the first evidence pipeline

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_full_release_evidence_pipeline.ps1 -ApiBaseUrl "https://app.airmius.com" -FlutterCommand "C:\flutter\bin\flutter.bat"
```

## 3. Prepare manual evidence folders

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\new_manual_release_evidence_pack.ps1
```

## 4. Complete manual evidence

Follow:

- `store_listing/release/manual_evidence_gates.md`
- `store_listing/release/logo_theme_parity_qa.md`
- `store_listing/release/secure_token_storage_qa.md`
- `store_listing/release/store_review_account_runbook.md`
- `store_listing/release/store_release_notes.md`

## 5. Check evidence status and next steps

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\show_release_evidence_status.ps1
.\scripts\show_next_release_steps.ps1
.\scripts\export_next_release_steps_report.ps1
```

## 6. Update manually approved gates

Example:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\update_release_evidence_gate.ps1 -GateId "real_api_qa" -Status "passed" -Note "Evidence attached in manual evidence pack."
```

## 7. Run final Go/No-Go

Only after every manual gate has direct evidence:

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_full_release_evidence_pipeline.ps1 -ApiBaseUrl "https://app.airmius.com" -FlutterCommand "C:\flutter\bin\flutter.bat" -RunGoNoGo
```

## 8. Final outputs

- `store_listing/release/airmius_release_evidence_bundle.zip`
- `store_listing/release/generated_release_evidence_report.md`
- `store_listing/release/generated_next_release_steps.md`

## 9. Store submission readiness

Before submitting to Play Console, App Store Connect or TestFlight, follow:

- `store_listing/release/store_submission_readiness_runbook.md`

Then update the matching gate after sign-off:

```powershell
.\scripts\update_release_evidence_gate.ps1 -GateId "store_submission_readiness" -Status "passed" -Note "Store submission readiness approved in manual evidence pack."
```

## Hard rule

If any evaluated gate is not `passed`, the release remains `NO-GO`.
