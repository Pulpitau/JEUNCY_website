# Lance la demo Jeuncy sur la base de TEST : API locale, site local, Expo.
# Clic droit sur ce fichier > « Exécuter avec PowerShell ». Voir parcours-demo.md.
# Relancer remet tout le jeu de demo a zero.

$racine = (Resolve-Path "$PSScriptRoot\..\..").Path

# Un lancement precedent occupe encore ses ports ? On l'arrete : sinon l'API
# demarrerait sur 3001 et l'app, qui vise 3000, ne la trouverait plus.
Get-NetTCPConnection -State Listen -LocalPort 3000, 5173, 8081 -ErrorAction SilentlyContinue |
    ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }

# IP du PC sur le reseau partage avec l'iPhone : celle qui a une passerelle.
$ip = (Get-NetIPConfiguration |
    Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } |
    Select-Object -First 1).IPv4Address.IPAddress
if (-not $ip) {
    Write-Host "Aucun reseau. Connecte le PC au partage de connexion de l'iPhone, puis relance." -ForegroundColor Red
    Read-Host 'Entree pour fermer'
    exit 1
}
Write-Host "IP du PC : $ip" -ForegroundColor Cyan

# L'app vise l'API de ce PC. Sans ce fichier, elle viserait la PRODUCTION.
Set-Content -Encoding ascii -Path "$racine\apps\mobile\.env" -Value @"
# Base de TEST (jeu de demo MatchDemoSeeder). Supprimer ce fichier et
# relancer Expo pour revenir a la production.
EXPO_PUBLIC_API_URL=http://${ip}:3000/api
"@

# Les photos du jeu de demo portent l'adresse du serveur : meme IP.
$env:APP_URL = "http://${ip}:3000"
Set-Location "$racine\apps\api"
php artisan db:seed --class=MatchDemoSeeder
if ($LASTEXITCODE -ne 0) {
    Write-Host 'Le jeu de demo n a pas pu etre remis a zero (internet ?).' -ForegroundColor Red
    Read-Host 'Entree pour fermer'
    exit 1
}

Start-Process powershell -ArgumentList '-NoExit', '-Command', "`$host.UI.RawUI.WindowTitle='API Jeuncy'; `$env:APP_URL='http://${ip}:3000'; Set-Location '$racine\apps\api'; php artisan serve --host=0.0.0.0 --port=3000"
Start-Process powershell -ArgumentList '-NoExit', '-Command', "`$host.UI.RawUI.WindowTitle='Site Jeuncy'; Set-Location '$racine\apps\web'; npx vite --port 5173"
Start-Process powershell -ArgumentList '-NoExit', '-Command', "`$host.UI.RawUI.WindowTitle='Expo Jeuncy'; Set-Location '$racine\apps\mobile'; npx expo start --clear"

Write-Host ''
Write-Host 'Trois fenetres s ouvrent : API, Site, Expo.' -ForegroundColor Green
Write-Host '1. iPhone : scanne le QR code de la fenetre Expo avec l appareil photo.'
Write-Host '2. PC : ouvre http://localhost:5173 (et https://jeuncy.com dans un autre onglet).'
Read-Host 'Entree pour fermer cette fenetre'
