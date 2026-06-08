param(
    [Parameter(Mandatory = $true)]
    [string]$GateId,

    [Parameter(Mandatory = $true)]
    [ValidateSet("pending_execution", "pending_deployment", "pending_capture", "pending_signoff", "pending_packaging", "pending_setup", "partial_evidence", "passed", "blocked", "failed")]
    [string]$Status,

    [string]$ManifestPath = "store_listing/release/release_evidence_manifest.json",
    [string]$Note = ""
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $ManifestPath)) {
    throw "Release evidence manifest not found: $ManifestPath"
}

$manifest = Get-Content -Path $ManifestPath -Raw | ConvertFrom-Json
$gate = $manifest.gates | Where-Object { $_.id -eq $GateId } | Select-Object -First 1

if ($null -eq $gate) {
    $known = ($manifest.gates | ForEach-Object { $_.id }) -join ", "
    throw "Unknown gate '$GateId'. Known gates: $known"
}

$gate.status = $Status
$gate | Add-Member -NotePropertyName "last_updated_at" -NotePropertyValue (Get-Date).ToString("o") -Force

if ($Note.Trim().Length -gt 0) {
    $gate | Add-Member -NotePropertyName "last_note" -NotePropertyValue $Note -Force
}

$manifest | ConvertTo-Json -Depth 10 | Set-Content -Path $ManifestPath -Encoding UTF8

Write-Host "Updated release evidence gate"
Write-Host "Gate: $GateId"
Write-Host "Status: $Status"
if ($Note.Trim().Length -gt 0) {
    Write-Host "Note: $Note"
}
