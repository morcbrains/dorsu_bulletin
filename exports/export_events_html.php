<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_once "../classes/XMLReport.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if (!in_array($_SESSION["role"], ["admin", "informant"])) {
    header("Location: ../login.php");
    exit();
}

try {
    $xmlReport = new XMLReport($conn);
    $xmlReport->transformEventsToHtml();
    header("Location: serve.php?file=university_events_report.html");
    exit();
} catch (Throwable $error) {
    http_response_code(500);
    echo htmlspecialchars($error->getMessage());
    exit();
}
?>
