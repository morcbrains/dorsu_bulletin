<?php
if (!class_exists("PrivateMessage")) {
    class PrivateMessage {
        private $conn;

        public function __construct($conn) {
            $this->conn = $conn;
        }

        public function getAllowedRoles($currentRole) {
            if ($currentRole === "informant") {
                return ["informant", "faculty"];
            }

            if ($currentRole === "faculty") {
                return ["informant", "faculty", "student"];
            }

            if ($currentRole === "student") {
                return ["faculty", "student"];
            }

            return [];
        }

        public function canMessageRole($senderRole, $receiverRole) {
            $allowedRoles = $this->getAllowedRoles($senderRole);

            return in_array($receiverRole, $allowedRoles);
        }

        public function getAvailableContacts($currentUserId, $currentRole) {
            $allowedRoles = $this->getAllowedRoles($currentRole);

            if (empty($allowedRoles)) {
                return false;
            }

            $placeholders = implode(",", array_fill(0, count($allowedRoles), "?"));
            $types = str_repeat("s", count($allowedRoles));
            $types .= "i";

            $sql = "
                SELECT 
                    id,
                    full_name,
                    email,
                    role,
                    status,
                    student_id,
                    faculty_id,
                    program,
                    department,
                    office_name
                FROM users
                WHERE role IN ($placeholders)
                AND id != ?
                AND status = 'approved'
                ORDER BY role ASC, full_name ASC
            ";

            $stmt = $this->conn->prepare($sql);

            $params = $allowedRoles;
            $params[] = $currentUserId;

            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            return $stmt->get_result();
        }

        public function getUserById($userId) {
            $stmt = $this->conn->prepare("
                SELECT 
                    id,
                    full_name,
                    email,
                    role,
                    status
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = null;

            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
            }

            $stmt->close();

            return $user;
        }

        public function sendMessage($senderId, $receiverId, $message) {
            $stmt = $this->conn->prepare("
                INSERT INTO private_messages (
                    sender_id,
                    receiver_id,
                    message
                ) VALUES (?, ?, ?)
            ");

            $stmt->bind_param("iis", $senderId, $receiverId, $message);

            $success = $stmt->execute();
            $messageId = $stmt->insert_id;

            $stmt->close();

            if ($success) {
                return $messageId;
            }

            return false;
        }

        public function getMessageById($messageId) {
            $stmt = $this->conn->prepare("
                SELECT 
                    private_messages.id,
                    private_messages.sender_id,
                    private_messages.receiver_id,
                    private_messages.message,
                    private_messages.is_read,
                    private_messages.created_at,
                    sender.full_name AS sender_name,
                    sender.role AS sender_role,
                    receiver.full_name AS receiver_name,
                    receiver.role AS receiver_role
                FROM private_messages
                INNER JOIN users AS sender ON private_messages.sender_id = sender.id
                INNER JOIN users AS receiver ON private_messages.receiver_id = receiver.id
                WHERE private_messages.id = ?
                LIMIT 1
            ");

            $stmt->bind_param("i", $messageId);
            $stmt->execute();

            $result = $stmt->get_result();
            $message = null;

            if ($result && $result->num_rows > 0) {
                $message = $result->fetch_assoc();
            }

            $stmt->close();

            return $message;
        }

        public function getConversation($currentUserId, $otherUserId) {
            $stmt = $this->conn->prepare("
                SELECT 
                    private_messages.id,
                    private_messages.sender_id,
                    private_messages.receiver_id,
                    private_messages.message,
                    private_messages.is_read,
                    private_messages.created_at,
                    sender.full_name AS sender_name,
                    sender.role AS sender_role,
                    receiver.full_name AS receiver_name,
                    receiver.role AS receiver_role
                FROM private_messages
                INNER JOIN users AS sender ON private_messages.sender_id = sender.id
                INNER JOIN users AS receiver ON private_messages.receiver_id = receiver.id
                WHERE 
                    (private_messages.sender_id = ? AND private_messages.receiver_id = ?)
                    OR
                    (private_messages.sender_id = ? AND private_messages.receiver_id = ?)
                ORDER BY private_messages.created_at ASC
            ");

            $stmt->bind_param("iiii", $currentUserId, $otherUserId, $otherUserId, $currentUserId);
            $stmt->execute();

            return $stmt->get_result();
        }

        public function markConversationAsRead($currentUserId, $otherUserId) {
            $stmt = $this->conn->prepare("
                UPDATE private_messages
                SET is_read = 1
                WHERE receiver_id = ?
                AND sender_id = ?
            ");

            $stmt->bind_param("ii", $currentUserId, $otherUserId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function getInboxThreads($currentUserId) {
            $stmt = $this->conn->prepare("
                SELECT 
                    users.id AS user_id,
                    users.full_name,
                    users.email,
                    users.role,
                    MAX(private_messages.created_at) AS last_message_time,
                    SUM(
                        CASE 
                            WHEN private_messages.receiver_id = ? 
                            AND private_messages.sender_id = users.id 
                            AND private_messages.is_read = 0 
                            THEN 1 
                            ELSE 0 
                        END
                    ) AS unread_count
                FROM users
                INNER JOIN private_messages 
                    ON (
                        private_messages.sender_id = users.id 
                        AND private_messages.receiver_id = ?
                    )
                    OR (
                        private_messages.receiver_id = users.id 
                        AND private_messages.sender_id = ?
                    )
                WHERE users.id != ?
                GROUP BY users.id, users.full_name, users.email, users.role
                ORDER BY last_message_time DESC
            ");

            $stmt->bind_param("iiii", $currentUserId, $currentUserId, $currentUserId, $currentUserId);
            $stmt->execute();

            return $stmt->get_result();
        }

        public function getTotalUnreadMessages($currentUserId) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM private_messages
                WHERE receiver_id = ?
                AND is_read = 0
            ");

            $stmt->bind_param("i", $currentUserId);
            $stmt->execute();

            $result = $stmt->get_result();
            $total = 0;

            if ($result) {
                $total = $result->fetch_assoc()["total"];
            }

            $stmt->close();

            return $total;
        }
    }
}
?>