param(
    [string]$OutputDirectory = "release"
)

$ErrorActionPreference = "Stop"

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot ".."))
$releaseRoot = [System.IO.Path]::GetFullPath((Join-Path $projectRoot $OutputDirectory))

if (-not $releaseRoot.StartsWith($projectRoot + [System.IO.Path]::DirectorySeparatorChar)) {
    throw "Output directory must be inside the project workspace: $releaseRoot"
}

$archivePath = Join-Path $releaseRoot "halopress-live2d.zip"
$archiveSources = @(
    "assets",
    "includes",
    "live2d-widget",
    "halopress-live2d.php",
    "uninstall.php",
    "LICENSE",
    "README.md",
    "readme.txt",
    "THIRD-PARTY-NOTICES.md"
)
$requiredFiles = @(
    "halopress-live2d.php",
    "uninstall.php",
    "LICENSE",
    "README.md",
    "readme.txt",
    "THIRD-PARTY-NOTICES.md",
    "assets/admin.css",
    "assets/images/live2d-logo.png",
    "includes/class-halopress-live2d.php",
    "includes/class-halopress-live2d-admin.php",
    "live2d-widget/widget.css",
    "live2d-widget/widget.js"
)

foreach ($source in $archiveSources) {
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot $source))) {
        throw "Release source is missing: $source"
    }
}

$tarCommand = Get-Command tar.exe -ErrorAction SilentlyContinue
if (-not $tarCommand) {
    throw "tar.exe is required to build a WordPress-compatible ZIP on Windows."
}

New-Item -ItemType Directory -Path $releaseRoot -Force | Out-Null
if (Test-Path -LiteralPath $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

Push-Location -Path $projectRoot
try {
    & $tarCommand.Source -a -cf $archivePath @archiveSources
    if (0 -ne $LASTEXITCODE) {
        throw "tar.exe failed with exit code $LASTEXITCODE."
    }
}
finally {
    Pop-Location
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    $entries = @($archive.Entries | ForEach-Object { $_.FullName })
}
finally {
    $archive.Dispose()
}

if ($entries | Where-Object { $_.Contains('\') }) {
    throw "ZIP entries must not contain Windows backslashes."
}
if ($entries | Where-Object { $_ -eq '.' -or $_ -eq './' -or $_.StartsWith('./') }) {
    throw "ZIP entries must not contain an explicit dot directory."
}
if ($entries | Where-Object { $_.StartsWith('/') -or $_ -match '^[A-Za-z]:' -or $_.Split('/').Contains('..') }) {
    throw "ZIP contains an unsafe path."
}
if ($entries | Where-Object { $_.StartsWith('halopress-live2d/') }) {
    throw "ZIP must not contain an extra halopress-live2d directory layer."
}

foreach ($file in $requiredFiles) {
    if ($entries -notcontains $file) {
        throw "ZIP is missing required file: $file"
    }
}

$verificationDirectory = Join-Path $releaseRoot ("verify-" + [System.Guid]::NewGuid().ToString("N"))
if (-not $verificationDirectory.StartsWith($releaseRoot + [System.IO.Path]::DirectorySeparatorChar)) {
    throw "Unsafe verification directory: $verificationDirectory"
}

New-Item -ItemType Directory -Path $verificationDirectory | Out-Null
try {
    Expand-Archive -LiteralPath $archivePath -DestinationPath $verificationDirectory
    foreach ($file in $requiredFiles) {
        $extractedPath = Join-Path $verificationDirectory $file.Replace('/', [System.IO.Path]::DirectorySeparatorChar)
        if (-not (Test-Path -LiteralPath $extractedPath -PathType Leaf)) {
            throw "Extracted ZIP is missing required file: $file"
        }
    }
}
finally {
    Remove-Item -LiteralPath $verificationDirectory -Recurse -Force
}

Write-Output $archivePath
