<?php
require_once __DIR__ . "/BaseUser.php";

if (!class_exists("StudentUser")) {
    class StudentUser extends BaseUser {
        private $studentId;
        private $department;
        private $program;
        private $yearLevel;
        private $section;

        public function __construct($userData) {
            parent::__construct($userData);
            $this->studentId = isset($userData["student_id"]) ? $userData["student_id"] : "";
            $this->department = isset($userData["department"]) ? $userData["department"] : "";
            $this->program = isset($userData["program"]) ? $userData["program"] : "";
            $this->yearLevel = isset($userData["year_level"]) ? $userData["year_level"] : "";
            $this->section = isset($userData["section"]) ? $userData["section"] : "";
        }

        public function getDashboardLink() {
            return "dashboards/student_dashboard.php";
        }

        public function getRoleDisplayName() {
            return "Student";
        }

        public function getStudentId() {
            return $this->studentId;
        }

        public function getDepartment() {
            return $this->department;
        }

        public function getProgram() {
            return $this->program;
        }

        public function getYearLevel() {
            return $this->yearLevel;
        }

        public function getSection() {
            return $this->section;
        }

        public function getPermissions() {
            return [
                "view_announcements",
                "view_university_events",
                "save_announcements",
                "mark_announcements_read",
                "view_notifications",
                "send_messages"
            ];
        }

        public function canCreateAnnouncement() {
            return false;
        }

        public function canCreateUniversityEvent() {
            return false;
        }

        public function getBasicInfo() {
            $info = parent::getBasicInfo();
            $info["student_id"] = $this->studentId;
            $info["department"] = $this->department;
            $info["program"] = $this->program;
            $info["year_level"] = $this->yearLevel;
            $info["section"] = $this->section;
            return $info;
        }
    }
}
?>