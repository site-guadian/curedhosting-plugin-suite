# Build script for CuredHosting Plugin Suite
# Produces two zips: full and freemium with version in filename

$root = Get-Location
$versionLine = Select-String -Path 'curedhosting-plugin-suite.php' -Pattern "define\('CHPS_VERSION', '([0-9]+\.[0-9]+\.[0-9]+)'\)" | Select-Object -First 1
if ($versionLine) {
    $version = $versionLine.Matches[0].Groups[1].Value
} else {
    $version = '1.0.1'
}

$fullZip = "curedhosting-plugin-suite-full-$version.zip"
$freeZip = "curedhosting-plugin-suite-freemium-$version.zip"

Write-Output "Building zips for version $version"

# Create full zip
if (Test-Path $fullZip) { Remove-Item $fullZip -Force }
Compress-Archive -Path * -DestinationPath $fullZip -Force
Write-Output "Created $fullZip"

# Create freemium zip by copying allowed files to a temp dir
$temp = Join-Path $env:TEMP ("chps_build_freemium_" + [guid]::NewGuid().Guid)
New-Item -ItemType Directory -Path $temp | Out-Null

# Excludes for freemium package (these are NOT distributed directly at top-level)
$excludes = @('modules\stripe-payment-module','key-maker')

Get-ChildItem -Path . -Force | Where-Object {
    $name = $_.FullName.Substring($root.Path.Length + 1)

    if ($name -match '(^\.git$|^curedhosting-plugin-suite-.*\.zip$)') { return $false }

    foreach ($ex in $excludes) {
        if ($name -like "$ex*" -or $_.FullName -like "*$ex*") { return $false }
    }
    return $true
} | ForEach-Object {
    $dest = Join-Path $temp $_.Name
    if ($_.PSIsContainer) { Copy-Item -Path $_.FullName -Destination $dest -Recurse -Force }
    else { Copy-Item -Path $_.FullName -Destination $dest -Force }
}

# Place premium modules inside a paid-modules folder within the freemium package
$paidDir = Join-Path $temp 'paid-modules'
New-Item -ItemType Directory -Path $paidDir | Out-Null

$paidModules = @('modules\wp-server-guardian','modules\wp-speed-autopilot')
foreach ($pm in $paidModules) {
    if (Test-Path $pm) {
        $name = Split-Path $pm -Leaf
        $dest = Join-Path $paidDir $name
        Copy-Item -Path $pm -Destination $dest -Recurse -Force
    }
}

if (Test-Path $freeZip) { Remove-Item $freeZip -Force }
Compress-Archive -Path (Join-Path $temp '*') -DestinationPath $freeZip -Force
Write-Output "Created $freeZip"

# Cleanup
Remove-Item -LiteralPath $temp -Recurse -Force

Write-Output "Build complete"
