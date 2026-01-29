<?php
require_once dirname(__DIR__) . '/db.php.inc';

requireLogin();

$status_filter = $_GET["status"] ?? "";

if ($_SESSION["role"] === "Client") {
    $sql = "SELECT o.*, s.image_1, u.first_name, u.last_name
            FROM orders o
            JOIN services s ON o.service_id = s.service_id
            JOIN users u ON o.freelancer_id = u.user_id
            WHERE o.client_id = :user_id";
} else {
    $sql = "SELECT o.*, s.image_1, u.first_name, u.last_name
            FROM orders o
            JOIN services s ON o.service_id = s.service_id
            JOIN users u ON o.client_id = u.user_id
            WHERE o.freelancer_id = :user_id";
}

$params = [":user_id" => $_SESSION["user_id"]];

if (!empty($status_filter)) {
    $sql .= " AND o.status = :status";
    $params[":status"] = $status_filter;
}

$sql .= " ORDER BY o.order_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

ob_start();
?>

      <h1><?= $_SESSION["role"] === "Client" ? "My Orders" : "Manage Orders" ?></h1>

      <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
          <div class="filter-group">
            <select name="status" class="filter-select">
              <option value="">All</option>
              <option value="Pending" <?= $status_filter === "Pending" ? "selected" : "" ?>>Pending</option>
              <option value="In Progress" <?= $status_filter === "In Progress" ? "selected" : "" ?>>In Progress</option>
              <option value="Delivered" <?= $status_filter === "Delivered" ? "selected" : "" ?>>Delivered</option>
              <option value="Completed" <?= $status_filter === "Completed" ? "selected" : "" ?>>Completed</option>
              <option value="Cancelled" <?= $status_filter === "Cancelled" ? "selected" : "" ?>>Cancelled</option>
              <option value="Revision Requested" <?= $status_filter === "Revision Requested" ? "selected" : "" ?>>Revision</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary filter-button">Apply Filter</button>
        </form>
      </div>

      <?php if (count($orders) === 0): ?>
        <div class="card text-center">
          <p class="text-muted">No orders found.</p>
          <?php if ($_SESSION["role"] === "Client"): ?>
            <a href="<?= url('services/browse.php') ?>" class="btn btn-primary">Browse Services</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="card">
          <table class="table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Image</th>
                <th>Service</th>
                <th><?= $_SESSION["role"] === "Client" ? "Freelancer" : "Client" ?></th>
                <th>Price</th>
                <th>Status</th>
                <th>Order Date</th>
                <th>Expected Delivery</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <tr>
                  <td>
                    <a href="<?= url('orders/details.php?id=' . $order["order_id"]) ?>" class="order-id-link">
                      <?= htmlspecialchars($order["order_id"]) ?>
                    </a>
                  </td>
                  <td>
                    <img src="<?= !empty($order["image_1"]) ? BASE_URL . "/" . htmlspecialchars($order["image_1"]) : DEFAULT_SERVICE_IMAGE ?>" alt="Service" class="avatar">
                  </td>
                  <td>
                    <a href="<?= url('orders/details.php?id=' . $order["order_id"]) ?>">
                      <?= htmlspecialchars($order["service_title"]) ?>
                    </a>
                  </td>
                  <td><?= formatFullName($order["first_name"], $order["last_name"]) ?></td>
                  <td><?= formatPrice($order["price"]) ?></td>
                  <td>
                    <span class="badge <?= getStatusClass($order["status"]) ?>">
                      <?= htmlspecialchars($order["status"]) ?>
                    </span>
                  </td>
                  <td><?= formatDate($order["order_date"]) ?></td>
                  <td><?= formatDate($order["expected_delivery"]) ?></td>
                  <td>
                    <a href="<?= url('orders/details.php?id=' . $order["order_id"]) ?>" class="btn btn-primary">View Details</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
<?php
$content = ob_get_clean();
renderPage($_SESSION["role"] === "Client" ? "My Orders" : "Manage Orders", $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);
