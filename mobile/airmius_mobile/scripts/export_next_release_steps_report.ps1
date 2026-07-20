param(
    [string]$ManifestPath = "store_listing/release/release_evidence_manifest.json",
    [string]$OutputPath = "store_listing/release/generated_next_release_steps.md",
    [int]$Limit = 12
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

    $PlatformActions = New-Object System.Collections.Generic.List[string]

    if ($Gate.local_command) {
        $PlatformActions.Add("Default: $($Gate.local_command)")
    }

    if ($Gate.linux_local_command) {
        $PlatformActions.Add("Linux: $($Gate.linux_local_command)")
    }

    if ($Gate.macos_local_command) {
        $PlatformActions.Add("macOS: $($Gate.macos_local_command)")
    }

    if ($PlatformActions.Count -gt 0) {
        return ($PlatformActions -join " | ")
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

$Lines = @(
    "# Airmius Mobile Next Release Steps",
    "",
    "- Product: $($Manifest.product)",
    "- Version: $($Manifest.version)",
    "- Product preparation: $($Manifest.overall_preparation_percent)%",
    "- Remaining product evidence: $($Manifest.remaining_percent_requires_evidence)%",
    "- Evidence progress: $EvidencePercent%",
    "- Evidence remaining: $RemainingEvidencePercent%",
    "- Open evaluated gates: $($OpenGates.Count)",
    "",
    "## Next open gates"
)

if ($OpenGates.Count -eq 0) {
    $Lines += ""
    $Lines += "No open evaluated gates remain."
    $Lines += ""
    $Lines += "Run final Go/No-Go:"
    $Lines += ""
    $Lines += "```powershell"
    $Lines += 'scripts/run_full_release_evidence_pipeline.ps1 -ApiBaseUrl "https://app.airmius.com" -RunGoNoGo'
    $Lines += "```"
} else {
    $Index = 1
    foreach ($Gate in ($OpenGates | Select-Object -First $Limit)) {
        $Lines += ""
        $Lines += "### $Index. $($Gate.title)"
        $Lines += ""
        $Lines += "- Gate ID: `$($Gate.id)`"
        $Lines += "- Owner: $($Gate.owner)"
        $Lines += "- Status: `$($Gate.status)`"
        $Lines += "- Required evidence: $($Gate.required_evidence)"
        $Lines += "- Next action: `$(Get-GateAction -Gate $Gate)`"
        $Index++
    }

    if ($OpenGates.Count -gt $Limit) {
        $Lines += ""
        $Lines += "Additional open gates not shown: $($OpenGates.Count - $Limit)"
    }
}

$OutputDirectory = Split-Path -Parent $OutputPath
if (-not [string]::IsNullOrWhiteSpace($OutputDirectory) -and -not (Test-Path $OutputDirectory)) {
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
}

$Lines | Set-Content -Path $OutputPath -Encoding UTF8

Write-Output "Next release steps report exported:"
Write-Output " - $OutputPath"
