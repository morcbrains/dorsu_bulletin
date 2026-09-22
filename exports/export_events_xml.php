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

$xmlReport = new XMLReport($conn);
$xmlReport->publishEventsXmlView();
header("Location: serve.php?file=university_events_xml_view.html");
exit();
?>
