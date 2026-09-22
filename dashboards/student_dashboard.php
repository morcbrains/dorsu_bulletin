<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_once "../classes/Dashboard.php";
require_once "../classes/Announcement.php";
require_once "../classes/Event.php";
require_once "../classes/Notification.php";

requireRole("student");

$dashboard = new Dashboard();
$announcementObj = new Announcement($conn);
$eventObj = new Event($conn);
$notificationObj = new Notification($conn);

$message = "";
$messageType = "";

$page = isset($_GET["page"]) ? $_GET["page"] : "home";

if (!in_array($page, ["home", "faculty_program_announcements", "saved_announcements", "events", "profile"])) {
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

$studentFaculty = "";
$studentProgram = "";
$studentYearLevel = "";
$studentIdNumber = "";

$stmtStudent = $conn->prepare("
    SELECT 
        student_id,
        department,
        program,
        year_level
    FROM users
    WHERE id = ?
    AND role = 'student'
    LIMIT 1
");

$stmtStudent->bind_param("i", $userId);
$stmtStudent->execute();

$studentResult = $stmtStudent->get_result();

if ($studentResult && $studentResult->num_rows > 0) {
    $studentData = $studentResult->fetch_assoc();

    $studentIdNumber = $studentData["student_id"];
    $studentFaculty = $studentData["department"];
    $studentProgram = $studentData["program"];
    $studentYearLevel = $studentData["year_level"];
}

$stmtStudent->close();

$categoryFilter = isset($_GET["category"]) ? trim($_GET["category"]) : "";
$urgencyFilter = isset($_GET["urgency"]) ? trim($_GET["urgency"]) : "";
$selectedAnnouncementId = isset($_GET["view"]) ? intval($_GET["view"]) : 0;

$facultyFilter = $studentFaculty;
$programFilter = $studentProgram;

if (empty($studentFaculty) || empty($studentProgram)) {
    $facultyFilter = "";
    $programFilter = "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["announcement_id"], $_POST["student_action"])) {
    $announcementId = intval($_POST["announcement_id"]);
    $studentAction = $_POST["student_action"];

    if ($studentAction === "read") {
        if ($announcementObj->markAsRead($announcementId, $userId)) {
            $message = "Announcement marked as read.";
            $messageType = "success";
        } else {
            $message = "Failed to mark announcement as read.";
            $messageType = "error";
        }

        $page = "faculty_program_announcements";
    } elseif ($studentAction === "save") {
        if ($announcementObj->saveAnnouncement($announcementId, $userId)) {
            $message = "Announcement saved successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to save announcement.";
            $messageType = "error";
        }

        $page = "faculty_program_announcements";
    } elseif ($studentAction === "unsave") {
        if ($announcementObj->unsaveAnnouncement($announcementId, $userId)) {
            $message = "Announcement removed from saved list.";
            $messageType = "success";
        } else {
            $message = "Failed to remove saved announcement.";
            $messageType = "error";
        }

        $page = "saved_announcements";
    }
}

$filterCategories = $announcementObj->getActiveCategories();

$studentAnnouncements = $announcementObj->getStudentAnnouncements(
    $categoryFilter,
    $urgencyFilter,
    $facultyFilter,
    $programFilter
);

$savedAnnouncements = $announcementObj->getSavedAnnouncements($userId);
$studentEvents = $eventObj->getEventsForStudent();

$totalStudentAnnouncements = $announcementObj->getTotalStudentAnnouncements(
    "",
    "",
    $facultyFilter,
    $programFilter
);

$totalStudentUrgentAnnouncements = $announcementObj->getTotalStudentUrgentAnnouncements(
    $facultyFilter,
    $programFilter
);

$totalSavedAnnouncements = $announcementObj->getTotalSavedAnnouncements($userId);
$totalReadAnnouncements = $announcementObj->getTotalReadAnnouncements($userId);
$totalStudentEvents = $eventObj->getTotalEventsForStudent();

$unreadAnnouncements = $totalStudentAnnouncements - $totalReadAnnouncements;

if ($unreadAnnouncements < 0) {
    $unreadAnnouncements = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard | DOrSU Connect</title>
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
        width: 320px;
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
        min-width: 280px;
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
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .profile-text p {
        font-size: 12px;
        opacity: 0.85;
        max-width: 200px;
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
        min-width: 280px;
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
        max-width: 850px;
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
    .section,
    .filter-box,
    .announcement-card,
    .event-card {
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
    .section::before,
    .filter-box::before,
    .announcement-card::before,
    .event-card::before {
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
    .announcement-card:hover,
    .event-card:hover {
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

    .section,
    .filter-box {
        margin-bottom: 25px;
    }

    .section h2,
    .filter-box h2 {
        color: var(--dorsu-blue-dark);
        margin-bottom: 18px;
    }

    .filter-form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        align-items: end;
    }

    .form-group label {
        display: block;
        margin-bottom: 7px;
        color: var(--dorsu-blue-dark);
        font-weight: bold;
        font-size: 14px;
    }

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

    .form-group select:focus {
        border-color: var(--dorsu-blue);
        box-shadow: 0 0 0 3px rgba(0, 63, 174, 0.13);
    }

    .faculty-filter-box {
        display: none;
    }

    .announcement-title-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .announcement-title-card {
        background: white;
        padding: 20px 22px;
        border-radius: 18px;
        box-shadow: 0 10px 28px rgba(0, 43, 127, 0.08);
        border: 1px solid #e3eaf8;
        border-left: 7px solid var(--dorsu-gold);
        transition: 0.25s;
    }

    .announcement-title-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 35px rgba(0, 43, 127, 0.14);
    }

    .announcement-title-card h2 {
        color: var(--dorsu-blue-dark);
        margin-bottom: 8px;
        font-size: 21px;
    }

    .announcement-title-meta {
        color: var(--dorsu-muted);
        font-size: 13px;
        line-height: 1.6;
        margin-bottom: 15px;
    }

    .back-link-box {
        margin-bottom: 18px;
    }

    .announcement-list,
    .event-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .announcement-top,
    .event-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 15px;
        margin-bottom: 15px;
    }

    .announcement-card h2,
    .event-card h2 {
        color: var(--dorsu-blue-dark);
        margin-bottom: 8px;
    }

    .announcement-meta,
    .event-meta {
        color: var(--dorsu-muted);
        font-size: 13px;
        line-height: 1.6;
    }

    .announcement-description,
    .event-description {
        color: #374151;
        line-height: 1.7;
        margin: 15px 0;
        white-space: pre-wrap;
    }

    .announcement-message-box {
        margin-top: 18px;
        background: #f8fbff;
        border: 1px solid #dce7fa;
        border-radius: 18px;
        overflow: hidden;
    }

    .message-title {
        background: linear-gradient(135deg, var(--dorsu-blue-dark), var(--dorsu-blue));
        color: white;
        padding: 12px 16px;
        font-weight: bold;
        font-size: 14px;
    }

    .message-content {
        padding: 18px;
        color: var(--dorsu-text);
        line-height: 1.8;
        font-size: 15px;
        white-space: pre-line;
        text-align: justify;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin: 15px 0;
    }

    .detail-item {
        background: #f8fbff;
        padding: 13px;
        border-radius: 14px;
        border: 1px solid #dce7fa;
    }

    .detail-item strong {
        display: block;
        color: var(--dorsu-blue-dark);
        font-size: 12px;
        margin-bottom: 5px;
        text-transform: uppercase;
    }

    .detail-item span {
        color: var(--dorsu-text);
        font-size: 14px;
        line-height: 1.5;
    }

    .badge-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }

    .badge {
        display: inline-block;
        padding: 7px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        text-transform: capitalize;
    }

    .published,
    .read,
    .saved {
        background: #dfffe6;
        color: #187333;
    }

    .urgent {
        background: #ffe0e0;
        color: #9b0000;
    }

    .normal,
    .unread {
        background: #eef4ff;
        color: var(--dorsu-blue-dark);
        border: 1px solid rgba(0, 63, 174, 0.15);
    }

    .category,
    .program-badge {
        background: var(--dorsu-gold-light);
        color: #856300;
        border: 1px solid rgba(246, 196, 0, 0.55);
    }

    .target,
    .faculty-badge {
        background: #eef4ff;
        color: var(--dorsu-blue-dark);
        border: 1px solid rgba(0, 63, 174, 0.15);
    }

    .announcement-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 15px;
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

    .read-btn {
        background: linear-gradient(135deg, #16803a, #1fa34a);
    }

    .save-btn {
        background: linear-gradient(135deg, var(--dorsu-gold-dark), var(--dorsu-gold));
        color: #3b2b00;
    }

    .remove-btn {
        background: linear-gradient(135deg, #9b0000, #c40000);
    }

    .empty {
        color: var(--dorsu-muted);
        background: #f8fbff;
        padding: 18px;
        border-radius: 14px;
        border: 1px dashed #c7d5ef;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 700px;
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

    @media (max-width: 1100px) {
        .summary-grid,
        .cards,
        .detail-grid {
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
            min-height: 360px;
        }

        .main {
            margin-left: 0;
            width: 100%;
            padding: 95px 18px 18px;
        }

        .summary-grid,
        .cards,
        .filter-form,
        .detail-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-header {
            padding: 25px;
        }

        .dashboard-header h1 {
            font-size: 28px;
        }

        .announcement-top,
        .event-top {
            flex-direction: column;
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
            <a href="student_dashboard.php?page=home" class="<?php echo $page === 'home' ? 'active' : ''; ?>">Home</a>
            <a href="student_dashboard.php?page=profile" class="<?php echo $page === 'profile' ? 'active' : ''; ?>">Profile</a>
            <a href="messages.php">Messages</a>
            <a href="student_dashboard.php?page=faculty_program_announcements" class="<?php echo $page === 'faculty_program_announcements' ? 'active' : ''; ?>">Faculty/Program Announcements</a>
            <a href="student_dashboard.php?page=events" class="<?php echo $page === 'events' ? 'active' : ''; ?>">Events</a>
            <a href="student_dashboard.php?page=saved_announcements" class="<?php echo $page === 'saved_announcements' ? 'active' : ''; ?>">Saved Announcements</a>
            <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </aside>

    <main class="main">
    <?php include __DIR__ . "/notification_bell.php"; ?>

        <?php if ($page === "home"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Student Dashboard</span>
                    <h1>Welcome, <?php echo htmlspecialchars(getUserFullName()); ?>!</h1>
                    <p>
                        <?php echo htmlspecialchars($dashboard->getWelcomeMessage("student")); ?>
                        This dashboard helps you stay updated with faculty/program announcements,
                        university events, urgent notices, and important reminders.
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
                    <h3><?php echo $totalStudentAnnouncements; ?></h3>
                    <p>Faculty/Program Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalStudentEvents; ?></h3>
                    <p>University Events</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalSavedAnnouncements; ?></h3>
                    <p>Saved Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $unreadAnnouncements; ?></h3>
                    <p>Unread Announcements</p>
                </div>
            </section>

            <section class="cards">
                <div class="card">
                    <h3>Faculty/Program Announcements</h3>
                    <p>View announcements created by faculty members for a selected faculty and program.</p>
                </div>

                <div class="card">
                    <h3>University Events</h3>
                    <p>View published university events such as programs, celebrations, seminars, and activities.</p>
                </div>

                <div class="card">
                    <h3>Save Important Posts</h3>
                    <p>Save announcements so you can easily check them again later.</p>
                </div>

                <div class="card">
                    <h3>Read Tracking</h3>
                    <p>Mark announcements as read to help you track what you already checked.</p>
                </div>
            </section>

        <?php elseif ($page === "profile"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Profile</span>
                    <h1>My Profile</h1>
                    <p>View your account information.</p>
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
                            <td>Student</td>
                        </tr>
                    </tbody>
                </table>
            </section>

        <?php elseif ($page === "faculty_program_announcements"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Faculty/Program Announcements</span>
                    <h1>Faculty/Program Announcements</h1>
                    <p>
                        View announcements created by faculty members. Announcement titles are shown first.
                        Click one announcement to view its full details.
                    </p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="filter-box">
                <h2>Filter Faculty/Program Announcements</h2>

                <form method="GET" action="student_dashboard.php" class="filter-form">
                    <input type="hidden" name="page" value="faculty_program_announcements">

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="category_filter">
                            <option value="">All Categories</option>

                            <option value="Faculty Announcement" <?php echo $categoryFilter === "Faculty Announcement" ? "selected" : ""; ?>>
                                Faculty Announcement
                            </option>

                            <?php if ($filterCategories && $filterCategories->num_rows > 0): ?>
                                <?php while ($category = $filterCategories->fetch_assoc()): ?>
                                    <?php if ($category["category_name"] !== "Faculty Announcement"): ?>
                                        <option value="<?php echo htmlspecialchars($category["category_name"]); ?>" <?php echo $categoryFilter === $category["category_name"] ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($category["category_name"]); ?>
                                        </option>
                                    <?php endif; ?>
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

                    <div class="form-group faculty-filter-box" id="faculty_filter_box">
                        <label>Faculty</label>
                        <select name="faculty_name" id="faculty_filter">
                            <option value="">Select faculty</option>

                            <?php foreach ($facultyPrograms as $faculty => $programs): ?>
                                <option value="<?php echo htmlspecialchars($faculty); ?>" <?php echo $facultyFilter === $faculty ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($faculty); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group faculty-filter-box" id="program_filter_box">
                        <label>Program</label>
                        <select name="program_name" id="program_filter" data-selected="<?php echo htmlspecialchars($programFilter); ?>">
                            <option value="">Select faculty first</option>
                        </select>
                    </div>

                    <button type="submit" class="btn primary">Apply Filter</button>
                    <a href="student_dashboard.php?page=faculty_program_announcements" class="btn remove-btn">Clear</a>
                </form>
            </section>

            <?php
                $baseQuery = [
                    "page" => "faculty_program_announcements",
                    "category" => $categoryFilter,
                    "urgency" => $urgencyFilter,
                    "faculty_name" => $facultyFilter,
                    "program_name" => $programFilter
                ];

                $selectedAnnouncement = null;
                $announcementRows = [];

                if ($studentAnnouncements && $studentAnnouncements->num_rows > 0) {
                    while ($row = $studentAnnouncements->fetch_assoc()) {
                        $announcementRows[] = $row;

                        if ($selectedAnnouncementId > 0 && intval($row["id"]) === $selectedAnnouncementId) {
                            $selectedAnnouncement = $row;
                        }
                    }
                }
            ?>

            <?php if ($selectedAnnouncementId > 0): ?>

                <div class="back-link-box">
                    <a href="student_dashboard.php?<?php echo http_build_query($baseQuery); ?>" class="btn remove-btn">
                        Back to Announcement Titles
                    </a>
                </div>

                <?php if ($selectedAnnouncement): ?>
                    <?php
                        $announcement = $selectedAnnouncement;
                        $isRead = $announcementObj->isRead($announcement["id"], $userId);
                        $isSaved = $announcementObj->isSaved($announcement["id"], $userId);
                    ?>

                    <section class="announcement-list">
                        <article class="announcement-card">
                            <div class="announcement-top">
                                <div>
                                    <h2><?php echo htmlspecialchars($announcement["title"]); ?></h2>
                                    <div class="announcement-meta">
                                        Posted by <?php echo htmlspecialchars($announcement["posted_by_name"]); ?>
                                        as <?php echo htmlspecialchars($announcement["posted_by_role"]); ?><br>
                                        Date Posted: <?php echo htmlspecialchars($announcement["created_at"]); ?>
                                    </div>
                                </div>

                                <div class="badge-row">
                                    <span class="badge <?php echo htmlspecialchars($announcement["urgency"]); ?>">
                                        <?php echo htmlspecialchars($announcement["urgency"]); ?>
                                    </span>

                                    <span class="badge <?php echo $isRead ? "read" : "unread"; ?>">
                                        <?php echo $isRead ? "read" : "unread"; ?>
                                    </span>

                                    <?php if ($isSaved): ?>
                                        <span class="badge saved">saved</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="badge-row">
                                <span class="badge category"><?php echo htmlspecialchars($announcement["category"]); ?></span>

                                <?php if (!empty($announcement["faculty_name"])): ?>
                                    <span class="badge faculty-badge">
                                        <?php echo htmlspecialchars($announcement["faculty_name"]); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($announcement["program_name"])): ?>
                                    <span class="badge program-badge">
                                        <?php echo htmlspecialchars($announcement["program_name"]); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="detail-grid">
                                <div class="detail-item">
                                    <strong>Faculty</strong>
                                    <span>
                                        <?php echo !empty($announcement["faculty_name"]) ? htmlspecialchars($announcement["faculty_name"]) : "Not specified"; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <strong>Program</strong>
                                    <span>
                                        <?php echo !empty($announcement["program_name"]) ? htmlspecialchars($announcement["program_name"]) : "Not specified"; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <strong>Announcement Date</strong>
                                    <span>
                                        <?php echo !empty($announcement["announcement_date"]) ? htmlspecialchars($announcement["announcement_date"]) : "No date"; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <strong>Announcement Time</strong>
                                    <span>
                                        <?php echo !empty($announcement["announcement_time"]) ? date("h:i A", strtotime($announcement["announcement_time"])) : "No time"; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="announcement-message-box">
                                <div class="message-title">
                                    Announcement Message
                                </div>

                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($announcement["description"])); ?>
                                </div>
                            </div>

                            <div class="announcement-actions">
                                <?php if (!$isRead): ?>
                                    <form method="POST" action="student_dashboard.php?page=faculty_program_announcements&view=<?php echo $announcement["id"]; ?>&category=<?php echo urlencode($categoryFilter); ?>&urgency=<?php echo urlencode($urgencyFilter); ?>&faculty_name=<?php echo urlencode($facultyFilter); ?>&program_name=<?php echo urlencode($programFilter); ?>">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                        <input type="hidden" name="student_action" value="read">
                                        <button type="submit" class="btn read-btn">Mark as Read</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!$isSaved): ?>
                                    <form method="POST" action="student_dashboard.php?page=faculty_program_announcements&view=<?php echo $announcement["id"]; ?>&category=<?php echo urlencode($categoryFilter); ?>&urgency=<?php echo urlencode($urgencyFilter); ?>&faculty_name=<?php echo urlencode($facultyFilter); ?>&program_name=<?php echo urlencode($programFilter); ?>">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                        <input type="hidden" name="student_action" value="save">
                                        <button type="submit" class="btn save-btn">Save Announcement</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    </section>

                <?php else: ?>
                    <div class="empty">Announcement not found.</div>
                <?php endif; ?>

            <?php else: ?>

                <section class="announcement-title-list">
                    <?php if (!empty($announcementRows)): ?>
                        <?php foreach ($announcementRows as $announcement): ?>
                            <?php
                                $isRead = $announcementObj->isRead($announcement["id"], $userId);
                                $isSaved = $announcementObj->isSaved($announcement["id"], $userId);

                                $viewQuery = $baseQuery;
                                $viewQuery["view"] = $announcement["id"];
                            ?>

                            <article class="announcement-title-card">
                                <h2><?php echo htmlspecialchars($announcement["title"]); ?></h2>

                                <div class="announcement-title-meta">
                                    Posted by <?php echo htmlspecialchars($announcement["posted_by_name"]); ?>
                                    as <?php echo htmlspecialchars($announcement["posted_by_role"]); ?><br>

                                    Faculty:
                                    <?php echo !empty($announcement["faculty_name"]) ? htmlspecialchars($announcement["faculty_name"]) : "Not specified"; ?><br>

                                    Program:
                                    <?php echo !empty($announcement["program_name"]) ? htmlspecialchars($announcement["program_name"]) : "Not specified"; ?><br>

                                    Date Posted: <?php echo htmlspecialchars($announcement["created_at"]); ?>
                                </div>

                                <div class="badge-row">
                                    <span class="badge <?php echo htmlspecialchars($announcement["urgency"]); ?>">
                                        <?php echo htmlspecialchars($announcement["urgency"]); ?>
                                    </span>

                                    <span class="badge <?php echo $isRead ? "read" : "unread"; ?>">
                                        <?php echo $isRead ? "read" : "unread"; ?>
                                    </span>

                                    <?php if ($isSaved): ?>
                                        <span class="badge saved">saved</span>
                                    <?php endif; ?>
                                </div>

                                <a href="student_dashboard.php?<?php echo http_build_query($viewQuery); ?>" class="btn primary">
                                    View Details
                                </a>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">No faculty/program announcements found.</div>
                    <?php endif; ?>
                </section>

            <?php endif; ?>

        <?php elseif ($page === "events"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Events</span>
                    <h1>University Events</h1>
                    <p>View upcoming published university events available for students.</p>
                </div>
            </section>

            <section class="event-list">
                <?php if ($studentEvents && $studentEvents->num_rows > 0): ?>
                    <?php while ($event = $studentEvents->fetch_assoc()): ?>
                        <article class="event-card">
                            <div class="event-top">
                                <div>
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
                                </div>

                                <div class="badge-row">
                                    <span class="badge published">published</span>
                                </div>
                            </div>

                            <div class="badge-row">
                                <span class="badge category"><?php echo htmlspecialchars($event["event_category"]); ?></span>
                                <span class="badge target"><?php echo htmlspecialchars($event["target_audience"]); ?></span>
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

        <?php elseif ($page === "saved_announcements"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Saved Announcements</span>
                    <h1>My Saved Announcements</h1>
                    <p>These are the announcements you saved for later viewing.</p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="announcement-list">
                <?php if ($savedAnnouncements && $savedAnnouncements->num_rows > 0): ?>
                    <?php while ($announcement = $savedAnnouncements->fetch_assoc()): ?>
                        <article class="announcement-card">
                            <div class="announcement-top">
                                <div>
                                    <h2><?php echo htmlspecialchars($announcement["title"]); ?></h2>
                                    <div class="announcement-meta">
                                        Posted by <?php echo htmlspecialchars($announcement["posted_by_name"]); ?>
                                        as <?php echo htmlspecialchars($announcement["posted_by_role"]); ?><br>
                                        Date Posted: <?php echo htmlspecialchars($announcement["created_at"]); ?><br>
                                        Saved At: <?php echo htmlspecialchars($announcement["saved_at"]); ?>
                                    </div>
                                </div>

                                <div class="badge-row">
                                    <span class="badge <?php echo htmlspecialchars($announcement["urgency"]); ?>">
                                        <?php echo htmlspecialchars($announcement["urgency"]); ?>
                                    </span>
                                    <span class="badge saved">saved</span>
                                </div>
                            </div>

                            <div class="badge-row">
                                <span class="badge category"><?php echo htmlspecialchars($announcement["category"]); ?></span>

                                <?php if (!empty($announcement["faculty_name"])): ?>
                                    <span class="badge faculty-badge">
                                        <?php echo htmlspecialchars($announcement["faculty_name"]); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($announcement["program_name"])): ?>
                                    <span class="badge program-badge">
                                        <?php echo htmlspecialchars($announcement["program_name"]); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="detail-grid">
                                <div class="detail-item">
                                    <strong>Faculty</strong>
                                    <span>
                                        <?php echo !empty($announcement["faculty_name"]) ? htmlspecialchars($announcement["faculty_name"]) : "Not specified"; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <strong>Program</strong>
                                    <span>
                                        <?php echo !empty($announcement["program_name"]) ? htmlspecialchars($announcement["program_name"]) : "Not specified"; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <strong>Announcement Date</strong>
                                    <span>
                                        <?php echo !empty($announcement["announcement_date"]) ? htmlspecialchars($announcement["announcement_date"]) : "No date"; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <strong>Announcement Time</strong>
                                    <span>
                                        <?php echo !empty($announcement["announcement_time"]) ? date("h:i A", strtotime($announcement["announcement_time"])) : "No time"; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="announcement-message-box">
                                <div class="message-title">
                                    Announcement Message
                                </div>

                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($announcement["description"])); ?>
                                </div>
                            </div>

                            <div class="announcement-actions">
                                <form method="POST" action="student_dashboard.php?page=saved_announcements">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                    <input type="hidden" name="student_action" value="unsave">
                                    <button type="submit" class="btn remove-btn">Remove from Saved</button>
                                </form>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty">You have no saved announcements yet.</div>
                <?php endif; ?>
            </section>

        <?php endif; ?>

    </main>
</div>

<script>
    const facultyPrograms = <?php echo json_encode($facultyPrograms); ?>;
    const categoryFilter = document.getElementById("category_filter");
    const facultyFilterBox = document.getElementById("faculty_filter_box");
    const programFilterBox = document.getElementById("program_filter_box");
    const facultyFilter = document.getElementById("faculty_filter");
    const programFilter = document.getElementById("program_filter");

    function toggleFacultyProgramFilters() {
        if (!categoryFilter || !facultyFilterBox || !programFilterBox) {
            return;
        }

        if (categoryFilter.value === "Faculty Announcement") {
            facultyFilterBox.style.display = "block";
            programFilterBox.style.display = "block";
        } else {
            facultyFilterBox.style.display = "none";
            programFilterBox.style.display = "none";

            if (facultyFilter) {
                facultyFilter.value = "";
            }

            if (programFilter) {
                programFilter.innerHTML = '<option value="">Select faculty first</option>';
            }
        }
    }

    function loadProgramOptions() {
        if (!facultyFilter || !programFilter) {
            return;
        }

        const selectedFaculty = facultyFilter.value;
        const selectedProgram = programFilter.getAttribute("data-selected");

        programFilter.innerHTML = "";

        if (!selectedFaculty || !facultyPrograms[selectedFaculty]) {
            const option = document.createElement("option");
            option.value = "";
            option.textContent = "Select faculty first";
            programFilter.appendChild(option);
            return;
        }

        const defaultOption = document.createElement("option");
        defaultOption.value = "";
        defaultOption.textContent = "All matching programs";
        programFilter.appendChild(defaultOption);

        const allProgramsOption = document.createElement("option");
        allProgramsOption.value = "All Programs";
        allProgramsOption.textContent = "All Programs only";
        programFilter.appendChild(allProgramsOption);

        facultyPrograms[selectedFaculty].forEach(function(program) {
            const option = document.createElement("option");
            option.value = program;
            option.textContent = program;
            programFilter.appendChild(option);
        });

        if (selectedProgram) {
            programFilter.value = selectedProgram;
        }
    }

    if (categoryFilter) {
        categoryFilter.addEventListener("change", function() {
            toggleFacultyProgramFilters();
            loadProgramOptions();
        });
    }

    if (facultyFilter) {
        facultyFilter.addEventListener("change", function() {
            programFilter.setAttribute("data-selected", "");
            loadProgramOptions();
        });
    }

    toggleFacultyProgramFilters();
    loadProgramOptions();
</script>

</body>
</html>