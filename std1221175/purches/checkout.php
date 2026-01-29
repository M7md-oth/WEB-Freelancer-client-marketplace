<?php
require_once '../db.php.inc';

requireClient();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (empty($_SESSION['cart'])) {
    $_SESSION["error"] = "Your cart is empty";
    header("Location: ../services/browse.php");
    exit;
}

if (!isset($_SESSION['checkout_data'])) {
    $_SESSION['checkout_data'] = [
        'step1' => [],
        'step2' => [],
        'step3' => []
    ];
}

$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($currentStep < 1 || $currentStep > 3) {
    $currentStep = 1;
}

if ($currentStep === 2 && isset($_GET['payment_method']) && $_SERVER["REQUEST_METHOD"] === "GET") {
    $selectedMethod = trim($_GET['payment_method']);
    $allowedMethods = ['Credit Card', 'PayPal', 'Bank Transfer'];
    if (in_array($selectedMethod, $allowedMethods)) {
        if (!isset($_SESSION['checkout_data']['step2'])) {
            $_SESSION['checkout_data']['step2'] = [];
        }
        $_SESSION['checkout_data']['step2']['payment_method'] = $selectedMethod;
        header("Location: " . url("purches/checkout.php?step=2"));
        exit;
    }
}

$errors = [];
$message = "";
$messageType = "";

$removedServices = [];
foreach ($_SESSION['cart'] as $serviceId => $serializedService) {
    $service = unserialize($serializedService);
    if ($service instanceof Service) {
        $sql = "SELECT service_id, title, status FROM services WHERE service_id = :service_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":service_id" => $serviceId]);
        $dbService = $stmt->fetch();
        
        if (!$dbService || $dbService['status'] !== 'Active') {
            unset($_SESSION['cart'][$serviceId]);
            if ($dbService) {
                $removedServices[] = $dbService['title'];
            }
        }
    } else {
        unset($_SESSION['cart'][$serviceId]);
    }
}

if (!empty($removedServices)) {
    foreach ($removedServices as $title) {
        $message .= "Service '" . htmlspecialchars($title) . "' is no longer available and has been removed. ";
    }
    $messageType = "warning";
}

if (empty($_SESSION['cart'])) {
    $_SESSION["error"] = "Your cart is empty";
    header("Location: " . url("services/browse.php"));
    exit;
}

$cartItems = [];
$subtotal = 0;
$serviceFee = 0;
$grandTotal = 0;

foreach ($_SESSION['cart'] as $serviceId => $serializedService) {
    $service = unserialize($serializedService);
    if ($service instanceof Service) {
        $cartItems[] = $service;
        $subtotal += $service->getPrice();
    }
}

$serviceFee = $subtotal * 0.05;
$grandTotal = $subtotal + $serviceFee;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'step1' || ($currentStep === 1 && $action === '')) {
        $step1Data = [];
        $step1Errors = [];
        
        foreach ($cartItems as $service) {
            $serviceId = $service->getServiceId();
            $requirements = trim($_POST["requirements_{$serviceId}"] ?? "");
            $specialInstructions = trim($_POST["special_instructions_{$serviceId}"] ?? "");
            $preferredDeadline = trim($_POST["preferred_deadline_{$serviceId}"] ?? "");
            
            if (empty($requirements)) {
                $step1Errors["requirements_{$serviceId}"] = "Service requirements are required.";
            } elseif (strlen($requirements) < 50 || strlen($requirements) > 1000) {
                $step1Errors["requirements_{$serviceId}"] = "Service requirements must be between 50 and 1000 characters.";
            }
            
            if (!empty($specialInstructions) && strlen($specialInstructions) > 500) {
                $step1Errors["special_instructions_{$serviceId}"] = "Special instructions must not exceed 500 characters.";
            }
            
            if (!empty($preferredDeadline)) {
                $deadlineDate = strtotime($preferredDeadline);
                $minDate = strtotime("+" . $service->getDeliveryTime() . " days");
                if ($deadlineDate === false || $deadlineDate < $minDate) {
                    $step1Errors["preferred_deadline_{$serviceId}"] = "Preferred deadline must be at least " . $service->getDeliveryTime() . " days from today.";
                }
            }
            
            $uploadedFiles = [];
            $fileErrors = [];
            
            for ($i = 1; $i <= 3; $i++) {
                $fileKey = "requirement_file_{$serviceId}_{$i}";
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$fileKey];
                    
                    if ($file['size'] > 10485760) {
                        $fileErrors[$fileKey] = "File size exceeds 10MB limit.";
                        continue;
                    }
                    
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'jpg', 'jpeg', 'png'];
                    if (!in_array($ext, $allowedExtensions)) {
                        $fileErrors[$fileKey] = "Invalid file type. Allowed: PDF, DOC, DOCX, TXT, ZIP, JPG, PNG.";
                        continue;
                    }
                    
                    $tempDir = basePath("uploads/temp/checkout/");
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }
                    
                    $tempFilename = "req_" . uniqid() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                    $tempPath = $tempDir . $tempFilename;
                    
                    if (move_uploaded_file($file['tmp_name'], $tempPath)) {
                        $uploadedFiles[] = [
                            'temp_path' => $tempPath,
                            'original_filename' => $file['name'],
                            'file_size' => $file['size'],
                            'extension' => $ext
                        ];
                    } else {
                        $fileErrors[$fileKey] = "Failed to upload file.";
                    }
                }
            }
            
            if (count($uploadedFiles) > 3) {
                $fileErrors["files_{$serviceId}"] = "Maximum 3 files allowed per service.";
            }
            
            if (!empty($fileErrors)) {
                $step1Errors = array_merge($step1Errors, $fileErrors);
            }
            
            $step1Data[$serviceId] = [
                'requirements' => $requirements,
                'special_instructions' => $specialInstructions,
                'preferred_deadline' => $preferredDeadline,
                'files' => $uploadedFiles
            ];
        }
        
        if (empty($step1Errors)) {
            $_SESSION['checkout_data']['step1'] = $step1Data;
            header("Location: " . url("purches/checkout.php?step=2"));
            exit;
        } else {
            $errors = $step1Errors;
            foreach ($step1Data as $serviceId => $data) {
                if (!empty($data['files'])) {
                    $_SESSION['checkout_data']['step1'][$serviceId]['files'] = $data['files'];
                }
            }
        }
    }
    
    elseif ($action === 'step2' || ($currentStep === 2 && $action === '')) {
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $cardNumber = trim($_POST['card_number'] ?? '');
        $cardholderName = trim($_POST['cardholder_name'] ?? '');
        $expirationDate = trim($_POST['expiration_date'] ?? '');
        $cvv = trim($_POST['cvv'] ?? '');
        $addressLine1 = trim($_POST['address_line1'] ?? '');
        $addressLine2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $stateProvince = trim($_POST['state_province'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        $allowedMethods = ['Credit Card', 'PayPal', 'Bank Transfer'];
        if (empty($paymentMethod) || !in_array($paymentMethod, $allowedMethods)) {
            $errors['payment_method'] = "Please select a valid payment method.";
        }
        
        if ($paymentMethod === 'Credit Card') {
            $cardNumberClean = str_replace(' ', '', $cardNumber);
            if (empty($cardNumberClean) || !preg_match("/^[0-9]{16}$/", $cardNumberClean)) {
                $errors['card_number'] = "Card number must be 16 digits.";
            } else {
                $cardNumber = substr($cardNumberClean, 0, 4) . ' ' . substr($cardNumberClean, 4, 4) . ' ' . substr($cardNumberClean, 8, 4) . ' ' . substr($cardNumberClean, 12, 4);
            }
            
            if (empty($cardholderName)) {
                $errors['cardholder_name'] = "Cardholder name is required.";
            } elseif (strlen($cardholderName) < 2 || strlen($cardholderName) > 100) {
                $errors['cardholder_name'] = "Cardholder name must be between 2 and 100 characters.";
            } elseif (!preg_match("/^[a-zA-Z\s]+$/", $cardholderName)) {
                $errors['cardholder_name'] = "Cardholder name can only contain letters and spaces.";
            }
            
            if (empty($expirationDate)) {
                $errors['expiration_date'] = "Expiration date is required.";
            } else {
                $expirationDateClean = str_replace(['/', ' '], '', $expirationDate);
                if (preg_match("/^([0-9]{2})([0-9]{2})$/", $expirationDateClean, $matches)) {
                    $expirationDate = $matches[1] . '/' . $matches[2];
                }
                
                if (!preg_match("/^(0[1-9]|1[0-2])\/([0-9]{2})$/", $expirationDate)) {
                    $errors['expiration_date'] = "Expiration date must be in MM/YY format.";
                } else {
                    list($month, $year) = explode('/', $expirationDate);
                    $expDate = strtotime("20{$year}-{$month}-01");
                    $lastDayOfMonth = date("Y-m-t", $expDate);
                    if (strtotime($lastDayOfMonth) < time()) {
                        $errors['expiration_date'] = "Expiration date must be in the future.";
                    }
                }
            }
            
            if (empty($cvv) || !preg_match("/^[0-9]{3}$/", $cvv)) {
                $errors['cvv'] = "CVV must be 3 digits.";
            }
        }
        
        if (empty($addressLine1)) {
            $errors['address_line1'] = "Address Line 1 is required.";
        }
        
        if (empty($city)) {
            $errors['city'] = "City is required.";
        }
        
        if (empty($stateProvince)) {
            $errors['state_province'] = "State/Province is required.";
        }
        
        if (empty($postalCode)) {
            $errors['postal_code'] = "Postal code is required.";
        }
        
        if (empty($country)) {
            $errors['country'] = "Country is required.";
        }
        
        if (empty($errors)) {
            $cardNumberLast4 = '';
            if ($paymentMethod === 'Credit Card' && !empty($cardNumber)) {
                $cardNumberClean = str_replace(' ', '', $cardNumber);
                $cardNumberLast4 = substr($cardNumberClean, -4);
            }
            
            $_SESSION['checkout_data']['step2'] = [
                'payment_method' => $paymentMethod,
                'card_number' => $cardNumberLast4,
                'cardholder_name' => $paymentMethod === 'Credit Card' ? $cardholderName : '',
                'expiration_date' => $paymentMethod === 'Credit Card' ? $expirationDate : '',
                'address_line1' => $addressLine1,
                'address_line2' => $addressLine2,
                'city' => $city,
                'state_province' => $stateProvince,
                'postal_code' => $postalCode,
                'country' => $country
            ];
            header("Location: " . url("purches/checkout.php?step=3"));
            exit;
        } else {
            $_SESSION['checkout_data']['step2'] = [
                'payment_method' => $paymentMethod,
                'card_number' => $paymentMethod === 'Credit Card' ? $cardNumber : '',
                'cardholder_name' => $paymentMethod === 'Credit Card' ? $cardholderName : '',
                'expiration_date' => $paymentMethod === 'Credit Card' ? $expirationDate : '',
                'address_line1' => $addressLine1,
                'address_line2' => $addressLine2,
                'city' => $city,
                'state_province' => $stateProvince,
                'postal_code' => $postalCode,
                'country' => $country
            ];
        }
    }
    
    elseif ($action === 'place_order') {
        if (empty($_SESSION['checkout_data']['step1']) || empty($_SESSION['checkout_data']['step2'])) {
            $errors['general'] = "Please complete all steps before placing your order.";
        } else {
            if (empty($_POST['terms_agreement'])) {
                $errors['terms_agreement'] = "You must agree to the Terms of Service and Privacy Policy to place an order.";
            } else {
                $transactionId = "TXN" . time() . bin2hex(random_bytes(4));
                
                $createdOrderIds = [];
                
                foreach ($cartItems as $service) {
                    $serviceId = $service->getServiceId();
                    $step1Data = $_SESSION['checkout_data']['step1'][$serviceId] ?? [];
                    
                    $idSql = "SELECT MAX(CAST(order_id AS UNSIGNED)) as max_id FROM orders";
                    $idStmt = $pdo->query($idSql);
                    $maxId = $idStmt->fetch()["max_id"];
                    $newOrderId = $maxId ? (string)((int)$maxId + 1) : "3000000001";
                    $newOrderId = str_pad($newOrderId, 10, "0", STR_PAD_LEFT);
                    
                    $preferredDeadline = !empty($step1Data['preferred_deadline']) ? $step1Data['preferred_deadline'] : null;
                    if ($preferredDeadline) {
                        $expectedDelivery = date("Y-m-d", strtotime($preferredDeadline));
                    } else {
                        $expectedDelivery = date("Y-m-d", strtotime("+" . $service->getDeliveryTime() . " days"));
                    }
                    
                    $requirementsText = $step1Data['requirements'] ?? '';
                    if (!empty($step1Data['special_instructions'])) {
                        $requirementsText .= "\n\nSpecial Instructions: " . $step1Data['special_instructions'];
                    }
                    
                    $insertSql = "INSERT INTO orders (order_id, client_id, freelancer_id, service_id, service_title, price, delivery_time, revisions_included, requirements, status, payment_method, expected_delivery) 
                                  VALUES (:order_id, :client_id, :freelancer_id, :service_id, :service_title, :price, :delivery_time, :revisions_included, :requirements, 'Pending', :payment_method, :expected_delivery)";
                    $insertStmt = $pdo->prepare($insertSql);
                    $insertStmt->execute([
                        ":order_id" => $newOrderId,
                        ":client_id" => $_SESSION["user_id"],
                        ":freelancer_id" => $service->getFreelancerId(),
                        ":service_id" => $service->getServiceId(),
                        ":service_title" => $service->getTitle(),
                        ":price" => $service->getPrice(),
                        ":delivery_time" => $service->getDeliveryTime(),
                        ":revisions_included" => $service->getRevisionsIncluded(),
                        ":requirements" => $requirementsText,
                        ":payment_method" => $_SESSION['checkout_data']['step2']['payment_method'],
                        ":expected_delivery" => $expectedDelivery
                    ]);
                    
                    if (!empty($step1Data['files'])) {
                        $uploadDir = basePath("uploads/orders/" . $newOrderId . "/requirements/");
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        foreach ($step1Data['files'] as $fileData) {
                            $originalFilename = $fileData['original_filename'];
                            $ext = $fileData['extension'];
                            $newFilename = "req_" . uniqid() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                            $newPath = $uploadDir . $newFilename;
                            
                            if (file_exists($fileData['temp_path']) && rename($fileData['temp_path'], $newPath)) {
                                $fileSql = "INSERT INTO file_attachments (order_id, file_path, original_filename, file_size, file_type) 
                                            VALUES (:order_id, :file_path, :original_filename, :file_size, 'requirement')";
                                $fileStmt = $pdo->prepare($fileSql);
                                $fileStmt->execute([
                                    ":order_id" => $newOrderId,
                                    ":file_path" => "uploads/orders/" . $newOrderId . "/requirements/" . $newFilename,
                                    ":original_filename" => $originalFilename,
                                    ":file_size" => $fileData['file_size']
                                ]);
                            }
                        }
                    }
                    
                    $createdOrderIds[] = $newOrderId;
                }
                
                $_SESSION['checkout_success'] = [
                    'transaction_id' => $transactionId,
                    'order_ids' => $createdOrderIds
                ];
                
                $_SESSION['cart'] = [];
                $_SESSION['checkout_data'] = [];
                
                $tempDir = basePath("uploads/temp/checkout/");
                if (is_dir($tempDir)) {
                    $files = glob($tempDir . "*");
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }
                
                header("Location: " . url("purches/order-success.php"));
                exit;
            }
        }
    }
}

$step1Data = $_SESSION['checkout_data']['step1'] ?? [];
$step2Data = $_SESSION['checkout_data']['step2'] ?? [];

function formatCardNumber($cardNumber) {
    $cleaned = str_replace(' ', '', $cardNumber);
    if (strlen($cleaned) === 16) {
        return substr($cleaned, 0, 4) . ' ' . substr($cleaned, 4, 4) . ' ' . substr($cleaned, 8, 4) . ' ' . substr($cleaned, 12, 4);
    }
    return $cardNumber;
}

function formatExpirationDate($date) {
    if (empty($date)) {
        return '';
    }
    if (preg_match('/^(\d{2})\/(\d{2})$/', $date, $matches)) {
        return $matches[1] . '/' . $matches[2];
    }
    if (preg_match('/^(\d{2})(\d{2})$/', $date, $matches)) {
        return $matches[1] . '/' . $matches[2];
    }
    return $date;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Checkout - MO Freelancing</title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>

<?php includeTemplate('header'); ?>

<div class="page-container">
  <?php includeTemplate('nav'); ?>

  <main class="main-content">
    <div class="container">
      
      <?php if (!empty($message)): ?>
        <div class="message-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      
      <?php if (isset($errors['general'])): ?>
        <div class="message-error"><?= htmlspecialchars($errors['general']) ?></div>
      <?php endif; ?>

      <div class="breadcrumbs">
        <a href="../main.php">Home</a>
        <span class="breadcrumb-separator">&gt;</span>
        <a href="<?= url('purches/cart.php') ?>">Cart</a>
        <span class="breadcrumb-separator">&gt;</span>
        <span class="breadcrumb-current">Checkout</span>
      </div>

      <h1>Checkout</h1>

      <div class="step-indicator">
        <div class="step-item <?= $currentStep >= 1 ? ($currentStep > 1 ? 'step-completed' : 'step-active') : '' ?>">
          <div class="step-number">1</div>
          <div class="step-label">Service Requirements</div>
        </div>
        <div class="step-item <?= $currentStep >= 2 ? ($currentStep > 2 ? 'step-completed' : 'step-active') : '' ?>">
          <div class="step-number">2</div>
          <div class="step-label">Payment Information</div>
        </div>
        <div class="step-item <?= $currentStep >= 3 ? 'step-active' : '' ?>">
          <div class="step-number">3</div>
          <div class="step-label">Review & Confirm</div>
        </div>
      </div>

      <?php if ($currentStep === 1): ?>
        <form method="POST" action="" enctype="multipart/form-data" class="checkout-form">
          <input type="hidden" name="action" value="step1">
          
          <h2>Service Requirements</h2>
          <p class="text-muted mb-3">Please provide requirements for each service in your cart.</p>
          
          <?php foreach ($cartItems as $index => $service): ?>
            <?php 
            $serviceId = $service->getServiceId();
            $serviceData = $step1Data[$serviceId] ?? [];
            $minDeadline = date("Y-m-d", strtotime("+" . $service->getDeliveryTime() . " days"));
            ?>
            <div class="card mb-3 checkout-service-card">
              <h3>Service <?= $index + 1 ?>: <?= htmlspecialchars($service->getTitle()) ?></h3>
              <p class="text-muted mb-2">by <?= htmlspecialchars($service->getFreelancerName()) ?> - <?= $service->getFormattedPrice() ?></p>
              <p class="text-muted mb-3">Delivery: <?= $service->getFormattedDelivery() ?></p>
              
              <div class="form-group">
                <label class="form-label" for="requirements_<?= $serviceId ?>">Service Requirements <span class="required">*</span></label>
                <textarea id="requirements_<?= $serviceId ?>" name="requirements_<?= $serviceId ?>" rows="6" required class="form-textarea <?= errorClass("requirements_{$serviceId}", $errors) ?>"
                          placeholder="Describe what you need for this service (50-1000 characters)"><?= htmlspecialchars($serviceData['requirements'] ?? '') ?></textarea>
                <?php if (hasError("requirements_{$serviceId}", $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors["requirements_{$serviceId}"]) ?></small>
                <?php else: ?>
                  <small class="text-muted">50-1000 characters required</small>
                <?php endif; ?>
              </div>
              
              <div class="form-group">
                <label class="form-label" for="special_instructions_<?= $serviceId ?>">Special Instructions (Optional)</label>
                <textarea id="special_instructions_<?= $serviceId ?>" name="special_instructions_<?= $serviceId ?>" rows="4" class="form-textarea <?= errorClass("special_instructions_{$serviceId}", $errors) ?>"
                          placeholder="Additional notes or preferences (up to 500 characters)"><?= htmlspecialchars($serviceData['special_instructions'] ?? '') ?></textarea>
                <?php if (hasError("special_instructions_{$serviceId}", $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors["special_instructions_{$serviceId}"]) ?></small>
                <?php endif; ?>
              </div>
              
              <div class="form-group">
                <label class="form-label" for="preferred_deadline_<?= $serviceId ?>">Preferred Deadline (Optional)</label>
                <input type="date" id="preferred_deadline_<?= $serviceId ?>" name="preferred_deadline_<?= $serviceId ?>" 
                       class="form-input <?= errorClass("preferred_deadline_{$serviceId}", $errors) ?>"
                       min="<?= $minDeadline ?>" value="<?= htmlspecialchars($serviceData['preferred_deadline'] ?? '') ?>">
                <?php if (hasError("preferred_deadline_{$serviceId}", $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors["preferred_deadline_{$serviceId}"]) ?></small>
                <?php else: ?>
                  <small class="text-muted">Minimum: <?= date("M d, Y", strtotime($minDeadline)) ?></small>
                <?php endif; ?>
              </div>
              
              <div class="form-group">
                <label class="form-label">Requirement Files (Optional, max 3)</label>
                <p class="text-muted text-sm mb-2">Allowed formats: PDF, DOC, DOCX, TXT, ZIP, JPG, PNG. Max size: 10MB per file.</p>
                
                <div class="d-flex gap-2">
                  <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="form-group form-group-flex">
                      <input type="file" id="requirement_file_<?= $serviceId ?>_<?= $i ?>" name="requirement_file_<?= $serviceId ?>_<?= $i ?>" 
                             accept=".pdf,.doc,.docx,.txt,.zip,.jpg,.jpeg,.png" class="form-input">
                      <?php if (hasError("requirement_file_{$serviceId}_{$i}", $errors)): ?>
                        <small class="form-error"><?= htmlspecialchars($errors["requirement_file_{$serviceId}_{$i}"]) ?></small>
                      <?php endif; ?>
                    </div>
                  <?php endfor; ?>
                </div>
                <?php if (hasError("files_{$serviceId}", $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors["files_{$serviceId}"]) ?></small>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Continue to Payment →</button>
            <a href="<?= url('purches/cart.php') ?>" class="btn btn-secondary">Edit Cart</a>
          </div>
        </form>

      <?php elseif ($currentStep === 2): ?>
        <?php
        $step2Data = $_SESSION['checkout_data']['step2'] ?? [];
        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['payment_method'])) {
            $currentPaymentMethod = trim($_POST['payment_method']);
        } elseif (isset($_GET['payment_method'])) {
            $currentPaymentMethod = trim($_GET['payment_method']);
        } else {
            $currentPaymentMethod = $step2Data['payment_method'] ?? 'Credit Card';
        }
        $showCardFields = ($currentPaymentMethod === 'Credit Card');
        
        $formattedCardNumber = '';
        if (!empty($_POST['card_number']) && isset($errors['card_number'])) {
            $formattedCardNumber = formatCardNumber($_POST['card_number']);
        } elseif (!empty($step2Data['card_number'])) {
            if (strlen($step2Data['card_number']) === 4) {
                $formattedCardNumber = '**** **** **** ' . $step2Data['card_number'];
            } else {
                $formattedCardNumber = formatCardNumber($step2Data['card_number']);
            }
        }
        
        $formattedExpirationDate = '';
        if (!empty($_POST['expiration_date']) && isset($errors['expiration_date'])) {
            $formattedExpirationDate = formatExpirationDate($_POST['expiration_date']);
        } else {
            $formattedExpirationDate = formatExpirationDate($step2Data['expiration_date'] ?? '');
        }
        ?>
        <form method="POST" action="" class="checkout-form" id="checkout-form-step2">
          <input type="hidden" name="action" value="step2">
          
          <h2>Payment Information</h2>
          <p class="text-muted mb-3">This is for simulation only; no real payment processing.</p>
          
          <div class="card">
            <div class="form-group">
              <label class="form-label">Payment Method <span class="required">*</span></label>
              <div class="radio-group <?= hasError('payment_method', $errors) ? 'radio-error' : '' ?>">
                <a href="<?= url('purches/checkout.php?step=2&payment_method=Credit Card') ?>" 
                   class="radio-label" style="text-decoration: none; display: inline-flex; align-items: center; cursor: pointer;">
                  <span style="display: inline-block; width: 20px; height: 20px; border: 2px solid #ccc; border-radius: 50%; margin-right: 8px; position: relative; <?= ($currentPaymentMethod === 'Credit Card') ? 'background-color: #007bff; border-color: #007bff;' : '' ?>">
                    <?php if ($currentPaymentMethod === 'Credit Card'): ?>
                      <span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 10px; height: 10px; background: white; border-radius: 50%;"></span>
                    <?php endif; ?>
                  </span>
                  <span>Credit Card</span>
                </a>
                <a href="<?= url('purches/checkout.php?step=2&payment_method=PayPal') ?>" 
                   class="radio-label" style="text-decoration: none; display: inline-flex; align-items: center; cursor: pointer;">
                  <span style="display: inline-block; width: 20px; height: 20px; border: 2px solid #ccc; border-radius: 50%; margin-right: 8px; position: relative; <?= ($currentPaymentMethod === 'PayPal') ? 'background-color: #007bff; border-color: #007bff;' : '' ?>">
                    <?php if ($currentPaymentMethod === 'PayPal'): ?>
                      <span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 10px; height: 10px; background: white; border-radius: 50%;"></span>
                    <?php endif; ?>
                  </span>
                  <span>PayPal</span>
                </a>
                <a href="<?= url('purches/checkout.php?step=2&payment_method=Bank Transfer') ?>" 
                   class="radio-label" style="text-decoration: none; display: inline-flex; align-items: center; cursor: pointer;">
                  <span style="display: inline-block; width: 20px; height: 20px; border: 2px solid #ccc; border-radius: 50%; margin-right: 8px; position: relative; <?= ($currentPaymentMethod === 'Bank Transfer') ? 'background-color: #007bff; border-color: #007bff;' : '' ?>">
                    <?php if ($currentPaymentMethod === 'Bank Transfer'): ?>
                      <span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 10px; height: 10px; background: white; border-radius: 50%;"></span>
                    <?php endif; ?>
                  </span>
                  <span>Bank Transfer</span>
                </a>
              </div>
              <input type="hidden" name="payment_method" value="<?= htmlspecialchars($currentPaymentMethod) ?>" form="checkout-form-step2">
              <?php if (hasError('payment_method', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['payment_method']) ?></small>
              <?php endif; ?>
            </div>
            
            <?php if ($showCardFields): ?>
            <div id="credit-card-fields" class="credit-card-section">
              <div class="form-group">
                <label class="form-label" for="card_number">Card Number <span class="required">*</span></label>
                <input type="text" id="card_number" name="card_number" maxlength="19" 
                       class="form-input <?= errorClass('card_number', $errors) ?>"
                       placeholder="1234 5678 9012 3456" value="<?= htmlspecialchars($formattedCardNumber ?: ($_POST['card_number'] ?? '')) ?>">
                <?php if (hasError('card_number', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['card_number']) ?></small>
                <?php endif; ?>
              </div>
              
              <div class="form-group">
                <label class="form-label" for="cardholder_name">Cardholder Name <span class="required">*</span></label>
                <input type="text" id="cardholder_name" name="cardholder_name" 
                       class="form-input <?= errorClass('cardholder_name', $errors) ?>"
                       placeholder="Mohammed othman" value="<?= htmlspecialchars($step2Data['cardholder_name'] ?? ($_POST['cardholder_name'] ?? '')) ?>">
                <?php if (hasError('cardholder_name', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['cardholder_name']) ?></small>
                <?php endif; ?>
              </div>
              
              <div class="form-group-flex-container">
                <div class="form-group form-group-flex">
                  <label class="form-label" for="expiration_date">Expiration Date <span class="required">*</span></label>
                  <input type="text" id="expiration_date" name="expiration_date" maxlength="5" 
                         class="form-input <?= errorClass('expiration_date', $errors) ?>"
                         placeholder="MM/YY" value="<?= htmlspecialchars($formattedExpirationDate ?: ($_POST['expiration_date'] ?? '')) ?>">
                  <?php if (hasError('expiration_date', $errors)): ?>
                    <small class="form-error"><?= htmlspecialchars($errors['expiration_date']) ?></small>
                  <?php endif; ?>
                </div>
                
                <div class="form-group form-group-flex">
                  <label class="form-label" for="cvv">CVV <span class="required">*</span></label>
                  <input type="text" id="cvv" name="cvv" maxlength="3" 
                         class="form-input <?= errorClass('cvv', $errors) ?>"
                         placeholder="123" value="<?= htmlspecialchars($step2Data['cvv'] ?? ($_POST['cvv'] ?? '')) ?>">
                  <?php if (hasError('cvv', $errors)): ?>
                    <small class="form-error"><?= htmlspecialchars($errors['cvv']) ?></small>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>
            
            <h3 class="mt-3 mb-2">Billing Address</h3>
            
            <div class="form-group">
              <label class="form-label" for="address_line1">Address Line 1 <span class="required">*</span></label>
              <input type="text" id="address_line1" name="address_line1" 
                     class="form-input <?= errorClass('address_line1', $errors) ?>"
                     value="<?= htmlspecialchars($step2Data['address_line1'] ?? '') ?>">
              <?php if (hasError('address_line1', $errors)): ?>
                <small class="form-error"><?= htmlspecialchars($errors['address_line1']) ?></small>
              <?php endif; ?>
            </div>
            
            <div class="form-group">
              <label class="form-label" for="address_line2">Address Line 2 (Optional)</label>
              <input type="text" id="address_line2" name="address_line2" 
                     class="form-input"
                     value="<?= htmlspecialchars($step2Data['address_line2'] ?? '') ?>">
            </div>
            
            <div class="form-group-flex-container">
              <div class="form-group form-group-flex">
                <label class="form-label" for="city">City <span class="required">*</span></label>
                <input type="text" id="city" name="city" 
                       class="form-input <?= errorClass('city', $errors) ?>"
                       value="<?= htmlspecialchars($step2Data['city'] ?? '') ?>">
                <?php if (hasError('city', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['city']) ?></small>
                <?php endif; ?>
              </div>
              
              <div class="form-group form-group-flex">
                <label class="form-label" for="state_province">State/Province <span class="required">*</span></label>
                <input type="text" id="state_province" name="state_province" 
                       class="form-input <?= errorClass('state_province', $errors) ?>"
                       value="<?= htmlspecialchars($step2Data['state_province'] ?? '') ?>">
                <?php if (hasError('state_province', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['state_province']) ?></small>
                <?php endif; ?>
              </div>
            </div>
            
            <div class="form-group-flex-container">
              <div class="form-group form-group-flex">
                <label class="form-label" for="postal_code">Postal Code <span class="required">*</span></label>
                <input type="text" id="postal_code" name="postal_code" 
                       class="form-input <?= errorClass('postal_code', $errors) ?>"
                       value="<?= htmlspecialchars($step2Data['postal_code'] ?? '') ?>">
                <?php if (hasError('postal_code', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['postal_code']) ?></small>
                <?php endif; ?>
              </div>
              
              <div class="form-group form-group-flex">
                <label class="form-label" for="country">Country <span class="required">*</span></label>
                <input type="text" id="country" name="country" 
                       class="form-input <?= errorClass('country', $errors) ?>"
                       value="<?= htmlspecialchars($step2Data['country'] ?? '') ?>">
                <?php if (hasError('country', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['country']) ?></small>
                <?php endif; ?>
              </div>
            </div>
          </div>
          
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Continue to Review →</button>
            <a href="<?= url('purches/checkout.php?step=1') ?>" class="btn btn-secondary">Edit Service Requirements</a>
          </div>
        </form>

      <?php elseif ($currentStep === 3): ?>
        <div class="checkout-review-layout">
          <div class="checkout-review-main">
            <h2>Review Your Order</h2>
            
            <?php foreach ($cartItems as $index => $service): ?>
              <?php 
              $serviceId = $service->getServiceId();
              $serviceData = $step1Data[$serviceId] ?? [];
              ?>
              <div class="card mb-3 checkout-review-service">
                <div class="checkout-review-service-header">
                  <h3><?= htmlspecialchars($service->getTitle()) ?></h3>
                  <p class="text-muted">by <a href="<?= url("services/browse.php?freelancer_id=" . $service->getFreelancerId()) ?>"><?= htmlspecialchars($service->getFreelancerName()) ?></a> - <?= $service->getFormattedPrice() ?></p>
                </div>
                
                <div class="checkout-review-service-content-expanded">
                  <div class="form-group">
                    <label class="form-label">Service Requirements</label>
                    <div class="checkout-review-text"><?= nl2br(htmlspecialchars($serviceData['requirements'] ?? '')) ?></div>
                  </div>
                  
                  <?php if (!empty($serviceData['special_instructions'])): ?>
                    <div class="form-group">
                      <label class="form-label">Special Instructions</label>
                      <div class="checkout-review-text"><?= nl2br(htmlspecialchars($serviceData['special_instructions'])) ?></div>
                    </div>
                  <?php endif; ?>
                  
                  <?php if (!empty($serviceData['preferred_deadline'])): ?>
                    <div class="form-group">
                      <label class="form-label">Preferred Deadline</label>
                      <div class="checkout-review-text"><?= date("M d, Y", strtotime($serviceData['preferred_deadline'])) ?></div>
                    </div>
                  <?php endif; ?>
                  
                  <?php if (!empty($serviceData['files'])): ?>
                    <div class="form-group">
                      <label class="form-label">Requirement Files</label>
                      <div class="file-display-container">
                        <?php foreach ($serviceData['files'] as $file): ?>
                          <div class="file-display-item">
                            <span class="file-icon">📄</span>
                            <div class="file-info">
                              <div class="file-name"><?= htmlspecialchars($file['original_filename']) ?></div>
                              <div class="file-meta"><?= formatFileSize($file['file_size']) ?></div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="form-group">
                      <label class="form-label">Requirement Files</label>
                      <div class="text-muted">No files uploaded</div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
            
            <div class="form-group">
              <label class="form-label">Payment Information</label>
              <div class="card">
                <p><strong>Payment Method:</strong> <?= htmlspecialchars($step2Data['payment_method'] ?? '') ?></p>
                <?php if ($step2Data['payment_method'] === 'Credit Card'): ?>
                  <p><strong>Card:</strong> **** **** **** <?= htmlspecialchars($step2Data['card_number'] ?? '') ?></p>
                  <p><strong>Cardholder:</strong> <?= htmlspecialchars($step2Data['cardholder_name'] ?? '') ?></p>
                  <p><strong>Expires:</strong> <?= htmlspecialchars($step2Data['expiration_date'] ?? '') ?></p>
                <?php endif; ?>
                <p><strong>Billing Address:</strong></p>
                <p><?= htmlspecialchars($step2Data['address_line1'] ?? '') ?><br>
                <?php if (!empty($step2Data['address_line2'])): ?>
                  <?= htmlspecialchars($step2Data['address_line2']) ?><br>
                <?php endif; ?>
                <?= htmlspecialchars($step2Data['city'] ?? '') ?>, <?= htmlspecialchars($step2Data['state_province'] ?? '') ?> <?= htmlspecialchars($step2Data['postal_code'] ?? '') ?><br>
                <?= htmlspecialchars($step2Data['country'] ?? '') ?></p>
              </div>
            </div>
            
            <form method="POST" action="" class="mt-3">
              <input type="hidden" name="action" value="place_order">
              
              <div class="form-group">
                <label class="checkbox-label">
                  <input type="checkbox" name="terms_agreement" value="1" required class="form-checkbox">
                  <span>I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a> <span class="required">*</span></span>
                </label>
                <?php if (hasError('terms_agreement', $errors)): ?>
                  <small class="form-error"><?= htmlspecialchars($errors['terms_agreement']) ?></small>
                <?php endif; ?>
              </div>
              
              <div class="form-actions">
                <button type="submit" class="btn btn-success btn-large" id="place-order-btn">Place Order</button>
                <a href="<?= url('purches/checkout.php?step=2') ?>" class="btn btn-secondary">Edit Payment Information</a>
                <a href="<?= url('purches/checkout.php?step=1') ?>" class="btn btn-secondary">Edit Service Requirements</a>
              </div>
            </form>
          </div>
          
          <div class="checkout-review-sidebar">
            <div class="card checkout-summary-card">
              <h3>Order Summary</h3>
              
              <p class="checkout-order-count">You will place <?= count($cartItems) ?> order<?= count($cartItems) > 1 ? 's' : '' ?></p>
              
              <div class="checkout-service-cards">
                <?php foreach ($cartItems as $service): ?>
                  <div class="checkout-service-summary-card">
                    <div class="checkout-service-summary-header">
                      <span class="checkout-checkmark">✓</span>
                      <div>
                        <div class="checkout-service-title"><?= htmlspecialchars($service->getTitle()) ?></div>
                        <div class="checkout-service-freelancer">
                          by <a href="<?= url("services/browse.php?freelancer_id=" . $service->getFreelancerId()) ?>"><?= htmlspecialchars($service->getFreelancerName()) ?></a>
                        </div>
                      </div>
                    </div>
                    <div class="checkout-service-summary-pricing">
                      <div class="checkout-price-row">
                        <span>Price:</span>
                        <span><?= $service->getFormattedPrice() ?></span>
                      </div>
                      <div class="checkout-price-row">
                        <span>Service Fee:</span>
                        <span><?= formatPrice($service->calculateServiceFee()) ?></span>
                      </div>
                      <div class="checkout-price-row checkout-price-total">
                        <span>Total:</span>
                        <span><?= formatPrice($service->getTotalWithFee()) ?></span>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              
              <div class="checkout-summary-totals">
                <div class="checkout-summary-row">
                  <span>Services Subtotal:</span>
                  <span><?= formatPrice($subtotal) ?></span>
                </div>
                <div class="checkout-summary-row">
                  <span>Total Service Fee (5%):</span>
                  <span><?= formatPrice($serviceFee) ?></span>
                </div>
                <div class="checkout-summary-row checkout-summary-grand-total">
                  <span>GRAND TOTAL:</span>
                  <span><?= formatPrice($grandTotal) ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<?php includeTemplate('footer'); ?>

</body>
</html>
