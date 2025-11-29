# ✅ URL Fixes Applied

## Problem Fixed

**Error:** `ERR_NAME_NOT_RESOLVED` for `littlehp.lolipop.jp`

**Root Cause:** Incorrect hardcoded URLs in components

## Files Updated

### Frontend Components (URLs fixed)

1. **`src/components/LoginPage.tsx`**
   - ❌ Old: `https://littlehp.lolipop.jp/occ/auth_api.php`
   - ✅ New: `https://exseed.main.jp/WG/analysis/OCC/auth_api.php`

2. **`src/components/PropertyOwnerDashboard.tsx`**
   - ❌ Old: `https://littlehp.lolipop.jp/occ/property_owner_api.php`
   - ✅ New: `https://exseed.main.jp/WG/analysis/OCC/property_owner_api.php`
   - Fixed 3 URLs (API, auth check, logout)

3. **`src/components/OccupancyDashboard.tsx`**
   - ❌ Old: `https://littlehp.lolipop.jp/occ/auth_api.php`
   - ✅ New: `https://exseed.main.jp/WG/analysis/OCC/auth_api.php`
   - Fixed 2 URLs (auth check, logout)

### Backend API Files (CORS fixed)

1. **`auth_api.php`**
   - ❌ Old: `Access-Control-Allow-Origin: http://localhost:3000`
   - ✅ New: `Access-Control-Allow-Origin: https://exseed.main.jp`

2. **`property_owner_api.php`**
   - ❌ Old: `Access-Control-Allow-Origin: http://localhost:3000`
   - ✅ New: `Access-Control-Allow-Origin: https://exseed.main.jp`

## Next Steps

### 1. Rebuild the Application
```bash
# Run deployment script
deploy.bat
```

### 2. Upload Files
Upload these files from `deploy-package` to server:
- `auth_api.php` (CORS headers updated)
- `property_owner_api.php` (CORS headers updated)
- All frontend files from `out/` or `_next/`

### 3. Test Again
Visit: `https://exseed.main.jp/WG/analysis/OCC/login`

Should now work without `ERR_NAME_NOT_RESOLVED` errors!

## What Changed

### Before:
```
Frontend (exseed.main.jp) → ❌ littlehp.lolipop.jp/occ/auth_api.php (doesn't exist)
```

### After:
```
Frontend (exseed.main.jp) → ✅ exseed.main.jp/WG/analysis/OCC/auth_api.php (correct path)
```

## Expected Behavior

After rebuilding and uploading:
1. ✅ Login page loads without errors
2. ✅ Can submit login form
3. ✅ API calls reach correct endpoints
4. ✅ No CORS errors
5. ✅ Authentication works

## Test URLs

After deployment, test these:

**Login Page:**
```
https://exseed.main.jp/WG/analysis/OCC/login
```

**Auth Check API:**
```
https://exseed.main.jp/WG/analysis/OCC/auth_api.php?action=check
```

Should return:
```json
{"authenticated":false}
```

## Credentials (unchanged)

- Admin: `admin` / `exseed2025`
- Property Owner: `comodita_owner` / `change123`

---

## Ready to Deploy!

Run: `deploy.bat` then upload the files! 🚀
