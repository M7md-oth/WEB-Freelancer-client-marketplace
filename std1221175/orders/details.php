<?php
require_once dirname(__DIR__) . '/db.php.inc';

requireLogin();

$order_id = $_GET["id"] ?? "";
if (empty($order_id) || !preg_match('/^[0-9]{10}$/', $order_id)) {
    header("Location: /std1221175/orders/my_orders.php");
    exit;
}

$success = $_GET["success"] ?? "";
$message = "";
$messageType = "success";

$allowedExtensions = ['pdf', 'zip', 'rar', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx'];
$maxFileSize = 10 * 1024 * 1024;

$allowedMimeTypes = [
    'pdf' => ['application/pdf'],
    'zip' => ['application/zip', 'application/x-zip-compressed'],
    'rar' => ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream'],
    'png' => ['image/png'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'webp' => ['image/webp'],
    'doc' => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
];

$order = null;
$sql = "SELECT o.*, 
               s.image_1, s.category, s.description as service_description,
               c.first_name as client_first, c.last_name as client_last, c.email as client_email,
               f.first_name as freelancer_first, f.last_name as freelancer_last, f.email as freelancer_email
        FROM orders o
        JOIN services s ON o.service_id = s.service_id
        JOIN users c ON o.client_id = c.user_id
        JOIN users f ON o.freelancer_id = f.user_id
        WHERE o.order_id = :order_id 
        AND (o.client_id = :user_id OR o.freelancer_id = :user_id2)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":order_id" => $order_id,
    ":user_id" => $_SESSION["user_id"],
    ":user_id2" => $_SESSION["user_id"]
]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: /std1221175/orders/my_orders.php");
    exit;
}

$filesSql = "SELECT * FROM file_attachments WHERE order_id = :order_id ORDER BY upload_timestamp";
$filesStmt = $pdo->prepare($filesSql);
$filesStmt->execute([":order_id" => $order_id]);
$files = $filesStmt->fetchAll();

$requirementFiles = array_filter($files, function($f) { return $f["file_type"] === "requirement"; });
$deliverableFiles = array_filter($files, function($f) { return $f["file_type"] === "deliverable"; });

$revisionsSql = "SELECT * FROM revision_requests WHERE order_id = :order_id ORDER BY request_date DESC";
$revisionsStmt = $pdo->prepare($revisionsSql);
$revisionsStmt->execute([":order_id" => $order_id]);
$revisions = $revisionsStmt->fetchAll();

$isClient = $order["client_id"] === $_SESSION["user_id"];
$isFreelancer = $order["freelancer_id"] === $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    if ($action === "start_work" && $isFreelancer && $order["status"] === "Pending") {
        $updateSql = "UPDATE orders SET status = 'In Progress' WHERE order_id = :order_id";
        $pdo->prepare($updateSql)->execute([":order_id" => $order_id]);
        $message = "Order status updated to In Progress.";
        $messageType = "success";
        $order["status"] = "In Progress";
    }
    
    elseif ($action === "deliver" && $isFreelancer && in_array($order["status"], ["In Progress", "Revision Requested"])) {
        $deliveryMessage = trim($_POST["delivery_message"] ?? "");
        $notes = trim($_POST["deliverable_notes"] ?? "");
        
        $errors = [];
        
        if (empty($deliveryMessage)) {
            $errors["delivery_message"] = "Delivery message is required.";
        } elseif (strlen($deliveryMessage) < 50 || strlen($deliveryMessage) > 500) {
            $errors["delivery_message"] = "Delivery message must be between 50 and 500 characters.";
        }
        
        $uploadedFiles = [];
        $maxFileSize = 50 * 1024 * 1024;
        
        for ($i = 1; $i <= 5; $i++) {
            $fileKey = "deliverable_file_{$i}";
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fileKey];
                
                if ($file['size'] > $maxFileSize) {
                    $errors[$fileKey] = "File size exceeds 50MB limit.";
                    continue;
                }
                
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    $errors[$fileKey] = "Invalid file type.";
                    continue;
                }
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if (!isset($allowedMimeTypes[$ext]) || !in_array($mimeType, $allowedMimeTypes[$ext])) {
                    if ($mimeType !== 'application/octet-stream' || !in_array($ext, ['zip', 'rar', 'doc', 'docx'])) {
                        $errors[$fileKey] = "File type does not match extension.";
                        continue;
                    }
                }
                
                $uploadedFiles[] = $file;
            }
        }
        
        if (count($uploadedFiles) === 0) {
            $errors["files"] = "At least one delivery file is required.";
        } elseif (count($uploadedFiles) > 5) {
            $errors["files"] = "Maximum 5 files allowed.";
        }
        
        if (empty($errors)) {
            $uploadDir = basePath("uploads/orders/" . $order_id . "/deliverables/");
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allFilesUploaded = true;
            $fileErrors = [];
            
            foreach ($uploadedFiles as $file) {
                $originalName = $file['name'];
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $safeFilename = "del_" . uniqid() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                $targetPath = $uploadDir . $safeFilename;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $fileSql = "INSERT INTO file_attachments (order_id, file_path, original_filename, file_size, file_type) 
                                VALUES (:order_id, :file_path, :original_filename, :file_size, 'deliverable')";
                    $pdo->prepare($fileSql)->execute([
                        ":order_id" => $order_id,
                        ":file_path" => "uploads/orders/" . $order_id . "/deliverables/" . $safeFilename,
                        ":original_filename" => $originalName,
                        ":file_size" => $file['size']
                    ]);
                } else {
                    $allFilesUploaded = false;
                    $fileErrors[] = "Failed to upload: " . $originalName;
                }
            }
            
            if ($allFilesUploaded) {
                $updateSql = "UPDATE orders SET status = 'Delivered', deliverable_notes = :notes WHERE order_id = :order_id";
                $pdo->prepare($updateSql)->execute([
                    ":order_id" => $order_id, 
                    ":notes" => $deliveryMessage . (!empty($notes) ? "\n\nAdditional Notes: " . $notes : "")
                ]);
                
                $message = "Order delivered successfully!";
                $messageType = "success";
                $order["status"] = "Delivered";
            } else {
                $message = "Some files failed to upload: " . implode(", ", $fileErrors);
                $messageType = "error";
            }
        } else {
            $message = "Validation failed: " . implode(" ", $errors);
            $messageType = "error";
        }
    }
    
    elseif ($action === "complete" && $isClient && $order["status"] === "Delivered") {
        $updateSql = "UPDATE orders SET status = 'Completed', completion_date = NOW() WHERE order_id = :order_id";
        $pdo->prepare($updateSql)->execute([":order_id" => $order_id]);
        $message = "Order marked as completed. Thank you!";
        $messageType = "success";
        $order["status"] = "Completed";
    }
    
    elseif ($action === "accept_revision" && $isFreelancer && $order["status"] === "Revision Requested") {
        $revisionId = (int)($_POST["revision_id"] ?? 0);
        
        $revSql = "SELECT * FROM revision_requests WHERE revision_id = :revision_id AND order_id = :order_id AND request_status = 'Pending'";
        $revStmt = $pdo->prepare($revSql);
        $revStmt->execute([
            ":revision_id" => $revisionId,
            ":order_id" => $order_id
        ]);
        $revision = $revStmt->fetch();
        
        if ($revision) {
            $updateRevSql = "UPDATE revision_requests SET request_status = 'Accepted', response_date = NOW() WHERE revision_id = :revision_id";
            $pdo->prepare($updateRevSql)->execute([":revision_id" => $revisionId]);
            
            $updateSql = "UPDATE orders SET status = 'Delivered' WHERE order_id = :order_id";
            $pdo->prepare($updateSql)->execute([":order_id" => $order_id]);
            
            $message = "Revision request accepted. You can now upload the revised work.";
            $messageType = "success";
            $order["status"] = "Delivered";
        } else {
            $message = "Revision request not found or already processed.";
            $messageType = "error";
        }
    }
    
    elseif ($action === "reject_revision" && $isFreelancer && $order["status"] === "Revision Requested") {
        $revisionId = (int)($_POST["revision_id"] ?? 0);
        $rejectionReason = trim($_POST["rejection_reason"] ?? "");
        
        if (empty($rejectionReason)) {
            $message = "Rejection reason is required.";
            $messageType = "error";
        } elseif (strlen($rejectionReason) < 50 || strlen($rejectionReason) > 500) {
            $message = "Rejection reason must be between 50 and 500 characters.";
            $messageType = "error";
        } else {
            $revSql = "SELECT * FROM revision_requests WHERE revision_id = :revision_id AND order_id = :order_id AND request_status = 'Pending'";
            $revStmt = $pdo->prepare($revSql);
            $revStmt->execute([
                ":revision_id" => $revisionId,
                ":order_id" => $order_id
            ]);
            $revision = $revStmt->fetch();
            
            if ($revision) {
                $updateRevSql = "UPDATE revision_requests SET request_status = 'Rejected', freelancer_response = :response, response_date = NOW() WHERE revision_id = :revision_id";
                $pdo->prepare($updateRevSql)->execute([
                    ":revision_id" => $revisionId,
                    ":response" => $rejectionReason
                ]);
                
                $updateSql = "UPDATE orders SET status = 'Delivered' WHERE order_id = :order_id";
                $pdo->prepare($updateSql)->execute([":order_id" => $order_id]);
                
                $message = "Revision request rejected. Client has been notified.";
                $messageType = "success";
                $order["status"] = "Delivered";
            } else {
                $message = "Revision request not found or already processed.";
                $messageType = "error";
            }
        }
    }
    
    elseif ($action === "cancel" && ($isClient || $isFreelancer) && $order["status"] === "Pending") {
        $updateSql = "UPDATE orders SET status = 'Cancelled' WHERE order_id = :order_id";
        $pdo->prepare($updateSql)->execute([":order_id" => $order_id]);
        $message = "Order has been cancelled.";
        $messageType = "success";
        $order["status"] = "Cancelled";
    }
    
    if ($messageType === "success" && !empty($message)) {
        header("Location: /std1221175/orders/details.php?id=" . $order_id . "&msg=" . urlencode($message) . "&type=success");
        exit;
    } elseif ($messageType === "error" && !empty($message)) {
        header("Location: /std1221175/orders/details.php?id=" . $order_id . "&msg=" . urlencode($message) . "&type=error");
        exit;
    }
}

$message = $_GET["msg"] ?? "";
$messageType = $_GET["type"] ?? "success";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order #<?= htmlspecialchars($order_id) ?> - MO Freelancing</title>
  <link rel="stylesheet" href="/std1221175/css/main.css">
</head>
<body>

<?php includeTemplate('header'); ?>

<div class="page-container">
  <?php includeTemplate('nav'); ?>

  <main class="main-content">
    <div class="container">
      <div class="breadcrumbs">
        <a href="/std1221175/orders/my_orders.php">My Orders</a>
        <span class="breadcrumb-separator">&gt;</span>
        <span class="breadcrumb-current">Order #<?= htmlspecialchars($order_id) ?></span>
      </div>

      <?php if ($success === "1"): ?>
        <div class="message-success">Order placed successfully! The freelancer will be notified.</div>
      <?php endif; ?>

      <?php if (!empty($message)): ?>
        <div class="message-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <div class="d-flex gap-3">
        <div style="flex:2">
          <div class="card">
            <div class="d-flex justify-between align-center mb-2">
              <div>
                <h1><?= htmlspecialchars($order["service_title"]) ?></h1>
                <p class="text-muted">Order #<?= htmlspecialchars($order_id) ?></p>
              </div>
              <span class="badge <?= getStatusClass($order["status"]) ?>">
                <?= htmlspecialchars($order["status"]) ?>
              </span>
            </div>
            
            <div class="info-grid">
              <div class="info-card">
                <span class="info-label">Service Category</span>
                <span class="info-value"><?= htmlspecialchars($order["category"] ?? 'N/A') ?></span>
              </div>
              <div class="info-card">
                <span class="info-label">Order Date</span>
                <span class="info-value"><?= date("M d, Y", strtotime($order["order_date"])) ?></span>
              </div>
              <div class="info-card">
                <span class="info-label">Expected Delivery</span>
                <span class="info-value"><?= date("M d, Y", strtotime($order["expected_delivery"])) ?></span>
              </div>
              <?php if ($order["status"] === "Delivered" && !empty($deliverableFiles)): 
                $firstDeliverable = reset($deliverableFiles);
              ?>
              <div class="info-card">
                <span class="info-label">Delivered On</span>
                <span class="info-value"><?= date("M d, Y", strtotime($firstDeliverable["upload_timestamp"])) ?></span>
              </div>
              <?php endif; ?>
              <div class="info-card">
                <span class="info-label">Service Price</span>
                <span class="info-value"><?= formatPrice($order["price"]) ?></span>
              </div>
              <div class="info-card">
                <span class="info-label">Service Fee (5%)</span>
                <span class="info-value"><?= formatPrice($order["price"] * 0.05) ?></span>
              </div>
              <div class="info-card">
                <span class="info-label">Total</span>
                <span class="info-value info-value-price"><?= formatPrice($order["price"] * 1.05) ?></span>
              </div>
              <div class="info-card">
                <span class="info-label">Payment Method</span>
                <span class="info-value"><?= htmlspecialchars($order["payment_method"]) ?></span>
              </div>
            </div>
            
            <?php if ($order["status"] === "Cancelled"): ?>
            <div class="order-cancellation-info mt-2">
              <?php if (!empty($order["deliverable_notes"]) && strpos($order["deliverable_notes"], "Cancellation Reason:") === 0): ?>
                <p class="text-muted"><strong>Cancellation Reason:</strong> <?= nl2br(htmlspecialchars(substr($order["deliverable_notes"], 21))) ?></p>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>

          <div class="card">
            <h2>Requirements</h2>
            <p><?= nl2br(htmlspecialchars($order["requirements"])) ?></p>
          </div>

          <?php if (!empty($order["deliverable_notes"])): ?>
          <div class="card">
            <h2>Delivery Notes</h2>
            <p><?= nl2br(htmlspecialchars($order["deliverable_notes"])) ?></p>
          </div>
          <?php endif; ?>

          <?php if (count($requirementFiles) > 0): ?>
          <div class="card">
            <h2>Requirement Files</h2>
            <div class="file-list">
              <?php foreach ($requirementFiles as $file): ?>
                <div class="file-item">
                  <div class="file-icon" data-ext="<?= getFileExt($file["original_filename"]) ?>"></div>
                  <div class="file-info">
                    <a href="/std1221175/<?= htmlspecialchars($file["file_path"]) ?>" class="file-name" download>
                      <?= htmlspecialchars($file["original_filename"]) ?>
                    </a>
                    <div class="file-size"><?= formatFileSize($file["file_size"]) ?> • <?= date("M d, Y", strtotime($file["upload_timestamp"])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if (count($deliverableFiles) > 0): ?>
          <div class="card">
            <h2>Delivery Files</h2>
            <div class="file-list">
              <?php foreach ($deliverableFiles as $file): ?>
                <div class="file-item">
                  <div class="file-icon" data-ext="<?= getFileExt($file["original_filename"]) ?>"></div>
                  <div class="file-info">
                    <a href="/std1221175/<?= htmlspecialchars($file["file_path"]) ?>" class="file-name" download>
                      <?= htmlspecialchars($file["original_filename"]) ?>
                    </a>
                    <div class="file-size"><?= formatFileSize($file["file_size"]) ?> • <?= date("M d, Y", strtotime($file["upload_timestamp"])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if (count($files) === 0): ?>
          <div class="card">
            <h2>Files</h2>
            <p class="text-muted">No files uploaded</p>
          </div>
          <?php endif; ?>

          <?php if (count($revisions) > 0): ?>
          <div class="card">
            <h2>Revision History</h2>
            
            <?php
            $totalRequests = count($revisions);
            $acceptedCount = 0;
            $rejectedCount = 0;
            $pendingCount = 0;
            foreach ($revisions as $rev) {
                if ($rev["request_status"] === "Accepted") $acceptedCount++;
                elseif ($rev["request_status"] === "Rejected") $rejectedCount++;
                elseif ($rev["request_status"] === "Pending") $pendingCount++;
            }
            $revisionsIncluded = (int)$order["revisions_included"];
            $isUnlimited = ($revisionsIncluded === 999);
            $revisionsRemaining = $isUnlimited ? 999 : max(0, $revisionsIncluded - ($acceptedCount + $rejectedCount));
            ?>
            
            <div class="revision-summary-card">
              <h3>Revision Summary</h3>
              <div class="revision-summary-grid">
                <div class="revision-summary-item">
                  <span class="revision-summary-label">Total Requests:</span>
                  <span class="revision-summary-value"><?= $totalRequests ?></span>
                </div>
                <div class="revision-summary-item">
                  <span class="revision-summary-label">Accepted:</span>
                  <span class="revision-summary-value revision-summary-accepted"><?= $acceptedCount ?></span>
                </div>
                <div class="revision-summary-item">
                  <span class="revision-summary-label">Rejected:</span>
                  <span class="revision-summary-value revision-summary-rejected"><?= $rejectedCount ?></span>
                </div>
                <div class="revision-summary-item">
                  <span class="revision-summary-label">Pending:</span>
                  <span class="revision-summary-value revision-summary-pending"><?= $pendingCount ?></span>
                </div>
                <div class="revision-summary-item">
                  <span class="revision-summary-label">Remaining:</span>
                  <span class="revision-summary-value revision-summary-remaining"><?= $isUnlimited ? 'Unlimited' : $revisionsRemaining ?></span>
                </div>
              </div>
            </div>
            
            <div class="revision-history-table">
              <table class="table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Request Date</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Freelancer Response</th>
                    <th>Response Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($revisions as $index => $rev): ?>
                    <tr>
                      <td><?= $index + 1 ?></td>
                      <td><?= date("M d, Y", strtotime($rev["request_date"])) ?></td>
                      <td><?= htmlspecialchars(substr($rev["revision_notes"], 0, 100)) ?><?= strlen($rev["revision_notes"]) > 100 ? '...' : '' ?></td>
                      <td>
                        <span class="badge <?= $rev["request_status"] === "Pending" ? "status-pending" : ($rev["request_status"] === "Accepted" ? "status-completed" : "status-cancelled") ?>">
                          <?= htmlspecialchars($rev["request_status"]) ?>
                        </span>
                      </td>
                      <td><?= !empty($rev["freelancer_response"]) ? htmlspecialchars(substr($rev["freelancer_response"], 0, 100)) . (strlen($rev["freelancer_response"]) > 100 ? '...' : '') : '-' ?></td>
                      <td><?= !empty($rev["response_date"]) ? date("M d, Y", strtotime($rev["response_date"])) : '-' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <div style="flex:1">
          <div class="card">
            <h3><?= $isClient ? "Freelancer" : "Client" ?></h3>
            <p class="fw-bold">
              <?= $isClient 
                  ? htmlspecialchars($order["freelancer_first"] . " " . $order["freelancer_last"])
                  : htmlspecialchars($order["client_first"] . " " . $order["client_last"]) ?>
            </p>
          </div>

          <div class="card">
            <h3>Actions</h3>
            
            <?php if ($isFreelancer && $order["status"] === "Pending"): ?>
              <form method="POST" class="mb-2">
                <input type="hidden" name="action" value="start_work">
                <button type="submit" class="btn btn-primary w-100">Start Working</button>
              </form>
            <?php endif; ?>

            <?php if ($isFreelancer && in_array($order["status"], ["In Progress", "Revision Requested"])): ?>
              <form method="POST" enctype="multipart/form-data" class="form-container">
                <input type="hidden" name="action" value="deliver">
                <div class="form-group">
                  <label class="form-label">Delivery Message <span class="required">*</span></label>
                  <textarea name="delivery_message" rows="4" required class="form-textarea" placeholder="Describe (50-500 characters)"></textarea>
                  <small class="text-muted">Required: 50-500 characters</small>
                </div>
                <div class="form-group">
                  <label class="form-label">Additional Notes (Optional)</label>
                  <textarea name="deliverable_notes" rows="3" class="form-textarea" placeholder="Any notes..."></textarea>
                </div>
                <div class="form-group">
                  <label class="form-label">Delivery Files <span class="required">*</span></label>
                  <small class="text-muted d-block mb-2">Upload 1-5 files. Max 50MB per file. Allowed: pdf, zip, rar, png, jpg, jpeg, webp, doc, docx</small>
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="form-group <?= $i === 1 ? '' : 'mt-2' ?>">
                      <input type="file" name="deliverable_file_<?= $i ?>" accept=".pdf,.zip,.rar,.png,.jpg,.jpeg,.webp,.doc,.docx" class="form-input">
                    </div>
                  <?php endfor; ?>
                </div>
                <button type="submit" class="btn btn-success w-100">Upload Delivery</button>
              </form>
            <?php endif; ?>

            <?php if ($isClient && $order["status"] === "Delivered"): ?>
              <a href="<?= url('orders/complete.php?id=' . $order_id) ?>" class="btn btn-success w-100 mb-2">Mark as Completed</a>
              
              <?php
              $revisionsIncluded = (int)$order["revisions_included"];
              $revisionsSql = "SELECT COUNT(*) as used_count FROM revision_requests WHERE order_id = :order_id AND request_status IN ('Accepted', 'Rejected')";
              $revisionsStmt = $pdo->prepare($revisionsSql);
              $revisionsStmt->execute([":order_id" => $order_id]);
              $revisionsUsed = (int)$revisionsStmt->fetch()["used_count"];
              $isUnlimited = ($revisionsIncluded === 999);
              $revisionsRemaining = $isUnlimited ? 999 : max(0, $revisionsIncluded - $revisionsUsed);
              ?>
              
              <?php if ($isUnlimited || $revisionsRemaining > 0): ?>
                <a href="<?= url('orders/request-revision.php?id=' . $order_id) ?>" class="btn btn-danger w-100">Request Revision</a>
              <?php else: ?>
                <div class="message-error text-sm">You have used all <?= $revisionsIncluded ?> revision requests for this order.</div>
              <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($isFreelancer && $order["status"] === "Revision Requested"): ?>
              <?php
              $pendingRevSql = "SELECT * FROM revision_requests WHERE order_id = :order_id AND request_status = 'Pending' ORDER BY request_date DESC LIMIT 1";
              $pendingRevStmt = $pdo->prepare($pendingRevSql);
              $pendingRevStmt->execute([":order_id" => $order_id]);
              $pendingRevision = $pendingRevStmt->fetch();
              
              if ($pendingRevision):
                $revisionsIncluded = (int)$order["revisions_included"];
                $revisionsSql = "SELECT COUNT(*) as used_count FROM revision_requests WHERE order_id = :order_id AND request_status IN ('Accepted', 'Rejected')";
                $revisionsStmt = $pdo->prepare($revisionsSql);
                $revisionsStmt->execute([":order_id" => $order_id]);
                $revisionsUsed = (int)$revisionsStmt->fetch()["used_count"];
                $isUnlimited = ($revisionsIncluded === 999);
                $revisionsRemaining = $isUnlimited ? 999 : max(0, $revisionsIncluded - $revisionsUsed);
              ?>
                <div class="revision-request-alert">
                  <div class="revision-request-alert-header">
                    <span class="revision-request-alert-icon">⚠️</span>
                    <span class="revision-request-alert-title">NEW REVISION REQUEST</span>
                  </div>
                  <div class="revision-request-alert-content">
                    <p class="revision-request-client-feedback"><?= nl2br(htmlspecialchars($pendingRevision["revision_notes"])) ?></p>
                    <p class="text-muted text-sm">Requested: <?= date("M d, Y g:i A", strtotime($pendingRevision["request_date"])) ?></p>
                    <p class="text-muted text-sm">Status: NEW - Awaiting Your Response</p>
                    <p class="text-muted text-sm">Client's Revisions: <?= $revisionsUsed ?>/<?= $isUnlimited ? '∞' : $revisionsIncluded ?> used<?= !$isUnlimited && $revisionsRemaining <= 1 ? ' (This is their ' . ($revisionsUsed + 1) . ($revisionsUsed + 1 === $revisionsIncluded ? 'th and final' : 'th') . ' request)' : '' ?></p>
                  </div>
                  <div class="revision-request-actions">
                    <form method="POST" class="mb-2">
                      <input type="hidden" name="action" value="accept_revision">
                      <input type="hidden" name="revision_id" value="<?= $pendingRevision["revision_id"] ?>">
                      <button type="submit" class="btn btn-success w-100">Accept & Upload Revision</button>
                    </form>
                    <button type="button" class="btn btn-danger w-100">Reject Request</button>
                    <form method="POST" id="reject-form-<?= $pendingRevision["revision_id"] ?>" style="display:none;" class="form-container mt-2">
                      <input type="hidden" name="action" value="reject_revision">
                      <input type="hidden" name="revision_id" value="<?= $pendingRevision["revision_id"] ?>">
                      <div class="form-group">
                        <label class="form-label">Reason for Rejection <span class="required">*</span></label>
                        <textarea name="rejection_reason" rows="4" required class="form-textarea" placeholder="Explain why you are reject this revision request (50-500 characters)"></textarea>
                        <small class="text-muted">50-500 characters required</small>
                      </div>
                      <button type="submit" class="btn btn-danger w-100">Submit Rejection</button>
                      <button type="button" class="btn btn-secondary w-100 mt-1">Cancel</button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($isClient && $order["status"] === "Pending"): ?>
              <a href="<?= url('orders/cancel.php?id=' . $order_id) ?>" class="btn btn-danger w-100 mt-2">Cancel Order</a>
            <?php endif; ?>

            <?php if ($order["status"] === "Completed"): ?>
              <div class="message-success">
                ✓ This order has been completed
                <?php if ($order["completion_date"]): ?>
                  <br><small>Completed on <?= date("M d, Y", strtotime($order["completion_date"])) ?></small>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($order["status"] === "Cancelled"): ?>
              <div class="message-error">This order was cancelled</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>
