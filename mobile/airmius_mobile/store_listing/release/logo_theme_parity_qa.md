# Airmius Mobile Logo and Theme Parity QA

Use this runbook before final release evidence is marked as complete.

## Scope

The app must follow the same brand principle as the web app:

- Normal theme uses the dark Airmius wordmark on light backgrounds.
- Dunkel theme uses the white Airmius wordmark on dark backgrounds.
- System theme follows the device brightness.
- Theme choice persists after app restart.

## Required screens

- Login screen.
- Authenticated shell screen.
- Store Release Configuration or Release Candidate Gates screen.

## Evidence to capture

- `logo-theme-normal.png`
- `logo-theme-dark.png`
- `logo-theme-system.png`
- `logo-theme-authenticated-shell.png`

## Test steps

0. Run the static logo asset and mapping check:

```powershell
.\scripts\assert_logo_theme_assets.ps1
```

1. Open the app.
2. Select `Normal` in the Design chooser.
3. Restart the app.
4. Confirm the background is light and the Airmius wordmark is dark/readable.
5. Capture the login screen.
6. Select `Dunkel` in the Design chooser.
7. Restart the app.
8. Confirm the background is dark and the Airmius wordmark is white/readable.
9. Capture the login screen.
10. Select `System` in the Design chooser.
11. Change the device/system appearance if available.
12. Restart the app.
13. Confirm the logo follows the device brightness.
14. Login with the review/demo account.
15. Capture one authenticated shell screen with the Airmius logo visible.

## Pass criteria

- No dark wordmark appears on a dark background.
- No white wordmark appears on a light background.
- The logo remains readable after restart.
- The selected theme and language still persist.
- Screenshots do not expose private user, member, payment or token data.

## Manifest update

After evidence is attached and reviewed:

```powershell
.\scripts\update_release_evidence_gate.ps1 -GateId "logo_theme_parity_qa" -Status "passed" -Note "Normal, Dunkel, System and restart persistence screenshots approved."
```
