# ============================================================================
# FICHEIRO: tools/backup.ps1
# Para que serve: criar um ZIP com o dump da base de dados e a pasta uploads.
# As credenciais são lidas de includes/config.php. O ficheiro temporário com a
# palavra-passe é apagado no fim.
#
# Como usar (a partir da raiz do projeto):
#   powershell -NoProfile -ExecutionPolicy Bypass -File tools\backup.ps1
#   powershell -NoProfile -ExecutionPolicy Bypass -File tools\backup.ps1 -BackupDir D:\Backups\CCV
#   powershell -NoProfile -ExecutionPolicy Bypass -File tools\backup.ps1 -KeepTemp
# ============================================================================

#Requires -Version 5.1

[CmdletBinding()]
param(
    [string]$BackupDir = '',
    [switch]$KeepTemp
)

$ErrorActionPreference = 'Stop'

$raiz = Split-Path -Parent $PSScriptRoot
$configPath = Join-Path $raiz 'includes\config.php'
if (-not (Test-Path -LiteralPath $configPath)) {
    throw "Configuração não encontrada: $configPath"
}

if ([string]::IsNullOrWhiteSpace($BackupDir)) {
    $BackupDir = Join-Path $raiz 'backups'
}
$BackupDir = [System.IO.Path]::GetFullPath($BackupDir)
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null

$config = [System.IO.File]::ReadAllText($configPath, [System.Text.Encoding]::UTF8)

function Get-Define {
    param([string]$Nome)
    $padrao = "define\(\s*'" + [regex]::Escape($Nome) + "'\s*,\s*'([^']*)'\s*\)"
    $m = [regex]::Match($config, $padrao)
    if (-not $m.Success) {
        throw "Constante $Nome não encontrada em includes/config.php"
    }
    return $m.Groups[1].Value
}

$dbHost = Get-Define 'DB_HOST'
$dbPort = Get-Define 'DB_PORT'
$dbName = Get-Define 'DB_NAME'
$dbUser = Get-Define 'DB_USER'
$dbPass = Get-Define 'DB_PASS'

# Localizar mysqldump.exe: primeiro no WampServer, depois no PATH.
$candidatos = @()
$wampMysql = 'C:\wamp64\bin\mysql'
if (Test-Path -LiteralPath $wampMysql) {
    $candidatos += Get-ChildItem -LiteralPath $wampMysql -Directory |
        Sort-Object Name -Descending |
        ForEach-Object { Join-Path $_.FullName 'bin\mysqldump.exe' }
}
$candidatos += 'C:\wamp64\bin\mysql\mysql8.4.7\bin\mysqldump.exe'
$mysqldump = $candidatos | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $mysqldump) {
    $comando = Get-Command 'mysqldump.exe' -ErrorAction SilentlyContinue
    if ($comando) {
        $mysqldump = $comando.Source
    }
}
if (-not $mysqldump) {
    throw 'mysqldump.exe não encontrado. Confirma a instalação do MySQL no WampServer.'
}

$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$zip = Join-Path $BackupDir ("ccv_backup_$stamp.zip")
$sufixo = 1
while (Test-Path -LiteralPath $zip) {
    $zip = Join-Path $BackupDir ("ccv_backup_${stamp}_$sufixo.zip")
    $sufixo++
}

$temp = Join-Path $env:TEMP ('ccv_backup_' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Force -Path $temp | Out-Null

$sqlFile = Join-Path $temp 'database.sql'
$defaultsFile = Join-Path $temp 'mysql-client.cnf'
$stdoutFile = Join-Path $temp 'mysqldump.out'
$stderrFile = Join-Path $temp 'mysqldump.err'

try {
    # Ficheiro de opções temporário: evita colocar a palavra-passe na linha
    # de comandos e funciona mesmo que a palavra-passe esteja vazia.
    $passwordEscapada = $dbPass.Replace('\', '\\').Replace('"', '\"')
    $linhas = @(
        '[client]',
        "user=$dbUser",
        "password=`"$passwordEscapada`"",
        "host=$dbHost",
        "port=$dbPort"
    )
    [System.IO.File]::WriteAllLines($defaultsFile, $linhas, (New-Object System.Text.UTF8Encoding($false)))

    $argumentos = @(
        "--defaults-extra-file=$defaultsFile",
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        '--events',
        '--default-character-set=utf8mb4',
        '--databases',
        $dbName,
        "--result-file=$sqlFile"
    )

    Write-Host "A criar dump da base de dados '$dbName'..."
    $processo = Start-Process -FilePath $mysqldump `
        -ArgumentList $argumentos `
        -NoNewWindow -Wait -PassThru `
        -RedirectStandardOutput $stdoutFile `
        -RedirectStandardError $stderrFile

    if ($processo.ExitCode -ne 0) {
        $erro = ''
        if (Test-Path -LiteralPath $stderrFile) {
            $erro = ([System.IO.File]::ReadAllText($stderrFile)).Trim()
        }
        throw "mysqldump falhou (código $($processo.ExitCode)). $erro"
    }

    if (-not (Test-Path -LiteralPath $sqlFile)) {
        throw 'O dump não foi criado.'
    }
    $tamanhoSql = (Get-Item -LiteralPath $sqlFile).Length
    if ($tamanhoSql -lt 1024) {
        throw "O dump tem apenas $tamanhoSql bytes; verifica a base de dados."
    }

    $uploadsOrigem = Join-Path $raiz 'uploads'
    $uploadsDestino = Join-Path $temp 'uploads'
    if (-not (Test-Path -LiteralPath $uploadsOrigem)) {
        throw "Pasta de uploads não encontrada: $uploadsOrigem"
    }
    Copy-Item -LiteralPath $uploadsOrigem -Destination $uploadsDestino -Recurse -Force

    Write-Host 'A comprimir o dump e os uploads...'
    Compress-Archive -Path @($sqlFile, $uploadsDestino) -DestinationPath $zip -CompressionLevel Optimal

    if (-not (Test-Path -LiteralPath $zip)) {
        throw 'O ficheiro ZIP não foi criado.'
    }
    $info = Get-Item -LiteralPath $zip
    if ($info.Length -lt 1024) {
        throw "O ZIP tem apenas $($info.Length) bytes."
    }

    Write-Host ''
    Write-Host "Backup criado: $($info.FullName)"
    Write-Host ("Tamanho: {0:N0} bytes" -f $info.Length)
    Write-Host "Base de dados: $dbName"
    Write-Host 'Conteúdo: database.sql + uploads/'
    Write-Host 'Guarda o ZIP num local seguro. Ele contém dados sensíveis.'
}
finally {
    if (Test-Path -LiteralPath $temp) {
        if ($KeepTemp) {
            Write-Host "Pasta temporária mantida em: $temp"
        }
        else {
            Remove-Item -LiteralPath $temp -Recurse -Force
        }
    }
}
