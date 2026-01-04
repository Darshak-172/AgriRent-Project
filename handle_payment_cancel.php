<?php
session_start();
require_once 'auth/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['pending_payment_request'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No payment to cancel'
    ]);
    exit;
}

$payment_data = $_SESSION['pending_payment_request'];

$conn->begin_transaction();

try {
    // Insert cancelled subscription with STATUS = 'C'
    $cancel_sub_query = "INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, Status) 
                        VALUES ({$payment_data['user_id']}, {$payment_data['plan_id']}, CURDATE(), CURDATE(), 'C')";
    
    if (!$conn->query($cancel_sub_query)) {
        throw new Exception("Failed to create cancelled subscription");
    }
    
    $subscription_id = $conn->insert_id;
    
    // Insert cancelled payment with STATUS = 'C'
    $cancel_pay_query = "INSERT INTO payments (Subscription_id, Amount, transaction_id, UPI_transaction_id, payment_date, Status) 
                        VALUES ($subscription_id, {$payment_data['amount']}, 'CANCELLED', 'USER_CANCELLED', NOW(), 'C')";
    
    if (!$conn->query($cancel_pay_query)) {
        throw new Exception("Failed to record cancelled payment");
    }
    
    $conn->commit();
    
    unset($_SESSION['pending_payment_request']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment cancelled',
        'subscription_status' => 'C'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
