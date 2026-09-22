<?php
if (!class_exists("SystemSetting")) {
    class SystemSetting {
        private $conn;

        public function __construct($conn) {
            $this->conn = $conn;
        }

        public function getAllSettings() {
            $settings = [];

            $result = $this->conn->query("
                SELECT setting_key, setting_value
                FROM system_settings
            ");

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $settings[$row["setting_key"]] = $row["setting_value"];
                }
            }

            return $settings;
        }

        public function getSetting($key, $defaultValue = "") {
            $stmt = $this->conn->prepare("
                SELECT setting_value
                FROM system_settings
                WHERE setting_key = ?
                LIMIT 1
            ");

            $stmt->bind_param("s", $key);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row["setting_value"];
            }

            $stmt->close();
            return $defaultValue;
        }

        public function updateSetting($key, $value) {
            $stmt = $this->conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

            $stmt->bind_param("ss", $key, $value);
            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function updateSettings($settings) {
            foreach ($settings as $key => $value) {
                if (!$this->updateSetting($key, $value)) {
                    return false;
                }
            }

            return true;
        }
    }
}
?>