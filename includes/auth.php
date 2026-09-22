<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirectByRole($role) {
    if ($role === "admin") {
        header("Location: ../dashboards/admin_dashboard.php");
        exit();
    } elseif ($role === "informant") {
        header("Location: ../dashboards/informant_dashboard.php");
        exit();
    } elseif ($role === "faculty") {
        header("Location: ../dashboards/faculty_dashboard.php");
        exit();
    } elseif ($role === "student") {
        header("Location: ../dashboards/student_dashboard.php");
        exit();
    } else {
        header("Location: ../login.php");
        exit();
    }
}

function requireRole($allowedRole) {
    if (!isset($_SESSION["user_id"])) {
        header("Location: ../login.php");
        exit();
    }

    if ($_SESSION["role"] !== $allowedRole) {
        redirectByRole($_SESSION["role"]);
    }
}

function getUserFullName() {
    return isset($_SESSION["full_name"]) ? $_SESSION["full_name"] : "User";
}

function getUserEmail() {
    return isset($_SESSION["email"]) ? $_SESSION["email"] : "";
}
?>