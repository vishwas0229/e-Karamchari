/**
 * e-Karamchari Page Loader
 * Shows a professional loading animation while page loads
 */

(function() {
    // Function to initialize loader
    function initLoader() {
        // Check if body exists
        if (!document.body) {
            return;
        }
        
        // Check if loader already exists (prevent duplicates)
        if (document.getElementById('page-loader')) {
            return;
        }
        
        // Determine if this is an admin page
        const isAdminPage = window.location.pathname.includes('/admin/') || 
                            window.location.pathname.includes('admin-login') ||
                            window.location.pathname.includes('admin-register');
        
        // Create loader HTML - single spinner only
        const loaderHTML = `
            <div class="page-loader ${isAdminPage ? 'admin-loader' : ''}" id="page-loader">
                <div class="page-loader-content">
                    <div class="loader-spinner"></div>
                    <div class="page-loader-logo">e-Karamchari</div>
                    <div class="page-loader-subtitle">${isAdminPage ? 'Admin Portal' : 'Employee Self-Service Portal'}</div>
                    <div class="loader-text">Loading...</div>
                </div>
            </div>
        `;
        
        // Insert loader at the beginning of body
        document.body.insertAdjacentHTML('afterbegin', loaderHTML);
    }
    
    // Hide loader when page is fully loaded
    function hideLoader() {
        const loader = document.getElementById('page-loader');
        if (loader) {
            loader.classList.add('fade-out');
            setTimeout(() => {
                if (loader.parentNode) {
                    loader.remove();
                }
            }, 500);
        }
    }
    
    // Wait for DOM to be ready before initializing
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoader);
    } else {
        // DOM is already ready
        initLoader();
    }
    
    // Hide loader on window load
    if (document.readyState === 'complete') {
        setTimeout(hideLoader, 100);
    } else {
        window.addEventListener('load', hideLoader);
    }
    
    // Fallback: Hide loader after 3 seconds max
    setTimeout(hideLoader, 3000);
})();
