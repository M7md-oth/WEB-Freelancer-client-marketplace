<?php
require_once dirname(__DIR__) . '/db.php.inc';
$error = "";
$success = "";
function checkLoginAttempts($email) {
    $attemptKey = 'login_attempts_' . md5($email);
    if (!isset($_SESSION[$attemptKey])) {
        $_SESSION[$attemptKey] = [
            'count' => 0,
            'first_attempt' => null,
            'locked_until' => null
        ];
    }
    
    $attempts = &$_SESSION[$attemptKey];
    
    if ($attempts['locked_until'] !== null && time() < $attempts['locked_until']) {
        $minutesRemaining = ceil(($attempts['locked_until'] - time()) / 60);
        return ['locked' => true, 'minutes' => $minutesRemaining, 'count' => $attempts['count']];
    }
    
    if ($attempts['locked_until'] !== null && time() >= $attempts['locked_until']) {
        $attempts['count'] = 0;
        $attempts['first_attempt'] = null;
        $attempts['locked_until'] = null;
    }
    
    if ($attempts['first_attempt'] !== null && (time() - $attempts['first_attempt']) > 900) {
        $attempts['count'] = 0;
        $attempts['first_attempt'] = null;
        $attempts['locked_until'] = null;
    }
    
    return ['locked' => false, 'count' => $attempts['count']];
}
function recordFailedAttempt($email) {
    $attemptKey = 'login_attempts_' . md5($email);
    
    if (!isset($_SESSION[$attemptKey])) {
        $_SESSION[$attemptKey] = [
            'count' => 0,
            'first_attempt' => null,
            'locked_until' => null
        ];
    }
    
    $attempts = &$_SESSION[$attemptKey];
    
    if ($attempts['first_attempt'] === null) {
        $attempts['first_attempt'] = time();
    }
    
    $attempts['count']++;
    
    if ($attempts['count'] >= 5) {
        $attempts['locked_until'] = time() + (30 * 60);
    }
}

function clearLoginAttempts($email) {
    $attemptKey = 'login_attempts_' . md5($email);
    unset($_SESSION[$attemptKey]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (empty($email) || empty($password)) {
        flashMessage("error", "Please fill in all fields.");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flashMessage("error", "Please enter a valid email address.");
    } else {
        $attemptCheck = checkLoginAttempts($email);
        
        if ($attemptCheck['locked']) {
            flashMessage("error", "Account temporarily locked. Please try again in " . $attemptCheck['minutes'] . " minutes.");
        } else {
            $sql = "SELECT user_id, first_name, last_name, email, password, role, status, profile_photo 
                    FROM users WHERE email = :email";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([":email" => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user["password"])) {
                if ($user["status"] !== "Active") {
                    flashMessage("error", "Your account is inactive. Please contact support.");
                } else {
                    clearLoginAttempts($email);
                    
                    $_SESSION["user_id"] = $user["user_id"];
                    $_SESSION["first_name"] = $user["first_name"];
                    $_SESSION["last_name"] = $user["last_name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];
                    $_SESSION["profile_photo"] = $user["profile_photo"];
                    $_SESSION['LAST_ACTIVITY'] = time();
                    
                    if ($user["role"] === "Client" && !isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }

                    if ($user["role"] === "Client") {
                        header("Location: " . url("services/browse.php"));
                    } else {
                        header("Location: " . url("profile/index.php"));
                    }
                    exit;
                }
            } else {
                recordFailedAttempt($email);
                
                $attemptCheck = checkLoginAttempts($email);
                
                if ($attemptCheck['count'] >= 3 && $attemptCheck['count'] < 5) {
                    $remainingAttempts = 5 - $attemptCheck['count'];
                    flashMessage("error", "Invalid email or password. Remaining attempts: " . $remainingAttempts);
                } elseif ($attemptCheck['locked']) {
                    flashMessage("error", "Account temporarily locked. Please try again in " . $attemptCheck['minutes'] . " minutes.");
                } else {
                    flashMessage("error", "Invalid email or password.");
                }
            }
        }
    }
}

ob_start();
?>
      <h1>Login to Your Account</h1>

      <div class="card">

        <form method="POST" action="" class="form-container">
          <div class="form-group">
            <label class="form-label" for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" required 
                   class="form-input"
                   value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                   placeholder="Enter your email">
          </div>

          <div class="form-group">
            <label class="form-label" for="password">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" required
                   class="form-input"
                   placeholder="Enter your password">
          </div>

          <div class="form-group d-flex justify-between align-center">
            <label class="checkbox-label">
              <input type="checkbox" name="remember_me" value="1" class="form-checkbox">
              Remember Me
            </label>
            <a href="<?= url('auth/forgot_password.php') ?>">Forgot password?</a>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary w-100">Login</button>
          </div>

          <div class="text-center mt-2">
            <p>Don't have an account? <a href="<?= url('auth/signup.php') ?>">Sign up</a></p>
          </div>
        </form>
      </div>
<?php
$content = ob_get_clean();
renderPage('Login', $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);
