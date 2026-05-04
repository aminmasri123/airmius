$ErrorActionPreference = 'Stop'

$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$partsPath = Join-Path $root 'storage\app\generated\airmius-manual-docx'
$docxPath = Join-Path $root 'docs\Airmius_Bedienhandbuch.docx'

php (Join-Path $PSScriptRoot 'create_user_manual.php')

if (-not (Test-Path -LiteralPath $partsPath)) {
    throw "DOCX parts directory was not created: $partsPath"
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$base = (Resolve-Path $partsPath).Path
$stream = [System.IO.File]::Open($docxPath, [System.IO.FileMode]::Create, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)

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

Write-Host "DOCX: $docxPath"
