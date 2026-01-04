<?php
session_start();
require_once 'auth/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] == 'A') {
    header('Location: login.php');
    exit;
}

$message = "";
$error = "";

// Handle subscription purchase with payment integration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['subscribe_plan'])) {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        header("Location: login.php");
        exit();
    }

    $user_id = intval($_SESSION['user_id']);
    $user_type = $_SESSION['user_type'];
    $plan_id = intval($_POST['plan_id']);

    try {
        // Check if user already has an ACTIVE subscription
        $active_query = "SELECT * FROM user_subscriptions WHERE user_id = $user_id AND Status = 'A' AND (end_date IS NULL OR end_date >= CURDATE())";
        $active_result = $conn->query($active_query);

        if ($active_result && $active_result->num_rows > 0) {
            $error = "You already have an active subscription. You cannot subscribe to another plan while your current subscription is active.";
        } else {
            // Get plan details
            $plan_query = "SELECT * FROM subscription_plans WHERE plan_id = $plan_id";
            $plan_result = $conn->query($plan_query);
            
            if (!$plan_result || $plan_result->num_rows == 0) {
                $error = "Invalid subscription plan selected.";
            } else {
                $plan = $plan_result->fetch_assoc();
                
                // Create transaction reference
                $transaction_ref = 'TXN' . time() . rand(1000, 9999);
                
                // Store payment details in session
                $_SESSION['pending_payment_request'] = [
                    'plan_id' => $plan_id,
                    'plan_name' => $plan['Plan_name'],
                    'amount' => $plan['price'],
                    'transaction_id' => $transaction_ref,
                    'user_id' => $user_id,
                    'user_type' => $user_type
                ];
                
                // Force session save before redirect
                session_write_close();
                
                // Redirect to payment page
                header("Location: plan_payment.php");
                exit();
            }
        }
    } catch (Exception $e) {
        $error = "System error: " . $e->getMessage();
    }
}

// Check if user has active subscription
$current_subscription = null;
$has_active_subscription = false;
$service_name = "Agricultural Equipment";

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    try {
        $sub_query = "
            SELECT us.subscription_id, us.user_id, us.plan_id, us.start_date, us.end_date, us.Status,
                   sp.plan_id, sp.Plan_name, sp.price, sp.Plan_type
            FROM user_subscriptions us 
            JOIN subscription_plans sp ON us.plan_id = sp.plan_id 
            WHERE us.user_id = {$_SESSION['user_id']} AND us.Status = 'A' 
            AND (us.end_date IS NULL OR us.end_date >= CURDATE())
            ORDER BY us.end_date DESC LIMIT 1
        ";
        $sub_result = $conn->query($sub_query);

        if ($sub_result && $sub_result->num_rows > 0) {
            $current_subscription = $sub_result->fetch_assoc();
            $has_active_subscription = true;
            $service_name = $_SESSION['user_type'] == 'O' ? 'Equipment Listings' : 'Farm Product Listings';
        }
    } catch (Exception $e) {
        // Handle error silently
    }
}

// Check if user has pending payment
$pending_payment = null;

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    try {
        $payment_query = "
            SELECT p.*, us.subscription_id, sp.Plan_name, sp.price, sp.Plan_type, sp.user_type,
                   u.Name as user_name, u.Email as user_email, u.Phone
            FROM payments p
            JOIN user_subscriptions us ON p.Subscription_id = us.subscription_id
            JOIN subscription_plans sp ON us.plan_id = sp.plan_id
            JOIN users u ON us.user_id = u.user_id
            WHERE us.user_id = {$_SESSION['user_id']} AND p.Status = 'P' AND us.Status = 'P'
            ORDER BY p.Payment_id DESC LIMIT 1
        ";
        $payment_result = $conn->query($payment_query);
        
        if ($payment_result && $payment_result->num_rows > 0) {
            $pending_payment = $payment_result->fetch_assoc();
        }
    } catch (Exception $e) {
        // Handle silently
    }
}

// Get available plans
$plans = [];
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    try {
        $plans_query = "SELECT * FROM subscription_plans ORDER BY price ASC";
        $plans_result = $conn->query($plans_query);

        if ($plans_result) {
            while ($plan = $plans_result->fetch_assoc()) {
                $plans[] = $plan;
            }
        }
    } catch (Exception $e) {
        // Handle error silently
    }
}

// Get admin WhatsApp number
$adminWhatsAppNumber = '';
$query = "SELECT Phone FROM users WHERE User_type = 'A' LIMIT 1";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $adminWhatsAppNumber = $row['Phone'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - Agricultural Equipment Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #234a23;
            --accent: #28a745;
            --warning: #ffc107;
            --error: #dc3545;
            --success-bg: linear-gradient(135deg, #d4edda, #c3e6cb);
            --error-bg: linear-gradient(135deg, #f8d7da, #f5c6cb);
            --pending-bg: linear-gradient(135deg, #fff3cd, #ffeaa7);
            --text-dark: #2c3e50;
            --text-light: #666;
            --white: #fff;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            min-height: 100vh;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
        }

        .alert.success {
            background: var(--success-bg);
            color: #155724;
        }

        .alert.error {
            background: var(--error-bg);
            color: #721c24;
        }

        .current-subscription {
            background: linear-gradient(135deg, var(--primary), #28a745);
            color: var(--white);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 12px 25px rgba(40, 167, 69, 0.3);
        }

        .current-subscription h2 {
            font-size: 1.8em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .subscription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .detail-item {
            background: rgba(255, 255, 255, 0.15);
            padding: 15px;
            border-radius: 12px;
            backdrop-filter: blur(8px);
        }

        .detail-item strong {
            display: block;
            font-size: 1em;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .detail-item .value {
            font-size: 1.1em;
            font-weight: 600;
        }

        .plans-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
        }

        .plans-section h2 {
            font-size: 2em;
            color: var(--text-dark);
            margin-bottom: 10px;
            text-align: center;
        }

        .plans-section > p {
            text-align: center;
            color: var(--text-light);
            font-size: 1em;
            margin-bottom: 25px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .plan-card {
            background: var(--white);
            border: 2px solid transparent;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            min-height: 600px;
            display: flex;
            flex-direction: column;
        }

        .plan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .plan-card:hover {
            border-color: var(--primary);
            transform: translateY(-6px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .plan-card:hover::before {
            transform: scaleX(1);
        }

        .plan-card h3 {
            font-size: 1.4em;
            color: var(--text-dark);
            margin-bottom: 12px;
            font-weight: 600;
        }

        .plan-price {
            font-size: 2.5em;
            font-weight: 700;
            background: var(--primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 15px 0;
            line-height: 1;
        }

        .plan-features {
            list-style: none;
            margin: 20px 0;
            text-align: left;
            flex-grow: 1;
        }

        .plan-features li {
            padding: 6px 0;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            line-height: 1.4;
        }

        .plan-features li i {
            color: var(--accent);
            font-size: 1em;
            width: 18px;
            flex-shrink: 0;
        }

        .plan-btn {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 12px 25px;
            border-radius: 40px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: auto;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .plan-btn:hover {
            transform: translateY(-2px);
        }

        .plan-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .no-refund-policy {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            color: #c62828;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            border-left: 4px solid #d32f2f;
            font-size: 1em;
            font-weight: 500;
        }

        .no-refund-policy h3 {
            margin-bottom: 10px;
            font-size: 1.3em;
        }

        .pending-notice {
            background: var(--pending-bg);
            color: #856404;
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid var(--warning);
            font-size: 0.95em;
        }

        .pending-notice h3 {
            margin-bottom: 8px;
            font-size: 1.2em;
        }

        .payment-notice {
            background: var(--pending-bg);
            color: #856404;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.95em;
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--warning);
        }

        .payment-notice h3 {
            margin-bottom: 8px;
            font-size: 1.2em;
        }

        .payment-actions {
            margin-top: 15px;
        }

        .payment-btn {
            background: #234a23;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin: 0 5px;
            font-weight: bold;
            transition: background 0.3s;
            cursor: pointer;
        }

        .payment-btn:hover {
            background: #1c381c;
            color: white;
            text-decoration: none;
        }

        .login-prompt {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px 25px;
            text-align: center;
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }

        .login-prompt h2 {
            font-size: 2em;
            color: var(--text-dark);
            margin-bottom: 15px;
        }

        .login-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: var(--white);
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 40px;
            display: inline-block;
            margin-top: 20px;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 80%;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .modal-header h2 {
            margin: 0;
            color: #333;
            font-size: 18px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
        }

        .form-row {
            display: flex;
            margin-bottom: 12px;
            align-items: center;
        }

        .form-group {
            display: flex;
            width: 100%;
            align-items: center;
            background: #f8f9fa;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            min-width: 140px;
            margin: 0;
            padding: 8px 12px;
            font-size: 14px;
        }

        .form-value {
            flex: 1;
            color: #555;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 4px;
        }

        .modal-footer {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 2px solid #eee;
            text-align: center;
        }

        .modal-close-btn {
            background: #234a23;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-close-btn:hover {
            background: #1a3a1a;
        }

        .status-active { color: #234a23; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-cancelled { color: #6c757d; font-weight: bold; }
        .payment-pending { color: #ffc107; font-weight: bold; }

        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }

            .subscription-details {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 15px;
            }

            .form-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-group {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-group label {
                min-width: auto;
                padding-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container" style="margin-top: 40px;">

        <?php if ($message): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>

            <?php if ($pending_payment): ?>
                <!-- Pending Payment Notice -->
                <div class="payment-notice">
                    <h3><i class="fas fa-hourglass-half"></i> Payment Pending Verification</h3>
                    <p>Your payment for <strong><?= htmlspecialchars($pending_payment['Plan_name']) ?></strong> (₹<?= number_format($pending_payment['Amount'], 2) ?>) is pending admin verification.</p>
                    <p>Transaction ID: <strong><?= htmlspecialchars($pending_payment['transaction_id']) ?></strong></p>
                    <p style="color: #666; margin-top: 10px;"><strong>Payment will be verified within 2-3 hours</strong></p>
                </div>
            <?php endif; ?>

            <?php if ($has_active_subscription && $current_subscription): ?>
                <!-- Current Active Subscription -->
                <div class="current-subscription">
                    <h2><i class="fas fa-check-circle"></i> You're All Set!</h2>
                    <p>You have unlimited access to <?= $service_name ?></p>

                    <div class="subscription-details">
                        <div class="detail-item">
                            <strong>Plan</strong>
                            <div class="value"><?= htmlspecialchars($current_subscription['Plan_name']) ?></div>
                        </div>
                        <div class="detail-item">
                            <strong>Status</strong>
                            <div class="value"><span class="status-active">ACTIVE (A)</span></div>
                        </div>
                        <div class="detail-item">
                            <strong>Start Date</strong>
                            <div class="value"><?= $current_subscription['start_date'] ?></div>
                        </div>
                        <div class="detail-item">
                            <strong>Amount Paid</strong>
                            <div class="value">₹<?= number_format($current_subscription['price'], 2) ?></div>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <!-- Show Available Plans -->
                <?php if (!empty($plans)): ?>
                    <div class="no-refund-policy">
                        <h3><i class="fas fa-exclamation-triangle"></i> NO REFUND POLICY</h3>
                        <p>All subscription payments are non-refundable. Please read carefully before subscribing.</p>
                    </div>

                    <div class="plans-section">
                        <h2>Choose Your Subscription Plan</h2>
                        <p>Select Your Plan • Secure Razorpay Payment • UPI Support</p>

                        <div class="plans-grid">
                            <?php foreach ($plans as $plan): ?>
                                <div class="plan-card">
                                    <h3><?= htmlspecialchars($plan['Plan_name']) ?></h3>
                                    <div class="plan-price">₹<?= number_format($plan['price'], 2) ?></div>

                                    <ul class="plan-features">
                                        <li><i class="fas fa-check"></i> Unlimited Listings</li>
                                        <li><i class="fas fa-check"></i> Professional Dashboard</li>
                                        <li><i class="fas fa-check"></i> Free UPI Payments</li>
                                        <li><i class="fas fa-check"></i> Analytics & Reports</li>
                                        <li><i class="fas fa-check"></i> Priority Support</li>
                                        <li><i class="fas fa-ban"></i> <strong>NO Refund Allowed</strong></li>
                                    </ul>

                                    <form method="POST" style="display: inline; width: 100%;">
                                        <input type="hidden" name="plan_id" value="<?= $plan['plan_id'] ?>">
                                        <button type="submit" name="subscribe_plan" class="plan-btn" 
                                                onclick="return confirm('Do you want to proceed with payment?');">
                                            <i class="fas fa-lock"></i> Pay Now
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        <?php else: ?>
            <!-- Login Prompt -->
            <div class="login-prompt">
                <h2><i class="fas fa-lock"></i> Please Login</h2>
                <p style="font-size: 1.1em; color: #666; margin-top: 15px;">Access subscription plans and manage your agricultural business</p>
                <a href="login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> Login to Continue</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Details Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Payment Details</h2>
                <button onclick="closeModal()" class="close-btn">&times;</button>
            </div>
            <div id="modalContent" class="details-form"></div>
            <div class="modal-footer">
                <button onclick="closeModal()" class="modal-close-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- WhatsApp Button -->
    <?php if ($adminWhatsAppNumber): ?>
        <a 
          href="https://wa.me/<?php echo $adminWhatsAppNumber; ?>?text=Hello%20Admin%2C%20I%20have%20a%20payment%20query." 
          target="_blank" 
          style="
            position: fixed; bottom: 25px; right: 25px; z-index: 9999; background: #25D366; color: white;
            border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15); text-decoration: none; font-size: 2em;"
          title="Contact Admin on WhatsApp"
        >
          <i class="fab fa-whatsapp"></i>
        </a>
    <?php endif; ?>

    <script>
        function closeModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
