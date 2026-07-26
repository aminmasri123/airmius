param(
    [string]$ApiBaseUrl = "https://airmius.com",
    [switch]$UseHttp,
    [switch]$AllowLocalhost
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($ApiBaseUrl)) {
    throw "ApiBaseUrl is required for release configuration."
}

$ParsedUrl = $null
if (-not [System.Uri]::TryCreate($ApiBaseUrl, [System.UriKind]::Absolute, [ref]$ParsedUrl)) {
    throw "ApiBaseUrl must be an absolute URL. Received: $ApiBaseUrl"
}

if ($ParsedUrl.Scheme -ne "https") {
    throw "Release ApiBaseUrl must use HTTPS. Received: $ApiBaseUrl"
}

$LocalHosts = @("localhost", "127.0.0.1", "10.0.2.2", "::1")
if (-not $AllowLocalhost -and $LocalHosts.Contains($ParsedUrl.Host.ToLowerInvariant())) {
    throw "Release ApiBaseUrl must not use localhost/emulator hosts. Received: $ApiBaseUrl"
}

if (-not $UseHttp) {
    throw "Release builds must pass -UseHttp so the app uses the Laravel HTTP API transport instead of demo/static data."
}

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$PubspecPath = Join-Path $Root "pubspec.yaml"
$AndroidGradlePath = Join-Path $Root "android\app\build.gradle.kts"
$AndroidManifestPath = Join-Path $Root "android\app\src\main\AndroidManifest.xml"

$Pubspec = Get-Content -Path $PubspecPath -Raw
$AndroidGradle = Get-Content -Path $AndroidGradlePath -Raw
$AndroidManifest = Get-Content -Path $AndroidManifestPath -Raw

if ($Pubspec -notmatch "flutter_secure_storage:\s*\^10\.3\.1") {
    throw "pubspec.yaml must include flutter_secure_storage ^10.3.1 for release token security."
}

if ($AndroidGradle -notmatch "minSdk\s*=\s*23") {
    throw "Android minSdk must be 23 for flutter_secure_storage 10.x release builds."
}

if ($AndroidManifest -notmatch 'android:allowBackup="false"') {
    throw "Android manifest must disable allowBackup for secure session material."
}

Write-Output "Release configuration check passed."
Write-Output "ApiBaseUrl: $ApiBaseUrl"
Write-Output "UseHttp: $UseHttp"
Write-Output "Secure storage dependency: present"
Write-Output "Android minSdk: 23"
Write-Output "Android allowBackup: false"
