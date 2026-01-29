<?php

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', 86400);
        session_set_cookie_params(86400);
        session_start();
    }
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

function requireLogin() {
    if (!isset($_SESSION["user_id"])) {
        flashMessage("error", "Please login to access this page");
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== $role) {
        flashMessage("error", "Access denied. This page requires {$role} role.");
        header("Location: " . BASE_URL . "/main.php");
        exit;
    }
}

function requireClient() {
    requireRole("Client");
}

function requireFreelancer() {
    requireRole("Freelancer");
}

function getStatusClass($status) {
    $classes = [
        "Pending" => "status-pending",
        "In Progress" => "status-in-progress",
        "Delivered" => "status-active",
        "Completed" => "status-completed",
        "Revision Requested" => "status-pending",
        "Cancelled" => "status-cancelled",
        "Active" => "status-active",
        "Inactive" => "status-inactive"
    ];
    return $classes[$status] ?? "status-inactive";
}

function hasError($field, $errors) {
    return isset($errors[$field]);
}

function errorClass($field, $errors) {
    return isset($errors[$field]) ? 'input-error' : '';
}

function processServiceImageUpload($file, $uploadDir, $allowedExtensions = ['jpg', 'jpeg', 'png'], $maxFileSize = 5242880, $minWidth = 800, $minHeight = 600) {
    if (!isset($file) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null, 'error' => null, 'width' => null, 'height' => null];
    }
    
    if ($file["error"] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'File upload failed.', 'width' => null, 'height' => null];
    }
    
    if ($file["size"] > $maxFileSize) {
        return ['success' => false, 'path' => null, 'error' => 'File size exceeds 5MB limit.', 'width' => null, 'height' => null];
    }
    
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'path' => null, 'error' => 'Invalid file type. Allowed: JPG, JPEG, PNG only.', 'width' => null, 'height' => null];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);
    
    $allowedMimes = ['image/jpeg', 'image/png'];
    if (!in_array($mimeType, $allowedMimes)) {
        return ['success' => false, 'path' => null, 'error' => 'Invalid image file.', 'width' => null, 'height' => null];
    }
    
    $imageInfo = @getimagesize($file["tmp_name"]);
    if ($imageInfo === false) {
        return ['success' => false, 'path' => null, 'error' => 'Invalid image file.', 'width' => null, 'height' => null];
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    if ($width < $minWidth || $height < $minHeight) {
        return ['success' => false, 'path' => null, 'error' => "Image dimensions must be at least {$minWidth}x{$minHeight} pixels. Your image is {$width}x{$height}.", 'width' => $width, 'height' => $height];
    }
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = uniqid() . "." . $ext;
    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file["tmp_name"], $targetPath)) {
        $basePath = dirname(__DIR__) . "/";
        $relativePath = str_replace($basePath, "", $targetPath);
        return ['success' => true, 'path' => $relativePath, 'error' => null, 'width' => $width, 'height' => $height];
    }
    
    return ['success' => false, 'path' => null, 'error' => 'Failed to save uploaded file.', 'width' => null, 'height' => null];
}

function processImageUpload($file, $uploadDir, $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'], $maxFileSize = 2097152) {
    if (!isset($file) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null, 'error' => null];
    }
    
    if ($file["error"] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'File upload failed.'];
    }
    
    if ($file["size"] > $maxFileSize) {
        return ['success' => false, 'path' => null, 'error' => 'File size exceeds limit.'];
    }
    
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'path' => null, 'error' => 'Invalid file type. Allowed: jpg, jpeg, png, webp.'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);
    
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes)) {
        return ['success' => false, 'path' => null, 'error' => 'Invalid image file.'];
    }
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = uniqid() . "." . $ext;
    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file["tmp_name"], $targetPath)) {
        $basePath = dirname(__DIR__) . "/";
        $relativePath = str_replace($basePath, "", $targetPath);
        return ['success' => true, 'path' => $relativePath, 'error' => null];
    }
    
    return ['success' => false, 'path' => null, 'error' => 'Failed to save uploaded file.'];
}

function processDeliverableUpload($file, $uploadDir, $allowedExtensions, $allowedMimeTypes, $maxFileSize) {
    if (!isset($file) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null, 'filename' => null, 'original' => null, 'size' => null, 'error' => null];
    }
    
    if ($file["error"] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'filename' => null, 'original' => null, 'size' => null, 'error' => 'File upload failed.'];
    }
    
    if ($file["size"] > $maxFileSize) {
        return ['success' => false, 'path' => null, 'filename' => null, 'original' => null, 'size' => null, 'error' => 'File size exceeds 10MB limit.'];
    }
    
    $originalName = $file["name"];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'path' => null, 'filename' => null, 'original' => null, 'size' => null, 'error' => 'Invalid file type. Allowed: pdf, zip, rar, png, jpg, jpeg, webp, doc, docx.'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);
    
    if (!isset($allowedMimeTypes[$ext]) || !in_array($mimeType, $allowedMimeTypes[$ext])) {
        if ($mimeType !== 'application/octet-stream' || !in_array($ext, ['zip', 'rar', 'doc', 'docx'])) {
            return ['success' => false, 'path' => null, 'filename' => null, 'original' => null, 'size' => null, 'error' => 'File type does not match extension.'];
        }
    }
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $safeFilename = "del_" . uniqid() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $targetPath = $uploadDir . $safeFilename;
    
    if (move_uploaded_file($file["tmp_name"], $targetPath)) {
        return ['success' => true, 'path' => $targetPath, 'filename' => $safeFilename, 'original' => $originalName, 'size' => $file["size"], 'error' => null];
    }
    
    return ['success' => false, 'path' => null, 'filename' => null, 'original' => null, 'size' => null, 'error' => 'Failed to save uploaded file.'];
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . " MB";
    if ($bytes >= 1024) return round($bytes / 1024, 2) . " KB";
    return $bytes . " bytes";
}

function getFileExt($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return 'img';
    if (in_array($ext, ['doc', 'docx'])) return 'doc';
    if (in_array($ext, ['zip', 'rar'])) return 'zip';
    if ($ext === 'pdf') return 'pdf';
    return 'file';
}

function basePath($relativePath = '') {
    return dirname(__DIR__) . ($relativePath ? '/' . ltrim($relativePath, '/') : '');
}

function includeTemplate($template) {
    include basePath("includes/{$template}.php");
}

function includeComponent($component) {
    include basePath("includes/components/{$component}.php");
}

spl_autoload_register(function ($class) {
    $file = basePath("includes/models/{$class}.php");
    if (file_exists($file)) {
        require_once $file;
    }
});

function flashMessage($type, $message) {
    $_SESSION[$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION[$type])) {
        $message = $_SESSION[$type];
        unset($_SESSION[$type]);
        return $message;
    }
    return null;
}

function renderFlashMessages() {
    $success = getFlashMessage('success');
    $error = getFlashMessage('error');
    ?>
    <?php if ($success): ?>
        <div class="message-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php
}

function formatPrice($price) {
    return '$' . number_format((float)$price, 2);
}

function formatDate($date) {
    return date("M d, Y", strtotime($date));
}

function formatFullName($firstName, $lastName) {
    return htmlspecialchars($firstName . " " . $lastName);
}

function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

function validateName($name, $fieldName = 'name') {
    $name = trim($name);
    
    if (empty($name)) {
        return ['valid' => false, 'error' => ucfirst(str_replace('_', ' ', $fieldName)) . " is required."];
    }
    
    if (strlen($name) < 2 || strlen($name) > 50) {
        return ['valid' => false, 'error' => ucfirst(str_replace('_', ' ', $fieldName)) . " must be between 2 and 50 characters."];
    }
    
    if (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
        return ['valid' => false, 'error' => ucfirst(str_replace('_', ' ', $fieldName)) . " can only contain letters and spaces."];
    }
    
    return ['valid' => true, 'error' => null];
}

function validateEmail($email) {
    $email = trim($email);
    
    if (empty($email)) {
        return ['valid' => false, 'error' => "Email address is required."];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => "Please enter a valid email address."];
    }
    
    return ['valid' => true, 'error' => null];
}

function validatePhone($phone) {
    $phone = trim($phone);
    
    if (empty($phone)) {
        return ['valid' => false, 'error' => "Phone number is required."];
    }
    
    if (!preg_match("/^[0-9]{10}$/", $phone)) {
        return ['valid' => false, 'error' => "Phone number must be exactly 10 digits."];
    }
    
    return ['valid' => true, 'error' => null];
}

function validatePassword($password, $required = true) {
    $password = trim($password);
    
    if (empty($password)) {
        if ($required) {
            return ['valid' => false, 'error' => "Password is required.", 'errors' => []];
        }
        return ['valid' => true, 'error' => null, 'errors' => []];
    }
    
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "at least 8 characters";
    }
    if (!preg_match("/[A-Z]/", $password)) {
        $errors[] = "uppercase letter";
    }
    if (!preg_match("/[a-z]/", $password)) {
        $errors[] = "lowercase letter";
    }
    if (!preg_match("/[0-9]/", $password)) {
        $errors[] = "number";
    }
    if (!preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?]/", $password)) {
        $errors[] = "special character";
    }
    
    if (!empty($errors)) {
        return ['valid' => false, 'error' => "Password must contain: " . implode(", ", $errors) . ".", 'errors' => $errors];
    }
    
    return ['valid' => true, 'error' => null, 'errors' => []];
}

function validatePasswordConfirm($password, $confirmPassword) {
    $confirmPassword = trim($confirmPassword);
    
    if (empty($confirmPassword)) {
        return ['valid' => false, 'error' => "Please confirm your password."];
    }
    
    if ($password !== $confirmPassword) {
        return ['valid' => false, 'error' => "Passwords do not match."];
    }
    
    return ['valid' => true, 'error' => null];
}

function validateBio($bio, $required = false, $maxLength = 500) {
    $bio = trim($bio);
    
    if (empty($bio)) {
        if ($required) {
            return ['valid' => false, 'error' => "Bio is required for Freelancers."];
        }
        return ['valid' => true, 'error' => null];
    }
    
    if (strlen($bio) > $maxLength) {
        return ['valid' => false, 'error' => "Bio must not exceed {$maxLength} characters."];
    }
    
    return ['valid' => true, 'error' => null];
}

function validateCity($city, $allowedCities = []) {
    $city = trim($city);
    
    if (empty($city)) {
        return ['valid' => false, 'error' => "City is required."];
    }
    
    if (!empty($allowedCities) && !in_array($city, $allowedCities)) {
        return ['valid' => false, 'error' => "Please select a valid city from the list."];
    }
    
    return ['valid' => true, 'error' => null];
}

function validateCurrentPassword($currentPassword, $hashedPassword) {
    $currentPassword = trim($currentPassword);
    
    if (empty($currentPassword)) {
        return ['valid' => false, 'error' => "Current password is required to change password."];
    }
    
    if (!password_verify($currentPassword, $hashedPassword)) {
        return ['valid' => false, 'error' => "Current password is incorrect."];
    }
    
    return ['valid' => true, 'error' => null];
}

function validateFields($rules) {
    $errors = [];
    $allValid = true;
    
    foreach ($rules as $fieldName => $rule) {
        $value = $rule['value'] ?? '';
        $type = $rule['type'] ?? 'text';
        $required = $rule['required'] ?? true;
        $options = $rule['options'] ?? [];
        
        switch ($type) {
            case 'name':
                $result = validateName($value, $fieldName);
                break;
            case 'email':
                $result = validateEmail($value);
                break;
            case 'phone':
                $result = validatePhone($value);
                break;
            case 'password':
                $result = validatePassword($value, $required);
                break;
            case 'bio':
                $result = validateBio($value, $required, $options['max_length'] ?? 500);
                break;
            case 'city':
                $result = validateCity($value, $options['allowed_cities'] ?? []);
                break;
            default:
                if ($required && empty(trim($value))) {
                    $result = ['valid' => false, 'error' => ucfirst(str_replace('_', ' ', $fieldName)) . " is required."];
                } else {
                    $result = ['valid' => true, 'error' => null];
                }
        }
        
        if (!$result['valid']) {
            $errors[$fieldName] = $result['error'];
            $allValid = false;
        }
    }
    
    return ['valid' => $allValid, 'errors' => $errors];
}

