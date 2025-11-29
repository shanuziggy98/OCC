@echo off
REM ============================================
REM Deployment Script for Property Management System
REM ============================================

echo.
echo ============================================
echo Property Management System - Deployment
echo ============================================
echo.

REM Step 1: Clean old build files
echo [1/5] Cleaning old build files...
if exist ".next" (
    rmdir /s /q ".next"
    echo   - Removed .next folder
)
if exist "out" (
    rmdir /s /q "out"
    echo   - Removed out folder
)
echo   ✓ Clean completed
echo.

REM Step 2: Install dependencies
echo [2/5] Installing/updating dependencies...
call npm install
if errorlevel 1 (
    echo   ✗ Failed to install dependencies
    pause
    exit /b 1
)
echo   ✓ Dependencies installed
echo.

REM Step 3: Build the application
echo [3/5] Building Next.js application...
call npm run build
if errorlevel 1 (
    echo   ✗ Build failed
    pause
    exit /b 1
)
echo   ✓ Build completed
echo.

REM Step 4: Export static files (if configured)
echo [4/5] Checking for export configuration...
if exist "out" (
    echo   ✓ Static export created in 'out' folder
) else (
    echo   i Using server mode (.next folder)
)
echo.

REM Step 5: Create deployment package
echo [5/5] Creating deployment package...
if not exist "deploy-package" mkdir "deploy-package"

REM Copy PHP files
echo   - Copying PHP backend files...
copy "auth_api.php" "deploy-package\" >nul 2>&1
copy "property_owner_api.php" "deploy-package\" >nul 2>&1
copy "occupancy_metrics_api.php" "deploy-package\" >nul 2>&1
copy "auto_import_cron.php" "deploy-package\" >nul 2>&1
copy "check_all_properties.php" "deploy-package\" >nul 2>&1
copy "auto_update_property_rooms.php" "deploy-package\" >nul 2>&1

REM Copy frontend files
if exist "out" (
    echo   - Copying static export files...
    xcopy "out\*" "deploy-package\" /E /I /Y >nul
) else (
    echo   - Copying Next.js server files...
    xcopy ".next\*" "deploy-package\.next\" /E /I /Y >nul
    copy "package.json" "deploy-package\" >nul
    copy "next.config.ts" "deploy-package\" >nul
    copy "tsconfig.json" "deploy-package\" >nul 2>nul
)

echo   ✓ Deployment package created
echo.

REM Summary
echo ============================================
echo Deployment Package Ready!
echo ============================================
echo.
echo Location: %CD%\deploy-package
echo.
echo Next Steps:
echo 1. Upload contents of 'deploy-package' folder to your server
echo 2. Target location: /WG/analysis/OCC/
echo 3. Ensure PHP files have 644 permissions
echo 4. Test the login page
echo.
echo Files to upload:
echo   - All files in deploy-package folder
echo   - PHP backend files (auth_api.php, etc.)
if exist "out" (
    echo   - Static frontend files from 'out' folder
) else (
    echo   - .next folder and Node.js dependencies
)
echo.
echo ============================================
pause
