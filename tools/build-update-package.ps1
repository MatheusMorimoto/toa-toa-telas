param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$')]
    [string]$Version,

    [string]$MinimumAppVersion = '1.0.0'
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$releaseDirectory = Join-Path $projectRoot 'releases'
$packageName = "toa-toa-$Version.zip"
$packagePath = Join-Path $releaseDirectory $packageName
$stagingRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("toa-toa-release-" + [guid]::NewGuid().ToString('N'))

try {
    New-Item -ItemType Directory -Force -Path $releaseDirectory | Out-Null
    New-Item -ItemType Directory -Force -Path $stagingRoot | Out-Null

    $trackedFiles = & git -C $projectRoot ls-files --cached --others --exclude-standard
    if ($LASTEXITCODE -ne 0) {
        throw 'Não foi possível consultar os arquivos rastreados pelo Git.'
    }

    $blockedPrefixes = @(
        '.git/',
        '.github/',
        'storage/',
        'releases/',
        'imagens/',
        'banco de dados/',
        '.venv/',
        'venv/'
    )
    $blockedNames = @('.env', 'settings.json', 'config.py')

    foreach ($relativePath in $trackedFiles) {
        $normalized = $relativePath.Replace('\', '/')
        $isBlockedPrefix = $false
        foreach ($prefix in $blockedPrefixes) {
            if ($normalized.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) {
                $isBlockedPrefix = $true
                break
            }
        }
        if ($isBlockedPrefix -or $blockedNames -contains [IO.Path]::GetFileName($normalized)) {
            continue
        }

        $sourcePath = Join-Path $projectRoot $relativePath
        if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
            continue
        }
        $destinationPath = Join-Path $stagingRoot $relativePath
        New-Item -ItemType Directory -Force -Path (Split-Path $destinationPath -Parent) | Out-Null
        Copy-Item -LiteralPath $sourcePath -Destination $destinationPath
    }

    if (Test-Path -LiteralPath $packagePath) {
        Remove-Item -LiteralPath $packagePath
    }
    Compress-Archive -Path (Join-Path $stagingRoot '*') -DestinationPath $packagePath -CompressionLevel Optimal
    $sha256 = (Get-FileHash -LiteralPath $packagePath -Algorithm SHA256).Hash.ToLowerInvariant()
    $publishedAt = [DateTimeOffset]::UtcNow.ToString('o')
    $downloadUrl = "https://github.com/MatheusMorimoto/toa-toa-telas/raw/refs/heads/main/releases/$packageName"

    $manifest = [ordered]@{
        version = $Version
        download_url = $downloadUrl
        sha256 = $sha256
        minimum_app_version = $MinimumAppVersion
        published_at = $publishedAt
    }
    $manifestJson = ($manifest | ConvertTo-Json) + [Environment]::NewLine
    $utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
    [IO.File]::WriteAllText((Join-Path $projectRoot 'version.json'), $manifestJson, $utf8WithoutBom)

    Write-Output "Pacote criado: $packagePath"
    Write-Output "SHA-256: $sha256"
    Write-Output 'O version.json foi atualizado. Revise, faça commit do manifesto e force a inclusão do ZIP com:'
    Write-Output "git add version.json; git add -f releases/$packageName"
} finally {
    if (Test-Path -LiteralPath $stagingRoot) {
        $resolvedStaging = (Resolve-Path -LiteralPath $stagingRoot).Path
        $resolvedTemp = (Resolve-Path -LiteralPath ([System.IO.Path]::GetTempPath())).Path
        if ($resolvedStaging.StartsWith($resolvedTemp, [StringComparison]::OrdinalIgnoreCase)) {
            Remove-Item -LiteralPath $resolvedStaging -Recurse -Force
        }
    }
}
