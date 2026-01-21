/**
 * Admin Employee Services Page
 * Handles employee service actions like Promotion, Transfer, Increment, etc.
 */

let selectedEmployee = null;
let departments = [];
let designations = [];

document.addEventListener('DOMContentLoaded', async function() {
    await Auth.requireAdmin();
    setupUI();
    loadUserInfo();
    loadEmployees();
    loadDepartments();
    loadDesignations();
    setupSearch();
    
    // Set default date to today
    document.getElementById('effective-date').value = Utils.getCurrentDate();
});

function setupUI() {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });
    
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });
    
    // User dropdown
    const userToggle = document.getElementById('user-dropdown-toggle');
    const userMenu = document.getElementById('user-dropdown-menu');
    
    userToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('active');
    });
    
    document.addEventListener('click', () => {
        userMenu.classList.remove('active');
    });
}

async function loadUserInfo() {
    let user = Utils.getSession('user');
    
    // If user not in session, try to fetch from API
    if (!user) {
        try {
            const response = await API.auth.getCurrentUser();
            if (response.success) {
                user = response.data;
                Utils.setSession('user', user);
            }
        } catch (error) {
            console.error('Error fetching user info:', error);
        }
    }
    
    if (user) {
        const fullName = user.name || `${user.first_name} ${user.last_name}`;
        document.getElementById('user-name').textContent = fullName;
        document.getElementById('user-avatar').textContent = Utils.getInitials(fullName);
    }
}

async function loadEmployees() {
    try {
        const response = await API.employees.list({ limit: 500, status: 1 });
        if (response.success) {
            const select = document.getElementById('employee-select');
            select.innerHTML = '<option value="">-- Select Employee --</option>';
            
            response.data.employees.forEach(emp => {
                const option = document.createElement('option');
                option.value = emp.id;
                option.textContent = `${emp.employee_id} - ${emp.first_name} ${emp.last_name}`;
                option.dataset.employee = JSON.stringify(emp);
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

async function loadDepartments() {
    try {
        const response = await API.settings.getDepartments();
        if (response.success) {
            departments = response.data;
            const select = document.getElementById('new-department');
            select.innerHTML = '<option value="">Select New Department</option>';
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.dept_name;
                select.appendChild(option);
            });
            
            // Add "Other" option at the end
            const otherOption = document.createElement('option');
            otherOption.value = 'other';
            otherOption.textContent = 'Other';
            select.appendChild(otherOption);
            
            // Setup change listener for "Other" option
            select.addEventListener('change', handleDepartmentChange);
        }
    } catch (error) {
        console.error('Error loading departments:', error);
    }
}

/**
 * Handle department dropdown change - show/hide other input field
 */
function handleDepartmentChange() {
    const select = document.getElementById('new-department');
    let otherInput = document.getElementById('other-department-input');
    
    if (select.value === 'other') {
        // Show other department input
        if (!otherInput) {
            const inputGroup = document.createElement('div');
            inputGroup.className = 'form-group';
            inputGroup.id = 'other-department-group';
            inputGroup.innerHTML = `
                <label class="form-label required">Enter Department Name</label>
                <input type="text" class="form-control" id="other-department-input" name="other_department" required placeholder="Enter new department name">
            `;
            select.parentElement.after(inputGroup);
        }
    } else {
        // Hide other department input
        const otherGroup = document.getElementById('other-department-group');
        if (otherGroup) {
            otherGroup.remove();
        }
    }
}

async function loadDesignations() {
    try {
        const response = await API.settings.getDesignations();
        if (response.success) {
            designations = response.data;
            const select = document.getElementById('new-designation');
            select.innerHTML = '<option value="">Select New Designation</option>';
            designations.forEach(des => {
                const option = document.createElement('option');
                option.value = des.id;
                option.textContent = des.designation_name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading designations:', error);
    }
}

function setupSearch() {
    const searchInput = document.getElementById('employee-search');
    const select = document.getElementById('employee-select');
    
    searchInput.addEventListener('input', Utils.debounce(function() {
        const searchTerm = this.value.trim().toUpperCase();
        
        if (!searchTerm) {
            // Reset to show all
            Array.from(select.options).forEach(opt => {
                opt.style.display = '';
            });
            return;
        }
        
        // Filter options
        let found = false;
        Array.from(select.options).forEach(opt => {
            if (opt.value === '') {
                opt.style.display = '';
                return;
            }
            
            const empData = opt.dataset.employee ? JSON.parse(opt.dataset.employee) : null;
            if (empData && empData.employee_id.toUpperCase().includes(searchTerm)) {
                opt.style.display = '';
                if (!found) {
                    select.value = opt.value;
                    onEmployeeSelect();
                    found = true;
                }
            } else {
                opt.style.display = 'none';
            }
        });
    }, 300));
}

function onEmployeeSelect() {
    const select = document.getElementById('employee-select');
    const selectedOption = select.options[select.selectedIndex];
    const employeeInfo = document.getElementById('employee-info');
    const serviceActions = document.getElementById('service-actions');
    
    if (!select.value) {
        employeeInfo.classList.add('hidden');
        serviceActions.classList.add('hidden');
        selectedEmployee = null;
        return;
    }
    
    selectedEmployee = JSON.parse(selectedOption.dataset.employee);
    
    const fullName = `${selectedEmployee.first_name} ${selectedEmployee.last_name}`;
    document.getElementById('emp-avatar').textContent = Utils.getInitials(fullName);
    document.getElementById('emp-name').textContent = fullName;
    document.getElementById('emp-designation').textContent = selectedEmployee.designation_name || 'N/A';
    document.getElementById('emp-department').textContent = selectedEmployee.dept_name || 'N/A';
    document.getElementById('emp-id').textContent = selectedEmployee.employee_id;
    document.getElementById('emp-email').textContent = selectedEmployee.email || 'N/A';
    document.getElementById('emp-joining').textContent = selectedEmployee.date_of_joining ? Utils.formatDate(selectedEmployee.date_of_joining) : 'N/A';
    
    employeeInfo.classList.remove('hidden');
    serviceActions.classList.remove('hidden');
}

function openServiceModal(type) {
    if (!selectedEmployee) {
        Utils.showToast('Please select an employee first', 'error');
        return;
    }
    
    document.getElementById('service-form').reset();
    document.getElementById('service-type').value = type;
    document.getElementById('effective-date').value = Utils.getCurrentDate();
    
    // Set modal title
    const titles = {
        'Promotion': '📈 Promotion',
        'Demotion': '📉 Demotion',
        'Transfer': '🔄 Transfer',
        'Increment': '💰 Increment',
        'Training': '📚 Training',
        'Award': '🏆 Award',
        'Disciplinary': '⚠️ Disciplinary Action'
    };
    document.getElementById('modal-title').textContent = titles[type] || type;
    
    // Show/hide relevant fields
    const showDesignation = ['Promotion', 'Demotion'].includes(type);
    const showDepartment = ['Transfer'].includes(type);
    
    if (showDesignation) {
        document.getElementById('designation-fields').classList.remove('hidden');
        // Filter designations based on type (Promotion = higher, Demotion = lower)
        filterDesignationsForAction(type);
    } else {
        document.getElementById('designation-fields').classList.add('hidden');
    }
    
    if (showDepartment) {
        document.getElementById('department-fields').classList.remove('hidden');
    } else {
        document.getElementById('department-fields').classList.add('hidden');
    }
    
    // Set placeholder based on type
    const placeholders = {
        'Promotion': 'e.g., Promoted to Senior Manager',
        'Demotion': 'e.g., Demoted to Junior Clerk',
        'Transfer': 'e.g., Transferred to Finance Department',
        'Increment': 'e.g., Annual Increment 2024',
        'Training': 'e.g., Completed Leadership Training',
        'Award': 'e.g., Best Employee Award 2024',
        'Disciplinary': 'e.g., Warning for misconduct'
    };
    document.getElementById('service-title').placeholder = placeholders[type] || 'Enter title';
    
    document.getElementById('service-modal').classList.add('active');
}

/**
 * Filter designations based on action type
 * Promotion: Show only designations with higher grade_pay than current
 * Demotion: Show only designations with lower grade_pay than current
 */
function filterDesignationsForAction(actionType) {
    const select = document.getElementById('new-designation');
    select.innerHTML = '<option value="">Select New Designation</option>';
    
    if (!selectedEmployee || !designations.length) {
        // Add Other option
        const otherOption = document.createElement('option');
        otherOption.value = 'other';
        otherOption.textContent = 'Other';
        select.appendChild(otherOption);
        return;
    }
    
    // Find current employee's designation grade_pay
    const currentDesignation = designations.find(d => d.id == selectedEmployee.designation_id);
    const currentGradePay = currentDesignation ? parseFloat(currentDesignation.grade_pay) : 0;
    
    // Filter designations based on action type
    let filteredDesignations = [];
    
    if (actionType === 'Promotion') {
        // Show only higher grade_pay designations
        filteredDesignations = designations.filter(d => parseFloat(d.grade_pay) > currentGradePay);
        // Sort by grade_pay ascending (lowest higher position first)
        filteredDesignations.sort((a, b) => parseFloat(a.grade_pay) - parseFloat(b.grade_pay));
    } else if (actionType === 'Demotion') {
        // Show only lower grade_pay designations
        filteredDesignations = designations.filter(d => parseFloat(d.grade_pay) < currentGradePay);
        // Sort by grade_pay descending (highest lower position first)
        filteredDesignations.sort((a, b) => parseFloat(b.grade_pay) - parseFloat(a.grade_pay));
    }
    
    filteredDesignations.forEach(des => {
        const option = document.createElement('option');
        option.value = des.id;
        option.textContent = des.designation_name;
        select.appendChild(option);
    });
    
    // Always add "Other" option at the end
    const otherOption = document.createElement('option');
    otherOption.value = 'other';
    otherOption.textContent = 'Other';
    select.appendChild(otherOption);
    
    // Setup change listener for "Other" option
    select.removeEventListener('change', handleDesignationChange);
    select.addEventListener('change', handleDesignationChange);
}

/**
 * Handle designation dropdown change - show/hide other input field
 */
function handleDesignationChange() {
    const select = document.getElementById('new-designation');
    let otherInput = document.getElementById('other-designation-input');
    
    if (select.value === 'other') {
        // Show other designation input
        if (!otherInput) {
            const inputGroup = document.createElement('div');
            inputGroup.className = 'form-group';
            inputGroup.id = 'other-designation-group';
            inputGroup.innerHTML = `
                <label class="form-label required">Enter Designation Name</label>
                <input type="text" class="form-control" id="other-designation-input" name="other_designation" required placeholder="Enter new designation name">
            `;
            select.parentElement.after(inputGroup);
        }
    } else {
        // Hide other designation input
        const otherGroup = document.getElementById('other-designation-group');
        if (otherGroup) {
            otherGroup.remove();
        }
    }
}

function closeModal() {
    document.getElementById('service-modal').classList.remove('active');
    
    // Remove any "Other" input fields
    const otherDesignationGroup = document.getElementById('other-designation-group');
    if (otherDesignationGroup) {
        otherDesignationGroup.remove();
    }
    
    const otherDepartmentGroup = document.getElementById('other-department-group');
    if (otherDepartmentGroup) {
        otherDepartmentGroup.remove();
    }
}

async function saveService() {
    const form = document.getElementById('service-form');
    const formData = new FormData(form);
    
    // Handle "Other" designation
    let newDesignationId = formData.get('new_designation_id');
    let otherDesignation = null;
    if (newDesignationId === 'other') {
        otherDesignation = document.getElementById('other-designation-input')?.value?.trim();
        if (!otherDesignation) {
            Utils.showToast('Please enter the designation name', 'error');
            return;
        }
        newDesignationId = null;
    }
    
    // Handle "Other" department
    let newDepartmentId = formData.get('new_department_id');
    let otherDepartment = null;
    if (newDepartmentId === 'other') {
        otherDepartment = document.getElementById('other-department-input')?.value?.trim();
        if (!otherDepartment) {
            Utils.showToast('Please enter the department name', 'error');
            return;
        }
        newDepartmentId = null;
    }
    
    const data = {
        employee_id: selectedEmployee.id,
        record_type: formData.get('record_type'),
        title: formData.get('title'),
        description: formData.get('description'),
        effective_date: formData.get('effective_date'),
        new_designation_id: newDesignationId || null,
        new_department_id: newDepartmentId || null,
        other_designation: otherDesignation,
        other_department: otherDepartment
    };
    
    if (!data.title || !data.effective_date) {
        Utils.showToast('Please fill required fields', 'error');
        return;
    }
    
    try {
        Utils.showLoading();
        const response = await API.serviceRecords.create(data);
        Utils.hideLoading();
        
        if (response.success) {
            Utils.showToast('Service record saved successfully!', 'success');
            closeModal();
            
            // If designation or department changed, reload employees
            if (data.new_designation_id || data.new_department_id || data.other_designation || data.other_department) {
                loadEmployees();
                document.getElementById('employee-select').value = '';
                document.getElementById('employee-info').classList.add('hidden');
                document.getElementById('service-actions').classList.add('hidden');
                selectedEmployee = null;
            }
        } else {
            Utils.showToast(response.message || 'Error saving record', 'error');
        }
    } catch (error) {
        Utils.hideLoading();
        Utils.showToast('Error saving record', 'error');
        console.error('Error:', error);
    }
}
