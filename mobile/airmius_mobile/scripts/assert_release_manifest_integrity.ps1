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
$AllowedStatuses = @(
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
$Failures = New-Object System.Collections.Generic.List[string]
$SeenGateIds = @{}

if ([string]::IsNullOrWhiteSpace($Manifest.product)) {
    $Failures.Add("Manifest product is missing.")
}

if ($null -eq $Manifest.gates -or $Manifest.gates.Count -eq 0) {
    $Failures.Add("Manifest gates are missing.")
}

foreach ($Gate in $Manifest.gates) {
    if ([string]::IsNullOrWhiteSpace($Gate.id)) {
        $Failures.Add("Gate with missing id found.")
        continue
    }

    if ($SeenGateIds.ContainsKey($Gate.id)) {
        $Failures.Add("Duplicate gate id found: $($Gate.id)")
    } else {
        $SeenGateIds[$Gate.id] = $true
    }

    if ([string]::IsNullOrWhiteSpace($Gate.title)) {
        $Failures.Add("Gate '$($Gate.id)' is missing title.")
    }

    if ([string]::IsNullOrWhiteSpace($Gate.owner)) {
        $Failures.Add("Gate '$($Gate.id)' is missing owner.")
    }

    if ([string]::IsNullOrWhiteSpace($Gate.status)) {
        $Failures.Add("Gate '$($Gate.id)' is missing status.")
    } elseif (-not $AllowedStatuses.Contains($Gate.status)) {
        $Failures.Add("Gate '$($Gate.id)' has unknown status '$($Gate.status)'.")
    }

    if ([string]::IsNullOrWhiteSpace($Gate.required_evidence)) {
        $Failures.Add("Gate '$($Gate.id)' is missing required_evidence.")
    }
}

if ($Failures.Count -gt 0) {
    Write-Output "Release evidence manifest integrity failed:"
    foreach ($Failure in $Failures) {
        Write-Output " - $Failure"
    }
    throw "Release manifest integrity check failed."
}

Write-Output "Release evidence manifest integrity passed."
Write-Output "Product: $($Manifest.product)"
Write-Output "Gate count: $($Manifest.gates.Count)"
