<?php
require_once dirname(__DIR__) . '/db.php.inc';

requireClient();

$service_id = $_GET["service_id"] ?? "";
$error = "";

$service = null;
if (!empty($service_id)) {
    $sql = "SELECT s.*, u.first_name, u.last_name, u.user_id as freelancer_id
            FROM services s
            JOIN users u ON s.freelancer_id = u.user_id
            WHERE s.service_id = :service_id AND s.status = 'Active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":service_id" => $service_id]);
    $service = $stmt->fetch();
}

if (!$service) {
    header("Location: " . url("services/browse.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requirements = trim($_POST["requirements"] ?? "");
    $payment_method = trim($_POST["payment_method"] ?? "");

    if (empty($requirements)) {
        $error = "Please provide your requirements.";
    } elseif (empty($payment_method)) {
        $error = "Please select a payment method.";
    } else {
        $idSql = "SELECT MAX(CAST(order_id AS UNSIGNED)) as max_id FROM orders";
        $idStmt = $pdo->query($idSql);
        $maxId = $idStmt->fetch()["max_id"];
        $newOrderId = $maxId ? (string)((int)$maxId + 1) : "3000000001";
        $newOrderId = str_pad($newOrderId, 10, "0", STR_PAD_LEFT);

        $expected_delivery = date("Y-m-d", strtotime("+" . $service["delivery_time"] . " days"));

        $insertSql = "INSERT INTO orders (order_id, client_id, freelancer_id, service_id, service_title, price, delivery_time, revisions_included, requirements, status, payment_method, expected_delivery) 
                      VALUES (:order_id, :client_id, :freelancer_id, :service_id, :service_title, :price, :delivery_time, :revisions_included, :requirements, 'Pending', :payment_method, :expected_delivery)";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ":order_id" => $newOrderId,
            ":client_id" => $_SESSION["user_id"],
            ":freelancer_id" => $service["freelancer_id"],
            ":service_id" => $service["service_id"],
            ":service_title" => $service["title"],
            ":price" => $service["price"],
            ":delivery_time" => $service["delivery_time"],
            ":revisions_included" => $service["revisions_included"],
            ":requirements" => $requirements,
            ":payment_method" => $payment_method,
            ":expected_delivery" => $expected_delivery
        ]);

        if (isset($_FILES["requirement_file"]) && $_FILES["requirement_file"]["error"] === UPLOAD_ERR_OK) {
            $uploadDir = basePath("uploads/orders/" . $newOrderId . "/");
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $originalName = $_FILES["requirement_file"]["name"];
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $filename = "req_" . uniqid() . "." . $ext;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES["requirement_file"]["tmp_name"], $targetPath)) {
                $fileSize = $_FILES["requirement_file"]["size"];
                
                $fileSql = "INSERT INTO file_attachments (order_id, file_path, original_filename, file_size, file_type) 
                            VALUES (:order_id, :file_path, :original_filename, :file_size, 'requirement')";
                $fileStmt = $pdo->prepare($fileSql);
                $fileStmt->execute([
                    ":order_id" => $newOrderId,
                    ":file_path" => "uploads/orders/" . $newOrderId . "/" . $filename,
                    ":original_filename" => $originalName,
                    ":file_size" => $fileSize
                ]);
            }
        }

        header("Location: " . url("orders/details.php?id=" . $newOrderId . "&success=1"));
        exit;
    }
}
ob_start();
?>
      <div class="breadcrumbs">
        <a href="<?= url('services/browse.php') ?>">Browse Services</a>
        <span class="breadcrumb-separator">&gt;</span>
        <a href="<?= url('services/details.php?id=' . $service_id) ?>"><?= htmlspecialchars($service["title"]) ?></a>
        <span class="breadcrumb-separator">&gt;</span>
        <span class="breadcrumb-current">Place Order</span>
      </div>

      <h1>Place Order</h1>

      <div class="d-flex gap-3">
        <div style="flex:2">

          <div class="card">
            <h2>Order Details</h2>
            <form method="POST" action="" enctype="multipart/form-data" class="form-container">
              <div class="form-group">
                <label class="form-label" for="requirements">Your Requirements <span class="required">*</span></label>
                <textarea id="requirements" name="requirements" rows="8" required class="form-textarea"
                          placeholder="Describe in detail."><?= htmlspecialchars($_POST["requirements"] ?? "") ?></textarea>
                <small class="text-muted">Be as specific as possible to help the freelancer understand your needs.</small>
              </div>

              <div class="form-group">
                <label class="form-label" for="requirement_file">Attach File (Optional)</label>
                <input type="file" id="requirement_file" name="requirement_file" class="form-input">
                <small class="text-muted">Upload any reference files that might help.</small>
              </div>

              <div class="form-group">
                <label class="form-label" for="payment_method">Payment Method <span class="required">*</span></label>
                <select id="payment_method" name="payment_method" required class="form-select">
                  <option value="">Select payment method</option>
                  <option value="Credit Card" <?= (($_POST["payment_method"] ?? "") === "Credit Card") ? "selected" : "" ?>>Credit Card</option>
                  <option value="PayPal" <?= (($_POST["payment_method"] ?? "") === "PayPal") ? "selected" : "" ?>>PayPal</option>
                  <option value="Bank Transfer" <?= (($_POST["payment_method"] ?? "") === "Bank Transfer") ? "selected" : "" ?>>Bank Transfer</option>
                </select>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Place Order - <?= formatPrice($service["price"]) ?></button>
                <a href="<?= url('services/details.php?id=' . $service_id) ?>" class="btn btn-secondary">Cancel</a>
              </div>
            </form>
          </div>
        </div>

        <div style="flex:1">
          <div class="card">
            <h3>Order Summary</h3>
            
            <img src="<?= !empty($service["image_1"]) ? BASE_URL . "/" . htmlspecialchars($service["image_1"]) : DEFAULT_SERVICE_IMAGE ?>" alt="<?= htmlspecialchars($service["title"]) ?>" class="w-100 mb-2" style="border-radius:8px;">
            
            <h4><?= htmlspecialchars($service["title"]) ?></h4>
            <p class="text-muted">by <?= formatFullName($service["first_name"], $service["last_name"]) ?></p>
            
            <div class="info-grid mt-2">
              <div class="info-card">
                <span class="info-label">Price</span>
                <span class="info-value info-value-price"><?= formatPrice($service["price"]) ?></span>
              </div>
              <div class="info-card">
                <span class="info-label">Delivery</span>
                <span class="info-value"><?= (int)$service["delivery_time"] ?> day(s)</span>
              </div>
              <div class="info-card">
                <span class="info-label">Revisions</span>
                <span class="info-value"><?= (int)$service["revisions_included"] ?></span>
              </div>
              <div class="info-card">
                <span class="info-label">Expected By</span>
                <span class="info-value"><?= date("M d, Y", strtotime("+" . $service["delivery_time"] . " days")) ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
<?php
$content = ob_get_clean();
renderPage('Place Order', $content, ['currentPage' => $_SERVER["REQUEST_URI"]]);
