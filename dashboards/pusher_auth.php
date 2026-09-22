<?php
require_once "../includes/auth.php";
require_once "../config/pusher.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    echo json_encode([
        "error" => "Not authenticated"
    ]);
    exit();
}

$userId = intval($_SESSION["user_id"]);

$socketId = isset($_POST["socket_id"]) ? $_POST["socket_id"] : "";
$channelName = isset($_POST["channel_name"]) ? $_POST["channel_name"] : "";

if (empty($socketId) || empty($channelName)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Missing socket_id or channel_name"
    ]);
    exit();
}

$allowedChannel = "private-user-" . $userId;

if ($channelName !== $allowedChannel) {
    http_response_code(403);
    echo json_encode([
        "error" => "Unauthorized channel"
    ]);
    exit();
}

try {
    $pusher = getPusherInstance();

    echo $pusher->authorizeChannel($channelName, $socketId);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Pusher authorization failed"
    ]);
    exit();
}
?>