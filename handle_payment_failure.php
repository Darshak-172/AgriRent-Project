<?php
session_start();
require_once 'auth/config.php';

header('Content-Type: application/json');

try {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    if (!isset($_SESSION['pending_payment_request'])) {
        echo json_encode([
            'success' => false,
            'error' => 'No pending payment'
        ]);
        exit;
    }
    
    $payment_data = $_SESSION['pending_payment_request'];
    $error_message = $data['error'] ?? 'Payment failed';
    $payment_id = $data['payment_id'] ?? null;
    
    $conn->begin_transaction();
    
    try {
        // Create failed subscription with STATUS = 'C'
        $failed_sub_query = "INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, Status) 
                            VALUES ({$payment_data['user_id']}, {$payment_data['plan_id']}, CURDATE(), CURDATE(), 'C')";
        
        if (!$conn->query($failed_sub_query)) {
            throw new Exception("Failed to create subscription record");
        }
        
        $subscription_id = $conn->insert_id;
        
        // Insert failed payment with STATUS = 'F'
        $failed_pay_query = "INSERT INTO payments (Subscription_id, Amount, transaction_id, UPI_transaction_id, payment_date, Status) 
                            VALUES ($subscription_id, {$payment_data['amount']}, 'FAILED', '{$payment_id}', NOW(), 'F')";
        
        if (!$conn->query($failed_pay_query)) {
            throw new Exception("Failed to record payment");
        }
        
        $conn->commit();
        
        unset($_SESSION['pending_payment_request']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment failed. Status set to Cancelled.',
            'subscription_status' => 'C'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
