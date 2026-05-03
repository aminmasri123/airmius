param(
    [string] $Source = "docs/USER_CASES.md",
    [string] $Destination = "docs/Airmius_Use_Cases_Test_Checklist.pptx"
)

$ErrorActionPreference = "Stop"

$root = (Resolve-Path ".").Path
$sourcePath = Join-Path $root $Source
$destinationPath = Join-Path $root $Destination
$workDir = Join-Path $root "storage\app\generated-pptx\use-case-checklist"
$logoPath = Join-Path $root "public\img\logo\Logo-Airmius-mit-Schrift.png"

function XmlEscape([string] $value) {
    if ($null -eq $value) { return "" }
    return [System.Security.SecurityElement]::Escape($value)
}

function ShapeId {
    $script:shapeCounter += 1
    return $script:shapeCounter
}

function TextBox([int64] $x, [int64] $y, [int64] $cx, [int64] $cy, [string] $text, [int] $size = 12, [string] $color = "FFFFFF", [bool] $bold = $false, [string] $align = "l", [string] $fill = "") {
    $id = ShapeId
    $boldAttr = if ($bold) { ' b="1"' } else { "" }
    $fillXml = if ($fill) { "<a:solidFill><a:srgbClr val=""$fill""/></a:solidFill>" } else { "<a:noFill/>" }
    return @"
<p:sp><p:nvSpPr><p:cNvPr id="$id" name="Text"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="$x" y="$y"/><a:ext cx="$cx" cy="$cy"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom>$fillXml<a:ln><a:noFill/></a:ln></p:spPr><p:txBody><a:bodyPr wrap="square" lIns="70000" tIns="45000" rIns="70000" bIns="45000"/><a:lstStyle/><a:p><a:pPr algn="$align"/><a:r><a:rPr lang="de-DE" sz="$($size * 100)"$boldAttr><a:solidFill><a:srgbClr val="$color"/></a:solidFill><a:latin typeface="Aptos"/></a:rPr><a:t>$(XmlEscape $text)</a:t></a:r></a:p></p:txBody></p:sp>
"@
}

function Rect([int64] $x, [int64] $y, [int64] $cx, [int64] $cy, [string] $color) {
    $id = ShapeId
    return @"
<p:sp><p:nvSpPr><p:cNvPr id="$id" name="Shape"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="$x" y="$y"/><a:ext cx="$cx" cy="$cy"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="$color"/></a:solidFill><a:ln><a:noFill/></a:ln></p:spPr><p:txBody><a:bodyPr/><a:lstStyle/><a:p/></p:txBody></p:sp>
"@
}

function Line([int64] $x, [int64] $y, [int64] $cx, [int64] $cy, [string] $color = "263244") {
    $id = ShapeId
    return @"
<p:sp><p:nvSpPr><p:cNvPr id="$id" name="Line"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="$x" y="$y"/><a:ext cx="$cx" cy="$cy"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="$color"/></a:solidFill><a:ln><a:noFill/></a:ln></p:spPr><p:txBody><a:bodyPr/><a:lstStyle/><a:p/></p:txBody></p:sp>
"@
}

function Picture([string] $relId, [int64] $x, [int64] $y, [int64] $cx, [int64] $cy) {
    $id = ShapeId
    return @"
<p:pic><p:nvPicPr><p:cNvPr id="$id" name="Picture"/><p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr><p:nvPr/></p:nvPicPr><p:blipFill><a:blip r:embed="$relId"/><a:stretch><a:fillRect/></a:stretch></p:blipFill><p:spPr><a:xfrm><a:off x="$x" y="$y"/><a:ext cx="$cx" cy="$cy"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic>
"@
}

function MakeExpectation([string] $item) {
    $clean = $item.Trim(".")
    if ($clean -match "löschen") { return "Datensatz wird nach Bestätigung entfernt oder korrekt blockiert." }
    if ($clean -match "erstellen|anlegen|einreichen|hochladen|importieren") { return "Datensatz wird angelegt, validiert und in der passenden Übersicht sichtbar." }
    if ($clean -match "bearbeiten|pflegen|setzen|aktualisieren|ändern") { return "Änderung wird gespeichert, angezeigt und bei Reload beibehalten." }
    if ($clean -match "anzeigen|öffnen|sehen|lesen|listen") { return "Seite oder Inhalt öffnet ohne Fehler und zeigt nur berechtigte Daten." }
    if ($clean -match "zahlen|bezahlen|Checkout|Überweisung|Stripe|PayPal") { return "Zahlungsstatus, Rechnung und Benachrichtigung werden korrekt aktualisiert." }
    if ($clean -match "prüfen|moderieren|melden|sperren") { return "Fall wird markiert, nachvollziehbar protokolliert und korrekt entschieden." }
    if ($clean -match "Benachrichtigung|E-Mail|Erinnerung|Mahnung") { return "Empfänger erhält die korrekte Nachricht mit passendem Inhalt." }
    return "Funktion ist ausführbar, validiert Eingaben und erzeugt den erwarteten Zustand."
}

function Parse-UseCases([string] $path) {
    $sections = @()
    $current = $null
    $capture = $false
    $lines = Get-Content -LiteralPath $path -Encoding UTF8

    foreach ($line in $lines) {
        if ($line -match "^###\s+(.+)$") {
            if ($null -ne $current -and $current.Items.Count -gt 0) {
                $sections += $current
            }
            $current = [PSCustomObject]@{
                Title = $matches[1].Trim()
                Items = New-Object System.Collections.Generic.List[string]
            }
            $capture = $false
            continue
        }

        if ($null -eq $current) { continue }

        if ($line.Trim() -eq "Use Cases:") {
            $capture = $true
            continue
        }

        if ($line.Trim() -eq "Relevante Routen:" -or $line -match "^##\s+") {
            $capture = $false
            continue
        }

        if ($capture -and $line -match "^\s*-\s+(.+)$") {
            $item = $matches[1].Trim()
            if ($item -and $item -notmatch "^(low|medium|high|draft|review|published|archived|new|contacted|quoted|in_progress|done|cancelled)\.?$") {
                $current.Items.Add($item.TrimEnd(".")) | Out-Null
            }
        }
    }

    if ($null -ne $current -and $current.Items.Count -gt 0) {
        $sections += $current
    }

    return $sections
}

function Chunk-Items($items, [int] $size) {
    $chunks = @()
    for ($i = 0; $i -lt $items.Count; $i += $size) {
        $end = [Math]::Min($i + $size - 1, $items.Count - 1)
        $chunks += ,@($items[$i..$end])
    }
    return $chunks
}

function Build-SlideXml($slide, [int] $number, [bool] $hasLogo) {
    $script:shapeCounter = 1
    $sp = @()
    $sp += Rect 0 0 12192000 6858000 "0B1220"
    $sp += Rect 0 0 12192000 480000 "111827"
    $sp += TextBox 520000 130000 5000000 220000 "Airmius Test-Checkliste" 13 "BFDBFE" $true
    $sp += TextBox 10900000 130000 760000 220000 ("{0:D2}" -f $number) 12 "38BDF8" $true "r"

    if ($hasLogo) {
        $sp += Picture "rIdLogo" 9500000 61000 1350000 360000
    }

    $sp += TextBox 520000 650000 10800000 470000 $slide.Title 24 "FFFFFF" $true
    $sp += TextBox 540000 1125000 10500000 310000 $slide.Subtitle 11 "BFDBFE"

    if ($slide.Type -eq "title") {
        $sp += TextBox 900000 2100000 9300000 900000 "Use Cases als prüfbare PowerPoint-Checkliste" 34 "FFFFFF" $true
        $sp += TextBox 940000 3260000 8200000 820000 "Jede Funktion aus docs/USER_CASES.md ist als Testpunkt mit Beschreibung, erwartetem Ergebnis und Statusfeld aufbereitet." 18 "DDEBFF"
        $sp += TextBox 940000 4550000 4300000 360000 ("Generiert: " + (Get-Date -Format "dd.MM.yyyy HH:mm")) 13 "38BDF8" $true
    } elseif ($slide.Type -eq "summary") {
        $y = 1900000
        foreach ($line in $slide.Lines) {
            $sp += TextBox 900000 $y 9800000 360000 ("• " + $line) 18 "E5E7EB"
            $y += 460000
        }
    } else {
        $y = 1660000
        $sp += Rect 520000 $y 11150000 360000 "172033"
        $sp += TextBox 670000 ($y + 45000) 5400000 180000 "Funktion / Testpunkt" 9 "93C5FD" $true
        $sp += TextBox 6300000 ($y + 45000) 2400000 180000 "Erwartetes Ergebnis" 9 "93C5FD" $true
        $sp += TextBox 8900000 ($y + 45000) 2450000 180000 "Status / Notiz" 9 "93C5FD" $true
        $y += 400000

        foreach ($item in $slide.Items) {
            $expectation = MakeExpectation $item
            $sp += Rect 520000 $y 11150000 520000 "0F172A"
            $sp += TextBox 650000 ($y + 50000) 5200000 180000 ("☐ " + $item) 10 "FFFFFF" $true
            $sp += TextBox 900000 ($y + 265000) 4700000 155000 ("Test: Funktion mit gültigen und ungültigen Eingaben ausführen.") 8 "CBD5E1"
            $sp += TextBox 6300000 ($y + 62000) 2400000 330000 $expectation 8 "E5E7EB"
            $sp += TextBox 8900000 ($y + 76000) 2300000 260000 "☐ offen   ☐ bestanden   ☐ Fehler" 8 "D1FAE5"
            $sp += Line 600000 ($y + 505000) 10950000 12000 "263244"
            $y += 560000
        }
    }

    $shapeTree = $sp -join ""
    return @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>$shapeTree</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>
"@
}

function Write-StaticParts([string] $dir, [int] $slideCount) {
    $overrides = ""
    $slideIds = ""
    $presentationRels = '<Relationship Id="rIdMaster" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>'

    for ($i = 1; $i -le $slideCount; $i++) {
        $overrides += "<Override PartName=""/ppt/slides/slide$i.xml"" ContentType=""application/vnd.openxmlformats-officedocument.presentationml.slide+xml""/>"
        $slideIds += "<p:sldId id=""$($i + 255)"" r:id=""rId$i""/>"
        $presentationRels += "<Relationship Id=""rId$i"" Type=""http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide"" Target=""slides/slide$i.xml""/>"
    }

    Set-Content -LiteralPath (Join-Path $dir "[Content_Types].xml") -Encoding UTF8 -Value "<?xml version=""1.0"" encoding=""UTF-8"" standalone=""yes""?><Types xmlns=""http://schemas.openxmlformats.org/package/2006/content-types""><Default Extension=""rels"" ContentType=""application/vnd.openxmlformats-package.relationships+xml""/><Default Extension=""xml"" ContentType=""application/xml""/><Default Extension=""png"" ContentType=""image/png""/>$overrides<Override PartName=""/ppt/presentation.xml"" ContentType=""application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml""/><Override PartName=""/ppt/slideMasters/slideMaster1.xml"" ContentType=""application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml""/><Override PartName=""/ppt/slideLayouts/slideLayout1.xml"" ContentType=""application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml""/><Override PartName=""/ppt/theme/theme1.xml"" ContentType=""application/vnd.openxmlformats-officedocument.theme+xml""/><Override PartName=""/docProps/core.xml"" ContentType=""application/vnd.openxmlformats-package.core-properties+xml""/><Override PartName=""/docProps/app.xml"" ContentType=""application/vnd.openxmlformats-officedocument.extended-properties+xml""/></Types>"
    Set-Content -LiteralPath (Join-Path $dir "_rels\.rels") -Encoding UTF8 -Value '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>'
    Set-Content -LiteralPath (Join-Path $dir "ppt\presentation.xml") -Encoding UTF8 -Value "<?xml version=""1.0"" encoding=""UTF-8"" standalone=""yes""?><p:presentation xmlns:a=""http://schemas.openxmlformats.org/drawingml/2006/main"" xmlns:r=""http://schemas.openxmlformats.org/officeDocument/2006/relationships"" xmlns:p=""http://schemas.openxmlformats.org/presentationml/2006/main""><p:sldMasterIdLst><p:sldMasterId id=""2147483648"" r:id=""rIdMaster""/></p:sldMasterIdLst><p:sldIdLst>$slideIds</p:sldIdLst><p:sldSz cx=""12192000"" cy=""6858000"" type=""wide""/><p:notesSz cx=""6858000"" cy=""9144000""/></p:presentation>"
    Set-Content -LiteralPath (Join-Path $dir "ppt\_rels\presentation.xml.rels") -Encoding UTF8 -Value "<?xml version=""1.0"" encoding=""UTF-8"" standalone=""yes""?><Relationships xmlns=""http://schemas.openxmlformats.org/package/2006/relationships"">$presentationRels</Relationships>"
    Set-Content -LiteralPath (Join-Path $dir "ppt\slideMasters\slideMaster1.xml") -Encoding UTF8 -Value '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/><p:sldLayoutIdLst><p:sldLayoutId id="1" r:id="rId1"/></p:sldLayoutIdLst></p:sldMaster>'
    Set-Content -LiteralPath (Join-Path $dir "ppt\slideMasters\_rels\slideMaster1.xml.rels") -Encoding UTF8 -Value '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/></Relationships>'
    Set-Content -LiteralPath (Join-Path $dir "ppt\slideLayouts\slideLayout1.xml") -Encoding UTF8 -Value '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1"><p:cSld name="Blank"><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld></p:sldLayout>'
    Set-Content -LiteralPath (Join-Path $dir "ppt\slideLayouts\_rels\slideLayout1.xml.rels") -Encoding UTF8 -Value '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/></Relationships>'
    Set-Content -LiteralPath (Join-Path $dir "ppt\theme\theme1.xml") -Encoding UTF8 -Value '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Airmius"><a:themeElements><a:clrScheme name="Airmius"><a:dk1><a:srgbClr val="0B1220"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="111827"/></a:dk2><a:lt2><a:srgbClr val="E5E7EB"/></a:lt2><a:accent1><a:srgbClr val="38BDF8"/></a:accent1><a:accent2><a:srgbClr val="22C55E"/></a:accent2><a:accent3><a:srgbClr val="F97316"/></a:accent3><a:accent4><a:srgbClr val="A78BFA"/></a:accent4><a:accent5><a:srgbClr val="FACC15"/></a:accent5><a:accent6><a:srgbClr val="EF4444"/></a:accent6><a:hlink><a:srgbClr val="38BDF8"/></a:hlink><a:folHlink><a:srgbClr val="A78BFA"/></a:folHlink></a:clrScheme><a:fontScheme name="Aptos"><a:majorFont><a:latin typeface="Aptos Display"/></a:majorFont><a:minorFont><a:latin typeface="Aptos"/></a:minorFont></a:fontScheme><a:fmtScheme name="Airmius"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme></a:themeElements></a:theme>'
    Set-Content -LiteralPath (Join-Path $dir "docProps\core.xml") -Encoding UTF8 -Value ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Airmius Use Case Test Checklist</dc:title><dc:creator>Airmius</dc:creator><cp:lastModifiedBy>Airmius</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' + (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ") + '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' + (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ") + '</dcterms:modified></cp:coreProperties>')
    Set-Content -LiteralPath (Join-Path $dir "docProps\app.xml") -Encoding UTF8 -Value "<?xml version=""1.0"" encoding=""UTF-8"" standalone=""yes""?><Properties xmlns=""http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"" xmlns:vt=""http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes""><Application>Airmius</Application><PresentationFormat>On-screen Show (16:9)</PresentationFormat><Slides>$slideCount</Slides></Properties>"
}

if (Test-Path $workDir) {
    Remove-Item -LiteralPath $workDir -Recurse -Force
}

New-Item -ItemType Directory -Force -Path $workDir, "$workDir\_rels", "$workDir\docProps", "$workDir\ppt\_rels", "$workDir\ppt\slides\_rels", "$workDir\ppt\slideMasters\_rels", "$workDir\ppt\slideLayouts\_rels", "$workDir\ppt\theme", "$workDir\ppt\media" | Out-Null

$sections = Parse-UseCases $sourcePath
$totalItems = ($sections | ForEach-Object { $_.Items.Count } | Measure-Object -Sum).Sum

$slides = @()
$slides += [PSCustomObject]@{
    Type = "title"
    Title = "Airmius Use Cases"
    Subtitle = "PowerPoint-Testcheckliste mit allen Funktionen aus docs/USER_CASES.md"
}
$slides += [PSCustomObject]@{
    Type = "summary"
    Title = "So wird getestet"
    Subtitle = "Jeder Testpunkt hat eine Funktion, eine kurze Testbeschreibung, ein erwartetes Ergebnis und ein Statusfeld."
    Lines = @(
        "Gesamtumfang: $($sections.Count) Use-Case-Bereiche und $totalItems einzelne Testpunkte.",
        "Statusfelder sind bewusst leer: offen, bestanden oder Fehler können direkt während des Tests markiert werden.",
        "Prüfe positive und negative Eingaben, Berechtigungen, Reload-Verhalten und Benachrichtigungen.",
        "Bei Fehlern Screenshot, Nutzerrolle, Route und konkrete Eingabe in der Notizspalte ergänzen."
    )
}

foreach ($section in $sections) {
    $chunks = Chunk-Items $section.Items 7
    for ($i = 0; $i -lt $chunks.Count; $i++) {
        $suffix = if ($chunks.Count -gt 1) { " Teil $($i + 1)/$($chunks.Count)" } else { "" }
        $slides += [PSCustomObject]@{
            Type = "checklist"
            Title = $section.Title + $suffix
            Subtitle = "Testbereich aus USER_CASES.md: jede Zeile als manuell prüfbarer Testfall."
            Items = $chunks[$i]
        }
    }
}

$hasLogo = Test-Path $logoPath
if ($hasLogo) {
    Copy-Item -LiteralPath $logoPath -Destination "$workDir\ppt\media\logo.png" -Force
}

Write-StaticParts $workDir $slides.Count

for ($i = 0; $i -lt $slides.Count; $i++) {
    $num = $i + 1
    Set-Content -LiteralPath "$workDir\ppt\slides\slide$num.xml" -Encoding UTF8 -Value (Build-SlideXml $slides[$i] $num $hasLogo)
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
    if ($hasLogo) {
        $rels += '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/logo.png"/>'
    }
    $rels += '</Relationships>'
    Set-Content -LiteralPath "$workDir\ppt\slides\_rels\slide$num.xml.rels" -Encoding UTF8 -Value $rels
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

if (Test-Path $destinationPath) {
    Remove-Item -LiteralPath $destinationPath -Force
}

$fs = [System.IO.File]::Open($destinationPath, [System.IO.FileMode]::Create, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
try {
    $zip = [System.IO.Compression.ZipArchive]::new($fs, [System.IO.Compression.ZipArchiveMode]::Create, $true)
    try {
        Get-ChildItem -LiteralPath $workDir -Recurse -File | ForEach-Object {
            $relative = $_.FullName.Substring($workDir.Length + 1).Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $relative, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    } finally {
        $zip.Dispose()
    }
} finally {
    $fs.Dispose()
}

Write-Host $destinationPath
Write-Host "Slides: $($slides.Count)"
Write-Host "Testpunkte: $totalItems"

