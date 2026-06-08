param()

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$Failures = New-Object System.Collections.Generic.List[string]

$RequiredAssets = @(
    "assets\images\airmius-mark.png",
    "assets\images\airmius-wordmark-dark.png",
    "assets\images\airmius-wordmark-light.png",
    "assets\images\airmius-full-dark.png",
    "assets\images\airmius-full-light.png"
)

foreach ($Asset in $RequiredAssets) {
    if (-not (Test-Path (Join-Path $Root $Asset))) {
        $Failures.Add("Missing logo asset: $Asset")
    }
}

$WidgetPath = Join-Path $Root "lib\widgets\airmius_widgets.dart"
if (-not (Test-Path $WidgetPath)) {
    $Failures.Add("Airmius logo widget missing: lib\widgets\airmius_widgets.dart")
} else {
    $WidgetSource = Get-Content -Raw -Path $WidgetPath

    $RequiredMappings = @(
        "ThemeMode.dark => true",
        "ThemeMode.light => false",
        "ThemeMode.system => Theme.of(context).brightness == Brightness.dark",
        "useDarkUiLogo ? 'assets/images/airmius-wordmark-dark.png' : 'assets/images/airmius-wordmark-light.png'",
        "useDarkUiLogo ? 'assets/images/airmius-full-dark.png' : 'assets/images/airmius-full-light.png'"
    )

    foreach ($Mapping in $RequiredMappings) {
        if (-not $WidgetSource.Contains($Mapping)) {
            $Failures.Add("Logo/theme mapping not found in AirmiusLogo: $Mapping")
        }
    }
}

if ($Failures.Count -gt 0) {
    Write-Output "Logo/theme asset check failed:"
    foreach ($Failure in $Failures) {
        Write-Output " - $Failure"
    }
    throw "Logo/theme asset check failed."
}

Write-Output "Logo/theme asset check passed."
Write-Output "Required logo assets exist and AirmiusLogo maps Normal/Dunkel/System as expected."
