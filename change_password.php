<link rel="stylesheet" href="../assets/css/main.css"/>
<?php
session_start();
require_once 'auth/config.php';

if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Authentica API config
$AUTHENTICA_HOST = "authentica1.p.rapidapi.com";
$AUTHENTICA_KEY = "50f31f193fmsh94363c9b03704d5p155688jsn8ae4f7677623";
$AUTHENTICA_URL = "https://authentica1.p.rapidapi.com/api/v2";

/**
 * Send OTP function
 */
function sendOtpAuthentica($phone) {
    global $AUTHENTICA_HOST, $AUTHENTICA_KEY, $AUTHENTICA_URL;

    $phone_clean = preg_replace('/\D/', '', $phone);
    if (strlen($phone_clean) == 10) {
        $phone = '+91' . $phone_clean;
    } else {
        return ['success' => false, 'message' => 'Invalid phone number.'];
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $AUTHENTICA_URL . "/send-otp",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'method' => 'whatsapp',
            'phone' => $phone
        ]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-rapidapi-host: $AUTHENTICA_HOST",
            "x-rapidapi-key: $AUTHENTICA_KEY",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return ['success' => false, 'message' => "Network error: $err"];
    }
    if (stripos($response, 'html') !== false || stripos($response, 'DOCTYPE') !== false) {
        return ['success' => false, 'message' => 'API configuration error.'];
    }
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Invalid API response format.'];
    }
    if (isset($result['success']) && $result['success'] === true) {
        return ['success' => true, 'message' => "OTP sent to " . $phone];
    } else {
        $msg = $result['message'] ?? $result['error'] ?? 'Failed to send OTP.';
        return ['success' => false, 'message' => $msg];
    }
}

/**
 * Verify OTP function
 */
function verifyOtpAuthentica($phone, $otp) {
    global $AUTHENTICA_HOST, $AUTHENTICA_KEY, $AUTHENTICA_URL;

    $phone_clean = preg_replace('/\D/', '', $phone);
    if (strlen($phone_clean) == 10) {
        $phone = '+91' . $phone_clean;
    }

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
        CURLOPT_POSTFIELDS => json_encode([
            'phone' => $phone,
            'otp' => $otp
        ]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-rapidapi-host: $AUTHENTICA_HOST",
            "x-rapidapi-key: $AUTHENTICA_KEY",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return ['success' => false, 'message' => "Network error: $err"];
    }
    if (stripos($response, 'html') !== false || stripos($response, 'DOCTYPE') !== false) {
        return ['success' => false, 'message' => 'API configuration error.'];
    }
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Invalid API response.'];
    }
    if ((isset($result['status']) && $result['status'] === true) || (isset($result['success']) && $result['success'] === true)) {
        return ['success' => true, 'message' => $result['message'] ?? 'OTP verified successfully.'];
    } else {
        return ['success' => false, 'message' => $result['message'] ?? 'Invalid OTP.'];
    }
}

// Fetch user's phone from DB for OTP sending
$stmt = $conn->prepare("SELECT Phone FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_phone);
$stmt->fetch();
$stmt->close();

if (isset($_POST['send_otp'])) {
    $otp_send = sendOtpAuthentica($user_phone);
    if ($otp_send['success']) {
        $_SESSION['otp_sent'] = true;
        $message = $otp_send['message'];
    } else {
        $error = $otp_send['message'];
    }
}

if (isset($_POST['verify_otp'])) {
    $otp_input = $_POST['otp'] ?? '';
    $otp_verify = verifyOtpAuthentica($user_phone, $otp_input);
    if ($otp_verify['success']) {
        $_SESSION['otp_verified'] = true;
        $message = $otp_verify['message'] . " You may now change your password.";
    } else {
        $error = $otp_verify['message'];
    }
}

// Allow password change only if OTP verified in this session
if (isset($_POST['change_password'])) {
    if (empty($_SESSION['otp_verified'])) {
        $error = "Please verify OTP before changing your password.";
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New password and confirmation do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long.";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($hashed_password);
            $stmt->fetch();
            $stmt->close();

            if (password_verify($current_password, $hashed_password)) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $update_stmt->bind_param("si", $new_hash, $user_id);
                if ($update_stmt->execute()) {
                    $message = "Password changed successfully.";
                    unset($_SESSION['otp_verified'], $_SESSION['otp_sent']); // reset OTP session
                } else {
                    $error = "Failed to update password.";
                }
                $update_stmt->close();
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}

require 'includes/header.php';
require 'includes/navigation.php';
?>

<div class="changepassword-container">
    <div class="password-change-form">
        <h1>Change Password</h1>

        <?php if ($message): ?>
            <div class="success-message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['otp_verified'])): ?>
            <form method="POST">
                <p>Before changing your password, please verify OTP sent to your registered phone number.</p><br>
                <button type="submit" name="send_otp" class="btn-primary">Send OTP</button>
                <a href="account.php" class="btn-secondary">Back to Profile</a>
            </form>

            <?php if (isset($_SESSION['otp_sent']) && $_SESSION['otp_sent']): ?>
                <form method="POST" style="margin-top: 20px;">
                    <label for="otp">Enter OTP:</label>
                    <input type="text" id="otp" name="otp" maxlength="6" minlength="4" required>
                    <button type="submit" name="verify_otp" class="btn-primary">Verify OTP</button>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <form method="POST" style="margin-top: 20px;">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small>Password must be at least 6 characters long</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="form-buttons">
                    <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                    <a href="../account.php" class="btn-secondary">Back to Profile</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
