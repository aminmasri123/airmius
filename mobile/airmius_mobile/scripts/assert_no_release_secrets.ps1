param(
    [string[]]$Paths = @(
        "README.md",
        "store_listing",
        "scripts",
        "lib\core\airmius_release_blockers.dart"
    )
)

$ErrorActionPreference = "Stop"

$Root = Resolve-Path (Join-Path $PSScriptRoot "..")
$Findings = New-Object System.Collections.Generic.List[string]

$SensitivePatterns = @(
    "password\s*[:=]\s*['""][^'""]{4,}",
    "passwort\s*[:=]\s*['""][^'""]{4,}",
    "token\s*[:=]\s*['""][^'""]{12,}",
    "secret\s*[:=]\s*['""][^'""]{8,}",
    "api[_-]?key\s*[:=]\s*['""][^'""]{8,}",
    "bearer\s+[a-zA-Z0-9._-]{12,}",
    "sk-[a-zA-Z0-9]{12,}"
)

foreach ($RelativePath in $Paths) {
    $FullPath = Join-Path $Root $RelativePath

    if (-not (Test-Path $FullPath)) {
        continue
    }

    $Files = if ((Get-Item $FullPath).PSIsContainer) {
        Get-ChildItem -Path $FullPath -Recurse -File -Include *.md,*.json,*.ps1,*.dart,*.yaml,*.yml,*.txt
    } else {
        @(Get-Item $FullPath)
    }

    foreach ($File in $Files) {
        $Content = Get-Content -Path $File.FullName -Raw
        foreach ($Pattern in $SensitivePatterns) {
            if ($Content -match $Pattern) {
                $DisplayPath = Resolve-Path -Path $File.FullName -Relative
                $Findings.Add("$DisplayPath matched pattern: $Pattern")
            }
        }
    }
}

if ($Findings.Count -gt 0) {
    Write-Output "Potential release secrets found:"
    foreach ($Finding in $Findings) {
        Write-Output " - $Finding"
    }
    throw "Release secrets hygiene check failed."
}

Write-Output "Release secrets hygiene check passed."
Write-Output "Scanned paths:"
foreach ($Path in $Paths) {
    Write-Output " - $Path"
}
