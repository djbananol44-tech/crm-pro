<#
.SYNOPSIS
JGGL CRM - Developer Commands (Windows PowerShell)

.DESCRIPTION
Usage:
  .\scripts\dev.ps1 help       - show all commands
  .\scripts\dev.ps1 up         - start containers
  .\scripts\dev.ps1 test       - run tests

.EXAMPLE
.\scripts\dev.ps1 install
#>

param(
    [Parameter(Position=0)]
    [string]$Command = "help",
    
    [Parameter(Position=1)]
    [string]$Arg1 = ""
)

$ErrorActionPreference = "Stop"

# Configuration
$ComposeFile = "docker-compose.yml"

# Execute based on command
switch ($Command.ToLower()) {
    "help" {
        Write-Host ""
        Write-Host "JGGL CRM - Developer Commands" -ForegroundColor Cyan
        Write-Host "=============================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "  up              Start containers" -ForegroundColor Green
        Write-Host "  down            Stop containers" -ForegroundColor Green
        Write-Host "  restart         Restart containers" -ForegroundColor Green
        Write-Host "  logs            Show logs" -ForegroundColor Green
        Write-Host "  ps              Container status" -ForegroundColor Green
        Write-Host ""
        Write-Host "  test            Run tests" -ForegroundColor Green
        Write-Host "  lint            Check code style" -ForegroundColor Green
        Write-Host "  lint-fix        Fix code style" -ForegroundColor Green
        Write-Host "  build           Build frontend" -ForegroundColor Green
        Write-Host "  doctor          System diagnostics" -ForegroundColor Green
        Write-Host ""
        Write-Host "  migrate         Run migrations" -ForegroundColor Green
        Write-Host "  seed            Run seeders" -ForegroundColor Green
        Write-Host "  reset           Quick reset (cache + migrate + seed)" -ForegroundColor Green
        Write-Host "  fresh           Full reset (DROP ALL!)" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "  shell           Open container shell" -ForegroundColor Green
        Write-Host "  tinker          Open Laravel Tinker" -ForegroundColor Green
        Write-Host "  install         First time setup" -ForegroundColor Green
        Write-Host ""
        Write-Host "Quick Start:" -ForegroundColor Yellow
        Write-Host "  .\scripts\dev.ps1 install"
        Write-Host "  .\scripts\dev.ps1 up"
        Write-Host "  .\scripts\dev.ps1 test"
        Write-Host ""
    }
    
    "up" {
        Write-Host "Starting containers..." -ForegroundColor Cyan
        docker compose -f $ComposeFile up -d
        Write-Host "Containers started" -ForegroundColor Green
        Write-Host "App: http://localhost:8080" -ForegroundColor Yellow
        Write-Host "Admin: http://localhost:8080/admin" -ForegroundColor Yellow
    }
    
    "down" {
        Write-Host "Stopping containers..." -ForegroundColor Cyan
        docker compose -f $ComposeFile down
        Write-Host "Containers stopped" -ForegroundColor Green
    }
    
    "restart" {
        Write-Host "Restarting containers..." -ForegroundColor Cyan
        docker compose -f $ComposeFile restart
        Write-Host "Containers restarted" -ForegroundColor Green
    }
    
    "logs" {
        docker compose -f $ComposeFile logs -f
    }
    
    "ps" {
        docker compose -f $ComposeFile ps
    }
    
    "test" {
        Write-Host "Running tests..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan test
        Write-Host "Tests completed" -ForegroundColor Green
    }
    
    "lint" {
        Write-Host "Checking code style..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app ./vendor/bin/pint --test
        Write-Host "Code style OK" -ForegroundColor Green
    }
    
    "lint-fix" {
        Write-Host "Fixing code style..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app ./vendor/bin/pint
        Write-Host "Code style fixed" -ForegroundColor Green
    }
    
    "build" {
        Write-Host "Building frontend..." -ForegroundColor Cyan
        npm run build
        Write-Host "Frontend built" -ForegroundColor Green
    }
    
    "doctor" {
        Write-Host "Running diagnostics..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan jggl:doctor
    }
    
    "migrate" {
        Write-Host "Running migrations..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan migrate
        Write-Host "Migrations completed" -ForegroundColor Green
    }
    
    "seed" {
        Write-Host "Running seeders..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan db:seed
        Write-Host "Seeders completed" -ForegroundColor Green
    }
    
    "reset" {
        Write-Host "Resetting environment..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan config:clear
        docker compose -f $ComposeFile exec -T app php artisan cache:clear
        docker compose -f $ComposeFile exec -T app php artisan view:clear
        docker compose -f $ComposeFile exec -T app php artisan migrate --force
        docker compose -f $ComposeFile exec -T app php artisan db:seed --force
        Write-Host "Environment reset completed" -ForegroundColor Green
    }
    
    "fresh" {
        Write-Host "WARNING: This will DELETE ALL DATA!" -ForegroundColor Yellow
        $confirm = Read-Host "Type 'yes' to confirm"
        if ($confirm -ne "yes") {
            Write-Host "Cancelled."
            exit 0
        }
        Write-Host "Fresh install..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan migrate:fresh --seed --force
        docker compose -f $ComposeFile exec -T app php artisan config:clear
        docker compose -f $ComposeFile exec -T app php artisan cache:clear
        Write-Host "Fresh install completed" -ForegroundColor Green
    }
    
    "shell" {
        docker compose -f $ComposeFile exec app bash
    }
    
    "tinker" {
        docker compose -f $ComposeFile exec app php artisan tinker
    }
    
    "install" {
        Write-Host ""
        Write-Host "JGGL CRM - First Time Setup" -ForegroundColor Cyan
        Write-Host "===========================" -ForegroundColor Cyan
        Write-Host ""
        
        if (-not (Test-Path ".env")) {
            Write-Host "Creating .env file..." -ForegroundColor Cyan
            Copy-Item "docker/env.example" ".env"
            Write-Host "Edit .env and set DB_PASSWORD before continuing!" -ForegroundColor Yellow
            Write-Host "Then run: .\scripts\dev.ps1 install"
            exit 0
        }
        
        Write-Host "Installing npm dependencies..." -ForegroundColor Cyan
        npm ci
        
        Write-Host "Starting containers..." -ForegroundColor Cyan
        docker compose -f $ComposeFile up -d --build
        
        Write-Host "Waiting for database (15s)..." -ForegroundColor Cyan
        Start-Sleep -Seconds 15
        
        Write-Host "Running migrations..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan migrate --force
        
        Write-Host "Running seeders..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan db:seed --force
        
        Write-Host "Building frontend..." -ForegroundColor Cyan
        npm run build
        
        Write-Host ""
        Write-Host "Installation Complete!" -ForegroundColor Green
        Write-Host ""
        Write-Host "App:   http://localhost:8080" -ForegroundColor Yellow
        Write-Host "Admin: http://localhost:8080/admin" -ForegroundColor Yellow
        Write-Host "Login: admin@crm.test / admin123" -ForegroundColor Yellow
        Write-Host ""
    }
    
    "cache-clear" {
        Write-Host "Clearing caches..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan config:clear
        docker compose -f $ComposeFile exec -T app php artisan cache:clear
        docker compose -f $ComposeFile exec -T app php artisan view:clear
        docker compose -f $ComposeFile exec -T app php artisan route:clear
        Write-Host "Caches cleared" -ForegroundColor Green
    }
    
    "check" {
        Write-Host "Checking code style..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app ./vendor/bin/pint --test
        Write-Host "Running tests..." -ForegroundColor Cyan
        docker compose -f $ComposeFile exec -T app php artisan test
        Write-Host "All checks passed" -ForegroundColor Green
    }
    
    default {
        Write-Host "Unknown command: $Command" -ForegroundColor Red
        Write-Host "Run '.\scripts\dev.ps1 help' for available commands."
        exit 1
    }
}
