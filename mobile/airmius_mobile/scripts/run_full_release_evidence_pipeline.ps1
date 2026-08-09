param(
    [string]$ApiBaseUrl = "https://airmius.com",
    [string]$FlutterCommand = "flutter",
    [switch]$RunGoNoGo
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")

Push-Location $Root
try {
    Write-Output "Airmius Mobile full release evidence pipeline"
    Write-Output "API base URL: $ApiBaseUrl"
    Write-Output "Flutter command: $FlutterCommand"
    Write-Output ""

    Write-Output "Step 1: Validate local release prerequisites"
    $PrerequisitesLog = Join-Path $Root "release_evidence\local-release-prerequisites.log"
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $PrerequisitesLog) | Out-Null
    .\scripts\assert_local_release_prerequisites.ps1 -FlutterCommand $FlutterCommand 2>&1 | Tee-Object -FilePath $PrerequisitesLog

    Write-Output ""
    Write-Output "Step 2: Sync evidence manifest after local prerequisites"
    .\scripts\sync_release_evidence_manifest.ps1

    Write-Output ""
    Write-Output "Step 3: Release candidate checks"
    .\scripts\release_candidate_checks.ps1 -ApiBaseUrl $ApiBaseUrl -FlutterCommand $FlutterCommand

    Write-Output ""
    Write-Output "Step 4: Validate release version consistency"
    $VersionConsistencyLog = Join-Path $Root "release_evidence\release-version-consistency.log"
    .\scripts\assert_release_version_consistency.ps1 2>&1 | Tee-Object -FilePath $VersionConsistencyLog

    Write-Output ""
    Write-Output "Step 5: Sync evidence manifest from generated logs/artifacts"
    .\scripts\sync_release_evidence_manifest.ps1

    Write-Output ""
    Write-Output "Step 6: Validate release evidence manifest integrity"
    .\scripts\assert_release_manifest_integrity.ps1

    Write-Output ""
    Write-Output "Step 7: Export readable evidence report"
    .\scripts\export_release_evidence_report.ps1

    Write-Output ""
    Write-Output "Step 8: Export next release steps report"
    .\scripts\export_next_release_steps_report.ps1

    Write-Output ""
    Write-Output "Step 9: Package release evidence bundle"
    .\scripts\package_release_evidence_bundle.ps1

    Write-Output ""
    Write-Output "Step 10: Validate release evidence bundle contents"
    $BundleContentsLog = Join-Path $Root "release_evidence\release-evidence-bundle-contents.log"
    .\scripts\assert_evidence_bundle_contents.ps1 2>&1 | Tee-Object -FilePath $BundleContentsLog

    Write-Output ""
    Write-Output "Step 11: Sync evidence manifest after bundle packaging"
    .\scripts\sync_release_evidence_manifest.ps1

    Write-Output ""
    Write-Output "Step 12: Export refreshed evidence report after bundle sync"
    .\scripts\export_release_evidence_report.ps1

    Write-Output ""
    Write-Output "Step 13: Export refreshed next release steps report after bundle sync"
    .\scripts\export_next_release_steps_report.ps1

    Write-Output ""
    Write-Output "Step 14: Package refreshed release evidence bundle"
    .\scripts\package_release_evidence_bundle.ps1

    Write-Output ""
    Write-Output "Step 15: Validate refreshed release evidence bundle contents"
    .\scripts\assert_evidence_bundle_contents.ps1 2>&1 | Tee-Object -FilePath $BundleContentsLog

    if ($RunGoNoGo) {
        Write-Output ""
        Write-Output "Step 16: Validate manual release evidence pack"
        $ManualEvidencePackLog = Join-Path $Root "release_evidence\manual-evidence-pack.log"
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $ManualEvidencePackLog) | Out-Null
        .\scripts\assert_manual_release_evidence_pack.ps1 2>&1 | Tee-Object -FilePath $ManualEvidencePackLog

        Write-Output ""
        Write-Output "Step 17: Sync evidence manifest after manual evidence pack check"
        .\scripts\sync_release_evidence_manifest.ps1

        Write-Output ""
        Write-Output "Step 18: Verify manual evidence gates"
        .\scripts\show_manual_evidence_gates.ps1

        Write-Output ""
        Write-Output "Step 19: Validate final release evidence manifest integrity"
        .\scripts\assert_release_manifest_integrity.ps1

        Write-Output ""
        Write-Output "Step 20: Final Go/No-Go check"
        .\scripts\assert_release_go_no_go.ps1

        Write-Output ""
        Write-Output "Step 21: Mark Go/No-Go gate as passed"
        .\scripts\update_release_evidence_gate.ps1 -GateId "release_go_no_go" -Status "passed" -Note "Final Go/No-Go command succeeded in full release evidence pipeline."

        Write-Output ""
        Write-Output "Step 22: Validate final manifest after Go/No-Go gate update"
        .\scripts\assert_release_manifest_integrity.ps1

        Write-Output ""
        Write-Output "Step 23: Export final evidence report"
        .\scripts\export_release_evidence_report.ps1

        Write-Output ""
        Write-Output "Step 24: Export final next release steps report"
        .\scripts\export_next_release_steps_report.ps1

        Write-Output ""
        Write-Output "Step 25: Package final release evidence bundle"
        .\scripts\package_release_evidence_bundle.ps1

        Write-Output ""
        Write-Output "Step 26: Validate final release evidence bundle contents"
        .\scripts\assert_evidence_bundle_contents.ps1 2>&1 | Tee-Object -FilePath $BundleContentsLog

        Write-Output ""
        Write-Output "Step 27: Show final release evidence status"
        .\scripts\show_release_evidence_status.ps1

        Write-Output ""
        Write-Output "Step 28: Show next release steps"
        .\scripts\show_next_release_steps.ps1
    } else {
        Write-Output ""
        Write-Output "Step 16 skipped: Go/No-Go check"
        Write-Output "Run again with -RunGoNoGo after all manual gates have direct evidence and the manifest is updated."

        Write-Output ""
        Write-Output "Step 17: Show next release steps"
        .\scripts\show_next_release_steps.ps1
    }

    Write-Output ""
    Write-Output "Full release evidence pipeline finished."
} finally {
    Pop-Location
}
