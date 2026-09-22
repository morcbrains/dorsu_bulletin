<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_once "../classes/Dashboard.php";
require_once "../classes/SystemSetting.php";
require_once "../classes/Notification.php";
require_once "../classes/UserManager.php";

requireRole("admin");

$dashboard = new Dashboard($conn);
$settingObj = new SystemSetting($conn);
$notificationObj = new Notification($conn);
$userManager = new UserManager($conn);

$message = "";
$messageType = "";

$page = isset($_GET["page"]) ? $_GET["page"] : "home";

function getRoleDisplayName($role) {
    if ($role === "informant") {
        return "University Office Informants";
    }

    if ($role === "faculty") {
        return "Faculty Member";
    }

    if ($role === "student") {
        return "Student";
    }

    if ($role === "admin") {
        return "Admin";
    }

    return ucfirst($role);
}

$universityOffices = [
    "University Student Council (USC)",
    "Office of Student Affairs (OSA)",
    "Office of Student Counseling and Development (OSCD)",
    "Directorate for Information, Communication, and Technology (DICT)",
    "Financial Assistance Scholarship Student Unit (FASST Unit)",
    "National Service Training Program (NSTP)",
    "Admission Office",
    "Accounting Office",
    "University Registrar Office",
    "University Library"
];

$facultyList = [
    "Faculty of Computing, Engineering and Technology (FaCET)",
    "Faculty of Teacher Education (FTED)",
    "Faculty of Business Management (FBM)",
    "Faculty of Agriculture and Life Sciences (FALS)",
    "Faculty of Nursing and Allied Health Sciences (FNAHS)",
    "Faculty of Humanities, Social Sciences, and Communication (FHuSoCom)",
    "Faculty of Criminal Justice Education (FCJE)"
];

if ($page === "create_staff") {
    $page = "manage_users";
}

if (!in_array($page, ["home", "manage_users", "manage_announcements", "categories", "reports", "settings"])) {
    $page = "home";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_staff_account"])) {
    $fullName = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirm_password"];
    $role = trim($_POST["role"]);

    $studentId = null;
    $facultyId = !empty($_POST["faculty_id"]) ? trim($_POST["faculty_id"]) : null;
    $officeName = !empty($_POST["office_name"]) ? trim($_POST["office_name"]) : null;
    $program = null;
    $yearLevel = null;
    $section = null;
    $department = !empty($_POST["department"]) ? trim($_POST["department"]) : null;
    $status = "approved";

    if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword) || empty($role)) {
        $message = "Please fill in all required staff account fields.";
        $messageType = "error";
        $page = "manage_users";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
        $page = "manage_users";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";
        $page = "manage_users";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $messageType = "error";
        $page = "manage_users";
    } elseif (!in_array($role, ["informant", "faculty"])) {
        $message = "Invalid staff role selected.";
        $messageType = "error";
        $page = "manage_users";
    } elseif ($role === "informant" && empty($officeName)) {
        $message = "Please select a university office.";
        $messageType = "error";
        $page = "manage_users";
    } elseif ($role === "informant" && !in_array($officeName, $universityOffices)) {
        $message = "Invalid university office selected.";
        $messageType = "error";
        $page = "manage_users";
    } elseif ($role === "faculty" && (empty($facultyId) || empty($department))) {
        $message = "Please complete the Faculty ID and select a faculty.";
        $messageType = "error";
        $page = "manage_users";
    } elseif ($role === "faculty" && !in_array($department, $facultyList)) {
        $message = "Invalid faculty selected.";
        $messageType = "error";
        $page = "manage_users";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $message = "Email is already registered.";
            $messageType = "error";
            $page = "manage_users";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (
                    full_name,
                    email,
                    password,
                    role,
                    student_id,
                    faculty_id,
                    office_name,
                    program,
                    year_level,
                    section,
                    department,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssssssssssss",
                $fullName,
                $email,
                $hashedPassword,
                $role,
                $studentId,
                $facultyId,
                $officeName,
                $program,
                $yearLevel,
                $section,
                $department,
                $status
            );

            if ($stmt->execute()) {
                $message = getRoleDisplayName($role) . " account created successfully. The account is already approved and can now log in.";
                $messageType = "success";
                $page = "manage_users";
            } else {
                $message = "Failed to create staff account. Please try again.";
                $messageType = "error";
                $page = "manage_users";
            }

            $stmt->close();
        }

        $check->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"], $_POST["action"])) {
    $user_id = intval($_POST["user_id"]);
    $action = $_POST["action"];

    if ($action === "approve") {
        $status = "approved";
    } elseif ($action === "reject") {
        $status = "rejected";
    } else {
        $status = "";
    }

    if (!empty($status)) {
        if ($dashboard->updateUserStatus($user_id, $status)) {
            $message = "User status updated successfully.";
            $messageType = "success";
            $page = "manage_users";
        } else {
            $message = "Failed to update user status.";
            $messageType = "error";
            $page = "manage_users";
        }
    }
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_user_account"])) {
    $editUserId = intval($_POST["edit_user_id"]);
    $fullName = trim($_POST["edit_full_name"]);
    $email = trim($_POST["edit_email"]);
    $role = trim($_POST["edit_role"]);
    $status = trim($_POST["edit_status"]);

    $studentId = !empty($_POST["edit_student_id"]) ? trim($_POST["edit_student_id"]) : null;
    $facultyId = !empty($_POST["edit_faculty_id"]) ? trim($_POST["edit_faculty_id"]) : null;
    $officeName = !empty($_POST["edit_office_name"]) ? trim($_POST["edit_office_name"]) : null;
    $program = !empty($_POST["edit_program"]) ? trim($_POST["edit_program"]) : null;
    $yearLevel = !empty($_POST["edit_year_level"]) ? trim($_POST["edit_year_level"]) : null;
    $section = !empty($_POST["edit_section"]) ? trim($_POST["edit_section"]) : null;
    $department = !empty($_POST["edit_department"]) ? trim($_POST["edit_department"]) : null;

    if ($role === "student" && !empty($_POST["edit_department_student"])) {
        $department = trim($_POST["edit_department_student"]);
    }

    $newPassword = !empty($_POST["edit_password"]) ? $_POST["edit_password"] : "";

    if (empty($fullName) || empty($email) || empty($role) || empty($status)) {
        $message = "Please fill in all required user fields.";
        $messageType = "error";
        $page = "manage_users";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
        $page = "manage_users";
    } elseif (!in_array($role, ["admin", "informant", "faculty", "student"])) {
        $message = "Invalid role selected.";
        $messageType = "error";
        $page = "manage_users";
    } elseif (!in_array($status, ["pending", "approved", "rejected"])) {
        $message = "Invalid account status selected.";
        $messageType = "error";
        $page = "manage_users";
    } elseif ($role === "informant" && (empty($officeName) || !in_array($officeName, $universityOffices))) {
        $message = "Please select a valid university office for the University Office Informant.";
        $messageType = "error";
        $page = "manage_users";
    } elseif ($role === "faculty" && (empty($facultyId) || empty($department) || !in_array($department, $facultyList))) {
        $message = "Please complete the Faculty ID and select a valid faculty.";
        $messageType = "error";
        $page = "manage_users";
    } elseif ($role === "student" && (empty($studentId) || empty($department) || empty($program) || empty($yearLevel))) {
        $message = "Please complete the student ID, faculty, program, and year level for the student account.";
        $messageType = "error";
        $page = "manage_users";
    } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
        $message = "New password must be at least 6 characters.";
        $messageType = "error";
        $page = "manage_users";
    } else {
        if ($role !== "informant") {
            $officeName = null;
        }

        if ($role !== "faculty") {
            $facultyId = $role === "student" ? null : $facultyId;
        }

        if ($role !== "student") {
            $studentId = null;
            $program = null;
            $yearLevel = null;
            $section = null;
        }

        if ($role === "admin") {
            $studentId = null;
            $facultyId = null;
            $officeName = null;
            $program = null;
            $yearLevel = null;
            $section = null;
            $department = null;
        }

        $updateResult = $userManager->updateUser(
            $editUserId,
            $fullName,
            $email,
            $role,
            $studentId,
            $facultyId,
            $officeName,
            $program,
            $yearLevel,
            $section,
            $department,
            $status
        );

        if ($updateResult["success"]) {
            if (!empty($newPassword)) {
                $passwordResult = $userManager->updateUserPassword($editUserId, $newPassword);

                if (!$passwordResult["success"]) {
                    $message = $passwordResult["message"];
                    $messageType = "error";
                    $page = "manage_users";
                }
            }

            if (empty($message)) {
                $message = $updateResult["message"];
                $messageType = "success";
                $page = "manage_users";
            }
        } else {
            $message = $updateResult["message"];
            $messageType = "error";
            $page = "manage_users";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_user_account"])) {
    $deleteUserId = intval($_POST["delete_user_id"]);

    if ($deleteUserId === intval($_SESSION["user_id"])) {
        $message = "You cannot delete your own admin account while logged in.";
        $messageType = "error";
        $page = "manage_users";
    } else {
        $deleteResult = $userManager->deleteUser($deleteUserId);

        if ($deleteResult["success"]) {
            $message = $deleteResult["message"];
            $messageType = "success";
        } else {
            $message = $deleteResult["message"];
            $messageType = "error";
        }

        $page = "manage_users";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["announcement_id"], $_POST["announcement_action"])) {
    $announcement_id = intval($_POST["announcement_id"]);
    $announcement_action = $_POST["announcement_action"];

    if ($announcement_action === "publish") {
        $announcement_status = "published";
    } elseif ($announcement_action === "archive") {
        $announcement_status = "archived";
    } elseif ($announcement_action === "draft") {
        $announcement_status = "draft";
    } else {
        $announcement_status = "";
    }

    if (!empty($announcement_status)) {
        if ($dashboard->updateAnnouncementStatus($announcement_id, $announcement_status)) {
            $message = "Announcement status updated successfully.";
            $messageType = "success";
            $page = "manage_announcements";
        } else {
            $message = "Failed to update announcement status.";
            $messageType = "error";
            $page = "manage_announcements";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_category"])) {
    $category_name = trim($_POST["category_name"]);
    $category_description = trim($_POST["category_description"]);

    if (empty($category_name)) {
        $message = "Please enter a category name.";
        $messageType = "error";
        $page = "categories";
    } else {
        if ($dashboard->addCategory($category_name, $category_description)) {
            $message = "Category added successfully.";
            $messageType = "success";
            $page = "categories";
        } else {
            $message = "Failed to add category. The category may already exist.";
            $messageType = "error";
            $page = "categories";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["category_id"], $_POST["category_action"])) {
    $category_id = intval($_POST["category_id"]);
    $category_action = $_POST["category_action"];

    if ($category_action === "activate") {
        $category_status = "active";
    } elseif ($category_action === "deactivate") {
        $category_status = "inactive";
    } else {
        $category_status = "";
    }

    if (!empty($category_status)) {
        if ($dashboard->updateCategoryStatus($category_id, $category_status)) {
            $message = "Category status updated successfully.";
            $messageType = "success";
            $page = "categories";
        } else {
            $message = "Failed to update category status.";
            $messageType = "error";
            $page = "categories";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_settings"])) {
    $systemName = trim($_POST["system_name"]);
    $universityName = trim($_POST["university_name"]);
    $contactEmail = trim($_POST["contact_email"]);
    $maintenanceMode = trim($_POST["maintenance_mode"]);
    $defaultAccountStatus = trim($_POST["default_account_status"]);

    if (empty($systemName) || empty($universityName) || empty($contactEmail) || empty($maintenanceMode) || empty($defaultAccountStatus)) {
        $message = "Please fill in all settings fields.";
        $messageType = "error";
        $page = "settings";
    } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid contact email.";
        $messageType = "error";
        $page = "settings";
    } elseif (!in_array($maintenanceMode, ["on", "off"])) {
        $message = "Invalid maintenance mode selected.";
        $messageType = "error";
        $page = "settings";
    } elseif (!in_array($defaultAccountStatus, ["pending", "approved"])) {
        $message = "Invalid default account status selected.";
        $messageType = "error";
        $page = "settings";
    } else {
        $settingsToUpdate = [
            "system_name" => $systemName,
            "university_name" => $universityName,
            "contact_email" => $contactEmail,
            "maintenance_mode" => $maintenanceMode,
            "default_account_status" => $defaultAccountStatus
        ];

        if ($settingObj->updateSettings($settingsToUpdate)) {
            $message = "System settings updated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to update system settings.";
            $messageType = "error";
        }

        $page = "settings";
    }
}

$systemSettings = $settingObj->getAllSettings();

$systemName = isset($systemSettings["system_name"]) ? $systemSettings["system_name"] : "DOrSU Bulletin";
$universityName = isset($systemSettings["university_name"]) ? $systemSettings["university_name"] : "Davao Oriental State University";
$contactEmail = isset($systemSettings["contact_email"]) ? $systemSettings["contact_email"] : "dorsu.bulletin@dorsu.edu.ph";
$maintenanceMode = isset($systemSettings["maintenance_mode"]) ? $systemSettings["maintenance_mode"] : "off";
$defaultAccountStatus = isset($systemSettings["default_account_status"]) ? $systemSettings["default_account_status"] : "pending";

$totalUsers = $dashboard->getTotalUsers();
$pendingUsers = $dashboard->getTotalByStatus("pending");
$approvedUsers = $dashboard->getTotalByStatus("approved");
$rejectedUsers = $dashboard->getTotalByStatus("rejected");
$totalStudents = $dashboard->getTotalByRole("student");
$totalFaculty = $dashboard->getTotalByRole("faculty");
$totalInformants = $dashboard->getTotalByRole("informant");

$totalAnnouncements = $dashboard->getTotalAnnouncements();
$publishedAnnouncements = $dashboard->getTotalAnnouncementsByStatus("published");
$draftAnnouncements = $dashboard->getTotalAnnouncementsByStatus("draft");
$archivedAnnouncements = $dashboard->getTotalAnnouncementsByStatus("archived");
$urgentAnnouncements = $dashboard->getTotalUrgentAnnouncements();

$totalCategories = $dashboard->getTotalCategories();
$activeCategories = $dashboard->getTotalCategoriesByStatus("active");
$inactiveCategories = $dashboard->getTotalCategoriesByStatus("inactive");

$pendingList = $userManager->getPendingUsers();
$allUsersList = $userManager->getAllUsers();
$editingUser = null;

if ($page === "manage_users" && isset($_GET["edit_user"])) {
    $editingUser = $userManager->getUserById(intval($_GET["edit_user"]));
}

$recentUsers = $dashboard->getRecentUsers();
$allAnnouncements = $dashboard->getAllAnnouncements();
$allCategories = $dashboard->getAllCategories();

$userRoleReport = $dashboard->getUserCountByRoleReport();
$userStatusReport = $dashboard->getUserCountByStatusReport();
$announcementStatusReport = $dashboard->getAnnouncementCountByStatusReport();
$announcementUrgencyReport = $dashboard->getAnnouncementCountByUrgencyReport();
$announcementCategoryReport = $dashboard->getAnnouncementCountByCategory();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | DOrSU Connect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
    :root {
        --dorsu-blue-dark: #002b7f;
        --dorsu-blue: #003fae;
        --dorsu-blue-light: #0b63d8;
        --dorsu-gold: #f6c400;
        --dorsu-gold-dark: #d9a400;
        --dorsu-gold-light: #fff7cc;
        --dorsu-white: #ffffff;
        --dorsu-bg: #f4f7ff;
        --dorsu-soft-blue: #eef4ff;
        --dorsu-text: #1f2937;
        --dorsu-muted: #5b6472;
        --dorsu-shadow: rgba(0, 43, 127, 0.22);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: Arial, sans-serif;
    }

    body {
        background:
            radial-gradient(circle at top left, rgba(246, 196, 0, 0.18), transparent 30%),
            linear-gradient(135deg, #f4f7ff, #ffffff);
        color: var(--dorsu-text);
        overflow-x: hidden;
    }

    .layout {
        min-height: 100vh;
        display: flex;
    }

    .sidebar {
        width: 78px;
        background: linear-gradient(180deg, var(--dorsu-blue-dark), var(--dorsu-blue), var(--dorsu-blue-light));
        color: white;
        padding: 22px 14px;
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 100;
        transition: width 0.35s ease;
        box-shadow: 8px 0 30px rgba(0, 43, 127, 0.22);
        overflow: hidden;
        border-right: 1px solid rgba(246, 196, 0, 0.35);
    }

    .sidebar:hover {
        width: 300px;
    }

    .menu-icon {
        width: 50px;
        height: 50px;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.18);
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 25px;
        cursor: pointer;
        transition: 0.3s;
        flex-shrink: 0;
        border: 1px solid rgba(246, 196, 0, 0.28);
    }

    .menu-icon:hover {
        background: rgba(246, 196, 0, 0.25);
        transform: scale(1.05);
    }

    .menu-icon span {
        position: relative;
        width: 24px;
        height: 3px;
        background: white;
        border-radius: 20px;
        display: block;
    }

    .menu-icon span::before,
    .menu-icon span::after {
        content: "";
        position: absolute;
        left: 0;
        width: 24px;
        height: 3px;
        background: white;
        border-radius: 20px;
    }

    .menu-icon span::before {
        top: -8px;
    }

    .menu-icon span::after {
        top: 8px;
    }

    .brand {
        opacity: 0;
        transform: translateX(-15px);
        transition: 0.3s ease;
        white-space: nowrap;
        margin-bottom: 28px;
        pointer-events: none;
    }

    .sidebar:hover .brand {
        opacity: 1;
        transform: translateX(0);
        pointer-events: auto;
    }

    .brand h2 {
        font-size: 25px;
        margin-bottom: 6px;
        color: white;
    }

    .brand p {
        font-size: 13px;
        opacity: 0.9;
        color: #fff7cc;
    }

    .admin-profile {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 13px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.14);
        margin-bottom: 25px;
        min-width: 260px;
        border: 1px solid rgba(246, 196, 0, 0.25);
    }

    .avatar {
        width: 47px;
        height: 47px;
        border-radius: 50%;
        background: var(--dorsu-gold-light);
        color: var(--dorsu-blue-dark);
        display: flex;
        justify-content: center;
        align-items: center;
        font-weight: bold;
        font-size: 18px;
        flex-shrink: 0;
        border: 2px solid var(--dorsu-gold);
    }

    .profile-text {
        opacity: 0;
        transform: translateX(-12px);
        transition: 0.3s ease;
        white-space: nowrap;
    }

    .sidebar:hover .profile-text {
        opacity: 1;
        transform: translateX(0);
    }

    .profile-text h3 {
        font-size: 14px;
        margin-bottom: 3px;
        max-width: 185px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .profile-text p {
        font-size: 12px;
        opacity: 0.85;
        max-width: 185px;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #fff7cc;
    }

    .nav {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .nav a {
        min-width: 260px;
        display: block;
        padding: 14px 18px;
        color: white;
        text-decoration: none;
        border-radius: 16px;
        transition: 0.3s;
        position: relative;
        font-weight: bold;
        font-size: 14px;
        white-space: nowrap;
        opacity: 0;
        transform: translateX(-12px);
        pointer-events: none;
    }

    .sidebar:hover .nav a {
        opacity: 1;
        transform: translateX(0);
        pointer-events: auto;
    }

    .nav a:hover,
    .nav a.active {
        background: rgba(246, 196, 0, 0.25);
        transform: translateX(4px);
        color: white;
    }

    .logout {
        margin-top: 18px;
        background: var(--dorsu-gold-light) !important;
        color: var(--dorsu-blue-dark) !important;
        font-weight: bold;
        text-align: center;
        border: 1px solid var(--dorsu-gold);
    }

    .main {
        margin-left: 78px;
        width: calc(100% - 78px);
        padding: 30px;
        transition: 0.35s ease;
    }

    .dashboard-header {
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.20), transparent 35%),
            linear-gradient(135deg, var(--dorsu-blue-dark), var(--dorsu-blue), var(--dorsu-gold));
        color: white;
        padding: 32px;
        border-radius: 25px;
        box-shadow: 0 18px 40px var(--dorsu-shadow);
        margin-bottom: 25px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(246, 196, 0, 0.35);
    }

    .dashboard-header::before {
        content: "";
        position: absolute;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.12);
        top: -90px;
        right: -70px;
    }

    .dashboard-header::after {
        content: "";
        position: absolute;
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: rgba(246, 196, 0, 0.18);
        bottom: -60px;
        right: 150px;
    }

    .header-content {
        position: relative;
        z-index: 2;
    }

    .header-badge {
        display: inline-block;
        background: rgba(255, 247, 204, 0.22);
        color: #fff7cc;
        padding: 9px 15px;
        border-radius: 30px;
        margin-bottom: 16px;
        font-size: 13px;
        font-weight: bold;
        border: 1px solid rgba(255, 247, 204, 0.35);
    }

    .dashboard-header h1 {
        font-size: 34px;
        margin-bottom: 10px;
    }

    .dashboard-header p {
        max-width: 780px;
        line-height: 1.6;
        opacity: 0.95;
    }

    .message {
        padding: 15px 18px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-weight: bold;
    }

    .success {
        background: #ecfff1;
        color: #187333;
        border: 1px solid #a8e8b4;
    }

    .error {
        background: #fff0f0;
        color: #9b0000;
        border: 1px solid #ffc9c9;
    }

    .cards,
    .summary-grid,
    .reports-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 25px;
    }

    .reports-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .card,
    .summary-box,
    .report-box,
    .form-box,
    .xml-export-box {
        background: white;
        padding: 23px;
        border-radius: 22px;
        box-shadow: 0 10px 28px rgba(0, 43, 127, 0.08);
        border: 1px solid #e3eaf8;
        position: relative;
        overflow: hidden;
        transition: 0.3s;
    }

    .card::before,
    .summary-box::before,
    .report-box::before,
    .form-box::before,
    .xml-export-box::before {
        content: "";
        position: absolute;
        width: 7px;
        top: 0;
        bottom: 0;
        left: 0;
        background: var(--dorsu-gold);
    }

    .card:hover,
    .summary-box:hover,
    .report-box:hover,
    .xml-export-box:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 35px rgba(0, 43, 127, 0.14);
    }

    .card-top {
        margin-bottom: 16px;
    }

    .card h3,
    .summary-box p {
        font-size: 15px;
        color: var(--dorsu-muted);
        font-weight: bold;
    }

    .card h2,
    .summary-box h3 {
        font-size: 34px;
        color: var(--dorsu-blue-dark);
        margin-bottom: 6px;
    }

    .section {
        background: white;
        padding: 25px;
        border-radius: 22px;
        box-shadow: 0 10px 28px rgba(0, 43, 127, 0.08);
        margin-bottom: 25px;
        overflow-x: auto;
        border: 1px solid #e3eaf8;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 18px;
    }

    .section h2,
    .form-box h2,
    .report-box h2,
    .xml-export-box h2 {
        color: var(--dorsu-blue-dark);
        margin-bottom: 15px;
    }

    .section-label {
        background: var(--dorsu-gold-light);
        color: var(--dorsu-blue-dark);
        padding: 8px 14px;
        border-radius: 30px;
        font-size: 13px;
        font-weight: bold;
        border: 1px solid rgba(246, 196, 0, 0.55);
    }

    .form-box {
        margin-bottom: 25px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 2fr auto;
        gap: 15px;
        align-items: end;
    }

    .settings-grid,
    .staff-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        align-items: end;
    }

    .staff-fields {
        display: none;
        grid-column: 1 / 3;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        padding: 18px;
        border-radius: 18px;
        background: #f8fbff;
        border: 1px solid #dce7fa;
        border-left: 7px solid var(--dorsu-gold);
    }

    .staff-fields.active {
        display: grid;
    }

    .form-group.full {
        grid-column: 1 / 3;
    }

    .form-group label {
        display: block;
        margin-bottom: 7px;
        font-weight: bold;
        color: var(--dorsu-blue-dark);
        font-size: 14px;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 13px;
        border: 1px solid #cfd8ea;
        border-radius: 12px;
        outline: none;
        font-size: 14px;
        color: var(--dorsu-text);
        background: white;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 48px;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        border-color: var(--dorsu-blue);
        box-shadow: 0 0 0 3px rgba(0, 63, 174, 0.13);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 950px;
    }

    th, td {
        padding: 15px;
        border-bottom: 1px solid #e7edf8;
        text-align: left;
        font-size: 14px;
        vertical-align: top;
    }

    th {
        background: #eef4ff;
        color: var(--dorsu-blue-dark);
        font-size: 13px;
        text-transform: uppercase;
    }

    tr:hover td {
        background: #f8fbff;
    }

    .report-table {
        min-width: 100%;
    }

    .badge {
        display: inline-block;
        padding: 7px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        text-transform: capitalize;
    }

    .pending,
    .draft {
        background: var(--dorsu-gold-light);
        color: #856300;
    }

    .approved,
    .published,
    .active {
        background: #dfffe6;
        color: #187333;
    }

    .rejected,
    .archived,
    .inactive {
        background: #ffe0e0;
        color: #9b0000;
    }

    .role,
    .normal {
        background: #eef4ff;
        color: var(--dorsu-blue-dark);
        border: 1px solid rgba(0, 63, 174, 0.15);
    }

    .urgent {
        background: #ffe0e0;
        color: #9b0000;
    }

    .description-cell {
        max-width: 280px;
        line-height: 1.5;
    }

    .action-form {
        display: inline-block;
        margin-right: 5px;
        margin-bottom: 5px;
    }

    .btn {
        border: none;
        padding: 10px 14px;
        border-radius: 11px;
        cursor: pointer;
        font-weight: bold;
        color: white;
        transition: 0.25s;
        text-decoration: none;
        display: inline-block;
    }

    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 18px rgba(0, 43, 127, 0.18);
    }

    .approve,
    .publish,
    .activate,
    .add-btn {
        background: linear-gradient(135deg, #16803a, #1fa34a);
    }

    .reject,
    .archive,
    .deactivate {
        background: linear-gradient(135deg, #9b0000, #c40000);
    }

    .draft-btn {
        background: linear-gradient(135deg, var(--dorsu-gold-dark), var(--dorsu-gold));
        color: #3b2b00;
    }

    .xml-btn {
        background: linear-gradient(135deg, var(--dorsu-blue-dark), var(--dorsu-blue));
    }

    .empty {
        color: var(--dorsu-muted);
        background: #f8fbff;
        padding: 18px;
        border-radius: 14px;
        border: 1px dashed #c7d5ef;
    }

    .xml-export-box p {
        color: var(--dorsu-muted);
        line-height: 1.6;
        margin-bottom: 18px;
    }

    .xml-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    @media (max-width: 1100px) {
        .cards,
        .summary-grid,
        .reports-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .sidebar {
            width: 100%;
            height: 74px;
            right: 0;
            bottom: auto;
            padding: 12px 16px;
        }

        .sidebar:hover {
            width: 100%;
            height: auto;
            min-height: 460px;
        }

        .menu-icon {
            margin-bottom: 12px;
        }

        .brand {
            margin-bottom: 12px;
        }

        .admin-profile {
            min-width: 100%;
        }

        .nav a {
            min-width: 100%;
        }

        .main {
            margin-left: 0;
            width: 100%;
            padding: 95px 18px 18px;
        }

        .cards,
        .summary-grid,
        .reports-grid,
        .settings-grid,
        .staff-grid,
        .staff-fields {
            grid-template-columns: 1fr;
        }

        .staff-fields,
        .form-group.full {
            grid-column: 1 / 2;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-header {
            padding: 25px;
        }

        .dashboard-header h1 {
            font-size: 28px;
        }

        .section-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="menu-icon">
            <span></span>
        </div>

        <div class="brand">
            <h2>DOrSU Connect</h2>
            <p>University Announcement System</p>
        </div>

        <div class="admin-profile">
            <div class="avatar">
                <?php echo strtoupper(substr(getUserFullName(), 0, 1)); ?>
            </div>

            <div class="profile-text">
                <h3><?php echo htmlspecialchars(getUserFullName()); ?></h3>
                <p><?php echo htmlspecialchars(getUserEmail()); ?></p>
            </div>
        </div>

        <nav class="nav">
            <a href="admin_dashboard.php?page=home" class="<?php echo $page === 'home' ? 'active' : ''; ?>">Home</a>
            <a href="admin_dashboard.php?page=manage_users" class="<?php echo $page === 'manage_users' ? 'active' : ''; ?>">Manage Users</a>
            <a href="admin_dashboard.php?page=manage_announcements" class="<?php echo $page === 'manage_announcements' ? 'active' : ''; ?>">Manage Announcements</a>
            <a href="admin_dashboard.php?page=categories" class="<?php echo $page === 'categories' ? 'active' : ''; ?>">Categories</a>
            <a href="admin_dashboard.php?page=reports" class="<?php echo $page === 'reports' ? 'active' : ''; ?>">Reports</a>
            <a href="admin_dashboard.php?page=settings" class="<?php echo $page === 'settings' ? 'active' : ''; ?>">Settings</a>
            <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </aside>

    <main class="main">
        <?php include __DIR__ . "/notification_bell.php"; ?>

        <?php if ($page === "home"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Admin Dashboard</span>
                    <h1>Welcome, <?php echo htmlspecialchars(getUserFullName()); ?>!</h1>
                    <p>
                        <?php echo htmlspecialchars($dashboard->getWelcomeMessage("admin")); ?>
                        Use this dashboard to approve students, create staff accounts,
                        monitor account status, and prepare the system for official university announcements.
                    </p>
                </div>
            </section>

            <?php include __DIR__ . "/weather_widget.php"; ?>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="cards">
                <div class="card">
                    <div class="card-top">
                        <h3>Total Users</h3>
                    </div>
                    <h2><?php echo $totalUsers; ?></h2>
                </div>

                <div class="card">
                    <div class="card-top">
                        <h3>Pending Accounts</h3>
                    </div>
                    <h2><?php echo $pendingUsers; ?></h2>
                </div>

                <div class="card">
                    <div class="card-top">
                        <h3>Approved Accounts</h3>
                    </div>
                    <h2><?php echo $approvedUsers; ?></h2>
                </div>

                <div class="card">
                    <div class="card-top">
                        <h3>Rejected Accounts</h3>
                    </div>
                    <h2><?php echo $rejectedUsers; ?></h2>
                </div>

                <div class="card">
                    <div class="card-top">
                        <h3>Students</h3>
                    </div>
                    <h2><?php echo $totalStudents; ?></h2>
                </div>

                <div class="card">
                    <div class="card-top">
                        <h3>Faculty Members</h3>
                    </div>
                    <h2><?php echo $totalFaculty; ?></h2>
                </div>

                <div class="card">
                    <div class="card-top">
                        <h3>University Office Informants</h3>
                    </div>
                    <h2><?php echo $totalInformants; ?></h2>
                </div>

                <div class="card">
                    <div class="card-top">
                        <h3>Total Announcements</h3>
                    </div>
                    <h2><?php echo $totalAnnouncements; ?></h2>
                </div>
            </section>

        <?php elseif ($page === "create_staff"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Create Staff Account</span>
                    <h1>Create University Office Informant or Faculty Account</h1>
                    <p>
                        Create approved login accounts for University Office Informants and Faculty Members.
                        Student accounts are still created through the public student sign up page.
                    </p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalInformants; ?></h3>
                    <p>University Office Informants</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalFaculty; ?></h3>
                    <p>Faculty Members</p>
                </div>

                <div class="summary-box">
                    <h3>Approved</h3>
                    <p>Default Staff Status</p>
                </div>

                <div class="summary-box">
                    <h3>Admin</h3>
                    <p>Account Creator</p>
                </div>
            </section>

            <section class="form-box">
                <h2>Staff Account Form</h2>

                <form method="POST" action="admin_dashboard.php?page=create_staff">
                    <div class="staff-grid">
                        <div class="form-group full">
                            <label>Full Name</label>
                            <input type="text" name="full_name" placeholder="Enter full name" required>
                        </div>

                        <div class="form-group full">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="Enter active email address" required>
                        </div>

                        <div class="form-group full">
                            <label>Staff Role</label>
                            <select name="role" id="staffRole" required onchange="showStaffFields()">
                                <option value="">Select staff role</option>
                                <option value="informant">University Office Informant</option>
                                <option value="faculty">Faculty Member</option>
                            </select>
                        </div>

                        <div id="officeFields" class="staff-fields">
                            <div class="form-group full">
                                <label>University Office</label>
                                <select name="office_name">
                                    <option value="">Select university office</option>
                                    <?php foreach ($universityOffices as $office): ?>
                                        <option value="<?php echo htmlspecialchars($office); ?>">
                                            <?php echo htmlspecialchars($office); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="facultyFields" class="staff-fields">
                            <div class="form-group">
                                <label>Faculty ID</label>
                                <input type="text" name="faculty_id" placeholder="Enter faculty ID">
                            </div>

                            <div class="form-group">
                                <label>Faculty</label>
                                <select name="department">
                                    <option value="">Select faculty</option>
                                    <?php foreach ($facultyList as $faculty): ?>
                                        <option value="<?php echo htmlspecialchars($faculty); ?>">
                                            <?php echo htmlspecialchars($faculty); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Create password" required>
                        </div>

                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm password" required>
                        </div>
                    </div>

                    <br>

                    <button type="submit" name="create_staff_account" class="btn add-btn">
                        Create Staff Account
                    </button>
                </form>
            </section>

            <section class="section">
                <div class="section-header">
                    <h2>Account Creation Guide</h2>
                    <span class="section-label">Staff accounts only</span>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Required Details</th>
                            <th>Default Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>University Office Informant</td>
                            <td>Full Name, Email, Password, Office Name</td>
                            <td><span class="badge approved">approved</span></td>
                        </tr>

                        <tr>
                            <td>Faculty Member</td>
                            <td>Full Name, Email, Password, Faculty ID, Department</td>
                            <td><span class="badge approved">approved</span></td>
                        </tr>

                        <tr>
                            <td>Student</td>
                            <td>Students must use public student sign up and wait for approval.</td>
                            <td><span class="badge pending">pending</span></td>
                        </tr>
                    </tbody>
                </table>
            </section>

        <?php elseif ($page === "manage_users"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Manage Users</span>
                    <h1>User Management CRUD</h1>
                    <p>
                        Create staff accounts, view registered users, update account details and status,
                        approve or reject pending students, and delete user accounts when necessary.
                    </p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Users</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $pendingUsers; ?></h3>
                    <p>Pending Accounts</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $approvedUsers; ?></h3>
                    <p>Approved Accounts</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $rejectedUsers; ?></h3>
                    <p>Rejected Accounts</p>
                </div>
            </section>

            <section class="form-box">
                <h2>Create User / Staff Account</h2>

                <form method="POST" action="admin_dashboard.php?page=manage_users">
                    <div class="staff-grid">
                        <div class="form-group full">
                            <label>Full Name</label>
                            <input type="text" name="full_name" placeholder="Enter full name" required>
                        </div>

                        <div class="form-group full">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="Enter active email address" required>
                        </div>

                        <div class="form-group full">
                            <label>Staff Role</label>
                            <select name="role" id="staffRole" required onchange="showStaffFields()">
                                <option value="">Select staff role</option>
                                <option value="informant">University Office Informant</option>
                                <option value="faculty">Faculty Member</option>
                            </select>
                        </div>

                        <div id="officeFields" class="staff-fields">
                            <div class="form-group full">
                                <label>University Office</label>
                                <select name="office_name">
                                    <option value="">Select university office</option>
                                    <?php foreach ($universityOffices as $office): ?>
                                        <option value="<?php echo htmlspecialchars($office); ?>">
                                            <?php echo htmlspecialchars($office); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="facultyFields" class="staff-fields">
                            <div class="form-group">
                                <label>Faculty ID</label>
                                <input type="text" name="faculty_id" placeholder="Enter faculty ID">
                            </div>

                            <div class="form-group">
                                <label>Faculty</label>
                                <select name="department">
                                    <option value="">Select faculty</option>
                                    <?php foreach ($facultyList as $faculty): ?>
                                        <option value="<?php echo htmlspecialchars($faculty); ?>">
                                            <?php echo htmlspecialchars($faculty); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Create password" required>
                        </div>

                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm password" required>
                        </div>
                    </div>

                    <br>

                    <button type="submit" name="create_staff_account" class="btn add-btn">
                        Create Account
                    </button>
                </form>
            </section>

            <?php if ($editingUser): ?>
                <section class="form-box">
                    <h2>Edit User Account</h2>

                    <form method="POST" action="admin_dashboard.php?page=manage_users">
                        <input type="hidden" name="edit_user_id" value="<?php echo htmlspecialchars($editingUser["id"]); ?>">

                        <div class="staff-grid">
                            <div class="form-group full">
                                <label>Full Name</label>
                                <input type="text" name="edit_full_name" value="<?php echo htmlspecialchars($editingUser["full_name"]); ?>" required>
                            </div>

                            <div class="form-group full">
                                <label>Email Address</label>
                                <input type="email" name="edit_email" value="<?php echo htmlspecialchars($editingUser["email"]); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Role</label>
                                <select name="edit_role" id="editRole" required onchange="showEditFields()">
                                    <option value="admin" <?php echo $editingUser["role"] === "admin" ? "selected" : ""; ?>>Admin</option>
                                    <option value="informant" <?php echo $editingUser["role"] === "informant" ? "selected" : ""; ?>>University Office Informant</option>
                                    <option value="faculty" <?php echo $editingUser["role"] === "faculty" ? "selected" : ""; ?>>Faculty Member</option>
                                    <option value="student" <?php echo $editingUser["role"] === "student" ? "selected" : ""; ?>>Student</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select name="edit_status" required>
                                    <option value="pending" <?php echo $editingUser["status"] === "pending" ? "selected" : ""; ?>>Pending</option>
                                    <option value="approved" <?php echo $editingUser["status"] === "approved" ? "selected" : ""; ?>>Approved</option>
                                    <option value="rejected" <?php echo $editingUser["status"] === "rejected" ? "selected" : ""; ?>>Rejected</option>
                                </select>
                            </div>

                            <div id="editOfficeFields" class="staff-fields">
                                <div class="form-group full">
                                    <label>University Office</label>
                                    <select name="edit_office_name">
                                        <option value="">Select university office</option>
                                        <?php foreach ($universityOffices as $office): ?>
                                            <option value="<?php echo htmlspecialchars($office); ?>" <?php echo $editingUser["office_name"] === $office ? "selected" : ""; ?>>
                                                <?php echo htmlspecialchars($office); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="editFacultyFields" class="staff-fields">
                                <div class="form-group">
                                    <label>Faculty ID</label>
                                    <input type="text" name="edit_faculty_id" value="<?php echo htmlspecialchars($editingUser["faculty_id"] ?? ""); ?>" placeholder="Enter faculty ID">
                                </div>

                                <div class="form-group">
                                    <label>Faculty</label>
                                    <select name="edit_department">
                                        <option value="">Select faculty</option>
                                        <?php foreach ($facultyList as $faculty): ?>
                                            <option value="<?php echo htmlspecialchars($faculty); ?>" <?php echo $editingUser["department"] === $faculty ? "selected" : ""; ?>>
                                                <?php echo htmlspecialchars($faculty); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="editStudentFields" class="staff-fields">
                                <div class="form-group">
                                    <label>Student ID</label>
                                    <input type="text" name="edit_student_id" value="<?php echo htmlspecialchars($editingUser["student_id"] ?? ""); ?>" placeholder="Enter student ID">
                                </div>

                                <div class="form-group">
                                    <label>Faculty</label>
                                    <select name="edit_department_student">
                                        <option value="">Select faculty</option>
                                        <?php foreach ($facultyList as $faculty): ?>
                                            <option value="<?php echo htmlspecialchars($faculty); ?>" <?php echo $editingUser["department"] === $faculty ? "selected" : ""; ?>>
                                                <?php echo htmlspecialchars($faculty); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Program</label>
                                    <input type="text" name="edit_program" value="<?php echo htmlspecialchars($editingUser["program"] ?? ""); ?>" placeholder="Enter program">
                                </div>

                                <div class="form-group">
                                    <label>Year Level</label>
                                    <input type="text" name="edit_year_level" value="<?php echo htmlspecialchars($editingUser["year_level"] ?? ""); ?>" placeholder="Enter year level">
                                </div>

                                <div class="form-group full">
                                    <label>Section</label>
                                    <input type="text" name="edit_section" value="<?php echo htmlspecialchars($editingUser["section"] ?? ""); ?>" placeholder="Enter section">
                                </div>
                            </div>

                            <div class="form-group full">
                                <label>New Password</label>
                                <input type="password" name="edit_password" placeholder="Leave blank if you do not want to change password">
                            </div>
                        </div>

                        <br>

                        <button type="submit" name="update_user_account" class="btn add-btn">
                            Update User Account
                        </button>

                        <a href="admin_dashboard.php?page=manage_users" class="btn draft-btn">
                            Cancel Edit
                        </a>
                    </form>
                </section>
            <?php elseif (isset($_GET["edit_user"])): ?>
                <div class="message error">
                    User account not found.
                </div>
            <?php endif; ?>

            <section class="section">
                <div class="section-header">
                    <h2>Pending Account Approval</h2>
                    <span class="section-label"><?php echo $pendingUsers; ?> pending</span>
                </div>

                <?php if ($pendingList && $pendingList->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Details</th>
                                <th>Date Registered</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($user = $pendingList->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user["full_name"]); ?></td>
                                    <td><?php echo htmlspecialchars($user["email"]); ?></td>
                                    <td>
                                        <span class="badge role">
                                            <?php echo htmlspecialchars(getRoleDisplayName($user["role"])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user["role"] === "student"): ?>
                                            Student ID: <?php echo htmlspecialchars($user["student_id"]); ?><br>
                                            Faculty: <?php echo htmlspecialchars($user["department"]); ?><br>
                                            Program: <?php echo htmlspecialchars($user["program"]); ?><br>
                                            Year Level: <?php echo htmlspecialchars($user["year_level"]); ?>
                                        <?php elseif ($user["role"] === "faculty"): ?>
                                            Faculty ID: <?php echo htmlspecialchars($user["faculty_id"]); ?><br>
                                            Department: <?php echo htmlspecialchars($user["department"]); ?>
                                        <?php elseif ($user["role"] === "informant"): ?>
                                            Office: <?php echo htmlspecialchars($user["office_name"]); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user["created_at"]); ?></td>
                                    <td>
                                        <form method="POST" class="action-form" action="admin_dashboard.php?page=manage_users">
                                            <input type="hidden" name="user_id" value="<?php echo $user["id"]; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn approve">Approve</button>
                                        </form>

                                        <form method="POST" class="action-form" action="admin_dashboard.php?page=manage_users">
                                            <input type="hidden" name="user_id" value="<?php echo $user["id"]; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn reject">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">No pending accounts at the moment.</div>
                <?php endif; ?>
            </section>

            <section class="section">
                <div class="section-header">
                    <h2>All Users</h2>
                    <span class="section-label">Full CRUD table</span>
                </div>

                <?php if ($allUsersList && $allUsersList->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Details</th>
                                <th>Date Registered</th>
                                <th>CRUD Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($user = $allUsersList->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user["full_name"]); ?></td>
                                    <td><?php echo htmlspecialchars($user["email"]); ?></td>
                                    <td>
                                        <span class="badge role">
                                            <?php echo htmlspecialchars(getRoleDisplayName($user["role"])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo htmlspecialchars($user["status"]); ?>">
                                            <?php echo htmlspecialchars($user["status"]); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user["role"] === "student"): ?>
                                            Student ID: <?php echo htmlspecialchars($user["student_id"]); ?><br>
                                            Faculty: <?php echo htmlspecialchars($user["department"]); ?><br>
                                            Program: <?php echo htmlspecialchars($user["program"]); ?><br>
                                            Year Level: <?php echo htmlspecialchars($user["year_level"]); ?>
                                        <?php elseif ($user["role"] === "faculty"): ?>
                                            Faculty ID: <?php echo htmlspecialchars($user["faculty_id"]); ?><br>
                                            Faculty: <?php echo htmlspecialchars($user["department"]); ?>
                                        <?php elseif ($user["role"] === "informant"): ?>
                                            Office: <?php echo htmlspecialchars($user["office_name"]); ?>
                                        <?php else: ?>
                                            Administrator Account
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user["created_at"]); ?></td>
                                    <td>
                                        <a href="admin_dashboard.php?page=manage_users&edit_user=<?php echo $user["id"]; ?>" class="btn draft-btn">
                                            Edit
                                        </a>

                                        <?php if (intval($user["id"]) !== intval($_SESSION["user_id"])): ?>
                                            <form method="POST" class="action-form" action="admin_dashboard.php?page=manage_users" onsubmit="return confirm('Are you sure you want to delete this user account?');">
                                                <input type="hidden" name="delete_user_id" value="<?php echo $user["id"]; ?>">
                                                <button type="submit" name="delete_user_account" class="btn reject">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge role">Current Admin</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">No users found.</div>
                <?php endif; ?>
            </section>

        <?php elseif ($page === "manage_announcements"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Manage Announcements</span>
                    <h1>Announcement Management</h1>
                    <p>View, monitor, publish, draft, or archive announcements posted by authorized users.</p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalAnnouncements; ?></h3>
                    <p>Total Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $publishedAnnouncements; ?></h3>
                    <p>Published</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $draftAnnouncements; ?></h3>
                    <p>Draft</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $urgentAnnouncements; ?></h3>
                    <p>Urgent</p>
                </div>
            </section>

            <section class="section">
                <div class="section-header">
                    <h2>All Announcements</h2>
                    <span class="section-label"><?php echo $archivedAnnouncements; ?> archived</span>
                </div>

                <?php if ($allAnnouncements && $allAnnouncements->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Target</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th>Posted By</th>
                                <th>Date Posted</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($announcement = $allAnnouncements->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($announcement["title"]); ?></td>
                                    <td class="description-cell">
                                        <?php echo htmlspecialchars(substr($announcement["description"], 0, 130)); ?>
                                        <?php echo strlen($announcement["description"]) > 130 ? "..." : ""; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($announcement["category"]); ?></td>
                                    <td><?php echo htmlspecialchars($announcement["target_audience"]); ?></td>
                                    <td><span class="badge <?php echo htmlspecialchars($announcement["urgency"]); ?>"><?php echo htmlspecialchars($announcement["urgency"]); ?></span></td>
                                    <td><span class="badge <?php echo htmlspecialchars($announcement["status"]); ?>"><?php echo htmlspecialchars($announcement["status"]); ?></span></td>
                                    <td>
                                        <?php echo htmlspecialchars($announcement["posted_by_name"]); ?><br>
                                        <span class="badge role">
                                            <?php echo htmlspecialchars(getRoleDisplayName($announcement["posted_by_role"])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($announcement["created_at"]); ?></td>
                                    <td>
                                        <form method="POST" class="action-form" action="admin_dashboard.php?page=manage_announcements">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="publish">
                                            <button type="submit" class="btn publish">Publish</button>
                                        </form>

                                        <form method="POST" class="action-form" action="admin_dashboard.php?page=manage_announcements">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="draft">
                                            <button type="submit" class="btn draft-btn">Draft</button>
                                        </form>

                                        <form method="POST" class="action-form" action="admin_dashboard.php?page=manage_announcements">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="archive">
                                            <button type="submit" class="btn archive">Archive</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">No announcements yet.</div>
                <?php endif; ?>
            </section>

        <?php elseif ($page === "categories"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Categories</span>
                    <h1>Announcement Categories</h1>
                    <p>Create and manage announcement categories used for organizing university updates.</p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalCategories; ?></h3>
                    <p>Total Categories</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $activeCategories; ?></h3>
                    <p>Active Categories</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $inactiveCategories; ?></h3>
                    <p>Inactive Categories</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalAnnouncements; ?></h3>
                    <p>Total Announcements</p>
                </div>
            </section>

            <section class="form-box">
                <h2>Add New Category</h2>

                <form method="POST" action="admin_dashboard.php?page=categories">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Category Name</label>
                            <input type="text" name="category_name" placeholder="Enter Category Name" required>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="category_description" placeholder="Enter category description"></textarea>
                        </div>

                        <button type="submit" name="add_category" class="btn add-btn">Add Category</button>
                    </div>
                </form>
            </section>

            <section class="section">
                <div class="section-header">
                    <h2>Category List</h2>
                    <span class="section-label"><?php echo $totalCategories; ?> categories</span>
                </div>

                <?php if ($allCategories && $allCategories->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Date Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($category = $allCategories->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category["category_name"]); ?></td>
                                    <td class="description-cell"><?php echo htmlspecialchars($category["description"]); ?></td>
                                    <td><span class="badge <?php echo htmlspecialchars($category["status"]); ?>"><?php echo htmlspecialchars($category["status"]); ?></span></td>
                                    <td><?php echo htmlspecialchars($category["created_at"]); ?></td>
                                    <td>
                                        <form method="POST" class="action-form" action="admin_dashboard.php?page=categories">
                                            <input type="hidden" name="category_id" value="<?php echo $category["id"]; ?>">
                                            <input type="hidden" name="category_action" value="activate">
                                            <button type="submit" class="btn activate">Activate</button>
                                        </form>

                                        <form method="POST" class="action-form" action="admin_dashboard.php?page=categories">
                                            <input type="hidden" name="category_id" value="<?php echo $category["id"]; ?>">
                                            <input type="hidden" name="category_action" value="deactivate">
                                            <button type="submit" class="btn deactivate">Deactivate</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">No categories found.</div>
                <?php endif; ?>
            </section>

        <?php elseif ($page === "reports"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Reports</span>
                    <h1>System Reports</h1>
                    <p>View summarized reports about users, announcements, categories, roles, statuses, urgency levels, and XML/XSLT report exports.</p>
                </div>
            </section>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Users</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalAnnouncements; ?></h3>
                    <p>Total Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalCategories; ?></h3>
                    <p>Total Categories</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $urgentAnnouncements; ?></h3>
                    <p>Urgent Announcements</p>
                </div>
            </section>

            <section class="xml-export-box">
                <h2>XML and XSLT Reports</h2>
                <p>
                    Export live database records to XML, view the raw XML tags, then transform the parsed XML into HTML using XSLT.
                    Each button below has a different purpose, similar to the AidLink reporting workflow.
                </p>

                <div class="xml-buttons">
                    <a href="../exports/export_announcements_xml.php" class="btn xml-btn" target="_blank">
                        Export Announcements XML
                    </a>

                    <a href="../exports/export_announcements_html.php" class="btn xml-btn" target="_blank">
                        Generate Announcements HTML via XSLT
                    </a>

                    <a href="../exports/export_events_xml.php" class="btn xml-btn" target="_blank">
                        Export Events XML
                    </a>

                    <a href="../exports/export_events_html.php" class="btn xml-btn" target="_blank">
                        Generate Events HTML via XSLT
                    </a>

                    <a href="../exports/export_dorsu_bulletin_xml.php" class="btn xml-btn" target="_blank">
                        Export Full Bulletin XML
                    </a>

                    <a href="../exports/export_dorsu_bulletin_html.php" class="btn xml-btn" target="_blank">
                        Generate Full Bulletin HTML via XSLT
                    </a>
                </div>
            </section>

            <section class="reports-grid">
                <div class="report-box">
                    <h2>Users by Role</h2>

                    <?php if ($userRoleReport && $userRoleReport->num_rows > 0): ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php while ($row = $userRoleReport->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span class="badge role">
                                                <?php echo htmlspecialchars(getRoleDisplayName($row["role"])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row["total"]); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty">No role report available.</div>
                    <?php endif; ?>
                </div>

                <div class="report-box">
                    <h2>Users by Status</h2>

                    <?php if ($userStatusReport && $userStatusReport->num_rows > 0): ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php while ($row = $userStatusReport->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="badge <?php echo htmlspecialchars($row["status"]); ?>"><?php echo htmlspecialchars($row["status"]); ?></span></td>
                                        <td><?php echo htmlspecialchars($row["total"]); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty">No status report available.</div>
                    <?php endif; ?>
                </div>

                <div class="report-box">
                    <h2>Announcements by Status</h2>

                    <?php if ($announcementStatusReport && $announcementStatusReport->num_rows > 0): ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php while ($row = $announcementStatusReport->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="badge <?php echo htmlspecialchars($row["status"]); ?>"><?php echo htmlspecialchars($row["status"]); ?></span></td>
                                        <td><?php echo htmlspecialchars($row["total"]); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty">No announcement status report available.</div>
                    <?php endif; ?>
                </div>

                <div class="report-box">
                    <h2>Announcements by Urgency</h2>

                    <?php if ($announcementUrgencyReport && $announcementUrgencyReport->num_rows > 0): ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Urgency</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php while ($row = $announcementUrgencyReport->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="badge <?php echo htmlspecialchars($row["urgency"]); ?>"><?php echo htmlspecialchars($row["urgency"]); ?></span></td>
                                        <td><?php echo htmlspecialchars($row["total"]); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty">No urgency report available.</div>
                    <?php endif; ?>
                </div>

                <div class="report-box">
                    <h2>Announcements by Category</h2>

                    <?php if ($announcementCategoryReport && $announcementCategoryReport->num_rows > 0): ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php while ($row = $announcementCategoryReport->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row["category"]); ?></td>
                                        <td><?php echo htmlspecialchars($row["total"]); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty">No category report available.</div>
                    <?php endif; ?>
                </div>

                <div class="report-box">
                    <h2>System Summary</h2>

                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>System Area</th>
                                <th>Total</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>Total Users</td>
                                <td><?php echo $totalUsers; ?></td>
                            </tr>

                            <tr>
                                <td>Total Announcements</td>
                                <td><?php echo $totalAnnouncements; ?></td>
                            </tr>

                            <tr>
                                <td>Total Categories</td>
                                <td><?php echo $totalCategories; ?></td>
                            </tr>

                            <tr>
                                <td>Active Categories</td>
                                <td><?php echo $activeCategories; ?></td>
                            </tr>

                            <tr>
                                <td>Pending Accounts</td>
                                <td><?php echo $pendingUsers; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($page === "settings"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Admin Settings</span>
                    <h1>System Settings</h1>
                    <p>
                        Manage the basic configuration of DOrSU Bulletin including system identity,
                        contact information, maintenance mode, and default account approval behavior.
                    </p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo htmlspecialchars($maintenanceMode === "on" ? "ON" : "OFF"); ?></h3>
                    <p>Maintenance Mode</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo htmlspecialchars(ucfirst($defaultAccountStatus)); ?></h3>
                    <p>Default Account Status</p>
                </div>

                <div class="summary-box">
                    <h3>Admin</h3>
                    <p>Settings Access</p>
                </div>

                <div class="summary-box">
                    <h3>System</h3>
                    <p>Configuration</p>
                </div>
            </section>

            <section class="form-box">
                <h2>Update System Settings</h2>

                <form method="POST" action="admin_dashboard.php?page=settings">
                    <div class="settings-grid">
                        <div class="form-group">
                            <label>System Name</label>
                            <input type="text" name="system_name" value="<?php echo htmlspecialchars($systemName); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>University Name</label>
                            <input type="text" name="university_name" value="<?php echo htmlspecialchars($universityName); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" name="contact_email" value="<?php echo htmlspecialchars($contactEmail); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Maintenance Mode</label>
                            <select name="maintenance_mode" required>
                                <option value="off" <?php echo $maintenanceMode === "off" ? "selected" : ""; ?>>Off</option>
                                <option value="on" <?php echo $maintenanceMode === "on" ? "selected" : ""; ?>>On</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Default Account Approval</label>
                            <select name="default_account_status" required>
                                <option value="pending" <?php echo $defaultAccountStatus === "pending" ? "selected" : ""; ?>>Pending Approval</option>
                                <option value="approved" <?php echo $defaultAccountStatus === "approved" ? "selected" : ""; ?>>Automatically Approved</option>
                            </select>
                        </div>
                    </div>

                    <br>

                    <button type="submit" name="update_settings" class="btn add-btn">
                        Save Settings
                    </button>
                </form>
            </section>

            <section class="section">
                <div class="section-header">
                    <h2>Settings Guide</h2>
                    <span class="section-label">Configuration reference</span>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>Use</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>System Name</td>
                            <td>Name displayed for your announcement system.</td>
                        </tr>

                        <tr>
                            <td>University Name</td>
                            <td>Official university name displayed in system pages.</td>
                        </tr>

                        <tr>
                            <td>Contact Email</td>
                            <td>Email shown as the system contact address.</td>
                        </tr>

                        <tr>
                            <td>Maintenance Mode</td>
                            <td>Can be used later to temporarily block normal users from logging in while the admin updates the system.</td>
                        </tr>

                        <tr>
                            <td>Default Account Approval</td>
                            <td>Controls whether new accounts should start as pending or automatically approved.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

        <?php endif; ?>
    </main>
</div>

<script>
function showStaffFields() {
    const role = document.getElementById("staffRole");
    const officeFields = document.getElementById("officeFields");
    const facultyFields = document.getElementById("facultyFields");

    if (!role || !officeFields || !facultyFields) {
        return;
    }

    officeFields.classList.remove("active");
    facultyFields.classList.remove("active");

    if (role.value === "informant") {
        officeFields.classList.add("active");
    } else if (role.value === "faculty") {
        facultyFields.classList.add("active");
    }
}

function showEditFields() {
    const role = document.getElementById("editRole");
    const officeFields = document.getElementById("editOfficeFields");
    const facultyFields = document.getElementById("editFacultyFields");
    const studentFields = document.getElementById("editStudentFields");

    if (!role || !officeFields || !facultyFields || !studentFields) {
        return;
    }

    officeFields.classList.remove("active");
    facultyFields.classList.remove("active");
    studentFields.classList.remove("active");

    if (role.value === "informant") {
        officeFields.classList.add("active");
    } else if (role.value === "faculty") {
        facultyFields.classList.add("active");
    } else if (role.value === "student") {
        studentFields.classList.add("active");
    }
}

document.addEventListener("DOMContentLoaded", function () {
    showStaffFields();
    showEditFields();
});
</script>

</body>
</html>