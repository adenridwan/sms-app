# ===========================================
# SMS Enterprise - Run new migrations in Docker
# ===========================================
# Applies any pending Laravel migrations inside the `php` container.
# Safe to run repeatedly: Laravel only executes migrations that
# haven't been recorded in the `migrations` table yet.
#
# Usage:
#   .\scripts\migrate.ps1              # run pending migrations
#   .\scripts\migrate.ps1 -Seed        # run pending migrations + seed
#   .\scripts\migrate.ps1 -Status      # show migration status only
#   .\scripts\migrate.ps1 -Force       # skip the production confirmation prompt

param(
    [switch]$Seed,
    [switch]$Status,
    [switch]$Force
)

$ErrorActionPreference = "Stop"

$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot

function Invoke-Compose {
    param([string[]]$Args)
    docker-compose @Args
    if ($LASTEXITCODE -ne 0) {
        throw "docker-compose $($Args -join ' ') failed with exit code $LASTEXITCODE"
    }
}

Write-Host "==> Checking that the php container is running..."
$running = docker-compose ps --status running php 2>$null
if (-not ($running -match "php")) {
    Write-Host "==> php container is not up. Starting containers (docker-compose up -d)..."
    Invoke-Compose @("up", "-d")
}

Write-Host "==> Waiting for PostgreSQL to accept connections..."
$maxTries = 30
$tries = 0
$dbUser = if ($env:DB_USERNAME) { $env:DB_USERNAME } else { "sms_user" }
$dbName = if ($env:DB_DATABASE) { $env:DB_DATABASE } else { "sms_db" }

while ($true) {
    docker-compose exec -T postgres pg_isready -U $dbUser -d $dbName *> $null
    if ($LASTEXITCODE -eq 0) { break }

    $tries++
    if ($tries -ge $maxTries) {
        Write-Error "Database did not become ready after $maxTries attempts."
        exit 1
    }
    Write-Host "    ...database not ready yet ($tries/$maxTries), retrying in 2s"
    Start-Sleep -Seconds 2
}
Write-Host "==> Database is ready."

if ($Status) {
    Write-Host "==> Migration status:"
    Invoke-Compose @("exec", "-T", "php", "php", "artisan", "migrate:status")
    exit 0
}

# Guard against accidentally migrating a production-looking env without
# an explicit -Force flag, mirroring Artisan's own production prompt.
$appEnv = (docker-compose exec -T php php artisan env 2>$null) -join "`n"
$forceFlag = @()
if ($appEnv -match "(?i)production") {
    $forceFlag = @("--force")
    if (-not $Force) {
        $reply = Read-Host "APP_ENV looks like production. Continue running migrations? [y/N]"
        if ($reply -notmatch "^(y|yes)$") {
            Write-Host "Aborted."
            exit 1
        }
    }
}

Write-Host "==> Running pending migrations..."
Invoke-Compose (@("exec", "-T", "php", "php", "artisan", "migrate") + $forceFlag)

if ($Seed) {
    Write-Host "==> Running seeders..."
    Invoke-Compose (@("exec", "-T", "php", "php", "artisan", "db:seed") + $forceFlag)
}

Write-Host "==> Current migration status:"
Invoke-Compose @("exec", "-T", "php", "php", "artisan", "migrate:status")

Write-Host "==> Done."
