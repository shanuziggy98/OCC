# Login System Documentation

## Overview

A complete authentication system has been implemented with different dashboards for admin and property owner users.

## Features

### 1. **Authentication API** (`auth_api.php`)
- **Login**: POST to `/auth_api.php?action=login`
- **Logout**: GET to `/auth_api.php?action=logout`
- **Check Session**: GET to `/auth_api.php?action=check`
- Session management using PHP sessions
- Automatic redirection based on user type

### 2. **Property Owner API** (`property_owner_api.php`)
- **Summary**: GET `/property_owner_api.php?action=summary&property={name}`
- **Yearly Metrics**: GET `/property_owner_api.php?action=yearly&property={name}&year={year}`
- **Year Comparison**: GET `/property_owner_api.php?action=compare&property={name}&year1={year1}&year2={year2}`
- Authenticated access only
- Property owners can only access their own property data

## User Types & Access

### Admin Users
- **Login**: Use credentials from `property_users` table with `user_type='admin'`
- **Dashboard**: Redirected to `/admin-dashboard`
- **Access**: Can view all properties, import data, view analytics
- **Example Credentials**:
  - Username: `admin`
  - Password: `exseed2025`

### Property Owner Users
- **Login**: Use credentials from `property_users` table with `user_type='property_owner'`
- **Dashboard**: Redirected to `/property-dashboard`
- **Access**: Can only view their own property data
- **Example Credentials**:
  - Username: `comodita_owner`
  - Password: `change123`

## Routes

| Route | Component | Access | Description |
|-------|-----------|--------|-------------|
| `/` | Home | Public | Redirects to `/login` |
| `/login` | LoginPage | Public | Login form |
| `/admin-dashboard` | OccupancyDashboard | Admin only | Full property management dashboard |
| `/property-dashboard` | PropertyOwnerDashboard | Property owners | Individual property analytics |

## Property Owner Dashboard Features

### 1. **Overview Tab**
- Property type (Hostel/Guesthouse)
- Total rooms
- Available years
- Last data import time

### 2. **Yearly Analysis Tab**
- Select any available year
- **Monthly Revenue Chart**: Bar chart showing revenue per month
- **Occupancy Rate Chart**: Line chart showing occupancy trends
- **Monthly Breakdown Table**: Detailed metrics for each month
  - Bookings
  - Revenue
  - Occupancy Rate
  - ADR (Average Daily Rate)

### 3. **Year Comparison Tab**
- Compare any two years side by side
- **Comparison Stats Cards**:
  - Revenue Difference (amount and percentage)
  - Occupancy Rate Difference
  - Booking Count Difference
  - ADR Difference
- **Revenue Comparison Chart**: Dual bar chart
- **Occupancy Comparison Chart**: Dual line chart

## Database Tables

### property_users
```sql
CREATE TABLE `property_users` (
  `id` int NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Plain text password',
  `user_type` enum('admin','property_owner') NOT NULL DEFAULT 'property_owner',
  `property_name` varchar(100) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
```

## How to Add New Property Owner Users

### Using SQL
```sql
INSERT INTO property_users (username, password, user_type, property_name, full_name, email, is_active)
VALUES ('property_username', 'password123', 'property_owner', 'property_name', 'Owner Name', 'email@example.com', 1);
```

### Example
```sql
-- Add owner for 'iwatoyama' property
INSERT INTO property_users (username, password, user_type, property_name, full_name, email, is_active)
VALUES ('iwatoyama_owner', 'secure_password', 'property_owner', 'iwatoyama', 'Iwatoyama Owner', 'iwatoyama@example.com', 1);
```

## Security Notes

⚠️ **Important**: The current implementation uses plain text passwords for simplicity. For production:
1. Implement password hashing (bcrypt/argon2)
2. Add HTTPS enforcement
3. Implement CSRF protection
4. Add rate limiting on login attempts
5. Set secure cookie flags

## API Responses

### Login Success
```json
{
  "success": true,
  "user": {
    "id": 1,
    "username": "admin",
    "user_type": "admin",
    "property_name": null,
    "full_name": "System Administrator",
    "email": "admin@exseed.jp"
  },
  "redirect": "/admin-dashboard"
}
```

### Login Failure
```json
{
  "success": false,
  "error": "Invalid username or password"
}
```

### Session Check (Authenticated)
```json
{
  "authenticated": true,
  "user": {
    "id": 2,
    "username": "comodita_owner",
    "user_type": "property_owner",
    "property_name": "comodita",
    "full_name": "Property: comodita Owner",
    "email": null
  }
}
```

### Session Check (Not Authenticated)
```json
{
  "authenticated": false
}
```

## Property Owner API Responses

### Yearly Metrics Example
```json
{
  "property_name": "comodita",
  "year": 2025,
  "property_type": "guesthouse",
  "monthly_data": {
    "1": {
      "month": 1,
      "booked_nights": 25,
      "booking_count": 5,
      "room_revenue": 125000,
      "occ_rate": 80.65,
      "adr": 5000,
      "total_people": 10,
      "available_rooms": 31
    },
    // ... months 2-12
  }
}
```

### Year Comparison Example
```json
{
  "property_name": "comodita",
  "year1": 2024,
  "year2": 2025,
  "year1_data": { /* full year 1 data */ },
  "year2_data": { /* full year 2 data */ },
  "year1_totals": {
    "total_revenue": 1500000,
    "total_bookings": 60,
    "total_booked_nights": 300,
    "avg_occ_rate": 82.19,
    "avg_adr": 5000,
    "total_people": 120
  },
  "year2_totals": {
    "total_revenue": 1800000,
    "total_bookings": 72,
    "total_booked_nights": 360,
    "avg_occ_rate": 98.63,
    "avg_adr": 5000,
    "total_people": 144
  },
  "differences": {
    "revenue_diff": 300000,
    "revenue_diff_percent": 20.00,
    "occ_rate_diff": 16.44,
    "booking_count_diff": 12,
    "adr_diff": 0
  }
}
```

## Testing the System

### 1. Test Admin Login
1. Navigate to `http://localhost:3000/login`
2. Enter:
   - Username: `admin`
   - Password: `exseed2025`
3. Should redirect to `/admin-dashboard`
4. Should see full property management interface
5. Logout button should appear in top right

### 2. Test Property Owner Login
1. Navigate to `http://localhost:3000/login`
2. Enter:
   - Username: `comodita_owner`
   - Password: `change123`
3. Should redirect to `/property-dashboard`
4. Should see property-specific analytics
5. Can switch between Overview, Yearly Analysis, and Comparison tabs

### 3. Test Authentication Protection
1. Try accessing `/admin-dashboard` without logging in
   - Should redirect to `/login`
2. Login as property owner
3. Try accessing `/admin-dashboard`
   - Should redirect back to `/property-dashboard`

## Customization

### Change Property Owner Password
```sql
UPDATE property_users
SET password = 'new_password'
WHERE username = 'property_username';
```

### Disable User
```sql
UPDATE property_users
SET is_active = 0
WHERE username = 'property_username';
```

### View All Users
```sql
SELECT id, username, user_type, property_name, full_name, is_active, last_login
FROM property_users
ORDER BY user_type, property_name;
```

## Troubleshooting

### Cannot Login
1. Check database connection
2. Verify user exists and `is_active = 1`
3. Check browser console for errors
4. Verify PHP session is working

### Redirects Not Working
1. Check CORS settings in PHP files
2. Verify credentials are being sent with requests
3. Check browser cookies/session storage

### Property Owner Cannot See Data
1. Verify `property_name` in `property_users` matches exactly with `property_sheets`
2. Check property has data in its table
3. Verify table naming (should be lowercase, underscored)

## Future Enhancements

- [ ] Password hashing
- [ ] "Remember Me" functionality
- [ ] Password reset via email
- [ ] Two-factor authentication
- [ ] Activity logs
- [ ] User management UI (for admins)
- [ ] Granular permissions
- [ ] Multi-property access for owners
- [ ] Mobile app support
