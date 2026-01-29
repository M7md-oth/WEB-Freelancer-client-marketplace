<?php
require_once dirname(__DIR__) . '/db.php.inc';

requireLogin();
includeComponent('service_card');

$sql = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([":user_id" => $_SESSION["user_id"]]);
$user = $stmt->fetch();

$services = [];
$statistics = [
    'total_services' => 0,
    'active_services' => 0,
    'featured_services' => 0,
    'total_orders' => 0
];

if ($user["role"] === "Freelancer") {
    $servicesSql = "SELECT * FROM services WHERE freelancer_id = :user_id ORDER BY created_date DESC";
    $servicesStmt = $pdo->prepare($servicesSql);
    $servicesStmt->execute([":user_id" => $_SESSION["user_id"]]);
    $services = $servicesStmt->fetchAll();
    
    $statistics['total_services'] = count($services);
    $statistics['active_services'] = count(array_filter($services, fn($s) => $s["status"] === "Active"));
    $statistics['featured_services'] = count(array_filter($services, fn($s) => $s["featured_status"] === "Yes"));
    
    $ordersSql = "SELECT COUNT(*) FROM orders WHERE freelancer_id = :user_id AND status = 'Completed'";
    $ordersStmt = $pdo->prepare($ordersSql);
    $ordersStmt->execute([":user_id" => $_SESSION["user_id"]]);
    $statistics['total_orders'] = $ordersStmt->fetchColumn();
}

$errors = [];
$formData = [
    'first_name' => $user["first_name"],
    'last_name' => $user["last_name"],
    'phone' => $user["phone"],
    'city' => $user["city"],
    'bio' => $user["bio"] ?? ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formData['first_name'] = trim($_POST["first_name"] ?? "");
    $formData['last_name'] = trim($_POST["last_name"] ?? "");
    $formData['phone'] = trim($_POST["phone"] ?? "");
    $formData['city'] = trim($_POST["city"] ?? "");
    $formData['bio'] = trim($_POST["bio"] ?? "");
    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    $result = validateName($formData['first_name'], 'first_name');
    if (!$result['valid']) {
        $errors['first_name'] = $result['error'];
    }

    $result = validateName($formData['last_name'], 'last_name');
    if (!$result['valid']) {
        $errors['last_name'] = $result['error'];
    }

    $result = validatePhone($formData['phone']);
    if (!$result['valid']) {
        $errors['phone'] = $result['error'];
    }

    $result = validateCity($formData['city']);
    if (!$result['valid']) {
        $errors['city'] = $result['error'];
    }

    $result = validateBio($formData['bio'], $user["role"] === "Freelancer", 500);
    if (!$result['valid']) {
        $errors['bio'] = $result['error'];
    }

    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        $result = validateCurrentPassword($current_password, $user["password"]);
        if (!$result['valid']) {
            $errors['current_password'] = $result['error'];
        }

        $result = validatePassword($new_password, true);
        if (!$result['valid']) {
            $errors['new_password'] = $result['error'];
        }

        $result = validatePasswordConfirm($new_password, $confirm_password);
        if (!$result['valid']) {
            $errors['confirm_password'] = $result['error'];
        }
    }

    if (empty($errors)) {
        $profile_photo = $user["profile_photo"];
        
        if (isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] === UPLOAD_ERR_OK) {
            $uploadDir = basePath("uploads/profiles/");
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
            $filename = uniqid() . "." . $ext;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $targetPath)) {
                $profile_photo = "uploads/profiles/" . $filename;
            }
        }

        $updateFields = [
            "first_name" => $formData['first_name'],
            "last_name" => $formData['last_name'],
            "phone" => $formData['phone'],
            "city" => $formData['city'],
            "bio" => $formData['bio'],
            "profile_photo" => $profile_photo
        ];
        
        $updateParams = [
            ":first_name" => $updateFields["first_name"],
            ":last_name" => $updateFields["last_name"],
            ":phone" => $updateFields["phone"],
            ":city" => $updateFields["city"],
            ":bio" => $updateFields["bio"],
            ":profile_photo" => $updateFields["profile_photo"],
            ":user_id" => $_SESSION["user_id"]
        ];

        if (!empty($new_password)) {
            $updateFields["password"] = password_hash($new_password, PASSWORD_DEFAULT);
            $updateParams[":password"] = $updateFields["password"];
        }

        $updateSql = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                      phone = :phone, city = :city, bio = :bio, profile_photo = :profile_photo";
        if (!empty($new_password)) {
            $updateSql .= ", password = :password";
        }
        $updateSql .= " WHERE user_id = :user_id";
        
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute($updateParams);

        $_SESSION["first_name"] = $formData['first_name'];
        $_SESSION["last_name"] = $formData['last_name'];
        $_SESSION["profile_photo"] = $profile_photo;

        flashMessage("success", "Profile updated successfully!");
        header("Location: " . url("profile/index.php"));
        exit;
    }
}

ob_start();
?>
      <h1>My Profile</h1>
      
      <?php 
      if (isset($_SESSION['created_service_id'])) {
          $serviceId = $_SESSION['created_service_id'];
          unset($_SESSION['created_service_id']);
          ?>
          <div class="message-success mb-3">
            <strong>Service created successfully!</strong>
            <p class="mt-1">Service ID: <?= htmlspecialchars($serviceId) ?></p>
            <div class="mt-2">
              <a href="<?= url('services/details.php?id=' . $serviceId) ?>" class="btn btn-primary btn-sm">View Service</a>
              <a href="<?= url('create-service.php?step=1') ?>" class="btn btn-secondary btn-sm">Create Another Service</a>
            </div>
          </div>
          <?php
      }
      ?>

      <div class="profile-layout">
        <div class="profile-sidebar">
          <div class="card text-center">
            <div class="profile-photo-container">
              <img src="<?= !empty($user["profile_photo"]) ? BASE_URL . "/" . htmlspecialchars($user["profile_photo"]) : DEFAULT_PROFILE_IMAGE ?>" alt="Profile Photo" class="profile-photo">
            </div>
            
            <div class="mb-2">
              <label for="profile_photo_form" class="btn btn-secondary btn-sm">Change Photo</label>
            </div>
            
            <h2><?= formatFullName($user["first_name"], $user["last_name"]) ?></h2>
            <p class="text-muted mt-1"><?= htmlspecialchars($user["email"]) ?></p>
            <span class="badge <?= $user["role"] === "Freelancer" ? "badge-freelancer" : "badge-client" ?>"><?= htmlspecialchars($user["role"]) ?></span>
            <p class="text-muted mt-1"><?= htmlspecialchars($user["city"] . ", " . $user["country"]) ?></p>
            <p class="text-muted text-sm">Member since <?= date("M Y", strtotime($user["registration_date"])) ?></p>
          </div>

          <?php if ($user["role"] === "Freelancer"): ?>
          <div class="card mt-3">
            <h3>Statistics</h3>
            <div class="statistics-grid">
              <div class="stat-item">
                <div class="stat-number"><?= $statistics['total_services'] ?></div>
                <div class="stat-label">Total Services</div>
              </div>
              <div class="stat-item">
                <div class="stat-number stat-active"><?= $statistics['active_services'] ?></div>
                <div class="stat-label">Active Services</div>
              </div>
              <div class="stat-item">
                <div class="stat-number stat-featured"><?= $statistics['featured_services'] ?>/3</div>
                <div class="stat-label">Featured Services</div>
              </div>
              <div class="stat-item">
                <div class="stat-number"><?= $statistics['total_orders'] ?></div>
                <div class="stat-label">Total Orders</div>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <div class="profile-content">
          <div class="card">
            <h2>Edit Profile</h2>
            <form method="POST" action="" enctype="multipart/form-data" class="form-container">
              <div class="d-flex gap-2">
                <div class="form-group form-group-flex">
                  <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                  <input type="text" id="first_name" name="first_name" required 
                         class="form-input <?= errorClass('first_name', $errors) ?>"
                         value="<?= htmlspecialchars($formData['first_name']) ?>">
                  <?php if (hasError('first_name', $errors)): ?>
                    <small class="form-error"><?= htmlspecialchars($errors['first_name']) ?></small>
                  <?php endif; ?>
                </div>

                <div class="form-group form-group-flex">
                  <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                  <input type="text" id="last_name" name="last_name" required 
                         class="form-input <?= errorClass('last_name', $errors) ?>"
                         value="<?= htmlspecialchars($formData['last_name']) ?>">
                  <?php if (hasError('last_name', $errors)): ?>
                    <small class="form-error"><?= htmlspecialchars($errors['last_name']) ?></small>
                  <?php endif; ?>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="email">Email Address (cannot be changed)</label>
                <input type="email" id="email" name="email" disabled class="form-input"
                       value="<?= htmlspecialchars($user["email"]) ?>">
              </div>

              <div class="form-group">
                <label class="form-label" for="phone">Phone Number <span class="required">*</span> (10 digits)</label>
                <input type="tel" id="phone" name="phone" required maxlength="10" 
                       class="form-input <?= errorClass('phone', $errors) ?>"
                       value="<?= htmlspecialchars($formData['phone']) ?>">
                <?php if (hasError('phone', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['phone']) ?></small>
                <?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label" for="city">City <span class="required">*</span></label>
                <input type="text" id="city" name="city" required 
                       class="form-input <?= errorClass('city', $errors) ?>"
                       value="<?= htmlspecialchars($formData['city']) ?>">
                <?php if (hasError('city', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['city']) ?></small>
                <?php endif; ?>
              </div>

              <?php if ($user["role"] === "Freelancer"): ?>
              <div class="form-group">
                <label class="form-label" for="bio">Bio <span class="required">*</span></label>
                <textarea id="bio" name="bio" rows="4" required 
                          class="form-textarea <?= errorClass('bio', $errors) ?>"><?= htmlspecialchars($formData['bio']) ?></textarea>
                <?php if (hasError('bio', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['bio']) ?></small>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <div class="form-section">
                <h3>Change Password (optional)</h3>
                <div class="form-group">
                  <label class="form-label" for="current_password">Current Password</label>
                  <input type="password" id="current_password" name="current_password" 
                         class="form-input <?= errorClass('current_password', $errors) ?>"
                         placeholder="Enter current password">
                  <?php if (hasError('current_password', $errors)): ?>
                    <small class="form-error"><?= htmlspecialchars($errors['current_password']) ?></small>
                  <?php endif; ?>
                </div>

                <div class="form-group">
                  <label class="form-label" for="new_password">New Password</label>
                  <input type="password" id="new_password" name="new_password" 
                         class="form-input <?= errorClass('new_password', $errors) ?>"
                         placeholder="Enter new password">
                  <small class="text-muted">Min 8 chars, uppercase, lowercase, number, special character</small>
                  <?php if (hasError('new_password', $errors)): ?>
                    <small class="form-error"><?= htmlspecialchars($errors['new_password']) ?></small>
                  <?php endif; ?>
                </div>

                <div class="form-group">
                  <label class="form-label" for="confirm_password">Confirm New Password</label>
                  <input type="password" id="confirm_password" name="confirm_password" 
                         class="form-input <?= errorClass('confirm_password', $errors) ?>"
                         placeholder="Confirm new password">
                  <?php if (hasError('confirm_password', $errors)): ?>
                    <small class="form-error"><?= htmlspecialchars($errors['confirm_password']) ?></small>
                  <?php endif; ?>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="profile_photo_form">Profile Photo</label>
                <input type="file" id="profile_photo_form" name="profile_photo" accept="image/*" class="form-input">
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
              </div>
            </form>
          </div>

          <?php if ($user["role"] === "Freelancer"): ?>
          <div class="mt-3">
            <div class="d-flex justify-between align-center mb-2">
              <h2>My Services</h2>
              <a href="<?= url('create-service.php') ?>" class="btn btn-primary">+ Add New Service</a>
            </div>

            <?php if (count($services) === 0): ?>
              <div class="card text-center">
                <p class="text-muted">You haven't created any services yet.</p>
                <a href="<?= url('create-service.php') ?>" class="btn btn-primary">Create Your First Service</a>
              </div>
            <?php else: ?>
              <div class="services-grid">
                <?php foreach ($services as $service): ?>
                  <div>
                    <?php 
                    $service["first_name"] = $user["first_name"];
                    $service["last_name"] = $user["last_name"];
                    $service["profile_photo"] = $user["profile_photo"];
                    renderServiceCard($service, ['showFeaturedBadge' => false]); 
                    ?>
                    <div class="mt-2">
                      <a href="<?= url('services/edit.php?id=' . $service["service_id"]) ?>" class="btn btn-secondary btn-sm">Edit</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
<?php
$content = ob_get_clean();
renderPage('My Profile', $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);
