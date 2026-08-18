param(
    [string]$OutputDirectory = "release"
)

$ErrorActionPreference = "Stop"

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot ".."))
$releaseRoot = [System.IO.Path]::GetFullPath((Join-Path $projectRoot $OutputDirectory))

if (-not $releaseRoot.StartsWith($projectRoot + [System.IO.Path]::DirectorySeparatorChar)) {
    throw "Output directory must be inside the project workspace: $releaseRoot"
}

$stagingDirectory = Join-Path $releaseRoot "halopress-live2d"
$archivePath = Join-Path $releaseRoot "halopress-live2d.zip"

if (Test-Path -LiteralPath $stagingDirectory) {
    Remove-Item -LiteralPath $stagingDirectory -Recurse -Force
}
if (Test-Path -LiteralPath $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

New-Item -ItemType Directory -Path $stagingDirectory -Force | Out-Null

$directories = @("assets", "includes", "live2d-widget")
$files = @(
    "halopress-live2d.php",
    "uninstall.php",
    "LICENSE",
    "README.md",
    "readme.txt",
    "THIRD-PARTY-NOTICES.md"
)

foreach ($directory in $directories) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination $stagingDirectory -Recurse
}
foreach ($file in $files) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $file) -Destination $stagingDirectory
}

Compress-Archive -LiteralPath $stagingDirectory -DestinationPath $archivePath -CompressionLevel Optimal
Write-Output $archivePath

