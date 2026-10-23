[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('init', 'start', 'stop', 'status')]
    [string]$Command = 'status'
)

$ErrorActionPreference = 'Stop'

$RepoRoot = Split-Path -Parent $PSScriptRoot
$RuntimeDir = Join-Path $RepoRoot '.runtime\mysql'
$DataDir = Join-Path $RuntimeDir 'data'
$LogDir = Join-Path $RuntimeDir 'logs'
$RunDir = Join-Path $RuntimeDir 'run'
$RootCnf = Join-Path $RuntimeDir 'root.cnf'
$AppCnf = Join-Path $RuntimeDir 'app.cnf'
$ErrorLog = Join-Path $LogDir 'error.log'
$PidFile = Join-Path $RunDir 'mysqld.pid'

$MysqlBase = 'C:\Program Files\MySQL\MySQL Server 8.4'
$Mysqld = Join-Path $MysqlBase 'bin\mysqld.exe'
$Mysql = Join-Path $MysqlBase 'bin\mysql.exe'
$MysqlAdmin = Join-Path $MysqlBase 'bin\mysqladmin.exe'
$Port = 3307

function Write-Step {
    param([string]$Message)
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Write-Ok {
    param([string]$Message)
    Write-Host "[ok] $Message" -ForegroundColor Green
}

function Write-Warn {
    param([string]$Message)
    Write-Host "[warn] $Message" -ForegroundColor Yellow
}

function Assert-Binaries {
    foreach ($binary in @($Mysqld, $Mysql, $MysqlAdmin)) {
        if (-not (Test-Path -LiteralPath $binary)) {
            throw "MySQL binary not found: $binary"
        }
    }
}

function New-RandomPassword {
    $bytes = New-Object byte[] 24
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $rng.GetBytes($bytes)
    }
    finally {
        $rng.Dispose()
    }

    $value = [Convert]::ToBase64String($bytes) -replace '[^A-Za-z0-9]', ''

    return $value.Substring(0, 24)
}

function Invoke-NativeQuiet {
    param(
        [string]$FilePath,
        [string[]]$Arguments
    )

    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'SilentlyContinue'

    try {
        & $FilePath @Arguments *> $null
        return $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previous
    }
}

function Test-Server {
    $arguments = @()
    if (Test-Path -LiteralPath $RootCnf) {
        $arguments += "--defaults-extra-file=$RootCnf"
    }
    $arguments += @('--protocol=TCP', '-h', '127.0.0.1', '-P', "$Port", 'ping')

    return (Invoke-NativeQuiet -FilePath $MysqlAdmin -Arguments $arguments) -eq 0
}

function Wait-ForServer {
    param([int]$TimeoutSeconds = 30)

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        if (Test-Server) {
            return
        }
        Start-Sleep -Milliseconds 500
    }

    throw "MySQL did not become ready on port $Port within $TimeoutSeconds seconds. See $ErrorLog."
}

function Invoke-MysqlRootWithoutPassword {
    param([string]$Sql)

    $attempts = @(
        @('--protocol=TCP', '-h', '127.0.0.1', '-P', "$Port", '-u', 'root', '-e', $Sql),
        @('--protocol=TCP', '-h', 'localhost', '-P', "$Port", '-u', 'root', '-e', $Sql)
    )

    foreach ($attempt in $attempts) {
        if ((Invoke-NativeQuiet -FilePath $Mysql -Arguments $attempt) -eq 0) {
            return
        }
    }

    throw 'Could not connect to MySQL as root without a password.'
}

function Invoke-MysqlWithCnf {
    param(
        [string]$Cnf,
        [string]$Sql
    )

    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    try {
        & $Mysql "--defaults-extra-file=$Cnf" --protocol=TCP -h 127.0.0.1 -P "$Port" -e $Sql
        $exitCode = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previous
    }

    if ($exitCode -ne 0) {
        throw "MySQL command failed with exit code $exitCode."
    }
}

function Start-Server {
    if (Test-Server) {
        Write-Ok "MySQL is already running on 127.0.0.1:$Port"
        return
    }

    if (-not (Test-Path -LiteralPath (Join-Path $DataDir 'mysql'))) {
        throw "MySQL data directory is not initialized at $DataDir. Run: scripts\dev-db.ps1 init"
    }

    New-Item -ItemType Directory -Force -Path $LogDir, $RunDir | Out-Null

    $argumentLine = @(
        '--no-defaults',
        "--basedir=`"$MysqlBase`"",
        "--datadir=`"$DataDir`"",
        "--port=$Port",
        '--bind-address=127.0.0.1',
        '--mysqlx=0',
        '--local-infile=0',
        '--character-set-server=utf8mb4',
        '--collation-server=utf8mb4_unicode_ci',
        '--default-time-zone=+00:00',
        "--log-error=`"$ErrorLog`"",
        "--pid-file=`"$PidFile`"",
        '--max-connections=100',
        '--innodb-buffer-pool-size=256M'
    ) -join ' '

    Write-Step "Starting MySQL 8.4 on 127.0.0.1:$Port"
    Start-Process -FilePath $Mysqld -ArgumentList $argumentLine -WindowStyle Hidden | Out-Null
    Wait-ForServer

    Write-Ok "MySQL is running on 127.0.0.1:$Port"
}

function Stop-Server {
    if (-not (Test-Server)) {
        Write-Warn "MySQL is not running on 127.0.0.1:$Port"
        return
    }

    $arguments = @()
    if (Test-Path -LiteralPath $RootCnf) {
        $arguments += "--defaults-extra-file=$RootCnf"
    }
    $arguments += @('--protocol=TCP', '-h', '127.0.0.1', '-P', "$Port", 'shutdown')

    $exitCode = Invoke-NativeQuiet -FilePath $MysqlAdmin -Arguments $arguments

    if ($exitCode -ne 0) {
        throw "mysqladmin shutdown failed with exit code $exitCode."
    }

    $deadline = (Get-Date).AddSeconds(20)

    while ((Get-Date) -lt $deadline) {
        if (-not (Test-Server)) {
            Write-Ok 'MySQL stopped'
            return
        }
        Start-Sleep -Milliseconds 500
    }

    throw 'MySQL did not stop within 20 seconds.'
}

function Show-Status {
    if (-not (Test-Server)) {
        Write-Warn "MySQL is not running on 127.0.0.1:$Port"
        exit 1
    }

    Write-Ok "MySQL is running on 127.0.0.1:$Port"

    if (Test-Path -LiteralPath $AppCnf) {
        Invoke-MysqlWithCnf -Cnf $AppCnf -Sql 'SELECT VERSION() AS version, @@port AS port, @@datadir AS datadir; SHOW DATABASES;'
    }
    else {
        Write-Warn "Application credentials not found at $AppCnf"
    }
}

function Initialize-Database {
    if (Test-Server) {
        throw "A MySQL server is already running on port $Port. Stop it before initializing."
    }

    if ((Test-Path -LiteralPath $DataDir) -and ((Get-ChildItem -LiteralPath $DataDir -Force | Measure-Object).Count -gt 0)) {
        throw "Refusing to initialize: $DataDir already contains files. Existing data will not be overwritten."
    }

    New-Item -ItemType Directory -Force -Path $DataDir, $LogDir, $RunDir | Out-Null

    Write-Step "Initializing MySQL 8.4 data directory at $DataDir"
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    try {
        & $Mysqld --no-defaults --initialize-insecure --basedir="$MysqlBase" --datadir="$DataDir" --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
        $exitCode = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previous
    }

    if ($exitCode -ne 0) {
        throw "mysqld --initialize-insecure failed with exit code $exitCode."
    }

    Start-Server

    $rootPassword = New-RandomPassword
    $rootCnfContent = @"
[client]
host=127.0.0.1
port=$Port
user=root
password=$rootPassword
"@

    Set-Content -LiteralPath $RootCnf -Value $rootCnfContent -Encoding ASCII

    Write-Step 'Securing the root account'
    Invoke-MysqlRootWithoutPassword "ALTER USER 'root'@'localhost' IDENTIFIED BY '$rootPassword';"

    if (-not (Test-Server)) {
        throw 'Root password was set, but authentication with the generated root credentials failed.'
    }

    $appPassword = New-RandomPassword
    $sql = @"
CREATE DATABASE IF NOT EXISTS agent_millow_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS agent_millow_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'agent_millow'@'localhost' IDENTIFIED BY '$appPassword';
CREATE USER IF NOT EXISTS 'agent_millow'@'127.0.0.1' IDENTIFIED BY '$appPassword';
ALTER USER 'agent_millow'@'localhost' IDENTIFIED BY '$appPassword';
ALTER USER 'agent_millow'@'127.0.0.1' IDENTIFIED BY '$appPassword';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER ON agent_millow_dev.* TO 'agent_millow'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER ON agent_millow_dev.* TO 'agent_millow'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER ON agent_millow_test.* TO 'agent_millow'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER ON agent_millow_test.* TO 'agent_millow'@'127.0.0.1';
FLUSH PRIVILEGES;
"@

    Write-Step 'Creating Agent Millow databases and least-privilege application user'
    Invoke-MysqlWithCnf -Cnf $RootCnf -Sql $sql

    $appCnfContent = @"
[client]
host=127.0.0.1
port=$Port
user=agent_millow
password=$appPassword
"@

    Set-Content -LiteralPath $AppCnf -Value $appCnfContent -Encoding ASCII

    Write-Step 'Verifying application credentials'
    Invoke-MysqlWithCnf -Cnf $AppCnf -Sql 'SELECT VERSION() AS version, CURRENT_USER() AS user; SHOW DATABASES;'

    Write-Ok 'Created databases: agent_millow_dev, agent_millow_test'
    Write-Ok "Application credentials written to $AppCnf (gitignored)"
    Write-Ok "Root credentials written to $RootCnf (gitignored)"
}

Assert-Binaries

switch ($Command) {
    'init' {
        Initialize-Database
    }
    'start' {
        Start-Server
    }
    'stop' {
        Stop-Server
    }
    'status' {
        Show-Status
    }
}
