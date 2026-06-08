param(
    [string]$HostName = "app.airmius.com"
)

$ErrorActionPreference = "Stop"

$assetLinksUrl = "https://$HostName/.well-known/assetlinks.json"
$appleAssociationUrl = "https://$HostName/.well-known/apple-app-site-association"

Write-Host "Checking Airmius deep-link domain verification files"
Write-Host "Host: $HostName"

Write-Host ""
Write-Host "Android App Links:"
Write-Host $assetLinksUrl
$assetLinks = Invoke-WebRequest -Uri $assetLinksUrl -MaximumRedirection 0
Write-Host "Status: $($assetLinks.StatusCode)"
Write-Host "Content-Type: $($assetLinks.Headers['Content-Type'])"
if ($assetLinks.StatusCode -ne 200) {
    throw "assetlinks.json did not return HTTP 200"
}
if (-not ($assetLinks.Content -match "com.airmius.app")) {
    throw "assetlinks.json does not mention com.airmius.app"
}

Write-Host ""
Write-Host "iOS Universal Links:"
Write-Host $appleAssociationUrl
$appleAssociation = Invoke-WebRequest -Uri $appleAssociationUrl -MaximumRedirection 0
Write-Host "Status: $($appleAssociation.StatusCode)"
Write-Host "Content-Type: $($appleAssociation.Headers['Content-Type'])"
if ($appleAssociation.StatusCode -ne 200) {
    throw "apple-app-site-association did not return HTTP 200"
}
if (-not ($appleAssociation.Content -match "com.airmius.app")) {
    throw "apple-app-site-association does not mention com.airmius.app"
}

Write-Host ""
Write-Host "Deep-link domain verification files look reachable and app-bound."
