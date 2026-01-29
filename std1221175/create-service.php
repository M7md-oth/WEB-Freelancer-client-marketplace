<?php
require_once __DIR__ . '/db.php.inc';

requireFreelancer();

$activeServicesSql = "SELECT COUNT(*) FROM services WHERE freelancer_id = :user_id AND status = 'Active'";
$activeServicesStmt = $pdo->prepare($activeServicesSql);
$activeServicesStmt->execute([":user_id" => $_SESSION["user_id"]]);
$activeServicesCount = $activeServicesStmt->fetchColumn();

if ($activeServicesCount >= 50) {
    flashMessage("error", "You have reached the maximum limit of 50 active services. Please deactivate some services before creating a new one.");
    header("Location: " . url("profile/index.php"));
    exit;
}

if (!isset($_SESSION['service_draft'])) {
    $_SESSION['service_draft'] = [
        'step' => 1,
        'basic_info' => [],
        'images' => []
    ];
}

$currentStep = isset($_GET['step']) ? intval($_GET['step']) : ($_SESSION['service_draft']['step'] ?? 1);

if ($currentStep < 1 || $currentStep > 3) {
    $currentStep = 1;
}

if ($currentStep > 1 && empty($_SESSION['service_draft']['basic_info'])) {
    flashMessage("error", "Please complete Step 1 first.");
    header("Location: " . url("create-service.php?step=1"));
    exit;
}

if ($currentStep > 2 && empty($_SESSION['service_draft']['images'])) {
    flashMessage("error", "Please complete Step 2 first.");
    header("Location: " . url("create-service.php?step=2"));
    exit;
}

$_SESSION['service_draft']['step'] = $currentStep;

switch ($currentStep) {
    case 1:
        require __DIR__ . '/services/create-step1.php';
        break;
    case 2:
        require __DIR__ . '/services/create-step2.php';
        break;
    case 3:
        require __DIR__ . '/services/create-step3.php';
        break;
    default:
        header("Location: " . url("create-service.php?step=1"));
        exit;
}
