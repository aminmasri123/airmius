param(
    [string]$ApiBaseUrl = "https://app.airmius.com",
    [Parameter(Mandatory = $true)]
    [string]$Email,
    [string]$Password = "",
    [string]$SearchQuery = "zbb",
    [int]$ClubId = 26,
    [switch]$IncludeWriteChecks,
    [string]$OutputPath = "release_evidence\laravel-api-smoke.log"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ResolvedOutputPath = Join-Path $Root $OutputPath
$OutputDirectory = Split-Path -Parent $ResolvedOutputPath

if (-not (Test-Path $OutputDirectory)) {
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
}

if ([string]::IsNullOrWhiteSpace($Password)) {
    $SecurePassword = Read-Host "Password for $Email" -AsSecureString
    $Password = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecurePassword))
}

function Write-SmokeLog {
    param([string]$Message)
    $Line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
    Write-Output $Line
    Add-Content -Path $ResolvedOutputPath -Value $Line
}

function Invoke-AirmiusJson {
    param(
        [string]$Method,
        [string]$Path,
        [hashtable]$Headers = @{},
        [object]$Body = $null
    )

    $Uri = "$ApiBaseUrl$Path"
    $Request = @{
        Method = $Method
        Uri = $Uri
        Headers = $Headers
        ContentType = "application/json"
        TimeoutSec = 30
    }

    if ($null -ne $Body) {
        $Request.Body = ($Body | ConvertTo-Json -Depth 10)
    }

    try {
        return Invoke-RestMethod @Request
    } catch {
        Write-SmokeLog "FAIL $Method $Path"
        Write-SmokeLog $_.Exception.Message
        throw
    }
}

Set-Content -Path $ResolvedOutputPath -Value "Airmius Laravel API smoke test"
Write-SmokeLog "API base URL: $ApiBaseUrl"
Write-SmokeLog "Account: $Email"
Write-SmokeLog "Password: <not logged>"
Write-SmokeLog "Include write checks: $IncludeWriteChecks"

$Login = Invoke-AirmiusJson -Method "POST" -Path "/api/v1/auth/login" -Body @{
    email = $Email
    password = $Password
}

$Token = $Login.token
if ([string]::IsNullOrWhiteSpace($Token) -and $null -ne $Login.session) {
    $Token = $Login.session.token
}

if ([string]::IsNullOrWhiteSpace($Token)) {
    throw "Login response did not contain token or session.token."
}

$Headers = @{
    Authorization = "Bearer $Token"
    Accept = "application/json"
}

Write-SmokeLog "PASS POST /api/v1/auth/login"

Invoke-AirmiusJson -Method "GET" -Path "/api/v1/search?q=$([uri]::EscapeDataString($SearchQuery))" -Headers $Headers | Out-Null
Write-SmokeLog "PASS GET /api/v1/search"

Invoke-AirmiusJson -Method "GET" -Path "/api/v1/clubs?q=$([uri]::EscapeDataString($SearchQuery))" -Headers $Headers | Out-Null
Write-SmokeLog "PASS GET /api/v1/clubs"

Invoke-AirmiusJson -Method "GET" -Path "/api/v1/clubs/$ClubId" -Headers $Headers | Out-Null
Write-SmokeLog "PASS GET /api/v1/clubs/$ClubId"

Invoke-AirmiusJson -Method "GET" -Path "/api/v1/notifications" -Headers $Headers | Out-Null
Write-SmokeLog "PASS GET /api/v1/notifications"

Invoke-AirmiusJson -Method "GET" -Path "/api/v1/conversations" -Headers $Headers | Out-Null
Write-SmokeLog "PASS GET /api/v1/conversations"

Invoke-AirmiusJson -Method "GET" -Path "/api/v1/events" -Headers $Headers | Out-Null
Write-SmokeLog "PASS GET /api/v1/events"

Invoke-AirmiusJson -Method "GET" -Path "/api/v1/billing/invoices" -Headers $Headers | Out-Null
Write-SmokeLog "PASS GET /api/v1/billing/invoices"

if ($IncludeWriteChecks) {
    Invoke-AirmiusJson -Method "POST" -Path "/api/v1/files/upload-intents" -Headers $Headers -Body @{
        scope = "club-document"
        file_name = "release-smoke-test.pdf"
        mime_type = "application/pdf"
    } | Out-Null
    Write-SmokeLog "PASS POST /api/v1/files/upload-intents"
} else {
    Write-SmokeLog "SKIP write checks. Re-run with -IncludeWriteChecks only on safe staging/review data."
}

Write-SmokeLog "Laravel API smoke test finished."
Write-SmokeLog "Log: $ResolvedOutputPath"
