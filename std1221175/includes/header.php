<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<header class="header bg-bronze">
  <a href="<?= BASE_URL ?>/main.php" class="logo">
    <img src="<?= LOGO_IMAGE ?>" alt="MO Freelancing Logo" class="logo-img">
    MO Freelancing
  </a>

  <form class="header-search" method="GET" action="<?= BASE_URL ?>/services/browse.php">
    <input type="text" name="search" placeholder="Service name"
      value="<?= htmlspecialchars($_GET["search"] ?? "") ?>">
    <button type="submit" class="btn btn-primary">Search</button>
  </form>

  <div class="header-auth">
    <?php if (isset($_SESSION["user_id"])): ?>
      <?php 
      $profileClass = $_SESSION["role"] === "Client" ? "client" : "freelancer";
      ?>
      <a href="<?= BASE_URL ?>/profile/index.php" class="profile-card <?= $profileClass ?>">
        <?php if (!empty($_SESSION["profile_photo"])): ?>
          <img src="<?= BASE_URL ?>/<?= htmlspecialchars($_SESSION["profile_photo"]) ?>" alt="Profile">
        <?php else: ?>
          <img src="<?= DEFAULT_PROFILE_IMAGE ?>" alt="Profile">
        <?php endif; ?>
        <span><?= htmlspecialchars($_SESSION["first_name"]) ?></span>
      </a>

      <?php if ($_SESSION["role"] === "Client"): ?>
        <?php
        $cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
        ?>
        <a href="<?= BASE_URL ?>/purches/cart.php" class="cart-icon">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
          <span class="cart-badge <?= $cartCount == 0 ? 'hidden' : '' ?>"><?= $cartCount ?></span>
        </a>
      <?php endif; ?>

      <?php if ($_SESSION["role"] === "Freelancer"): ?>
        <a class="btn btn-success" href="<?= BASE_URL ?>/create-service.php">+ Add Service</a>
      <?php endif; ?>
      <a class="btn btn-secondary" href="<?= BASE_URL ?>/auth/logout.php">Logout</a>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= BASE_URL ?>/auth/login.php">Login</a>
      <a class="btn btn-secondary" href="<?= BASE_URL ?>/auth/signup.php">Sign up</a>
    <?php endif; ?>
  </div>
</header>
