<?php
if (!isset($current_page)) {
    $current_page = $_SERVER["REQUEST_URI"] ?? "";
}
?>
<nav class="navigation bg-bronze">
  <ul>
    <li class="nav-item">
      <a class="nav-link <?= strpos($current_page, 'main.php') !== false ? 'nav-link-active' : '' ?>"
         href="<?= BASE_URL ?>/main.php">Home</a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= strpos($current_page, 'services/browse.php') !== false ? 'nav-link-active' : '' ?>"
         href="<?= BASE_URL ?>/services/browse.php">Browse Services</a>
    </li>

    <?php if (isset($_SESSION["user_id"])): ?>
      <li class="nav-item">
        <a class="nav-link <?= strpos($current_page, 'profile/index.php') !== false ? 'nav-link-active' : '' ?>"
           href="<?= BASE_URL ?>/profile/index.php">My Profile</a>
      </li>

      <?php if ($_SESSION["role"] === "Client"): ?>
        <li class="nav-item">
          <a class="nav-link <?= (strpos($current_page, 'cart.php') !== false || strpos($current_page, 'checkout.php') !== false) ? 'nav-link-active' : '' ?>"
             href="<?= BASE_URL ?>/purches/cart.php">Cart</a>
        </li>
      <?php endif; ?>

      <li class="nav-item">
        <a class="nav-link <?= strpos($current_page, 'orders/my_orders.php') !== false ? 'nav-link-active' : '' ?>"
           href="<?= BASE_URL ?>/orders/my_orders.php">
          <?= $_SESSION["role"] === "Freelancer" ? "My Orders" : "My Orders" ?>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/auth/logout.php">Logout</a>
      </li>
    <?php else: ?>
      <li class="nav-item">
        <a class="nav-link <?= strpos($current_page, 'auth/login.php') !== false ? 'nav-link-active' : '' ?>"
           href="<?= BASE_URL ?>/auth/login.php">Login</a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= strpos($current_page, 'auth/signup.php') !== false ? 'nav-link-active' : '' ?>"
           href="<?= BASE_URL ?>/auth/signup.php">Sign up</a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
