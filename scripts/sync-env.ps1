[CmdletBinding()]
param(
    [string]$EnvFile = ''
)

$ErrorActionPreference = 'Stop'

$RepoRoot = Split-Path -Parent $PSScriptRoot
$AppCnf = Join-Path $RepoRoot '.runtime\mysql\app.cnf'
$ExampleEnv = Join-Path $RepoRoot 'backend\.env.example'

if ([string]::IsNullOrWhiteSpace($EnvFile)) {
    $EnvFile = Join-Path $RepoRoot 'backend\.env'
}

function Read-CnfValue {
    param(
        [string]$CnfPath,
        [string]$Key
    )

    $line = Get-Content -LiteralPath $CnfPath | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1

    if ($null -eq $line) {
        throw "Missing '$Key' in $CnfPath"
    }

    return ($line -replace "^$Key=", '')
}

if (-not (Test-Path -LiteralPath $AppCnf)) {
    throw "Application database credentials not found at $AppCnf. Run scripts\dev-db.ps1 init first."
}

if (-not (Test-Path -LiteralPath $EnvFile)) {
    if (-not (Test-Path -LiteralPath $ExampleEnv)) {
        throw "Neither $EnvFile nor $ExampleEnv exists."
    }

    Copy-Item -LiteralPath $ExampleEnv -Destination $EnvFile
}

$values = @{
    DB_CONNECTION = 'mysql'
    DB_HOST       = Read-CnfValue -CnfPath $AppCnf -Key 'host'
    DB_PORT       = Read-CnfValue -CnfPath $AppCnf -Key 'port'
    DB_DATABASE   = 'agent_millow_dev'
    DB_USERNAME   = Read-CnfValue -CnfPath $AppCnf -Key 'user'
    DB_PASSWORD   = Read-CnfValue -CnfPath $AppCnf -Key 'password'
    DB_TIMEZONE   = '+00:00'
}

$content = Get-Content -LiteralPath $EnvFile -Raw

foreach ($key in $values.Keys) {
    $pattern = "(?m)^$key=.*$"

    if ($content -match $pattern) {
        $content = $content -replace $pattern, "$key=$($values[$key])"
    }
    else {
        $content = $content.TrimEnd() + "`n$key=$($values[$key])`n"
    }
}

Set-Content -LiteralPath $EnvFile -Value $content -Encoding ASCII -NoNewline

Write-Host "[ok] Updated database settings in $EnvFile" -ForegroundColor Green
