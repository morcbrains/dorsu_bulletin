<?php
require_once __DIR__ . "/AdminUser.php";
require_once __DIR__ . "/InformantUser.php";
require_once __DIR__ . "/FacultyUser.php";
require_once __DIR__ . "/StudentUser.php";

if (!class_exists("UserRoleFactory")) {
    class UserRoleFactory {
        public static function create($userData) {
            if (!isset($userData["role"])) {
                return null;
            }

            if ($userData["role"] === "admin") {
                return new AdminUser($userData);
            }

            if ($userData["role"] === "informant") {
                return new InformantUser($userData);
            }

            if ($userData["role"] === "faculty") {
                return new FacultyUser($userData);
            }

            if ($userData["role"] === "student") {
                return new StudentUser($userData);
            }

            return null;
        }
    }
}
?>