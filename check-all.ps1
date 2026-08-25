# check-all.ps1
# Lance tous les checks qualite (backend + frontend) depuis la racine du monorepo.
# Usage : powershell -ExecutionPolicy Bypass -File .\check-all.ps1
#   (ou, si l'ExecutionPolicy le permet deja : .\check-all.ps1)

$ErrorActionPreference = "Stop"
$root = $PSScriptRoot

function Invoke-Step {
    param(
        [string]$Name,
        [scriptblock]$Command
    )
    Write-Host ""
    Write-Host "==> $Name" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "[ECHEC] $Name" -ForegroundColor Red
        Set-Location $root
        exit $LASTEXITCODE
    }
    Write-Host "[OK] $Name" -ForegroundColor Green
}

# --- Backend ---
Set-Location "$root\backend"
Invoke-Step "Backend - CS Fixer"  { composer run cs }
Invoke-Step "Backend - PHPStan"   { composer run stan }
Invoke-Step "Backend - PHPUnit"   { composer run test }

# --- Frontend ---
Set-Location "$root\frontend"
Invoke-Step "Frontend - Format (prettier)"   { npm run format }
Invoke-Step "Frontend - Lint (eslint)"       { npm run lint }
Invoke-Step "Frontend - Typecheck (vue-tsc)" { npm run typecheck }
Invoke-Step "Frontend - Tests (vitest)"      { npm run test }

Set-Location $root
Write-Host ""
Write-Host "Tous les checks sont passes." -ForegroundColor Green