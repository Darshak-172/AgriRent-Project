<?php
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: index.php');
    exit;
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navigation.php'; ?>
<?php require_once('auth/config.php'); ?>

<?php
// Store form data in session
foreach (['first_name', 'last_name', 'email', 'phone', 'user_type', 'admin_code', 'password', 'confirm_password'] as $field) {
    if (isset($_POST[$field])) {
        $_SESSION[$field] = $_POST[$field];
    }
}

// ========== AUTHENTICA API - CORRECT CONFIGURATION ==========
// The correct API endpoint for Authentica sms OTP
$AUTHENTICA_HOST = "authentica1.p.rapidapi.com";
$AUTHENTICA_KEY = "b32859924fmsh952b026627df10ep12e2fcjsnd552bf7f118e";
$AUTHENTICA_URL = "https://authentica1.p.rapidapi.com/api/v2";

/**
 * SEND OTP - Correct Implementation
 */
function sendOtpAuthentica($phone, $name) {
    global $AUTHENTICA_HOST, $AUTHENTICA_KEY, $AUTHENTICA_URL;

    // Format phone number - ensure +91 prefix
    $phone_clean = preg_replace('/\D/', '', $phone);
    if (strlen($phone_clean) == 10) {
        $phone = '+91' . $phone_clean;
    } else {
        return [
            'success' => false,
            'message' => "Invalid phone number. Please enter 10 digits."
        ];
    }

    error_log("====== SEND OTP DEBUG ======");
    error_log("Phone: " . $phone);
    error_log("URL: " . $AUTHENTICA_URL . "/send-otp");
    error_log("Host: " . $AUTHENTICA_HOST);

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $AUTHENTICA_URL . "/send-otp",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'method' => 'sms',
            'phone' => $phone
        ]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-rapidapi-host: " . $AUTHENTICA_HOST,
            "x-rapidapi-key: " . $AUTHENTICA_KEY,
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => false  // DON'T follow redirects
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $url_info = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);

    error_log("HTTP Code: " . $http_code);
    error_log("Effective URL: " . $url_info);
    error_log("Response: " . substr($response, 0, 200));
    error_log("Error: " . $err);
    error_log("============================");

    if ($err) {
        return [
            'success' => false,
            'message' => "Network error: " . $err
        ];
    }

    // Check if response is HTML (redirect/error)
    if (stripos($response, 'html') !== false || stripos($response, 'DOCTYPE') !== false) {
        error_log("ERROR: Received HTML response instead of JSON");
        return [
            'success' => false,
            'message' => "API configuration error. Please check credentials."
        ];
    }

    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Error: " . json_last_error_msg());
        return [
            'success' => false,
            'message' => "Invalid API response format"
        ];
    }

    if (isset($result['success']) && $result['success'] === true) {
        return [
            'success' => true,
            'message' => "OTP sent successfully to sms " . $phone
        ];
    } else {
        $msg = $result['message'] ?? $result['error'] ?? "Failed to send OTP";
        return [
            'success' => false,
            'message' => $msg
        ];
    }
}

/**
 * VERIFY OTP - Correct Implementation
 */
function verifyOtpAuthentica($phone, $otp) {
    global $AUTHENTICA_HOST, $AUTHENTICA_KEY, $AUTHENTICA_URL;

    // Format phone number
    $phone_clean = preg_replace('/\D/', '', $phone);
    if (strlen($phone_clean) == 10) {
        $phone = '+91' . $phone_clean;
    }

    // Validate OTP
    $otp = trim($otp);
    if (empty($otp) || !preg_match('/^\d{4,6}$/', $otp)) {
        return [
            'success' => false,
            'message' => "Invalid OTP format"
        ];
    }

    error_log("====== VERIFY OTP DEBUG ======");
    error_log("Phone: " . $phone);
    error_log("OTP: " . $otp);
    error_log("URL: " . $AUTHENTICA_URL . "/verify-otp");

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $AUTHENTICA_URL . "/verify-otp",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'phone' => $phone,
            'otp' => $otp
        ]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-rapidapi-host: " . $AUTHENTICA_HOST,
            "x-rapidapi-key: " . $AUTHENTICA_KEY,
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => false  // DON'T follow redirects
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    error_log("HTTP Code: " . $http_code);
    error_log("Response: " . substr($response, 0, 200));
    error_log("Error: " . $err);
    error_log("==============================");

    if ($err) {
        return [
            'success' => false,
            'message' => "Network error: " . $err
        ];
    }

    // Check if response is HTML (redirect/error)
    if (stripos($response, 'html') !== false || stripos($response, 'DOCTYPE') !== false) {
        error_log("ERROR: Received HTML response instead of JSON");
        return [
            'success' => false,
            'message' => "API configuration error. Please check credentials."
        ];
    }

    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Error: " . json_last_error_msg());
        return [
            'success' => false,
            'message' => "Invalid API response"
        ];
    }

    // Check for success
    if ((isset($result['status']) && $result['status'] === true) ||
            (isset($result['success']) && $result['success'] === true)) {
        return [
            'success' => true,
            'message' => $result['message'] ?? "OTP verified successfully"
        ];
    } else {
        return [
            'success' => false,
            'message' => $result['message'] ?? "Invalid OTP"
        ];
    }
}

// ========== HANDLE GET OTP ==========
if (isset($_POST['get_otp'])) {
    if (!$conn) {
        $_SESSION['error'] = 'Database connection failed';
        header('location: register.php');
        exit;
    }

    if (!empty($_POST['phone'])) {
        $mobile = $_POST['phone'];
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE Phone LIKE ?");
        $search = '%' . preg_replace('/\D/', '', $mobile);
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $_SESSION['error'] = "Mobile Number Already Registered";
            header("Location: register.php");
            exit;
        }
        $stmt->close();
    }

    $phone = $_POST['phone'];
    $name = $_POST['first_name'];
    $_SESSION['reg_phone'] = '+91' . preg_replace('/\D/', '', $phone);
    $_SESSION['reg_name'] = $name;

    // Send OTP
    $otp_result = sendOtpAuthentica($phone, $name);

    if ($otp_result['success']) {
        $_SESSION['otp_sent'] = true;
        $_SESSION['success'] = $otp_result['message'];
    } else {
        $_SESSION['error'] = $otp_result['message'];
    }

    if (isset($_GET['admin_code'])) {
        $admin_code = $_GET['admin_code'];
        header("Location: register.php?admin_code=$admin_code");
        exit;
    } else {
        header("Location: register.php");
        exit;
    }
}

// ========== HANDLE VERIFY OTP ==========
if (isset($_POST['verify_otp'])) {
    if (!isset($_SESSION['reg_phone'])) {
        $_SESSION['error'] = "Phone number missing from session. Please request OTP again.";
        header("Location: register.php");
        exit;
    }

    $phone = $_SESSION['reg_phone'];
    $otp = $_POST['otp'];

    $verify_result = verifyOtpAuthentica($phone, $otp);

    if ($verify_result['success']) {
        $_SESSION['otp_verified'] = true;
        $_SESSION['verified_phone'] = $phone;
        $_SESSION['success'] = $verify_result['message'];
    } else {
        $_SESSION['error'] = $verify_result['message'];
    }

    if (isset($_GET['admin_code'])) {
        $admin_code = $_GET['admin_code'];
        header("Location: register.php?admin_code=$admin_code");
        exit;
    } else {
        header("Location: register.php");
        exit;
    }
}

// ========== HANDLE CREATE ACCOUNT ==========
if (isset($_POST['create_account'])) {
    if (empty($_SESSION['otp_verified'])) {
        $_SESSION['error'] = "Please verify your OTP before creating the account.";
        header("Location: register.php");
        exit;
    }
    if (isset($_GET['admin_code'])) {
        $admin_code = $_GET['admin_code'];
        header("Location: " . "auth/register_check.php?admin_code=$admin_code");
        exit;
    } else {
        header("Location: " . "auth/register_check.php");
        exit;
    }
}
?>

<main class="auth-wrapper">
    <section class="auth-card" aria-labelledby="reg-heading">
        <h2 id="reg-heading">Create an AgriRent account</h2>

        <?php
        if (isset($_SESSION['error'])) {
            echo '<div style="color:#d32f2f;font-weight:bold;margin-bottom:10px;padding:10px;background:#ffebee;border-radius:4px;border-left:4px solid #d32f2f;">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div style="color:#388e3c;font-weight:bold;margin-bottom:10px;padding:10px;background:#e8f5e9;border-radius:4px;border-left:4px solid #388e3c;">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        ?>

        <form action="" method="POST">
            <div class="input-group">
                <label for="first_name">First name</label>
                <input type="text" id="first_name" name="first_name"
                       pattern="[a-zA-Z]+"
                       oninput="this.value=this.value.replace(/[^a-zA-Z]/g,'')"
                       placeholder="Name"
                       title="Only alphabets are allowed (no spaces, numbers, or special characters)"
                       value="<?= isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : '' ?>"
                       required>
            </div>

            <div class="input-group">
                <label for="last_name">Last name</label>
                <input type="text" id="last_name" name="last_name"
                       pattern="[a-zA-Z]+"
                       oninput="this.value=this.value.replace(/[^a-zA-Z]/g,'')"
                       placeholder="Surname"
                       title="Only alphabets are allowed (no spaces, numbers, or special characters)"
                       value="<?= isset($_SESSION['last_name']) ? htmlspecialchars($_SESSION['last_name']) : '' ?>"
                       required>
            </div>

            <div class="input-group">
    <label for="email">Email address</label>
    <input type="email" id="email" name="email"
           pattern="[a-zA-Z0-9]+@(gmail|yahoo|hotmail|outlook)\.com"
           placeholder="abc1@gmail.com"
           title="Enter valid email with @gmail.com, @yahoo.com, @hotmail.com, or @outlook.com"
           oninput="this.value=this.value.replace(/[^a-zA-Z0-9@.]/g,'')"
           value="<?= isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '' ?>"
           required>
</div>



            <div class="input-group">
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="phone"
                       pattern="[789][0-9]{9}"
                       maxlength="10" minlength="10"
                       placeholder="98765XXXXX"
                       title="Enter a valid 10-digit Indian mobile number starting with 7, 8, or 9"
                       oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                       value="<?php
                       if (isset($_SESSION['otp_sent']) && isset($_SESSION['reg_phone'])) {
                           echo htmlspecialchars(substr($_SESSION['reg_phone'], 3)); // Remove +91 for display
                       } elseif (isset($_SESSION['phone'])) {
                           echo htmlspecialchars($_SESSION['phone']);
                       } else {
                           echo '';
                       }
                       ?>"
                       <?php
                       if (isset($_SESSION['otp_sent'])) {
                           echo 'readonly'; // Make the field readonly once OTP is sent
                       }
                       ?>
                       required>
            </div>


            <?php
            $flag = false;
            if (isset($_GET['admin_code']) && $_GET['admin_code'] === 'SECRET123') {
                $_SESSION['user_type'] = 'A';
                echo '<input type="hidden" name="user_type" value="A">';
                $flag = true;
            }

            if (!$flag) {
                echo '<fieldset class="radio-set">';
                echo '<legend>I am a…</legend>';

                $checkedF = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'F') ? 'checked' : '';
                $checkedO = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'O') ? 'checked' : '';

                echo '<label><input type="radio" name="user_type" value="F" required ' . $checkedF . '> Farmer - renting equipment</label>';
                echo '<label><input type="radio" name="user_type" value="O" required ' . $checkedO . '> Equipment owner - listing equipment</label>';

                echo '</fieldset>';
            }
            ?>

            <?php if (!isset($_SESSION['otp_sent'])): ?>

                <button type="submit" class="primary-btn" name="get_otp">Get OTP</button>

            <?php elseif (!isset($_SESSION['otp_verified'])): ?>

                <div class="input-group">
                    <label for="otp">Enter OTP</label>
                    <input type="tel" id="otp" name="otp" 
                           minlength="4" maxlength="6" 
                           pattern="[0-9]{4,6}"
                           placeholder="0000" 
                           title="Enter 4-6 digit OTP"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                           required autofocus>
                </div>
                <button type="submit" class="primary-btn" name="verify_otp">Verify OTP</button>

            <?php else: ?>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           minlength="6" maxlength="20"
                           placeholder="At least 6 characters"
                           title="Password must be between 6-20 characters"
                           value="<?= isset($_SESSION['password']) ? htmlspecialchars($_SESSION['password']) : '' ?>"
                           required>
                </div>
                <div class="input-group">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           minlength="6" maxlength="20"
                           placeholder="Confirm password"
                           title="Password must match"
                           value="<?= isset($_SESSION['confirm_password']) ? htmlspecialchars($_SESSION['confirm_password']) : '' ?>"
                           required>
                </div>
                <label class="checkbox-label">
                    <input type="checkbox" name="agree" required>
                    <span>I agree to the <a href="includes/terms.php">Terms</a> & <a href="includes/privacy.php">Privacy</a></span>
                </label>
                <button type="submit" class="primary-btn" name="create_account">Create account</button>

            <?php endif; ?>
        </form>

        <p class="alt-text">
            Already have an account? <a href="login.php">Sign in</a>
        </p>
    </section>
</main>

<style>
    .auth-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 200px);
        padding: 20px;
        background: white;
    }

    .auth-card {
        background: white;
        padding: 40px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 450px;
    }

    .auth-card h2 {
        color: #234a23;
        margin-bottom: 30px;
        text-align: center;
        font-size: 24px;
        font-weight: 700;
    }

    .input-group {
        margin-bottom: 20px;
    }

    .input-group label {
        display: block;
        margin-bottom: 8px;
        color: #333;
        font-weight: 500;
        font-size: 14px;
    }

    .input-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }

    .input-group input:focus {
        outline: none;
        border-color: #234a23;
        box-shadow: 0 0 4px rgba(35, 74, 35, 0.2);
    }

    .radio-set {
        margin-bottom: 20px;
        border: none;
        padding: 0;
    }

    .radio-set legend {
        font-weight: 500;
        color: #333;
        margin-bottom: 12px;
        font-size: 14px;
    }

    .radio-set label {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        cursor: pointer;
        font-size: 14px;
    }

    .radio-set input[type="radio"] {
        width: auto;
        margin-right: 8px;
        cursor: pointer;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        cursor: pointer;
    }

    .checkbox-label input {
        width: auto !important;
        margin-right: 8px !important;
    }

    .checkbox-label span {
        font-size: 14px;
        color: #333;
    }

    .checkbox-label a {
        color: #234a23;
        text-decoration: none;
    }

    .checkbox-label a:hover {
        text-decoration: underline;
    }

    .primary-btn {
        width: 100%;
        padding: 12px;
        background: #234a23;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        font-size: 16px;
        transition: background-color 0.3s;
    }

    .primary-btn:hover {
        background: #2d5d2f;
    }

    .alt-text {
        text-align: center;
        color: #666;
        font-size: 14px;
        margin-top: 20px;
    }

    .alt-text a {
        color: #234a23;
        text-decoration: none;
        font-weight: 600;
    }

    @media (max-width: 600px) {
        .auth-card {
            padding: 30px 20px;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
