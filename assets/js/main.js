// Main JavaScript file for CineVault

document.addEventListener('DOMContentLoaded', function() {
    // Initialize forms
    initAuthForms();
    initPaymentForm();
    initModal();
    initInputFormatting();
});

// Auth Forms Handler
function initAuthForms() {
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
    
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
}

// Handle Registration
async function handleRegister(e) {
    e.preventDefault();
    
    const formData = {
        username: document.getElementById('username').value.trim(),
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value,
        confirm_password: document.getElementById('confirm_password').value
    };
    
    // Client-side validation
    if (formData.username.length < 3) {
        showMessage('Username must be at least 3 characters', 'error');
        return;
    }
    
    if (!isValidEmail(formData.email)) {
        showMessage('Please enter a valid email address', 'error');
        return;
    }
    
    if (formData.password.length < 6) {
        showMessage('Password must be at least 6 characters', 'error');
        return;
    }
    
    if (formData.password !== formData.confirm_password) {
        showMessage('Passwords do not match', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Creating account...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('../api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Account created successfully! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = 'plans.php';
            }, 1500);
        } else {
            showMessage(data.message, 'error');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    } catch (error) {
        showMessage('Connection error. Please try again.', 'error');
        console.error('Error:', error);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

// Handle Login
async function handleLogin(e) {
    e.preventDefault();
    
    const formData = {
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value
    };
    
    if (!formData.email || !formData.password) {
        showMessage('Please enter both email and password', 'error');
        return;
    }
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Signing in...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('../api/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Login successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1500);
        } else {
            showMessage(data.message, 'error');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    } catch (error) {
        showMessage('Connection error. Please try again.', 'error');
        console.error('Error:', error);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

// Plan Selection
function selectPlan(planId) {
    const planCards = document.querySelectorAll('.plan-card');
    let selectedPlan = null;
    
    planCards.forEach(card => {
        const planData = JSON.parse(card.dataset.plan);
        if (planData.id === planId) {
            selectedPlan = planData;
        }
    });
    
    if (selectedPlan) {
        document.getElementById('plan_id').value = planId;
        
        const preview = document.getElementById('selectedPlanPreview');
        preview.innerHTML = `
            <div class="selected-plan">
                <h3>${selectedPlan.plan_name} Plan</h3>
                <p class="selected-price">₱${selectedPlan.monthly_price.toFixed(2)}/month after trial</p>
                <p class="selected-quality">${selectedPlan.video_quality} • ${selectedPlan.resolution}</p>
            </div>
        `;
        
        openModal();
    }
}

// Payment Form Handler
function initPaymentForm() {
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', handlePayment);
    }
}

async function handlePayment(e) {
    e.preventDefault();
    
    const planId = document.getElementById('plan_id').value;
    
    if (!planId) {
        showMessage('Please select a plan', 'error');
        return;
    }
    
    // Get payment details
    const paymentData = {
        plan_id: planId,
        card_number: document.getElementById('card_number').value.replace(/\s/g, ''),
        expiry: document.getElementById('expiry').value,
        cvv: document.getElementById('cvv').value,
        card_name: document.getElementById('card_name').value
    };
    
    // Validate card
    if (!validateCard(paymentData.card_number)) {
        showMessage('Please enter a valid card number', 'error');
        return;
    }
    
    if (!validateExpiry(paymentData.expiry)) {
        showMessage('Please enter a valid expiry date (MM/YY)', 'error');
        return;
    }
    
    if (!validateCVV(paymentData.cvv)) {
        showMessage('Please enter a valid CVV', 'error');
        return;
    }
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Processing...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('../api/subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(paymentData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Welcome to CineVault! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1500);
        } else {
            showMessage(data.message, 'error');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    } catch (error) {
        showMessage('Payment processing error. Please try again.', 'error');
        console.error('Error:', error);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

// Modal Functions
function initModal() {
    const modal = document.getElementById('paymentModal');
    const closeBtn = document.querySelector('.close-modal');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    
    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            closeModal();
        }
    });
}

function openModal() {
    const modal = document.getElementById('paymentModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal() {
    const modal = document.getElementById('paymentModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Input Formatting
function initInputFormatting() {
    // Card number formatting
    const cardInput = document.getElementById('card_number');
    if (cardInput) {
        cardInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = value.match(new RegExp('.{1,4}', 'g')).join(' ');
            }
            e.target.value = value;
        });
    }
    
    // Expiry date formatting
    const expiryInput = document.getElementById('expiry');
    if (expiryInput) {
        expiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });
    }
    
    // CVV - numbers only
    const cvvInput = document.getElementById('cvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }
}

// Validation Helpers
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validateCard(cardNumber) {
    const re = /^\d{16}$/;
    return re.test(cardNumber);
}

function validateExpiry(expiry) {
    const re = /^(0[1-9]|1[0-2])\/([0-9]{2})$/;
    if (!re.test(expiry)) return false;
    
    const [month, year] = expiry.split('/');
    const expiryDate = new Date(2000 + parseInt(year), parseInt(month) - 1);
    const today = new Date();
    
    return expiryDate > today;
}

function validateCVV(cvv) {
    const re = /^\d{3,4}$/;
    return re.test(cvv);
}

// Message Display
function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    if (!container) return;
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert-message alert-${type}`;
    messageDiv.innerHTML = `
        <div class="alert-content">
            <span class="alert-text">${message}</span>
            <button class="alert-close">&times;</button>
        </div>
    `;
    
    container.innerHTML = '';
    container.appendChild(messageDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 5000);
    
    // Close button
    const closeBtn = messageDiv.querySelector('.alert-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => messageDiv.remove());
    }
}

// User Menu Dropdown
const userMenu = document.querySelector('.user-menu');
if (userMenu) {
    userMenu.addEventListener('click', function() {
        this.classList.toggle('active');
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const userMenu = document.querySelector('.user-menu');
    if (userMenu && !userMenu.contains(event.target)) {
        userMenu.classList.remove('active');
    }
});