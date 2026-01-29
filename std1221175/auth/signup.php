<?php
require_once dirname(__DIR__) . '/db.php.inc';
$allowedCities = [
    "Ramallah",
    "Nablus",
    "Hebron",
    "Jerusalem",
    "Bethlehem",
    "Gaza",
    "Jenin",
    "Tulkarm",
    "Jericho",
    "Qalqilya"
];

$errors = []; 
$success = "";

$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'city' => '',
    'role' => '',
    'bio' => '',
    'age_verification' => false
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formData['first_name'] = trim($_POST["first_name"] ?? "");
    $formData['last_name'] = trim($_POST["last_name"] ?? "");
    $formData['email'] = trim($_POST["email"] ?? "");
    $formData['phone'] = trim($_POST["phone"] ?? "");
    $formData['city'] = trim($_POST["city"] ?? "");
    $formData['role'] = trim($_POST["role"] ?? "");
    $formData['bio'] = trim($_POST["bio"] ?? "");
    $formData['age_verification'] = isset($_POST["age_verification"]);
    
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    $result = validateName($formData['first_name'], 'first_name');
    if (!$result['valid']) {
        $errors['first_name'] = $result['error'];
    }

    $result = validateName($formData['last_name'], 'last_name');
    if (!$result['valid']) {
        $errors['last_name'] = $result['error'];
    }

    $result = validateEmail($formData['email']);
    if (!$result['valid']) {
        $errors['email'] = $result['error'];
    }

    $result = validatePassword($password, true);
    if (!$result['valid']) {
        $errors['password'] = $result['error'];
    }

    $result = validatePasswordConfirm($password, $confirm_password);
    if (!$result['valid']) {
        $errors['confirm_password'] = $result['error'];
    }

    $result = validatePhone($formData['phone']);
    if (!$result['valid']) {
        $errors['phone'] = $result['error'];
    }

    $result = validateCity($formData['city'], $allowedCities);
    if (!$result['valid']) {
        $errors['city'] = $result['error'];
    }

    if (empty($formData['role'])) {
        $errors['role'] = "Please select an account type.";
    } elseif (!in_array($formData['role'], ["Client", "Freelancer"])) {
        $errors['role'] = "Please select a valid account type.";
    }

    $result = validateBio($formData['bio'], false, 500);
    if (!$result['valid']) {
        $errors['bio'] = $result['error'];
    }

    if (!$formData['age_verification']) {
        $errors['age_verification'] = "You must confirm that you are 18+ years old.";
    }

    if (empty($errors)) {
        $checkSql = "SELECT user_id FROM users WHERE email = :email";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([":email" => $formData['email']]);
        
        if ($checkStmt->fetch()) {
            $errors['email'] = "An account with this email already exists.";
        } else {
            $idSql = "SELECT MAX(CAST(user_id AS UNSIGNED)) as max_id FROM users";
            $idStmt = $pdo->query($idSql);
            $maxId = $idStmt->fetch()["max_id"];
            $newUserId = $maxId ? (string)((int)$maxId + 1) : "1000000001";

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertSql = "INSERT INTO users (user_id, first_name, last_name, email, password, phone, country, city, bio, role, status) 
                          VALUES (:user_id, :first_name, :last_name, :email, :password, :phone, :country, :city, :bio, :role, 'Active')";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ":user_id" => $newUserId,
                ":first_name" => $formData['first_name'],
                ":last_name" => $formData['last_name'],
                ":email" => $formData['email'],
                ":password" => $hashedPassword,
                ":phone" => $formData['phone'],
                ":country" => "Palestine",
                ":city" => $formData['city'],
                ":bio" => !empty($formData['bio']) ? $formData['bio'] : null,
                ":role" => $formData['role']
            ]);

            $success = "Account created successfully! Please login.";
            
            $formData = [
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'city' => '',
                'role' => '',
                'bio' => '',
                'age_verification' => false
            ];
        }
    }
}
ob_start();
?>
      <h1>Create Your Account</h1>

      <div class="card">
        <?php if (!empty($success)): ?>
          <div class="message-success">
            <?= htmlspecialchars($success) ?>
            <p class="text-sm mt-1">Redirecting to login page...</p>
          </div>
        <?php else: ?>

        <form method="POST" action="" novalidate class="form-container form-container-centered">
          
          <div class="form-section">
            <h2>Personal Information</h2>
            
            <div class="d-flex gap-2">
              <div class="form-group form-group-flex">
                <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" 
                       class="form-input <?= errorClass('first_name', $errors) ?>"
                       value="<?= htmlspecialchars($formData['first_name']) ?>"
                       placeholder="First name">
                <?php if (hasError('first_name', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['first_name']) ?></small>
                <?php endif; ?>
              </div>

              <div class="form-group form-group-flex">
                <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name"
                       class="form-input <?= errorClass('last_name', $errors) ?>"
                       value="<?= htmlspecialchars($formData['last_name']) ?>"
                       placeholder="Last name">
                <?php if (hasError('last_name', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['last_name']) ?></small>
                <?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="email">Email Address <span class="required">*</span></label>
              <input type="email" id="email" name="email"
                     class="form-input <?= errorClass('email', $errors) ?>"
                     value="<?= htmlspecialchars($formData['email']) ?>"
                     placeholder="Enter your email">
              <?php if (hasError('email', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['email']) ?></small>
              <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
              <div class="form-group form-group-flex">
                <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" maxlength="10"
                       class="form-input <?= errorClass('phone', $errors) ?>"
                       value="<?= htmlspecialchars($formData['phone']) ?>"
                       placeholder="0591234567">
                <small class="text-muted">10 digits only</small>
                <?php if (hasError('phone', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['phone']) ?></small>
                <?php endif; ?>
              </div>

              <div class="form-group form-group-flex">
                <label class="form-label" for="city">City <span class="required">*</span></label>
                <select id="city" name="city" class="form-select <?= errorClass('city', $errors) ?>">
                  <option value="">Select your city</option>
                  <?php foreach ($allowedCities as $city): ?>
                    <option value="<?= htmlspecialchars($city) ?>" <?= ($formData['city'] === $city) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($city) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (hasError('city', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['city']) ?></small>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h2>Account Security</h2>

            <div class="d-flex gap-2">
              <div class="form-group form-group-flex">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password"
                       class="form-input <?= errorClass('password', $errors) ?>"
                       placeholder="Create a password">
                <small class="text-muted">Min 8 chars, uppercase, lowercase, number, special character</small>
                <?php if (hasError('password', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['password']) ?></small>
                <?php endif; ?>
              </div>

              <div class="form-group form-group-flex">
                <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="form-input <?= errorClass('confirm_password', $errors) ?>"
                       placeholder="Confirm your password">
                <?php if (hasError('confirm_password', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['confirm_password']) ?></small>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h2>Account Type</h2>

            <div class="form-group">
              <label class="form-label">I want to join as <span class="required">*</span></label>
              <div class="radio-group <?= hasError('role', $errors) ? 'radio-error' : '' ?>">
                <label class="radio-label">
                  <input type="radio" name="role" value="Client" class="form-radio"
                         <?= ($formData['role'] === 'Client') ? 'checked' : '' ?>>
                  <span>Client - I want to hire freelancers</span>
                </label>
                <label class="radio-label">
                  <input type="radio" name="role" value="Freelancer" class="form-radio"
                         <?= ($formData['role'] === 'Freelancer') ? 'checked' : '' ?>>
                  <span>Freelancer - I want to offer services</span>
                </label>
              </div>
              <?php if (hasError('role', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['role']) ?></small>
              <?php endif; ?>
              <?php if (empty($formData['role'])): ?>
                <small class="text-muted">Select your role to continue</small>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="bio">Bio / About</label>
              <textarea id="bio" name="bio" rows="4" maxlength="500"
                        class="form-textarea <?= errorClass('bio', $errors) ?>"
                        placeholder="Tell us about yourself (optional)"><?= htmlspecialchars($formData['bio']) ?></textarea>
              <?php if (hasError('bio', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['bio']) ?></small>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label class="checkbox-label <?= hasError('age_verification', $errors) ? 'checkbox-error' : '' ?>">
              <input type="checkbox" name="age_verification" id="age_verification" class="form-checkbox"
                     <?= $formData['age_verification'] ? 'checked' : '' ?>>
              I am 18+ years old <span class="required">*</span>
            </label>
            <?php if (hasError('age_verification', $errors)): ?>
              <small class="form-error"><?= htmlspecialchars($errors['age_verification']) ?></small>
            <?php endif; ?>
          </div>

          <div class="form-actions">
            <a href="<?= url('main.php') ?>" class="btn btn-secondary">Cancel</a> 
            <button type="submit" class="btn btn-primary">Create Account</button>
          </div>

          <div class="text-center mt-2">
            <p>Already have an account? <a href="<?= url('auth/login.php') ?>">Login here</a></p>
          </div>
        </form>
        <?php endif; ?>
      </div>
<?php
$content = ob_get_clean();
$extraHead = !empty($success) ? '<meta http-equiv="refresh" content="2;url=' . url('auth/login.php') . '">' : '';
renderPage('Create Account', $content, [
    'currentPage' => $_SERVER["REQUEST_URI"],
    'extraHead' => $extraHead
]);
