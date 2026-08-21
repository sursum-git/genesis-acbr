@echo off
setlocal

set "BASE_DIR=%~dp0"
set "CLI_EXE=%BASE_DIR%..\dist\acbr-api-cli.exe"
set "SUITE_CONFIG=%BASE_DIR%suites\nfe-cenarios.ini"

if not exist "%CLI_EXE%" (
  echo Nao encontrei o executavel: "%CLI_EXE%"
  echo Gere o executavel antes de rodar este app.
  pause
  exit /b 4
)

"%CLI_EXE%" --suite-config "%SUITE_CONFIG%"
set "EXIT_CODE=%ERRORLEVEL%"

echo.
echo Suite finalizada com codigo %EXIT_CODE%.
echo Respostas em: "%BASE_DIR%saida"
pause
exit /b %EXIT_CODE%
