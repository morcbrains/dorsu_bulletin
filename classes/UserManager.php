<?php
if (!class_exists("UserManager")) {
    class UserManager {
        private $conn;

        public function __construct($conn) {
            $this->conn = $conn;
        }

        public function createUser($fullName, $email, $password, $role, $studentId = null, $facultyId = null, $officeName = null, $program = null, $yearLevel = null, $section = null, $department = null, $status = "approved") {
            if ($this->emailExists($email)) {
                return [
                    "success" => false,
                    "message" => "Email is already registered."
                ];
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->conn->prepare("
                INSERT INTO users (
                    full_name,
                    email,
                    password,
                    role,
                    student_id,
                    faculty_id,
                    office_name,
                    program,
                    year_level,
                    section,
                    department,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssssssssssss",
                $fullName,
                $email,
                $hashedPassword,
                $role,
                $studentId,
                $facultyId,
                $officeName,
                $program,
                $yearLevel,
                $section,
                $department,
                $status
            );

            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                return [
                    "success" => true,
                    "message" => "User account created successfully."
                ];
            }

            return [
                "success" => false,
                "message" => "Failed to create user account."
            ];
        }

        public function getAllUsers() {
            return $this->conn->query("
                SELECT
                    id,
                    full_name,
                    email,
                    role,
                    student_id,
                    faculty_id,
                    office_name,
                    program,
                    year_level,
                    section,
                    department,
                    status,
                    created_at
                FROM users
                ORDER BY created_at DESC
            ");
        }

        public function getUserById($userId) {
            $stmt = $this->conn->prepare("
                SELECT
                    id,
                    full_name,
                    email,
                    role,
                    student_id,
                    faculty_id,
                    office_name,
                    program,
                    year_level,
                    section,
                    department,
                    status,
                    created_at
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

        public function updateUser($userId, $fullName, $email, $role, $studentId = null, $facultyId = null, $officeName = null, $program = null, $yearLevel = null, $section = null, $department = null, $status = "pending") {
            if ($this->emailExistsExceptUser($email, $userId)) {
                return [
                    "success" => false,
                    "message" => "Email is already used by another account."
                ];
            }

            $stmt = $this->conn->prepare("
                UPDATE users
                SET
                    full_name = ?,
                    email = ?,
                    role = ?,
                    student_id = ?,
                    faculty_id = ?,
                    office_name = ?,
                    program = ?,
                    year_level = ?,
                    section = ?,
                    department = ?,
                    status = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "sssssssssssi",
                $fullName,
                $email,
                $role,
                $studentId,
                $facultyId,
                $officeName,
                $program,
                $yearLevel,
                $section,
                $department,
                $status,
                $userId
            );

            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                return [
                    "success" => true,
                    "message" => "User account updated successfully."
                ];
            }

            return [
                "success" => false,
                "message" => "Failed to update user account."
            ];
        }

        public function updateUserPassword($userId, $newPassword) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $this->conn->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            $stmt->bind_param("si", $hashedPassword, $userId);

            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                return [
                    "success" => true,
                    "message" => "User password updated successfully."
                ];
            }

            return [
                "success" => false,
                "message" => "Failed to update user password."
            ];
        }

        public function updateUserStatus($userId, $status) {
            if (!in_array($status, ["pending", "approved", "rejected"])) {
                return false;
            }

            $stmt = $this->conn->prepare("
                UPDATE users
                SET status = ?
                WHERE id = ?
            ");

            $stmt->bind_param("si", $status, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function deleteUser($userId) {
            try {
                $this->conn->begin_transaction();

                $this->deleteFromTableIfExists("notifications", "user_id", $userId);
                $this->deleteFromTableIfExists("announcement_reads", "user_id", $userId);
                $this->deleteFromTableIfExists("saved_announcements", "user_id", $userId);
                $this->deletePrivateMessages($userId);

                $stmt = $this->conn->prepare("
                    DELETE FROM users
                    WHERE id = ?
                ");

                $stmt->bind_param("i", $userId);
                $success = $stmt->execute();
                $stmt->close();

                if ($success) {
                    $this->conn->commit();

                    return [
                        "success" => true,
                        "message" => "User account deleted successfully."
                    ];
                }

                $this->conn->rollback();

                return [
                    "success" => false,
                    "message" => "Failed to delete user account."
                ];
            } catch (Exception $e) {
                $this->conn->rollback();

                return [
                    "success" => false,
                    "message" => "Failed to delete user account. This account may still be connected to announcements, events, or other records."
                ];
            }
        }

        public function getPendingUsers() {
            $stmt = $this->conn->prepare("
                SELECT
                    id,
                    full_name,
                    email,
                    role,
                    student_id,
                    faculty_id,
                    office_name,
                    program,
                    year_level,
                    section,
                    department,
                    status,
                    created_at
                FROM users
                WHERE status = 'pending'
                ORDER BY created_at DESC
            ");

            $stmt->execute();

            return $stmt->get_result();
        }

        public function searchUsers($keyword) {
            $search = "%" . $keyword . "%";

            $stmt = $this->conn->prepare("
                SELECT
                    id,
                    full_name,
                    email,
                    role,
                    student_id,
                    faculty_id,
                    office_name,
                    program,
                    year_level,
                    section,
                    department,
                    status,
                    created_at
                FROM users
                WHERE full_name LIKE ?
                   OR email LIKE ?
                   OR role LIKE ?
                   OR office_name LIKE ?
                   OR program LIKE ?
                   OR department LIKE ?
                   OR status LIKE ?
                ORDER BY created_at DESC
            ");

            $stmt->bind_param(
                "sssssss",
                $search,
                $search,
                $search,
                $search,
                $search,
                $search,
                $search
            );

            $stmt->execute();

            return $stmt->get_result();
        }

        public function emailExists($email) {
            $stmt = $this->conn->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $exists = $result && $result->num_rows > 0;

            $stmt->close();

            return $exists;
        }

        public function emailExistsExceptUser($email, $userId) {
            $stmt = $this->conn->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                AND id != ?
                LIMIT 1
            ");

            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $exists = $result && $result->num_rows > 0;

            $stmt->close();

            return $exists;
        }

        public function getRoleDisplayName($role) {
            if ($role === "admin") {
                return "Admin";
            }

            if ($role === "informant") {
                return "University Office Informant";
            }

            if ($role === "faculty") {
                return "Faculty Member";
            }

            if ($role === "student") {
                return "Student";
            }

            return ucfirst($role);
        }

        public function getStatusBadgeClass($status) {
            if ($status === "approved") {
                return "approved";
            }

            if ($status === "pending") {
                return "pending";
            }

            if ($status === "rejected") {
                return "rejected";
            }

            return "pending";
        }

        private function deleteFromTableIfExists($tableName, $columnName, $userId) {
            if (!$this->tableExists($tableName)) {
                return true;
            }

            if (!$this->columnExists($tableName, $columnName)) {
                return true;
            }

            $sql = "DELETE FROM " . $tableName . " WHERE " . $columnName . " = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            return true;
        }

        private function deletePrivateMessages($userId) {
            if (!$this->tableExists("private_messages")) {
                return true;
            }

            $stmt = $this->conn->prepare("
                DELETE FROM private_messages
                WHERE sender_id = ? OR receiver_id = ?
            ");

            $stmt->bind_param("ii", $userId, $userId);
            $stmt->execute();
            $stmt->close();

            return true;
        }

        private function tableExists($tableName) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = ?
            ");

            $stmt->bind_param("s", $tableName);
            $stmt->execute();

            $result = $stmt->get_result();
            $exists = false;

            if ($result) {
                $exists = intval($result->fetch_assoc()["total"]) > 0;
            }

            $stmt->close();

            return $exists;
        }

        private function columnExists($tableName, $columnName) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                AND table_name = ?
                AND column_name = ?
            ");

            $stmt->bind_param("ss", $tableName, $columnName);
            $stmt->execute();

            $result = $stmt->get_result();
            $exists = false;

            if ($result) {
                $exists = intval($result->fetch_assoc()["total"]) > 0;
            }

            $stmt->close();

            return $exists;
        }
    }
}
?>
