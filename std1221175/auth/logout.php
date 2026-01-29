<?php
require_once dirname(__DIR__) . '/db.php.inc';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_unset();
session_destroy();
session_regenerate_id(true);
header("Location: " . url("main.php"));
exit;
?>
