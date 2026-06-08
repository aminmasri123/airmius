param(
    [string]$OutputPath = "store_listing\release\airmius_release_evidence_bundle.zip"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ResolvedOutputPath = Join-Path $Root $OutputPath
$ResolvedOutputDirectory = Split-Path -Parent $ResolvedOutputPath
$StagingDirectory = Join-Path $Root "release_evidence_bundle_staging"

if (-not (Test-Path $ResolvedOutputDirectory)) {
    New-Item -ItemType Directory -Path $ResolvedOutputDirectory | Out-Null
}

if (Test-Path $StagingDirectory) {
    Remove-Item -LiteralPath $StagingDirectory -Recurse -Force
}

if (Test-Path $ResolvedOutputPath) {
    Remove-Item -LiteralPath $ResolvedOutputPath -Force
}

New-Item -ItemType Directory -Path $StagingDirectory | Out-Null

$EvidenceSources = @(
    "scripts",
    "store_listing\release",
    "store_listing\deep_links",
    "store_listing\privacy",
    "store_listing\screenshots",
    "store_listing\localization",
    "release_evidence",
    "build\app\outputs\bundle\release\app-release.aab",
    "build\app\outputs\flutter-apk\app-release.apk",
    "build\ios\ipa"
)

$RequiredReleaseFiles = @(
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
    "store_listing\release\release_evidence_template.md",
    "store_listing\release\final_command_cheatsheet.md",
    "store_listing\release\windows_android_setup_runbook.md",
    "store_listing\release\store_submission_readiness_runbook.md",
    "store_listing\release\manual_tester_quickstart.md",
    "store_listing\release\manual_evidence_gates.md",
    "store_listing\release\manual_gate_signoff_template.md",
    "store_listing\release\logo_theme_parity_qa.md",
    "store_listing\release\final_execution_sequence.md",
    "store_listing\release\final_operator_handoff.md",
    "store_listing\release\membership_payload.template.json"
)

$Included = New-Object System.Collections.Generic.List[string]
$Missing = New-Object System.Collections.Generic.List[string]

foreach ($Source in $EvidenceSources) {
    $FullSource = Join-Path $Root $Source

    if (-not (Test-Path $FullSource)) {
        $Missing.Add($Source)
        continue
    }

    $Destination = Join-Path $StagingDirectory $Source
    $DestinationParent = Split-Path -Parent $Destination

    if (-not (Test-Path $DestinationParent)) {
        New-Item -ItemType Directory -Path $DestinationParent -Force | Out-Null
    }

    Copy-Item -LiteralPath $FullSource -Destination $Destination -Recurse -Force
    $Included.Add($Source)
}

foreach ($RequiredFile in $RequiredReleaseFiles) {
    if (-not (Test-Path (Join-Path $Root $RequiredFile))) {
        $Missing.Add($RequiredFile)
    }
}

$ManifestPath = Join-Path $StagingDirectory "bundle_manifest.txt"
$ManifestLines = @(
    "Airmius Mobile Release Evidence Bundle",
    "Created: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss zzz')",
    "",
    "Included sources:"
)

foreach ($Item in $Included) {
    $ManifestLines += "- $Item"
}

$ManifestLines += ""
$ManifestLines += "Missing or not generated yet:"

foreach ($Item in $Missing) {
    $ManifestLines += "- $Item"
}

$ManifestLines | Set-Content -Path $ManifestPath -Encoding UTF8

Compress-Archive -Path (Join-Path $StagingDirectory "*") -DestinationPath $ResolvedOutputPath -Force

Remove-Item -LiteralPath $StagingDirectory -Recurse -Force

Write-Output "Release evidence bundle created:"
Write-Output $ResolvedOutputPath
Write-Output ""
Write-Output "Included:"
foreach ($Item in $Included) {
    Write-Output " - $Item"
}
Write-Output ""
Write-Output "Missing or not generated yet:"
foreach ($Item in $Missing) {
    Write-Output " - $Item"
}
