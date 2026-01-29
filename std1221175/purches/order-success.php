<?php
require_once '../db.php.inc';

requireClient();

if (!isset($_SESSION['checkout_success']) || empty($_SESSION['checkout_success']['order_ids'])) {
    header("Location: " . url("services/browse.php"));
    exit;
}

$transactionId = $_SESSION['checkout_success']['transaction_id'] ?? '';
$orderIds = $_SESSION['checkout_success']['order_ids'] ?? [];

$orders = [];
foreach ($orderIds as $orderId) {
    $sql = "SELECT o.*, u.first_name, u.last_name, u.user_id as freelancer_id
            FROM orders o
            JOIN users u ON o.freelancer_id = u.user_id
            WHERE o.order_id = :order_id AND o.client_id = :client_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":order_id" => $orderId,
        ":client_id" => $_SESSION["user_id"]
    ]);
    $order = $stmt->fetch();
    if ($order) {
        $orders[] = $order;
    }
}

unset($_SESSION['checkout_success']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Success - MO Freelancing</title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>

<?php includeTemplate('header'); ?>

<div class="page-container">
  <?php includeTemplate('nav'); ?>

  <main class="main-content">
    <div class="container">
      
      <div class="order-success-header">
        <h1>Order Placed Successfully!</h1>
        <p class="text-muted">Your orders have been placed and the freelancers have been notified.</p>
        <?php if (!empty($transactionId)): ?>
          <p class="text-muted"><strong>Transaction ID:</strong> <?= htmlspecialchars($transactionId) ?></p>
        <?php endif; ?>
      </div>

      <div class="order-success-cards">
        <?php foreach ($orders as $order): ?>
          <div class="card order-success-card">
            <div class="order-success-card-header">
              <div>
                <h3>Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                <h4><?= htmlspecialchars($order['service_title']) ?></h4>
                <p class="text-muted">
                  Freelancer: <a href="<?= url("services/browse.php?freelancer_id=" . $order['freelancer_id']) ?>">
                    <?= formatFullName($order['first_name'], $order['last_name']) ?>
                  </a>
                </p>
              </div>
              <div class="order-success-badge">
                <span class="badge badge-pending">Pending</span>
              </div>
            </div>
            
            <div class="order-success-card-body">
              <div class="order-success-info-row">
                <span class="order-success-label">Total Amount:</span>
                <span class="order-success-value"><?= formatPrice($order['price'] + ($order['price'] * 0.05)) ?></span>
              </div>
              <div class="order-success-info-row">
                <span class="order-success-label">Expected Delivery:</span>
                <span class="order-success-value"><?= formatDate($order['expected_delivery']) ?></span>
              </div>
            </div>
            
            <div class="order-success-card-actions">
              <a href="<?= url("orders/details.php?id=" . $order['order_id']) ?>" class="btn btn-primary">View Order Details</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="order-success-actions">
        <a href="<?= url("orders/my_orders.php") ?>" class="btn btn-primary btn-large">View All Orders</a>
        <a href="<?= url("services/browse.php") ?>" class="btn btn-secondary btn-large">Browse More Services</a>
      </div>

    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>

