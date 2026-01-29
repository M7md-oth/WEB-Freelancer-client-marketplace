<?php

$basicInfo = $_SESSION['service_draft']['basic_info'] ?? [];
$images = $_SESSION['service_draft']['images'] ?? [];
$mainImageIndex = $_SESSION['service_draft']['main_image_index'] ?? 1;

if (empty($basicInfo) || empty($images)) {
    flashMessage("error", "Please complete all previous steps.");
    header("Location: " . url("create-service.php?step=1"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['step3_confirm'])) {
    $idSql = "SELECT MAX(CAST(service_id AS UNSIGNED)) as max_id FROM services";
    $idStmt = $pdo->query($idSql);
    $maxId = $idStmt->fetch()["max_id"];
    $newServiceId = $maxId ? (string)((int)$maxId + 1) : "2000000001";
    $newServiceId = str_pad($newServiceId, 10, "0", STR_PAD_LEFT);
    
    $serviceDir = basePath("uploads/services/{$newServiceId}/");
    if (!is_dir($serviceDir)) {
        mkdir($serviceDir, 0755, true);
    }
    
    $imagePaths = [null, null, null];
    
    $mainImage = $images[$mainImageIndex - 1] ?? null;
    $otherImages = [];
    foreach ($images as $index => $image) {
        if (($index + 1) != $mainImageIndex) {
            $otherImages[] = $image;
        }
    }
    
    if ($mainImage) {
        $tempPath = basePath($mainImage['path']);
        $ext = pathinfo($mainImage['path'], PATHINFO_EXTENSION);
        $newFilename = "image_01.{$ext}";
        $newPath = $serviceDir . $newFilename;
        
        if (file_exists($tempPath) && rename($tempPath, $newPath)) {
            $imagePaths[0] = "uploads/services/{$newServiceId}/{$newFilename}";
        }
    }
    
    foreach ($otherImages as $index => $image) {
        if ($index >= 2) break; 
        
        $tempPath = basePath($image['path']);
        $imageNumber = str_pad($index + 2, 2, "0", STR_PAD_LEFT);
        $ext = pathinfo($image['path'], PATHINFO_EXTENSION);
        $newFilename = "image_{$imageNumber}.{$ext}";
        $newPath = $serviceDir . $newFilename;
        
        if (file_exists($tempPath) && rename($tempPath, $newPath)) {
            $imagePaths[$index + 1] = "uploads/services/{$newServiceId}/{$newFilename}";
        }
    }
    
    $insertSql = "INSERT INTO services (service_id, freelancer_id, title, category, subcategory, description, price, delivery_time, revisions_included, image_1, image_2, image_3, status, featured_status, created_date) 
                  VALUES (:service_id, :freelancer_id, :title, :category, :subcategory, :description, :price, :delivery_time, :revisions_included, :image_1, :image_2, :image_3, 'Active', 'No', NOW())";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        ":service_id" => $newServiceId,
        ":freelancer_id" => $_SESSION["user_id"],
        ":title" => $basicInfo['title'],
        ":category" => $basicInfo['category'],
        ":subcategory" => $basicInfo['subcategory'],
        ":description" => $basicInfo['description'],
        ":price" => floatval($basicInfo['price']),
        ":delivery_time" => intval($basicInfo['delivery_time']),
        ":revisions_included" => intval($basicInfo['revisions_included']),
        ":image_1" => $imagePaths[0],
        ":image_2" => $imagePaths[1],
        ":image_3" => $imagePaths[2]
    ]);
    
    $tempDir = basePath("uploads/temp/services/");
    if (is_dir($tempDir)) {
        $files = glob($tempDir . "*");
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    
    unset($_SESSION['service_draft']);
    
    flashMessage("success", "Service created successfully! Service ID: {$newServiceId}");
    $_SESSION['created_service_id'] = $newServiceId;
    header("Location: " . url("profile/index.php"));
    exit;
}

ob_start();
?>
      <h1>Create New Service - Step 3 of 3</h1>

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
        <div class="step-item step-completed">
          <span class="step-number">2</span>
          <span class="step-label">Upload Images</span>
        </div>
        <div class="step-item step-active">
          <span class="step-number">3</span>
          <span class="step-label">Review & Confirm</span>
        </div>
      </div>

      <div class="card">
        <h2>Review & Confirm</h2>
        <p class="text-muted mb-3">Please review all information before confirming your service listing.</p>

        <form method="POST" action="" class="form-container">
          <input type="hidden" name="step3_confirm" value="1">
          
          <div class="form-section">
            <h3>Basic Information</h3>
            <div class="info-grid">
              <div>
                <strong>Service Title:</strong>
                <p><?= htmlspecialchars($basicInfo['title']) ?></p>
              </div>
              <div>
                <strong>Category:</strong>
                <p><?= htmlspecialchars($basicInfo['category']) ?></p>
              </div>
              <div>
                <strong>Subcategory:</strong>
                <p><?= htmlspecialchars($basicInfo['subcategory']) ?></p>
              </div>
              <div>
                <strong>Price:</strong>
                <p><?= formatPrice($basicInfo['price']) ?></p>
              </div>
              <div>
                <strong>Delivery Time:</strong>
                <p><?= htmlspecialchars($basicInfo['delivery_time']) ?> days</p>
              </div>
              <div>
                <strong>Revisions Included:</strong>
                <p><?= htmlspecialchars($basicInfo['revisions_included']) ?></p>
              </div>
            </div>
            <div class="mt-2">
              <strong>Description:</strong>
              <p><?= nl2br(htmlspecialchars($basicInfo['description'])) ?></p>
            </div>
          </div>

          <div class="form-section">
            <h3>Service Images</h3>
            <div class="image-review-grid">
              <?php foreach ($images as $index => $image): ?>
                <div class="image-review-item text-center">
                  <img src="<?= BASE_URL ?>/<?= htmlspecialchars($image['path']) ?>" alt="Service Image <?= $index + 1 ?>">
                  <?php if (($index + 1) == $mainImageIndex): ?>
                    <span class="badge badge-success">Main Image</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-actions">
            <a href="<?= url('create-service.php?step=2') ?>" class="btn btn-secondary">Back to Step 2</a>
            <button type="submit" class="btn btn-primary">Confirm & Create Service</button>
          </div>
        </form>
      </div>
<?php
$content = ob_get_clean();
renderPage('Create Service - Step 3', $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);

