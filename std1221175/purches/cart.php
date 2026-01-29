<?php
require_once '../db.php.inc';

requireClient();

includeComponent('service_card');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_GET['remove']) && !empty($_GET['remove'])) {
    $removeId = $_GET['remove'];
    if (isset($_SESSION['cart'][$removeId])) {
        unset($_SESSION['cart'][$removeId]);
        flashMessage("success", "Service removed from cart.");
        header("Location: " . url("purches/cart.php"));
        exit;
    }
}

$removedServices = [];
$validCartItems = [];

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $serviceId => $serializedService) {
        $service = unserialize($serializedService);
        if ($service instanceof Service) {
            $checkSql = "SELECT service_id, title, status FROM services WHERE service_id = :service_id";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([":service_id" => $serviceId]);
            $dbService = $checkStmt->fetch();
            
            if ($dbService && $dbService['status'] === 'Active') {
                $validCartItems[] = $service;
            } else {
                unset($_SESSION['cart'][$serviceId]);
                if ($dbService) {
                    $removedServices[] = $dbService['title'];
                }
            }
        } else {
            unset($_SESSION['cart'][$serviceId]);
        }
    }
}

if (!empty($removedServices)) {
    foreach ($removedServices as $title) {
        flashMessage("warning", "Service '" . htmlspecialchars($title) . "' is no longer available and has been removed.");
    }
}

$cartItems = $validCartItems;
$subtotal = 0;

foreach ($cartItems as $service) {
    $subtotal += $service->getPrice();
}

$serviceFee = $subtotal * 0.05;
$total = $subtotal + $serviceFee;

ob_start();
?>

      <?php if (!empty($cartItems)): ?>
        <div class="breadcrumbs">
          <a href="<?= url('main.php') ?>">Home</a>
          <span class="breadcrumb-separator">&gt;</span>
          <span class="breadcrumb-current">Cart</span>
        </div>

        <h1>Shopping Cart</h1>

        <div class="d-flex gap-3">
          <div style="flex: 2;">
            <div class="card">
              <table class="table">
                <thead>
                  <tr>
                    <th>Service</th>
                    <th>Freelancer</th>
                    <th>Category</th>
                    <th>Delivery</th>
                    <th>Revisions</th>
                    <th>Price</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cartItems as $service): ?>
                    <tr>
                      <td>
                        <div class="d-flex gap-2 align-center">
                          <a href="<?= url("services/details.php?id=" . $service->getServiceId()) ?>">
                            <img src="<?= !empty($service->getMainImagePath()) ? BASE_URL . "/" . htmlspecialchars($service->getMainImagePath()) : DEFAULT_SERVICE_IMAGE ?>" 
                                 alt="<?= htmlspecialchars($service->getTitle()) ?>" 
                                 width="100" height="75" style="object-fit:cover;border-radius:6px;">
                          </a>
                          <a href="<?= url("services/details.php?id=" . $service->getServiceId()) ?>">
                            <?= htmlspecialchars($service->getTitle()) ?>
                          </a>
                        </div>
                      </td>
                      <td>
                        <a href="<?= url("services/browse.php?freelancer_id=" . $service->getFreelancerId()) ?>">
                          <?= htmlspecialchars($service->getFreelancerName()) ?>
                        </a>
                      </td>
                      <td>
                        <small class="text-muted"><?= htmlspecialchars($service->getCategory()) ?></small>
                      </td>
                      <td><?= $service->getFormattedDelivery() ?></td>
                      <td><?= $service->getRevisionsIncluded() > 0 ? $service->getRevisionsIncluded() : 'Unlimited' ?></td>
                      <td class="fw-bold text-success"><?= $service->getFormattedPrice() ?></td>
                      <td>
                        <a href="<?= url("purches/cart.php?remove=" . $service->getServiceId()) ?>" 
                           class="btn btn-danger btn-sm">Remove</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div style="flex: 1;">
            <div class="card">
              <h3>Order Summary</h3>
              
              <div class="mt-2 mb-2">
                <div class="d-flex justify-between mb-1">
                  <span class="text-muted">Subtotal:</span>
                  <span><?= formatPrice($subtotal) ?></span>
                </div>
                <div class="d-flex justify-between mb-1">
                  <span class="text-muted">Service Fee (5%):</span>
                  <span><?= formatPrice($serviceFee) ?></span>
                </div>
                <div class="d-flex justify-between mt-2 pt-2" style="border-top:1px solid #DEE2E6;">
                  <span class="fw-bold">Total:</span>
                  <span class="fw-bold text-success"><?= formatPrice($total) ?></span>
                </div>
              </div>

              <a href="<?= url('purches/checkout.php') ?>" class="btn btn-primary w-100 mt-2">Proceed to Checkout</a>
              
              <a href="<?= url('services/browse.php') ?>" class="btn btn-secondary w-100 mt-1">Continue Shopping</a>
            </div>
          </div>
        </div>

      <?php else: ?>
        <div class="card text-center mt-3">
          <div class="mb-3">
            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1.5" style="margin: 0 auto; display: block;">
              <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-8 2a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
            </svg>
          </div>
          <div class="message-info mb-2">
            Your cart is empty
          </div>
          <a href="<?= url('services/browse.php') ?>" class="btn btn-primary">Browse Services</a>
        </div>

      <?php endif; ?>
      
      <?php
      $recentCookieName = "recent_services";
      $recentServiceIds = [];
      
      if (isset($_COOKIE[$recentCookieName])) {
          $recentServiceIds = explode(",", $_COOKIE[$recentCookieName]);
          $recentServiceIds = array_filter($recentServiceIds, function($id) {
              return preg_match('/^[0-9]{10}$/', $id);
          });
          $recentServiceIds = array_slice($recentServiceIds, -4);
      }
      
      if (!empty($recentServiceIds)) {
          $serviceModel = new ServiceModel($pdo);
          $placeholders = implode(',', array_fill(0, count($recentServiceIds), '?'));
          $fieldPlaceholders = implode(',', array_fill(0, count($recentServiceIds), '?'));
          $recentSql = "SELECT s.*, u.first_name, u.last_name, u.profile_photo 
                        FROM services s 
                        JOIN users u ON s.freelancer_id = u.user_id 
                        WHERE s.service_id IN ($placeholders) AND s.status = 'Active'
                        ORDER BY FIELD(s.service_id, $fieldPlaceholders)";
          $recentStmt = $pdo->prepare($recentSql);
          $recentStmt->execute(array_merge($recentServiceIds, $recentServiceIds));
          $recentServices = $recentStmt->fetchAll();
          
          if (!empty($recentServices)):
      ?>
        <div class="mt-4">
          <h2>Recently Viewed Services</h2>
          <div class="services-grid">
            <?php foreach ($recentServices as $recentService): ?>
              <?php renderServiceCard($recentService); ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php 
          endif;
      }
      ?>
<?php
$content = ob_get_clean();
renderPage('Shopping Cart', $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);

