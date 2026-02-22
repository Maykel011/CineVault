<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit();
}

$auth = new Auth();
$user = $auth->getCurrentUser();
$db = getDB();

// Get available plans
$plansQuery = "SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY monthly_price";
$plansResult = $db->query($plansQuery);
$plans = $plansResult->fetch_all(MYSQLI_ASSOC);

// Calculate trial days left
$trialEnd = new DateTime($user['trial_end']);
$today = new DateTime();
$trialDaysLeft = $today->diff($trialEnd)->days;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Plan - CineVault</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #e50914;
            --primary-dark: #b2070f;
            --background: #0f0f0f;
            --surface: #1a1a1a;
            --surface-light: #2a2a2a;
            --text: #ffffff;
            --text-secondary: #b3b3b3;
            --border: #333333;
            --success: #4caf50;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.5;
        }

        /* Navigation */
        .simple-nav {
            background: rgba(0, 0, 0, 0.95);
            padding: 15px 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 100;
            border-bottom: 1px solid var(--border);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: var(--primary);
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .btn-skip {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s;
            border: 1px solid var(--border);
        }

        .btn-skip:hover {
            background: var(--surface);
            color: var(--text);
            border-color: var(--primary);
        }

        /* Plans Page */
        .plans-page {
            padding-top: 80px;
            min-height: 100vh;
        }

        .plans-header {
            text-align: center;
            padding: 40px 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .plans-header h1 {
            font-size: clamp(28px, 5vw, 40px);
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .trial-banner {
            display: inline-flex;
            align-items: center;
            gap: 15px;
            background: var(--surface);
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 16px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .badge-trial {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .plans-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 60px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .plan-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 30px 25px;
            position: relative;
            transition: all 0.3s;
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            height: fit-content;
            min-width: 0; /* Prevents overflow */
        }

        .plan-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary);
            box-shadow: 0 20px 30px rgba(229, 9, 20, 0.2);
        }

        .plan-card.popular {
            border-color: var(--primary);
            transform: scale(1.05);
            z-index: 2;
        }

        .popular-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: white;
            padding: 6px 20px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 4px 10px rgba(229, 9, 20, 0.3);
        }

        .plan-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .plan-header h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: var(--text);
            font-weight: 600;
        }

        .plan-price {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
            word-break: break-word;
        }

        .plan-price span {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: normal;
            display: inline-block;
            margin-left: 4px;
        }

        .plan-features {
            margin-bottom: 30px;
            flex: 1;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .feature-item:last-child {
            border-bottom: none;
        }

        .check-icon {
            width: 18px;
            height: 18px;
            fill: var(--primary);
            flex-shrink: 0;
        }

        .quality {
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
        }

        .resolution {
            margin-left: auto;
            background: var(--surface-light);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .btn-select-plan {
            width: 100%;
            background: transparent;
            color: white;
            border: 2px solid var(--primary);
            border-radius: 8px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: auto;
        }

        .btn-select-plan:hover {
            background: var(--primary);
            transform: translateY(-2px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--surface);
            margin: 5% auto;
            padding: 40px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            border: 1px solid var(--border);
            animation: modalSlideIn 0.3s;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .modal-header h2 {
            font-size: 24px;
            color: var(--primary);
        }

        .close-modal {
            font-size: 28px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: var(--primary);
        }

        .selected-plan-preview {
            background: var(--surface-light);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }

        .selected-plan-preview h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .selected-price {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }

        .selected-quality {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .payment-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-section h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }

        .payment-note {
            color: var(--text-secondary);
            font-size: 13px;
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(text-secondary);
            font-size: 13px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(229, 9, 20, 0.1);
        }

        .form-group input::placeholder {
            color: #555;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-block {
            width: 100%;
        }

        .modal-footer {
            margin-top: 20px;
            text-align: center;
        }

        .secure-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--text-secondary);
            font-size: 13px;
            margin-bottom: 10px;
        }

        .lock-icon {
            width: 16px;
            height: 16px;
            fill: var(--text-secondary);
        }

        .cancel-note {
            font-size: 12px;
            color: #666;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .plans-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .plan-card.popular {
                transform: scale(1.02);
            }
        }

        @media (max-width: 768px) {
            .plans-header h1 {
                font-size: 28px;
            }
            
            .trial-banner {
                flex-direction: column;
                gap: 10px;
                padding: 15px;
            }
            
            .plans-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .plan-card.popular {
                transform: scale(1);
            }
            
            .plan-card {
                padding: 25px 20px;
            }
            
            .plan-header h3 {
                font-size: 22px;
            }
            
            .plan-price {
                font-size: 32px;
            }
            
            .feature-item {
                font-size: 13px;
            }
            
            .modal-content {
                margin: 2% auto;
                padding: 25px;
                width: 95%;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .plans-header {
                padding: 30px 15px;
            }
            
            .plans-header h1 {
                font-size: 24px;
            }
            
            .plan-card {
                padding: 20px 15px;
            }
            
            .feature-item {
                flex-wrap: wrap;
            }
            
            .resolution {
                margin-left: 28px;
                width: fit-content;
            }
            
            .btn-select-plan {
                padding: 12px;
                font-size: 14px;
            }
            
            .modal-header h2 {
                font-size: 20px;
            }
            
            .selected-price {
                font-size: 24px;
            }
        }

        /* Fix for very small screens */
        @media (max-width: 360px) {
            .feature-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .resolution {
                margin-left: 0;
            }
            
            .plan-price {
                font-size: 28px;
            }
            
            .plan-price span {
                display: block;
                margin-left: 0;
                margin-top: 4px;
            }
        }

        /* Ensure text doesn't overflow */
        .plan-features, 
        .feature-item span:last-child {
            overflow-wrap: break-word;
            word-wrap: break-word;
            hyphens: auto;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--surface);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
    </style>
</head>
<body>
    <nav class="simple-nav">
        <div class="nav-container">
            <h1 class="logo">CineVault</h1>
            <a href="dashboard.php" class="btn-skip">Skip for now</a>
        </div>
    </nav>
    
    <main class="plans-page">
        <div class="plans-header">
            <h1>Choose the plan that's right for you</h1>
            <p class="trial-banner">
                <span class="badge-trial">10 DAYS FREE</span>
                Your trial ends in <strong><?php echo $trialDaysLeft; ?> days</strong>
            </p>
        </div>
        
        <div class="plans-container">
            <div class="plans-grid">
                <?php foreach ($plans as $index => $plan): ?>
                <div class="plan-card <?php echo $index === 1 ? 'popular' : ''; ?>" data-plan='<?php echo json_encode($plan); ?>'>
                    <?php if ($index === 1): ?>
                    <div class="popular-badge">MOST POPULAR</div>
                    <?php endif; ?>
                    
                    <div class="plan-header">
                        <h3><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                        <div class="plan-price">
                            ₱<?php echo number_format($plan['monthly_price'], 2); ?>
                            <span>/month</span>
                        </div>
                    </div>
                    
                    <div class="plan-features">
                        <div class="feature-item">
                            <svg class="check-icon" viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                            </svg>
                            <span class="quality"><?php echo htmlspecialchars($plan['video_quality']); ?> Quality</span>
                            <span class="resolution"><?php echo htmlspecialchars($plan['resolution']); ?></span>
                        </div>
                        <div class="feature-item">
                            <svg class="check-icon" viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                            </svg>
                            <span>Watch on <?php echo $plan['screens']; ?> screen<?php echo $plan['screens'] > 1 ? 's' : ''; ?></span>
                        </div>
                        <?php 
                        $featureList = explode('•', $plan['features']);
                        foreach ($featureList as $feature): 
                        if (trim($feature)):
                        ?>
                        <div class="feature-item">
                            <svg class="check-icon" viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                            </svg>
                            <span><?php echo htmlspecialchars(trim($feature)); ?></span>
                        </div>
                        <?php 
                        endif;
                        endforeach; 
                        ?>
                    </div>
                    
                    <button class="btn-select-plan" onclick="selectPlan(<?php echo $plan['id']; ?>)">
                        Choose <?php echo htmlspecialchars($plan['plan_name']); ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Payment Modal -->
        <div id="paymentModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Complete Your Subscription</h2>
                    <span class="close-modal">&times;</span>
                </div>
                
                <div id="selectedPlanPreview" class="selected-plan-preview"></div>
                
                <form id="paymentForm" class="payment-form">
                    <input type="hidden" id="plan_id" name="plan_id">
                    
                    <div class="form-section">
                        <h3>Payment Details</h3>
                        <p class="payment-note">Your card won't be charged until after your 10-day trial</p>
                        
                        <div class="form-group">
                            <label for="card_number">Card Number</label>
                            <input type="text" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="expiry">Expiry Date</label>
                                <input type="text" id="expiry" placeholder="MM/YY" maxlength="5" required>
                            </div>
                            <div class="form-group">
                                <label for="cvv">CVV</label>
                                <input type="text" id="cvv" placeholder="123" maxlength="4" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="card_name">Name on Card</label>
                            <input type="text" id="card_name" placeholder="As shown on card" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary btn-block">Start Membership</button>
                    
                    <div class="modal-footer">
                        <p class="secure-note">
                            <svg class="lock-icon" viewBox="0 0 24 24">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                            Secure encrypted payment
                        </p>
                        <p class="cancel-note">You can cancel anytime before your trial ends</p>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <script>
        // Modal functions
        const modal = document.getElementById('paymentModal');
        const closeBtn = document.querySelector('.close-modal');
        
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
                    <h3>${selectedPlan.plan_name} Plan</h3>
                    <p class="selected-price">₱${parseFloat(selectedPlan.monthly_price).toFixed(2)}<span style="font-size: 14px; color: #666;">/month after trial</span></p>
                    <p class="selected-quality">${selectedPlan.video_quality} • ${selectedPlan.resolution}</p>
                `;
                
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
        
        // Format card number
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
        
        // Format expiry date
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
        
        // Form submission
        document.getElementById('paymentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const planId = document.getElementById('plan_id').value;
            
            if (!planId) {
                alert('Please select a plan');
                return;
            }
            
            // Simple validation
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            const expiry = document.getElementById('expiry').value;
            const cvv = document.getElementById('cvv').value;
            
            if (cardNumber.length !== 16) {
                alert('Please enter a valid 16-digit card number');
                return;
            }
            
            if (!expiry.match(/^(0[1-9]|1[0-2])\/([0-9]{2})$/)) {
                alert('Please enter a valid expiry date (MM/YY)');
                return;
            }
            
            if (!cvv.match(/^\d{3,4}$/)) {
                alert('Please enter a valid CVV');
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
                    body: JSON.stringify({
                        plan_id: planId,
                        card_number: cardNumber,
                        expiry: expiry,
                        cvv: cvv,
                        card_name: document.getElementById('card_name').value
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Subscription successful! Redirecting to dashboard...');
                    window.location.href = data.redirect;
                } else {
                    alert(data.message);
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                alert('Error processing payment. Please try again.');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>