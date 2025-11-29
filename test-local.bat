@echo off
REM ============================================
REM Local Testing Script
REM Test the application before deploying
REM ============================================

echo.
echo ============================================
echo Testing Application Locally
echo ============================================
echo.

REM Step 1: Check if dependencies are installed
echo [1/3] Checking dependencies...
if not exist "node_modules" (
    echo   Installing dependencies...
    call npm install
    if errorlevel 1 (
        echo   ✗ Failed to install dependencies
        pause
        exit /b 1
    )
)
echo   ✓ Dependencies OK
echo.

REM Step 2: Build the application
echo [2/3] Building application...
call npm run build
if errorlevel 1 (
    echo   ✗ Build failed - Please fix compilation errors first
    pause
    exit /b 1
)
echo   ✓ Build successful
echo.

REM Step 3: Start the development server
echo [3/3] Starting local test server...
echo.
echo ============================================
echo Server will start at: http://localhost:3000
echo ============================================
echo.
echo Test URLs:
echo   - Home:     http://localhost:3000
echo   - Login:    http://localhost:3000/login
echo   - Admin:    http://localhost:3000/admin-dashboard
echo   - Property: http://localhost:3000/property-dashboard
echo.
echo Test Credentials:
echo   Admin:          admin / exseed2025
echo   Property Owner: comodita_owner / change123
echo.
echo Press Ctrl+C to stop the server
echo ============================================
echo.

call npm run dev
