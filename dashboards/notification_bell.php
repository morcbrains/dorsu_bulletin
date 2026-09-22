<?php
if (!isset($notificationObj)) {
    require_once "../classes/Notification.php";
    $notificationObj = new Notification($conn);
}

$currentUserId = $_SESSION["user_id"];
$notificationCount = $notificationObj->getUnreadCount($currentUserId);
$userNotifications = $notificationObj->getUserNotifications($currentUserId, 10);
?>

<style>
    .notification-wrapper {
        position: fixed;
        top: 25px;
        right: 30px;
        z-index: 999;
    }

    .notification-bell {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        background: white;
        border: 1px solid rgba(246, 196, 0, 0.65);
        box-shadow: 0 10px 28px rgba(0, 43, 127, 0.14);
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        position: relative;
        transition: 0.25s;
        color: var(--dorsu-blue-dark, #002b7f);
        font-size: 23px;
    }

    .notification-bell:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 32px rgba(0, 43, 127, 0.20);
        background: #fff7cc;
    }

    .notification-count {
        position: absolute;
        top: -7px;
        right: -7px;
        min-width: 23px;
        height: 23px;
        padding: 0 6px;
        border-radius: 30px;
        background: #c40000;
        color: white;
        font-size: 12px;
        font-weight: bold;
        display: flex;
        justify-content: center;
        align-items: center;
        border: 2px solid white;
    }

    .notification-dropdown {
        width: 360px;
        max-height: 460px;
        overflow-y: auto;
        background: white;
        position: absolute;
        top: 65px;
        right: 0;
        border-radius: 22px;
        box-shadow: 0 22px 55px rgba(0, 43, 127, 0.22);
        border: 1px solid #e3eaf8;
        display: none;
        overflow-x: hidden;
    }

    .notification-dropdown.active {
        display: block;
        animation: notifDrop 0.22s ease;
    }

    @keyframes notifDrop {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-header {
        padding: 17px 18px;
        background: linear-gradient(135deg, var(--dorsu-blue-dark, #002b7f), var(--dorsu-blue, #003fae));
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-header h3 {
        font-size: 16px;
        margin: 0;
    }

    .notification-header span {
        font-size: 12px;
        background: rgba(255, 247, 204, 0.22);
        color: #fff7cc;
        padding: 6px 10px;
        border-radius: 20px;
        font-weight: bold;
    }

    .notification-list {
        display: flex;
        flex-direction: column;
    }

    .notification-item {
        text-decoration: none;
        color: var(--dorsu-text, #1f2937);
        padding: 16px 18px;
        border-bottom: 1px solid #edf2fb;
        display: block;
        transition: 0.22s;
        position: relative;
    }

    .notification-item:hover {
        background: #f4f7ff;
    }

    .notification-item.unread {
        background: linear-gradient(90deg, #fff7cc, #ffffff);
        border-left: 6px solid var(--dorsu-gold, #f6c400);
    }

    .notification-item.read {
        background: white;
        border-left: 6px solid transparent;
    }

    .notification-title-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 6px;
    }

    .notification-title {
        font-size: 14px;
        font-weight: bold;
        color: var(--dorsu-blue-dark, #002b7f);
        line-height: 1.4;
    }

    .notification-item.read .notification-title {
        color: #374151;
        font-weight: normal;
    }

    .new-badge {
        background: #c40000;
        color: white;
        font-size: 10px;
        padding: 4px 7px;
        border-radius: 20px;
        font-weight: bold;
        flex-shrink: 0;
    }

    .notification-message {
        font-size: 13px;
        color: var(--dorsu-muted, #5b6472);
        line-height: 1.5;
        margin-bottom: 7px;
    }

    .notification-time {
        font-size: 11px;
        color: #7b8494;
    }

    .notification-empty {
        padding: 22px;
        text-align: center;
        color: var(--dorsu-muted, #5b6472);
        font-size: 14px;
        line-height: 1.5;
    }

    @media (max-width: 760px) {
        .notification-wrapper {
            top: 12px;
            right: 18px;
        }

        .notification-bell {
            width: 48px;
            height: 48px;
        }

        .notification-dropdown {
            width: calc(100vw - 36px);
            right: 0;
            top: 58px;
        }
    }
</style>

<div class="notification-wrapper">
    <div class="notification-bell" onclick="toggleNotificationDropdown()">
        🔔

        <?php if ($notificationCount > 0): ?>
            <span class="notification-count">
                <?php echo $notificationCount > 99 ? "99+" : $notificationCount; ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <span><?php echo $notificationCount; ?> new</span>
        </div>

        <div class="notification-list">
            <?php if ($userNotifications && $userNotifications->num_rows > 0): ?>
                <?php while ($notification = $userNotifications->fetch_assoc()): ?>
                    <a
                        href="mark_notification_read.php?id=<?php echo $notification["id"]; ?>"
                        class="notification-item <?php echo intval($notification["is_read"]) === 0 ? "unread" : "read"; ?>"
                    >
                        <div class="notification-title-row">
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notification["title"]); ?>
                            </div>

                            <?php if (intval($notification["is_read"]) === 0): ?>
                                <span class="new-badge">NEW</span>
                            <?php endif; ?>
                        </div>

                        <div class="notification-message">
                            <?php echo htmlspecialchars($notification["message"]); ?>
                        </div>

                        <div class="notification-time">
                            <?php echo date("M d, Y h:i A", strtotime($notification["created_at"])); ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="notification-empty">
                    No notifications yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleNotificationDropdown() {
    const dropdown = document.getElementById("notificationDropdown");
    dropdown.classList.toggle("active");
}

document.addEventListener("click", function(event) {
    const wrapper = document.querySelector(".notification-wrapper");

    if (wrapper && !wrapper.contains(event.target)) {
        const dropdown = document.getElementById("notificationDropdown");

        if (dropdown) {
            dropdown.classList.remove("active");
        }
    }
});
</script>