param(
    [string]$ApiBaseUrl = "http://localhost",
    [switch]$UseHttp
)

$ErrorActionPreference = "Stop"

$dartDefines = @(
    "--dart-define=AIRMIUS_API_BASE_URL=$ApiBaseUrl"
)

if ($UseHttp) {
    $dartDefines += "--dart-define=AIRMIUS_USE_HTTP=true"
}

Write-Host "Starting Airmius Mobile on Chrome"
Write-Host "API base URL: $ApiBaseUrl"
Write-Host "HTTP transport: $UseHttp"

flutter run -d chrome @dartDefines
