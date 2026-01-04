<?php
require_once 'auth/config.php';
require_once 'vendor/autoload.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

define('RAZORPAY_KEY_ID', 'rzp_test_RaVY7GzITrjKHT');
define('RAZORPAY_KEY_SECRET', 'cGibm4NpcBsddtEIpyEkXGqE');

try {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    if (!isset($data['amount'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Amount missing'
        ]);
        exit;
    }
    
    $amount = floatval($data['amount']);
    
    if ($amount <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid amount'
        ]);
        exit;
    }
    
    $amountInPaise = $amount * 100;
    
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    
    $order = $api->order->create([
        'receipt' => 'subscription_' . time(),
        'amount' => $amountInPaise,
        'currency' => 'INR',
        'payment_capture' => 1
    ]);
    
    echo json_encode([
        'success' => true,
        'order_id' => $order['id'],
        'amount' => $order['amount'],
        'currency' => $order['currency']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
