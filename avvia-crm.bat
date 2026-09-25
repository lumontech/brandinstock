@echo off
setlocal
cd /d "%~dp0"
title Brandinstock CRM - avvio

echo.
echo  ===============================================
echo    Brandinstock CRM - avvio in locale
echo  ===============================================
echo.

where php >nul 2>nul
if errorlevel 1 goto :no_php
where composer >nul 2>nul
if errorlevel 1 goto :no_php
where npm >nul 2>nul
if errorlevel 1 goto :no_node

rem --- Backend: prima installazione ---
cd /d "%~dp0backend"
if not exist vendor\autoload.php (
    echo [1/4] Installo le dipendenze del backend, attendere qualche minuto...
    call composer install --no-interaction
    if errorlevel 1 goto :error
)
if not exist .env (
    echo [2/4] Creo la configurazione locale...
    copy .env.example .env >nul
    call php artisan key:generate --force
    if errorlevel 1 goto :error
)
if not exist database\database.sqlite (
    echo [3/4] Creo il database con i dati di esempio...
    type nul > database\database.sqlite
    call php artisan migrate --seed --force
    if errorlevel 1 goto :error
    call php artisan db:seed --class=DemoSeeder --force
    if errorlevel 1 goto :error
)

rem --- Frontend: prima installazione ---
cd /d "%~dp0frontend"
if not exist node_modules (
    echo [4/4] Installo le dipendenze del frontend, attendere qualche minuto...
    call npm install
    if errorlevel 1 goto :error
)

echo.
echo Avvio dei server: si apriranno due finestre nere. NON chiuderle finche usi il CRM.
start "CRM - backend (non chiudere)" /d "%~dp0backend" cmd /k php artisan serve
start "CRM - frontend (non chiudere)" /d "%~dp0frontend" cmd /k npm run dev

echo Attendo che il CRM sia pronto...
timeout /t 10 /nobreak >nul
start "" http://localhost:5173

echo.
echo  Il CRM e' aperto nel browser: http://localhost:5173
echo.
echo  Utenti demo, password per tutti: password
echo    giulia@brandinstock.test   - venditore
echo    marco@brandinstock.test    - venditore
echo    admin@brandinstock.test    - amministratore, al primo accesso chiede la 2FA
echo.
echo  Per spegnere il CRM chiudi le due finestre nere dei server.
echo.
pause
exit /b 0

:no_php
echo PHP o Composer non trovati.
echo Installa Laravel Herd da https://herd.laravel.com/windows
echo poi chiudi questa finestra e fai di nuovo doppio clic su avvia-crm.bat
echo.
pause
exit /b 1

:no_node
echo Node.js non trovato.
echo Installa la versione LTS da https://nodejs.org
echo poi chiudi questa finestra e fai di nuovo doppio clic su avvia-crm.bat
echo.
pause
exit /b 1

:error
echo.
echo Si e' verificato un errore durante l'installazione.
echo Copia il testo qui sopra e invialo a chi ti assiste.
echo.
pause
exit /b 1
