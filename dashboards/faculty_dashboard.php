<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_once "../classes/Dashboard.php";
require_once "../classes/Announcement.php";
require_once "../classes/Event.php";
require_once "../classes/Notification.php";

requireRole("faculty");

$dashboard = new Dashboard();
$announcementObj = new Announcement($conn);
$eventObj = new Event($conn);
$notificationObj = new Notification($conn);

$message = "";
$messageType = "";

$page = isset($_GET["page"]) ? $_GET["page"] : "home";

if (!in_array($page, ["home", "profile", "university_announcements", "create_announcement", "my_announcements", "university_events"])) {
    $page = "home";
}

$userId = $_SESSION["user_id"];

$facultyPrograms = [
    "Faculty of Computing, Engineering and Technology (FaCET)" => [
        "BS Information Technology",
        "BS Civil Engineering",
        "Industrial Technology Management",
        "BS Mathematics with Research Statistics"
    ],
    "Faculty of Teacher Education (FTED)" => [
        "Bachelor of Elementary Education",
        "Bachelor of Secondary Education",
        "Bachelor of Physical Education",
        "Bachelor of Early Childhood Education",
        "Bachelor of Special Needs Education"
    ],
    "Faculty of Business Management (FBM)" => [
        "BS Business Administration",
        "BS Hospitality Management"
    ],
    "Faculty of Agriculture and Life Sciences (FALS)" => [
        "BS Agribusiness Management",
        "Bachelor of Agricultural Technology",
        "BS Biology",
        "BS Environmental Science"
    ],
    "Faculty of Nursing and Allied Health Sciences (FNAHS)" => [
        "BS Nursing"
    ],
    "Faculty of Humanities, Social Sciences, and Communication (FHuSoCom)" => [
        "BA Political Science",
        "BS Psychology",
        "Bachelor of Development Communication"
    ],
    "Faculty of Criminal Justice Education (FCJE)" => [
        "BS Criminology"
    ]
];

$currentFaculty = "";

$stmtFaculty = $conn->prepare("
    SELECT department 
    FROM users 
    WHERE id = ? 
    AND role = 'faculty'
    LIMIT 1
");
$stmtFaculty->bind_param("i", $userId);
$stmtFaculty->execute();
$facultyResult = $stmtFaculty->get_result();

if ($facultyResult && $facultyResult->num_rows > 0) {
    $facultyData = $facultyResult->fetch_assoc();
    $currentFaculty = $facultyData["department"];
}

$stmtFaculty->close();

$categoryFilter = isset($_GET["category"]) ? trim($_GET["category"]) : "";
$urgencyFilter = isset($_GET["urgency"]) ? trim($_GET["urgency"]) : "";


function notifyFacultyAnnouncementStudents($notificationObj, $facultyName, $programName, $title, $postedByName) {
    if (empty($facultyName) || empty($programName) || empty($title)) {
        return false;
    }

    $notificationTitle = "New Faculty Announcement";
    $notificationMessage = "A new faculty announcement has been posted by " . $postedByName . ": " . $title;
    $notificationLink = "student_dashboard.php?page=faculty_program_announcements";

    return $notificationObj->createNotificationForFacultyProgramStudents(
        $facultyName,
        $programName,
        "faculty_announcement",
        $notificationTitle,
        $notificationMessage,
        $notificationLink
    );
}



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_announcement"])) {
    $facultyName = $currentFaculty;
    $programName = trim($_POST["program_name"]);
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $announcementDate = trim($_POST["announcement_date"]);
    $announcementTime = trim($_POST["announcement_time"]);
    $status = trim($_POST["status"]);

    if (empty($facultyName) || empty($programName) || empty($title) || empty($description) || empty($announcementDate) || empty($announcementTime) || empty($status)) {
        $message = "Please fill in all faculty announcement fields.";
        $messageType = "error";
        $page = "create_announcement";
    } elseif (!array_key_exists($facultyName, $facultyPrograms)) {
        $message = "Your faculty account is not assigned to a valid faculty. Please contact the administrator.";
        $messageType = "error";
        $page = "create_announcement";
    } elseif ($programName !== "All Programs" && !in_array($programName, $facultyPrograms[$facultyName])) {
        $message = "Invalid program selected for your assigned faculty.";
        $messageType = "error";
        $page = "create_announcement";
    } elseif (!in_array($status, ["draft", "published"])) {
        $message = "Invalid status selected.";
        $messageType = "error";
        $page = "create_announcement";
    } else {
        $announcementType = "faculty";
        $category = "Faculty Announcement";
        $targetAudience = "Students and Faculty";
        $urgency = "normal";

        if ($announcementObj->createAnnouncement($title, $description, $category, $targetAudience, $urgency, $status, $userId, $announcementType, $announcementDate, $announcementTime, $facultyName, $programName)) {
            if ($status === "published") {
                notifyFacultyAnnouncementStudents(
                    $notificationObj,
                    $facultyName,
                    $programName,
                    $title,
                    getUserFullName()
                );
            }

            $message = "Faculty announcement created successfully.";

            if ($status === "published") {
                $message .= " Student notification was sent.";
            }

            $messageType = "success";
            $page = "my_announcements";
        } else {
            $message = "Failed to create faculty announcement. Please try again.";
            $messageType = "error";
            $page = "create_announcement";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["announcement_id"], $_POST["announcement_action"])) {
    $announcementId = intval($_POST["announcement_id"]);
    $announcementAction = $_POST["announcement_action"];

    if ($announcementAction === "publish") {
        if ($announcementObj->updateMyAnnouncementStatus($announcementId, $userId, "published")) {
            $stmtNotify = $conn->prepare("
                SELECT title, faculty_name, program_name
                FROM announcements
                WHERE id = ?
                AND posted_by = ?
                LIMIT 1
            ");
            $stmtNotify->bind_param("ii", $announcementId, $userId);
            $stmtNotify->execute();
            $notifyResult = $stmtNotify->get_result();

            if ($notifyResult && $notifyResult->num_rows > 0) {
                $notifyData = $notifyResult->fetch_assoc();

                notifyFacultyAnnouncementStudents(
                    $notificationObj,
                    $notifyData["faculty_name"],
                    $notifyData["program_name"],
                    $notifyData["title"],
                    getUserFullName()
                );
            }

            $stmtNotify->close();

            $message = "Announcement published successfully. Student notification was sent.";
            $messageType = "success";
        } else {
            $message = "Failed to publish announcement.";
            $messageType = "error";
        }

        $page = "my_announcements";
    } elseif ($announcementAction === "draft") {
        if ($announcementObj->updateMyAnnouncementStatus($announcementId, $userId, "draft")) {
            $message = "Announcement moved to draft successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to update announcement.";
            $messageType = "error";
        }

        $page = "my_announcements";
    } elseif ($announcementAction === "archive") {
        if ($announcementObj->updateMyAnnouncementStatus($announcementId, $userId, "archived")) {
            $message = "Announcement archived successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to archive announcement.";
            $messageType = "error";
        }

        $page = "my_announcements";
    } elseif ($announcementAction === "delete") {
        if ($announcementObj->deleteMyAnnouncement($announcementId, $userId)) {
            $message = "Announcement deleted successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to delete announcement.";
            $messageType = "error";
        }

        $page = "my_announcements";
    }
}

$filterCategories = $announcementObj->getActiveCategories();
$myAnnouncements = $announcementObj->getMyAnnouncements($userId);
$facultyEvents = $eventObj->getEventsForFaculty();

$facultyAnnouncements = $announcementObj->getFacultyAnnouncements($categoryFilter, $urgencyFilter);
$totalFacultyAnnouncements = $announcementObj->getTotalFacultyAnnouncements();
$totalFilteredFacultyAnnouncements = $announcementObj->getTotalFacultyAnnouncements($categoryFilter, $urgencyFilter);
$totalFacultyUrgentAnnouncements = $announcementObj->getTotalFacultyUrgentAnnouncements();

$totalMyAnnouncements = $announcementObj->getTotalMyAnnouncements($userId);
$publishedMyAnnouncements = $announcementObj->getTotalMyAnnouncementsByStatus($userId, "published");
$draftMyAnnouncements = $announcementObj->getTotalMyAnnouncementsByStatus($userId, "draft");
$archivedMyAnnouncements = $announcementObj->getTotalMyAnnouncementsByStatus($userId, "archived");
$totalFacultyEvents = $eventObj->getTotalEventsForFaculty();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty Dashboard | DOrSU Connect</title>
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
            radial-gradient(circle at top left, rgba(246, 196, 0, 0.16), transparent 30%),
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

    .user-profile {
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
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .profile-text p {
        font-size: 12px;
        opacity: 0.85;
        max-width: 180px;
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

    .summary-grid,
    .cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 25px;
    }

    .card,
    .summary-box,
    .form-box,
    .section,
    .event-card,
    .announcement-card {
        background: white;
        padding: 23px;
        border-radius: 22px;
        box-shadow: 0 10px 28px rgba(0, 43, 127, 0.08);
        border: 1px solid #e3eaf8;
        position: relative;
        overflow: hidden;
    }

    .card::before,
    .summary-box::before,
    .form-box::before,
    .section::before,
    .event-card::before,
    .announcement-card::before {
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
    .event-card:hover,
    .announcement-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 35px rgba(0, 43, 127, 0.14);
    }

    .card h3,
    .summary-box p {
        font-size: 15px;
        color: var(--dorsu-muted);
        font-weight: bold;
        margin-bottom: 12px;
    }

    .card h2,
    .summary-box h3 {
        font-size: 34px;
        color: var(--dorsu-blue-dark);
        margin-bottom: 6px;
    }

    .card p {
        color: var(--dorsu-muted);
        line-height: 1.6;
    }

    .form-box,
    .section {
        margin-bottom: 25px;
        overflow-x: auto;
    }

    .form-box h2,
    .section h2 {
        color: var(--dorsu-blue-dark);
        margin-bottom: 18px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    .filter-form {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 15px;
        align-items: end;
    }

    .form-group.full {
        grid-column: 1 / 3;
    }

    .form-group label {
        display: block;
        margin-bottom: 7px;
        color: var(--dorsu-blue-dark);
        font-weight: bold;
        font-size: 14px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 14px;
        border: 1px solid #cfd8ea;
        border-radius: 12px;
        outline: none;
        font-size: 14px;
        color: var(--dorsu-text);
        background: white;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: var(--dorsu-blue);
        box-shadow: 0 0 0 3px rgba(0, 63, 174, 0.13);
    }

    .form-group input[readonly] {
        background: #eef4ff;
        color: var(--dorsu-blue-dark);
        font-weight: bold;
        cursor: not-allowed;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 160px;
    }

    .form-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 20px;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 18px;
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

    .event-list,
    .announcement-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .event-card h2,
    .announcement-card h2 {
        color: var(--dorsu-blue-dark);
        margin-bottom: 8px;
    }

    .event-meta,
    .announcement-meta {
        color: var(--dorsu-muted);
        font-size: 13px;
        line-height: 1.6;
        margin-bottom: 15px;
    }

    .event-description,
    .announcement-description {
        color: #374151;
        line-height: 1.7;
        margin: 15px 0;
        white-space: pre-wrap;
    }

    .badge-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1120px;
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

    .description-cell {
        max-width: 300px;
        line-height: 1.5;
    }

    .badge {
        display: inline-block;
        padding: 7px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        text-transform: capitalize;
    }

    .draft {
        background: var(--dorsu-gold-light);
        color: #856300;
        border: 1px solid rgba(246, 196, 0, 0.55);
    }

    .published {
        background: #dfffe6;
        color: #187333;
    }

    .archived,
    .urgent {
        background: #ffe0e0;
        color: #9b0000;
    }

    .normal {
        background: #eef4ff;
        color: var(--dorsu-blue-dark);
        border: 1px solid rgba(0, 63, 174, 0.15);
    }

    .target,
    .faculty {
        background: #eef4ff;
        color: var(--dorsu-blue-dark);
        border: 1px solid rgba(0, 63, 174, 0.15);
    }

    .category,
    .program {
        background: var(--dorsu-gold-light);
        color: #856300;
        border: 1px solid rgba(246, 196, 0, 0.55);
    }

    .office {
        background: #ecfff1;
        color: #187333;
        border: 1px solid #a8e8b4;
    }

    .btn {
        border: none;
        padding: 11px 15px;
        border-radius: 11px;
        cursor: pointer;
        font-weight: bold;
        color: white;
        transition: 0.25s;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
    }

    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 18px rgba(0, 43, 127, 0.18);
    }

    .primary {
        background: linear-gradient(135deg, var(--dorsu-blue-dark), var(--dorsu-blue));
    }

    .publish {
        background: linear-gradient(135deg, #16803a, #1fa34a);
    }

    .draft-btn {
        background: linear-gradient(135deg, var(--dorsu-gold-dark), var(--dorsu-gold));
        color: #3b2b00;
    }

    .archive,
    .delete {
        background: linear-gradient(135deg, #9b0000, #c40000);
    }

    .action-form {
        display: inline-block;
        margin-right: 5px;
        margin-bottom: 5px;
    }

    .empty {
        color: var(--dorsu-muted);
        background: #f8fbff;
        padding: 18px;
        border-radius: 14px;
        border: 1px dashed #c7d5ef;
    }

    @media (max-width: 1100px) {
        .summary-grid,
        .cards {
            grid-template-columns: repeat(2, 1fr);
        }

        .filter-form {
            grid-template-columns: 1fr 1fr;
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
            min-height: 420px;
        }

        .main {
            margin-left: 0;
            width: 100%;
            padding: 95px 18px 18px;
        }

        .summary-grid,
        .cards,
        .form-grid,
        .filter-form {
            grid-template-columns: 1fr;
        }

        .form-group.full {
            grid-column: 1 / 2;
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

        .user-profile {
            min-width: 100%;
        }

        .nav a {
            min-width: 100%;
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

        <div class="user-profile">
            <div class="avatar">
                <?php echo strtoupper(substr(getUserFullName(), 0, 1)); ?>
            </div>

            <div class="profile-text">
                <h3><?php echo htmlspecialchars(getUserFullName()); ?></h3>
                <p><?php echo htmlspecialchars(getUserEmail()); ?></p>
            </div>
        </div>

        <nav class="nav">
                        <a href="faculty_dashboard.php?page=home" class="<?php echo $page === 'home' ? 'active' : ''; ?>">Home</a>
                        <a href="faculty_dashboard.php?page=profile" class="<?php echo $page === 'profile' ? 'active' : ''; ?>">Profile</a>
                        <a href="messages.php">Messages</a>
                        <a href="faculty_dashboard.php?page=create_announcement" class="<?php echo $page === 'create_announcement' ? 'active' : ''; ?>">Create Faculty Announcement</a>
                        <a href="faculty_dashboard.php?page=my_announcements" class="<?php echo $page === 'my_announcements' ? 'active' : ''; ?>">My Announcements</a>
                        <a href="faculty_dashboard.php?page=university_announcements" class="<?php echo $page === 'university_announcements' ? 'active' : ''; ?>">University Announcements</a>
                        <a href="faculty_dashboard.php?page=university_events" class="<?php echo $page === 'university_events' ? 'active' : ''; ?>">University Events</a>
                        <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </aside>

    <main class="main">
    <?php include __DIR__ . "/notification_bell.php"; ?>


        <?php if ($page === "home"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Faculty Member Dashboard</span>
                    <h1>Welcome, <?php echo htmlspecialchars(getUserFullName()); ?>!</h1>
                    <p>
                        Share meaningful academic reminders, faculty updates, program advisories, and important student-focused notices.
    This dashboard helps you stay connected with university announcements, publish updates under your assigned faculty, and keep track of official university events.
                    </p>
                </div>
            </section>

            <?php include __DIR__ . "/weather_widget.php"; ?>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalFacultyAnnouncements; ?></h3>
                    <p>University Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalFacultyUrgentAnnouncements; ?></h3>
                    <p>Urgent Office Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalMyAnnouncements; ?></h3>
                    <p>My Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalFacultyEvents; ?></h3>
                    <p>University Events</p>
                </div>
            </section>

            <section class="cards">
                <div class="card">
                    <h3>University Announcements</h3>
                    <p>View announcements from University Office Informants intended for faculty members.</p>
                </div>

                <div class="card">
                    <h3>Assigned Faculty</h3>
                    <p><?php echo !empty($currentFaculty) ? htmlspecialchars($currentFaculty) : "No assigned faculty. Please contact the administrator."; ?></p>
                </div>

                <div class="card">
                    <h3>Create Faculty Announcement</h3>
                    <p>Create announcements only under your assigned faculty.</p>
                </div>

                <div class="card">
                    <h3>University Events</h3>
                    <p>View published university events created by authorized university offices.</p>
                </div>
            </section>

        <?php elseif ($page === "profile"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Profile</span>
                    <h1>My Profile</h1>
                    <p>View your current account information.</p>
                </div>
            </section>

            <section class="section">
                <h2>Account Information</h2>

                <table>
                    <tbody>
                        <tr>
                            <th>Full Name</th>
                            <td><?php echo htmlspecialchars(getUserFullName()); ?></td>
                        </tr>

                        <tr>
                            <th>Email</th>
                            <td><?php echo htmlspecialchars(getUserEmail()); ?></td>
                        </tr>

                        <tr>
                            <th>Role</th>
                            <td>Faculty Member</td>
                        </tr>

                        <tr>
                            <th>Assigned Faculty</th>
                            <td><?php echo !empty($currentFaculty) ? htmlspecialchars($currentFaculty) : "No assigned faculty"; ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

        <?php elseif ($page === "university_announcements"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">University Announcements</span>
                    <h1>University Office Announcements</h1>
                    <p>
                        View official announcements from University Office Informants intended for faculty members.
                    </p>
                </div>
            </section>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalFacultyAnnouncements; ?></h3>
                    <p>Total Available</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalFacultyUrgentAnnouncements; ?></h3>
                    <p>Urgent</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalFilteredFacultyAnnouncements; ?></h3>
                    <p>Filtered Results</p>
                </div>

                <div class="summary-box">
                    <h3>Office</h3>
                    <p>Posted By</p>
                </div>
            </section>

            <section class="form-box">
                <h2>Filter Announcements</h2>

                <form method="GET" action="faculty_dashboard.php">
                    <input type="hidden" name="page" value="university_announcements">

                    <div class="filter-form">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="">All Categories</option>
                                <?php if ($filterCategories && $filterCategories->num_rows > 0): ?>
                                    <?php while ($category = $filterCategories->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($category["category_name"]); ?>" <?php echo $categoryFilter === $category["category_name"] ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($category["category_name"]); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Urgency</label>
                            <select name="urgency">
                                <option value="">All Urgency</option>
                                <option value="normal" <?php echo $urgencyFilter === "normal" ? "selected" : ""; ?>>Normal</option>
                                <option value="urgent" <?php echo $urgencyFilter === "urgent" ? "selected" : ""; ?>>Urgent</option>
                            </select>
                        </div>

                        <button type="submit" class="btn primary">Apply Filter</button>
                    </div>
                </form>
            </section>

            <section class="announcement-list">
                <?php if ($facultyAnnouncements && $facultyAnnouncements->num_rows > 0): ?>
                    <?php while ($announcement = $facultyAnnouncements->fetch_assoc()): ?>
                        <article class="announcement-card">
                            <h2><?php echo htmlspecialchars($announcement["title"]); ?></h2>

                            <div class="announcement-meta">
                                Posted by: <?php echo htmlspecialchars($announcement["posted_by_name"]); ?><br>
                                Office:
                                <?php echo !empty($announcement["posted_by_office"]) ? htmlspecialchars($announcement["posted_by_office"]) : "University Office"; ?><br>
                                Date Posted: <?php echo htmlspecialchars($announcement["created_at"]); ?><br>
                                Announcement Date:
                                <?php echo !empty($announcement["announcement_date"]) ? htmlspecialchars($announcement["announcement_date"]) : "No date"; ?>
                                <?php echo !empty($announcement["announcement_time"]) ? " at " . date("h:i A", strtotime($announcement["announcement_time"])) : ""; ?>
                            </div>

                            <div class="badge-row">
                                <span class="badge category"><?php echo htmlspecialchars($announcement["category"]); ?></span>
                                <span class="badge target"><?php echo htmlspecialchars($announcement["target_audience"]); ?></span>
                                <span class="badge <?php echo htmlspecialchars($announcement["urgency"]); ?>">
                                    <?php echo htmlspecialchars($announcement["urgency"]); ?>
                                </span>
                                <span class="badge office">
                                    <?php echo !empty($announcement["posted_by_office"]) ? htmlspecialchars($announcement["posted_by_office"]) : "University Office"; ?>
                                </span>
                            </div>

                            <div class="announcement-description">
                                <?php echo htmlspecialchars($announcement["description"]); ?>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty">No university office announcements available for faculty members yet.</div>
                <?php endif; ?>
            </section>

        <?php elseif ($page === "create_announcement"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Create Faculty Announcement</span>
                    <h1>Create Faculty Announcement</h1>
                    <p>
                        Your faculty is locked based on the faculty assigned by the admin.
                        You may only select programs under your assigned faculty.
                    </p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="form-box">
                <h2>Faculty Announcement Form</h2>

                <?php if (empty($currentFaculty) || !array_key_exists($currentFaculty, $facultyPrograms)): ?>
                    <div class="message error">
                        Your account is not assigned to a valid faculty. Please contact the administrator.
                    </div>
                <?php else: ?>
                    <form method="POST" action="faculty_dashboard.php?page=create_announcement">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Assigned Faculty</label>
                                <input type="text" value="<?php echo htmlspecialchars($currentFaculty); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Program</label>
                                <select name="program_name" required>
                                    <option value="">Select program</option>
                                    <option value="All Programs">All Programs</option>
                                    <?php foreach ($facultyPrograms[$currentFaculty] as $program): ?>
                                        <option value="<?php echo htmlspecialchars($program); ?>">
                                            <?php echo htmlspecialchars($program); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group full">
                                <label>Announcement Title</label>
                                <input type="text" name="title" placeholder="Enter announcement title" required>
                            </div>

                            <div class="form-group full">
                                <label>Description</label>
                                <textarea name="description" placeholder="Write the announcement details here..." required></textarea>
                            </div>

                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="announcement_date" required>
                            </div>

                            <div class="form-group">
                                <label>Time</label>
                                <input type="time" name="announcement_time" required>
                            </div>

                            <div class="form-group full">
                                <label>Status</label>
                                <select name="status" required>
                                    <option value="">Select status</option>
                                    <option value="published">Published</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="create_announcement" class="btn primary">
                                Save Faculty Announcement
                            </button>

                            <a href="faculty_dashboard.php?page=my_announcements" class="btn draft-btn">
                                View My Announcements
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

        <?php elseif ($page === "my_announcements"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">My Announcements</span>
                    <h1>Manage My Faculty Announcements</h1>
                    <p>View all faculty announcements you created. You can publish, draft, archive, or delete your own posts.</p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalMyAnnouncements; ?></h3>
                    <p>Total Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $publishedMyAnnouncements; ?></h3>
                    <p>Published</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $draftMyAnnouncements; ?></h3>
                    <p>Draft</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $archivedMyAnnouncements; ?></h3>
                    <p>Archived</p>
                </div>
            </section>

            <section class="section">
                <div class="section-header">
                    <h2>Announcement List</h2>
                    <span class="section-label"><?php echo $totalMyAnnouncements; ?> total</span>
                </div>

                <?php if ($myAnnouncements && $myAnnouncements->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Faculty</th>
                                <th>Program</th>
                                <th>Description</th>
                                <th>Date/Time</th>
                                <th>Category</th>
                                <th>Target</th>
                                <th>Status</th>
                                <th>Date Posted</th>
                                <th>Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($announcement = $myAnnouncements->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($announcement["title"]); ?></td>

                                    <td>
                                        <?php echo !empty($announcement["faculty_name"]) ? htmlspecialchars($announcement["faculty_name"]) : "No faculty"; ?>
                                    </td>

                                    <td>
                                        <span class="badge program">
                                            <?php echo !empty($announcement["program_name"]) ? htmlspecialchars($announcement["program_name"]) : "No program"; ?>
                                        </span>
                                    </td>

                                    <td class="description-cell">
                                        <?php echo htmlspecialchars(substr($announcement["description"], 0, 130)); ?>
                                        <?php echo strlen($announcement["description"]) > 130 ? "..." : ""; ?>
                                    </td>

                                    <td>
                                        <?php echo !empty($announcement["announcement_date"]) ? htmlspecialchars($announcement["announcement_date"]) : "No date"; ?><br>
                                        <?php echo !empty($announcement["announcement_time"]) ? date("h:i A", strtotime($announcement["announcement_time"])) : "No time"; ?>
                                    </td>

                                    <td><?php echo htmlspecialchars($announcement["category"]); ?></td>

                                    <td>
                                        <span class="badge target">
                                            <?php echo htmlspecialchars($announcement["target_audience"]); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge <?php echo htmlspecialchars($announcement["status"]); ?>">
                                            <?php echo htmlspecialchars($announcement["status"]); ?>
                                        </span>
                                    </td>

                                    <td><?php echo htmlspecialchars($announcement["created_at"]); ?></td>

                                    <td>
                                        <?php echo !empty($announcement["updated_at"]) ? htmlspecialchars($announcement["updated_at"]) : "Not updated"; ?>
                                    </td>

                                    <td>
                                        <form method="POST" class="action-form" action="faculty_dashboard.php?page=my_announcements">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="publish">
                                            <button type="submit" class="btn publish">Publish</button>
                                        </form>

                                        <form method="POST" class="action-form" action="faculty_dashboard.php?page=my_announcements">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="draft">
                                            <button type="submit" class="btn draft-btn">Draft</button>
                                        </form>

                                        <form method="POST" class="action-form" action="faculty_dashboard.php?page=my_announcements">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="archive">
                                            <button type="submit" class="btn archive">Archive</button>
                                        </form>

                                        <form method="POST" class="action-form" action="faculty_dashboard.php?page=my_announcements" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="delete">
                                            <button type="submit" class="btn delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">
                        You have not created any faculty announcements yet.
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($page === "university_events"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">University Events</span>
                    <h1>University Events</h1>
                    <p>View published university events available for faculty members.</p>
                </div>
            </section>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalFacultyEvents; ?></h3>
                    <p>Available Events</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $publishedMyAnnouncements; ?></h3>
                    <p>My Published Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalMyAnnouncements; ?></h3>
                    <p>My Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $draftMyAnnouncements; ?></h3>
                    <p>Draft Posts</p>
                </div>
            </section>

            <section class="event-list">
                <?php if ($facultyEvents && $facultyEvents->num_rows > 0): ?>
                    <?php while ($event = $facultyEvents->fetch_assoc()): ?>
                        <article class="event-card">
                            <h2><?php echo htmlspecialchars($event["event_title"]); ?></h2>

                            <div class="event-meta">
                                Date: <?php echo htmlspecialchars($event["event_date"]); ?><br>
                                Time:
                                <?php echo date("h:i A", strtotime($event["start_time"])); ?>
                                -
                                <?php echo !empty($event["end_time"]) ? date("h:i A", strtotime($event["end_time"])) : "No end time"; ?><br>
                                Venue: <?php echo htmlspecialchars($event["venue"]); ?><br>
                                Organizer: <?php echo htmlspecialchars($event["organizer"]); ?><br>
                                Posted by: <?php echo htmlspecialchars($event["created_by_name"]); ?>
                            </div>

                            <div class="badge-row">
                                <span class="badge category"><?php echo htmlspecialchars($event["event_category"]); ?></span>
                                <span class="badge target"><?php echo htmlspecialchars($event["target_audience"]); ?></span>
                                <span class="badge published">published</span>
                            </div>

                            <div class="event-description">
                                <?php echo htmlspecialchars($event["event_description"]); ?>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty">No university events available yet.</div>
                <?php endif; ?>
            </section>

        <?php endif; ?>

    </main>
</div>

</body>
</html>