<?php
session_start();
require_once('../auth/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'F') {
    header('Location: ../login.php');
    exit();
}

$farmer_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle subscription purchase
if ($_POST) {
    if (isset($_POST['subscribe_plan'])) {
        $plan_id = intval($_POST['plan_id']);
        
        try {
            $active_check = $conn->prepare("SELECT * FROM user_subscriptions WHERE user_id = ? AND Status = 'A'");
            $active_check->bind_param("i", $farmer_id);
            $active_check->execute();
            $active_result = $active_check->get_result();

            if ($active_result->num_rows > 0) {
                $error = "You already have an active subscription.";
            } else {
                $plan_stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE plan_id = ? AND user_type = 'F'");
                $plan_stmt->bind_param("i", $plan_id);
                $plan_stmt->execute();
                $plan_result = $plan_stmt->get_result();

                if ($plan_result->num_rows > 0) {
                    $plan = $plan_result->fetch_assoc();
                    
                    // Calculate end date based on plan type
                    $start_date = date('Y-m-d');
                    $days = ($plan['Plan_type'] == 'M') ? 30 : 365;
                    $end_date = date('Y-m-d', strtotime("+$days days", strtotime($start_date)));
                    
                    // Create subscription with both start and end dates
                    $subscription_stmt = $conn->prepare("INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, Status) VALUES (?, ?, ?, ?, 'A')");
                    $subscription_stmt->bind_param("iiss", $farmer_id, $plan_id, $start_date, $end_date);
                    
                    if ($subscription_stmt->execute()) {
                        $message = "Subscription activated successfully!";
                    } else {
                        $error = "Failed to create subscription.";
                    }
                    $subscription_stmt->close();
                } else {
                    $error = "Invalid subscription plan selected.";
                }
                $plan_stmt->close();
            }
            $active_check->close();
        } catch (Exception $e) {
            $error = "System error: " . $e->getMessage();
        }
    }
}

// Get subscription plans
$plans_stmt = $conn->prepare("SELECT plan_id, Plan_name, Plan_type, price FROM subscription_plans WHERE user_type = 'F' ORDER BY price ASC");
$plans_stmt->execute();
$plans_result = $plans_stmt->get_result();
$plans = [];
while ($plan = $plans_result->fetch_assoc()) {
    $plans[] = $plan;
}
$plans_stmt->close();

// Get ALL subscriptions
$current_subs_stmt = $conn->prepare("SELECT us.*, sp.Plan_name, sp.Plan_type, sp.price 
                                    FROM user_subscriptions us 
                                    JOIN subscription_plans sp ON us.plan_id = sp.plan_id 
                                    WHERE us.user_id = ? 
                                    ORDER BY us.start_date DESC");
$current_subs_stmt->bind_param("i", $farmer_id);
$current_subs_stmt->execute();
$current_subs_result = $current_subs_stmt->get_result();
$current_subscriptions = [];
while ($sub = $current_subs_result->fetch_assoc()) {
    $current_subscriptions[] = $sub;
}
$current_subs_stmt->close();

// Get ACTIVE subscription
$active_subscription = null;
foreach ($current_subscriptions as $sub) {
    if ($sub['Status'] == 'A') {
        $active_subscription = $sub;
        break;
    }
}

// Get payment history
$payments_stmt = $conn->prepare("SELECT p.*, sp.Plan_name 
                                FROM payments p 
                                JOIN user_subscriptions us ON p.Subscription_id = us.subscription_id 
                                JOIN subscription_plans sp ON us.plan_id = sp.plan_id 
                                WHERE us.user_id = ? 
                                ORDER BY p.payment_date DESC LIMIT 10");
$payments_stmt->bind_param("i", $farmer_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$payments = [];
while ($payment = $payments_result->fetch_assoc()) {
    $payments[] = $payment;
}
$payments_stmt->close();

require 'fheader.php';
require 'farmer_nav.php';
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        
    }

    body {
        background: #f5f7fa;
    }

    .main-content {
        padding: 30px 20px;
        max-width: 1200px;
        margin: 0 auto;
        font-family: 'Arial', sans-serif;
        background: #f5f7fa;
        margin-right: 0.1px;
    }

    .header-section {
        margin-bottom: 30px;
    }

    .main-content h1 {
        font-size: 2.2em;
        color: #234a23;
        margin-bottom: 8px;
        text-align: center;
        font-weight: 700;
    }

    .main-content > p {
        text-align: center;
        color: #666;
        font-size: 1em;
        margin-bottom: 30px;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        border-left: 4px solid;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert.alert-success {
        background: #d4edda;
        color: #155724;
        border-color: #28a745;
    }

    .alert.alert-danger {
        background: #f8d7da;
        color: #721c24;
        border-color: #dc3545;
    }

    .section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .section h2 {
        font-size: 1.5em;
        color: #234a23;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
    }

    /* Current Plan Section */
    .current-plan {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border-radius: 10px;
        padding: 25px;
        border-left: 5px solid #4caf50;
    }

    .plan-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        gap: 15px;
        border-bottom: 2px solid rgba(255,255,255,0.5);
        padding-bottom: 15px;
    }

    .plan-header h3 {
        font-size: 1.4em;
        color: #234a23;
        margin: 0;
        font-weight: 700;
    }

    .plan-badge {
        padding: 8px 18px;
        border-radius: 25px;
        font-weight: bold;
        font-size: 0.85em;
        text-transform: uppercase;
        background: #4caf50;
        color: white;
        white-space: nowrap;
        box-shadow: 0 2px 6px rgba(76,175,80,0.3);
    }

    .plan-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 15px;
    }

    .detail-item {
        padding: 15px;
        background: rgba(255,255,255,0.9);
        border-radius: 8px;
        border-left: 4px solid #4caf50;
    }

    .detail-item strong {
        color: #234a23;
        display: block;
        margin-bottom: 8px;
        font-weight: 700;
        font-size: 0.95em;
    }

    .detail-item span {
        color: #333;
        font-size: 1.1em;
        font-weight: 600;
    }

    /* No Subscription */
    .no-subscription {
        text-align: center;
        padding: 50px 20px;
        background: #f8f9fa;
        border-radius: 10px;
        border: 2px dashed #dee2e6;
    }

    .no-subscription h3 {
        font-size: 1.3em;
        color: #dc3545;
        margin-bottom: 10px;
        font-weight: 700;
    }

    .no-subscription p {
        color: #666;
        font-size: 0.95em;
    }

    /* Table Styles */
    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
    }

    table thead {
        background: #234a23;
        color: white;
    }

    table th {
        padding: 16px 14px;
        text-align: left;
        font-weight: 700;
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    table td {
        padding: 14px 14px;
        border-bottom: 1px solid #eee;
        font-size: 0.95em;
    }

    table tbody tr {
        transition: background-color 0.2s ease;
    }

    table tbody tr:hover {
        background: #f8f9fa;
    }

    table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Status Badges */
    .status-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.8em;
        text-transform: uppercase;
        text-align: center;
        letter-spacing: 0.3px;
    }

    .status-a {
        background: #d4edda;
        color: #155724;
    }

    .status-p {
        background: #fff3cd;
        color: #856404;
    }

    .status-e {
        background: #e2e3e5;
        color: #6c757d;
    }

    .status-s {
        background: #d4edda;
        color: #155724;
    }

    .status-c {
        background: #f8d7da;
        color: #721c24;
    }

    .text-success {
        color: #28a745;
        font-weight: 700;
    }

    .text-warning {
        color: #ffc107;
        font-weight: 700;
    }

    .no-data {
        text-align: center;
        padding: 40px 20px;
        color: #999;
        background: #f8f9fa;
        border-radius: 8px;
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 15px 10px;
        }

        .main-content h1 {
            font-size: 1.8em;
        }

        .section {
            padding: 15px;
            margin-bottom: 15px;
        }

        .plan-details {
            grid-template-columns: 1fr;
        }

        .plan-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .plan-header h3,
        .plan-header .plan-badge {
            width: 100%;
        }

        table th, table td {
            padding: 10px;
            font-size: 0.85em;
        }

        .detail-item {
            padding: 12px;
        }
    }
</style>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="header-section">
        <h1>Subscription Management</h1>
        <p>Manage your AgriRent subscription plan and billing</p>
    </div>
    
    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Current Subscription Status -->
    <div class="section">
        <h2><i class="fas fa-credit-card"></i> Current Subscription Status</h2>
        
        <?php if ($active_subscription): ?>
            <div class="current-plan">
                <div class="plan-header">
                    <h3><?= htmlspecialchars($active_subscription['Plan_name']) ?></h3>
                    <span class="plan-badge">Active</span>
                </div>
                
                <div class="plan-details">
                    <div class="detail-item">
                        <strong>Plan Type:</strong>
                        <span><?= $active_subscription['Plan_type'] == 'M' ? 'Monthly' : 'Yearly' ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Amount:</strong>
                        <span>₹<?= number_format($active_subscription['price'], 2) ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Start Date:</strong>
                        <span><?= isset($active_subscription['start_date']) && !empty($active_subscription['start_date']) ? date('d M, Y', strtotime($active_subscription['start_date'])) : 'N/A' ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>End Date:</strong>
                        <span><?= isset($active_subscription['end_date']) && !empty($active_subscription['end_date']) ? date('d M, Y', strtotime($active_subscription['end_date'])) : 'N/A' ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Days Remaining:</strong>
                        <span class="<?php 
                            if (isset($active_subscription['end_date']) && !empty($active_subscription['end_date'])) {
                                $days = ceil((strtotime($active_subscription['end_date']) - time()) / (24 * 60 * 60));
                                echo ($days < 30) ? 'text-warning' : 'text-success';
                            } else {
                                echo 'text-warning';
                            }
                        ?>">
                            <?php
                            if (isset($active_subscription['end_date']) && !empty($active_subscription['end_date'])) {
                                $days = ceil((strtotime($active_subscription['end_date']) - time()) / (24 * 60 * 60));
                                echo max(0, $days) . ' days';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>Status:</strong>
                        <span><span class="status-badge status-a">Active</span></span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="no-subscription">
                <h3><i class="fas fa-inbox"></i> No Active Subscription</h3>
                <p>You don't have an active subscription. Subscribe to a plan to access premium features.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Subscription History -->
    <div class="section">
        <h2><i class="fas fa-history"></i> Subscription History</h2>
        
        <?php if (count($current_subscriptions) > 0): ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($current_subscriptions as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['Plan_name']) ?></td>
                                <td><?= $sub['Plan_type'] == 'M' ? 'Monthly' : 'Yearly' ?></td>
                                <td>₹<?= number_format($sub['price'], 2) ?></td>
                                <td><?= (isset($sub['start_date']) && !empty($sub['start_date'])) ? date('d M, Y', strtotime($sub['start_date'])) : 'N/A' ?></td>
                                <td><?= (isset($sub['end_date']) && !empty($sub['end_date'])) ? date('d M, Y', strtotime($sub['end_date'])) : 'N/A' ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($sub['Status']) ?>">
                                        <?php
                                        switch($sub['Status']) {
                                            case 'A': echo 'Active'; break;
                                            case 'P': echo 'Pending'; break;
                                            case 'E': echo 'Expired'; break;
                                            default: echo 'Cancelled';
                                        }
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data">No subscription history found.</div>
        <?php endif; ?>
    </div>

    <!-- Payment History -->
    <div class="section">
        <h2><i class="fas fa-receipt"></i> Payment History</h2>
        
        <?php if (count($payments) > 0): ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Plan Name</th>
                            <th>Amount</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= date('d M, Y H:i', strtotime($payment['payment_date'])) ?></td>
                                <td><?= htmlspecialchars($payment['Plan_name']) ?></td>
                                <td>₹<?= number_format($payment['Amount'], 2) ?></td>
                                <td><?= htmlspecialchars($payment['transaction_id'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($payment['Status']) ?>">
                                        <?php
                                        switch($payment['Status']) {
                                            case 'S': echo 'Success'; break;
                                            case 'P': echo 'Pending'; break;
                                            case 'F': echo 'Failed'; break;
                                            default: echo 'Cancelled';
                                        }
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data">No payment history found.</div>
        <?php endif; ?>
    </div>

</div>

<?php require 'ffooter.php'; ?>
