param(
    [string]$ManifestPath = "store_listing/release/release_evidence_manifest.json",
    [string]$OutputPath = "store_listing/release/generated_release_evidence_report.md"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $ManifestPath)) {
    throw "Release evidence manifest not found: $ManifestPath"
}

$manifest = Get-Content -Path $ManifestPath -Raw | ConvertFrom-Json
$pending = @($manifest.gates | Where-Object { $_.status -like "pending*" })
$passed = @($manifest.gates | Where-Object { $_.status -eq "passed" })
$blocked = @($manifest.gates | Where-Object { $_.status -in @("blocked", "failed") })
$partial = @($manifest.gates | Where-Object { $_.status -eq "partial_evidence" })
$knownStatuses = @(
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
$unknown = @($manifest.gates | Where-Object { $_.status -notin $knownStatuses })
$evaluatedBlockers = @($manifest.gates | Where-Object { $_.id -ne "release_go_no_go" -and $_.status -ne "passed" })
$decision = if ($evaluatedBlockers.Count -eq 0 -and $unknown.Count -eq 0) { "GO candidate" } else { "NO-GO" }

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add("# Airmius Mobile Release Evidence Report")
$lines.Add("")
$lines.Add("- Product: $($manifest.product)")
$lines.Add("- Version: $($manifest.version)")
$lines.Add("- Preparation: $($manifest.overall_preparation_percent)%")
$lines.Add("- Remaining evidence: $($manifest.remaining_percent_requires_evidence)%")
$lines.Add("- API base URL: $($manifest.api_base_url)")
$lines.Add("- Android application ID: $($manifest.android_application_id)")
$lines.Add("- iOS bundle ID: $($manifest.ios_bundle_id)")
$lines.Add("- Decision: $decision")
$lines.Add("")
$lines.Add("## Summary")
$lines.Add("")
$lines.Add("- Gates: $($manifest.gates.Count)")
$lines.Add("- Passed: $($passed.Count)")
$lines.Add("- Pending: $($pending.Count)")
$lines.Add("- Partial evidence: $($partial.Count)")
$lines.Add("- Blocked/failed: $($blocked.Count)")
$lines.Add("- Unknown status: $($unknown.Count)")
$lines.Add("- Evaluated release blockers: $($evaluatedBlockers.Count)")
$lines.Add("")
$lines.Add("## Gates")
$lines.Add("")

foreach ($gate in $manifest.gates) {
    $lines.Add("### $($gate.title)")
    $lines.Add("")
    $lines.Add("- ID: `$($gate.id)`")
    $lines.Add("- Owner: $($gate.owner)")
    $lines.Add("- Status: $($gate.status)")
    $lines.Add("- Required evidence: $($gate.required_evidence)")
    if ($gate.local_command) {
        $lines.Add("- Local command: `$($gate.local_command)`")
    }
    if ($gate.runbook) {
        $lines.Add("- Runbook: `$($gate.runbook)`")
    }
    if ($gate.urls) {
        $lines.Add("- URLs:")
        foreach ($url in $gate.urls) {
            $lines.Add("  - $url")
        }
    }
    if ($gate.ci_artifacts) {
        $lines.Add("- CI artifacts:")
        foreach ($artifact in $gate.ci_artifacts) {
            $lines.Add("  - `$artifact`")
        }
    }
    if ($gate.last_updated_at) {
        $lines.Add("- Last updated: $($gate.last_updated_at)")
    }
    if ($gate.last_note) {
        $lines.Add("- Last note: $($gate.last_note)")
    }
    $lines.Add("")
}

$lines.Add("## Go / No-Go rule")
$lines.Add("")
$lines.Add($manifest.go_no_go_rule)

$directory = Split-Path -Path $OutputPath -Parent
if ($directory -and -not (Test-Path $directory)) {
    New-Item -ItemType Directory -Path $directory | Out-Null
}

$lines | Set-Content -Path $OutputPath -Encoding UTF8

Write-Host "Release evidence report exported:"
Write-Host $OutputPath
