param(
    [string]$EvidenceRoot = "release_evidence\manual",
    [switch]$Force
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$TargetRoot = Join-Path $Root $EvidenceRoot
$Template = Join-Path $Root "store_listing\release\manual_gate_signoff_template.md"

if (-not (Test-Path $Template)) {
    throw "Manual gate sign-off template not found: $Template"
}

$Gates = @(
    @{
        Id = "logo_theme_parity_qa"
        Title = "Logo and Theme Parity QA"
        EvidenceFiles = @(
            "logo-theme-normal.png",
            "logo-theme-dark.png",
            "logo-theme-system.png",
            "logo-theme-authenticated-shell.png"
        )
    },
    @{
        Id = "store_screenshots"
        Title = "Final Store Screenshots"
        EvidenceFiles = @(
            "android-login.png",
            "android-clubs.png",
            "android-membership-form.png",
            "ios-login.png",
            "ios-clubs.png",
            "ios-membership-form.png"
        )
    },
    @{
        Id = "localization_visual_qa"
        Title = "Localization Visual QA"
        EvidenceFiles = @(
            "de-core-flow.png",
            "en-core-flow.png",
            "fr-core-flow.png",
            "ar-rtl-core-flow.png"
        )
    },
    @{
        Id = "real_api_qa"
        Title = "Real Laravel API QA"
        EvidenceFiles = @(
            "laravel-api-smoke.log",
            "membership-api-smoke.log",
            "api-qa-notes.md"
        )
    },
    @{
        Id = "legal_privacy_approval"
        Title = "Legal and Privacy Approval"
        EvidenceFiles = @(
            "play-data-safety-approval.md",
            "app-store-privacy-approval.md"
        )
    },
    @{
        Id = "store_submission_readiness"
        Title = "Store Submission Readiness"
        EvidenceFiles = @(
            "play-console-readiness.md",
            "app-store-connect-readiness.md",
            "testflight-readiness.md",
            "review-account-notes.md",
            "release-notes-approval.md"
        )
    },
    @{
        Id = "secure_token_storage_qa"
        Title = "Secure Token Storage QA"
        EvidenceFiles = @(
            "android-session-restore.png",
            "android-logout-cleared.png",
            "ios-session-restore.png",
            "ios-logout-cleared.png"
        )
    }
)

New-Item -ItemType Directory -Path $TargetRoot -Force | Out-Null

foreach ($Gate in $Gates) {
    $GateDir = Join-Path $TargetRoot $Gate.Id
    New-Item -ItemType Directory -Path $GateDir -Force | Out-Null

    $SignoffPath = Join-Path $GateDir "signoff.md"
    if ($Force -or -not (Test-Path $SignoffPath)) {
        Copy-Item -Path $Template -Destination $SignoffPath -Force
        (Get-Content -Raw -Path $SignoffPath).
            Replace("- Gate ID:", "- Gate ID: $($Gate.Id)").
            Replace("- Gate title:", "- Gate title: $($Gate.Title)") |
            Set-Content -Path $SignoffPath -Encoding UTF8
    }

    $ChecklistPath = Join-Path $GateDir "expected_files.md"
    if ($Force -or -not (Test-Path $ChecklistPath)) {
        $Lines = @(
            "# $($Gate.Title) Expected Evidence",
            "",
            "Place the following evidence files in this folder before marking the gate as passed:",
            ""
        )

        foreach ($EvidenceFile in $Gate.EvidenceFiles) {
            $Lines += "- `$EvidenceFile`"
        }

        $Lines += ""
        $Lines += "Do not include passwords, raw tokens, private member data or private payment data."
        $Lines | Set-Content -Path $ChecklistPath -Encoding UTF8
    }

    foreach ($EvidenceFile in $Gate.EvidenceFiles) {
        if ($EvidenceFile -notlike "*.md") {
            continue
        }

        $EvidenceFilePath = Join-Path $GateDir $EvidenceFile
        if ($Force -or -not (Test-Path $EvidenceFilePath)) {
            @(
                "# $($Gate.Title) Evidence Notes",
                "",
                "- Gate ID: $($Gate.Id)",
                "- Evidence file: $EvidenceFile",
                "- Status: Pending",
                "- Reviewer:",
                "- Review date:",
                "",
                "## Notes",
                "",
                "Add only safe release evidence notes here.",
                "",
                "Do not include passwords, raw tokens, private member data or private payment data."
            ) | Set-Content -Path $EvidenceFilePath -Encoding UTF8
        }
    }
}

Write-Output "Manual release evidence pack prepared:"
Write-Output " - $TargetRoot"
Write-Output "Fill each gate folder with screenshots/logs/approvals, then update release_evidence_manifest.json via scripts\update_release_evidence_gate.ps1."
