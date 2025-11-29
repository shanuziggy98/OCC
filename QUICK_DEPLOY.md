# 🚀 Quick Deployment Instructions

## Step-by-Step Guide

### 1️⃣ Run the Deployment Script

**Windows:**
```bash
deploy.bat
```

**Mac/Linux:**
```bash
chmod +x deploy.sh
./deploy.sh
```

This will create a `deploy-package` folder with all files ready to upload.

---

### 2️⃣ Upload Files to Server

#### Option A: Using FileZilla (Easiest)

1. **Open FileZilla**
2. **Connect to your server:**
   - Host: `your-server.com`
   - Username: `your-username`
   - Password: `your-password`
   - Port: `21` (FTP) or `22` (SFTP)

3. **Navigate on server side:**
   - Go to: `/WG/analysis/OCC/`

4. **IMPORTANT - Delete old files first:**
   - Delete `login.html`
   - Delete `_next` folder
   - Delete any old static files

5. **Upload new files:**
   - Select all files from local `deploy-package` folder
   - Drag and drop to server `/WG/analysis/OCC/` folder

6. **Set permissions for PHP files:**
   - Right-click each `.php` file
   - Change permissions to `644`

#### Option B: Using cPanel

1. **Log into cPanel**
2. **Open File Manager**
3. **Navigate to:** `/WG/analysis/OCC/`
4. **Delete old files** (login.html, _next folder)
5. **Upload:** Click "Upload" and select all files from `deploy-package`
6. **Set PHP file permissions to 644**

---

### 3️⃣ Test Your Deployment

#### Test 1: API Check
Open in browser:
```
https://littlehp.lolipop.jp/occ/auth_api.php?action=check
```

✅ Should show: `{"authenticated":false}`

#### Test 2: Login Page
Open in browser:
```
https://exseed.main.jp/WG/analysis/OCC/login
```

✅ Should show clean login form (no errors)

#### Test 3: Login
Try logging in:
- **Admin:** `admin` / `exseed2025`
- **Property Owner:** `comodita_owner` / `change123`

✅ Should redirect to appropriate dashboard

---

## Common Issues & Quick Fixes

### ❌ Issue: "checkUA is not defined"
**Fix:** Old HTML file still on server
```
1. Delete /WG/analysis/OCC/login.html on server
2. Delete /WG/analysis/OCC/_next/ folder
3. Re-upload files from deploy-package
```

### ❌ Issue: "Failed to fetch" or CORS error
**Fix:** Check CORS headers in PHP files
```php
// In auth_api.php and property_owner_api.php
header("Access-Control-Allow-Origin: https://exseed.main.jp");
header("Access-Control-Allow-Credentials: true");
```

### ❌ Issue: White screen or 404
**Fix:** basePath might be wrong
```
Check browser console (F12)
Look for 404 errors on /_next/ files
Verify files uploaded to correct location
```

---

## Deployment Checklist

Before uploading:
- [ ] Run `deploy.bat` or `deploy.sh`
- [ ] Check that `deploy-package` folder was created
- [ ] Verify PHP files are in deploy-package

During upload:
- [ ] Delete old `login.html` on server
- [ ] Delete old `_next` folder on server
- [ ] Upload all files from `deploy-package`
- [ ] Set PHP files to 644 permissions

After upload:
- [ ] Test: https://littlehp.lolipop.jp/occ/auth_api.php?action=check
- [ ] Test: https://exseed.main.jp/WG/analysis/OCC/login
- [ ] Try logging in as admin
- [ ] Try logging in as property owner
- [ ] Check browser console for errors (F12)

---

## File Locations

Your files should be at:

```
Server: /WG/analysis/OCC/
├── _next/                    (Next.js files)
│   ├── static/
│   └── chunks/
├── auth_api.php              ← Backend
├── property_owner_api.php    ← Backend
├── occupancy_metrics_api.php ← Backend
├── auto_import_cron.php
├── check_all_properties.php
└── ...other PHP files
```

---

## Need Help?

### Check Browser Console
1. Press `F12`
2. Click "Console" tab
3. Look for red errors
4. Share screenshot if needed

### Test APIs Directly
```bash
# Test auth API
curl https://littlehp.lolipop.jp/occ/auth_api.php?action=check

# Test occupancy API
curl https://littlehp.lolipop.jp/occ/occupancy_metrics_api.php?action=last_import_time
```

### Common URLs to Test
- Login: https://exseed.main.jp/WG/analysis/OCC/login
- Admin Dashboard: https://exseed.main.jp/WG/analysis/OCC/admin-dashboard
- Auth API: https://littlehp.lolipop.jp/occ/auth_api.php?action=check

---

## Summary

1. ✅ Run `deploy.bat`
2. ✅ Delete old files on server
3. ✅ Upload `deploy-package` contents
4. ✅ Set PHP permissions to 644
5. ✅ Test the URLs above

That's it! Your login system should now be live! 🎉
