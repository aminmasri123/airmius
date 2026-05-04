$ErrorActionPreference = 'Stop'

$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$partsPath = Join-Path $root 'storage\app\generated\use-case-testmatrix-xlsx'
$xlsxPath = Join-Path $root 'docs\Airmius_Use_Cases_Testmatrix.xlsx'

php (Join-Path $PSScriptRoot 'create_use_case_test_matrix.php')

if (-not (Test-Path -LiteralPath $partsPath)) {
    throw "XLSX parts directory was not created: $partsPath"
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$base = (Resolve-Path $partsPath).Path
$stream = [System.IO.File]::Open($xlsxPath, [System.IO.FileMode]::Create, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)

try {
    $zip = [System.IO.Compression.ZipArchive]::new($stream, [System.IO.Compression.ZipArchiveMode]::Create, $true)

    try {
        Get-ChildItem -LiteralPath $base -Recurse -File | ForEach-Object {
            $relative = $_.FullName.Substring($base.Length + 1).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $zip,
                $_.FullName,
                $relative,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    } finally {
        $zip.Dispose()
    }
} finally {
    $stream.Dispose()
}

Write-Host "XLSX: $xlsxPath"
