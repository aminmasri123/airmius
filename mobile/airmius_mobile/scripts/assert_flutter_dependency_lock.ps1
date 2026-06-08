param(
    [string]$PubspecPath = "pubspec.yaml",
    [string]$LockPath = "pubspec.lock"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ResolvedPubspecPath = Join-Path $Root $PubspecPath
$ResolvedLockPath = Join-Path $Root $LockPath

if (-not (Test-Path $ResolvedPubspecPath)) {
    throw "pubspec.yaml not found: $ResolvedPubspecPath"
}

if (-not (Test-Path $ResolvedLockPath)) {
    throw "pubspec.lock not found. Run flutter pub get before this check."
}

$Pubspec = Get-Content -Path $ResolvedPubspecPath -Raw
$Lock = Get-Content -Path $ResolvedLockPath -Raw

if ($Pubspec -notmatch "flutter_secure_storage:\s*\^10\.3\.1") {
    throw "pubspec.yaml must declare flutter_secure_storage ^10.3.1."
}

if ($Lock -notmatch "(?m)^\s{2}flutter_secure_storage:") {
    throw "pubspec.lock does not contain flutter_secure_storage. Run flutter pub get and inspect dependency resolution."
}

if ($Lock -notmatch "(?m)^\s{2}flutter_secure_storage_platform_interface:") {
    throw "pubspec.lock does not contain flutter_secure_storage_platform_interface."
}

Write-Output "Flutter dependency lock check passed."
Write-Output "flutter_secure_storage is declared and locked."
