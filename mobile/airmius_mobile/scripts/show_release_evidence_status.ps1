param(
    [string]$ManifestPath = "store_listing/release/release_evidence_manifest.json"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $ManifestPath)) {
    throw "Release evidence manifest not found: $ManifestPath"
}

$manifest = Get-Content -Path $ManifestPath -Raw | ConvertFrom-Json

Write-Host "Airmius Mobile Release Evidence Status"
Write-Host "Product: $($manifest.product)"
Write-Host "Version: $($manifest.version)"
Write-Host "Preparation: $($manifest.overall_preparation_percent)%"
Write-Host "Remaining evidence: $($manifest.remaining_percent_requires_evidence)%"
Write-Host ""

$manifest.gates |
    Select-Object id, title, owner, status, required_evidence |
    Format-Table -AutoSize

$passed = @($manifest.gates | Where-Object { $_.status -eq "passed" })
$partial = @($manifest.gates | Where-Object { $_.status -eq "partial_evidence" })
$pending = @($manifest.gates | Where-Object { $_.status -like "pending*" })
$failedOrBlocked = @($manifest.gates | Where-Object { $_.status -eq "failed" -or $_.status -eq "blocked" })
$unknown = @($manifest.gates | Where-Object {
    $_.status -notin @(
        "pending_execution",
        "pending_deployment",
        "pending_capture",
        "pending_signoff",
        "pending_packaging",
        "pending_setup",
        "partial_evidence",
        "passed",
        "blocked",
        "failed"
    )
})
$releaseBlockers = @($manifest.gates | Where-Object { $_.id -ne "release_go_no_go" -and $_.status -ne "passed" })
$evaluatedGates = @($manifest.gates | Where-Object { $_.id -ne "release_go_no_go" })
$passedEvaluated = @($evaluatedGates | Where-Object { $_.status -eq "passed" })
$evidencePercent = if ($evaluatedGates.Count -eq 0) { 0 } else { [math]::Round(($passedEvaluated.Count / $evaluatedGates.Count) * 100, 1) }
$remainingEvidencePercent = [math]::Round((100 - $evidencePercent), 1)

Write-Host ""
Write-Host "Passed gates: $($passed.Count) / $($manifest.gates.Count)"
Write-Host "Pending gates: $($pending.Count) / $($manifest.gates.Count)"
Write-Host "Partial evidence gates: $($partial.Count) / $($manifest.gates.Count)"
Write-Host "Failed/blocked gates: $($failedOrBlocked.Count) / $($manifest.gates.Count)"
Write-Host "Unknown status gates: $($unknown.Count) / $($manifest.gates.Count)"
Write-Host ""
Write-Host "Evidence progress: $evidencePercent%"
Write-Host "Evidence remaining: $remainingEvidencePercent%"
Write-Host "Product preparation remains: $($manifest.overall_preparation_percent)% prepared / $($manifest.remaining_percent_requires_evidence)% requires real evidence"

if ($partial.Count -gt 0) {
    Write-Host ""
    Write-Host "Partial evidence:"
    $partial | ForEach-Object { Write-Host " - $($_.id): $($_.title)" }
}

if ($unknown.Count -gt 0) {
    Write-Host ""
    Write-Host "Unknown status values:"
    $unknown | ForEach-Object { Write-Host " - $($_.id): $($_.status)" }
}

if ($releaseBlockers.Count -gt 0 -or $unknown.Count -gt 0) {
    Write-Host ""
    Write-Host "Go/No-Go: NO-GO until every evaluated gate is explicitly passed."
    exit 1
}

Write-Host ""
Write-Host "Go/No-Go: GO candidate. Confirm release_go_no_go is passed only after the final Go/No-Go command succeeds."
