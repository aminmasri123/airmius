param(
    [string]$FlutterCommand = "flutter"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$Failures = New-Object System.Collections.Generic.List[string]

function Test-CommandAvailable {
    param([string]$Command)
    return $null -ne (Get-Command $Command -ErrorAction SilentlyContinue)
}

function Test-AnyPath {
    param([string[]]$Paths)

    foreach ($Path in $Paths) {
        if (-not [string]::IsNullOrWhiteSpace($Path) -and (Test-Path $Path)) {
            return $Path
        }
    }

    return $null
}

if (-not (Test-CommandAvailable -Command $FlutterCommand)) {
    $Failures.Add("Flutter command not found: $FlutterCommand")
}

$RequiredFiles = @(
    "pubspec.yaml",
    "android\app\build.gradle.kts",
    "android\app\src\main\AndroidManifest.xml",
    "ios\Runner.xcodeproj\project.pbxproj",
    "store_listing\release\release_evidence_manifest.json",
    "store_listing\release\final_execution_sequence.md",
    "store_listing\release\final_command_cheatsheet.md",
    "store_listing\release\windows_android_setup_runbook.md",
    "store_listing\release\store_submission_readiness_runbook.md",
    "scripts\run_full_release_evidence_pipeline.ps1",
    "scripts\release_candidate_checks.ps1",
    "scripts\assert_release_configuration.ps1",
    "scripts\assert_no_release_secrets.ps1",
    "scripts\assert_flutter_dependency_lock.ps1",
    "scripts\assert_logo_theme_assets.ps1",
    "scripts\assert_manual_release_evidence_pack.ps1",
    "scripts\show_next_release_steps.ps1",
    "scripts\export_next_release_steps_report.ps1"
)

foreach ($File in $RequiredFiles) {
    if (-not (Test-Path (Join-Path $Root $File))) {
        $Failures.Add("Required file missing: $File")
    }
}

$AndroidHome = $env:ANDROID_HOME
$AndroidSdkRoot = $env:ANDROID_SDK_ROOT
$AndroidSdkPath = ""
if ([string]::IsNullOrWhiteSpace($AndroidHome) -and [string]::IsNullOrWhiteSpace($AndroidSdkRoot)) {
    $Failures.Add("ANDROID_HOME or ANDROID_SDK_ROOT is not set.")
} elseif (-not [string]::IsNullOrWhiteSpace($AndroidHome)) {
    $AndroidSdkPath = $AndroidHome
} else {
    $AndroidSdkPath = $AndroidSdkRoot
}

$AdbPath = $null
$SdkManagerPath = $null
$LicensePath = $null

if (-not [string]::IsNullOrWhiteSpace($AndroidSdkPath)) {
    if (-not (Test-Path $AndroidSdkPath)) {
        $Failures.Add("Android SDK path does not exist: $AndroidSdkPath")
    } else {
        $AdbPath = Test-AnyPath @(
            (Join-Path $AndroidSdkPath "platform-tools\adb.exe"),
            (Join-Path $AndroidSdkPath "platform-tools\adb")
        )

        if ($null -eq $AdbPath) {
            $Failures.Add("Android adb not found. Install Android SDK Platform-Tools.")
        }

        $SdkManagerPath = Test-AnyPath @(
            (Join-Path $AndroidSdkPath "cmdline-tools\latest\bin\sdkmanager.bat"),
            (Join-Path $AndroidSdkPath "cmdline-tools\latest\bin\sdkmanager"),
            (Join-Path $AndroidSdkPath "cmdline-tools\bin\sdkmanager.bat"),
            (Join-Path $AndroidSdkPath "cmdline-tools\bin\sdkmanager"),
            (Join-Path $AndroidSdkPath "tools\bin\sdkmanager.bat"),
            (Join-Path $AndroidSdkPath "tools\bin\sdkmanager")
        )

        if ($null -eq $SdkManagerPath) {
            $Failures.Add("Android cmdline-tools sdkmanager not found. Install Android Studio Command-line Tools.")
        }

        $LicensesDirectory = Join-Path $AndroidSdkPath "licenses"
        if (Test-Path $LicensesDirectory) {
            $LicenseFiles = Get-ChildItem -Path $LicensesDirectory -File -ErrorAction SilentlyContinue
            if ($LicenseFiles.Count -gt 0) {
                $LicensePath = $LicensesDirectory
            }
        }

        if ($null -eq $LicensePath) {
            $Failures.Add("Android SDK licenses are missing. Run 'flutter doctor --android-licenses' after installing cmdline-tools.")
        }
    }
}

$JavaCommandAvailable = Test-CommandAvailable -Command "java"
if (-not $JavaCommandAvailable) {
    $Failures.Add("Java command not found.")
}

if ($Failures.Count -gt 0) {
    Write-Output "Local release prerequisites failed:"
    foreach ($Failure in $Failures) {
        Write-Output " - $Failure"
    }
    throw "Local release prerequisites check failed."
}

Write-Output "Local release prerequisites check passed."
Write-Output "Workspace: $Root"
Write-Output "Flutter command: $FlutterCommand"
Write-Output "ANDROID_HOME: $AndroidHome"
Write-Output "ANDROID_SDK_ROOT: $AndroidSdkRoot"
Write-Output "Android SDK path: $AndroidSdkPath"
Write-Output "adb: $AdbPath"
Write-Output "sdkmanager: $SdkManagerPath"
Write-Output "Android licenses: $LicensePath"
