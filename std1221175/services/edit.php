<?php
require_once dirname(__DIR__) . '/db.php.inc';

requireFreelancer();

$service_id = $_GET["id"] ?? "";
if (empty($service_id) || !preg_match('/^[0-9]{10}$/', $service_id)) {
    header("Location: /std1221175/main.php");
    exit;
}

$error = "";
$success = "";
$service = null;

$categories = [
    "Web Development" => ["WordPress", "PHP", "JavaScript", "React", "Full Stack", "Performance", "UI/UX"],
    "Graphic Design" => ["Logo Design", "Brand Identity", "Illustration", "Print Design", "Social Media"],
    "Digital Marketing" => ["SEO", "Social Media Marketing", "PPC", "Content Marketing", "Email Marketing"],
    "Writing & Content" => ["Article Writing", "Copywriting", "Technical Writing", "Editing", "Translation"],
    "Video & Animation" => ["Video Editing", "Animation", "Motion Graphics", "Explainer Videos"],
    "Music & Audio" => ["Voice Over", "Mixing", "Music Production", "Sound Effects", "Podcast Editing"]
];

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
$maxFileSize = 2 * 1024 * 1024;

$sql = "SELECT * FROM services WHERE service_id = :service_id AND freelancer_id = :freelancer_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([":service_id" => $service_id, ":freelancer_id" => $_SESSION["user_id"]]);
$service = $stmt->fetch();

if (!$service) {
    header("Location: /std1221175/main.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $subcategory = trim($_POST["subcategory"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $delivery_time = trim($_POST["delivery_time"] ?? "");
    $revisions_included = trim($_POST["revisions_included"] ?? "");
    $status = trim($_POST["status"] ?? "Active");

    if (empty($title) || empty($category) || empty($subcategory) || empty($description) ||
        $price === "" || $delivery_time === "" || $revisions_included === "") {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || floatval($price) <= 0) {
        $error = "Price must be a number greater than 0.";
    } elseif (!ctype_digit($delivery_time) || intval($delivery_time) < 1) {
        $error = "Delivery time must be at least 1 day.";
    } elseif (!ctype_digit($revisions_included) || intval($revisions_included) < 0) {
        $error = "Revisions must be 0 or more.";
    } elseif (!in_array($status, ["Active", "Inactive"], true)) {
        $error = "Invalid service status.";
    } elseif (!array_key_exists($category, $categories)) {
        $error = "Invalid category selected.";
    } elseif (!in_array($subcategory, $categories[$category], true)) {
        $error = "Please choose a subcategory that matches the selected category.";
    } else {
        $image_1 = $service["image_1"];
        $image_2 = $service["image_2"];
        $image_3 = $service["image_3"];
        $uploadDir = basePath("uploads/services/");

        $result1 = processImageUpload($_FILES["image_1"] ?? null, $uploadDir, $allowedExtensions, $maxFileSize);
        if (!$result1['success']) {
            $error = "Main Image: " . $result1['error'];
        } elseif (!empty($result1['path'])) {
            $image_1 = $result1['path'];
        }

        if (empty($error)) {
            $result2 = processImageUpload($_FILES["image_2"] ?? null, $uploadDir, $allowedExtensions, $maxFileSize);
            if (!$result2['success']) {
                $error = "Image 2: " . $result2['error'];
            } elseif (!empty($result2['path'])) {
                $image_2 = $result2['path'];
            }
        }

        if (empty($error)) {
            $result3 = processImageUpload($_FILES["image_3"] ?? null, $uploadDir, $allowedExtensions, $maxFileSize);
            if (!$result3['success']) {
                $error = "Image 3: " . $result3['error'];
            } elseif (!empty($result3['path'])) {
                $image_3 = $result3['path'];
            }
        }

        if (empty($error)) {
            $updateSql = "UPDATE services
                         SET title = :title, category = :category, subcategory = :subcategory,
                             description = :description, price = :price, delivery_time = :delivery_time,
                             revisions_included = :revisions_included, status = :status,
                             image_1 = :image_1, image_2 = :image_2, image_3 = :image_3
                         WHERE service_id = :service_id AND freelancer_id = :freelancer_id";

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ":title" => $title,
                ":category" => $category,
                ":subcategory" => $subcategory,
                ":description" => $description,
                ":price" => floatval($price),
                ":delivery_time" => intval($delivery_time),
                ":revisions_included" => intval($revisions_included),
                ":status" => $status,
                ":image_1" => $image_1,
                ":image_2" => $image_2,
                ":image_3" => $image_3,
                ":service_id" => $service_id,
                ":freelancer_id" => $_SESSION["user_id"]
            ]);

            $success = "Service updated successfully!";
            $stmt->execute([":service_id" => $service_id, ":freelancer_id" => $_SESSION["user_id"]]);
            $service = $stmt->fetch();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Service - MO Freelancing</title>
  <link rel="stylesheet" href="/std1221175/css/main.css">
</head>
<body>

<?php includeTemplate('header'); ?>

<div class="page-container">
  <?php includeTemplate('nav'); ?>

  <main class="main-content">
    <div class="container">
      <div class="breadcrumbs">
        <a href="/std1221175/profile/index.php">My Profile</a>
        <span class="breadcrumb-separator">&gt;</span>
        <span class="breadcrumb-current">Edit Service</span>
      </div>

      <h1>Edit Service</h1>

      <div class="card">
        <?php if (!empty($error)): ?>
          <div class="message-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="message-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="form-container">
          <div class="form-group">
            <label class="form-label" for="title">Service Title <span class="required">*</span></label>
            <input type="text" id="title" name="title" required maxlength="200"
                   class="form-input"
                   value="<?= htmlspecialchars($service["title"]) ?>">
          </div>

          <div class="d-flex gap-2">
            <div class="form-group form-group-flex">
              <label class="form-label" for="category">Category <span class="required">*</span></label>
              <select id="category" name="category" required class="form-select">
                <option value="">Select category</option>
                <?php foreach ($categories as $cat => $subs): ?>
                  <option value="<?= htmlspecialchars($cat) ?>" <?= (($service["category"] ?? '') === $cat) ? "selected" : "" ?>>
                    <?= htmlspecialchars($cat) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group form-group-flex">
              <label class="form-label" for="subcategory">Subcategory <span class="required">*</span></label>
              <select id="subcategory" name="subcategory" required class="form-select">
                <option value="">Select subcategory</option>
                <?php
                foreach ($categories as $cat => $subs) {
                    foreach ($subs as $sub) {
                        $selected = (($service["subcategory"] ?? '') === $sub) ? "selected" : "";
                        echo '<option value="' . htmlspecialchars($sub) . '" ' . $selected . '>' . htmlspecialchars($sub) . '</option>';
                    }
                }
                ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="description">Service Description <span class="required">*</span></label>
            <textarea id="description" name="description" rows="6" required class="form-textarea"><?= htmlspecialchars($service["description"]) ?></textarea>
          </div>

          <div class="d-flex gap-2">
            <div class="form-group form-group-flex">
              <label class="form-label" for="price">Price ($) <span class="required">*</span></label>
              <input type="number" id="price" name="price" required min="0.01" step="0.01"
                     class="form-input"
                     value="<?= htmlspecialchars($service["price"]) ?>">
            </div>

            <div class="form-group form-group-flex">
              <label class="form-label" for="delivery_time">Delivery Time (days) <span class="required">*</span></label>
              <input type="number" id="delivery_time" name="delivery_time" required min="1"
                     class="form-input"
                     value="<?= htmlspecialchars($service["delivery_time"]) ?>">
            </div>

            <div class="form-group form-group-flex">
              <label class="form-label" for="revisions_included">Revisions Included <span class="required">*</span></label>
              <input type="number" id="revisions_included" name="revisions_included" required min="0"
                     class="form-input"
                     value="<?= htmlspecialchars($service["revisions_included"]) ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="status">Service Status <span class="required">*</span></label>
            <select id="status" name="status" required class="form-select">
              <option value="Active" <?= ($service["status"] === "Active") ? "selected" : "" ?>>Active</option>
              <option value="Inactive" <?= ($service["status"] === "Inactive") ? "selected" : "" ?>>Inactive</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Current Images</label>
            <div class="d-flex gap-2 mb-2">
              <?php if (!empty($service["image_1"])): ?>
                <div>
                  <img src="/std1221175/<?= htmlspecialchars($service["image_1"]) ?>" alt="Current Image 1" class="gallery-thumb">
                  <small class="text-muted d-block">Main Image</small>
                </div>
              <?php endif; ?>
              <?php if (!empty($service["image_2"])): ?>
                <div>
                  <img src="/std1221175/<?= htmlspecialchars($service["image_2"]) ?>" alt="Current Image 2" class="gallery-thumb">
                  <small class="text-muted d-block">Image 2</small>
                </div>
              <?php endif; ?>
              <?php if (!empty($service["image_3"])): ?>
                <div>
                  <img src="/std1221175/<?= htmlspecialchars($service["image_3"]) ?>" alt="Current Image 3" class="gallery-thumb">
                  <small class="text-muted d-block">Image 3</small>
                </div>
              <?php endif; ?>
            </div>

            <p class="text-muted text-sm mb-1">Allowed formats: JPG, JPEG, PNG, WEBP. Max size: 2MB per image.</p>

            <div class="d-flex gap-2">
              <div class="form-group form-group-flex">
                <label class="form-label" for="image_1">Replace Main Image</label>
                <input type="file" id="image_1" name="image_1" accept=".jpg,.jpeg,.png,.webp" class="form-input">
              </div>
              <div class="form-group form-group-flex">
                <label class="form-label" for="image_2">Replace Image 2</label>
                <input type="file" id="image_2" name="image_2" accept=".jpg,.jpeg,.png,.webp" class="form-input">
              </div>
              <div class="form-group form-group-flex">
                <label class="form-label" for="image_3">Replace Image 3</label>
                <input type="file" id="image_3" name="image_3" accept=".jpg,.jpeg,.png,.webp" class="form-input">
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Service</button>
            <a href="/std1221175/services/details.php?id=<?= htmlspecialchars($service_id) ?>" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>
