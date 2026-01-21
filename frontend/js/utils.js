/**
 * e-Karamchari Utility Functions
 * Common helper functions used across the application
 */

const Utils = {
    /**
     * Format date to readable string
     */
    formatDate(dateString, format = 'short') {
        if (!dateString) return '-';
        
        const date = new Date(dateString);
        const options = {
            short: { day: '2-digit', month: 'short', year: 'numeric' },
            long: { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' },
            time: { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
        };
        
        return date.toLocaleDateString('en-IN', options[format] || options.short);
    },
    
    /**
     * Format time
     */
    formatTime(timeString) {
        if (!timeString) return '-';
        
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        
        return `${hour12}:${minutes} ${ampm}`;
    },
    
    /**
     * Get relative time (e.g., "2 hours ago")
     */
    getRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        
        if (days > 7) {
            return this.formatDate(dateString);
        } else if (days > 0) {
            return `${days} day${days > 1 ? 's' : ''} ago`;
        } else if (hours > 0) {
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else if (minutes > 0) {
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else {
            return 'Just now';
        }
    },
    
    /**
     * Get status badge HTML
     */
    getStatusBadge(status) {
        const statusMap = {
            'Pending': 'warning',
            'Approved': 'success',
            'Rejected': 'danger',
            'Cancelled': 'secondary',
            'Open': 'info',
            'In Progress': 'warning',
            'Resolved': 'success',
            'Closed': 'secondary',
            'Reopened': 'warning',
            'Present': 'success',
            'Absent': 'danger',
            'Half Day': 'warning',
            'On Leave': 'info',
            'Holiday': 'primary',
            'Weekend': 'secondary',
            'Active': 'success',
            'Inactive': 'danger',
            'Low': 'info',
            'Medium': 'warning',
            'High': 'danger',
            'Critical': 'danger'
        };
        
        const badgeClass = statusMap[status] || 'secondary';
        return `<span class="badge badge-${badgeClass}">${status}</span>`;
    },
    
    /**
     * Get priority badge HTML
     */
    getPriorityBadge(priority) {
        const priorityMap = {
            'Low': { class: 'info', icon: '▽' },
            'Medium': { class: 'warning', icon: '◇' },
            'High': { class: 'danger', icon: '△' },
            'Critical': { class: 'danger', icon: '⚠' }
        };
        
        const config = priorityMap[priority] || priorityMap['Medium'];
        return `<span class="badge badge-${config.class}">${config.icon} ${priority}</span>`;
    },
    
    /**
     * Get initials from name
     */
    getInitials(name) {
        if (!name) return '?';
        
        const parts = name.trim().split(' ');
        if (parts.length === 1) {
            return parts[0].charAt(0).toUpperCase();
        }
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    },
    
    /**
     * Truncate text
     */
    truncate(text, length = 50) {
        if (!text || text.length <= length) return text;
        return text.substring(0, length) + '...';
    },
    
    /**
     * Escape HTML - Enhanced XSS protection
     */
    escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    },

    /**
     * Sanitize URL to prevent javascript: protocol
     */
    sanitizeUrl(url) {
        if (!url) return '';
        const trimmed = url.trim().toLowerCase();
        if (trimmed.startsWith('javascript:') || trimmed.startsWith('data:') || trimmed.startsWith('vbscript:')) {
            return '';
        }
        return url;
    },
    
    /**
     * Debounce function
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    /**
     * Show loading overlay
     */
    showLoading() {
        let overlay = document.getElementById('loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    },
    
    /**
     * Hide loading overlay
     */
    hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    },
    
    /**
     * Show toast notification
     */
    showToast(message, type = 'info', duration = 3000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${this.escapeHtml(message)}</span>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    
    /**
     * Show confirmation modal
     */
    showConfirm(title, message, onConfirm, onCancel = null) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        
        overlay.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3>${this.escapeHtml(title)}</h3>
                    <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <p>${this.escapeHtml(message)}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="confirm-cancel">Cancel</button>
                    <button class="btn btn-primary" id="confirm-ok">Confirm</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        overlay.querySelector('#confirm-ok').addEventListener('click', () => {
            overlay.remove();
            if (onConfirm) onConfirm();
        });
        
        overlay.querySelector('#confirm-cancel').addEventListener('click', () => {
            overlay.remove();
            if (onCancel) onCancel();
        });
        
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.remove();
                if (onCancel) onCancel();
            }
        });
    },
    
    /**
     * Show alert modal
     */
    showAlert(title, message, type = 'info') {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        overlay.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3>${this.escapeHtml(title)}</h3>
                    <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                </div>
                <div class="modal-body text-center">
                    <div class="error-icon ${type}" style="font-size: 3rem; margin-bottom: 1rem;">
                        ${icons[type] || icons.info}
                    </div>
                    <p>${this.escapeHtml(message)}</p>
                </div>
                <div class="modal-footer justify-center">
                    <button class="btn btn-primary" onclick="this.closest('.modal-overlay').remove()">OK</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
    },
    
    /**
     * Validate form
     */
    validateForm(formElement) {
        const inputs = formElement.querySelectorAll('[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            const value = input.value.trim();
            const errorElement = input.parentElement.querySelector('.form-error');
            
            if (!value) {
                isValid = false;
                input.classList.add('error');
                if (errorElement) {
                    errorElement.textContent = 'This field is required';
                }
            } else {
                input.classList.remove('error');
                if (errorElement) {
                    errorElement.textContent = '';
                }
            }
            
            // Email validation
            if (input.type === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    input.classList.add('error');
                    if (errorElement) {
                        errorElement.textContent = 'Please enter a valid email';
                    }
                }
            }
        });
        
        return isValid;
    },
    
    /**
     * Generate pagination HTML
     */
    generatePagination(currentPage, totalPages, onPageChange) {
        if (totalPages <= 1) return '';
        
        let html = '<div class="pagination">';
        
        // Previous button
        html += `<button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} 
                 onclick="(${onPageChange})(${currentPage - 1})">‹ Prev</button>`;
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            html += `<button class="pagination-btn" onclick="(${onPageChange})(1)">1</button>`;
            if (startPage > 2) {
                html += '<span class="pagination-ellipsis">...</span>';
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" 
                     onclick="(${onPageChange})(${i})">${i}</button>`;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += '<span class="pagination-ellipsis">...</span>';
            }
            html += `<button class="pagination-btn" onclick="(${onPageChange})(${totalPages})">${totalPages}</button>`;
        }
        
        // Next button
        html += `<button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} 
                 onclick="(${onPageChange})(${currentPage + 1})">Next ›</button>`;
        
        html += '</div>';
        
        return html;
    },
    
    /**
     * Store data in session storage
     */
    setSession(key, value) {
        sessionStorage.setItem(key, JSON.stringify(value));
    },
    
    /**
     * Get data from session storage
     */
    getSession(key) {
        const value = sessionStorage.getItem(key);
        return value ? JSON.parse(value) : null;
    },
    
    /**
     * Remove data from session storage
     */
    removeSession(key) {
        sessionStorage.removeItem(key);
    },
    
    /**
     * Clear all session storage
     */
    clearSession() {
        sessionStorage.clear();
    },
    
    /**
     * Get URL parameter
     */
    getUrlParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    },
    
    /**
     * Format number with commas
     */
    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },
    
    /**
     * Calculate days between dates
     */
    daysBetween(date1, date2) {
        // Parse dates properly - handle both YYYY-MM-DD and other formats
        let d1, d2;
        
        if (typeof date1 === 'string' && date1.includes('-')) {
            // YYYY-MM-DD format from input[type=date]
            const [y1, m1, day1] = date1.split('-').map(Number);
            d1 = new Date(y1, m1 - 1, day1);
        } else {
            d1 = new Date(date1);
        }
        
        if (typeof date2 === 'string' && date2.includes('-')) {
            const [y2, m2, day2] = date2.split('-').map(Number);
            d2 = new Date(y2, m2 - 1, day2);
        } else {
            d2 = new Date(date2);
        }
        
        const diffTime = Math.abs(d2 - d1);
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    },
    
    /**
     * Get current date in YYYY-MM-DD format
     */
    getCurrentDate() {
        return new Date().toISOString().split('T')[0];
    },
    
    /**
     * Get month name
     */
    getMonthName(monthNumber) {
        const months = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
        return months[monthNumber - 1] || '';
    },

    /**
     * Validate phone number (Indian format)
     */
    validatePhone(phone) {
        const phoneRegex = /^[6-9]\d{9}$/;
        return phoneRegex.test(phone.replace(/\s/g, ''));
    },

    /**
     * Validate email
     */
    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },

    /**
     * Validate password strength
     */
    validatePassword(password) {
        const result = {
            isValid: false,
            strength: 'weak',
            message: ''
        };
        
        if (password.length < 8) {
            result.message = 'Password must be at least 8 characters';
            return result;
        }
        
        let strength = 0;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        if (strength < 2) {
            result.strength = 'weak';
            result.message = 'Add uppercase, numbers or special characters';
        } else if (strength < 3) {
            result.strength = 'medium';
            result.message = 'Good, but could be stronger';
            result.isValid = true;
        } else {
            result.strength = 'strong';
            result.message = 'Strong password';
            result.isValid = true;
        }
        
        return result;
    },

    /**
     * Validate date range (end date should be after start date)
     */
    validateDateRange(startDate, endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        return end >= start;
    },

    /**
     * Check for overlapping dates
     */
    checkDateOverlap(newStart, newEnd, existingRanges) {
        const start = new Date(newStart);
        const end = new Date(newEnd);
        
        for (const range of existingRanges) {
            const existStart = new Date(range.start);
            const existEnd = new Date(range.end);
            
            if (start <= existEnd && end >= existStart) {
                return true; // Overlap found
            }
        }
        return false;
    },

    /**
     * Add ARIA attributes for accessibility
     */
    addAriaLabel(element, label) {
        if (element) {
            element.setAttribute('aria-label', label);
        }
    },

    /**
     * Setup keyboard navigation for modal
     */
    setupModalKeyboard(modalElement) {
        if (!modalElement) return;
        
        const focusableElements = modalElement.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        modalElement.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                modalElement.remove();
                return;
            }
            
            if (e.key === 'Tab') {
                if (e.shiftKey && document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                } else if (!e.shiftKey && document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        });
        
        if (firstElement) firstElement.focus();
    },

    /**
     * Format currency (Indian Rupees)
     */
    formatCurrency(amount) {
        return '₹' + parseFloat(amount || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    /**
     * Handle API errors consistently
     */
    handleApiError(error, defaultMessage = 'An error occurred') {
        console.error('API Error:', error);
        
        if (error.message === 'Failed to fetch') {
            return 'Unable to connect to server. Please check your connection.';
        }
        
        if (error.status === 401) {
            return 'Session expired. Please login again.';
        }
        
        if (error.status === 403) {
            return 'You do not have permission to perform this action.';
        }
        
        if (error.status === 404) {
            return 'The requested resource was not found.';
        }
        
        if (error.status >= 500) {
            return 'Server error. Please try again later.';
        }
        
        return error.message || defaultMessage;
    }
};

// Export for use in other files
window.Utils = Utils;
