# Start Resume AI microservice (Windows)
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

if (-not (Test-Path "venv\Scripts\python.exe")) {
    Write-Host "Creating virtual environment..."
    python -m venv venv
}

Write-Host "Installing dependencies (first run may take a few minutes)..."
.\venv\Scripts\pip.exe install -q -r requirements.txt

$env:PORT = "5001"
Write-Host "Starting AI service on http://127.0.0.1:5001"
.\venv\Scripts\python.exe app.py
