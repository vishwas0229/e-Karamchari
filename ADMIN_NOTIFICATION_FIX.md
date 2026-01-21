# Admin Notification Fix - Complete Guide

## Problem Solved
Admin dashboard mein notifications show nahi ho rahe the kyunki:
1. Employees jab leave apply karte the, admins ko notification nahi milta tha
2. Employees jab grievance submit karte the, admins ko notification nahi milta tha

## Solution Implemented

### 1. Notification Helper System ✓
**New File**: `backend/middleware/notifications.php`

Helper functions banaye gaye:
- `notifyAdmins()` - Sabhi admins ko notify karo
- `notifyUser()` - Specific user ko notify karo
- `notifyByRole()` - Role ke basis pe notify karo
- `notifyDepartment()` - Department ke basis pe notify karo
- `getUserName()` - User ka naam get karo

### 2. Leave Application Notifications ✓
**File**: `backend/api/leaves.php`

Ab jab employee leave apply karta hai:
- Sabhi admins ko notification milta hai
- Notification mein employee name aur request number hota hai
- Link directly leave-approvals.html pe jaata hai

### 3. Grievance Submission Notifications ✓
**File**: `backend/api/grievances.php`

Ab jab employee grievance submit karta hai:
- Sabhi admins ko notification milta hai
- Priority ke basis pe notification type set hota hai:
  - Critical → Error (red)
  - High → Warning (yellow)
  - Medium/Low → Info (blue)
- Link directly grievances.html pe jaata hai

## How to Test

### Method 1: Employee Actions (Recommended)

1. **Login as Employee**
   - Go to `employee-login.html`
   - Login with employee credentials

2. **Apply for Leave**
   - Go to "Apply Leave"
   - Fill form and submit
   - This will create notification for admins

3. **Submit Grievance**
   - Go to "Submit Grievance"
   - Fill form and submit
   - This will create notification for admins

4. **Login as Admin**
   - Logout from employee
   - Go to `admin-login.html`
   - Login with admin credentials

5. **Check Notifications**
   - Click bell icon (🔔)
   - You should see notifications!

### Method 2: Direct Test Notification

1. **Login as Admin**
   - Go to admin dashboard

2. **Open Console** (F12)

3. **Create Test Notification**
   ```javascript
   API.dashboard.createTestNotification()
       .then(() => loadNotifications())
       .then(() => console.log('✓ Done!'));
   ```

4. **Click Bell Icon**
   - Notification should appear

### Method 3: Use Test Page

1. **Login as Admin**

2. **Open**: `test-notifications.html`

3. **Click**: "Create Test Notification"

4. **Go back to admin dashboard**

5. **Click bell icon**

## Expected Behavior

### When Employee Applies Leave:
```
Admin Dashboard → Bell Icon → Shows:
┌─────────────────────────────────────┐
│ 🔔 New Leave Request                │
│ John Doe has applied for leave      │
│ (LV-2024-001)                       │
│ Just now                            │
└─────────────────────────────────────┘
```

### When Employee Submits Grievance:
```
Admin Dashboard → Bell Icon → Shows:
┌─────────────────────────────────────┐
│ ⚠ New Grievance Submitted           │
│ John Doe has submitted a grievance: │
│ Salary Issue (GR-2024-001)          │
│ Just now                            │
└─────────────────────────────────────┘
```

## Console Debugging

### Check if notifications are loading:
```javascript
// Should show detailed logs
[Admin Dashboard] Loading notifications...
[Admin Dashboard] Notifications response: {success: true, ...}
[Admin Dashboard] Notifications data: {count: X, ...}
[Admin Dashboard] Rendering X notifications
```

### Manually load notifications:
```javascript
loadNotifications();
```

### Check notification count:
```javascript
API.dashboard.getNotifications({limit: 10})
    .then(r => console.log('Count:', r.data.unread_count));
```

## Files Modified

### Backend
1. `backend/middleware/notifications.php` - NEW helper file
2. `backend/api/leaves.php` - Added admin notifications
3. `backend/api/grievances.php` - Added admin notifications

### Frontend (Already Fixed)
1. `employee/dashboard.html` - Detailed logging
2. `admin/dashboard.html` - Detailed logging
3. `frontend/js/api.js` - Test notification method

## Notification Types

| Action | Notification Type | Color | Link |
|--------|------------------|-------|------|
| Leave Applied | Info | Blue | leave-approvals.html |
| Leave Approved | Success | Green | leave-status.html |
| Leave Rejected | Warning | Yellow | leave-status.html |
| Grievance (Low/Medium) | Info | Blue | grievances.html |
| Grievance (High) | Warning | Yellow | grievances.html |
| Grievance (Critical) | Error | Red | grievances.html |
| Service Record Added | Info | Blue | service-record.html |

## Troubleshooting

### Issue: Admin ko notification nahi mil raha

**Check 1**: Admin role verify karo
```sql
SELECT u.id, u.first_name, u.last_name, r.role_code 
FROM users u 
JOIN roles r ON u.role_id = r.id 
WHERE u.id = YOUR_ADMIN_ID;
```
Role code should be: `SUPER_ADMIN`, `ADMIN`, or `OFFICER`

**Check 2**: Database mein notification create hua?
```sql
SELECT * FROM notifications 
WHERE user_id = YOUR_ADMIN_ID 
ORDER BY created_at DESC 
LIMIT 5;
```

**Check 3**: Console mein errors?
- Open F12
- Look for red errors
- Share error message

### Issue: Notification create ho raha but show nahi ho raha

**Solution**: Frontend issue hai
1. Check console logs
2. Run: `loadNotifications()`
3. Check if `notifications-list` element exists

### Issue: Multiple admins mein se kisi ko nahi mil raha

**Check**: Active admins count
```sql
SELECT COUNT(*) as admin_count
FROM users u
JOIN roles r ON u.role_id = r.id
WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN', 'OFFICER') 
AND u.is_active = 1;
```

## Testing Checklist

- [ ] Admin login successful
- [ ] Bell icon visible in header
- [ ] Console shows loading logs
- [ ] Test notification creates successfully
- [ ] Test notification shows in panel
- [ ] Employee leave application creates admin notification
- [ ] Employee grievance submission creates admin notification
- [ ] Notification click redirects to correct page
- [ ] Mark as read works
- [ ] Mark all as read works

## Success Criteria

✅ Admin logs in
✅ Employee applies leave
✅ Admin sees notification immediately (after refresh/reload)
✅ Notification shows employee name and request number
✅ Click on notification redirects to leave approvals
✅ Mark as read works properly

## Next Steps

1. **Test with real data**: Employee apply leave karo
2. **Check admin dashboard**: Notification dikhai dena chahiye
3. **Test grievance**: Employee grievance submit karo
4. **Verify notifications**: Admin dashboard mein check karo

## Support

Agar abhi bhi issue hai:
1. Console output share karo (F12)
2. Database query results share karo
3. Screenshot share karo (notification panel ka)
