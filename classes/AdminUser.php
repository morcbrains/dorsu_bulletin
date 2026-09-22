<?php
require_once __DIR__ . "/BaseUser.php";

if (!class_exists("AdminUser")) {
    class AdminUser extends BaseUser {
        public function getDashboardLink() {
            return "dashboards/admin_dashboard.php";
        }

        public function getRoleDisplayName() {
            return "Admin";
        }

        public function getPermissions() {
            return [
                "manage_users",
                "create_staff_accounts",
                "approve_accounts",
                "reject_accounts",
                "edit_users",
                "delete_users",
                "manage_announcements",
                "manage_categories",
                "view_reports",
                "update_settings",
                "view_notifications",
                "send_messages"
            ];
        }

        public function canManageUsers() {
            return true;
        }

        public function canManageSystemSettings() {
            return true;
        }

        public function canViewReports() {
            return true;
        }
    }
}
?>