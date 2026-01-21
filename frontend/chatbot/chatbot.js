/**
 * e-Karamchari Help Chatbot
 * Assists users with common questions and navigation
 * NO PRIVATE DATA - Only general help
 */

const Chatbot = {
    isOpen: false,
    isTyping: false,
    basePath: '',
    isAdminPage: false,
    
    // Knowledge base for common questions
    knowledge: {
        greetings: [
            "Namaste! 🙏 Main aapka e-Karamchari assistant hoon. Aap mujhse portal ke baare mein kuch bhi pooch sakte hain!",
            "Hello! Main aapki madad ke liye yahan hoon. Kya sawal hai aapka?"
        ],
        
        // Leave related
        leave: {
            keywords: ['leave', 'chutti', 'छुट्टी', 'apply leave', 'leave apply', 'leave balance', 'leave status'],
            response: `**Leave Apply karne ke liye:**
1. Left menu mein "Apply Leave" par click karein
2. Leave type select karein (Casual/Medical/Earned)
3. Start date aur End date choose karein
4. Reason likhein aur Submit karein

**Leave Balance dekhne ke liye:**
Dashboard par aapka current leave balance dikhai deta hai.

**Leave Status check karne ke liye:**
"Leave Status" page par jaayein - wahan pending, approved aur rejected leaves dikhengi.`,
            quickReplies: ['Leave Types', 'Apply Leave', 'Contact HR']
        },
        
        // Attendance
        attendance: {
            keywords: ['attendance', 'hajri', 'हाजिरी', 'check in', 'check out', 'punch', 'present', 'absent'],
            response: `**Attendance dekhne ke liye:**
1. Menu mein "Attendance" par click karein
2. Yahan aapki monthly attendance dikhai degi
3. Present, Absent, Half Day sab status dikh jayega

**Check-In/Check-Out:**
Dashboard par Check-In aur Check-Out buttons hain - daily use karein.`,
            quickReplies: ['Monthly Report', 'Holidays', 'Dashboard']
        },
        
        // Grievance
        grievance: {
            keywords: ['grievance', 'complaint', 'shikayat', 'शिकायत', 'problem', 'issue'],
            response: `**Grievance/Complaint submit karne ke liye:**
1. Menu mein "Submit Grievance" par click karein
2. Category select karein (Salary, Leave, Workplace, etc.)
3. Subject aur detailed description likhein
4. Priority set karein aur Submit karein

**Grievance Status check karne ke liye:**
"Grievance Status" page par jaayein.

**Response Time:** Usually 3-7 din mein response milta hai.`,
            quickReplies: ['Submit Grievance', 'Categories', 'Contact HR']
        },
        
        // Salary
        salary: {
            keywords: ['salary', 'pay', 'slip', 'vetan', 'वेतन', 'payment', 'deduction', 'pf', 'tax'],
            response: `**Salary Slip dekhne ke liye:**
1. Menu mein "Salary Slip" par click karein
2. Month aur Year select karein
3. Download ya Print kar sakte hain

**Salary Components:**
- Basic Pay + Grade Pay
- DA (Dearness Allowance)
- HRA (House Rent Allowance)
- TA (Transport Allowance)

**Deductions:**
- PF (Provident Fund)
- Income Tax (TDS)
- Other deductions`,
            quickReplies: ['Salary Slip', 'PF Details', 'Contact HR']
        },

        // Profile
        profile: {
            keywords: ['profile', 'details', 'update', 'phone', 'address', 'password', 'change password'],
            response: `**Profile dekhne/update karne ke liye:**
1. Top-right corner mein apne naam par click karein
2. "My Profile" select karein
3. Yahan aap phone, address, emergency contact update kar sakte hain

**Password Change karne ke liye:**
1. Profile page par jaayein
2. "Change Password" section mein
3. Current password, New password enter karein
4. Save karein

**Note:** Email aur Employee ID change nahi ho sakte - HR se contact karein.`,
            quickReplies: ['Change Password', 'Contact HR']
        },
        
        // Service Record
        service: {
            keywords: ['service', 'record', 'promotion', 'transfer', 'increment', 'history'],
            response: `**Service Record dekhne ke liye:**
1. Menu mein "Service Record" par click karein
2. Yahan aapki complete service history dikhegi:
   - Promotions
   - Transfers
   - Increments
   - Training records
   - Awards`,
            quickReplies: ['Service Record', 'Contact HR']
        },
        
        // Login Issues
        login: {
            keywords: ['login', 'password forgot', 'cant login', 'locked', 'account'],
            response: `**Login Problems?**

**Agar password bhool gaye:**
- Admin se contact karein password reset ke liye
- HR department ko email karein

**Account Locked hai:**
- 5 baar galat password se account lock ho jata hai
- Admin se unlock karwayein

**Employee ID bhool gaye:**
- HR department se apna Employee ID confirm karein`,
            quickReplies: ['Contact Admin', 'Contact HR']
        },
        
        // Navigation Help
        navigation: {
            keywords: ['how to', 'where', 'kahan', 'kaise', 'find', 'menu', 'page'],
            response: `**Website Navigation:**

**Left Sidebar Menu:**
- 🏠 Dashboard - Overview & Quick Actions
- 📋 Apply Leave - Leave application
- 📊 Leave Status - Check leave requests
- ⏰ Attendance - View attendance
- 💰 Salary Slip - Download pay slips
- 📁 Service Record - Employment history
- 📝 Submit Grievance - File complaints
- 📢 Grievance Status - Track complaints

**Top Right:**
- Profile icon - Your profile & logout`,
            quickReplies: ['Dashboard', 'Apply Leave', 'Salary Slip']
        },
        
        // Admin specific
        admin: {
            keywords: ['employee add', 'add employee', 'approve', 'reject', 'manage', 'admin'],
            adminOnly: true,
            response: `**Admin Functions:**

**Employee Management:**
- All Employees - View/Edit employees
- Add Employee - Register new employee
- Employee Services - Promotion/Transfer/etc.

**Approvals:**
- Leave Requests - Approve/Reject leaves
- Grievances - Handle complaints

**Reports:**
- Attendance Reports
- Department wise reports`,
            quickReplies: ['Add Employee', 'Leave Approvals', 'Reports'],
            employeeResponse: `⚠️ Yeh admin functions hain jo sirf administrators ke liye available hain.

**Aap employee ke taur par yeh kar sakte hain:**
• Leave apply karein
• Attendance dekhein
• Salary slip download karein
• Grievance submit karein

Kya main in mein se kisi mein madad kar sakta hoon?`,
            employeeQuickReplies: ['Apply Leave', 'Salary Slip', 'Contact HR']
        },
        
        // Contact
        contact: {
            keywords: ['contact', 'help', 'support', 'hr', 'admin', 'phone', 'email'],
            response: `**Contact Information:**

**HR Department:**
📧 hr@mcd.gov.in
📞 011-XXXXXXXX

**IT Support:**
📧 itsupport@mcd.gov.in
📞 011-XXXXXXXX

**Working Hours:**
Monday - Friday: 9:00 AM - 5:30 PM
Saturday: 9:00 AM - 1:00 PM`,
            quickReplies: ['HR Email', 'IT Support']
        },
        
        // Default/Fallback
        default: {
            response: `Main samajh nahi paaya. Kya aap in topics mein se kisi ke baare mein jaanna chahte hain?

• Leave Apply/Status
• Attendance
• Salary Slip
• Grievance/Complaint
• Profile Update
• Service Record
• Login Problems

Ya specific question poochein!`,
            quickReplies: ['Leave Help', 'Salary Help', 'Attendance', 'Contact HR']
        }
    },
    
    // Initialize chatbot
    init() {
        const path = window.location.pathname;
        if (path.includes('/admin/') || path.includes('/employee/')) {
            this.basePath = '../frontend/chatbot/';
        } else {
            this.basePath = 'frontend/chatbot/';
        }
        
        this.isAdminPage = path.includes('/admin/') || path.includes('admin-');
        
        this.createChatbotHTML();
        this.attachEventListeners();
        
        setTimeout(() => {
            this.showWelcomeMessage();
        }, 1500);
    },

    // Create chatbot HTML
    createChatbotHTML() {
        const chatbotHTML = `
            <div class="chatbot-container" id="chatbot">
                <button class="chatbot-toggle" id="chatbot-toggle" title="Help Assistant">
                    <span class="chatbot-toggle-icon">💬</span>
                    <span class="chatbot-badge" id="chatbot-badge" style="display: none;">1</span>
                </button>
                
                <div class="chatbot-window" id="chatbot-window">
                    <div class="chatbot-header">
                        <div class="chatbot-avatar">🤖</div>
                        <div class="chatbot-info">
                            <h3>e-Karamchari Assistant</h3>
                            <p class="chatbot-status">Online - Ready to help</p>
                        </div>
                        <button class="chatbot-close" id="chatbot-close">&times;</button>
                    </div>
                    
                    <div class="chatbot-messages" id="chatbot-messages"></div>
                    
                    <div class="chatbot-input">
                        <input type="text" id="chatbot-input" placeholder="Type your question..." autocomplete="off">
                        <button id="chatbot-send" title="Send">➤</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', chatbotHTML);
    },
    
    // Attach event listeners
    attachEventListeners() {
        const toggle = document.getElementById('chatbot-toggle');
        const close = document.getElementById('chatbot-close');
        const input = document.getElementById('chatbot-input');
        const send = document.getElementById('chatbot-send');
        
        toggle.addEventListener('click', () => this.toggleChat());
        close.addEventListener('click', () => this.toggleChat());
        
        send.addEventListener('click', () => this.sendMessage());
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });
        
        document.getElementById('chatbot-messages').addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-reply-btn')) {
                this.handleQuickReply(e.target.textContent);
            }
        });
    },
    
    // Toggle chat window
    toggleChat() {
        const chatWindow = document.getElementById('chatbot-window');
        const toggle = document.getElementById('chatbot-toggle');
        const badge = document.getElementById('chatbot-badge');
        
        this.isOpen = !this.isOpen;
        chatWindow.classList.toggle('active', this.isOpen);
        toggle.classList.toggle('active', this.isOpen);
        
        if (this.isOpen) {
            badge.style.display = 'none';
            document.getElementById('chatbot-input').focus();
        }
    },
    
    // Show welcome message
    showWelcomeMessage() {
        const messages = document.getElementById('chatbot-messages');
        if (messages && messages.children.length === 0) {
            const greeting = this.knowledge.greetings[Math.floor(Math.random() * this.knowledge.greetings.length)];
            this.addBotMessage(greeting, ['Leave Help', 'Salary Slip', 'Grievance', 'Attendance']);
            
            if (!this.isOpen) {
                document.getElementById('chatbot-badge').style.display = 'flex';
            }
        }
    },
    
    // Send user message
    sendMessage() {
        const input = document.getElementById('chatbot-input');
        const message = input.value.trim();
        
        if (!message || this.isTyping) return;
        
        this.addUserMessage(message);
        input.value = '';
        
        this.showTyping();
        setTimeout(() => {
            this.hideTyping();
            this.processMessage(message);
        }, 800 + Math.random() * 700);
    },
    
    // Handle quick reply
    handleQuickReply(text) {
        document.getElementById('chatbot-input').value = text;
        this.sendMessage();
    },
    
    // Process message
    processMessage(message) {
        const lowerMessage = message.toLowerCase();
        let response = null;
        let quickReplies = [];
        
        // Check knowledge base
        for (const [key, data] of Object.entries(this.knowledge)) {
            if (key === 'greetings' || key === 'default') continue;
            
            if (data.keywords && data.keywords.some(kw => lowerMessage.includes(kw))) {
                if (data.adminOnly && !this.isAdminPage) {
                    response = data.employeeResponse || this.knowledge.default.response;
                    quickReplies = data.employeeQuickReplies || this.knowledge.default.quickReplies;
                } else {
                    response = data.response;
                    quickReplies = data.quickReplies || [];
                }
                break;
            }
        }
        
        // Check greetings
        if (!response && /^(hi|hello|hey|namaste|namaskar|hii+)/.test(lowerMessage)) {
            response = this.knowledge.greetings[Math.floor(Math.random() * this.knowledge.greetings.length)];
            quickReplies = ['Leave Help', 'Salary Help', 'Grievance', 'Contact HR'];
        }
        
        // Check thanks
        if (!response && /(thank|thanks|dhanyawad|shukriya)/.test(lowerMessage)) {
            response = "Aapka swagat hai! 😊 Agar aur koi sawal ho to zaroor poochein.";
            quickReplies = ['More Help', 'Contact HR'];
        }
        
        // Default response
        if (!response) {
            response = this.knowledge.default.response;
            quickReplies = this.knowledge.default.quickReplies;
        }
        
        this.addBotMessage(response, quickReplies);
    },

    // Add user message
    addUserMessage(text) {
        const messages = document.getElementById('chatbot-messages');
        const messageHTML = `
            <div class="chat-message user">
                <div class="message-avatar">👤</div>
                <div class="message-content">${this.escapeHtml(text)}</div>
            </div>
        `;
        messages.insertAdjacentHTML('beforeend', messageHTML);
        this.scrollToBottom();
    },
    
    // Add bot message
    addBotMessage(text, quickReplies = []) {
        const messages = document.getElementById('chatbot-messages');
        let formattedText = this.formatMessage(text);
        
        let quickRepliesHTML = '';
        if (quickReplies.length > 0) {
            quickRepliesHTML = `
                <div class="quick-replies">
                    ${quickReplies.map(qr => `<button class="quick-reply-btn">${qr}</button>`).join('')}
                </div>
            `;
        }
        
        const messageHTML = `
            <div class="chat-message bot">
                <div class="message-avatar">🤖</div>
                <div class="message-content">
                    ${formattedText}
                    ${quickRepliesHTML}
                </div>
            </div>
        `;
        messages.insertAdjacentHTML('beforeend', messageHTML);
        this.scrollToBottom();
    },
    
    // Show typing indicator
    showTyping() {
        this.isTyping = true;
        const messages = document.getElementById('chatbot-messages');
        const typingHTML = `
            <div class="chat-message bot" id="typing-indicator">
                <div class="message-avatar">🤖</div>
                <div class="typing-indicator">
                    <span></span><span></span><span></span>
                </div>
            </div>
        `;
        messages.insertAdjacentHTML('beforeend', typingHTML);
        this.scrollToBottom();
    },
    
    // Hide typing indicator
    hideTyping() {
        this.isTyping = false;
        const typing = document.getElementById('typing-indicator');
        if (typing) typing.remove();
    },
    
    // Format message
    formatMessage(text) {
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    },
    
    // Escape HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    // Scroll to bottom
    scrollToBottom() {
        const messages = document.getElementById('chatbot-messages');
        messages.scrollTop = messages.scrollHeight;
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname;
    if (!path.includes('login') && !path.includes('register') && !path.includes('session-expired') && !path.includes('unauthorized')) {
        Chatbot.init();
    }
});

window.Chatbot = Chatbot;
