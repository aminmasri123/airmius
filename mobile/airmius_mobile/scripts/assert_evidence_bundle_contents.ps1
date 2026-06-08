param(
    [string]$BundlePath = "store_listing\release\airmius_release_evidence_bundle.zip"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ResolvedBundlePath = Join-Path $Root $BundlePath
$ValidationDirectory = Join-Path $Root "release_evidence_bundle_validation"

if (-not (Test-Path $ResolvedBundlePath)) {
    throw "Release evidence bundle not found: $ResolvedBundlePath"
}

if (Test-Path $ValidationDirectory) {
    Remove-Item -LiteralPath $ValidationDirectory -Recurse -Force
}

New-Item -ItemType Directory -Path $ValidationDirectory | Out-Null

try {
    Expand-Archive -Path $ResolvedBundlePath -DestinationPath $ValidationDirectory -Force

    $RequiredPaths = @(
        "bundle_manifest.txt",
        "scripts\new_manual_release_evidence_pack.ps1",
        "scripts\assert_local_release_prerequisites.ps1",
        "scripts\run_full_release_evidence_pipeline.ps1",
        "scripts\release_candidate_checks.ps1",
        "scripts\assert_evidence_bundle_contents.ps1",
        "scripts\assert_logo_theme_assets.ps1",
        "scripts\assert_manual_release_evidence_pack.ps1",
        "scripts\show_next_release_steps.ps1",
        "scripts\export_next_release_steps_report.ps1",
        "store_listing\release\release_evidence_manifest.json",
        "store_listing\release\generated_release_evidence_report.md",
        "store_listing\release\generated_next_release_steps.md",
        "store_listing\release\final_operator_handoff.md",
        "store_listing\release\final_command_cheatsheet.md",
        "store_listing\release\windows_android_setup_runbook.md",
        "store_listing\release\store_submission_readiness_runbook.md",
        "store_listing\release\manual_evidence_gates.md",
        "store_listing\release\manual_gate_signoff_template.md",
        "store_listing\release\logo_theme_parity_qa.md",
        "store_listing\release\final_execution_sequence.md",
        "store_listing\release\membership_payload.template.json",
        "store_listing\release\release_evidence_template.md",
        "store_listing\release\manual_tester_quickstart.md",
        "store_listing\deep_links",
        "store_listing\privacy",
        "store_listing\localization"
    )

    $Missing = New-Object System.Collections.Generic.List[string]

    foreach ($Path in $RequiredPaths) {
        $FullPath = Join-Path $ValidationDirectory $Path
        if (-not (Test-Path $FullPath)) {
            $Missing.Add($Path)
        }
    }

    if ($Missing.Count -gt 0) {
        Write-Output "Release evidence bundle is missing required content:"
        foreach ($Item in $Missing) {
            Write-Output " - $Item"
        }
        throw "Release evidence bundle content check failed."
    }

    Write-Output "Release evidence bundle content check passed."
    Write-Output "Bundle: $ResolvedBundlePath"
} finally {
    if (Test-Path $ValidationDirectory) {
        Remove-Item -LiteralPath $ValidationDirectory -Recurse -Force
    }
}
