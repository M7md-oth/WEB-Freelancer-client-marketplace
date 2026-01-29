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
    $_SESSION["error"] = "This order cannot be marked as completed. Only delivered orders can be completed.";
    header("Location: " . url("orders/details.php?id=" . $order_id));
    exit;
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $confirmation = isset($_POST["confirmation"]) && $_POST["confirmation"] === "1";
    
    if (!$confirmation) {
        $errors["confirmation"] = "You must confirm to proceed.";
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
            $updateSql = "UPDATE orders SET status = 'Completed', completion_date = NOW() WHERE order_id = :order_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([":order_id" => $order_id]);
            
            $_SESSION["success"] = "Order marked as completed. Thank you!";
            header("Location: " . url("orders/details.php?id=" . $order_id));
            exit;
        } else {
            $errors["general"] = "This order can no longer be marked as completed. The order status has changed.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Complete Order - MO Freelancing</title>
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
        <span class="breadcrumb-current">Complete Order</span>
      </div>

      <?php if (isset($errors["general"])): ?>
        <div class="message-error"><?= htmlspecialchars($errors["general"]) ?></div>
      <?php endif; ?>

      <div class="card order-completion-warning">
        <h1>Mark Order as Completed</h1>
        <div class="order-completion-message">
          <p class="text-muted">Are you sure you want to mark this order as completed? This action cannot be undone.</p>
        </div>
      </div>

      <div class="card">
        <h2>Order Details</h2>
        <div class="order-completion-details">
          <div class="order-completion-detail-row">
            <span class="order-completion-label">Order ID:</span>
            <span class="order-completion-value"><?= htmlspecialchars($order_id) ?></span>
          </div>
          <div class="order-completion-detail-row">
            <span class="order-completion-label">Service:</span>
            <span class="order-completion-value"><?= htmlspecialchars($order["service_title"]) ?></span>
          </div>
          <div class="order-completion-detail-row">
            <span class="order-completion-label">Category:</span>
            <span class="order-completion-value"><?= htmlspecialchars($order["category"]) ?></span>
          </div>
          <div class="order-completion-detail-row">
            <span class="order-completion-label">Freelancer:</span>
            <span class="order-completion-value"><?= formatFullName($order["first_name"], $order["last_name"]) ?></span>
          </div>
          <div class="order-completion-detail-row">
            <span class="order-completion-label">Price:</span>
            <span class="order-completion-value"><?= formatPrice($order["price"]) ?></span>
          </div>
        </div>
      </div>

      <form method="POST" action="" class="card">
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="confirmation" value="1" required class="form-checkbox">
            <span>I confirm that I want to mark this order as completed. I understand that this action cannot be undone. <span class="required">*</span></span>
          </label>
          <?php if (hasError('confirmation', $errors)): ?>
            <small class="form-error"><?= htmlspecialchars($errors['confirmation']) ?></small>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-success">Mark as Completed</button>
          <a href="<?= url('orders/details.php?id=' . $order_id) ?>" class="btn btn-secondary">Go Back</a>
        </div>
      </form>

    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>

