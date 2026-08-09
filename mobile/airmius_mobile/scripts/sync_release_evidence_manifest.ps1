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

function Test-LogMatchesEvidence {
    param(
        [string]$Path,
        [string[]]$RequiredMarkers = @(),
        [string[]]$RequiredPatterns = @()
    )

    if (-not (Test-LogContainsNoFailure -Path $Path)) {
        return $false
    }

    $Content = Get-Content -Path $Path -Raw

    foreach ($Marker in $RequiredMarkers) {
        if (-not $Content.Contains($Marker)) {
            return $false
        }
    }

    foreach ($Pattern in $RequiredPatterns) {
        if ($Content -notmatch $Pattern) {
            return $false
        }
    }

    return $true
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
$LocalPrerequisitesLog = Join-Path $ResolvedEvidencePath "local-release-prerequisites.log"
$HasWindowsPrerequisitesEvidence = Test-LogMatchesEvidence -Path $LocalPrerequisitesLog -RequiredMarkers @("Local release prerequisites check passed.")
$HasLinuxPrerequisitesEvidence = Test-LogMatchesEvidence -Path $LocalPrerequisitesLog -RequiredMarkers @("Linux Android release prerequisites passed.")
if ($HasWindowsPrerequisitesEvidence -or $HasLinuxPrerequisitesEvidence) {
    Set-GateStatus -GateId "local_release_prerequisites" -Status "passed" -Note "The prerequisite log contains an explicit successful checker result."
}

$VersionConsistencyLog = Join-Path $ResolvedEvidencePath "release-version-consistency.log"
$VersionConsistencyIosLog = Join-Path $ResolvedEvidencePath "release-version-consistency-ios.log"
$VersionMarkers = @("Release version consistency check passed.", "Version: $($Manifest.version)")
$HasVersionConsistencyEvidence =
    (Test-LogMatchesEvidence -Path $VersionConsistencyLog -RequiredMarkers $VersionMarkers) -or
    (Test-LogMatchesEvidence -Path $VersionConsistencyIosLog -RequiredMarkers $VersionMarkers)

if ($HasVersionConsistencyEvidence -and (Test-LogMatchesEvidence -Path $AnalyzeLog -RequiredMarkers @("No issues found!"))) {
    Set-GateStatus -GateId "flutter_analyze" -Status "passed" -Note "Version-bound Flutter analyze evidence contains the explicit success marker."
}

$AndroidBundleLog = Join-Path $ResolvedEvidencePath "android-appbundle-build.log"
$AndroidApkLog = Join-Path $ResolvedEvidencePath "android-apk-build.log"
$AndroidBundle = Join-Path $ResolvedEvidencePath "artifacts\app-release.aab"
$AndroidApk = Join-Path $ResolvedEvidencePath "artifacts\app-release.apk"
if ($HasVersionConsistencyEvidence -and
    (Test-LogMatchesEvidence -Path $AndroidBundleLog -RequiredPatterns @("(?i)\bBuilt\b.*app-release\.aab")) -and
    (Test-LogMatchesEvidence -Path $AndroidApkLog -RequiredPatterns @("(?i)\bBuilt\b.*app-release\.apk")) -and
    (Test-Path $AndroidBundle -PathType Leaf) -and (Get-Item $AndroidBundle).Length -gt 0 -and
    (Test-Path $AndroidApk -PathType Leaf) -and (Get-Item $AndroidApk).Length -gt 0) {
    Set-GateStatus -GateId "android_release_build" -Status "passed" -Note "Version-bound Android build logs contain explicit success markers and non-empty AAB/APK artifacts are present."
}

$IosNoCodesignLog = Join-Path $ResolvedEvidencePath "ios-release-build-no-codesign.log"
$IosCiNoCodesignLog = Join-Path $ResolvedEvidencePath "ios-release-build-no-codesign-ios.log"
$IosAnalyzeLog = Join-Path $ResolvedEvidencePath "flutter-analyze-ios.log"
$IosRunnerAppZip = Join-Path $ResolvedEvidencePath "artifacts\Runner.app.zip"
$IosIpaDirectory = Join-Path $Root "build\ios\ipa"
$EvidenceArtifactsDirectory = Join-Path $ResolvedEvidencePath "artifacts"
$SignedIpa = Get-ChildItem -Path $IosIpaDirectory -Filter "*.ipa" -File -ErrorAction SilentlyContinue | Select-Object -First 1
$EvidenceSignedIpa = Get-ChildItem -Path $EvidenceArtifactsDirectory -Filter "*.ipa" -File -ErrorAction SilentlyContinue | Select-Object -First 1
$HasSignedIpaEvidence =
    (($null -ne $SignedIpa) -and $SignedIpa.Length -gt 0) -or
    (($null -ne $EvidenceSignedIpa) -and $EvidenceSignedIpa.Length -gt 0)
$HasIosNoCodesignLog =
    (Test-LogMatchesEvidence -Path $IosNoCodesignLog -RequiredPatterns @("(?i)\bBuilt\b.*Runner\.app")) -or
    (Test-LogMatchesEvidence -Path $IosCiNoCodesignLog -RequiredPatterns @("(?i)\bBuilt\b.*Runner\.app"))
$HasIosRunnerArtifact = (Test-Path $IosRunnerAppZip -PathType Leaf) -and (Get-Item $IosRunnerAppZip).Length -gt 0
$HasIosNoCodesignEvidence = $HasIosNoCodesignLog -and $HasIosRunnerArtifact
$HasIosAnalyzeEvidence = Test-LogMatchesEvidence -Path $IosAnalyzeLog -RequiredMarkers @("No issues found!")

if ($HasVersionConsistencyEvidence -and $HasIosNoCodesignEvidence -and $HasSignedIpaEvidence) {
    Set-GateStatus -GateId "ios_release_build" -Status "passed" -Note "iOS no-codesign evidence and signed IPA/TestFlight artifact evidence are present."
} elseif ($HasVersionConsistencyEvidence -and $HasIosNoCodesignEvidence) {
    $IosGate = $Manifest.gates | Where-Object { $_.id -eq "ios_release_build" } | Select-Object -First 1
    if ($null -ne $IosGate -and $IosGate.status -ne "passed") {
        Set-GateStatus -GateId "ios_release_build" -Status "partial_evidence" -Note "iOS no-codesign evidence is present; signed IPA/TestFlight evidence is still required."
    }
}

if ($HasVersionConsistencyEvidence -and ((Test-LogMatchesEvidence -Path $AnalyzeLog -RequiredMarkers @("No issues found!")) -or $HasIosAnalyzeEvidence)) {
    Set-GateStatus -GateId "flutter_analyze" -Status "passed" -Note "Version-bound Flutter analyze evidence contains the explicit success marker."
}

$DependencyLockLog = Join-Path $ResolvedEvidencePath "flutter-dependency-lock.log"
$DependencyLockIosLog = Join-Path $ResolvedEvidencePath "flutter-dependency-lock-ios.log"
if ($HasVersionConsistencyEvidence -and ((Test-LogMatchesEvidence -Path $DependencyLockLog -RequiredMarkers @("Flutter dependency lock check passed.")) -or (Test-LogMatchesEvidence -Path $DependencyLockIosLog -RequiredMarkers @("Flutter dependency lock check passed.")))) {
    Set-GateStatus -GateId "flutter_dependency_lock" -Status "passed" -Note "Version-bound dependency-lock evidence contains the explicit success marker."
}

$LogoThemeLog = Join-Path $ResolvedEvidencePath "logo-theme-assets.log"
$LogoThemeIosLog = Join-Path $ResolvedEvidencePath "logo-theme-assets-ios.log"
if ($HasVersionConsistencyEvidence -and ((Test-LogMatchesEvidence -Path $LogoThemeLog -RequiredMarkers @("Logo/theme asset check passed.")) -or (Test-LogMatchesEvidence -Path $LogoThemeIosLog -RequiredMarkers @("Logo/theme asset check passed.")))) {
    Set-GateStatus -GateId "logo_theme_asset_mapping" -Status "passed" -Note "Version-bound logo/theme evidence contains the explicit success marker."
}

$ManualEvidencePackLog = Join-Path $ResolvedEvidencePath "manual-evidence-pack.log"
if ($HasVersionConsistencyEvidence -and (Test-LogMatchesEvidence -Path $ManualEvidencePackLog -RequiredMarkers @("Manual release evidence pack check passed."))) {
    Set-GateStatus -GateId "manual_evidence_pack" -Status "passed" -Note "Version-bound manual evidence pack log contains the explicit success marker."
}

$ReleaseConfigLog = Join-Path $ResolvedEvidencePath "release-configuration-check.log"
if ($HasVersionConsistencyEvidence -and (Test-LogMatchesEvidence -Path $ReleaseConfigLog -RequiredMarkers @("Release configuration check passed."))) {
    Set-GateStatus -GateId "release_configuration" -Status "passed" -Note "Version-bound release configuration evidence contains the explicit success marker."
}

$SecretsLog = Join-Path $ResolvedEvidencePath "release-secrets-hygiene.log"
if ($HasVersionConsistencyEvidence -and (Test-LogMatchesEvidence -Path $SecretsLog -RequiredMarkers @("Release secrets hygiene check passed."))) {
    Set-GateStatus -GateId "release_secrets_hygiene" -Status "passed" -Note "Version-bound secret-hygiene evidence contains the explicit success marker."
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
$BundleContentsLog = Join-Path $ResolvedEvidencePath "release-evidence-bundle-contents.log"
if ($HasVersionConsistencyEvidence -and
    (Test-Path $BundleZip -PathType Leaf) -and (Get-Item $BundleZip).Length -gt 0 -and
    (Test-LogMatchesEvidence -Path $BundleContentsLog -RequiredMarkers @("Release evidence bundle content check passed."))) {
    Set-GateStatus -GateId "release_evidence_bundle" -Status "passed" -Note "The version-bound non-empty bundle passed the explicit content validator."
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
