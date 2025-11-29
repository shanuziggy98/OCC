#!/bin/bash

# ============================================
# Deployment Script for Property Management System
# ============================================

echo ""
echo "============================================"
echo "Property Management System - Deployment"
echo "============================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Clean old build files
echo "[1/5] Cleaning old build files..."
rm -rf .next
rm -rf out
echo -e "  ${GREEN}✓${NC} Clean completed"
echo ""

# Step 2: Install dependencies
echo "[2/5] Installing/updating dependencies..."
npm install
if [ $? -ne 0 ]; then
    echo -e "  ${RED}✗${NC} Failed to install dependencies"
    exit 1
fi
echo -e "  ${GREEN}✓${NC} Dependencies installed"
echo ""

# Step 3: Build the application
echo "[3/5] Building Next.js application..."
npm run build
if [ $? -ne 0 ]; then
    echo -e "  ${RED}✗${NC} Build failed"
    exit 1
fi
echo -e "  ${GREEN}✓${NC} Build completed"
echo ""

# Step 4: Check export
echo "[4/5] Checking for export configuration..."
if [ -d "out" ]; then
    echo -e "  ${GREEN}✓${NC} Static export created in 'out' folder"
else
    echo -e "  ${YELLOW}i${NC} Using server mode (.next folder)"
fi
echo ""

# Step 5: Create deployment package
echo "[5/5] Creating deployment package..."
rm -rf deploy-package
mkdir -p deploy-package

# Copy PHP files
echo "  - Copying PHP backend files..."
cp auth_api.php deploy-package/ 2>/dev/null || echo "    Warning: auth_api.php not found"
cp property_owner_api.php deploy-package/ 2>/dev/null || echo "    Warning: property_owner_api.php not found"
cp occupancy_metrics_api.php deploy-package/ 2>/dev/null || echo "    Warning: occupancy_metrics_api.php not found"
cp auto_import_cron.php deploy-package/ 2>/dev/null || echo "    Warning: auto_import_cron.php not found"
cp check_all_properties.php deploy-package/ 2>/dev/null || echo "    Warning: check_all_properties.php not found"
cp auto_update_property_rooms.php deploy-package/ 2>/dev/null || echo "    Warning: auto_update_property_rooms.php not found"

# Copy frontend files
if [ -d "out" ]; then
    echo "  - Copying static export files..."
    cp -r out/* deploy-package/
else
    echo "  - Copying Next.js server files..."
    cp -r .next deploy-package/
    cp package.json deploy-package/
    cp next.config.ts deploy-package/ 2>/dev/null
    cp tsconfig.json deploy-package/ 2>/dev/null
    cp package-lock.json deploy-package/ 2>/dev/null
fi

echo -e "  ${GREEN}✓${NC} Deployment package created"
echo ""

# Create upload script
cat > deploy-package/UPLOAD_INSTRUCTIONS.txt << 'EOF'
UPLOAD INSTRUCTIONS
===================

1. Upload all files in this folder to your server
2. Target location: /WG/analysis/OCC/
3. Set permissions:
   - PHP files: chmod 644 *.php
   - Directories: chmod 755 (if using .next folder)

4. Test endpoints:
   - https://littlehp.lolipop.jp/occ/auth_api.php?action=check
   - https://exseed.main.jp/WG/analysis/OCC/login

5. If using .next folder (server mode):
   - Install Node.js on server
   - Run: npm install --production
   - Run: npm start (or use PM2: pm2 start npm -- start)

6. Test login:
   - Admin: admin / exseed2025
   - Property Owner: comodita_owner / change123
EOF

# Summary
echo "============================================"
echo "Deployment Package Ready!"
echo "============================================"
echo ""
echo "Location: $(pwd)/deploy-package"
echo ""
echo "Next Steps:"
echo "1. Upload contents of 'deploy-package' folder to your server"
echo "2. Target location: /WG/analysis/OCC/"
echo "3. Read UPLOAD_INSTRUCTIONS.txt in deploy-package folder"
echo ""
echo "Quick FTP Upload (if configured):"
echo "  cd deploy-package"
echo "  ftp your-server.com"
echo "  > cd /WG/analysis/OCC/"
echo "  > mput *"
echo ""
echo "Or use SFTP/FileZilla to upload the deploy-package folder"
echo ""
echo "============================================"
