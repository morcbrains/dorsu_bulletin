<?php
if (!class_exists("Notification")) {
    class Notification {
        private $conn;

        public function __construct($conn) {
            $this->conn = $conn;
        }

        public function createNotification($userId, $notificationType, $title, $message, $link = null) {
            $stmt = $this->conn->prepare("
                INSERT INTO notifications (
                    user_id,
                    notification_type,
                    title,
                    message,
                    link,
                    is_read
                ) VALUES (?, ?, ?, ?, ?, 0)
            ");

            $stmt->bind_param(
                "issss",
                $userId,
                $notificationType,
                $title,
                $message,
                $link
            );

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function createNotificationForUsers($userIds, $notificationType, $title, $message, $link = null) {
            if (empty($userIds) || !is_array($userIds)) {
                return false;
            }

            $successCount = 0;

            foreach ($userIds as $userId) {
                if ($this->createNotification($userId, $notificationType, $title, $message, $link)) {
                    $successCount++;
                }
            }

            return $successCount > 0;
        }

        public function createNotificationForRole($role, $notificationType, $title, $message, $link = null) {
            $stmt = $this->conn->prepare("
                SELECT id
                FROM users
                WHERE role = ?
                AND status = 'approved'
            ");

            $stmt->bind_param("s", $role);
            $stmt->execute();

            $result = $stmt->get_result();
            $userIds = [];

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $userIds[] = $row["id"];
                }
            }

            $stmt->close();

            return $this->createNotificationForUsers($userIds, $notificationType, $title, $message, $link);
        }

        public function createNotificationForTargetAudience($targetAudience, $notificationType, $title, $message, $link = null, $excludeUserId = null) {
            $roles = [];

            if ($targetAudience === "All Users") {
                $roles = ["admin", "informant", "faculty", "student"];
            } elseif ($targetAudience === "All Students") {
                $roles = ["student"];
            } elseif ($targetAudience === "All Faculty Members") {
                $roles = ["faculty"];
            } elseif ($targetAudience === "Students and Faculty") {
                $roles = ["student", "faculty"];
            } elseif ($targetAudience === "University Office Informants") {
                $roles = ["informant"];
            }

            if (empty($roles)) {
                return false;
            }

            $placeholders = implode(",", array_fill(0, count($roles), "?"));
            $types = str_repeat("s", count($roles));

            $sql = "
                SELECT id
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

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            $result = $stmt->get_result();
            $userIds = [];

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $userIds[] = $row["id"];
                }
            }

            $stmt->close();

            return $this->createNotificationForUsers($userIds, $notificationType, $title, $message, $link);
        }

        public function createNotificationForFacultyProgramStudents($facultyName, $programName, $notificationType, $title, $message, $link = null) {
            if (empty($facultyName) || empty($programName)) {
                return false;
            }

            if ($programName === "All Programs") {
                $stmt = $this->conn->prepare("
                    SELECT id
                    FROM users
                    WHERE role = 'student'
                    AND status = 'approved'
                    AND department = ?
                ");

                $stmt->bind_param("s", $facultyName);
            } else {
                $stmt = $this->conn->prepare("
                    SELECT id
                    FROM users
                    WHERE role = 'student'
                    AND status = 'approved'
                    AND department = ?
                    AND program = ?
                ");

                $stmt->bind_param("ss", $facultyName, $programName);
            }

            $stmt->execute();

            $result = $stmt->get_result();
            $userIds = [];

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $userIds[] = $row["id"];
                }
            }

            $stmt->close();

            return $this->createNotificationForUsers($userIds, $notificationType, $title, $message, $link);
        }

        public function getUnreadCount($userId) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM notifications
                WHERE user_id = ?
                AND is_read = 0
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $total = 0;

            if ($result) {
                $row = $result->fetch_assoc();
                $total = $row["total"];
            }

            $stmt->close();

            return $total;
        }

        public function getUserNotifications($userId, $limit = 10) {
            $stmt = $this->conn->prepare("
                SELECT
                    id,
                    user_id,
                    notification_type,
                    title,
                    message,
                    link,
                    is_read,
                    created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");

            $stmt->bind_param("ii", $userId, $limit);
            $stmt->execute();

            return $stmt->get_result();
        }

        public function markAsRead($notificationId, $userId) {
            $stmt = $this->conn->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = ?
                AND user_id = ?
            ");

            $stmt->bind_param("ii", $notificationId, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function markAllAsRead($userId) {
            $stmt = $this->conn->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE user_id = ?
            ");

            $stmt->bind_param("i", $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function getNotificationById($notificationId, $userId) {
            $stmt = $this->conn->prepare("
                SELECT
                    id,
                    user_id,
                    notification_type,
                    title,
                    message,
                    link,
                    is_read,
                    created_at
                FROM notifications
                WHERE id = ?
                AND user_id = ?
                LIMIT 1
            ");

            $stmt->bind_param("ii", $notificationId, $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $notification = null;

            if ($result && $result->num_rows > 0) {
                $notification = $result->fetch_assoc();
            }

            $stmt->close();

            return $notification;
        }

        public function deleteNotification($notificationId, $userId) {
            $stmt = $this->conn->prepare("
                DELETE FROM notifications
                WHERE id = ?
                AND user_id = ?
            ");

            $stmt->bind_param("ii", $notificationId, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function deleteAllNotifications($userId) {
            $stmt = $this->conn->prepare("
                DELETE FROM notifications
                WHERE user_id = ?
            ");

            $stmt->bind_param("i", $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }
    }
}
?>