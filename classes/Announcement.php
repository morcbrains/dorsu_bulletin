<?php
if (!class_exists("Announcement")) {
    class Announcement {
        private $conn;

        public function __construct($conn) {
            $this->conn = $conn;
        }

        public function getActiveCategories() {
            $result = $this->conn->query("
                SELECT category_name
                FROM categories
                WHERE status = 'active'
                ORDER BY category_name ASC
            ");

            return $result;
        }

        public function createAnnouncement($title, $description, $category, $targetAudience, $urgency, $status, $postedBy, $announcementType = "faculty", $announcementDate = null, $announcementTime = null, $facultyName = null, $programName = null) {
            $stmt = $this->conn->prepare("
                INSERT INTO announcements (
                    announcement_type,
                    title,
                    description,
                    announcement_date,
                    announcement_time,
                    faculty_name,
                    program_name,
                    category,
                    target_audience,
                    urgency,
                    status,
                    posted_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sssssssssssi",
                $announcementType,
                $title,
                $description,
                $announcementDate,
                $announcementTime,
                $facultyName,
                $programName,
                $category,
                $targetAudience,
                $urgency,
                $status,
                $postedBy
            );

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function getMyAnnouncements($userId) {
            $stmt = $this->conn->prepare("
                SELECT 
                    id,
                    announcement_type,
                    title,
                    description,
                    announcement_date,
                    announcement_time,
                    faculty_name,
                    program_name,
                    category,
                    target_audience,
                    urgency,
                    status,
                    created_at,
                    updated_at
                FROM announcements
                WHERE posted_by = ?
                ORDER BY created_at DESC
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            return $stmt->get_result();
        }

        public function getTotalMyAnnouncements($userId) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM announcements
                WHERE posted_by = ?
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $total = 0;

            if ($result) {
                $total = $result->fetch_assoc()["total"];
            }

            $stmt->close();

            return $total;
        }

        public function getTotalMyAnnouncementsByStatus($userId, $status) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM announcements
                WHERE posted_by = ? AND status = ?
            ");

            $stmt->bind_param("is", $userId, $status);
            $stmt->execute();

            $result = $stmt->get_result();
            $total = 0;

            if ($result) {
                $total = $result->fetch_assoc()["total"];
            }

            $stmt->close();

            return $total;
        }

        public function getTotalMyUrgentAnnouncements($userId) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM announcements
                WHERE posted_by = ? AND urgency = 'urgent'
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $total = 0;

            if ($result) {
                $total = $result->fetch_assoc()["total"];
            }

            $stmt->close();

            return $total;
        }

        public function updateMyAnnouncementStatus($announcementId, $userId, $status) {
            $stmt = $this->conn->prepare("
                UPDATE announcements
                SET status = ?
                WHERE id = ? AND posted_by = ?
            ");

            $stmt->bind_param("sii", $status, $announcementId, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function deleteMyAnnouncement($announcementId, $userId) {
            $stmt = $this->conn->prepare("
                DELETE FROM announcements
                WHERE id = ? AND posted_by = ?
            ");

            $stmt->bind_param("ii", $announcementId, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function getStudentAnnouncements($category = "", $urgency = "", $facultyName = "", $programName = "") {
            $sql = "
                SELECT 
                    announcements.id,
                    announcements.announcement_type,
                    announcements.title,
                    announcements.description,
                    announcements.announcement_date,
                    announcements.announcement_time,
                    announcements.faculty_name,
                    announcements.program_name,
                    announcements.category,
                    announcements.target_audience,
                    announcements.urgency,
                    announcements.status,
                    announcements.created_at,
                    announcements.updated_at,
                    users.full_name AS posted_by_name,
                    users.role AS posted_by_role
                FROM announcements
                INNER JOIN users ON announcements.posted_by = users.id
                WHERE announcements.status = 'published'
                AND (
                    announcements.target_audience = 'All Users'
                    OR announcements.target_audience = 'All Students'
                    OR announcements.target_audience = 'Students and Faculty'
                )
            ";

            $params = [];
            $types = "";

            if (!empty($category)) {
                $sql .= " AND announcements.category = ?";
                $params[] = $category;
                $types .= "s";
            }

            if (!empty($urgency)) {
                $sql .= " AND announcements.urgency = ?";
                $params[] = $urgency;
                $types .= "s";
            }

            if (!empty($facultyName)) {
                $sql .= "
                    AND (
                        announcements.announcement_type != 'faculty'
                        OR announcements.faculty_name = ?
                    )
                ";
                $params[] = $facultyName;
                $types .= "s";
            }

            if (!empty($programName)) {
                $sql .= "
                    AND (
                        announcements.announcement_type != 'faculty'
                        OR announcements.program_name = 'All Programs'
                        OR announcements.program_name = ?
                    )
                ";
                $params[] = $programName;
                $types .= "s";
            }

            $sql .= "
                ORDER BY 
                CASE WHEN announcements.urgency = 'urgent' THEN 1 ELSE 2 END,
                announcements.created_at DESC
            ";

            $stmt = $this->conn->prepare($sql);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();

            return $stmt->get_result();
        }

        public function getTotalStudentAnnouncements($category = "", $urgency = "", $facultyName = "", $programName = "") {
            $sql = "
                SELECT COUNT(*) AS total
                FROM announcements
                WHERE status = 'published'
                AND (
                    target_audience = 'All Users'
                    OR target_audience = 'All Students'
                    OR target_audience = 'Students and Faculty'
                )
            ";

            $params = [];
            $types = "";

            if (!empty($category)) {
                $sql .= " AND category = ?";
                $params[] = $category;
                $types .= "s";
            }

            if (!empty($urgency)) {
                $sql .= " AND urgency = ?";
                $params[] = $urgency;
                $types .= "s";
            }

            if (!empty($facultyName)) {
                $sql .= "
                    AND (
                        announcement_type != 'faculty'
                        OR faculty_name = ?
                    )
                ";
                $params[] = $facultyName;
                $types .= "s";
            }

            if (!empty($programName)) {
                $sql .= "
                    AND (
                        announcement_type != 'faculty'
                        OR program_name = 'All Programs'
                        OR program_name = ?
                    )
                ";
                $params[] = $programName;
                $types .= "s";
            }

            $stmt = $this->conn->prepare($sql);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result) {
                return $result->fetch_assoc()["total"];
            }

            return 0;
        }

        public function getTotalStudentUrgentAnnouncements($facultyName = "", $programName = "") {
            $sql = "
                SELECT COUNT(*) AS total
                FROM announcements
                WHERE status = 'published'
                AND urgency = 'urgent'
                AND (
                    target_audience = 'All Users'
                    OR target_audience = 'All Students'
                    OR target_audience = 'Students and Faculty'
                )
            ";

            $params = [];
            $types = "";

            if (!empty($facultyName)) {
                $sql .= "
                    AND (
                        announcement_type != 'faculty'
                        OR faculty_name = ?
                    )
                ";
                $params[] = $facultyName;
                $types .= "s";
            }

            if (!empty($programName)) {
                $sql .= "
                    AND (
                        announcement_type != 'faculty'
                        OR program_name = 'All Programs'
                        OR program_name = ?
                    )
                ";
                $params[] = $programName;
                $types .= "s";
            }

            $stmt = $this->conn->prepare($sql);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result) {
                return $result->fetch_assoc()["total"];
            }

            return 0;
        }

        public function getFacultyAnnouncements($category = "", $urgency = "") {
            $sql = "
                SELECT 
                    announcements.id,
                    announcements.announcement_type,
                    announcements.title,
                    announcements.description,
                    announcements.announcement_date,
                    announcements.announcement_time,
                    announcements.faculty_name,
                    announcements.program_name,
                    announcements.category,
                    announcements.target_audience,
                    announcements.urgency,
                    announcements.status,
                    announcements.created_at,
                    announcements.updated_at,
                    users.full_name AS posted_by_name,
                    users.role AS posted_by_role,
                    users.office_name AS posted_by_office
                FROM announcements
                INNER JOIN users ON announcements.posted_by = users.id
                WHERE announcements.status = 'published'
                AND users.role = 'informant'
                AND (
                    announcements.target_audience = 'All Users'
                    OR announcements.target_audience = 'All Faculty Members'
                    OR announcements.target_audience = 'Students and Faculty'
                )
            ";

            $params = [];
            $types = "";

            if (!empty($category)) {
                $sql .= " AND announcements.category = ?";
                $params[] = $category;
                $types .= "s";
            }

            if (!empty($urgency)) {
                $sql .= " AND announcements.urgency = ?";
                $params[] = $urgency;
                $types .= "s";
            }

            $sql .= "
                ORDER BY 
                CASE WHEN announcements.urgency = 'urgent' THEN 1 ELSE 2 END,
                announcements.created_at DESC
            ";

            $stmt = $this->conn->prepare($sql);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();

            return $stmt->get_result();
        }

        public function getTotalFacultyAnnouncements($category = "", $urgency = "") {
            $sql = "
                SELECT COUNT(*) AS total
                FROM announcements
                INNER JOIN users ON announcements.posted_by = users.id
                WHERE announcements.status = 'published'
                AND users.role = 'informant'
                AND (
                    announcements.target_audience = 'All Users'
                    OR announcements.target_audience = 'All Faculty Members'
                    OR announcements.target_audience = 'Students and Faculty'
                )
            ";

            $params = [];
            $types = "";

            if (!empty($category)) {
                $sql .= " AND announcements.category = ?";
                $params[] = $category;
                $types .= "s";
            }

            if (!empty($urgency)) {
                $sql .= " AND announcements.urgency = ?";
                $params[] = $urgency;
                $types .= "s";
            }

            $stmt = $this->conn->prepare($sql);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result) {
                return $result->fetch_assoc()["total"];
            }

            return 0;
        }

        public function getTotalFacultyUrgentAnnouncements() {
            $sql = "
                SELECT COUNT(*) AS total
                FROM announcements
                INNER JOIN users ON announcements.posted_by = users.id
                WHERE announcements.status = 'published'
                AND announcements.urgency = 'urgent'
                AND users.role = 'informant'
                AND (
                    announcements.target_audience = 'All Users'
                    OR announcements.target_audience = 'All Faculty Members'
                    OR announcements.target_audience = 'Students and Faculty'
                )
            ";

            $result = $this->conn->query($sql);

            if ($result) {
                return $result->fetch_assoc()["total"];
            }

            return 0;
        }

        public function markAsRead($announcementId, $userId) {
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO announcement_reads (announcement_id, user_id)
                VALUES (?, ?)
            ");

            $stmt->bind_param("ii", $announcementId, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function saveAnnouncement($announcementId, $userId) {
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO saved_announcements (announcement_id, user_id)
                VALUES (?, ?)
            ");

            $stmt->bind_param("ii", $announcementId, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function unsaveAnnouncement($announcementId, $userId) {
            $stmt = $this->conn->prepare("
                DELETE FROM saved_announcements
                WHERE announcement_id = ? AND user_id = ?
            ");

            $stmt->bind_param("ii", $announcementId, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function isRead($announcementId, $userId) {
            $stmt = $this->conn->prepare("
                SELECT id
                FROM announcement_reads
                WHERE announcement_id = ? AND user_id = ?
            ");

            $stmt->bind_param("ii", $announcementId, $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $isRead = $result->num_rows > 0;

            $stmt->close();

            return $isRead;
        }

        public function isSaved($announcementId, $userId) {
            $stmt = $this->conn->prepare("
                SELECT id
                FROM saved_announcements
                WHERE announcement_id = ? AND user_id = ?
            ");

            $stmt->bind_param("ii", $announcementId, $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $isSaved = $result->num_rows > 0;

            $stmt->close();

            return $isSaved;
        }

        public function getSavedAnnouncements($userId) {
            $stmt = $this->conn->prepare("
                SELECT 
                    announcements.id,
                    announcements.announcement_type,
                    announcements.title,
                    announcements.description,
                    announcements.announcement_date,
                    announcements.announcement_time,
                    announcements.faculty_name,
                    announcements.program_name,
                    announcements.category,
                    announcements.target_audience,
                    announcements.urgency,
                    announcements.status,
                    announcements.created_at,
                    saved_announcements.saved_at,
                    users.full_name AS posted_by_name,
                    users.role AS posted_by_role
                FROM saved_announcements
                INNER JOIN announcements ON saved_announcements.announcement_id = announcements.id
                INNER JOIN users ON announcements.posted_by = users.id
                WHERE saved_announcements.user_id = ?
                ORDER BY saved_announcements.saved_at DESC
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            return $stmt->get_result();
        }

        public function getTotalSavedAnnouncements($userId) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM saved_announcements
                WHERE user_id = ?
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $total = 0;

            if ($result) {
                $total = $result->fetch_assoc()["total"];
            }

            $stmt->close();

            return $total;
        }

        public function getTotalReadAnnouncements($userId) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM announcement_reads
                WHERE user_id = ?
            ");

            $stmt->bind_param("i", $userId);
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