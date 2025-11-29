# 🚀 Deployment Guide

## Quick Start

### For Windows:
```bash
# Run the deployment script
deploy.bat
```

### For Mac/Linux:
```bash
# Make script executable
chmod +x deploy.sh

# Run the deployment script
./deploy.sh
```

## What the Script Does

1. **Cleans** old build files (.next and out folders)
2. **Installs** npm dependencies
3. **Builds** the Next.js application
4. **Creates** a deployment package with all necessary files
5. **Prepares** instructions for upload

## After Running the Script

You'll find a `deploy-package` folder containing:
- All PHP backend files (auth_api.php, property_owner_api.php, etc.)
- Frontend files (either `out/` static files or `.next/` server files)
- UPLOAD_INSTRUCTIONS.txt with detailed upload steps

## Upload Methods

### Method 1: FTP/SFTP (Recommended for beginners)

Use FileZilla or any FTP client:

1. Connect to your server
2. Navigate to `/WG/analysis/OCC/`
3. **Delete old files first:**
   - Delete old `login.html`
   - Delete old `_next` folder
4. Upload all files from `deploy-package` folder
5. Set PHP file permissions to 644

### Method 2: Command Line SFTP

```bash
cd deploy-package

# Connect via SFTP
sftp user@your-server.com

# Navigate to target directory
cd /WG/analysis/OCC/

# Upload all files
put *

# Exit
exit
```

### Method 3: cPanel File Manager

1. Log into cPanel
2. Open File Manager
3. Navigate to `/WG/analysis/OCC/`
4. Delete old files
5. Upload the contents of `deploy-package` folder
6. Set permissions for PHP files to 644

## Verifying Deployment

### 1. Test PHP APIs

Visit these URLs in your browser:

```
https://littlehp.lolipop.jp/occ/auth_api.php?action=check
```

Expected response:
```json
{
  "authenticated": false
}
```

### 2. Test Login Page

Visit:
```
https://exseed.main.jp/WG/analysis/OCC/login
```

Should show a clean login form (no JavaScript errors)

### 3. Test Login

Try these credentials:
- **Admin**: `admin` / `exseed2025`
- **Property Owner**: `comodita_owner` / `change123`

## Troubleshooting

### Error: "checkUA is not defined"

**Cause**: Old static HTML files still on server

**Fix**:
1. Delete `/WG/analysis/OCC/login.html` on server
2. Delete `/WG/analysis/OCC/_next/` folder on server
3. Re-upload new files from `deploy-package`

### Error: "Mixed Content"

**Cause**: HTTP resources loaded on HTTPS page

**Fix**: Update PHP files to use HTTPS URLs only

### Error: "404 Not Found" on API calls

**Cause**: PHP files not uploaded or wrong location

**Fix**:
1. Verify PHP files are at: `/path/to/occ/auth_api.php`
2. Check file permissions (should be 644)
3. Test API URL directly in browser

### Error: "CORS Policy"

**Cause**: Frontend can't communicate with backend

**Fix**: Update CORS headers in PHP files:
```php
header("Access-Control-Allow-Origin: https://exseed.main.jp");
header("Access-Control-Allow-Credentials: true");
```

## Configuration Files

### next.config.mjs

Ensure your config has the correct basePath:

```javascript
/** @type {import('next').NextConfig} */
const nextConfig = {
  basePath: '/WG/analysis/OCC',
  output: 'export', // For static export
  images: {
    unoptimized: true, // Required for static export
  },
};

export default nextConfig;
```

### package.json

Add export script if not present:

```json
{
  "scripts": {
    "dev": "next dev",
    "build": "next build",
    "start": "next start",
    "export": "next export",
    "lint": "next lint"
  }
}
```

## Two Deployment Modes

### Mode 1: Static Export (Simpler, recommended for shared hosting)

**Pros:**
- No Node.js required on server
- Works on any web hosting
- Faster page loads

**Cons:**
- No server-side rendering
- Limited dynamic features

**Setup:**
```javascript
// next.config.mjs
export default {
  basePath: '/WG/analysis/OCC',
  output: 'export',
  images: { unoptimized: true },
};
```

**Deploy:** Upload contents of `out/` folder

### Mode 2: Server Mode (More features)

**Pros:**
- Full Next.js features
- Server-side rendering
- API routes support

**Cons:**
- Requires Node.js on server
- More complex setup

**Setup:**
```javascript
// next.config.mjs
export default {
  basePath: '/WG/analysis/OCC',
};
```

**Deploy:**
1. Upload `.next/` folder
2. Upload `package.json`
3. Run `npm install --production` on server
4. Run `npm start` or use PM2

## File Checklist

After deployment, verify these files exist:

```
/WG/analysis/OCC/
├── _next/                    (or out/ for static)
│   ├── static/
│   └── chunks/
├── auth_api.php              ✓ Required
├── property_owner_api.php    ✓ Required
├── occupancy_metrics_api.php ✓ Required
├── auto_import_cron.php      (Optional)
├── check_all_properties.php  (Optional)
└── favicon.ico
```

## Testing Checklist

- [ ] Login page loads without errors
- [ ] Can login as admin
- [ ] Admin dashboard displays
- [ ] Can logout
- [ ] Can login as property owner
- [ ] Property dashboard displays with charts
- [ ] Year comparison works
- [ ] No console errors

## Need Help?

If you're still having issues:

1. **Check browser console** (F12 → Console tab)
2. **Check server error logs**
3. **Test each API endpoint individually**
4. **Verify file paths and permissions**
5. **Compare with working local version**

## Quick Test Script

Run this after deployment:

```bash
# Test API
curl https://littlehp.lolipop.jp/occ/auth_api.php?action=check

# Should return: {"authenticated":false}
```

## Support

For issues, check:
- Browser console (F12)
- Server error logs
- Network tab (F12 → Network)
- PHP error logs

Common fix: Delete ALL old files before uploading new ones!
