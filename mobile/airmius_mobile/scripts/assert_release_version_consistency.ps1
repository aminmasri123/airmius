param(
    [string]$ManifestPath = "store_listing\release\release_evidence_manifest.json",
    [string]$PubspecPath = "pubspec.yaml",
    [string]$AndroidGradlePath = "android\app\build.gradle.kts",
    [string]$IosProjectPath = "ios\Runner.xcodeproj\project.pbxproj"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ResolvedManifestPath = Join-Path $Root $ManifestPath
$ResolvedPubspecPath = Join-Path $Root $PubspecPath
$ResolvedAndroidGradlePath = Join-Path $Root $AndroidGradlePath
$ResolvedIosProjectPath = Join-Path $Root $IosProjectPath

if (-not (Test-Path $ResolvedManifestPath)) {
    throw "Release evidence manifest not found: $ResolvedManifestPath"
}

if (-not (Test-Path $ResolvedPubspecPath)) {
    throw "pubspec.yaml not found: $ResolvedPubspecPath"
}

$Manifest = Get-Content -Path $ResolvedManifestPath -Raw | ConvertFrom-Json
$Pubspec = Get-Content -Path $ResolvedPubspecPath -Raw
$VersionMatch = [regex]::Match($Pubspec, "(?m)^version:\s*(?<version>[^\s#]+)")

if (-not $VersionMatch.Success) {
    throw "pubspec.yaml does not contain a version line."
}

$PubspecVersion = $VersionMatch.Groups["version"].Value.Trim()

if ($Manifest.version -ne $PubspecVersion) {
    throw "Version mismatch. Manifest version '$($Manifest.version)' does not match pubspec version '$PubspecVersion'."
}

if (Test-Path $ResolvedAndroidGradlePath) {
    $AndroidGradle = Get-Content -Path $ResolvedAndroidGradlePath -Raw
    if ($AndroidGradle -notmatch 'applicationId\s*=\s*"com\.airmius\.app"') {
        throw "Android applicationId must be com.airmius.app."
    }
}

if ($Manifest.android_application_id -ne "com.airmius.app") {
    throw "Manifest android_application_id must be com.airmius.app."
}

if ($Manifest.ios_bundle_id -ne "com.airmius.app") {
    throw "Manifest ios_bundle_id must be com.airmius.app."
}

if (Test-Path $ResolvedIosProjectPath) {
    $IosProject = Get-Content -Path $ResolvedIosProjectPath -Raw
    if ($IosProject -notmatch "com\.airmius\.app") {
        throw "iOS project does not contain bundle identifier com.airmius.app."
    }
}

Write-Output "Release version consistency check passed."
Write-Output "Version: $PubspecVersion"
Write-Output "Android application ID: $($Manifest.android_application_id)"
Write-Output "iOS bundle ID: $($Manifest.ios_bundle_id)"
