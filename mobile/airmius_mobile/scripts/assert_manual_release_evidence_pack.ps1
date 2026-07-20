param(
    [string]$EvidenceRoot = "release_evidence\manual"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$TargetRoot = Join-Path $Root $EvidenceRoot
$Failures = New-Object System.Collections.Generic.List[string]

if (-not (Test-Path $TargetRoot)) {
    $Failures.Add("Manual evidence root is missing: $EvidenceRoot. Run scripts\new_manual_release_evidence_pack.ps1 first.")
}

$RequiredGateFiles = @{
    "logo_theme_parity_qa" = @(
        "signoff.md",
        "expected_files.md",
        "logo-theme-normal.png",
        "logo-theme-dark.png",
        "logo-theme-system.png",
        "logo-theme-authenticated-shell.png"
    )
    "store_screenshots" = @(
        "signoff.md",
        "expected_files.md"
    )
    "localization_visual_qa" = @(
        "signoff.md",
        "expected_files.md"
    )
    "real_api_qa" = @(
        "signoff.md",
        "expected_files.md",
        "api-qa-notes.md"
    )
    "real_device_smoke" = @(
        "signoff.md",
        "expected_files.md",
        "android-real-device-smoke.md",
        "ios-real-device-smoke.md"
    )
    "legal_privacy_approval" = @(
        "signoff.md",
        "expected_files.md"
    )
    "store_submission_readiness" = @(
        "signoff.md",
        "expected_files.md",
        "play-console-readiness.md",
        "app-store-connect-readiness.md",
        "testflight-readiness.md",
        "review-account-notes.md",
        "release-notes-approval.md"
    )
    "secure_token_storage_qa" = @(
        "signoff.md",
        "expected_files.md"
    )
}

foreach ($GateId in $RequiredGateFiles.Keys) {
    $GateDir = Join-Path $TargetRoot $GateId

    if (-not (Test-Path $GateDir)) {
        $Failures.Add("Manual evidence gate folder missing: $GateId")
        continue
    }

    foreach ($RequiredFile in $RequiredGateFiles[$GateId]) {
        $FullPath = Join-Path $GateDir $RequiredFile
        if (-not (Test-Path $FullPath)) {
            $Failures.Add("Manual evidence file missing: $GateId\$RequiredFile")
        }
    }
}

if ($Failures.Count -gt 0) {
    Write-Output "Manual release evidence pack check failed:"
    foreach ($Failure in $Failures) {
        Write-Output " - $Failure"
    }
    throw "Manual release evidence pack check failed."
}

Write-Output "Manual release evidence pack check passed."
Write-Output "Manual evidence root: $TargetRoot"
Write-Output "Required manual folders, sign-offs and key evidence placeholders are present."
