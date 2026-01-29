<?php
require_once dirname(__DIR__) . '/db.php.inc';
includeComponent('service_card');

$category = $_GET["category"] ?? "";
$search = trim($_GET["search"] ?? "");
$freelancerId = $_GET["freelancer_id"] ?? "";
$sort = $_GET["sort"] ?? "newest";
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = max(12, min(20, (int)($_GET["per_page"] ?? 12)));
$validPerPage = [12, 15, 20];
if (!in_array($perPage, $validPerPage)) {
    $perPage = 12;
}

$validSorts = ['newest', 'oldest', 'price_low', 'price_high'];
if (!in_array($sort, $validSorts)) {
    $sort = 'newest';
}

$serviceModel = new ServiceModel($pdo);
$categories = $serviceModel->getCategories();
$featuredServices = $serviceModel->getFeaturedServices();

$result = $serviceModel->getServicesWithFilters(
    ['category' => $category, 'search' => $search, 'freelancer_id' => $freelancerId],
    $sort,
    $page,
    $perPage
);
$services = $result['services'];
$totalServices = $result['total'];
$totalPages = ceil($totalServices / $perPage);

function buildUrl($params) {
    $base = BASE_URL . '/services/browse.php';
    $filtered = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return $base . (count($filtered) > 0 ? '?' . http_build_query($filtered) : '');
}

$currentParams = [
    'search' => $search,
    'category' => $category,
    'freelancer_id' => $freelancerId,
    'sort' => $sort,
    'per_page' => $perPage
];

ob_start();
?>
      <h1>Browse Services</h1>

      <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
          <div class="filter-group">
            <input type="text" name="search" placeholder="Search services.." 
                   value="<?= htmlspecialchars($search) ?>" class="filter-input">
          </div>
          
          <div class="filter-group">
            <select name="category" class="filter-select">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? "selected" : "" ?>>
                  <?= htmlspecialchars($cat) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="filter-group">
            <select name="sort" class="filter-select">
              <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest First</option>
              <option value="oldest" <?= ($sort === 'oldest') ? 'selected' : '' ?>>Oldest First</option>
              <option value="price_low" <?= ($sort === 'price_low') ? 'selected' : '' ?>>Price: Low to High</option>
              <option value="price_high" <?= ($sort === 'price_high') ? 'selected' : '' ?>>Price: High to Low</option>
            </select>
          </div>
          
          <div class="filter-group">
            <select name="per_page" class="filter-select">
              <option value="12" <?= ($perPage === 12) ? 'selected' : '' ?>>12 per page</option>
              <option value="15" <?= ($perPage === 15) ? 'selected' : '' ?>>15 per page</option>
              <option value="20" <?= ($perPage === 20) ? 'selected' : '' ?>>20 per page</option>
            </select>
          </div>
          
          <button type="submit" class="btn btn-primary filter-button">Search</button>
          <?php if (!empty($search) || !empty($category)): ?>
            <a href="<?= url('services/browse.php') ?>" class="btn btn-secondary">Clear Filters</a>
          <?php endif; ?>
        </form>
      </div>

      <div class="mb-2">
        <?php if (!empty($search)): ?>
          <p class="text-muted">Search results for "<strong><?= htmlspecialchars($search) ?></strong>" — <?= $totalServices ?> service(s) found</p>
        <?php elseif (!empty($category)): ?>
          <p class="text-muted">Category: <strong><?= htmlspecialchars($category) ?></strong> — <?= $totalServices ?> service(s) found</p>
        <?php else: ?>
          <p class="text-muted"><?= $totalServices ?> service(s) available</p>
        <?php endif; ?>
        
        <div class="d-flex gap-2 mt-1">
          <?php if (!empty($category)): ?>
            <a href="<?= buildUrl(array_merge($currentParams, ['category' => '', 'page' => 1])) ?>" class="text-primary">Show All Categories</a>
          <?php endif; ?>
          <?php if (!empty($search) || !empty($category)): ?>
            <a href="<?= url('services/browse.php') ?>" class="text-primary">Show All Services</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if (count($featuredServices) > 0 && empty($search) && empty($category)): ?>
      <section class="mb-3">
        <h2>Featured Services</h2>
        <div class="services-grid">
          <?php foreach ($featuredServices as $service): ?>
            <?php renderServiceCard($service, ['showFeaturedBadge' => true, 'pricePrefix' => 'Starting at ']); ?>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section>
        <h2>All Services</h2>
        
        <?php if (count($services) === 0): ?>
          <div class="card text-center">
            <p>No services found matching your criteria.</p>
            <a href="<?= url('services/browse.php') ?>" class="btn btn-primary">View All Services</a>
          </div>
        <?php else: ?>
          <div class="services-grid">
            <?php foreach ($services as $service): ?>
              <?php renderServiceCard($service, ['pricePrefix' => 'Starting at ']); ?>
            <?php endforeach; ?>
          </div>

          <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php if ($page > 1): ?>
              <a href="<?= buildUrl(array_merge($currentParams, ['page' => $page - 1])) ?>" class="pagination-btn">
                &laquo; Previous
              </a>
            <?php else: ?>
              <span class="pagination-btn pagination-disabled">&laquo; Previous</span>
            <?php endif; ?>
            
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1): ?>
              <a href="<?= buildUrl(array_merge($currentParams, ['page' => 1])) ?>" class="pagination-btn">1</a>
              <?php if ($startPage > 2): ?>
                <span class="pagination-btn">...</span>
              <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
              <a href="<?= buildUrl(array_merge($currentParams, ['page' => $i])) ?>" 
                 class="pagination-btn <?= ($i === $page) ? 'pagination-active' : '' ?>">
                <?= $i ?>
              </a>
            <?php endfor; ?>
            
            <?php if ($endPage < $totalPages): ?>
              <?php if ($endPage < $totalPages - 1): ?>
                <span class="pagination-btn">...</span>
              <?php endif; ?>
              <a href="<?= buildUrl(array_merge($currentParams, ['page' => $totalPages])) ?>" class="pagination-btn"><?= $totalPages ?></a>
            <?php endif; ?>
            
            <?php if ($page < $totalPages): ?>
              <a href="<?= buildUrl(array_merge($currentParams, ['page' => $page + 1])) ?>" class="pagination-btn">
                Next &raquo;
              </a>
            <?php else: ?>
              <span class="pagination-btn pagination-disabled">Next &raquo;</span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
<?php
$content = ob_get_clean();
renderPage('Browse Services', $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);
