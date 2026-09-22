<?php
if (!class_exists("BaseUser")) {
    abstract class BaseUser {
        protected $id;
        protected $fullName;
        protected $email;
        protected $role;
        protected $status;

        public function __construct($userData) {
            $this->id = isset($userData["id"]) ? $userData["id"] : null;
            $this->fullName = isset($userData["full_name"]) ? $userData["full_name"] : "";
            $this->email = isset($userData["email"]) ? $userData["email"] : "";
            $this->role = isset($userData["role"]) ? $userData["role"] : "";
            $this->status = isset($userData["status"]) ? $userData["status"] : "";
        }

        public function getId() {
            return $this->id;
        }

        public function getFullName() {
            return $this->fullName;
        }

        public function getEmail() {
            return $this->email;
        }

        public function getRole() {
            return $this->role;
        }

        public function getStatus() {
            return $this->status;
        }

        public function isApproved() {
            return $this->status === "approved";
        }

        public function getBasicInfo() {
            return [
                "id" => $this->id,
                "full_name" => $this->fullName,
                "email" => $this->email,
                "role" => $this->role,
                "status" => $this->status
            ];
        }

        abstract public function getDashboardLink();

        abstract public function getRoleDisplayName();

        abstract public function getPermissions();
    }
}
?>