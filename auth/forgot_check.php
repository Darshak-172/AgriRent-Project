<?php
session_start();
require_once '../auth/config.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: index.php');
    exit;
}

$message = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Authentica API config
$AUTHENTICA_HOST = "authentica1.p.rapidapi.com";
$AUTHENTICA_KEY = "6f435f07f4msh0b946ad6b308200p18070fjsn34890f3f5744";
$AUTHENTICA_URL = "https://authentica1.p.rapidapi.com/api/v2";

/**
 * Send OTP function
 */
function sendOtpAuthentica($phone) {
    global $AUTHENTICA_HOST, $AUTHENTICA_KEY, $AUTHENTICA_URL;

    $phone_clean = preg_replace('/\D/', '', $phone);
    if (strlen($phone_clean) != 10) {
        return ['success' => false, 'message' => 'Invalid phone number.'];
    }
    $phone = '+91' . $phone_clean;

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $AUTHENTICA_URL . "/send-otp",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode(['method' => 'whatsapp', 'phone' => $phone]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-rapidapi-host: $AUTHENTICA_HOST",
            "x-rapidapi-key: $AUTHENTICA_KEY",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) return ['success' => false, 'message' => "Network error: $err"];
    if (stripos($response, 'html') !== false || stripos($response, 'DOCTYPE') !== false)
        return ['success' => false, 'message' => 'API configuration error.'];

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE)
        return ['success' => false, 'message' => 'Invalid API response format.'];

    if (isset($result['success']) && $result['success'] === true)
        return ['success' => true, 'message' => "OTP sent successfully to WhatsApp " . $phone_clean];
    else
        return ['success' => false, 'message' => $result['message'] ?? $result['error'] ?? 'Failed to send OTP.'];
}

/**
 * Verify OTP function
 */
function verifyOtpAuthentica($phone, $otp) {
    global $AUTHENTICA_HOST, $AUTHENTICA_KEY, $AUTHENTICA_URL;

    $phone_clean = preg_replace('/\D/', '', $phone);
    if (strlen($phone_clean) != 10) {
        return ['success' => false, 'message' => 'Invalid phone number.'];
    }
    $phone = '+91' . $phone_clean;

    $otp = trim($otp);
    if (empty($otp) || !preg_match('/^\d{4,6}$/', $otp)) {
        return ['success' => false, 'message' => 'Invalid OTP format.'];
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $AUTHENTICA_URL . "/verify-otp",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode(['phone' => $phone, 'otp' => $otp]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-rapidapi-host: $AUTHENTICA_HOST",
            "x-rapidapi-key: $AUTHENTICA_KEY",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) return ['success' => false, 'message' => "Network error: $err"];
    if (stripos($response, 'html') !== false || stripos($response, 'DOCTYPE') !== false)
        return ['success' => false, 'message' => 'API configuration error.'];

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE)
        return ['success' => false, 'message' => 'Invalid API response format.'];

    if ((isset($result['status']) && $result['status'] === true) || (isset($result['success']) && $result['success'] === true))
        return ['success' => true, 'message' => $result['message'] ?? 'OTP verified successfully.'];
    else
        return ['success' => false, 'message' => $result['message'] ?? 'Invalid OTP.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php'; // Your DB connection

    // Handle send OTP
    if (isset($_POST['send_otp'])) {
        $phone_input = preg_replace('/\D/', '', $_POST['phone'] ?? '');

        if (strlen($phone_input) !== 10) {
            $_SESSION['error'] = 'Please enter a valid 10-digit mobile number.';
            header("Location: ../forgotpassword.php");
            exit;
        }

        $stmt = $conn->prepare("SELECT user_id, Name FROM users WHERE Phone = ?");
        $stmt->bind_param("s", $phone_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $_SESSION['error'] = 'Mobile number not registered.';
            header("Location: ../forgotpassword.php");
            exit;
        }

        $user = $result->fetch_assoc();
        $_SESSION['reset_phone'] = $phone_input;
        $_SESSION['reset_name'] = $user['Name'];
        $_SESSION['reset_user_id'] = $user['user_id'];

        $send_otp_result = sendOtpAuthentica($phone_input);
        if ($send_otp_result['success']) {
            $_SESSION['otp_sent'] = true;
            $_SESSION['success'] = $send_otp_result['message'];
        } else {
            $_SESSION['error'] = $send_otp_result['message'];
        }
        header("Location: ../forgotpassword.php");
        exit;
    }

    // Handle verify OTP and reset password
    if (isset($_POST['reset_password'])) {
        if (empty($_SESSION['otp_sent']) || !isset($_SESSION['reset_phone'], $_SESSION['reset_user_id'])) {
            $_SESSION['error'] = 'Session expired. Please try again.';
            header("Location: ../forgotpassword.php");
            exit;
        }

        $otp = $_POST['otp'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($otp) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error'] = 'All fields are required.';
            header("Location: ../forgotpassword.php");
            exit;
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = 'Passwords do not match.';
            header("Location: ../forgotpassword.php");
            exit;
        }

        if (strlen($new_password) < 6) {
            $_SESSION['error'] = 'Password must be at least 6 characters.';
            header("Location: ../forgotpassword.php");
            exit;
        }

        $verify_otp_result = verifyOtpAuthentica($_SESSION['reset_phone'], $otp);
        if (!$verify_otp_result['success']) {
            $_SESSION['error'] = $verify_otp_result['message'];
            header("Location: ../forgotpassword.php");
            exit;
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $_SESSION['reset_user_id']);
        if ($stmt->execute()) {
            unset($_SESSION['reset_phone'], $_SESSION['reset_user_id'], $_SESSION['reset_name'], $_SESSION['otp_sent']);
            $_SESSION['success'] = 'Password reset successful. You may now login.';
            header("Location: ../login.php");
            exit;
        } else {
            $_SESSION['error'] = 'Failed to reset password. Please try again.';
            header("Location: ../forgotpassword.php");
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/css/main.css" />
</head>
<body>
    <main class="auth-wrapper">
        <section class="auth-card" aria-labelledby="forgot-heading">
            <h2 id="forgot-heading">Forgot Password</h2>
            <p>Please Enter Your Registered Mobile Number</p>

            <?php if ($error): ?>
                <div style="color: red; font-weight: bold; margin-bottom: 10px;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div style="color: green; font-weight: bold; margin-bottom: 10px;"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <input
                        type="tel"
                        name="phone"
                        pattern="[0-9]{10}"
                        maxlength="10" minlength="10"
                        placeholder="Enter registered mobile number"
                        required
                        <?= isset($_SESSION['otp_sent']) ? 'readonly value="' . htmlspecialchars($_SESSION['reset_phone'] ?? '') . '"' : '' ?>
                    />
                </div>

                <?php if (isset($_SESSION['otp_sent'])): ?>
                    <div class="input-group">
                        <input type="text" name="otp" placeholder="Enter OTP" required autofocus />
                    </div>

                    <div class="input-group">
                        <input
                            type="password"
                            name="new_password"
                            placeholder="Enter new password (min 6 characters)"
                            required
                            minlength="6"
                        />
                    </div>

                    <div class="input-group">
                        <input
                            type="password"
                            name="confirm_password"
                            placeholder="Confirm new password"
                            required
                            minlength="6"
                        />
                    </div>

                    <button type="submit" class="primary-btn" name="reset_password">Reset Password</button>
                <?php else: ?>
                    <button type="submit" class="primary-btn" name="send_otp">Send OTP</button>
                <?php endif; ?>
            </form>

            <?php if (isset($_SESSION['otp_sent'])): ?>
                <form method="POST" action="" style="margin-top: 10px;">
                    <button type="submit" name="send_otp" class="primary-btn" style="background-color: #6c757d;">
                        Resend OTP
                    </button>
                </form>
            <?php endif; ?>

            <p style="margin-top: 15px;">
                <a href="login.php">Back to Login</a>
            </p>
        </section>
    </main>
</body>
</html>
