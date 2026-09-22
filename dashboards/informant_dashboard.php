<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_once "../classes/Dashboard.php";
require_once "../classes/Announcement.php";
require_once "../classes/Event.php";
require_once "../classes/Notification.php";

requireRole("informant");

$dashboard = new Dashboard();
$announcementObj = new Announcement($conn);
$eventObj = new Event($conn);
$notificationObj = new Notification($conn);

$message = "";
$messageType = "";

$page = isset($_GET["page"]) ? $_GET["page"] : "home";

if (!in_array($page, ["home", "create_announcement", "my_announcements", "events", "profile"])) {
    $page = "home";
}

$eventCategories = [
    "Academic Event",
    "University Program",
    "Seminar / Workshop",
    "Orientation",
    "Student Activity",
    "Sports Event",
    "Cultural Event",
    "Training",
    "Meeting / Assembly",
    "Scholarship / Financial Assistance",
    "Admission / Enrollment",
    "Library Activity",
    "Counseling Activity",
    "ICT Advisory",
    "Community Extension",
    "Holiday / Class Suspension",
    "Other"
];

$userId = $_SESSION["user_id"];
$currentOffice = "";

$stmtOffice = $conn->prepare("
    SELECT office_name
    FROM users
    WHERE id = ?
    AND role = 'informant'
    LIMIT 1
");

$stmtOffice->bind_param("i", $userId);
$stmtOffice->execute();

$officeResult = $stmtOffice->get_result();

if ($officeResult && $officeResult->num_rows > 0) {
    $officeData = $officeResult->fetch_assoc();
    $currentOffice = $officeData["office_name"];
}

$stmtOffice->close();


function getInformantNotificationRoles($targetAudience) {
    if ($targetAudience === "All Users") {
        return ["admin", "informant", "faculty", "student"];
    }

    if ($targetAudience === "All Students") {
        return ["student"];
    }

    if ($targetAudience === "All Faculty Members") {
        return ["faculty"];
    }

    if ($targetAudience === "Students and Faculty") {
        return ["student", "faculty"];
    }

    if ($targetAudience === "University Office Informants" || $targetAudience === "University Informants") {
        return ["informant"];
    }

    return [];
}

function getInformantNotificationLink($role, $type) {
    if ($type === "event") {
        if ($role === "student") {
            return "student_dashboard.php?page=events";
        }

        if ($role === "faculty") {
            return "faculty_dashboard.php?page=university_events";
        }

        if ($role === "informant") {
            return "informant_dashboard.php?page=events";
        }

        if ($role === "admin") {
            return "admin_dashboard.php?page=reports";
        }
    }

    if ($type === "announcement") {
        if ($role === "student") {
            return "student_dashboard.php?page=faculty_program_announcements";
        }

        if ($role === "faculty") {
            return "faculty_dashboard.php?page=university_announcements";
        }

        if ($role === "informant") {
            return "informant_dashboard.php?page=my_announcements";
        }

        if ($role === "admin") {
            return "admin_dashboard.php?page=manage_announcements";
        }
    }

    return "../login.php";
}

function createInformantNotificationForAudience($conn, $notificationObj, $targetAudience, $notificationType, $title, $message, $excludeUserId = null) {
    $roles = getInformantNotificationRoles($targetAudience);

    if (empty($roles)) {
        return false;
    }

    $placeholders = implode(",", array_fill(0, count($roles), "?"));
    $types = str_repeat("s", count($roles));

    $sql = "
        SELECT id, role
        FROM users
        WHERE role IN ($placeholders)
        AND status = 'approved'
    ";

    $params = $roles;

    if (!empty($excludeUserId)) {
        $sql .= " AND id != ?";
        $types .= "i";
        $params[] = $excludeUserId;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();
    $created = false;

    if ($result && $result->num_rows > 0) {
        while ($user = $result->fetch_assoc()) {
            $link = getInformantNotificationLink($user["role"], $notificationType);

            if ($notificationObj->createNotification($user["id"], $notificationType, $title, $message, $link)) {
                $created = true;
            }
        }
    }

    $stmt->close();

    return $created;
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_announcement"])) {
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $category = trim($_POST["category"]);
    $targetAudience = trim($_POST["target_audience"]);
    $urgency = trim($_POST["urgency"]);
    $status = trim($_POST["status"]);

    if (empty($title) || empty($description) || empty($category) || empty($targetAudience) || empty($urgency) || empty($status)) {
        $message = "Please fill in all announcement fields.";
        $messageType = "error";
        $page = "create_announcement";
    } elseif ($announcementObj->createAnnouncement($title, $description, $category, $targetAudience, $urgency, $status, $userId)) {
        if ($status === "published") {
            $officeDisplay = !empty($currentOffice) ? $currentOffice : "University Office";

            createInformantNotificationForAudience(
                $conn,
                $notificationObj,
                $targetAudience,
                "announcement",
                "New University Announcement",
                "A new announcement has been posted by " . $officeDisplay . ": " . $title,
                $userId
            );
        }

        $message = "Announcement created successfully.";
        $messageType = "success";
        $page = "my_announcements";
    } else {
        $message = "Failed to create announcement.";
        $messageType = "error";
        $page = "create_announcement";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["announcement_id"], $_POST["announcement_action"])) {
    $announcementId = intval($_POST["announcement_id"]);
    $announcementAction = $_POST["announcement_action"];

    if ($announcementAction === "publish") {
        $newStatus = "published";

        if ($announcementObj->updateMyAnnouncementStatus($announcementId, $userId, $newStatus)) {
            $announcementInfoStmt = $conn->prepare("
                SELECT title, target_audience
                FROM announcements
                WHERE id = ?
                AND posted_by = ?
                LIMIT 1
            ");
            $announcementInfoStmt->bind_param("ii", $announcementId, $userId);
            $announcementInfoStmt->execute();
            $announcementInfoResult = $announcementInfoStmt->get_result();

            if ($announcementInfoResult && $announcementInfoResult->num_rows > 0) {
                $announcementInfo = $announcementInfoResult->fetch_assoc();
                $officeDisplay = !empty($currentOffice) ? $currentOffice : "University Office";

                createInformantNotificationForAudience(
                    $conn,
                    $notificationObj,
                    $announcementInfo["target_audience"],
                    "announcement",
                    "New University Announcement",
                    "A new announcement has been posted by " . $officeDisplay . ": " . $announcementInfo["title"],
                    $userId
                );
            }

            $announcementInfoStmt->close();

            $message = "Announcement published successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to publish announcement.";
            $messageType = "error";
        }

        $page = "my_announcements";
    } elseif ($announcementAction === "draft") {
        $newStatus = "draft";

        if ($announcementObj->updateMyAnnouncementStatus($announcementId, $userId, $newStatus)) {
            $message = "Announcement moved to draft successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to update announcement.";
            $messageType = "error";
        }

        $page = "my_announcements";
    } elseif ($announcementAction === "archive") {
        $newStatus = "archived";

        if ($announcementObj->updateMyAnnouncementStatus($announcementId, $userId, $newStatus)) {
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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_event"])) {
    $eventTitle = trim($_POST["event_title"]);
    $eventDescription = trim($_POST["event_description"]);
    $eventCategory = trim($_POST["event_category"]);
    $eventDate = trim($_POST["event_date"]);
    $startTime = trim($_POST["start_time"]);
    $endTime = trim($_POST["end_time"]);
    $venue = trim($_POST["venue"]);
    $organizer = $currentOffice;
    $targetAudience = trim($_POST["target_audience"]);
    $status = trim($_POST["status"]);

    if (empty($eventTitle) || empty($eventDescription) || empty($eventCategory) || empty($eventDate) || empty($startTime) || empty($venue) || empty($organizer) || empty($targetAudience) || empty($status)) {
    $message = "Please fill in all required event fields. If organizer is missing, please contact the administrator to assign your office.";
    $messageType = "error";
    $page = "events";
    } elseif (!in_array($eventCategory, $eventCategories)) {
        $message = "Invalid event category selected.";
        $messageType = "error";
        $page = "events";
    } elseif (!in_array($status, ["draft", "published"])) {
        $message = "Invalid event status selected.";
        $messageType = "error";
        $page = "events";
    } else {
        if ($eventObj->createEvent($eventTitle, $eventDescription, $eventCategory, $eventDate, $startTime, $endTime, $venue, $organizer, $targetAudience, $status, $userId)) {
            if ($status === "published") {
                $officeDisplay = !empty($currentOffice) ? $currentOffice : "University Office";

                createInformantNotificationForAudience(
                    $conn,
                    $notificationObj,
                    $targetAudience,
                    "event",
                    "New University Event",
                    "A new university event has been posted by " . $officeDisplay . ": " . $eventTitle,
                    $userId
                );
            }

            $message = "University event created successfully.";
            $messageType = "success";
            $page = "events";
        } else {
            $message = "Failed to create university event.";
            $messageType = "error";
            $page = "events";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["event_id"], $_POST["event_action"])) {
    $eventId = intval($_POST["event_id"]);
    $eventAction = $_POST["event_action"];

    if ($eventAction === "publish") {
        $newStatus = "published";

        if ($eventObj->updateMyEventStatus($eventId, $userId, $newStatus)) {
            $eventInfoStmt = $conn->prepare("
                SELECT event_title, target_audience
                FROM university_events
                WHERE id = ?
                AND created_by = ?
                LIMIT 1
            ");
            $eventInfoStmt->bind_param("ii", $eventId, $userId);
            $eventInfoStmt->execute();
            $eventInfoResult = $eventInfoStmt->get_result();

            if ($eventInfoResult && $eventInfoResult->num_rows > 0) {
                $eventInfo = $eventInfoResult->fetch_assoc();
                $officeDisplay = !empty($currentOffice) ? $currentOffice : "University Office";

                createInformantNotificationForAudience(
                    $conn,
                    $notificationObj,
                    $eventInfo["target_audience"],
                    "event",
                    "New University Event",
                    "A new university event has been posted by " . $officeDisplay . ": " . $eventInfo["event_title"],
                    $userId
                );
            }

            $eventInfoStmt->close();

            $message = "Event published successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to publish event.";
            $messageType = "error";
        }

        $page = "events";
    } elseif ($eventAction === "draft") {
        $newStatus = "draft";

        if ($eventObj->updateMyEventStatus($eventId, $userId, $newStatus)) {
            $message = "Event moved to draft successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to update event.";
            $messageType = "error";
        }

        $page = "events";
    } elseif ($eventAction === "archive") {
        $newStatus = "archived";

        if ($eventObj->updateMyEventStatus($eventId, $userId, $newStatus)) {
            $message = "Event archived successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to archive event.";
            $messageType = "error";
        }

        $page = "events";
    } elseif ($eventAction === "delete") {
        if ($eventObj->deleteMyEvent($eventId, $userId)) {
            $message = "Event deleted successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to delete event.";
            $messageType = "error";
        }

        $page = "events";
    }
}

$activeCategories = $announcementObj->getActiveCategories();
$myAnnouncements = $announcementObj->getMyAnnouncements($userId);

$totalMyAnnouncements = $announcementObj->getTotalMyAnnouncements($userId);
$publishedMyAnnouncements = $announcementObj->getTotalMyAnnouncementsByStatus($userId, "published");
$draftMyAnnouncements = $announcementObj->getTotalMyAnnouncementsByStatus($userId, "draft");
$archivedMyAnnouncements = $announcementObj->getTotalMyAnnouncementsByStatus($userId, "archived");
$urgentMyAnnouncements = $announcementObj->getTotalMyUrgentAnnouncements($userId);

$myEvents = $eventObj->getMyEvents($userId);
$totalMyEvents = $eventObj->getTotalMyEvents($userId);
$publishedMyEvents = $eventObj->getTotalMyEventsByStatus($userId, "published");
$draftMyEvents = $eventObj->getTotalMyEventsByStatus($userId, "draft");
$archivedMyEvents = $eventObj->getTotalMyEventsByStatus($userId, "archived");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>University Office Informant Dashboard | DOrSU Connect</title>
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
        width: 290px;
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

    .user-profile {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 13px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.14);
        margin-bottom: 25px;
        min-width: 250px;
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
        max-width: 170px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .profile-text p {
        font-size: 12px;
        opacity: 0.85;
        max-width: 170px;
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
        min-width: 250px;
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
    .xml-export-box {
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
    .xml-export-box:hover {
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

    .card p,
    .xml-export-box p {
        color: var(--dorsu-muted);
        line-height: 1.6;
    }

    .xml-export-box {
        margin-bottom: 25px;
    }

    .xml-export-box h2 {
        color: var(--dorsu-blue-dark);
        margin-bottom: 12px;
    }

    .xml-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 18px;
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
    .form-group input[readonly] {
        background: #eef4ff;
        color: var(--dorsu-blue-dark);
        font-weight: bold;
        cursor: not-allowed;
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

    .form-group textarea {
        resize: vertical;
        min-height: 140px;
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

    .target {
        background: #eef4ff;
        color: var(--dorsu-blue-dark);
        border: 1px solid rgba(0, 63, 174, 0.15);
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
        .form-grid {
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
            <a href="informant_dashboard.php?page=home" class="<?php echo $page === 'home' ? 'active' : ''; ?>">Home</a>
            <a href="informant_dashboard.php?page=profile" class="<?php echo $page === 'profile' ? 'active' : ''; ?>">Profile</a>
            <a href="messages.php">Messages</a>
            <a href="informant_dashboard.php?page=create_announcement" class="<?php echo $page === 'create_announcement' ? 'active' : ''; ?>">Create Announcements</a>
            <a href="informant_dashboard.php?page=my_announcements" class="<?php echo $page === 'my_announcements' ? 'active' : ''; ?>">My Announcements</a>
            <a href="informant_dashboard.php?page=events" class="<?php echo $page === 'events' ? 'active' : ''; ?>">Create University Events</a>
            <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </aside>

    <main class="main">
    <?php include __DIR__ . "/notification_bell.php"; ?>

        <?php if ($page === "home"): ?>
            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">University Office Informant Dashboard</span>
                    <h1>Welcome, <?php echo htmlspecialchars(getUserFullName()); ?>!</h1>
                    <p>
                        Publish trusted university announcements, highlight campus events, promote student activities, and share important updates from your office.
                        Use this dashboard to keep the DOrSU community informed, connected, and actively engaged.
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
                    <h3><?php echo $totalMyAnnouncements; ?></h3>
                    <p>My Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $publishedMyAnnouncements; ?></h3>
                    <p>Published Announcements</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $totalMyEvents; ?></h3>
                    <p>My Events</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $publishedMyEvents; ?></h3>
                    <p>Published Events</p>
                </div>
            </section>

            <section class="cards">
                <div class="card">
                    <h3>Create Official Announcement</h3>
                    <p>Post verified announcements for students, faculty members, or the entire university.</p>
                </div>

                <div class="card">
                    <h3>Create University Event</h3>
                    <p>Add events with date, time, venue, organizer, audience, and status.</p>
                </div>

                <div class="card">
                    <h3>Manage Own Posts</h3>
                    <p>Publish, draft, archive, or delete your announcements and events.</p>
                </div>

                <div class="card">
                    <h3>Organized Updates</h3>
                    <p>Use categories and event details so students can understand updates faster.</p>
                </div>
            </section>

            <section class="xml-export-box">
                <h2>XML and XSLT Reports</h2>
                <p>
                    Export live database records to XML, view the raw XML tags, then transform the parsed XML into HTML using XSLT.
                    Each button below has a different purpose.
                </p>

                <div class="xml-buttons">
                    <a href="../exports/export_announcements_xml.php" class="btn primary" target="_blank">
                        Export Announcements XML
                    </a>

                    <a href="../exports/export_announcements_html.php" class="btn primary" target="_blank">
                        Generate Announcements HTML via XSLT
                    </a>

                    <a href="../exports/export_events_xml.php" class="btn primary" target="_blank">
                        Export Events XML
                    </a>

                    <a href="../exports/export_events_html.php" class="btn primary" target="_blank">
                        Generate Events HTML via XSLT
                    </a>

                    <a href="../exports/export_dorsu_bulletin_xml.php" class="btn primary" target="_blank">
                        Export Full Bulletin XML
                    </a>

                    <a href="../exports/export_dorsu_bulletin_html.php" class="btn primary" target="_blank">
                        Generate Full Bulletin HTML via XSLT
                    </a>
                </div>
            </section>

        <?php elseif ($page === "create_announcement"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">Create Announcement</span>
                    <h1>Create Official Announcement</h1>
                    <p>Fill in the announcement details below.</p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="form-box">
                <h2>Announcement Form</h2>

                <form method="POST" action="informant_dashboard.php?page=create_announcement">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Announcement Title</label>
                            <input type="text" name="title" placeholder="Enter announcement title" required>
                        </div>

                        <div class="form-group full">
                            <label>Description</label>
                            <textarea name="description" placeholder="Write the announcement details here..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" required>
                                <option value="">Select category</option>
                                <?php if ($activeCategories && $activeCategories->num_rows > 0): ?>
                                    <?php while ($category = $activeCategories->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($category["category_name"]); ?>">
                                            <?php echo htmlspecialchars($category["category_name"]); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Target Audience</label>
                            <select name="target_audience" required>
                                <option value="">Select target audience</option>
                                <option value="All Users">All Users</option>
                                <option value="All Students">All Students</option>
                                <option value="All Faculty Members">All Faculty Members</option>
                                <option value="Students and Faculty">Students and Faculty</option>
                                <option value="University Informants">University Office Informants</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Urgency</label>
                            <select name="urgency" required>
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" required>
                                <option value="published">Publish Now</option>
                                <option value="draft">Save as Draft</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="create_announcement" class="btn primary">Submit Announcement</button>
                        <a href="informant_dashboard.php?page=my_announcements" class="btn draft-btn">View My Announcements</a>
                    </div>
                </form>
            </section>

        <?php elseif ($page === "my_announcements"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">My Announcements</span>
                    <h1>Manage My Announcements</h1>
                    <p>View and manage all announcements you created.</p>
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
                                <th>Description</th>
                                <th>Category</th>
                                <th>Target</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th>Date Posted</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($announcement = $myAnnouncements->fetch_assoc()): ?>
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
                                    <td><?php echo htmlspecialchars($announcement["created_at"]); ?></td>
                                    <td>
                                        <form method="POST" class="action-form" action="informant_dashboard.php?page=my_announcements">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="publish">
                                            <button type="submit" class="btn publish">Publish</button>
                                        </form>

                                        <form method="POST" class="action-form" action="informant_dashboard.php?page=my_announcements">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="draft">
                                            <button type="submit" class="btn draft-btn">Draft</button>
                                        </form>

                                        <form method="POST" class="action-form" action="informant_dashboard.php?page=my_announcements">
                                            <input type="hidden" name="announcement_id" value="<?php echo $announcement["id"]; ?>">
                                            <input type="hidden" name="announcement_action" value="archive">
                                            <button type="submit" class="btn archive">Archive</button>
                                        </form>

                                        <form method="POST" class="action-form" action="informant_dashboard.php?page=my_announcements" onsubmit="return confirm('Delete this announcement?');">
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
                    <div class="empty">You have not created any announcements yet.</div>
                <?php endif; ?>
            </section>

        <?php elseif ($page === "events"): ?>

            <section class="dashboard-header">
                <div class="header-content">
                    <span class="header-badge">University Events</span>
                    <h1>Manage University Events</h1>
                    <p>Create and manage university activities, programs, celebrations, seminars, and events.</p>
                </div>
            </section>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <section class="summary-grid">
                <div class="summary-box">
                    <h3><?php echo $totalMyEvents; ?></h3>
                    <p>Total Events</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $publishedMyEvents; ?></h3>
                    <p>Published</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $draftMyEvents; ?></h3>
                    <p>Draft</p>
                </div>

                <div class="summary-box">
                    <h3><?php echo $archivedMyEvents; ?></h3>
                    <p>Archived</p>
                </div>
            </section>

            <section class="form-box">
                <h2>Add New University Event</h2>

                <form method="POST" action="informant_dashboard.php?page=events">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Event Title</label>
                            <input type="text" name="event_title" placeholder="Enter event title" required>
                        </div>

                        <div class="form-group full">
                            <label>Description</label>
                            <textarea name="event_description" placeholder="Enter event description" required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <select name="event_category" required>
                                <option value="">Select event category</option>
                                <?php foreach ($eventCategories as $eventCategoryOption): ?>
                                    <option value="<?php echo htmlspecialchars($eventCategoryOption); ?>">
                                        <?php echo htmlspecialchars($eventCategoryOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="event_date" required>
                        </div>

                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" required>
                        </div>

                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time">
                        </div>

                        <div class="form-group">
                            <label>Venue</label>
                            <input type="text" name="venue" placeholder="Enter venue" required>
                        </div>

                        <div class="form-group">
                            <label>Organizer</label>
                            <input type="text" value="<?php echo !empty($currentOffice) ? htmlspecialchars($currentOffice) : 'No office assigned'; ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Target Audience</label>
                            <select name="target_audience" required>
                                <option value="">Select target audience</option>
                                <option value="All Users">All Users</option>
                                <option value="All Students">All Students</option>
                                <option value="All Faculty Members">All Faculty Members</option>
                                <option value="Students and Faculty">Students and Faculty</option>
                                <option value="University Informants">University Office Informants</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" required>
                                <option value="">Select status</option>
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="create_event" class="btn primary">Save University Event</button>
                    </div>
                </form>
            </section>

            <section class="section">
                <div class="section-header">
                    <h2>My University Events</h2>
                    <span class="section-label"><?php echo $totalMyEvents; ?> total</span>
                </div>

                <?php if ($myEvents && $myEvents->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Event Title</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Venue</th>
                                <th>Organizer</th>
                                <th>Target</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($event = $myEvents->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event["event_title"]); ?></td>

                                    <td class="description-cell">
                                        <?php echo htmlspecialchars(substr($event["event_description"], 0, 130)); ?>
                                        <?php echo strlen($event["event_description"]) > 130 ? "..." : ""; ?>
                                    </td>

                                    <td><?php echo htmlspecialchars($event["event_category"]); ?></td>
                                    <td><?php echo htmlspecialchars($event["event_date"]); ?></td>

                                    <td>
                                        <?php echo date("h:i A", strtotime($event["start_time"])); ?>
                                        -
                                        <?php echo !empty($event["end_time"]) ? date("h:i A", strtotime($event["end_time"])) : "No end time"; ?>
                                    </td>

                                    <td><?php echo htmlspecialchars($event["venue"]); ?></td>
                                    <td><?php echo htmlspecialchars($event["organizer"]); ?></td>

                                    <td>
                                        <span class="badge target">
                                            <?php echo htmlspecialchars($event["target_audience"]); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge <?php echo htmlspecialchars($event["status"]); ?>">
                                            <?php echo htmlspecialchars($event["status"]); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <form method="POST" class="action-form" action="informant_dashboard.php?page=events">
                                            <input type="hidden" name="event_id" value="<?php echo $event["id"]; ?>">
                                            <input type="hidden" name="event_action" value="publish">
                                            <button type="submit" class="btn publish">Publish</button>
                                        </form>

                                        <form method="POST" class="action-form" action="informant_dashboard.php?page=events">
                                            <input type="hidden" name="event_id" value="<?php echo $event["id"]; ?>">
                                            <input type="hidden" name="event_action" value="draft">
                                            <button type="submit" class="btn draft-btn">Draft</button>
                                        </form>

                                        <form method="POST" class="action-form" action="informant_dashboard.php?page=events">
                                            <input type="hidden" name="event_id" value="<?php echo $event["id"]; ?>">
                                            <input type="hidden" name="event_action" value="archive">
                                            <button type="submit" class="btn archive">Archive</button>
                                        </form>

                                        <form method="POST" class="action-form" action="informant_dashboard.php?page=events" onsubmit="return confirm('Delete this event?');">
                                            <input type="hidden" name="event_id" value="<?php echo $event["id"]; ?>">
                                            <input type="hidden" name="event_action" value="delete">
                                            <button type="submit" class="btn delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">No university events created yet.</div>
                <?php endif; ?>
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
                            <td>University Office Informant</td>
                        </tr>
                    </tbody>
                </table>
            </section>

        <?php endif; ?>

    </main>
</div>

</body>
</html>