/**
 * Common Notifications Handler
 * Loads notification badges on all pages
 */

// Load notifications and update sidebar badges
async function loadSidebarNotifications() {
    try {
        const response = await API.dashboard.getNotifications({ limit: 10 });
        
        if (response && response.success) {
            const { unread_count, page_counts } = response.data;
            
            // Update header notification badge if exists
            const headerBadge = document.getElementById('notification-count');
            if (headerBadge) {
                if (unread_count > 0) {
                    headerBadge.textContent = unread_count;
                    headerBadge.style.display = 'flex';
                } else {
                    headerBadge.style.display = 'none';
                }
            }
            
            // Update sidebar badges
            if (page_counts) {
                const badges = {
                    'badge-leave': page_counts.leave || 0,
                    'badge-grievance': page_counts.grievance || 0,
                    'badge-attendance': page_counts.attendance || 0,
                    'badge-salary': page_counts.salary || 0,
                    'badge-profile': page_counts.profile || 0,
                    'badge-service': page_counts.service || 0,
                    'badge-reports': page_counts.reports || 0,
                    'badge-settings': page_counts.settings || 0
                };
                
                for (const [id, count] of Object.entries(badges)) {
                    const badge = document.getElementById(id);
                    if (badge) {
                        if (count > 0) {
                            badge.textContent = count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }
            }
        } else {
            console.error('Failed to load sidebar notifications:', response);
        }
    } catch (error) {
        console.error('Error loading sidebar notifications:', error);
    }
}

// Auto-load notifications when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait a bit for auth to complete, then load notifications
    setTimeout(() => {
        loadSidebarNotifications();
    }, 500);
});
