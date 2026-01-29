<?php
require_once dirname(__DIR__) . '/db.php.inc';

$errors = [];
$success = isset($_GET['success']) && $_GET['success'] === '1';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if (empty($name)) {
        $errors['name'] = "Name is required.";
    }

    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
    }

    if (empty($subject)) {
        $errors['subject'] = "Subject is required.";
    }

    if (empty($message)) {
        $errors['message'] = "Message is required.";
    }

    if (empty($errors)) {
        header("Location: " . url("pages/contact.php?success=1"));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us - MO Freelancing</title>
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
      <h1>Contact Us</h1>

      <div class="card">
        <h2>Get in Touch</h2>
        <p>If you have any questions, concerns, or feedback, please don't hesitate to reach out to us. We're here to help!</p>

        <div class="mb-3">
          <p><strong>Email:</strong> 1221175@student.birzeit.edu</p>
          <p><strong>Phone:</strong> +970 2 123 4567</p>
          <p><strong>Address:</strong> Ramallah, Palestine</p>
        </div>
      </div>

      <div class="card mt-3">
        <h2>Send us a Message</h2>

        <?php if ($success): ?>
          <div class="message-success">Your message has been sent.</div>
        <?php else: ?>
          <form method="POST" action="" class="form-container">
            <div class="form-group">
              <label class="form-label" for="name">Name <span class="required">*</span></label>
              <input type="text" id="name" name="name" required
                     class="form-input <?= errorClass('name', $errors) ?>"
                     value="<?= htmlspecialchars($_POST["name"] ?? "") ?>">
              <?php if (hasError('name', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['name']) ?></small>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="email">Email <span class="required">*</span></label>
              <input type="email" id="email" name="email" required
                     class="form-input <?= errorClass('email', $errors) ?>"
                     value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">
              <?php if (hasError('email', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['email']) ?></small>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="subject">Subject <span class="required">*</span></label>
              <input type="text" id="subject" name="subject" required
                     class="form-input <?= errorClass('subject', $errors) ?>"
                     value="<?= htmlspecialchars($_POST["subject"] ?? "") ?>">
              <?php if (hasError('subject', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['subject']) ?></small>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label" for="message">Message <span class="required">*</span></label>
              <textarea id="message" name="message" rows="6" required
                        class="form-textarea <?= errorClass('message', $errors) ?>"><?= htmlspecialchars($_POST["message"] ?? "") ?></textarea>
              <?php if (hasError('message', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['message']) ?></small>
              <?php endif; ?>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Send Message</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>

