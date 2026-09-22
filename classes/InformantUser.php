<?php
require_once __DIR__ . "/BaseUser.php";

if (!class_exists("InformantUser")) {
    class InformantUser extends BaseUser {
        private $officeName;

        public function __construct($userData) {
            parent::__construct($userData);
            $this->officeName = isset($userData["office_name"]) ? $userData["office_name"] : "";
        }

        public function getDashboardLink() {
            return "dashboards/informant_dashboard.php";
        }

        public function getRoleDisplayName() {
            return "University Office Informant";
        }

        public function getOfficeName() {
            return $this->officeName;
        }

        public function getPermissions() {
            return [
                "create_university_announcements",
                "manage_own_announcements",
                "create_university_events",
                "manage_own_events",
                "view_notifications",
                "send_messages",
                "view_xml_reports"
            ];
        }

        public function canCreateUniversityAnnouncement() {
            return true;
        }

        public function canCreateUniversityEvent() {
            return true;
        }

        public function getBasicInfo() {
            $info = parent::getBasicInfo();
            $info["office_name"] = $this->officeName;
            return $info;
        }
    }
}
?>