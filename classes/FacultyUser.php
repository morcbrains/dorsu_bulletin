<?php
require_once __DIR__ . "/BaseUser.php";

if (!class_exists("FacultyUser")) {
    class FacultyUser extends BaseUser {
        private $facultyId;
        private $department;

        public function __construct($userData) {
            parent::__construct($userData);
            $this->facultyId = isset($userData["faculty_id"]) ? $userData["faculty_id"] : "";
            $this->department = isset($userData["department"]) ? $userData["department"] : "";
        }

        public function getDashboardLink() {
            return "dashboards/faculty_dashboard.php";
        }

        public function getRoleDisplayName() {
            return "Faculty Member";
        }

        public function getFacultyId() {
            return $this->facultyId;
        }

        public function getDepartment() {
            return $this->department;
        }

        public function getPermissions() {
            return [
                "create_faculty_announcements",
                "manage_own_announcements",
                "view_university_announcements",
                "view_university_events",
                "view_notifications",
                "send_messages"
            ];
        }

        public function canCreateFacultyAnnouncement() {
            return true;
        }

        public function canCreateUniversityEvent() {
            return false;
        }

        public function getBasicInfo() {
            $info = parent::getBasicInfo();
            $info["faculty_id"] = $this->facultyId;
            $info["department"] = $this->department;
            return $info;
        }
    }
}
?>