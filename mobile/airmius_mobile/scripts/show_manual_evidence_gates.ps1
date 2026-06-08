param(
    [string]$ManifestPath = "store_listing\release\release_evidence_manifest.json"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ResolvedManifestPath = Join-Path $Root $ManifestPath

if (-not (Test-Path $ResolvedManifestPath)) {
    throw "Release evidence manifest not found: $ResolvedManifestPath"
}

$Manifest = Get-Content -Path $ResolvedManifestPath -Raw | ConvertFrom-Json
$ManualGateIds = @(
    "ios_release_build",
    "domain_verification",
    "screenshots",
    "real_api_qa",
    "legal_privacy",
    "localization_qa",
    "secure_token_storage",
    "store_review_account",
    "store_release_notes",
    "store_submission_readiness"
)

Write-Output "Airmius Mobile Manual Evidence Gates"
Write-Output "Runbook: store_listing/release/manual_evidence_gates.md"
Write-Output ""

$ManualGates = @($Manifest.gates | Where-Object { $ManualGateIds.Contains($_.id) })

foreach ($Gate in $ManualGates) {
    Write-Output "$($Gate.id)"
    Write-Output "  Title: $($Gate.title)"
    Write-Output "  Owner: $($Gate.owner)"
    Write-Output "  Status: $($Gate.status)"
    Write-Output "  Required evidence: $($Gate.required_evidence)"
    if ($Gate.runbook) {
        Write-Output "  Runbook: $($Gate.runbook)"
    }
    Write-Output ""
}

$OpenManualGates = @($ManualGates | Where-Object { $_.status -ne "passed" })

Write-Output "Open manual gates: $($OpenManualGates.Count) / $($ManualGates.Count)"

if ($OpenManualGates.Count -gt 0) {
    Write-Output ""
    Write-Output "Manual evidence is still required before final Go/No-Go:"
    foreach ($Gate in $OpenManualGates) {
        Write-Output " - $($Gate.id): $($Gate.status)"
    }
    exit 1
}

Write-Output ""
Write-Output "All manual evidence gates are passed."
