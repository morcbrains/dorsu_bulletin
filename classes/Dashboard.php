<?php
class Dashboard {
    private $conn;

    public function __construct($conn = null) {
        $this->conn = $conn;
    }

    public function getRoleTitle($role) {
        if ($role === "admin") {
            return "Admin Area";
        } elseif ($role === "informant") {
            return "University Informant Area";
        } elseif ($role === "faculty") {
            return "Faculty Member Area";
        } elseif ($role === "student") {
            return "Student Area";
        }

        return "User Area";
    }

    public function getWelcomeMessage($role) {
        if ($role === "admin") {
            return "Manage users, approve accounts, monitor announcements, and supervise the whole DOrSU Bulletin system.";
        } elseif ($role === "informant") {
            return "Post official university announcements, events, activities, and important updates for the DOrSU community.";
        } elseif ($role === "faculty") {
            return "Create academic reminders, department updates, class announcements, and program-related posts.";
        } elseif ($role === "student") {
            return "View official announcements, events, activities, reminders, and important DOrSU updates.";
        }

        return "Welcome to DOrSU Bulletin.";
    }

    public function getTotalUsers() {
        if (!$this->conn) {
            return 0;
        }

        $result = $this->conn->query("SELECT COUNT(*) AS total FROM users");

        if ($result) {
            return $result->fetch_assoc()["total"];
        }

        return 0;
    }

    public function getTotalByStatus($status) {
        if (!$this->conn) {
            return 0;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM users WHERE status = ?");
        $stmt->bind_param("s", $status);
        $stmt->execute();

        $result = $stmt->get_result();
        $total = 0;

        if ($result) {
            $total = $result->fetch_assoc()["total"];
        }

        $stmt->close();

        return $total;
    }

    public function getTotalByRole($role) {
        if (!$this->conn) {
            return 0;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = ?");
        $stmt->bind_param("s", $role);
        $stmt->execute();

        $result = $stmt->get_result();
        $total = 0;

        if ($result) {
            $total = $result->fetch_assoc()["total"];
        }

        $stmt->close();

        return $total;
    }

    public function getPendingUsers() {
        if (!$this->conn) {
            return false;
        }

        return $this->conn->query("
            SELECT id, full_name, email, role, student_id, faculty_id, office_name, program, year_level, section, department, created_at
            FROM users
            WHERE status = 'pending' AND role != 'admin'
            ORDER BY created_at DESC
        ");
    }

    public function getRecentUsers() {
        if (!$this->conn) {
            return false;
        }

        return $this->conn->query("
            SELECT full_name, email, role, status, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT 8
        ");
    }

    public function updateUserStatus($userId, $status) {
        if (!$this->conn) {
            return false;
        }

        $stmt = $this->conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role != 'admin'");
        $stmt->bind_param("si", $status, $userId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function getTotalAnnouncements() {
        if (!$this->conn) {
            return 0;
        }

        $result = $this->conn->query("SELECT COUNT(*) AS total FROM announcements");

        if ($result) {
            return $result->fetch_assoc()["total"];
        }

        return 0;
    }

    public function getTotalAnnouncementsByStatus($status) {
        if (!$this->conn) {
            return 0;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM announcements WHERE status = ?");
        $stmt->bind_param("s", $status);
        $stmt->execute();

        $result = $stmt->get_result();
        $total = 0;

        if ($result) {
            $total = $result->fetch_assoc()["total"];
        }

        $stmt->close();

        return $total;
    }

    public function getTotalUrgentAnnouncements() {
        if (!$this->conn) {
            return 0;
        }

        $result = $this->conn->query("SELECT COUNT(*) AS total FROM announcements WHERE urgency = 'urgent'");

        if ($result) {
            return $result->fetch_assoc()["total"];
        }

        return 0;
    }

    public function getAllAnnouncements() {
        if (!$this->conn) {
            return false;
        }

        return $this->conn->query("
            SELECT 
                announcements.id,
                announcements.title,
                announcements.description,
                announcements.category,
                announcements.target_audience,
                announcements.urgency,
                announcements.status,
                announcements.created_at,
                users.full_name AS posted_by_name,
                users.role AS posted_by_role
            FROM announcements
            INNER JOIN users ON announcements.posted_by = users.id
            ORDER BY announcements.created_at DESC
        ");
    }

    public function updateAnnouncementStatus($announcementId, $status) {
        if (!$this->conn) {
            return false;
        }

        $stmt = $this->conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $announcementId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function getTotalCategories() {
        if (!$this->conn) {
            return 0;
        }

        $result = $this->conn->query("SELECT COUNT(*) AS total FROM categories");

        if ($result) {
            return $result->fetch_assoc()["total"];
        }

        return 0;
    }

    public function getTotalCategoriesByStatus($status) {
        if (!$this->conn) {
            return 0;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM categories WHERE status = ?");
        $stmt->bind_param("s", $status);
        $stmt->execute();

        $result = $stmt->get_result();
        $total = 0;

        if ($result) {
            $total = $result->fetch_assoc()["total"];
        }

        $stmt->close();

        return $total;
    }

    public function addCategory($categoryName, $description) {
        if (!$this->conn) {
            return false;
        }

        $status = "active";

        $stmt = $this->conn->prepare("
            INSERT INTO categories (category_name, description, status)
            VALUES (?, ?, ?)
        ");

        $stmt->bind_param("sss", $categoryName, $description, $status);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function updateCategoryStatus($categoryId, $status) {
        if (!$this->conn) {
            return false;
        }

        $stmt = $this->conn->prepare("UPDATE categories SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $categoryId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function getAllCategories() {
        if (!$this->conn) {
            return false;
        }

        return $this->conn->query("
            SELECT id, category_name, description, status, created_at
            FROM categories
            ORDER BY created_at DESC
        ");
    }

    public function getAnnouncementCountByCategory() {
        if (!$this->conn) {
            return false;
        }

        return $this->conn->query("
            SELECT category, COUNT(*) AS total
            FROM announcements
            GROUP BY category
            ORDER BY total DESC
        ");
    }

    public function getUserCountByRoleReport() {
        if (!$this->conn) {
            return false;
        }

        return $this->conn->query("
            SELECT role, COUNT(*) AS total
            FROM users
            GROUP BY role
            ORDER BY total DESC
        ");
    }

    public function getUserCountByStatusReport() {
        if (!$this->conn) {
            return false;
        }

        return $this->conn->query("
            SELECT status, COUNT(*) AS total
            FROM users
            GROUP BY status
            ORDER BY total DESC
        ");
    }

    public function getAnnouncementCountByStatusReport() {
        if (!$this->conn) {
            return false;
        }

        return $this->conn->query("
            SELECT status, COUNT(*) AS total
            FROM announcements
            GROUP BY status
            ORDER BY total DESC
        ");
    }

    public function getAnnouncementCountByUrgencyReport() {
        if (!$this->conn) {
            return false;
        }

        return $this->conn->query("
            SELECT urgency, COUNT(*) AS total
            FROM announcements
            GROUP BY urgency
            ORDER BY total DESC
        ");
    }
}
?>