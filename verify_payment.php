<?php
session_start();
require_once 'auth/config.php';
require_once 'vendor/autoload.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

header('Content-Type: application/json');

// Enable error reporting for debugging during development, disable on production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('RAZORPAY_KEY_ID', 'rzp_test_RaVY7GzITrjKHT');
define('RAZORPAY_KEY_SECRET', 'cGibm4NpcBsddtEIpyEkXGqE');

try {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    $razorpay_payment_id = $data['razorpay_payment_id'] ?? null;
    $razorpay_order_id = $data['razorpay_order_id'] ?? null;
    $razorpay_signature = $data['razorpay_signature'] ?? null;
    $transaction_id = $data['transaction_id'] ?? null;

    if (!$razorpay_payment_id || !$razorpay_order_id || !$razorpay_signature) {
        echo json_encode(['success' => false, 'error' => 'Missing payment details']);
        exit;
    }

    if (!isset($_SESSION['pending_payment_request'])) {
        echo json_encode(['success' => false, 'error' => 'No pending payment request found']);
        exit;
    }

    $payment_data = $_SESSION['pending_payment_request'];
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    try {
        // Verify signature
        $attributes = [
            'razorpay_order_id' => $razorpay_order_id,
            'razorpay_payment_id' => $razorpay_payment_id,
            'razorpay_signature' => $razorpay_signature,
        ];
        $api->utility->verifyPaymentSignature($attributes);

        // Start DB transaction
        $conn->begin_transaction();

        // Fetch subscription plan duration_days
        $plan_id = (int)$payment_data['plan_id'];
        $plan_query = "SELECT duration_days FROM subscription_plans WHERE plan_id = $plan_id LIMIT 1";
        $plan_result = $conn->query($plan_query);
        if (!$plan_result || $plan_result->num_rows === 0) {
            throw new Exception("Subscription plan not found.");
        }
        $plan = $plan_result->fetch_assoc();
        $duration_days = (int)$plan['duration_days'];
        if ($duration_days <= 0) {
            throw new Exception("Invalid plan duration.");
        }

        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+$duration_days days", strtotime($start_date)));

        // Insert subscription record with end date
        $user_id = (int)$payment_data['user_id'];
        $amount = floatval($payment_data['amount']);

        $sub_query = "INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, Status) 
                      VALUES ($user_id, $plan_id, '$start_date', '$end_date', 'A')";
        if (!$conn->query($sub_query)) {
            throw new Exception("Failed to create subscription: " . $conn->error);
        }
        $subscription_id = $conn->insert_id;

        // Insert payment record with success status
        $pay_query = "INSERT INTO payments (Subscription_id, Amount, transaction_id, UPI_transaction_id, payment_date, Status)
                      VALUES ($subscription_id, $amount, '{$transaction_id}', '$razorpay_payment_id', NOW(), 'S')";
        if (!$conn->query($pay_query)) {
            throw new Exception("Failed to record payment: " . $conn->error);
        }

        // Commit transaction
        $conn->commit();

        // Clear session
        unset($_SESSION['pending_payment_request']);
        $_SESSION['payment_success'] = true;

        echo json_encode([
            'success' => true,
            'message' => 'Payment verified and subscription activated',
            'subscription_status' => 'A',
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);

    } catch (SignatureVerificationError $e) {
        $conn->rollback();

        // Insert failed subscription/payment records
        $conn->begin_transaction();
        try {
            $failed_sub_query = "INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, Status)
                                VALUES ({$payment_data['user_id']}, {$payment_data['plan_id']}, CURDATE(), CURDATE(), 'C')";
            if (!$conn->query($failed_sub_query)) {
                throw new Exception("Failed to create cancelled subscription: " . $conn->error);
            }
            $failed_subscription_id = $conn->insert_id;
            $failed_pay_query = "INSERT INTO payments (Subscription_id, Amount, transaction_id, UPI_transaction_id, payment_date, Status) 
                                VALUES ($failed_subscription_id, {$payment_data['amount']}, 'FAILED', '$razorpay_payment_id', NOW(), 'F')";
            if (!$conn->query($failed_pay_query)) {
                throw new Exception("Failed to record failed payment: " . $conn->error);
            }
            $conn->commit();
        } catch (Exception $ex) {
            $conn->rollback();
        }
        unset($_SESSION['pending_payment_request']);

        echo json_encode([
            'success' => false,
            'error' => 'Payment verification failed: ' . $e->getMessage(),
            'subscription_status' => 'C'
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
