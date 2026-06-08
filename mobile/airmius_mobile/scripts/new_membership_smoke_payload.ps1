param(
    [string]$TemplatePath = "store_listing\release\membership_payload.template.json",
    [string]$OutputPath = "release_evidence\membership-payload.example.local.json",
    [switch]$Force
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ResolvedTemplatePath = Join-Path $Root $TemplatePath
$ResolvedOutputPath = Join-Path $Root $OutputPath
$OutputDirectory = Split-Path -Parent $ResolvedOutputPath

if (-not (Test-Path $ResolvedTemplatePath)) {
    throw "Membership payload template not found: $ResolvedTemplatePath"
}

if (Test-Path $ResolvedOutputPath -and -not $Force) {
    throw "Local membership smoke payload already exists: $ResolvedOutputPath. Re-run with -Force to overwrite."
}

if (-not (Test-Path $OutputDirectory)) {
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
}

Copy-Item -LiteralPath $ResolvedTemplatePath -Destination $ResolvedOutputPath -Force:$Force

Write-Output "Local membership smoke payload created:"
Write-Output $ResolvedOutputPath
Write-Output ""
Write-Output "Review and edit the local file before running membership API smoke."
Write-Output "Do not add real private member data."
