<?php
require_once dirname(__DIR__) . '/db.php.inc';

requireClient();

$order_id = $_GET["id"] ?? "";
if (empty($order_id) || !preg_match('/^[0-9]{10}$/', $order_id)) {
    header("Location: " . url("orders/my_orders.php"));
    exit;
}

$sql = "SELECT o.*, s.category, u.first_name, u.last_name
        FROM orders o
        JOIN services s ON o.service_id = s.service_id
        JOIN users u ON o.freelancer_id = u.user_id
        WHERE o.order_id = :order_id AND o.client_id = :client_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":order_id" => $order_id,
    ":client_id" => $_SESSION["user_id"]
]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION["error"] = "Order not found or access denied.";
    header("Location: " . url("orders/my_orders.php"));
    exit;
}

if ($order["status"] !== "Delivered") {
    $_SESSION["error"] = "This order cannot have a revision requested. Only delivered orders can have revision requests.";
    header("Location: " . url("orders/details.php?id=" . $order_id));
    exit;
}

$revisionsIncluded = (int)$order["revisions_included"];
$revisionsSql = "SELECT COUNT(*) as used_count FROM revision_requests WHERE order_id = :order_id AND request_status IN ('Accepted', 'Rejected')";
$revisionsStmt = $pdo->prepare($revisionsSql);
$revisionsStmt->execute([":order_id" => $order_id]);
$revisionsUsed = (int)$revisionsStmt->fetch()["used_count"];

$isUnlimited = ($revisionsIncluded === 999);
$revisionsRemaining = $isUnlimited ? 999 : max(0, $revisionsIncluded - $revisionsUsed);

if (!$isUnlimited && $revisionsRemaining <= 0) {
    $_SESSION["error"] = "You have used all {$revisionsIncluded} revision requests for this order.";
    header("Location: " . url("orders/details.php?id=" . $order_id));
    exit;
}

$errors = [];
$revisionDescription = "";
$confirmation = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $revisionDescription = trim($_POST["revision_description"] ?? "");
    $confirmation = isset($_POST["confirmation"]) && $_POST["confirmation"] === "1";
    
    if (empty($revisionDescription)) {
        $errors["revision_description"] = "Revision description is required.";
    } elseif (strlen($revisionDescription) < 50 || strlen($revisionDescription) > 500) {
        $errors["revision_description"] = "Revision description must be between 50 and 500 characters.";
    }
    
    if (!$confirmation) {
        $errors["confirmation"] = "You must confirm that this request will count toward your revision limit.";
    }
    
    $revisionsStmt->execute([":order_id" => $order_id]);
    $revisionsUsed = (int)$revisionsStmt->fetch()["used_count"];
    $revisionsRemaining = $isUnlimited ? 999 : max(0, $revisionsIncluded - $revisionsUsed);
    
    if (!$isUnlimited && $revisionsRemaining <= 0) {
        $errors["general"] = "You have used all {$revisionsIncluded} revision requests for this order.";
    }
    
    if (empty($errors)) {
        $checkSql = "SELECT status FROM orders WHERE order_id = :order_id AND client_id = :client_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([
            ":order_id" => $order_id,
            ":client_id" => $_SESSION["user_id"]
        ]);
        $checkOrder = $checkStmt->fetch();
        
        if ($checkOrder && $checkOrder["status"] === "Delivered") {
            $revSql = "INSERT INTO revision_requests (order_id, revision_notes, request_status) VALUES (:order_id, :notes, 'Pending')";
            $revStmt = $pdo->prepare($revSql);
            $revStmt->execute([
                ":order_id" => $order_id,
                ":notes" => $revisionDescription
            ]);
            
            $updateSql = "UPDATE orders SET status = 'Revision Requested' WHERE order_id = :order_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([":order_id" => $order_id]);
            
            $_SESSION["success"] = "Revision request submitted successfully.";
            header("Location: " . url("orders/details.php?id=" . $order_id));
            exit;
        } else {
            $errors["general"] = "This order can no longer have a revision requested. The order status has changed.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Request Revision - MO Freelancing</title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>

<?php includeTemplate('header'); ?>

<div class="page-container">
  <?php includeTemplate('nav'); ?>

  <main class="main-content">
    <div class="container">
      
      <div class="breadcrumbs">
        <a href="<?= url('orders/my_orders.php') ?>">My Orders</a>
        <span class="breadcrumb-separator">&gt;</span>
        <a href="<?= url('orders/details.php?id=' . $order_id) ?>">Order #<?= htmlspecialchars($order_id) ?></a>
        <span class="breadcrumb-separator">&gt;</span>
        <span class="breadcrumb-current">Request Revision</span>
      </div>

      <?php if (isset($errors["general"])): ?>
        <div class="message-error"><?= htmlspecialchars($errors["general"]) ?></div>
      <?php endif; ?>

      <div class="card revision-request-header">
        <h1>Request Revision</h1>
        
        <div class="revision-notice">
          <div class="revision-notice-icon">⚠️</div>
          <div class="revision-notice-content">
            <p class="revision-notice-title">This service includes <?= $isUnlimited ? 'unlimited' : $revisionsIncluded ?> revision request<?= $revisionsIncluded > 1 ? 's' : '' ?>.</p>
            <ul class="revision-notice-list">
              <li>ALL requests count (accepted + rejected)</li>
              <li>Freelancer may reject if request is outside original scope</li>
              <li>Rejected requests still count toward your limit</li>
              <li>Be clear and specific</li>
            </ul>
          </div>
        </div>
        
        <div class="revision-usage">
          <div class="revision-usage-item">
            <span class="revision-usage-label">Revisions Used:</span>
            <span class="revision-usage-value"><?= $revisionsUsed ?>/<?= $isUnlimited ? '∞' : $revisionsIncluded ?></span>
          </div>
          <div class="revision-usage-item">
            <span class="revision-usage-label">Revisions Remaining:</span>
            <span class="revision-usage-value"><?= $isUnlimited ? 'Unlimited' : $revisionsRemaining ?></span>
          </div>
        </div>
      </div>

      <form method="POST" action="" class="card">
        <h2>Revision Description</h2>
        
        <div class="form-group">
          <label class="form-label" for="revision_description">Describe what needs to be changed <span class="required">*</span></label>
          <p class="text-muted mb-2">Be specific about what needs change:</p>
          <ul class="text-muted mb-2" style="padding-left: 20px;">
            <li>What element to modify</li>
            <li>How to modify it</li>
            <li>Why it needs changing</li>
          </ul>
          <textarea id="revision_description" name="revision_description" rows="8" required class="form-textarea <?= errorClass('revision_description', $errors) ?>"
                    placeholder="provide in detailed  of what you needs to be revised (50-500 characters)"><?= htmlspecialchars($revisionDescription) ?></textarea>
          <?php if (hasError('revision_description', $errors)): ?>
            <small class="form-error"><?= htmlspecialchars($errors['revision_description']) ?></small>
          <?php else: ?>
            <small class="text-muted">50-500 characters required</small>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="confirmation" value="1" required class="form-checkbox">
            <span>I understand this request will count toward my revision limit. <span class="required">*</span></span>
          </label>
          <?php if (hasError('confirmation', $errors)): ?>
            <small class="form-error"><?= htmlspecialchars($errors['confirmation']) ?></small>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Submit Request</button>
          <a href="<?= url('orders/details.php?id=' . $order_id) ?>" class="btn btn-secondary">Cancel</a>
        </div>
      </form>

    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>

