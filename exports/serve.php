<?php
require_once "../includes/auth.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if (!in_array($_SESSION["role"], ["admin", "informant"], true)) {
    http_response_code(403);
    echo "Access denied.";
    exit();
}

$allowedFiles = [
    "announcements_report.xml",
    "announcements_xml_view.html",
    "announcements_report.html",
    "announcements_report.xsl",
    "university_events_report.xml",
    "university_events_xml_view.html",
    "university_events_report.html",
    "events_report.xsl",
    "dorsu_bulletin_report.xml",
    "dorsu_bulletin_xml_view.html",
    "dorsu_bulletin_report.html",
    "dorsu_bulletin_report_dynamic.xsl",
];

$file = isset($_GET["file"]) ? basename($_GET["file"]) : "";

if (!in_array($file, $allowedFiles, true)) {
    http_response_code(404);
    echo "File not found.";
    exit();
}

$path = __DIR__ . "/" . $file;

if (!is_file($path)) {
    http_response_code(404);
    echo "File not found. Please generate the export first from the dashboard.";
    exit();
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$contentTypes = [
    "xml" => "application/xml; charset=UTF-8",
    "html" => "text/html; charset=UTF-8",
    "xsl" => "application/xml; charset=UTF-8",
];

header("Content-Type: " . ($contentTypes[$extension] ?? "application/octet-stream"));
header("Content-Disposition: inline; filename=" . $file);
readfile($path);
exit();
