<?php
session_start();

require_once "../config/db.php";
require_once "../classes/Notification.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

$notificationObj = new Notification($conn);

$userId = $_SESSION["user_id"];
$notificationId = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if ($notificationId <= 0) {
    header("Location: " . getDashboardHome($_SESSION["role"]));
    exit();
}

$notification = $notificationObj->getNotificationById($notificationId, $userId);

if (!$notification) {
    header("Location: " . getDashboardHome($_SESSION["role"]));
    exit();
}

$notificationObj->markAsRead($notificationId, $userId);

$link = $notification["link"];

if (empty($link)) {
    $link = getDashboardHome($_SESSION["role"]);
}

header("Location: " . $link);
exit();

function getDashboardHome($role) {
    if ($role === "admin") {
        return "admin_dashboard.php?page=home";
    }

    if ($role === "informant") {
        return "informant_dashboard.php?page=home";
    }

    if ($role === "faculty") {
        return "faculty_dashboard.php?page=home";
    }

    if ($role === "student") {
        return "student_dashboard.php?page=home";
    }

    return "../login.php";
}
?>