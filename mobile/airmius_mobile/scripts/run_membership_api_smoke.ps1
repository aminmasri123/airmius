param(
    [string]$ApiBaseUrl = "https://app.airmius.com",
    [Parameter(Mandatory = $true)]
    [string]$Email,
    [string]$Password = "",
    [int]$ClubId = 26,
    [Parameter(Mandatory = $true)]
    [string]$ApplicationPayloadPath,
    [switch]$WithdrawAfterCreate,
    [string]$OutputPath = "release_evidence\membership-api-smoke.log"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ResolvedPayloadPath = if ([System.IO.Path]::IsPathRooted($ApplicationPayloadPath)) {
    $ApplicationPayloadPath
} else {
    Join-Path $Root $ApplicationPayloadPath
}
$ResolvedOutputPath = Join-Path $Root $OutputPath
$OutputDirectory = Split-Path -Parent $ResolvedOutputPath

if (-not (Test-Path $ResolvedPayloadPath)) {
    throw "Membership application payload file not found: $ResolvedPayloadPath"
}

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
        $Request.Body = ($Body | ConvertTo-Json -Depth 20)
    }

    try {
        return Invoke-RestMethod @Request
    } catch {
        Write-SmokeLog "FAIL $Method $Path"
        Write-SmokeLog $_.Exception.Message
        throw
    }
}

Set-Content -Path $ResolvedOutputPath -Value "Airmius membership API smoke test"
Write-SmokeLog "API base URL: $ApiBaseUrl"
Write-SmokeLog "Account: $Email"
Write-SmokeLog "Password: <not logged>"
Write-SmokeLog "Club ID: $ClubId"
Write-SmokeLog "Payload file: $ResolvedPayloadPath"
Write-SmokeLog "Withdraw after create: $WithdrawAfterCreate"

$Payload = Get-Content -Path $ResolvedPayloadPath -Raw | ConvertFrom-Json

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

$Created = Invoke-AirmiusJson -Method "POST" -Path "/api/v1/clubs/$ClubId/membership-applications" -Headers $Headers -Body $Payload
$ApplicationId = $Created.id
if ($null -eq $ApplicationId -and $null -ne $Created.application) {
    $ApplicationId = $Created.application.id
}
if ($null -eq $ApplicationId -and $null -ne $Created.data) {
    $ApplicationId = $Created.data.id
}

if ($null -eq $ApplicationId) {
    throw "Membership application response did not contain id, application.id or data.id."
}

Write-SmokeLog "PASS POST /api/v1/clubs/$ClubId/membership-applications"
Write-SmokeLog "Created application id: $ApplicationId"

Invoke-AirmiusJson -Method "GET" -Path "/api/v1/membership-applications/$ApplicationId" -Headers $Headers | Out-Null
Write-SmokeLog "PASS GET /api/v1/membership-applications/$ApplicationId"

if ($WithdrawAfterCreate) {
    Invoke-AirmiusJson -Method "POST" -Path "/api/v1/membership-applications/$ApplicationId/withdraw" -Headers $Headers | Out-Null
    Write-SmokeLog "PASS POST /api/v1/membership-applications/$ApplicationId/withdraw"
} else {
    Write-SmokeLog "SKIP withdraw. Re-run with -WithdrawAfterCreate only on safe staging/review data."
}

Write-SmokeLog "Membership API smoke test finished."
Write-SmokeLog "Log: $ResolvedOutputPath"
