<?php
require_once dirname(__DIR__) . '/db.php.inc';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Terms of Service - MO Freelancing</title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>

<?php includeTemplate('header'); ?>

<div class="page-container">
  <?php 
  $current_page = $_SERVER["REQUEST_URI"] ?? "";
  includeTemplate('nav'); 
  ?>

  <main class="main-content">
    <div class="container">
      <h1>Terms of Service</h1>

      <div class="card">
        <h2>1. Accounts</h2>
        <p>By creating an account on MO Freelancing, you agree to provide accurate and complete information. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>

        <h2>2. Payments</h2>
        <p>All payments for services are processed securely. Clients agree to pay the agreed-upon price for services rendered. Service fees may apply as specified at the time of purchase.</p>

        <h2>3. Service Delivery</h2>
        <p>Freelancers agree to deliver services according to the specifications agreed upon with clients. Delivery times are estimates and may vary based on project complexity. Clients and freelancers should communicate clearly about expectations and deadlines.</p>

        <h2>4. Refunds</h2>
        <p>Refund policies are determined on a case-by-case basis. Clients may request refunds if services are not delivered as agreed. All refund requests must be submitted within 30 days of service completion or delivery.</p>

        <h2>5. Acceptable Use</h2>
        <p>Users must not use the platform for any illegal activities or to violate any laws. Prohibited activities include fraud, harassment, spamming, or posting inappropriate content. Violations may result in account suspension or termination.</p>

        <h2>6. Dispute Resolution</h2>
        <p>In case of disputes between clients and freelancers, both parties should attempt to resolve issues through direct communication. If resolution cannot be reached, the platform may assist in mediation. Final decisions rest with the platform administrators.</p>
      </div>
    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>

