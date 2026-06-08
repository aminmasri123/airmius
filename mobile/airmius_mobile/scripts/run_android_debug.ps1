param(
    [string]$ApiBaseUrl = "http://10.0.2.2",
    [switch]$UseHttp
)

$ErrorActionPreference = "Stop"

$dartDefines = @(
    "--dart-define=AIRMIUS_API_BASE_URL=$ApiBaseUrl"
)

if ($UseHttp) {
    $dartDefines += "--dart-define=AIRMIUS_USE_HTTP=true"
}

Write-Host "Starting Airmius Mobile on Android"
Write-Host "API base URL: $ApiBaseUrl"
Write-Host "HTTP transport: $UseHttp"

flutter run -d android @dartDefines
