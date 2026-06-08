param(
    [string]$ApiBaseUrl = "https://app.airmius.com",
    [string]$FlutterCommand = "flutter"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$EvidenceDir = Join-Path $Root "release_evidence"
New-Item -ItemType Directory -Force -Path $EvidenceDir | Out-Null

function Invoke-EvidenceCommand {
    param(
        [string]$Title,
        [string]$LogName,
        [scriptblock]$Command
    )

    $LogPath = Join-Path $EvidenceDir $LogName

    Write-Host ""
    Write-Host $Title

    $global:LASTEXITCODE = 0
    try {
        & $Command 2>&1 | Tee-Object -FilePath $LogPath
        $CommandSucceeded = $?
        $ExitCode = $global:LASTEXITCODE
    } catch {
        $_ | Out-String | Add-Content -Path $LogPath
        throw "$Title failed: $($_.Exception.Message)"
    }

    if (-not $CommandSucceeded) {
        throw "$Title failed. See evidence log: $LogPath"
    }

    if ($null -ne $ExitCode -and $ExitCode -ne 0) {
        throw "$Title failed with exit code $ExitCode. See evidence log: $LogPath"
    }

    $global:LASTEXITCODE = 0
}

Push-Location $Root
try {
    Write-Host "Airmius Mobile release-candidate checks"
    Write-Host "API base URL: $ApiBaseUrl"
    Write-Host "Evidence directory: $EvidenceDir"

    Invoke-EvidenceCommand -Title "1. Release configuration" -LogName "release-configuration-check.log" -Command {
        .\scripts\assert_release_configuration.ps1 -ApiBaseUrl $ApiBaseUrl -UseHttp
    }

    Invoke-EvidenceCommand -Title "2. Release secrets hygiene" -LogName "release-secrets-hygiene.log" -Command {
        .\scripts\assert_no_release_secrets.ps1
    }

    Invoke-EvidenceCommand -Title "3. Logo/theme asset mapping" -LogName "logo-theme-assets.log" -Command {
        .\scripts\assert_logo_theme_assets.ps1
    }

    Write-Host ""
    Write-Host "4. Release Go/No-Go preflight"
    Write-Host "Go/No-Go will still be NO-GO until evidence gates are updated after real checks."

    Invoke-EvidenceCommand -Title "5. Flutter dependencies" -LogName "flutter-pub-get.log" -Command {
        & $FlutterCommand pub get
    }

    Invoke-EvidenceCommand -Title "6. Flutter dependency lock" -LogName "flutter-dependency-lock.log" -Command {
        .\scripts\assert_flutter_dependency_lock.ps1
    }

    Invoke-EvidenceCommand -Title "7. Flutter analyze" -LogName "flutter-analyze.log" -Command {
        & $FlutterCommand analyze
    }

    Invoke-EvidenceCommand -Title "8. Android App Bundle release build" -LogName "android-appbundle-build.log" -Command {
        & $FlutterCommand build appbundle --release --dart-define=AIRMIUS_API_BASE_URL=$ApiBaseUrl --dart-define=AIRMIUS_USE_HTTP=true
    }

    Invoke-EvidenceCommand -Title "9. Android APK release build" -LogName "android-apk-build.log" -Command {
        & $FlutterCommand build apk --release --dart-define=AIRMIUS_API_BASE_URL=$ApiBaseUrl --dart-define=AIRMIUS_USE_HTTP=true
    }

    $ArtifactsDir = Join-Path $EvidenceDir "artifacts"
    New-Item -ItemType Directory -Force -Path $ArtifactsDir | Out-Null
    Copy-Item -Path "build\app\outputs\bundle\release\app-release.aab" -Destination (Join-Path $ArtifactsDir "app-release.aab") -ErrorAction SilentlyContinue
    Copy-Item -Path "build\app\outputs\flutter-apk\app-release.apk" -Destination (Join-Path $ArtifactsDir "app-release.apk") -ErrorAction SilentlyContinue

    Write-Host ""
    Write-Host "Release-candidate checks finished. iOS IPA must be built on macOS with Apple signing:"
    Write-Host "$FlutterCommand build ipa --release --dart-define=AIRMIUS_API_BASE_URL=$ApiBaseUrl --dart-define=AIRMIUS_USE_HTTP=true"
} finally {
    Pop-Location
}
