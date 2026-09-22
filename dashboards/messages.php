<?php
require_once "../includes/auth.php";
require_once "../config/db.php";

$pusherConfigPath = __DIR__ . "/../config/pusher.php";

if (file_exists($pusherConfigPath)) {
    require_once $pusherConfigPath;
}

require_once "../classes/PrivateMessage.php";
require_once "../classes/RabbitMQPublisher.php";
require_once "../classes/Notification.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION["user_id"];
$userRole = $_SESSION["role"];

if (!in_array($userRole, ["informant", "faculty", "student"])) {
    header("Location: ../login.php");
    exit();
}

function getDashboardLink($role) {
    if ($role === "informant") {
        return "informant_dashboard.php";
    }

    if ($role === "faculty") {
        return "faculty_dashboard.php";
    }

    if ($role === "student") {
        return "student_dashboard.php";
    }

    return "../login.php";
}

function clearRabbitMQPrivateMessageQueue($currentUserId) {
    if (!defined("RABBITMQ_HOST")) {
        return 0;
    }

    $currentUserId = intval($currentUserId);

    if ($currentUserId <= 0) {
        return 0;
    }

    $queueName = "dorsu_private_messages_user_" . $currentUserId;
    $clearedMessages = 0;

    try {
        $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
            RABBITMQ_HOST,
            RABBITMQ_PORT,
            RABBITMQ_USER,
            RABBITMQ_PASSWORD,
            RABBITMQ_VHOST
        );

        $channel = $connection->channel();

        $channel->queue_declare(
            $queueName,
            false,
            true,
            false,
            false
        );

        while (true) {
            $queuedMessage = $channel->basic_get($queueName, false);

            if (!$queuedMessage) {
                break;
            }

            if (method_exists($queuedMessage, "getDeliveryTag")) {
                $deliveryTag = $queuedMessage->getDeliveryTag();
            } else {
                $deliveryTag = $queuedMessage->delivery_info["delivery_tag"];
            }

            $channel->basic_ack($deliveryTag);
            $clearedMessages++;
        }

        $channel->close();
        $connection->close();
    } catch (Exception $e) {
        return 0;
    }

    return $clearedMessages;
}

$pusherReady = function_exists("getPusherInstance") && defined("PUSHER_APP_KEY") && defined("PUSHER_APP_CLUSTER");

$messageObj = new PrivateMessage($conn);
$notificationObj = new Notification($conn);

$message = "";
$messageType = "";

$selectedUserId = isset($_GET["user"]) ? intval($_GET["user"]) : 0;
$selectedUser = null;

$rabbitQueueCleared = clearRabbitMQPrivateMessageQueue($userId);

$contacts = $messageObj->getAvailableContacts($userId, $userRole);
$threads = $messageObj->getInboxThreads($userId);
$totalUnread = $messageObj->getTotalUnreadMessages($userId);

if ($selectedUserId > 0) {
    $selectedUser = $messageObj->getUserById($selectedUserId);

    if (!$selectedUser) {
        $selectedUserId = 0;
        $selectedUser = null;
        $message = "Selected user was not found.";
        $messageType = "error";
    } elseif (!$messageObj->canMessageRole($userRole, $selectedUser["role"])) {
        $selectedUserId = 0;
        $selectedUser = null;
        $message = "You are not allowed to message this user.";
        $messageType = "error";
    } elseif ($selectedUser["status"] !== "approved") {
        $selectedUserId = 0;
        $selectedUser = null;
        $message = "You can only message approved users.";
        $messageType = "error";
    } else {
        $messageObj->markConversationAsRead($userId, $selectedUserId);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["send_message"])) {
    $receiverId = intval($_POST["receiver_id"]);
    $messageText = trim($_POST["message"]);

    $receiver = $messageObj->getUserById($receiverId);

    if (!$receiver) {
        $message = "Receiver not found.";
        $messageType = "error";
    } elseif (!$messageObj->canMessageRole($userRole, $receiver["role"])) {
        $message = "You are not allowed to message this user.";
        $messageType = "error";
    } elseif ($receiver["status"] !== "approved") {
        $message = "You can only message approved users.";
        $messageType = "error";
    } elseif (empty($messageText)) {
        $message = "Please type a message.";
        $messageType = "error";
        $selectedUserId = $receiverId;
        $selectedUser = $receiver;
    } else {
        $newMessageId = $messageObj->sendMessage($userId, $receiverId, $messageText);

        if ($newMessageId) {
            $newMessage = $messageObj->getMessageById($newMessageId);

            if ($newMessage) {
                $eventData = [
                    "id" => $newMessage["id"],
                    "sender_id" => $newMessage["sender_id"],
                    "receiver_id" => $newMessage["receiver_id"],
                    "message" => $newMessage["message"],
                    "created_at" => $newMessage["created_at"],
                    "sender_name" => $newMessage["sender_name"],
                    "sender_role" => $newMessage["sender_role"],
                    "receiver_name" => $newMessage["receiver_name"],
                    "receiver_role" => $newMessage["receiver_role"]
                ];

                $notificationObj->createNotification(
                    $receiverId,
                    "private_message",
                    "New Private Message",
                    "You received a new message from " . $newMessage["sender_name"] . ".",
                    "messages.php?user=" . $userId
                );

                try {
                    $rabbitPublisher = new RabbitMQPublisher();

                    if ($rabbitPublisher->isConnected()) {
                        $rabbitPublisher->publishPrivateMessage($receiverId, [
                            "type" => "private_message",
                            "message_id" => $newMessage["id"],
                            "sender_id" => $newMessage["sender_id"],
                            "receiver_id" => $newMessage["receiver_id"],
                            "sender_name" => $newMessage["sender_name"],
                            "sender_role" => $newMessage["sender_role"],
                            "receiver_name" => $newMessage["receiver_name"],
                            "receiver_role" => $newMessage["receiver_role"],
                            "message" => $newMessage["message"],
                            "created_at" => $newMessage["created_at"],
                            "status" => "pending_receiver_open"
                        ]);
                    }

                    $rabbitPublisher->close();
                } catch (Exception $e) {
                }

                if ($pusherReady) {
                    try {
                        $pusher = getPusherInstance();

                        $pusher->trigger("private-user-" . $receiverId, "new-message", $eventData);
                        $pusher->trigger("private-user-" . $userId, "new-message", $eventData);
                    } catch (Exception $e) {
                        $message = "Message sent, but realtime notification failed. Please check your Pusher configuration.";
                        $messageType = "error";
                    }
                }
            }

            header("Location: messages.php?user=" . $receiverId);
            exit();
        } else {
            $message = "Failed to send message.";
            $messageType = "error";
            $selectedUserId = $receiverId;
            $selectedUser = $receiver;
        }
    }
}

$conversation = null;

if ($selectedUserId > 0) {
    $conversation = $messageObj->getConversation($userId, $selectedUserId);
}

$pusherAppKey = defined("PUSHER_APP_KEY") ? PUSHER_APP_KEY : "";
$pusherAppCluster = defined("PUSHER_APP_CLUSTER") ? PUSHER_APP_CLUSTER : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Private Messages | DOrSU Connect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php if ($pusherReady): ?>
        <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <?php endif; ?>

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

        .brand,
        .profile-text,
        .nav a {
            opacity: 0;
            transform: translateX(-12px);
            transition: 0.3s ease;
            white-space: nowrap;
            pointer-events: none;
        }

        .sidebar:hover .brand,
        .sidebar:hover .profile-text,
        .sidebar:hover .nav a {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
        }

        .brand {
            margin-bottom: 25px;
        }

        .brand h2 {
            font-size: 24px;
            margin-bottom: 5px;
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

        .profile-text h3 {
            font-size: 14px;
            margin-bottom: 3px;
            max-width: 190px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-text p {
            font-size: 12px;
            opacity: 0.85;
            max-width: 190px;
            overflow: hidden;
            text-overflow: ellipsis;
            text-transform: capitalize;
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
            font-weight: bold;
            font-size: 14px;
        }

        .nav a:hover,
        .nav a.active {
            background: rgba(246, 196, 0, 0.25);
        }

        .logout {
            margin-top: 18px;
            background: var(--dorsu-gold-light) !important;
            color: var(--dorsu-blue-dark) !important;
            text-align: center;
            border: 1px solid var(--dorsu-gold);
        }

        .main {
            margin-left: 78px;
            width: calc(100% - 78px);
            padding: 30px;
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

        .message-box {
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

        .pusher-status,
        .rabbitmq-status {
            background: white;
            padding: 13px 16px;
            border-radius: 15px;
            margin-bottom: 18px;
            border-left: 7px solid var(--dorsu-gold);
            box-shadow: 0 10px 24px rgba(0, 43, 127, 0.08);
            color: var(--dorsu-muted);
            font-weight: bold;
        }

        .messaging-layout {
            display: grid;
            grid-template-columns: 330px 1fr;
            gap: 20px;
        }

        .panel,
        .chat-panel,
        .contacts-panel {
            background: white;
            border-radius: 22px;
            box-shadow: 0 10px 28px rgba(0, 43, 127, 0.08);
            border: 1px solid #e3eaf8;
            overflow: hidden;
        }

        .panel-header {
            padding: 18px;
            background: #eef4ff;
            border-bottom: 1px solid #dce7fa;
        }

        .panel-header h2 {
            color: var(--dorsu-blue-dark);
            font-size: 18px;
            margin-bottom: 5px;
        }

        .panel-header p {
            color: var(--dorsu-muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .thread-list,
        .contact-list {
            padding: 12px;
            max-height: 330px;
            overflow-y: auto;
        }

        .thread-item,
        .contact-item {
            display: block;
            padding: 14px;
            border-radius: 15px;
            text-decoration: none;
            color: var(--dorsu-text);
            margin-bottom: 8px;
            border: 1px solid #e3eaf8;
            transition: 0.25s;
            background: white;
        }

        .thread-item:hover,
        .thread-item.active,
        .contact-item:hover {
            background: #f8fbff;
            border-color: #c7d5ef;
            transform: translateY(-2px);
        }

        .thread-item strong,
        .contact-item strong {
            display: block;
            color: var(--dorsu-blue-dark);
            margin-bottom: 4px;
        }

        .thread-item span,
        .contact-item span {
            font-size: 12px;
            color: var(--dorsu-muted);
            text-transform: capitalize;
        }

        .unread-pill {
            display: inline-block;
            background: var(--dorsu-gold);
            color: #3b2b00;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 6px;
        }

        .chat-header {
            padding: 20px;
            background: #eef4ff;
            border-bottom: 1px solid #dce7fa;
        }

        .chat-header h2 {
            color: var(--dorsu-blue-dark);
            margin-bottom: 5px;
        }

        .chat-header p {
            color: var(--dorsu-muted);
            font-size: 13px;
            text-transform: capitalize;
        }

        .chat-body {
            padding: 20px;
            min-height: 420px;
            max-height: 520px;
            overflow-y: auto;
            background: #f8fbff;
        }

        .bubble-row {
            display: flex;
            margin-bottom: 14px;
        }

        .bubble-row.mine {
            justify-content: flex-end;
        }

        .bubble-row.theirs {
            justify-content: flex-start;
        }

        .bubble {
            max-width: 70%;
            padding: 14px 16px;
            border-radius: 18px;
            line-height: 1.6;
            font-size: 14px;
            white-space: pre-line;
        }

        .bubble-row.mine .bubble {
            background: linear-gradient(135deg, var(--dorsu-blue-dark), var(--dorsu-blue));
            color: white;
            border-bottom-right-radius: 5px;
        }

        .bubble-row.theirs .bubble {
            background: white;
            color: var(--dorsu-text);
            border: 1px solid #dce7fa;
            border-bottom-left-radius: 5px;
        }

        .bubble-time {
            display: block;
            font-size: 11px;
            opacity: 0.75;
            margin-top: 8px;
        }

        .chat-form {
            padding: 18px;
            border-top: 1px solid #dce7fa;
            background: white;
        }

        .chat-form textarea {
            width: 100%;
            min-height: 95px;
            resize: vertical;
            padding: 14px;
            border: 1px solid #cfd8ea;
            border-radius: 14px;
            outline: none;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .chat-form textarea:focus {
            border-color: var(--dorsu-blue);
            box-shadow: 0 0 0 3px rgba(0, 63, 174, 0.13);
        }

        .btn {
            border: none;
            padding: 11px 15px;
            border-radius: 11px;
            cursor: pointer;
            font-weight: bold;
            color: white;
            background: linear-gradient(135deg, var(--dorsu-blue-dark), var(--dorsu-blue));
            transition: 0.25s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0, 43, 127, 0.18);
        }

        .empty {
            color: var(--dorsu-muted);
            background: #f8fbff;
            padding: 18px;
            border-radius: 14px;
            border: 1px dashed #c7d5ef;
            margin: 12px;
        }

        .empty.center {
            margin: 0;
            text-align: center;
            padding: 45px 20px;
        }

        @media (max-width: 1000px) {
            .messaging-layout {
                grid-template-columns: 1fr;
            }

            .thread-list,
            .contact-list {
                max-height: 260px;
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
                min-height: 330px;
            }

            .user-profile {
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

            .dashboard-header {
                padding: 25px;
            }

            .dashboard-header h1 {
                font-size: 28px;
            }

            .bubble {
                max-width: 88%;
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
            <p>Private Messaging</p>
        </div>

        <div class="user-profile">
            <div class="avatar">
                <?php echo strtoupper(substr(getUserFullName(), 0, 1)); ?>
            </div>

            <div class="profile-text">
                <h3><?php echo htmlspecialchars(getUserFullName()); ?></h3>
                <p><?php echo htmlspecialchars($userRole); ?></p>
            </div>
        </div>

        <nav class="nav">
            <a href="<?php echo getDashboardLink($userRole); ?>">Back to Dashboard</a>
            <a href="messages.php" class="active">Messages <?php echo $totalUnread > 0 ? "(" . $totalUnread . ")" : ""; ?></a>
            <a href="../logout.php" class="logout">Logout</a>
        </nav>
    </aside>

    <main class="main">
        <?php include __DIR__ . "/notification_bell.php"; ?>

        <section class="dashboard-header">
            <div class="header-content">
                <span class="header-badge">Private Messaging</span>
                <h1>Messages</h1>
                <p>
                    Send private person-to-person messages based on allowed communication channels.
                    Pusher handles realtime display while RabbitMQ stores pending receiver queue notifications.
                </p>
            </div>
        </section>

        <div class="pusher-status" id="pusherStatus">
            <?php if ($pusherReady): ?>
                Realtime status: Connecting...
            <?php else: ?>
                Realtime status: Pusher not configured. Messages will still save, but realtime receiving is disabled.
            <?php endif; ?>
        </div>

        <?php if ($rabbitQueueCleared > 0): ?>
            <div class="rabbitmq-status">
                RabbitMQ status: <?php echo intval($rabbitQueueCleared); ?> pending private message queue notification(s) received and cleared.
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <section class="messaging-layout">
            <div>
                <div class="panel">
                    <div class="panel-header">
                        <h2>Conversations</h2>
                        <p>Recent private message threads.</p>
                    </div>

                    <div class="thread-list">
                        <?php if ($threads && $threads->num_rows > 0): ?>
                            <?php while ($thread = $threads->fetch_assoc()): ?>
                                <a href="messages.php?user=<?php echo $thread["user_id"]; ?>" class="thread-item <?php echo $selectedUserId === intval($thread["user_id"]) ? "active" : ""; ?>">
                                    <strong><?php echo htmlspecialchars($thread["full_name"]); ?></strong>
                                    <span><?php echo htmlspecialchars($thread["role"]); ?> • <?php echo htmlspecialchars($thread["email"]); ?></span>

                                    <?php if (intval($thread["unread_count"]) > 0): ?>
                                        <br>
                                        <span class="unread-pill">
                                            <?php echo intval($thread["unread_count"]); ?> unread
                                        </span>
                                    <?php endif; ?>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty">No conversations yet.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <br>

                <div class="contacts-panel">
                    <div class="panel-header">
                        <h2>Start New Message</h2>
                        <p>Select an allowed user to start a conversation.</p>
                    </div>

                    <div class="contact-list">
                        <?php if ($contacts && $contacts->num_rows > 0): ?>
                            <?php while ($contact = $contacts->fetch_assoc()): ?>
                                <a href="messages.php?user=<?php echo $contact["id"]; ?>" class="contact-item">
                                    <strong><?php echo htmlspecialchars($contact["full_name"]); ?></strong>
                                    <span>
                                        <?php echo htmlspecialchars($contact["role"]); ?> • 
                                        <?php echo htmlspecialchars($contact["email"]); ?>
                                    </span>

                                    <?php if ($contact["role"] === "student"): ?>
                                        <br>
                                        <span>
                                            <?php echo !empty($contact["program"]) ? htmlspecialchars($contact["program"]) : "No program"; ?>
                                        </span>
                                    <?php elseif ($contact["role"] === "faculty"): ?>
                                        <br>
                                        <span>
                                            <?php echo !empty($contact["department"]) ? htmlspecialchars($contact["department"]) : "No department"; ?>
                                        </span>
                                    <?php elseif ($contact["role"] === "informant"): ?>
                                        <br>
                                        <span>
                                            <?php echo !empty($contact["office_name"]) ? htmlspecialchars($contact["office_name"]) : "No office"; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty">No available contacts for your role.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="chat-panel">
                <?php if ($selectedUser): ?>
                    <div class="chat-header">
                        <h2><?php echo htmlspecialchars($selectedUser["full_name"]); ?></h2>
                        <p><?php echo htmlspecialchars($selectedUser["role"]); ?> • <?php echo htmlspecialchars($selectedUser["email"]); ?></p>
                    </div>

                    <div class="chat-body" id="chatBody">
                        <?php if ($conversation && $conversation->num_rows > 0): ?>
                            <?php while ($chat = $conversation->fetch_assoc()): ?>
                                <?php
                                    $isMine = intval($chat["sender_id"]) === intval($userId);
                                ?>

                                <div class="bubble-row <?php echo $isMine ? "mine" : "theirs"; ?>" data-message-id="<?php echo $chat["id"]; ?>">
                                    <div class="bubble">
                                        <?php echo nl2br(htmlspecialchars($chat["message"])); ?>

                                        <span class="bubble-time">
                                            <?php echo htmlspecialchars($chat["created_at"]); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty center" id="emptyChatMessage">
                                No messages yet. Start the conversation below.
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="messages.php?user=<?php echo $selectedUserId; ?>" class="chat-form">
                        <input type="hidden" name="receiver_id" value="<?php echo $selectedUserId; ?>">

                        <textarea name="message" placeholder="Type your private message here..." required></textarea>

                        <button type="submit" name="send_message" class="btn">
                            Send Message
                        </button>
                    </form>
                <?php else: ?>
                    <div class="chat-header">
                        <h2>Select a Conversation</h2>
                        <p>Choose a contact or conversation from the left panel.</p>
                    </div>

                    <div class="chat-body" id="chatBody">
                        <div class="empty center" id="emptyChatMessage">
                            No conversation selected.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<?php if ($pusherReady): ?>
<script>
    const currentUserId = <?php echo json_encode(intval($userId)); ?>;
    const selectedUserId = <?php echo json_encode(intval($selectedUserId)); ?>;
    const pusherKey = <?php echo json_encode($pusherAppKey); ?>;
    const pusherCluster = <?php echo json_encode($pusherAppCluster); ?>;

    const chatBody = document.getElementById("chatBody");
    const pusherStatus = document.getElementById("pusherStatus");

    function scrollChatToBottom() {
        if (chatBody) {
            chatBody.scrollTop = chatBody.scrollHeight;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    function appendMessage(data) {
        if (!chatBody) {
            return;
        }

        if (selectedUserId <= 0) {
            return;
        }

        const existing = document.querySelector('[data-message-id="' + data.id + '"]');

        if (existing) {
            return;
        }

        const emptyChatMessage = document.getElementById("emptyChatMessage");

        if (emptyChatMessage) {
            emptyChatMessage.remove();
        }

        const senderId = parseInt(data.sender_id);
        const receiverId = parseInt(data.receiver_id);

        const belongsToOpenConversation =
            (senderId === currentUserId && receiverId === selectedUserId) ||
            (senderId === selectedUserId && receiverId === currentUserId);

        if (!belongsToOpenConversation) {
            return;
        }

        const row = document.createElement("div");
        row.className = "bubble-row " + (senderId === currentUserId ? "mine" : "theirs");
        row.setAttribute("data-message-id", data.id);

        const bubble = document.createElement("div");
        bubble.className = "bubble";

        bubble.innerHTML =
            escapeHtml(data.message).replace(/\n/g, "<br>") +
            '<span class="bubble-time">' + escapeHtml(data.created_at) + '</span>';

        row.appendChild(bubble);
        chatBody.appendChild(row);

        scrollChatToBottom();
    }

    scrollChatToBottom();

    const pusher = new Pusher(pusherKey, {
        cluster: pusherCluster,
        channelAuthorization: {
            endpoint: "pusher_auth.php"
        }
    });

    pusher.connection.bind("connected", function() {
        if (pusherStatus) {
            pusherStatus.textContent = "Realtime status: Connected";
        }
    });

    pusher.connection.bind("error", function() {
        if (pusherStatus) {
            pusherStatus.textContent = "Realtime status: Connection error";
        }
    });

    pusher.connection.bind("disconnected", function() {
        if (pusherStatus) {
            pusherStatus.textContent = "Realtime status: Disconnected";
        }
    });

    const channel = pusher.subscribe("private-user-" + currentUserId);

    channel.bind("pusher:subscription_succeeded", function() {
        if (pusherStatus) {
            pusherStatus.textContent = "Realtime status: Connected and subscribed";
        }
    });

    channel.bind("pusher:subscription_error", function() {
        if (pusherStatus) {
            pusherStatus.textContent = "Realtime status: Subscription failed. Check pusher_auth.php.";
        }
    });

    channel.bind("new-message", function(data) {
        appendMessage(data);
    });
</script>
<?php else: ?>
<?php endif; ?>

</body>
</html>