<?php
require_once dirname(__DIR__) . '/db.php.inc';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_GET['add_to_cart']) && !empty($_GET['add_to_cart'])) {
    $addId = $_GET['add_to_cart'];

    if (!isset($_SESSION["user_id"])) {
        flashMessage("error", "Please login to add services to cart.");
        header("Location: " . url("auth/login.php"));
        exit;
    }

    if (preg_match('/^[0-9]{10}$/', $addId)) {
        $checkSql = "SELECT s.*, u.first_name, u.last_name 
                     FROM services s 
                     JOIN users u ON s.freelancer_id = u.user_id 
                     WHERE s.service_id = :service_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([":service_id" => $addId]);
        $checkService = $checkStmt->fetch();

        if (!$checkService) {
            flashMessage("error", "Service not found.");
            header("Location: " . url("services/details.php?id=" . $addId));
            exit;
        }

        if ($checkService['status'] !== 'Active') {
            flashMessage("error", "This service is no longer available.");
            header("Location: " . url("services/details.php?id=" . $addId));
            exit;
        }

        if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] == $checkService["freelancer_id"]) {
            flashMessage("error", "You cannot add your own service to cart.");
            header("Location: " . url("services/details.php?id=" . $addId));
            exit;
        }

        if (isset($_SESSION['cart'][$addId])) {
            flashMessage("error", "Service already in cart.");
            header("Location: " . url("services/details.php?id=" . $addId));
            exit;
        }

        $freelancerName = formatFullName($checkService['first_name'], $checkService['last_name']);
        $mainImagePath = !empty($checkService['image_1']) ? $checkService['image_1'] : '';
        $timestamp = date('Y-m-d H:i:s');

        $serviceObject = new Service(
            (int)$checkService['service_id'],
            $checkService['title'],
            $checkService['category'],
            $checkService['subcategory'],
            (float)$checkService['price'],
            (int)$checkService['delivery_time'],
            (int)$checkService['revisions_included'],
            (int)$checkService['freelancer_id'],
            $freelancerName,
            $mainImagePath,
            $timestamp
        );

        $_SESSION['cart'][$addId] = serialize($serviceObject);

        flashMessage("success", "Service added to cart successfully!");
        header("Location: " . url("services/details.php?id=" . $addId));
        exit;
    }
}

if (isset($_GET['order_now']) && !empty($_GET['order_now'])) {
    $orderId = $_GET['order_now'];

    if (!isset($_SESSION["user_id"])) {
        flashMessage("error", "Please login to order services.");
        header("Location: " . url("auth/login.php"));
        exit;
    }

    if (preg_match('/^[0-9]{10}$/', $orderId)) {
        $checkSql = "SELECT s.*, u.first_name, u.last_name 
                     FROM services s 
                     JOIN users u ON s.freelancer_id = u.user_id 
                     WHERE s.service_id = :service_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([":service_id" => $orderId]);
        $checkService = $checkStmt->fetch();

        if (!$checkService) {
            flashMessage("error", "Service not found.");
            header("Location: " . url("services/details.php?id=" . $orderId));
            exit;
        }

        if ($checkService['status'] !== 'Active') {
            flashMessage("error", "This service is no longer available.");
            header("Location: " . url("services/details.php?id=" . $orderId));
            exit;
        }

        if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] == $checkService["freelancer_id"]) {
            flashMessage("error", "You cannot order your own service.");
            header("Location: " . url("services/details.php?id=" . $orderId));
            exit;
        }

        if (!isset($_SESSION['cart'][$orderId])) {
            $freelancerName = formatFullName($checkService['first_name'], $checkService['last_name']);
            $mainImagePath = !empty($checkService['image_1']) ? $checkService['image_1'] : '';
            $timestamp = date('Y-m-d H:i:s');

            $serviceObject = new Service(
                (int)$checkService['service_id'],
                $checkService['title'],
                $checkService['category'],
                $checkService['subcategory'],
                (float)$checkService['price'],
                (int)$checkService['delivery_time'],
                (int)$checkService['revisions_included'],
                (int)$checkService['freelancer_id'],
                $freelancerName,
                $mainImagePath,
                $timestamp
            );

            $_SESSION['cart'][$orderId] = serialize($serviceObject);
        }

        flashMessage("success", "Service added to cart! Proceed to checkout to complete your order.");
        header("Location: " . url("purches/cart.php"));
        exit;
    }
}

$service_id = $_GET["id"] ?? "";
if (empty($service_id) || !preg_match('/^[0-9]{10}$/', $service_id)) {
    $service_id = "";
}

$service = null;
$canView = false;
$isOwner = false;
$showInactiveWarning = false;

if (!empty($service_id)) {
    $sql = "SELECT s.*, u.first_name, u.last_name, u.email, u.country, u.city, u.profile_photo, u.registration_date as freelancer_since
            FROM services s
            JOIN users u ON s.freelancer_id = u.user_id
            WHERE s.service_id = :service_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":service_id" => $service_id]);
    $service = $stmt->fetch();

    if ($service) {
        $isOwner = isset($_SESSION["user_id"]) && $_SESSION["user_id"] === $service["freelancer_id"];

        if ($service["status"] === "Active") {
            $canView = true;
        } elseif ($isOwner) {
            $canView = true;
            $showInactiveWarning = true;
        } else {
            $canView = false;
            $service = null;
        }

        if ($canView && $service) {
            $viewCookieName = "viewed_" . $service_id;
            if (!isset($_COOKIE[$viewCookieName])) {
                $updateSql = "UPDATE services SET views = views + 1 WHERE service_id = :service_id";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([":service_id" => $service_id]);
                setcookie($viewCookieName, "1", time() + 3600, "/");
            }

            $recentCookieName = "recent_services";
            $recentServices = [];

            if (isset($_COOKIE[$recentCookieName])) {
                $recentServices = explode(",", $_COOKIE[$recentCookieName]);
                $recentServices = array_filter($recentServices, function ($id) {
                    return preg_match('/^[0-9]{10}$/', $id);
                });
            }

            $recentServices = array_values(array_filter($recentServices, function ($id) use ($service_id) {
                return $id !== $service_id;
            }));

            $recentServices[] = $service_id;

            if (count($recentServices) > 4) {
                $recentServices = array_slice($recentServices, -4);
            }

            $cookieValue = implode(",", $recentServices);
            setcookie($recentCookieName, $cookieValue, time() + (30 * 24 * 60 * 60), "/");
        }
    }
}

$isGuest = !isset($_SESSION["user_id"]);
$isClient = isset($_SESSION["user_id"]) && $_SESSION["role"] === "Client";
$isFreelancer = isset($_SESSION["user_id"]) && $_SESSION["role"] === "Freelancer";
$isLoggedIn = isset($_SESSION["user_id"]);

ob_start();
?>
<?php if (!$service || !$canView): ?>
  <div class="message-error">Service not found or no longer available.</div>
  <a href="<?= url('services/browse.php') ?>" class="btn btn-secondary">Browse Services</a>
<?php else: ?>

  <?php if ($showInactiveWarning): ?>
    <div class="message-warning">
      This service is currently inactive and not visible to clients.
    </div>
  <?php endif; ?>

  <?php renderFlashMessages(); ?>

  <div class="breadcrumbs">
    <a href="<?= url('main.php') ?>">Home</a>
    <span class="breadcrumb-separator">&gt;</span>
    <a href="<?= url('services/browse.php?category=' . urlencode($service["category"])) ?>"><?= htmlspecialchars($service["category"]) ?></a>
    <span class="breadcrumb-separator">&gt;</span>
    <span class="breadcrumb-current"><?= htmlspecialchars($service["title"]) ?></span>
  </div>

  <div class="d-flex gap-3 service-details-layout">
    <div class="service-details-left">
      <h1><?= htmlspecialchars($service["title"]) ?></h1>

      <div class="d-flex gap-1 align-center mb-2">
        <span class="badge <?= $service["status"] === "Active" ? "status-active" : "status-inactive" ?>">
          <?= htmlspecialchars($service["status"]) ?>
        </span>
        <?php if ($service["featured_status"] === "Yes"): ?>
          <span class="badge badge-featured">Featured</span>
        <?php endif; ?>
        <span class="text-muted">
          <?= htmlspecialchars($service["category"]) ?> &gt; <?= htmlspecialchars($service["subcategory"]) ?>
        </span>
      </div>

      <div class="gallery">
        <?php
        $images = array_filter([
            '1' => $service["image_1"] ?? null,
            '2' => $service["image_2"] ?? null,
            '3' => $service["image_3"] ?? null
        ]);
        $hasImages = count($images) > 0;
        
        // If no images, use default image
        if (!$hasImages) {
            $images = ['1' => DEFAULT_SERVICE_IMAGE];
            $hasImages = true;
        }
        
        $firstImageKey = array_key_first($images);
        ?>

        <div class="gallery-main">
          <?php foreach ($images as $key => $img): ?>
            <div id="img<?= $key ?>" class="gallery-item <?= ($key === $firstImageKey) ? 'gallery-default' : '' ?>">
              <img src="<?= (strpos($img, 'http') === 0 || strpos($img, '/') === 0) ? $img : BASE_URL . "/" . htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($service["title"]) ?>">
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (count($images) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $key => $img): ?>
              <a href="#img<?= $key ?>" class="gallery-thumb">
                <img src="<?= (strpos($img, 'http') === 0 || strpos($img, '/') === 0) ? $img : BASE_URL . "/" . htmlspecialchars($img) ?>" alt="Thumbnail <?= $key ?>">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>About This Service</h2>
        <p><?= nl2br(htmlspecialchars($service["description"])) ?></p>
      </div>

      <div class="info-grid">
        <div class="info-card">
          <span class="info-label">Created</span>
          <span class="info-value"><?= formatDate($service["created_date"]) ?></span>
        </div>
      </div>
    </div>

    <div class="service-details-right">
      <div class="card booking-card-sticky">
        <div class="text-center mb-2">
          <span class="text-lg fw-bold text-success">Starting at <?= formatPrice($service["price"]) ?></span>
          <p class="text-muted text-sm">Starting price</p>
        </div>

        <div class="mb-2 service-meta-box">
          <div class="d-flex justify-between mb-1">
            <span class="text-muted">Delivery Time:</span>
            <span class="fw-bold"><?= (int)$service["delivery_time"] ?> day(s)</span>
          </div>
          <div class="d-flex justify-between">
            <span class="text-muted">Revisions Included:</span>
            <span class="fw-bold"><?= (int)$service["revisions_included"] ?></span>
          </div>
        </div>

        <?php if ($isGuest): ?>
          <a href="<?= url('auth/login.php') ?>" class="btn btn-primary w-100">Login to Order</a>

        <?php elseif ($isOwner): ?>
          <a href="<?= url('services/edit.php?id=' . $service["service_id"]) ?>" class="btn btn-secondary w-100">Edit Service</a>

        <?php elseif (($isClient || ($isFreelancer && !$isOwner)) && $service["status"] === "Active"): ?>
          <?php $serviceInCart = isset($_SESSION['cart'][$service["service_id"]]); ?>

          <?php if (!$serviceInCart): ?>
            <a href="<?= url('services/details.php?id=' . $service["service_id"] . '&add_to_cart=' . $service["service_id"]) ?>"
               class="btn btn-secondary w-100 mb-1">Add to Cart</a>
          <?php else: ?>
            <a href="<?= url('purches/cart.php') ?>" class="btn btn-secondary w-100 mb-1">View Cart</a>
          <?php endif; ?>

          <a href="<?= url('services/details.php?id=' . $service["service_id"] . '&order_now=' . $service["service_id"]) ?>"
             class="btn btn-primary w-100 mb-1">Order Now</a>

        <?php elseif ($service["status"] !== "Active"): ?>
          <button class="btn btn-disabled w-100" disabled>Service Unavailable</button>
        <?php endif; ?>

        <?php if ($isLoggedIn && !$isOwner): ?>
          <a href="<?= url('messages.php?to=' . $service["freelancer_id"]) ?>" class="btn btn-secondary w-100 mt-1">Contact Freelancer</a>
        <?php elseif (!$isLoggedIn): ?>
          <a href="<?= url('auth/login.php') ?>" class="btn btn-secondary w-100 mt-1">Login to Contact</a>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3>About the Freelancer</h3>
        <div class="d-flex gap-2 align-center">
          <img src="<?= !empty($service["profile_photo"]) ? BASE_URL . "/" . htmlspecialchars($service["profile_photo"]) : DEFAULT_PROFILE_IMAGE ?>"
               alt="<?= htmlspecialchars($service["first_name"]) ?>"
               class="avatar">
          <div>
            <div class="fw-bold"><?= formatFullName($service["first_name"], $service["last_name"]) ?></div>
            <div class="text-muted text-sm"><?= htmlspecialchars($service["city"] . ", " . $service["country"]) ?></div>
            <?php if (!empty($service["freelancer_since"])): ?>
              <div class="text-muted text-xs">Member since <?= date("M Y", strtotime($service["freelancer_since"])) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$pageTitle = $service ? htmlspecialchars($service["title"]) . " - MO Freelancing" : "Service Not Found - MO Freelancing";
renderPage($service ? htmlspecialchars($service["title"]) : "Service Not Found", $content, [
    'currentPage' => $_SERVER["REQUEST_URI"],
    'pageTitle' => $pageTitle
]);
