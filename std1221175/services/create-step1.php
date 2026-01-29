<?php
$categories = [
    "Web Development" => ["WordPress", "PHP", "JavaScript", "React", "Full Stack", "Performance", "UI/UX"],
    "Graphic Design" => ["Logo Design", "Brand Identity", "Illustration", "Print Design", "Social Media"],
    "Digital Marketing" => ["SEO", "Social Media Marketing", "PPC", "Content Marketing", "Email Marketing"],
    "Writing & Translation" => ["Article Writing", "Copywriting", "Technical Writing", "Editing", "Translation"],
    "Video & Animation" => ["Video Editing", "Animation", "Motion Graphics", "Explainer Videos"],
    "Music & Audio" => ["Voice Over", "Mixing", "Music Production", "Sound Effects", "Podcast Editing"],
    "Business Consulting" => ["Business Planning", "Financial Consulting", "Legal Consulting", "HR Consulting"],
    "Tutoring & Education" => ["Online Tutoring", "Course Creation", "Test Preparation", "Language Teaching"]
];

$errors = [];
$formData = $_SESSION['service_draft']['basic_info'] ?? [
    'title' => '',
    'category' => '',
    'subcategory' => '',
    'description' => '',
    'price' => '',
    'delivery_time' => '',
    'revisions_included' => ''
];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['step1_submit'])) {
    $formData['title'] = trim($_POST["title"] ?? "");
    $formData['category'] = trim($_POST["category"] ?? "");
    $formData['subcategory'] = trim($_POST["subcategory"] ?? "");
    $formData['description'] = trim($_POST["description"] ?? "");
    $formData['price'] = trim($_POST["price"] ?? "");
    $formData['delivery_time'] = trim($_POST["delivery_time"] ?? "");
    $formData['revisions_included'] = trim($_POST["revisions_included"] ?? "");

    if (empty($formData['title'])) {
        $errors['title'] = "Service title is required.";
    } elseif (strlen($formData['title']) < 10 || strlen($formData['title']) > 100) {
        $errors['title'] = "Service title must be between 10 and 100 characters.";
    }

    if (empty($formData['category'])) {
        $errors['category'] = "Category is required.";
    } elseif (!array_key_exists($formData['category'], $categories)) {
        $errors['category'] = "Please select a valid category from the list.";
    }

    if (empty($formData['subcategory'])) {
        $errors['subcategory'] = "Subcategory is required.";
    } elseif (!empty($formData['category']) && !in_array($formData['subcategory'], $categories[$formData['category']])) {
        $errors['subcategory'] = "Please select a valid subcategory for the selected category.";
    }

    if (empty($formData['description'])) {
        $errors['description'] = "Service description is required.";
    } elseif (strlen($formData['description']) < 100 || strlen($formData['description']) > 2000) {
        $errors['description'] = "Description must be between 100 and 2000 characters.";
    }

    if (empty($formData['price'])) {
        $errors['price'] = "Price is required.";
    } elseif (!is_numeric($formData['price'])) {
        $errors['price'] = "Price must be a number.";
    } else {
        $price = floatval($formData['price']);
        if ($price < 5 || $price > 10000) {
            $errors['price'] = "Price must be between $5 and $10,000.";
        }
    }

    if (empty($formData['delivery_time'])) {
        $errors['delivery_time'] = "Delivery time is required.";
    } elseif (!ctype_digit($formData['delivery_time'])) {
        $errors['delivery_time'] = "Delivery time must be a whole number.";
    } else {
        $deliveryTime = intval($formData['delivery_time']);
        if ($deliveryTime < 1 || $deliveryTime > 90) {
            $errors['delivery_time'] = "Delivery time must be between 1 and 90 days.";
        }
    }

    if ($formData['revisions_included'] === "") {
        $errors['revisions_included'] = "Number of revisions is required.";
    } elseif (!ctype_digit($formData['revisions_included'])) {
        $errors['revisions_included'] = "Revisions must be a whole number.";
    } else {
        $revisions = intval($formData['revisions_included']);
        if ($revisions < 0 || $revisions > 999) {
            $errors['revisions_included'] = "Revisions must be between 0 and 999.";
        }
    }

    if (empty($errors['title'])) {
        $titleCheckSql = "SELECT service_id FROM services WHERE freelancer_id = :user_id AND title = :title";
        $titleCheckStmt = $pdo->prepare($titleCheckSql);
        $titleCheckStmt->execute([
            ":user_id" => $_SESSION["user_id"],
            ":title" => $formData['title']
        ]);
        if ($titleCheckStmt->fetch()) {
            $errors['title'] = "You already have a service with this title. Please choose a different title.";
        }
    }

    if (empty($errors)) {
        $_SESSION['service_draft']['basic_info'] = $formData;
        $_SESSION['service_draft']['step'] = 2;
        header("Location: " . url("create-service.php?step=2"));
        exit;
    }
}

ob_start();
?>
      <h1>Create New Service - Step 1 of 3</h1>

      <div class="breadcrumbs mb-3">
        <a href="<?= url('main.php') ?>">Home</a>
        <span class="breadcrumb-separator">></span>
        <a href="<?= url('profile/index.php') ?>">My Services</a>
        <span class="breadcrumb-separator">></span>
        <span class="breadcrumb-current">Create New Service</span>
      </div>

      <div class="step-indicator mb-3">
        <div class="step-item step-active">
          <span class="step-number">1</span>
          <span class="step-label">Basic Information</span>
        </div>
        <div class="step-item">
          <span class="step-number">2</span>
          <span class="step-label">Upload Images</span>
        </div>
        <div class="step-item">
          <span class="step-number">3</span>
          <span class="step-label">Review & Confirm</span>
        </div>
      </div>

      <div class="card">
        <h2>Basic Information</h2>
        <?php renderFlashMessages(); ?>
        
        <form method="POST" action="" class="form-container">
          <input type="hidden" name="step1_submit" value="1">
          
          <div class="form-group">
            <label class="form-label" for="title">Service Title <span class="required">*</span></label>
            <input type="text" id="title" name="title" required minlength="10" maxlength="100"
                   class="form-input <?= errorClass('title', $errors) ?>"
                   value="<?= htmlspecialchars($formData['title'] ?? '') ?>"
                   placeholder="example: I will create a professional WordPress website">
            <small class="text-muted">10-100 characters</small>
            <?php if (hasError('title', $errors)): ?>
              <small class="form-error"><?= htmlspecialchars($errors['title']) ?></small>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2">
            <div class="form-group form-group-flex">
              <label class="form-label" for="category">Category <span class="required">*</span></label>
              <select id="category" name="category" required class="form-select <?= errorClass('category', $errors) ?>">
                <option value="">Select category</option>
                <?php foreach ($categories as $cat => $subs): ?>
                  <option value="<?= htmlspecialchars($cat) ?>" <?= (($formData['category'] ?? '') === $cat) ? "selected" : "" ?>>
                    <?= htmlspecialchars($cat) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (hasError('category', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['category']) ?></small>
              <?php endif; ?>
            </div>

            <div class="form-group form-group-flex">
              <label class="form-label" for="subcategory">Subcategory <span class="required">*</span></label>
              <select id="subcategory" name="subcategory" required class="form-select <?= errorClass('subcategory', $errors) ?>">
                <option value="">Select subcategory</option>
                <?php foreach ($categories as $cat => $subs): ?>
                  <optgroup label="<?= htmlspecialchars($cat) ?>">
                    <?php foreach ($subs as $sub): ?>
                      <option value="<?= htmlspecialchars($sub) ?>" 
                              data-category="<?= htmlspecialchars($cat) ?>"
                              <?= (($formData['subcategory'] ?? '') === $sub && ($formData['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sub) ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <?php if (hasError('subcategory', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['subcategory']) ?></small>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="description">Service Description <span class="required">*</span></label>
            <textarea id="description" name="description" rows="6" required minlength="100" maxlength="2000"
                      class="form-textarea <?= errorClass('description', $errors) ?>"
                      placeholder="Describe what you are offering in detail"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
            <small class="text-muted">100-2000 characters</small>
            <?php if (hasError('description', $errors)): ?>
              <small class="form-error"><?= htmlspecialchars($errors['description']) ?></small>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2">
            <div class="form-group form-group-flex">
              <label class="form-label" for="price">Price ($) <span class="required">*</span></label>
              <input type="number" id="price" name="price" required min="5" max="10000" step="0.01"
                     class="form-input <?= errorClass('price', $errors) ?>"
                     value="<?= htmlspecialchars($formData['price'] ?? '') ?>">
              <small class="text-muted">$5 - $10,000</small>
              <?php if (hasError('price', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['price']) ?></small>
              <?php endif; ?>
            </div>

            <div class="form-group form-group-flex">
              <label class="form-label" for="delivery_time">Delivery Time (days) <span class="required">*</span></label>
              <input type="number" id="delivery_time" name="delivery_time" required min="1" max="90"
                     class="form-input <?= errorClass('delivery_time', $errors) ?>"
                     value="<?= htmlspecialchars($formData['delivery_time'] ?? '') ?>">
              <small class="text-muted">1-90 days</small>
              <?php if (hasError('delivery_time', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['delivery_time']) ?></small>
              <?php endif; ?>
            </div>

            <div class="form-group form-group-flex">
              <label class="form-label" for="revisions_included">Revisions Included <span class="required">*</span></label>
              <input type="number" id="revisions_included" name="revisions_included" required min="0" max="999"
                     class="form-input <?= errorClass('revisions_included', $errors) ?>"
                     value="<?= htmlspecialchars($formData['revisions_included'] ?? '') ?>">
              <small class="text-muted">0-999</small>
              <?php if (hasError('revisions_included', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['revisions_included']) ?></small>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Continue to Step 2</button>
            <a href="<?= url('profile/index.php') ?>" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
<?php
$content = ob_get_clean();
renderPage('Create Service - Step 1', $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);

