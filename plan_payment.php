<?php
session_start();
require_once 'auth/config.php';

// Check if there's a pending payment request
if (!isset($_SESSION['pending_payment_request'])) {
    header('Location: subscription.php?error=no_payment');
    exit();
}

$payment_data = $_SESSION['pending_payment_request'];

// Your Razorpay credentials
define('RAZORPAY_KEY_ID', 'rzp_test_RaVY7GzITrjKHT');
define('RAZORPAY_KEY_SECRET', 'cGibm4NpcBsddtEIpyEkXGqE');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - AgriRent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #234a23, #28a745);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .payment-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 45px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .payment-header h1 {
            font-size: 28px;
            color: #234a23;
            margin-bottom: 10px;
        }
        
        .payment-header p {
            color: #666;
            font-size: 14px;
        }
        
        .payment-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
            padding-top: 12px;
            border-top: 2px solid #ddd;
            font-weight: 600;
            color: #234a23;
        }
        
        .detail-label {
            color: #666;
        }
        
        .detail-value {
            color: #333;
            font-weight: 500;
        }
        
        .payment-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #234a23, #28a745);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(36, 74, 35, 0.3);
        }
        
        .payment-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .cancel-btn {
            background: #6c757d;
        }
        
        .cancel-btn:hover {
            background: #5a6268;
        }
        
        .security-note {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #234a23;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1>Complete Your Payment</h1>
            <p>Secure payment powered by Razorpay</p>
        </div>
        
        <div class="payment-details">
            <div class="detail-row">
                <span class="detail-label">Plan Name:</span>
                <span class="detail-value"><?php echo htmlspecialchars($payment_data['plan_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($payment_data['transaction_id']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value">Rs. <?php echo number_format($payment_data['amount'], 2); ?></span>
            </div>
        </div>
        
        <form id="paymentForm">
            <button type="button" class="payment-btn" id="razorpayBtn">
                <i class="fas fa-lock"></i> Pay Now with Razorpay
            </button>
            <button type="button" class="payment-btn cancel-btn" id="cancelBtn">
                <i class="fas fa-times"></i> Cancel Payment
            </button>
        </form>
        
        <div class="loading" id="loadingDiv">
            <div class="spinner"></div>
            <p>Processing your payment...</p>
        </div>
        
        <div class="security-note">
            <i class="fas fa-shield-alt"></i> Your payment is secured with 256-bit encryption
        </div>
    </div>
    
    <!-- Razorpay Checkout Script -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        const razorpayBtn = document.getElementById('razorpayBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const loadingDiv = document.getElementById('loadingDiv');
        let paymentInProgress = false;
        
        // PAY NOW BUTTON
        razorpayBtn.addEventListener('click', async function() {
            if (paymentInProgress) return;
            
            paymentInProgress = true;
            razorpayBtn.disabled = true;
            loadingDiv.style.display = 'block';
            
            try {
                const createOrderResponse = await fetch('create_payment_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        amount: <?php echo $payment_data['amount']; ?>
                    })
                });
                
                const orderData = await createOrderResponse.json();
                
                if (!orderData.success) {
                    alert('Error creating payment: ' + orderData.error);
                    paymentInProgress = false;
                    razorpayBtn.disabled = false;
                    loadingDiv.style.display = 'none';
                    return;
                }
                
                const options = {
                    key: '<?php echo RAZORPAY_KEY_ID; ?>',
                    amount: orderData.amount,
                    currency: orderData.currency,
                    name: 'AgriRent',
                    description: '<?php echo htmlspecialchars($payment_data['plan_name']); ?> Subscription',
                    order_id: orderData.order_id,
                    handler: function(response) {
                        loadingDiv.style.display = 'block';
                        verifyPayment(response);
                    },
                    prefill: {
                        email: '<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>',
                        contact: '<?php echo htmlspecialchars($_SESSION['phone'] ?? ''); ?>'
                    },
                    theme: {
                        color: '#234a23'
                    },
                    modal: {
                        ondismiss: function() {
                            handlePaymentCancellation('User closed payment modal');
                        }
                    }
                };
                
                const rzp = new Razorpay(options);
                rzp.open();
                loadingDiv.style.display = 'none';
                
            } catch (error) {
                alert('Error initiating payment: ' + error.message);
                paymentInProgress = false;
                razorpayBtn.disabled = false;
                loadingDiv.style.display = 'none';
            }
        });
        
        // VERIFY PAYMENT (SUCCESS)
        async function verifyPayment(response) {
            try {
                const verifyResponse = await fetch('verify_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature,
                        transaction_id: '<?php echo htmlspecialchars($payment_data['transaction_id']); ?>'
                    })
                });
                
                const verifyData = await verifyResponse.json();
                
                if (verifyData.success) {
                    window.location.href = 'subscription_success.php?payment_id=' + response.razorpay_payment_id;
                } else {
                    handlePaymentFailure({ error: { description: verifyData.error } });
                }
            } catch (error) {
                alert('Error verifying payment: ' + error.message);
                paymentInProgress = false;
                razorpayBtn.disabled = false;
                loadingDiv.style.display = 'none';
            }
        }
        
        // HANDLE PAYMENT FAILURE
        async function handlePaymentFailure(response) {
            try {
                const failureResponse = await fetch('handle_payment_failure.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        error: response.error?.description || 'Payment processing failed',
                        payment_id: response.razorpay_payment_id || null
                    })
                });
                
                const failureData = await failureResponse.json();
                
                if (failureData.success) {
                    alert('Payment failed. Status set to Cancelled. Redirecting...');
                    window.location.href = 'subscription.php?error=payment_failed&status=C';
                }
            } catch (error) {
                alert('Error handling failure: ' + error.message);
            } finally {
                paymentInProgress = false;
                razorpayBtn.disabled = false;
                loadingDiv.style.display = 'none';
            }
        }
        
        // CANCEL BUTTON
        cancelBtn.addEventListener('click', async function() {
            if (confirm('Are you sure you want to cancel this payment?')) {
                await handlePaymentCancellation('User clicked cancel button');
            }
        });
        
        // HANDLE CANCELLATION
        async function handlePaymentCancellation(reason) {
            try {
                cancelBtn.disabled = true;
                razorpayBtn.disabled = true;
                loadingDiv.style.display = 'block';
                
                const cancelResponse = await fetch('handle_payment_cancel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        reason: reason
                    })
                });
                
                const cancelData = await cancelResponse.json();
                
                if (cancelData.success) {
                    alert('Payment cancelled. Status set to Cancelled.');
                    window.location.href = 'subscription.php?cancelled=true&status=C';
                }
            } catch (error) {
                alert('Error cancelling payment: ' + error.message);
                cancelBtn.disabled = false;
                razorpayBtn.disabled = false;
                loadingDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>
