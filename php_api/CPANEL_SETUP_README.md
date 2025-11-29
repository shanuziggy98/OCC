# C-Panel Setup Instructions

## Overview
This C-Panel allows you to manage properties and users in your Property Management System. All changes made in the C-Panel will be reflected in the Admin Dashboard and Property Owner dashboards.

## Login Credentials
- **Username:** `cpanel`
- **Password:** `cpanel123`
- **URL:** Your app URL `/login` (same login page, will redirect to C-Panel dashboard)

## Setup Steps

### Step 1: Upload PHP API File

Upload the `cpanel_api.php` file to your server at:
```
https://exseed.main.jp/WG/analysis/OCC/cpanel_api.php
```

### Step 2: Update Database Configuration

Edit the `cpanel_api.php` file and update the database credentials:

```php
$db_host = 'localhost';           // Your database host
$db_name = 'your_database_name';  // Your database name
$db_user = 'your_database_user';  // Your database username
$db_pass = 'your_database_password'; // Your database password
```

### Step 3: Create C-Panel User in Database

Run the SQL in `auth_api_update.sql` to add the cpanel user:

```sql
INSERT INTO users (username, password, user_type, property_name, full_name, email)
VALUES (
    'cpanel',
    '$2y$10$8K1p/a0dL1LXMEZz8fO8aeh.YB.N9GpwU4NvAcQ8E4KLb3z8V5gAy',
    'cpanel',
    NULL,
    'C-Panel Administrator',
    'cpanel@example.com'
);
```

**Important:** If the password hash doesn't work, generate a new one:
```php
<?php echo password_hash('cpanel123', PASSWORD_DEFAULT); ?>
```

### Step 4: Update User Type ENUM (if needed)

If your `users` table has a strict ENUM for `user_type`, update it to include 'cpanel':

```sql
ALTER TABLE users MODIFY COLUMN user_type ENUM('admin', 'owner', 'cpanel') NOT NULL DEFAULT 'owner';
```

### Step 5: Build and Deploy Next.js App

```bash
npm run build
```

Then deploy the built application to your hosting.

## Features

### Property Management
- **Add Properties:** Create new properties with all configuration (name, type, rooms, commission, cleaning fee, 180-day limit)
- **Edit Properties:** Modify any property settings
- **Delete Properties:** Remove properties (with confirmation)
- **Hostel Support:** Add room types for hostel-type properties
- **Owner Assignment:** Assign property owners to each property

### User Management
- **Add Users:** Create new admin, owner, or cpanel users
- **Edit Users:** Update user details and passwords
- **Delete Users:** Remove users from the system
- **User Types:**
  - `admin` - Access to Admin Dashboard
  - `owner` - Access to Property Owner Dashboard (for their assigned property)
  - `cpanel` - Access to C-Panel Dashboard

## How Changes Affect the System

1. **Property Changes:**
   - New properties appear in Admin Dashboard metrics table
   - Commission rates and cleaning fees are used in calculations
   - 180-day limit properties show in the limit tracking
   - Hostel room types enable room-level metrics

2. **User Changes:**
   - New owners can log in to see their property dashboard
   - Owners see only their assigned property data
   - Multiple properties can be assigned to the same owner username

## Database Tables Created

The C-Panel will create these tables if they don't exist:

### `cpanel_properties`
Stores property configuration managed through C-Panel.

### `property_config`
Synced configuration used by the main metrics API.

## API Endpoints

### C-Panel API (`cpanel_api.php`)
- `GET ?action=get_properties` - List all properties
- `POST ?action=add_property` - Add new property
- `POST ?action=update_property` - Update property
- `POST ?action=delete_property` - Delete property
- `GET ?action=get_users` - List all users
- `POST ?action=add_user` - Add new user
- `POST ?action=update_user` - Update user
- `POST ?action=delete_user` - Delete user

All endpoints require authentication as a cpanel user.

## Troubleshooting

### "Unauthorized" Error
- Make sure you're logged in as the cpanel user
- Check that the session is properly maintained
- Verify the user exists in the database with user_type = 'cpanel'

### Property Not Showing in Admin Dashboard
- The property configuration is synced to `property_config` table
- Your main `occupancy_metrics_api.php` should read from this table
- Check that the property name matches exactly

### Login Fails
- Verify the password hash in the database
- Generate a new hash if needed using `password_hash('cpanel123', PASSWORD_DEFAULT)`
- Check database connection in auth_api.php

## Security Notes

1. Change the default password after setup
2. Limit C-Panel access to authorized administrators only
3. Use HTTPS for all API communications
4. Regularly backup your database

## Existing Calculation Methods Preserved

The C-Panel does NOT change any existing calculation methods:
- **OCC Rate:** `sold_rooms / available_rooms * 100`
- **ADR:** `room_revenue / sold_rooms`
- **RevPAR:** `room_revenue / available_rooms`
- **OTA Commission:** `room_revenue * (commission_percent / 100)`
- **Total Cleaning Fee:** `booking_count * cleaning_fee_per_time`

Special commission methods (fixed, kaguya_monthly) remain unchanged.
