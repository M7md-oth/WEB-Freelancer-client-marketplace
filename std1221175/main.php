<?php
require_once "db.php.inc";
includeComponent('service_card');

$serviceModel = new ServiceModel($pdo);
$featuredServices = $serviceModel->getFeaturedServices();
$services = $serviceModel->getAllServices();

ob_start();
?>
<?php if (count($featuredServices) > 0): ?>
  <h2>Featured Services</h2>
  <div class="services-grid mb-3">
    <?php foreach ($featuredServices as $service): ?>
      <?php renderServiceCard($service, ['showFeaturedBadge' => true]); ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2>All Services</h2>
<div class="card">
  <table class="table">
    <thead>
      <tr>
        <th>Image</th>
        <th>Service Title</th>
        <th>Freelancer</th>
        <th>Category</th>
        <th>Price</th>
        <th>Status</th>
        <th>Created Date</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($services) === 0): ?>
        <tr>
          <td colspan="7" class="text-center">No services found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($services as $s): ?>
          <tr>
            <td>
              <img src="<?= !empty($s["image_1"]) ? BASE_URL . "/" . htmlspecialchars($s["image_1"]) : DEFAULT_SERVICE_IMAGE ?>" alt="Service Image" class="avatar">
            </td>

            <td>
              <a href="<?= BASE_URL ?>/services/details.php?id=<?= htmlspecialchars($s["service_id"]) ?>">
                <?= htmlspecialchars($s["title"]) ?>
              </a>
              <?php if (($s["featured_status"] ?? "") === "Yes"): ?>
                <span class="badge badge-featured">Featured</span>
              <?php endif; ?>
            </td>

            <td><?= formatFullName($s["first_name"], $s["last_name"]) ?></td>

            <td><?= htmlspecialchars($s["category"]) ?></td>

            <td><strong class="text-success"><?= formatPrice($s["price"]) ?></strong></td>

            <td>
              <span class="badge <?= ($s["status"] ?? "") === "Active" ? "status-active" : "status-inactive" ?>">
                <?= htmlspecialchars($s["status"]) ?>
              </span>
            </td>

            <td><?= formatDate($s["created_date"]) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$content = ob_get_clean();
renderPage('Home', $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);
