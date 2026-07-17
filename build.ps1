# Build script for CuredHosting Plugin Suite
# Produces two zips: full and freemium with version in filename

$root = Get-Location
$versionLine = Select-String -Path 'curedhosting-plugin-suite.php' -Pattern "define\('CHPS_VERSION', '([0-9]+\.[0-9]+\.[0-9]+)'\)" | Select-Object -First 1
if ($versionLine) {
    $version = $versionLine.Matches[0].Groups[1].Value
} else {
    $version = '1.0.2'
}

$fullZip = "curedhosting-plugin-suite-full-$version.zip"
$freeZip = "curedhosting-plugin-suite-freemium-$version.zip"

Write-Output "Building zips for version $version"

function Convert-MarkdownToHtml {
    param([string[]]$Lines)

    $html = @()
    $inList = $false

    foreach ($line in $Lines) {
        if ($line -match '^\s*$') {
            if ($inList) { $html += '</ul>'; $inList = $false }
            continue
        }

        if ($line -match '^## \[(.+?)\] - (.+)$') {
            if ($inList) { $html += '</ul>'; $inList = $false }
            $html += "<h3>Version $($matches[1]) - $($matches[2])</h3>"
            continue
        }

        if ($line -match '^# (.+)$') {
            if ($inList) { $html += '</ul>'; $inList = $false }
            $html += "<h1>$($matches[1])</h1>"
            continue
        }

        if ($line -match '^- (.+)$') {
            if (-not $inList) { $html += '<ul>'; $inList = $true }
            $html += "<li>$($matches[1])</li>"
            continue
        }

        if ($inList) { $html += '</ul>'; $inList = $false }
        $html += "<p>$line</p>"
    }

    if ($inList) { $html += '</ul>' }
    return $html -join "`n"
}

function Get-ReleaseNotesHtml {
    param([string]$ChangelogText, [string]$Version)

    $lines = $ChangelogText -split "\r?\n"
    $section = @()
    $record = $false

    foreach ($line in $lines) {
        if ($line -match '^## \[' + [regex]::Escape($Version) + '\] -') {
            $record = $true
            $section += $line
            continue
        }
        if ($record) {
            if ($line -match '^## \[') { break }
            $section += $line
        }
    }

    if ($section.Count -eq 0) {
        return '<p>No release notes found for version ' + $Version + '.</p>'
    }

    return Convert-MarkdownToHtml $section
}

function Get-ChangelogHtml {
    param([string]$ChangelogText)
    $lines = $ChangelogText -split "\r?\n"
    return Convert-MarkdownToHtml $lines
}

# Create full zip
if (Test-Path $fullZip) { Remove-Item $fullZip -Force }

# Generate release notes HTML and sales page for this version
$releaseNotesHtml = Join-Path $root "release-notes-$version.html"
$salesHtml = Join-Path $root "sales.html"
$changelog = Get-Content -Path "CHANGELOG.md" -Raw
$releaseNotesHtmlContent = Get-ReleaseNotesHtml -ChangelogText $changelog -Version $version
$changelogHtml = Get-ChangelogHtml -ChangelogText $changelog

$template = Get-Content -Path 'release-notes-template.html' -Raw
$template = $template -replace '{{VERSION}}', $version
$template = $template -replace '{{DATE}}', (Get-Date -Format 'yyyy-MM-dd')
$template = $template -replace '{{RELEASE_NOTES}}', $releaseNotesHtmlContent
$template = $template -replace '{{CHANGELOG_HTML}}', $changelogHtml

Set-Content -Path $releaseNotesHtml -Value $template -Encoding UTF8
Set-Content -Path $salesHtml -Value $template -Encoding UTF8

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

# Freemium includes core free modules such as wp-server-guardian and wp-speed-autopilot at top-level.
# Paid modules (for example `key-maker` and `modules/stripe-payment-module`) remain excluded from the freemium package.

if (Test-Path $freeZip) { Remove-Item $freeZip -Force }
Compress-Archive -Path (Join-Path $temp '*') -DestinationPath $freeZip -Force
Write-Output "Created $freeZip"

# Cleanup
Remove-Item -LiteralPath $temp -Recurse -Force

Write-Output "Build complete"
