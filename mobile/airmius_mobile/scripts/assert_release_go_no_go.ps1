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
$BlockingStatuses = @(
    "pending_execution",
    "pending_deployment",
    "pending_capture",
    "pending_signoff",
    "pending_packaging",
    "pending_setup",
    "partial_evidence",
    "failed",
    "blocked"
)
$AllowedPassingStatuses = @("passed")

$BlockedGates = @()

foreach ($Gate in $Manifest.gates) {
    if ($Gate.id -eq "release_go_no_go") {
        continue
    }

    if ($BlockingStatuses.Contains($Gate.status) -or -not $AllowedPassingStatuses.Contains($Gate.status)) {
        $BlockedGates += $Gate
    }
}

if ($BlockedGates.Count -gt 0) {
    Write-Output "Airmius Mobile release is NO-GO."
    Write-Output ""
    Write-Output "Blocking gates:"
    foreach ($Gate in $BlockedGates) {
        Write-Output " - $($Gate.id): $($Gate.title) [$($Gate.status)]"
    }
    Write-Output ""
    Write-Output "Go is allowed only when every gate has direct evidence and no gate remains pending."
    throw "Release Go/No-Go check failed."
}

Write-Output "Airmius Mobile release is GO."
Write-Output "All evaluated manifest gates are explicitly passed."
