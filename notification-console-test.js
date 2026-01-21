/**
 * Notification System Console Test Script
 * Copy-paste this in browser console to debug notifications
 */

console.log('=== Notification System Diagnostic ===');

// Test 1: Check if API is loaded
console.log('\n1. Checking API...');
if (typeof API !== 'undefined') {
    console.log('✓ API object found');
    if (API.dashboard && API.dashboard.getNotifications) {
        console.log('✓ getNotifications method exists');
    } else {
        console.error('✗ getNotifications method not found');
    }
} else {
    console.error('✗ API object not found');
}

// Test 2: Check if Utils is loaded
console.log('\n2. Checking Utils...');
if (typeof Utils !== 'undefined') {
    console.log('✓ Utils object found');
    if (Utils.escapeHtml) console.log('✓ escapeHtml exists');
    if (Utils.getRelativeTime) console.log('✓ getRelativeTime exists');
} else {
    console.error('✗ Utils object not found');
}

// Test 3: Check DOM elements
console.log('\n3. Checking DOM elements...');
const notificationPanel = document.getElementById('notifications-panel');
const notificationList = document.getElementById('notifications-list');
const notificationBtn = document.getElementById('notification-btn');
const notificationCount = document.getElementById('notification-count');

console.log('Notification Panel:', notificationPanel ? '✓ Found' : '✗ Not found');
console.log('Notification List:', notificationList ? '✓ Found' : '✗ Not found');
console.log('Notification Button:', notificationBtn ? '✓ Found' : '✗ Not found');
console.log('Notification Count Badge:', notificationCount ? '✓ Found' : '✗ Not found');

// Test 4: Try to load notifications
console.log('\n4. Testing notification load...');
if (typeof API !== 'undefined' && API.dashboard) {
    API.dashboard.getNotifications({ limit: 10 })
        .then(response => {
            console.log('API Response:', response);
            if (response && response.success) {
                console.log('✓ API call successful');
                console.log('Notifications:', response.data.notifications);
                console.log('Unread count:', response.data.unread_count);
                console.log('Page counts:', response.data.page_counts);
                
                if (response.data.notifications && response.data.notifications.length > 0) {
                    console.log('✓ Found', response.data.notifications.length, 'notifications');
                    console.log('First notification:', response.data.notifications[0]);
                } else {
                    console.warn('⚠ No notifications in database');
                    console.log('TIP: Create a test notification using:');
                    console.log('API.dashboard.createTestNotification().then(r => console.log(r))');
                }
            } else {
                console.error('✗ API call failed:', response);
            }
        })
        .catch(error => {
            console.error('✗ Error loading notifications:', error);
        });
} else {
    console.error('✗ Cannot test - API not available');
}

// Test 5: Check session
console.log('\n5. Checking session...');
if (typeof API !== 'undefined' && API.auth) {
    API.auth.checkSession()
        .then(response => {
            console.log('Session Response:', response);
            if (response && response.success) {
                console.log('✓ User is logged in');
            } else {
                console.error('✗ User not logged in');
            }
        })
        .catch(error => {
            console.error('✗ Session check failed:', error);
        });
}

// Helpful commands
console.log('\n=== Helpful Commands ===');
console.log('Create test notification:');
console.log('  API.dashboard.createTestNotification().then(r => console.log(r))');
console.log('\nLoad notifications:');
console.log('  API.dashboard.getNotifications({limit: 10}).then(r => console.log(r))');
console.log('\nToggle notification panel:');
console.log('  toggleNotifications()');
console.log('\nManually load notifications in panel:');
console.log('  loadNotifications()');
console.log('\n=== End Diagnostic ===');
