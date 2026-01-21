/**
 * e-Karamchari API Client
 * Handles all API communications with the backend
 * Enhanced with security features
 */

const API = {
    // CSRF token storage
    csrfToken: null,
    
    // Detect base URL dynamically
    get baseUrl() {
        const path = window.location.pathname;
        if (path.includes('/admin/') || path.includes('/employee/')) {
            return '../backend/api';
        }
        return 'backend/api';
    },
    
    /**
     * Get CSRF token from server
     */
    async getCsrfToken() {
        if (this.csrfToken) return this.csrfToken;
        
        try {
            const response = await fetch(`${this.baseUrl}/auth.php?action=csrf`, {
                credentials: 'include'
            });
            const data = await response.json();
            if (data.success && data.data.token) {
                this.csrfToken = data.data.token;
            }
        } catch (e) {
            console.error('Failed to get CSRF token:', e);
        }
        return this.csrfToken;
    },
    
    /**
     * Make API request
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}/${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'include'
        };
        
        const config = { ...defaultOptions, ...options };
        
        // Add CSRF token for POST/PUT/DELETE requests
        if (['POST', 'PUT', 'DELETE'].includes(config.method)) {
            const token = await this.getCsrfToken();
            if (token) {
                config.headers['X-CSRF-Token'] = token;
            }
        }
        
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }
        
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
            
            const response = await fetch(url, { ...config, signal: controller.signal });
            clearTimeout(timeoutId);
            
            const data = await response.json();
            
            // Don't redirect on auth pages (login, register)
            const isAuthPage = window.location.pathname.includes('login') || 
                              window.location.pathname.includes('register');
            
            if (response.status === 401 && !isAuthPage) {
                // Session expired - only redirect if not on auth page
                this.csrfToken = null; // Clear cached token
                window.location.href = 'session-expired.html';
                return;
            }
            
            if (response.status === 403 && !isAuthPage) {
                // Unauthorized
                window.location.href = 'unauthorized.html';
                return;
            }
            
            // Handle CSRF token refresh
            if (response.status === 419) {
                this.csrfToken = null;
                return this.request(endpoint, options); // Retry with new token
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            
            // Handle specific error types
            if (error.name === 'AbortError') {
                throw new Error('Request timed out. Please try again.');
            }
            
            if (error.message === 'Failed to fetch') {
                throw new Error('Unable to connect to server. Please check your connection.');
            }
            
            throw error;
        }
    },
    
    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        let url = endpoint;
        const queryString = new URLSearchParams(params).toString();
        
        if (queryString) {
            // Check if endpoint already has query params
            url += endpoint.includes('?') ? '&' : '?';
            url += queryString;
        }
        
        return this.request(url, { method: 'GET' });
    },
    
    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: data
        });
    },
    
    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: data
        });
    },
    
    /**
     * DELETE request
     */
    async delete(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'DELETE',
            body: data
        });
    },
    
    // Auth APIs
    auth: {
        async login(employeeId, password) {
            return API.post('auth.php?action=login', { employee_id: employeeId, password });
        },
        
        async adminLogin(identifier, password) {
            return API.post('auth.php?action=admin-login', { identifier, password });
        },
        
        async logout() {
            return API.get('auth.php?action=logout');
        },
        
        async checkSession() {
            return API.get('auth.php?action=check');
        },
        
        async getCurrentUser() {
            return API.get('auth.php?action=user');
        }
    },
    
    // Employee APIs
    employees: {
        async list(params = {}) {
            return API.get('employees.php?action=list', params);
        },
        
        async get(id) {
            return API.get('employees.php?action=get', { id });
        },
        
        async create(data) {
            return API.post('employees.php?action=create', data);
        },
        
        async update(data) {
            return API.post('employees.php?action=update', data);
        },
        
        async delete(id) {
            return API.post('employees.php?action=delete', { id });
        },
        
        async getProfile() {
            return API.get('employees.php?action=profile');
        },
        
        async updateProfile(data) {
            return API.post('employees.php?action=update-profile', data);
        },
        
        async changePassword(currentPassword, newPassword, confirmPassword) {
            return API.post('employees.php?action=change-password', {
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword
            });
        },
        
        async getDepartments() {
            return API.get('employees.php?action=departments');
        },
        
        async getDesignations() {
            return API.get('employees.php?action=designations');
        },
        
        async getStats() {
            return API.get('employees.php?action=stats');
        }
    },
    
    // Leave APIs
    leaves: {
        async list(params = {}) {
            return API.get('leaves.php?action=list', params);
        },
        
        async myLeaves(params = {}) {
            return API.get('leaves.php?action=my-leaves', params);
        },
        
        async apply(data) {
            return API.post('leaves.php?action=apply', data);
        },
        
        async approve(id) {
            return API.post('leaves.php?action=approve', { id });
        },
        
        async reject(id, reason) {
            return API.post('leaves.php?action=reject', { id, reason });
        },
        
        async cancel(id) {
            return API.post('leaves.php?action=cancel', { id });
        },
        
        async getBalance(employeeId = null) {
            const params = employeeId ? { employee_id: employeeId } : {};
            return API.get('leaves.php?action=balance', params);
        },
        
        async getTypes() {
            return API.get('leaves.php?action=types');
        },
        
        async getStats() {
            return API.get('leaves.php?action=stats');
        },
        
        async getPendingCount() {
            return API.get('leaves.php?action=pending-count');
        }
    },
    
    // Grievance APIs
    grievances: {
        async list(params = {}) {
            return API.get('grievances.php?action=list', params);
        },
        
        async myGrievances(params = {}) {
            return API.get('grievances.php?action=my-grievances', params);
        },
        
        async get(id) {
            return API.get('grievances.php?action=get', { id });
        },
        
        async submit(data) {
            return API.post('grievances.php?action=submit', data);
        },
        
        async updateStatus(id, status) {
            return API.post('grievances.php?action=update-status', { id, status });
        },
        
        async assign(id, assignTo) {
            return API.post('grievances.php?action=assign', { id, assign_to: assignTo });
        },
        
        async resolve(id, resolution) {
            return API.post('grievances.php?action=resolve', { id, resolution });
        },
        
        async addComment(grievanceId, comment, isInternal = false) {
            return API.post('grievances.php?action=add-comment', {
                grievance_id: grievanceId,
                comment,
                is_internal: isInternal
            });
        },
        
        async getCategories() {
            return API.get('grievances.php?action=categories');
        },
        
        async getStats() {
            return API.get('grievances.php?action=stats');
        },
        
        async getPendingCount() {
            return API.get('grievances.php?action=pending-count');
        }
    },
    
    // Attendance APIs
    attendance: {
        async list(params = {}) {
            return API.get('attendance.php?action=list', params);
        },
        
        async myAttendance(params = {}) {
            return API.get('attendance.php?action=my-attendance', params);
        },
        
        async checkIn() {
            return API.post('attendance.php?action=check-in');
        },
        
        async checkOut() {
            return API.post('attendance.php?action=check-out');
        },
        
        async getToday() {
            return API.get('attendance.php?action=today');
        },
        
        async mark(data) {
            return API.post('attendance.php?action=mark', data);
        },
        
        async getReport(params = {}) {
            return API.get('attendance.php?action=report', params);
        },
        
        async getSummary() {
            return API.get('attendance.php?action=summary');
        },
        
        async getStats() {
            return API.get('attendance.php?action=stats');
        },
        
        async getHolidays(year = null) {
            const params = year ? { year } : {};
            return API.get('attendance.php?action=holidays', params);
        }
    },
    
    // Service Records APIs
    serviceRecords: {
        async list(params = {}) {
            return API.get('service-records.php?action=list', params);
        },
        
        async myRecords() {
            return API.get('service-records.php?action=my-records');
        },
        
        async get(id) {
            return API.get('service-records.php?action=get', { id });
        },
        
        async create(data) {
            return API.post('service-records.php?action=create', data);
        },
        
        async update(data) {
            return API.post('service-records.php?action=update', data);
        },
        
        async delete(id) {
            return API.post('service-records.php?action=delete', { id });
        },
        
        async getTypes() {
            return API.get('service-records.php?action=types');
        }
    },
    
    // Dashboard APIs
    dashboard: {
        async getAdminStats() {
            return API.get('dashboard.php?action=admin-stats');
        },
        
        async getEmployeeStats() {
            return API.get('dashboard.php?action=employee-stats');
        },
        
        async getNotifications(params = {}) {
            return API.get('dashboard.php?action=notifications', params);
        },
        
        async markNotificationRead(id = null, markAll = false) {
            const data = {};
            if (id !== null && id !== undefined) {
                data.id = id;
            }
            if (markAll) {
                data.mark_all = markAll;
            }
            return API.post('dashboard.php?action=mark-read', data);
        },
        
        async getRecentActivity(limit = 20) {
            return API.get('dashboard.php?action=recent-activity', { limit });
        },
        
        async createTestNotification() {
            return API.get('dashboard.php?action=test-notification');
        }
    },
    
    // Reports APIs
    reports: {
        async getOverview() {
            return API.get('reports.php?action=overview');
        },
        
        async getAttendance(params = {}) {
            return API.get('reports.php?action=attendance', params);
        },
        
        async getLeaves(params = {}) {
            return API.get('reports.php?action=leaves', params);
        },
        
        async getGrievances(params = {}) {
            return API.get('reports.php?action=grievances', params);
        },
        
        async getEmployees() {
            return API.get('reports.php?action=employees');
        },
        
        async getDepartment(departmentId) {
            return API.get('reports.php?action=department', { department_id: departmentId });
        }
    },
    
    // Settings APIs
    settings: {
        async list() {
            return API.get('settings.php?action=list');
        },
        
        async update(key, value) {
            return API.post('settings.php?action=update', { key, value });
        },
        
        async getDepartments() {
            return API.get('settings.php?action=departments');
        },
        
        async manageDepartment(action, data) {
            return API.post('settings.php?action=departments', { action, ...data });
        },
        
        async getDesignations() {
            return API.get('settings.php?action=designations');
        },
        
        async manageDesignation(action, data) {
            return API.post('settings.php?action=designations', { action, ...data });
        },
        
        async getHolidays(year = null) {
            const params = year ? { year } : {};
            return API.get('settings.php?action=holidays', params);
        },
        
        async manageHoliday(action, data) {
            return API.post('settings.php?action=holidays', { action, ...data });
        },
        
        async getActivityLogs(params = {}) {
            return API.get('settings.php?action=activity-logs', params);
        },
        
        async getActiveSessions() {
            return API.get('settings.php?action=sessions');
        },
        
        async terminateSession(sessionId) {
            return API.post('settings.php?action=terminate-session', { session_id: sessionId });
        },
        
        async resetPassword(userId, newPassword) {
            return API.post('settings.php?action=reset-password', { user_id: userId, new_password: newPassword });
        },
        
        async unlockAccount(userId) {
            return API.post('settings.php?action=unlock-account', { user_id: userId });
        }
    }
};

// Export for use in other files
window.API = API;
