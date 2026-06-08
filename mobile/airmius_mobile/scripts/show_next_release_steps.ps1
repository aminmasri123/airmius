param(
    [string]$ManifestPath = "store_listing/release/release_evidence_manifest.json",
    [int]$Limit = 8
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $ManifestPath)) {
    throw "Release evidence manifest not found: $ManifestPath"
}

$Manifest = Get-Content -Path $ManifestPath -Raw | ConvertFrom-Json
$EvaluatedGates = @($Manifest.gates | Where-Object { $_.id -ne "release_go_no_go" })
$PassedEvaluated = @($EvaluatedGates | Where-Object { $_.status -eq "passed" })
$OpenGates = @($EvaluatedGates | Where-Object { $_.status -ne "passed" })
$EvidencePercent = if ($EvaluatedGates.Count -eq 0) { 0 } else { [math]::Round(($PassedEvaluated.Count / $EvaluatedGates.Count) * 100, 1) }
$RemainingEvidencePercent = [math]::Round((100 - $EvidencePercent), 1)

function Get-GateAction {
    param($Gate)

    if ($Gate.local_command) {
        return $Gate.local_command
    }

    if ($Gate.runbook) {
        return "Open runbook: $($Gate.runbook)"
    }

    if ($Gate.urls) {
        return "Validate URLs: $($Gate.urls -join ', ')"
    }

    if ($Gate.artifact) {
        return "Produce artifact: $($Gate.artifact)"
    }

    return "Attach evidence and update gate with scripts/update_release_evidence_gate.ps1"
}

Write-Output "Airmius Mobile Next Release Steps"
Write-Output "Product: $($Manifest.product)"
Write-Output "Version: $($Manifest.version)"
Write-Output "Product preparation: $($Manifest.overall_preparation_percent)%"
Write-Output "Evidence progress: $EvidencePercent%"
Write-Output "Evidence remaining: $RemainingEvidencePercent%"
Write-Output ""

if ($OpenGates.Count -eq 0) {
    Write-Output "No open evaluated gates remain."
    Write-Output "Run final Go/No-Go:"
    Write-Output " - scripts/run_full_release_evidence_pipeline.ps1 -ApiBaseUrl `"https://app.airmius.com`" -RunGoNoGo"
    exit 0
}

Write-Output "Next open gates:"

$Index = 1
foreach ($Gate in ($OpenGates | Select-Object -First $Limit)) {
    Write-Output ""
    Write-Output "$Index. $($Gate.title)"
    Write-Output "   Gate ID: $($Gate.id)"
    Write-Output "   Owner: $($Gate.owner)"
    Write-Output "   Status: $($Gate.status)"
    Write-Output "   Required evidence: $($Gate.required_evidence)"
    Write-Output "   Next action: $(Get-GateAction -Gate $Gate)"
    $Index++
}

if ($OpenGates.Count -gt $Limit) {
    Write-Output ""
    Write-Output "More open gates remain: $($OpenGates.Count - $Limit)"
}
