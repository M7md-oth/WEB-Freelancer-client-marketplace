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

if ($order["status"] !== "Pending") {
    $_SESSION["error"] = "This order cannot be cancelled. Only pending orders can be cancelled.";
    header("Location: " . url("orders/details.php?id=" . $order_id));
    exit;
}

$errors = [];
$cancellationReason = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cancellationReason = trim($_POST["cancellation_reason"] ?? "");
    $confirmation = isset($_POST["confirmation"]) && $_POST["confirmation"] === "1";
    
    if (!$confirmation) {
        $errors["confirmation"] = "You must confirm the cancellation to proceed.";
    }
    
    if (!empty($cancellationReason) && strlen($cancellationReason) > 500) {
        $errors["cancellation_reason"] = "Cancellation reason must not exceed 500 characters.";
    }
    
    if (empty($errors)) {
        $checkSql = "SELECT status FROM orders WHERE order_id = :order_id AND client_id = :client_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([
            ":order_id" => $order_id,
            ":client_id" => $_SESSION["user_id"]
        ]);
        $checkOrder = $checkStmt->fetch();
        
        if ($checkOrder && $checkOrder["status"] === "Pending") {
            $updateSql = "UPDATE orders SET status = 'Cancelled' WHERE order_id = :order_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ":order_id" => $order_id
            ]);
            
            if (!empty($cancellationReason)) {
                $notesSql = "UPDATE orders SET deliverable_notes = :notes WHERE order_id = :order_id";
                $notesStmt = $pdo->prepare($notesSql);
                $notesStmt->execute([
                    ":order_id" => $order_id,
                    ":notes" => "Cancellation Reason: " . $cancellationReason
                ]);
            }
            
            $_SESSION["success"] = "Order cancelled successfully. A refund has been processed (simulated).";
            header("Location: " . url("orders/my_orders.php"));
            exit;
        } else {
            $errors["general"] = "This order can no longer be cancelled. The order status has changed.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cancel Order - MO Freelancing</title>
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
        <span class="breadcrumb-current">Cancel Order</span>
      </div>

      <?php if (isset($errors["general"])): ?>
        <div class="message-error"><?= htmlspecialchars($errors["general"]) ?></div>
      <?php endif; ?>

      <div class="card order-cancellation-warning">
        <div class="order-cancellation-header">
          <h1>Cancel Order</h1>
          <div class="warning-badge">
            <span class="warning-icon">⚠️</span>
            <span>Warning</span>
          </div>
        </div>
        
        <div class="order-cancellation-message">
          <p class="text-muted">You are about to cancel this order. This action cannot be undone.</p>
        </div>
      </div>

      <div class="card">
        <h2>Order Details</h2>
        <div class="order-cancellation-details">
          <div class="order-cancellation-detail-row">
            <span class="order-cancellation-label">Order ID:</span>
            <span class="order-cancellation-value"><?= htmlspecialchars($order_id) ?></span>
          </div>
          <div class="order-cancellation-detail-row">
            <span class="order-cancellation-label">Service:</span>
            <span class="order-cancellation-value"><?= htmlspecialchars($order["service_title"]) ?></span>
          </div>
          <div class="order-cancellation-detail-row">
            <span class="order-cancellation-label">Category:</span>
            <span class="order-cancellation-value"><?= htmlspecialchars($order["category"]) ?></span>
          </div>
          <div class="order-cancellation-detail-row">
            <span class="order-cancellation-label">Freelancer:</span>
            <span class="order-cancellation-value"><?= formatFullName($order["first_name"], $order["last_name"]) ?></span>
          </div>
          <div class="order-cancellation-detail-row">
            <span class="order-cancellation-label">Price:</span>
            <span class="order-cancellation-value"><?= formatPrice($order["price"]) ?></span>
          </div>
        </div>
      </div>

      <form method="POST" action="" class="card">
        <h2>Cancellation Information</h2>
        
        <div class="form-group">
          <label class="form-label" for="cancellation_reason">Cancellation Reason (Optional)</label>
          <textarea id="cancellation_reason" name="cancellation_reason" rows="4" class="form-textarea <?= errorClass('cancellation_reason', $errors) ?>"
                    placeholder="Ther reason for cancelling this order"><?= htmlspecialchars($cancellationReason) ?></textarea>
          <?php if (hasError('cancellation_reason', $errors)): ?>
            <small class="form-error"><?= htmlspecialchars($errors['cancellation_reason']) ?></small>
          <?php else: ?>
            <small class="text-muted">Optional: Help us understand why you're cancelling this order.</small>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="confirmation" value="1" required class="form-checkbox">
            <span>I confirm that I want to cancel this order. I understand that this action cannot be undone. <span class="required">*</span></span>
          </label>
          <?php if (hasError('confirmation', $errors)): ?>
            <small class="form-error"><?= htmlspecialchars($errors['confirmation']) ?></small>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-danger">Cancel Order</button>
          <a href="<?= url('orders/details.php?id=' . $order_id) ?>" class="btn btn-secondary">Go Back</a>
        </div>
      </form>

    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>

