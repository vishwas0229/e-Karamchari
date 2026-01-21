# Notification System Debug Guide

## Problem
Notifications panel mein notifications show nahi ho rahe hain.

## Quick Fix Steps

### Step 1: Open Browser Console
1. Dashboard page pe jao (employee ya admin)
2. Press `F12` to open Developer Tools
3. Go to "Console" tab

### Step 2: Run Diagnostic Script
Console mein ye paste karo:
```javascript
// Quick diagnostic
API.dashboard.getNotifications({limit: 10}).then(r => console.log('Response:', r));
```

### Step 3: Check Output
- Agar `success: true` aur `notifications: []` (empty array) hai, to database mein notifications nahi hain
- Agar error hai, to API issue hai
- Agar response nahi aa raha, to session issue hai

### Step 4: Create Test Notification
Console mein run karo:
```javascript
API.dashboard.createTestNotification().then(r => console.log(r));
```

### Step 5: Reload Notifications
Console mein run karo:
```javascript
loadNotifications();
```

## Detailed Diagnostic

### Method 1: Use Console Test Script
1. Open `notification-console-test.js` file
2. Copy all content
3. Paste in browser console
4. Check output for issues

### Method 2: Use Test Page
1. Login to system first
2. Open `test-notifications.html` in browser
3. Click "Check Current Session"
4. Click "Create Test Notification"
5. Click "Load All Notifications"
6. Go back to dashboard and check

## Common Issues & Solutions

### Issue 1: "No notifications" message in panel
**Cause**: Database mein notifications nahi hain
**Solution**: 
```javascript
// Console mein run karo
API.dashboard.createTestNotification().then(r => {
    console.log('Created:', r);
    loadNotifications();
});
```

### Issue 2: Panel khulta hai but empty/white
**Cause**: JavaScript error ya CSS issue
**Solution**:
1. Console mein errors check karo
2. Check if `notifications-list` element exists:
   ```javascript
   console.log(document.getElementById('notifications-list'));
   ```

### Issue 3: API call fail ho raha hai
**Cause**: Session expired ya backend issue
**Solution**:
```javascript
// Check session
API.auth.checkSession().then(r => console.log('Session:', r));
// If session invalid, login again
```

### Issue 4: Utils.escapeHtml error
**Cause**: utils.js load nahi hua
**Solution**:
1. Check if utils.js included hai page mein
2. Console mein check karo:
   ```javascript
   console.log(typeof Utils);
   ```

### Issue 5: Notifications create ho rahe but show nahi ho rahe
**Cause**: Frontend rendering issue
**Solution**:
1. Console logs check karo (detailed logs ab add kiye hain)
2. Manually trigger:
   ```javascript
   toggleNotifications(); // Panel open karo
   loadNotifications();   // Notifications load karo
   ```

## Browser Console Commands

### Check Everything
```javascript
// Complete diagnostic
console.log('API:', typeof API);
console.log('Utils:', typeof Utils);
console.log('Panel:', document.getElementById('notifications-panel'));
console.log('List:', document.getElementById('notifications-list'));
```

### Create & Load
```javascript
// Create test notification and load
API.dashboard.createTestNotification()
    .then(() => API.dashboard.getNotifications({limit: 10}))
    .then(r => console.log('Notifications:', r.data.notifications));
```

### Manual Render Test
```javascript
// Test if rendering works
const container = document.getElementById('notifications-list');
container.innerHTML = '<div class="notification-item">TEST NOTIFICATION</div>';
```

### Check Session
```javascript
API.auth.checkSession().then(r => console.log(r));
API.auth.getCurrentUser().then(r => console.log(r));
```

## Database Check

Run these SQL queries:

```sql
-- Check notifications table
SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10;

-- Count by user
SELECT user_id, COUNT(*) as count FROM notifications GROUP BY user_id;

-- Check unread
SELECT COUNT(*) FROM notifications WHERE is_read = 0;

-- Create test notification (replace user_id with actual ID)
INSERT INTO notifications (user_id, title, message, type, is_read) 
VALUES (1, 'Test Notification', 'This is a test', 'Info', 0);
```

## Files Modified (Latest)

1. `employee/dashboard.html` - Added detailed console logging
2. `admin/dashboard.html` - Added detailed console logging
3. `notification-console-test.js` - NEW diagnostic script
4. `NOTIFICATION_DEBUG_GUIDE.md` - Updated guide

## What to Check in Console

After opening dashboard, console mein ye logs dikhne chahiye:

```
[Notifications] Loading sidebar notifications...
[Notifications] API Response: {success: true, data: {...}}
[Notifications] Unread count: X
```

Agar ye logs nahi dikh rahe:
1. Check if `notifications.js` loaded hai
2. Check if API object available hai
3. Check browser console for errors

## Step-by-Step Debugging

1. **Login** to employee or admin dashboard
2. **Open Console** (F12)
3. **Look for logs** starting with `[Notifications]` or `[Dashboard]`
4. **If no logs**: notifications.js load nahi hua
5. **If logs show "No notifications"**: Database empty hai
6. **If logs show error**: Check error message
7. **Create test notification**: Use console command
8. **Click bell icon**: Panel should open
9. **Check console**: Should show loading logs
10. **If still empty**: Share console output

## Next Steps

Agar abhi bhi kaam nahi kar raha:

1. **Screenshot lein**:
   - Browser console ka
   - Network tab ka (F12 > Network)
   - Notification panel ka

2. **Console output copy karein**:
   ```javascript
   // Ye run karo aur output share karo
   API.dashboard.getNotifications({limit: 10}).then(r => console.log(JSON.stringify(r, null, 2)));
   ```

3. **Database check karein**:
   ```sql
   SELECT COUNT(*) FROM notifications;
   ```
