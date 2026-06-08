param(
    [string]$ManifestPath = "store_listing\release\release_evidence_manifest.json",
    [string]$EvidencePath = "release_evidence"
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$ResolvedManifestPath = Join-Path $Root $ManifestPath
$ResolvedEvidencePath = Join-Path $Root $EvidencePath

if (-not (Test-Path $ResolvedManifestPath)) {
    throw "Release evidence manifest not found: $ResolvedManifestPath"
}

$Manifest = Get-Content -Path $ResolvedManifestPath -Raw | ConvertFrom-Json
$Updates = New-Object System.Collections.Generic.List[string]

function Test-LogContainsNoFailure {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return $false
    }

    $Content = Get-Content -Path $Path -Raw
    if ([string]::IsNullOrWhiteSpace($Content)) {
        return $false
    }

    $NormalizedContent = $Content -replace "(?i)\b0\s+errors?\b", "zero-error-marker"
    $NormalizedContent = $NormalizedContent -replace "(?i)\b0\s+failed\b", "zero-failed-marker"
    $NormalizedContent = $NormalizedContent -replace "(?i)\bno\s+errors?\b", "no-error-marker"
    $NormalizedContent = $NormalizedContent -replace "(?i)\bno\s+failures?\b", "no-failure-marker"

    return -not ($NormalizedContent -match "(?i)\b(error|failed|exception|fatal)\b")
}

function Set-GateStatus {
    param(
        [string]$GateId,
        [string]$Status,
        [string]$Note
    )

    foreach ($Gate in $Manifest.gates) {
        if ($Gate.id -eq $GateId) {
            $Gate.status = $Status
            $Gate | Add-Member -NotePropertyName "evidence_note" -NotePropertyValue $Note -Force
            $Updates.Add("$GateId -> $Status: $Note")
            return
        }
    }
}

$AnalyzeLog = Join-Path $ResolvedEvidencePath "flutter-analyze.log"
if (Test-LogContainsNoFailure -Path $AnalyzeLog) {
    Set-GateStatus -GateId "flutter_analyze" -Status "passed" -Note "flutter-analyze.log exists and contains no obvious failure markers."
}

$LocalPrerequisitesLog = Join-Path $ResolvedEvidencePath "local-release-prerequisites.log"
if (Test-LogContainsNoFailure -Path $LocalPrerequisitesLog) {
    Set-GateStatus -GateId "local_release_prerequisites" -Status "passed" -Note "Local release prerequisites log exists and contains no obvious failure markers."
}

$AndroidBundleLog = Join-Path $ResolvedEvidencePath "android-appbundle-build.log"
$AndroidApkLog = Join-Path $ResolvedEvidencePath "android-apk-build.log"
$AndroidBundle = Join-Path $ResolvedEvidencePath "artifacts\app-release.aab"
$AndroidApk = Join-Path $ResolvedEvidencePath "artifacts\app-release.apk"
if ((Test-LogContainsNoFailure -Path $AndroidBundleLog) -and (Test-LogContainsNoFailure -Path $AndroidApkLog) -and (Test-Path $AndroidBundle) -and (Test-Path $AndroidApk)) {
    Set-GateStatus -GateId "android_release_build" -Status "passed" -Note "Android release logs and AAB/APK artifacts are present."
}

$IosNoCodesignLog = Join-Path $ResolvedEvidencePath "ios-release-build-no-codesign.log"
$IosCiNoCodesignLog = Join-Path $ResolvedEvidencePath "ios-release-build-no-codesign-ios.log"
$IosAnalyzeLog = Join-Path $ResolvedEvidencePath "flutter-analyze-ios.log"
$IosRunnerAppZip = Join-Path $ResolvedEvidencePath "artifacts\Runner.app.zip"
$IosIpaDirectory = Join-Path $Root "build\ios\ipa"
$EvidenceArtifactsDirectory = Join-Path $ResolvedEvidencePath "artifacts"
$SignedIpa = Get-ChildItem -Path $IosIpaDirectory -Filter "*.ipa" -File -ErrorAction SilentlyContinue | Select-Object -First 1
$EvidenceSignedIpa = Get-ChildItem -Path $EvidenceArtifactsDirectory -Filter "*.ipa" -File -ErrorAction SilentlyContinue | Select-Object -First 1
$HasSignedIpaEvidence = ($null -ne $SignedIpa) -or ($null -ne $EvidenceSignedIpa)
$HasIosNoCodesignEvidence = (Test-LogContainsNoFailure -Path $IosNoCodesignLog) -or (Test-LogContainsNoFailure -Path $IosCiNoCodesignLog) -or (Test-Path $IosRunnerAppZip)
$HasIosAnalyzeEvidence = Test-LogContainsNoFailure -Path $IosAnalyzeLog

if ($HasIosNoCodesignEvidence -and $HasSignedIpaEvidence) {
    Set-GateStatus -GateId "ios_release_build" -Status "passed" -Note "iOS no-codesign evidence and signed IPA/TestFlight artifact evidence are present."
} elseif ($HasIosNoCodesignEvidence) {
    $IosGate = $Manifest.gates | Where-Object { $_.id -eq "ios_release_build" } | Select-Object -First 1
    if ($null -ne $IosGate -and $IosGate.status -ne "passed") {
        Set-GateStatus -GateId "ios_release_build" -Status "partial_evidence" -Note "iOS no-codesign evidence is present; signed IPA/TestFlight evidence is still required."
    }
}

if ((Test-LogContainsNoFailure -Path $AnalyzeLog) -or $HasIosAnalyzeEvidence) {
    Set-GateStatus -GateId "flutter_analyze" -Status "passed" -Note "Flutter analyze evidence is present and contains no obvious failure markers."
}

$DependencyLockLog = Join-Path $ResolvedEvidencePath "flutter-dependency-lock.log"
$DependencyLockIosLog = Join-Path $ResolvedEvidencePath "flutter-dependency-lock-ios.log"
if ((Test-LogContainsNoFailure -Path $DependencyLockLog) -or (Test-LogContainsNoFailure -Path $DependencyLockIosLog)) {
    Set-GateStatus -GateId "flutter_dependency_lock" -Status "passed" -Note "Flutter dependency lock evidence is present and contains no obvious failure markers."
}

$LogoThemeLog = Join-Path $ResolvedEvidencePath "logo-theme-assets.log"
$LogoThemeIosLog = Join-Path $ResolvedEvidencePath "logo-theme-assets-ios.log"
if ((Test-LogContainsNoFailure -Path $LogoThemeLog) -or (Test-LogContainsNoFailure -Path $LogoThemeIosLog)) {
    Set-GateStatus -GateId "logo_theme_asset_mapping" -Status "passed" -Note "Logo/theme asset mapping evidence is present and contains no obvious failure markers."
}

$ManualEvidencePackLog = Join-Path $ResolvedEvidencePath "manual-evidence-pack.log"
if (Test-LogContainsNoFailure -Path $ManualEvidencePackLog) {
    Set-GateStatus -GateId "manual_evidence_pack" -Status "passed" -Note "Manual evidence pack checker log exists and contains no obvious failure markers."
}

$ReleaseConfigLog = Join-Path $ResolvedEvidencePath "release-configuration-check.log"
if (Test-LogContainsNoFailure -Path $ReleaseConfigLog) {
    Set-GateStatus -GateId "release_configuration" -Status "passed" -Note "Release configuration check log exists and contains no obvious failure markers."
}

$SecretsLog = Join-Path $ResolvedEvidencePath "release-secrets-hygiene.log"
if (Test-LogContainsNoFailure -Path $SecretsLog) {
    Set-GateStatus -GateId "release_secrets_hygiene" -Status "passed" -Note "Release secrets hygiene log exists and contains no obvious failure markers."
}

$LaravelApiSmokeLog = Join-Path $ResolvedEvidencePath "laravel-api-smoke.log"
if (Test-Path $LaravelApiSmokeLog) {
    $LaravelApiSmokeContent = Get-Content -Path $LaravelApiSmokeLog -Raw
    $RequiredReadSmokeMarkers = @(
        "PASS POST /api/v1/auth/login",
        "PASS GET /api/v1/search",
        "PASS GET /api/v1/clubs",
        "PASS GET /api/v1/notifications",
        "PASS GET /api/v1/conversations",
        "PASS GET /api/v1/events",
        "PASS GET /api/v1/billing/invoices"
    )
    $HasRequiredReadSmoke = $true

    foreach ($Marker in $RequiredReadSmokeMarkers) {
        if ($LaravelApiSmokeContent -notmatch [regex]::Escape($Marker)) {
            $HasRequiredReadSmoke = $false
            break
        }
    }

    $HasClubDetailSmoke = $LaravelApiSmokeContent -match "PASS GET /api/v1/clubs/\d+"
    $HasUploadIntentSmoke = $LaravelApiSmokeContent -match "PASS POST /api/v1/files/upload-intents"
    $HasSmokeFailure = $LaravelApiSmokeContent -match "(?i)\bFAIL\b"

    if ($HasRequiredReadSmoke -and $HasClubDetailSmoke -and -not $HasSmokeFailure) {
        $RealApiGate = $Manifest.gates | Where-Object { $_.id -eq "real_api_qa" } | Select-Object -First 1
        if ($null -ne $RealApiGate -and $RealApiGate.status -ne "passed") {
            if ($HasUploadIntentSmoke) {
                Set-GateStatus -GateId "real_api_qa" -Status "partial_evidence" -Note "Laravel API smoke log includes read-flow and upload-intent evidence; membership request, withdrawal and full backend QA are still required."
            } else {
                Set-GateStatus -GateId "real_api_qa" -Status "partial_evidence" -Note "Laravel API smoke log includes read-flow evidence; upload-intent, membership request, withdrawal and full backend QA are still required."
            }
        }
    }
}

$MembershipApiSmokeLog = Join-Path $ResolvedEvidencePath "membership-api-smoke.log"
if (Test-Path $MembershipApiSmokeLog) {
    $MembershipApiSmokeContent = Get-Content -Path $MembershipApiSmokeLog -Raw
    $HasMembershipCreate = $MembershipApiSmokeContent -match "PASS POST /api/v1/clubs/\d+/membership-applications"
    $HasMembershipDetail = $MembershipApiSmokeContent -match "PASS GET /api/v1/membership-applications/\d+"
    $HasMembershipWithdraw = $MembershipApiSmokeContent -match "PASS POST /api/v1/membership-applications/\d+/withdraw"
    $HasMembershipFailure = $MembershipApiSmokeContent -match "(?i)\bFAIL\b"

    if ($HasMembershipCreate -and $HasMembershipDetail -and -not $HasMembershipFailure) {
        $RealApiGate = $Manifest.gates | Where-Object { $_.id -eq "real_api_qa" } | Select-Object -First 1
        if ($null -ne $RealApiGate -and $RealApiGate.status -ne "passed") {
            if ($HasMembershipWithdraw) {
                Set-GateStatus -GateId "real_api_qa" -Status "partial_evidence" -Note "Membership API smoke log includes create, detail and withdrawal evidence; full backend QA is still required."
            } else {
                Set-GateStatus -GateId "real_api_qa" -Status "partial_evidence" -Note "Membership API smoke log includes create and detail evidence; withdrawal and full backend QA are still required."
            }
        }
    }
}

$BundleZip = Join-Path $Root "store_listing\release\airmius_release_evidence_bundle.zip"
if (Test-Path $BundleZip) {
    Set-GateStatus -GateId "release_evidence_bundle" -Status "passed" -Note "Release evidence bundle ZIP exists."
}

$ManifestJson = $Manifest | ConvertTo-Json -Depth 12
$ManifestJson | Set-Content -Path $ResolvedManifestPath -Encoding UTF8

if ($Updates.Count -eq 0) {
    Write-Output "No release evidence manifest gates were updated."
    Write-Output "Evidence path checked: $ResolvedEvidencePath"
    exit 0
}

Write-Output "Release evidence manifest updated:"
foreach ($Update in $Updates) {
    Write-Output " - $Update"
}
