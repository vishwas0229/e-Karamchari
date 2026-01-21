/**
 * e-Karamchari Authentication Module
 * Handles login, logout, and session management
 */

const Auth = {
    /**
     * Initialize authentication
     */
    init() {
        this.setupPasswordToggle();
        this.setupFormValidation();
    },
    
    /**
     * Setup password visibility toggle
     */
    setupPasswordToggle() {
        const toggleBtns = document.querySelectorAll('.password-toggle-btn');
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.parentElement.querySelector('input');
                const icon = btn.querySelector('span');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.textContent = 'Hide';
                } else {
                    input.type = 'password';
                    icon.textContent = 'Show';
                }
            });
        });
    },
    
    /**
     * Setup form validation
     */
    setupFormValidation() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const inputs = form.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('blur', () => this.validateInput(input));
                input.addEventListener('input', () => {
                    if (input.classList.contains('error')) {
                        this.validateInput(input);
                    }
                });
            });
        });
    },
    
    /**
     * Validate single input
     */
    validateInput(input) {
        const value = input.value.trim();
        const errorElement = input.parentElement.querySelector('.form-error') ||
                            input.parentElement.parentElement.querySelector('.form-error');
        
        let isValid = true;
        let errorMessage = '';
        
        if (input.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        } else if (input.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        } else if (input.name === 'password' && value && value.length < 8) {
            isValid = false;
            errorMessage = 'Password must be at least 8 characters';
        }
        
        if (isValid) {
            input.classList.remove('error');
            if (errorElement) errorElement.textContent = '';
        } else {
            input.classList.add('error');
            if (errorElement) errorElement.textContent = errorMessage;
        }
        
        return isValid;
    },
    
    /**
     * Handle employee login
     */
    async handleEmployeeLogin(event) {
        event.preventDefault();
        
        const form = event.target;
        const employeeId = form.querySelector('[name="employee_id"]').value.trim();
        const password = form.querySelector('[name="password"]').value;
        const submitBtn = form.querySelector('button[type="submit"]');
        const errorDiv = document.getElementById('login-error');
        
        // Validate
        if (!employeeId || !password) {
            if (errorDiv) {
                errorDiv.textContent = 'Please enter Employee ID and Password';
                errorDiv.style.display = 'block';
            }
            return;
        }
        
        // Show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner" style="width:20px;height:20px;border-width:2px;"></span> Logging in...';
        if (errorDiv) errorDiv.style.display = 'none';
        
        try {
            const response = await API.auth.login(employeeId, password);
            
            if (response && response.success) {
                // Store user info
                Utils.setSession('user', response.data);
                Utils.setSession('isLoggedIn', true);
                Utils.setSession('userRole', 'employee');
                
                // Redirect to employee dashboard
                window.location.href = 'employee/dashboard.html';
            } else {
                if (errorDiv) {
                    errorDiv.textContent = response?.message || 'Invalid credentials';
                    errorDiv.style.display = 'block';
                }
            }
        } catch (error) {
            console.error('Login error:', error);
            if (errorDiv) {
                // Show more detailed error
                errorDiv.textContent = 'Server error: ' + (error.message || 'Unable to connect to server. Please check if the database is running.');
                errorDiv.style.display = 'block';
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Login';
        }
    },
    
    /**
     * Handle admin login
     */
    async handleAdminLogin(event) {
        event.preventDefault();
        
        const form = event.target;
        const identifier = form.querySelector('[name="identifier"]').value.trim();
        const password = form.querySelector('[name="password"]').value;
        const submitBtn = form.querySelector('button[type="submit"]');
        const errorDiv = document.getElementById('login-error');
        
        // Validate
        if (!identifier || !password) {
            if (errorDiv) {
                errorDiv.textContent = 'Please enter Admin ID/Email and Password';
                errorDiv.style.display = 'block';
            }
            return;
        }
        
        // Show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner" style="width:20px;height:20px;border-width:2px;"></span> Logging in...';
        if (errorDiv) errorDiv.style.display = 'none';
        
        try {
            const response = await API.auth.adminLogin(identifier, password);
            
            if (response.success) {
                // Store user info
                Utils.setSession('user', response.data);
                Utils.setSession('isLoggedIn', true);
                Utils.setSession('userRole', 'admin');
                
                // Redirect to admin dashboard
                const basePath = window.location.pathname.includes('/admin/') ? '' : 'admin/';
                window.location.href = basePath + 'dashboard.html';
            } else {
                if (errorDiv) {
                    errorDiv.textContent = response.message || 'Invalid credentials';
                    errorDiv.style.display = 'block';
                }
            }
        } catch (error) {
            console.error('Login error:', error);
            if (errorDiv) {
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Login';
        }
    },
    
    /**
     * Handle logout
     */
    async logout() {
        try {
            await API.auth.logout();
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            // Clear session
            Utils.clearSession();
            
            // Redirect to home
            window.location.href = '../index.html';
        }
    },
    
    /**
     * Check if user is authenticated
     */
    async checkAuth(requiredRole = null) {
        try {
            const response = await API.auth.checkSession();
            
            if (!response.success || !response.data.authenticated) {
                window.location.href = '../session-expired.html';
                return false;
            }
            
            // Check role if required
            if (requiredRole) {
                const userRole = response.data.role;
                const adminRoles = ['SUPER_ADMIN', 'ADMIN', 'OFFICER'];
                
                if (requiredRole === 'admin' && !adminRoles.includes(userRole)) {
                    window.location.href = '../unauthorized.html';
                    return false;
                }
                
                if (requiredRole === 'employee' && userRole !== 'EMPLOYEE') {
                    window.location.href = '../unauthorized.html';
                    return false;
                }
            }
            
            return true;
        } catch (error) {
            console.error('Auth check error:', error);
            window.location.href = '../session-expired.html';
            return false;
        }
    },
    
    /**
     * Require admin authentication - redirects if not admin
     */
    async requireAdmin() {
        try {
            const response = await API.auth.checkSession();
            
            if (!response.success || !response.data.authenticated) {
                window.location.href = '../session-expired.html';
                return false;
            }
            
            const userRole = response.data.role;
            const adminRoles = ['SUPER_ADMIN', 'ADMIN', 'OFFICER'];
            
            if (!adminRoles.includes(userRole)) {
                window.location.href = '../unauthorized.html';
                return false;
            }
            
            // Store user data in session for later use
            if (response.data.user) {
                Utils.setSession('user', response.data.user);
            }
            
            return true;
        } catch (error) {
            console.error('Admin auth check error:', error);
            window.location.href = '../session-expired.html';
            return false;
        }
    },
    
    /**
     * Require employee authentication - redirects if not employee
     */
    async requireEmployee() {
        try {
            const response = await API.auth.checkSession();
            
            if (!response.success || !response.data.authenticated) {
                window.location.href = '../session-expired.html';
                return false;
            }
            
            // Store user data in session for later use
            if (response.data.user) {
                Utils.setSession('user', response.data.user);
            }
            
            return true;
        } catch (error) {
            console.error('Employee auth check error:', error);
            window.location.href = '../session-expired.html';
            return false;
        }
    },
    
    /**
     * Get current user
     */
    async getCurrentUser() {
        try {
            const response = await API.auth.getCurrentUser();
            if (response.success) {
                Utils.setSession('user', response.data);
                return response.data;
            }
            return null;
        } catch (error) {
            console.error('Get user error:', error);
            return null;
        }
    },
    
    /**
     * Update user display in header
     */
    updateUserDisplay(user) {
        const userNameEl = document.querySelector('.user-name');
        const userRoleEl = document.querySelector('.user-role');
        const userAvatarEl = document.querySelector('.user-avatar');
        
        if (userNameEl && user.name) {
            userNameEl.textContent = user.name;
        }
        
        if (userRoleEl && user.role_name) {
            userRoleEl.textContent = user.role_name;
        }
        
        if (userAvatarEl && user.name) {
            userAvatarEl.textContent = Utils.getInitials(user.name);
        }
    }
};

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
});

// Export for use in other files
window.Auth = Auth;
