<?php
require_once dirname(__DIR__) . '/db.php.inc';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Privacy Policy - MO Freelancing</title>
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
      <h1>Privacy Policy</h1>

      <div class="card">
        <h2>1. Data We Collect</h2>
        <p>We collect information that you provide directly to us, including your name, email address, phone number, city, and profile information. For freelancers, we also collect bio information and service details. We collect payment information necessary to process transactions.</p>

        <h2>2. How We Use Data</h2>
        <p>We use your personal information to provide and improve our services, process transactions, communicate with you about your account and services, and send you important updates. We may also use your information to prevent fraud and ensure platform security.</p>

        <h2>3. Cookies and Sessions</h2>
        <p>We use session cookies to maintain your login state and remember your preferences while you browse our platform. These cookies are essential for the platform to function properly. Session data is stored securely and expires after a period of inactivity.</p>

        <h2>4. Data Sharing</h2>
        <p>We do not sell your personal information to third parties. We may share your information with service providers who assist us in operating our platform, such as payment processors, but only to the extent necessary to provide our services. Client and freelancer information is shared only as necessary to facilitate service transactions.</p>

        <h2>5. Security</h2>
        <p>We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. This includes encryption of sensitive data and secure password storage using industry-standard hashing algorithms.</p>

        <h2>6. Your Rights</h2>
        <p>You have the right to access, update, or delete your personal information at any time through your account settings. You may also request a copy of your data or request that we stop processing your information, subject to legal and contractual obligations.</p>
      </div>
    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>

