<?php

$errors = [];
$uploadedImages = $_SESSION['service_draft']['images'] ?? [];
$tempImageDir = basePath("uploads/temp/services/");

if (!is_dir($tempImageDir)) {
    mkdir($tempImageDir, 0755, true);
}

if (isset($_GET['confirm_remove'])) {
    $removeIndex = intval($_GET['confirm_remove']);
    if (isset($uploadedImages[$removeIndex - 1])) {
        $imageToRemove = $uploadedImages[$removeIndex - 1];
        $fullPath = basePath($imageToRemove['path']);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
        unset($uploadedImages[$removeIndex - 1]);
        $_SESSION['service_draft']['images'] = array_values($uploadedImages);
    }
    header("Location: " . url("create-service.php?step=2"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['step2_submit'])) {
    $mainImageIndex = intval($_POST['main_image'] ?? 0);
    $allowedExtensions = ['jpg', 'jpeg', 'png'];
    $maxFileSize = 5 * 1024 * 1024; 

    $newImages = [];

    for ($i = 1; $i <= 3; $i++) {
        if (isset($_FILES["image_{$i}"]) && $_FILES["image_{$i}"]["error"] !== UPLOAD_ERR_NO_FILE) {
            $result = processServiceImageUpload(
                $_FILES["image_{$i}"],
                $tempImageDir,
                $allowedExtensions,
                $maxFileSize,
                800, 
                600  
            );

            if (!$result['success']) {
                $errors["image_{$i}"] = $result['error'];
            } elseif (!empty($result['path'])) {
                $newImages[] = [
                    'path' => $result['path'],
                    'width' => $result['width'],
                    'height' => $result['height'],
                    'index' => $i
                ];
            }
        }
    }

    if (!empty($newImages)) {
        $_SESSION['service_draft']['images'] = array_merge($uploadedImages, $newImages);
        $uploadedImages = $_SESSION['service_draft']['images'];
    }

    $totalImages = count($_SESSION['service_draft']['images'] ?? []);
    if ($totalImages === 0 && empty($newImages)) {
        $errors['images'] = "Please upload at least one image for your service.";
    }

    if (empty($errors) && $totalImages > 0) {
        if ($mainImageIndex < 1 || $mainImageIndex > $totalImages) {
            $errors['main_image'] = "Please select a main image.";
        } else {
            $_SESSION['service_draft']['main_image_index'] = $mainImageIndex;
            $_SESSION['service_draft']['step'] = 3;
            header("Location: " . url("create-service.php?step=3"));
            exit;
        }
    }
}

ob_start();
?>
<h1>Create New Service - Step 2 of 3</h1>

<div class="breadcrumbs mb-3">
  <a href="<?= url('main.php') ?>">Home</a>
  <span class="breadcrumb-separator">></span>
  <a href="<?= url('profile/index.php') ?>">My Services</a>
  <span class="breadcrumb-separator">></span>
  <span class="breadcrumb-current">Create New Service</span>
</div>

<div class="step-indicator mb-3">
  <div class="step-item step-completed">
    <span class="step-number">1</span>
    <span class="step-label">Basic Information</span>
  </div>
  <div class="step-item step-active">
    <span class="step-number">2</span>
    <span class="step-label">Upload Images</span>
  </div>
  <div class="step-item">
    <span class="step-number">3</span>
    <span class="step-label">Review & Confirm</span>
  </div>
</div>

<div class="card">
  <h2>Upload Service Images</h2>
  <?php renderFlashMessages(); ?>

  <p class="text-muted mb-2">
    Upload 1-3 images for your service. Minimum dimensions: 800x600 pixels. Maximum file size: 5MB per image.
  </p>

  <?php if (hasError('images', $errors)): ?>
    <div class="message-error"><?= htmlspecialchars($errors['images']) ?></div>
  <?php endif; ?>

  <?php
  if (isset($_GET['remove_image'])):
    $removeIndexUi = intval($_GET['remove_image']);
    if (isset($uploadedImages[$removeIndexUi - 1])):
  ?>
    <div class="message-warning mb-2">
      Are you sure you want to remove this image?
      <div class="mt-2">
        <a href="<?= url('create-service.php?step=2&confirm_remove=' . $removeIndexUi) ?>" class="btn btn-primary btn-sm">Yes, Remove</a>
        <a href="<?= url('create-service.php?step=2') ?>" class="btn btn-secondary btn-sm">Cancel</a>
      </div>
    </div>
  <?php
    endif;
  endif;
  ?>

  <form method="POST" action="" enctype="multipart/form-data" class="form-container">
    <input type="hidden" name="step2_submit" value="1">

    <div class="form-group">
      <label class="form-label">Service Images</label>
      <p class="text-muted text-sm mb-2">Allowed formats: JPG, JPEG, PNG only. Max size: 5MB per image.</p>

      <div class="d-flex gap-2">
        <div class="form-group form-group-flex">
          <label class="form-label" for="image_1">Service Image 1 <?= count($uploadedImages) === 0 ? '<span class="required">*</span>' : '' ?></label>
          <input type="file" id="image_1" name="image_1" accept=".jpg,.jpeg,.png" class="form-input">
          <?php if (hasError('image_1', $errors)): ?>
            <small class="form-error"><?= htmlspecialchars($errors['image_1']) ?></small>
          <?php endif; ?>
        </div>

        <div class="form-group form-group-flex">
          <label class="form-label" for="image_2">Service Image 2 (Optional)</label>
          <input type="file" id="image_2" name="image_2" accept=".jpg,.jpeg,.png" class="form-input">
          <?php if (hasError('image_2', $errors)): ?>
            <small class="form-error"><?= htmlspecialchars($errors['image_2']) ?></small>
          <?php endif; ?>
        </div>

        <div class="form-group form-group-flex">
          <label class="form-label" for="image_3">Service Image 3 (Optional)</label>
          <input type="file" id="image_3" name="image_3" accept=".jpg,.jpeg,.png" class="form-input">
          <?php if (hasError('image_3', $errors)): ?>
            <small class="form-error"><?= htmlspecialchars($errors['image_3']) ?></small>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php
    $currentImages = $_SESSION['service_draft']['images'] ?? [];
    if (!empty($currentImages)):
    ?>
      <div class="form-group">
        <label class="form-label">Select Main Image <span class="required">*</span></label>
        <p class="text-muted text-sm mb-2">Choose one image below as the main image.</p>

        <div class="image-selection-grid">
          <?php foreach ($currentImages as $index => $image): ?>
            <div class="image-selection-item text-center">
              <label class="image-radio-label">
                <input type="radio"
                       name="main_image"
                       value="<?= $index + 1 ?>"
                       <?= ($_SESSION['service_draft']['main_image_index'] ?? 1) == ($index + 1) ? 'checked' : '' ?>
                       required>

                <div class="image-preview">
                  <img src="<?= BASE_URL ?>/<?= htmlspecialchars($image['path']) ?>" alt="Image <?= $index + 1 ?>">
                  <span class="image-badge">Main</span>
                </div>

                <div class="mt-1">
                  <a href="<?= url('create-service.php?step=2&remove_image=' . ($index + 1)) ?>"
                     class="btn btn-secondary btn-sm">Remove</a>
                </div>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (hasError('main_image', $errors)): ?>
          <small class="form-error"><?= htmlspecialchars($errors['main_image']) ?></small>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <a href="<?= url('create-service.php?step=1') ?>" class="btn btn-secondary">Back to Step 1</a>
      <button type="submit" class="btn btn-primary">Continue to Step 3</button>
    </div>
  </form>
</div>

<?php
$content = ob_get_clean();
renderPage('Create Service - Step 2', $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);
