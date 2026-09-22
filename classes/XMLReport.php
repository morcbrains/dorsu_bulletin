<?php
if (!class_exists("XMLReport")) {
    class XMLReport {
        private $conn;
        private $exportsDir;

        public function __construct($conn) {
            $this->conn = $conn;
            $this->exportsDir = dirname(__DIR__) . "/exports";
        }

        public function getSystemInformation() {
            require_once __DIR__ . "/SystemSetting.php";

            $settings = (new SystemSetting($this->conn))->getAllSettings();

            return [
                "system_name" => $settings["system_name"] ?? "DOrSU Bulletin: University Announcement System",
                "university" => $settings["university_name"] ?? "Davao Oriental State University",
                "description" => "A centralized web-based platform for official university announcements, academic reminders, events, activities, and important updates."
            ];
        }

        public function getRoleOverview() {
            $definitions = [
                "admin" => [
                    "role" => "Admin",
                    "responsibility" => "Manages users, staff accounts, announcements, categories, reports, and system settings."
                ],
                "informant" => [
                    "role" => "University Office Informant",
                    "responsibility" => "Creates official university announcements and university events for the DOrSU community."
                ],
                "faculty" => [
                    "role" => "Faculty Member",
                    "responsibility" => "Creates faculty and program-related announcements under the assigned faculty."
                ],
                "student" => [
                    "role" => "Student",
                    "responsibility" => "Views announcements, events, notifications, saved announcements, and private messages."
                ]
            ];

            $counts = [
                "admin" => 0,
                "informant" => 0,
                "faculty" => 0,
                "student" => 0,
            ];

            $stmt = $this->conn->prepare("
                SELECT role, COUNT(*) AS total
                FROM users
                WHERE status = 'approved'
                GROUP BY role
            ");
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $roleKey = $row["role"];

                    if (array_key_exists($roleKey, $counts)) {
                        $counts[$roleKey] = intval($row["total"]);
                    }
                }
            }

            $stmt->close();

            $overview = [];

            foreach ($definitions as $roleKey => $definition) {
                $overview[] = [
                    "role" => $definition["role"],
                    "responsibility" => $definition["responsibility"],
                    "approved_count" => $counts[$roleKey],
                ];
            }

            return $overview;
        }

        public function getApprovedAccounts() {
            $stmt = $this->conn->prepare("
                SELECT
                    full_name,
                    email,
                    role,
                    status,
                    student_id,
                    faculty_id,
                    office_name,
                    program,
                    year_level,
                    department
                FROM users
                WHERE status = 'approved'
                ORDER BY role ASC, full_name ASC
            ");

            $stmt->execute();
            $result = $stmt->get_result();
            $accounts = [];

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $unit = "System Account";

                    if (!empty($row["office_name"])) {
                        $unit = $row["office_name"];
                    } elseif (!empty($row["department"])) {
                        $unit = $row["department"];
                    } elseif (!empty($row["program"])) {
                        $unit = $row["program"];
                    }

                    $accounts[] = [
                        "full_name" => $row["full_name"],
                        "email" => $row["email"],
                        "role" => $this->formatRole($row["role"]),
                        "status" => $row["status"],
                        "unit" => $unit,
                        "student_id" => $row["student_id"] ?? "",
                        "faculty_id" => $row["faculty_id"] ?? "",
                    ];
                }
            }

            $stmt->close();

            return $accounts;
        }

        public function getAnnouncements() {
            $stmt = $this->conn->prepare("
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
                    users.office_name,
                    users.department
                FROM announcements
                LEFT JOIN users ON announcements.posted_by = users.id
                ORDER BY announcements.created_at DESC
            ");

            $stmt->execute();
            $result = $stmt->get_result();

            $announcements = [];

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $postedBy = "Unknown";

                    if (!empty($row["office_name"])) {
                        $postedBy = $row["office_name"];
                    } elseif (!empty($row["department"])) {
                        $postedBy = $row["department"];
                    } elseif (!empty($row["posted_by_name"])) {
                        $postedBy = $row["posted_by_name"];
                    }

                    $announcements[] = [
                        "id" => $row["id"],
                        "title" => $row["title"],
                        "description" => $row["description"],
                        "category" => $row["category"],
                        "target_audience" => $row["target_audience"],
                        "urgency" => $row["urgency"],
                        "status" => $row["status"],
                        "posted_by" => $postedBy,
                        "created_at" => $row["created_at"]
                    ];
                }
            }

            $stmt->close();

            return $announcements;
        }

        public function getEvents() {
            $stmt = $this->conn->prepare("
                SELECT
                    university_events.id,
                    university_events.event_title,
                    university_events.event_description,
                    university_events.event_category,
                    university_events.event_date,
                    university_events.start_time,
                    university_events.end_time,
                    university_events.venue,
                    university_events.organizer,
                    university_events.target_audience,
                    university_events.status,
                    university_events.created_at,
                    users.full_name AS created_by_name
                FROM university_events
                LEFT JOIN users ON university_events.created_by = users.id
                ORDER BY university_events.event_date DESC, university_events.start_time DESC
            ");

            $stmt->execute();
            $result = $stmt->get_result();

            $events = [];

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $events[] = [
                        "id" => $row["id"],
                        "event_title" => $row["event_title"],
                        "event_description" => $row["event_description"],
                        "event_category" => $row["event_category"],
                        "event_date" => $row["event_date"],
                        "start_time" => $row["start_time"],
                        "end_time" => $row["end_time"],
                        "venue" => $row["venue"],
                        "organizer" => $row["organizer"],
                        "target_audience" => $row["target_audience"],
                        "status" => $row["status"],
                        "created_by" => $row["created_by_name"] ?? "Unknown",
                        "created_at" => $row["created_at"]
                    ];
                }
            }

            $stmt->close();

            return $events;
        }

        public function getNotifications() {
            $stmt = $this->conn->prepare("
                SELECT
                    notifications.notification_type,
                    notifications.title,
                    notifications.message,
                    notifications.is_read,
                    notifications.created_at,
                    users.full_name AS receiver_name
                FROM notifications
                LEFT JOIN users ON notifications.user_id = users.id
                ORDER BY notifications.created_at DESC
            ");

            $stmt->execute();
            $result = $stmt->get_result();

            $notifications = [];

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $notifications[] = [
                        "type" => $row["notification_type"],
                        "title" => $row["title"],
                        "message" => $row["message"],
                        "receiver" => $row["receiver_name"] ?? "Unknown",
                        "status" => intval($row["is_read"]) === 1 ? "Read" : "Unread",
                        "created_at" => $row["created_at"]
                    ];
                }
            }

            $stmt->close();

            return $notifications;
        }

        public function getMessagesSummary() {
            $stmt = $this->conn->prepare("
                SELECT
                    private_messages.message,
                    private_messages.created_at,
                    sender.full_name AS sender_name,
                    sender.role AS sender_role,
                    receiver.full_name AS receiver_name,
                    receiver.role AS receiver_role
                FROM private_messages
                INNER JOIN users AS sender ON private_messages.sender_id = sender.id
                INNER JOIN users AS receiver ON private_messages.receiver_id = receiver.id
                ORDER BY private_messages.created_at DESC
                LIMIT 20
            ");

            $stmt->execute();
            $result = $stmt->get_result();

            $messages = [];

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $messages[] = [
                        "sender" => $row["sender_name"] . " (" . $this->formatRole($row["sender_role"]) . ")",
                        "receiver" => $row["receiver_name"] . " (" . $this->formatRole($row["receiver_role"]) . ")",
                        "message" => $row["message"],
                        "message_type" => "Private Message",
                        "status" => "Stored",
                        "created_at" => $row["created_at"]
                    ];
                }
            }

            $stmt->close();

            return $messages;
        }

        public function buildExportServeUrl(string $basename): string {
            return "serve.php?file=" . rawurlencode($basename);
        }

        public function exportAnnouncementsXml(): string {
            $xml = $this->buildAnnouncementsDocument();
            $path = $this->exportsDir . "/announcements_report.xml";
            $xml->save($path);

            return $path;
        }

        public function exportEventsXml(): string {
            $xml = $this->buildEventsDocument();
            $path = $this->exportsDir . "/university_events_report.xml";
            $xml->save($path);

            return $path;
        }

        public function exportFullBulletinXml(): string {
            $xml = $this->buildFullBulletinDocument();
            $path = $this->exportsDir . "/dorsu_bulletin_report.xml";
            $xml->save($path);

            return $path;
        }

        public function publishAnnouncementsXmlView(): string {
            $xmlPath = $this->exportAnnouncementsXml();
            $viewPath = $this->exportsDir . "/announcements_xml_view.html";
            $this->writeXmlViewPage(
                $xmlPath,
                $viewPath,
                "Announcements XML Records",
                "Raw XML output generated from the announcements database table.",
                "announcements_report.xml",
                "announcements_report.xsl"
            );

            return "announcements_xml_view.html";
        }

        public function publishEventsXmlView(): string {
            $xmlPath = $this->exportEventsXml();
            $viewPath = $this->exportsDir . "/university_events_xml_view.html";
            $this->writeXmlViewPage(
                $xmlPath,
                $viewPath,
                "University Events XML Records",
                "Raw XML output generated from the university_events database table.",
                "university_events_report.xml",
                "events_report.xsl"
            );

            return "university_events_xml_view.html";
        }

        public function publishFullBulletinXmlView(): string {
            $xmlPath = $this->exportFullBulletinXml();
            $viewPath = $this->exportsDir . "/dorsu_bulletin_xml_view.html";
            $this->writeXmlViewPage(
                $xmlPath,
                $viewPath,
                "Full DOrSU Bulletin XML Records",
                "Raw XML output generated from multiple bulletin system tables.",
                "dorsu_bulletin_report.xml",
                "dorsu_bulletin_report_dynamic.xsl"
            );

            return "dorsu_bulletin_xml_view.html";
        }

        public function streamRawXmlFile(string $basename): void {
            $allowed = [
                "announcements_report.xml",
                "university_events_report.xml",
                "dorsu_bulletin_report.xml",
            ];

            if (!in_array($basename, $allowed, true)) {
                throw new InvalidArgumentException("Invalid XML file requested.");
            }

            $path = $this->exportsDir . "/" . $basename;

            if (!is_file($path)) {
                if ($basename === "announcements_report.xml") {
                    $this->exportAnnouncementsXml();
                } elseif ($basename === "university_events_report.xml") {
                    $this->exportEventsXml();
                } else {
                    $this->exportFullBulletinXml();
                }
            }

            header("Content-Type: application/xml; charset=UTF-8");
            header("Content-Disposition: inline; filename=" . $basename);
            readfile($path);
            exit();
        }

        public function transformAnnouncementsToHtml(): string {
            $this->exportAnnouncementsXml();

            $xmlPath = $this->exportsDir . "/announcements_report.xml";
            $xslPath = $this->exportsDir . "/announcements_report.xsl";
            $htmlPath = $this->exportsDir . "/announcements_report.html";

            return $this->transformToHtml($xmlPath, $xslPath, $htmlPath);
        }

        public function transformEventsToHtml(): string {
            $this->exportEventsXml();

            $xmlPath = $this->exportsDir . "/university_events_report.xml";
            $xslPath = $this->exportsDir . "/events_report.xsl";
            $htmlPath = $this->exportsDir . "/university_events_report.html";

            return $this->transformToHtml($xmlPath, $xslPath, $htmlPath);
        }

        public function transformFullBulletinToHtml(): string {
            $this->exportFullBulletinXml();

            $xmlPath = $this->exportsDir . "/dorsu_bulletin_report.xml";
            $xslPath = $this->exportsDir . "/dorsu_bulletin_report_dynamic.xsl";
            $htmlPath = $this->exportsDir . "/dorsu_bulletin_report.html";

            return $this->transformToHtml($xmlPath, $xslPath, $htmlPath);
        }

        public function transformToHtml(string $xmlPath, string $xslPath, string $htmlPath): string {
            $this->ensureXsltExtension();

            if (!is_file($xmlPath)) {
                throw new RuntimeException("XML file was not found: " . basename($xmlPath));
            }

            if (!is_file($xslPath)) {
                throw new RuntimeException("XSLT stylesheet was not found: " . basename($xslPath));
            }

            $xml = new DOMDocument("1.0", "UTF-8");
            $xml->load($xmlPath);

            $xsl = new DOMDocument("1.0", "UTF-8");
            $xsl->load($xslPath);

            $processor = new XSLTProcessor();
            $processor->importStylesheet($xsl);
            $html = $processor->transformToXml($xml);

            if ($html === false) {
                throw new RuntimeException("XSLT transformation failed for " . basename($xmlPath));
            }

            file_put_contents($htmlPath, $html);

            return $htmlPath;
        }

        public function generateFullXML() {
            return $this->buildFullBulletinDocument()->saveXML();
        }

        private function buildAnnouncementsDocument(): DOMDocument {
            $announcements = $this->getAnnouncements();

            $xml = new DOMDocument("1.0", "UTF-8");
            $xml->formatOutput = true;

            $root = $xml->createElement("announcements");
            $root->setAttribute("generatedAt", date("Y-m-d H:i:s"));
            $xml->appendChild($root);

            foreach ($announcements as $announcement) {
                $announcementNode = $xml->createElement("announcement");
                $root->appendChild($announcementNode);

                foreach ($announcement as $fieldName => $fieldValue) {
                    $this->appendTextNode($xml, $announcementNode, $fieldName, $fieldValue);
                }
            }

            return $xml;
        }

        private function buildEventsDocument(): DOMDocument {
            $events = $this->getEvents();

            $xml = new DOMDocument("1.0", "UTF-8");
            $xml->formatOutput = true;

            $root = $xml->createElement("university_events");
            $root->setAttribute("generatedAt", date("Y-m-d H:i:s"));
            $xml->appendChild($root);

            foreach ($events as $event) {
                $eventNode = $xml->createElement("event");
                $root->appendChild($eventNode);

                foreach ($event as $fieldName => $fieldValue) {
                    $this->appendTextNode($xml, $eventNode, $fieldName, $fieldValue);
                }
            }

            return $xml;
        }

        private function buildFullBulletinDocument(): DOMDocument {
            $systemInformation = $this->getSystemInformation();
            $roleOverview = $this->getRoleOverview();
            $approvedAccounts = $this->getApprovedAccounts();
            $announcements = $this->getAnnouncements();
            $events = $this->getEvents();
            $messages = $this->getMessagesSummary();
            $notifications = $this->getNotifications();

            $xml = new DOMDocument("1.0", "UTF-8");
            $xml->formatOutput = true;

            $root = $xml->createElement("dorsu_bulletin");
            $root->setAttribute("generatedAt", date("Y-m-d H:i:s"));
            $xml->appendChild($root);

            $systemNode = $xml->createElement("system_information");
            $root->appendChild($systemNode);

            $this->appendTextNode($xml, $systemNode, "system_name", $systemInformation["system_name"]);
            $this->appendTextNode($xml, $systemNode, "university", $systemInformation["university"]);
            $this->appendTextNode($xml, $systemNode, "description", $systemInformation["description"]);

            $roleOverviewNode = $xml->createElement("role_overview");
            $root->appendChild($roleOverviewNode);

            foreach ($roleOverview as $roleItem) {
                $roleNode = $xml->createElement("role_record");
                $roleOverviewNode->appendChild($roleNode);

                $this->appendTextNode($xml, $roleNode, "role", $roleItem["role"]);
                $this->appendTextNode($xml, $roleNode, "responsibility", $roleItem["responsibility"]);
                $this->appendTextNode($xml, $roleNode, "approved_count", $roleItem["approved_count"]);
            }

            $accountsNode = $xml->createElement("approved_accounts");
            $root->appendChild($accountsNode);

            foreach ($approvedAccounts as $account) {
                $accountNode = $xml->createElement("account");
                $accountsNode->appendChild($accountNode);

                $this->appendTextNode($xml, $accountNode, "full_name", $account["full_name"]);
                $this->appendTextNode($xml, $accountNode, "email", $account["email"]);
                $this->appendTextNode($xml, $accountNode, "role", $account["role"]);
                $this->appendTextNode($xml, $accountNode, "status", $account["status"]);
                $this->appendTextNode($xml, $accountNode, "unit", $account["unit"]);
            }

            $announcementsNode = $xml->createElement("announcements");
            $root->appendChild($announcementsNode);

            foreach ($announcements as $announcement) {
                $announcementNode = $xml->createElement("announcement");
                $announcementsNode->appendChild($announcementNode);

                $this->appendTextNode($xml, $announcementNode, "title", $announcement["title"]);
                $this->appendTextNode($xml, $announcementNode, "description", $announcement["description"]);
                $this->appendTextNode($xml, $announcementNode, "category", $announcement["category"]);
                $this->appendTextNode($xml, $announcementNode, "target_audience", $announcement["target_audience"]);
                $this->appendTextNode($xml, $announcementNode, "urgency", $announcement["urgency"]);
                $this->appendTextNode($xml, $announcementNode, "status", $announcement["status"]);
                $this->appendTextNode($xml, $announcementNode, "posted_by", $announcement["posted_by"]);
                $this->appendTextNode($xml, $announcementNode, "created_at", $announcement["created_at"]);
            }

            $eventsNode = $xml->createElement("events");
            $root->appendChild($eventsNode);

            foreach ($events as $event) {
                $eventNode = $xml->createElement("event");
                $eventsNode->appendChild($eventNode);

                $this->appendTextNode($xml, $eventNode, "event_title", $event["event_title"]);
                $this->appendTextNode($xml, $eventNode, "event_description", $event["event_description"]);
                $this->appendTextNode($xml, $eventNode, "event_category", $event["event_category"]);
                $this->appendTextNode($xml, $eventNode, "event_date", $event["event_date"]);
                $this->appendTextNode($xml, $eventNode, "start_time", $event["start_time"]);
                $this->appendTextNode($xml, $eventNode, "end_time", $event["end_time"]);
                $this->appendTextNode($xml, $eventNode, "venue", $event["venue"]);
                $this->appendTextNode($xml, $eventNode, "organizer", $event["organizer"]);
                $this->appendTextNode($xml, $eventNode, "target_audience", $event["target_audience"]);
                $this->appendTextNode($xml, $eventNode, "status", $event["status"]);
                $this->appendTextNode($xml, $eventNode, "created_at", $event["created_at"]);
            }

            $messagesNode = $xml->createElement("messages");
            $root->appendChild($messagesNode);

            foreach ($messages as $message) {
                $messageNode = $xml->createElement("message_record");
                $messagesNode->appendChild($messageNode);

                $this->appendTextNode($xml, $messageNode, "sender", $message["sender"]);
                $this->appendTextNode($xml, $messageNode, "receiver", $message["receiver"]);
                $this->appendTextNode($xml, $messageNode, "message", $message["message"]);
                $this->appendTextNode($xml, $messageNode, "message_type", $message["message_type"]);
                $this->appendTextNode($xml, $messageNode, "status", $message["status"]);
                $this->appendTextNode($xml, $messageNode, "created_at", $message["created_at"]);
            }

            $notificationsNode = $xml->createElement("notifications");
            $root->appendChild($notificationsNode);

            foreach ($notifications as $notification) {
                $notificationNode = $xml->createElement("notification");
                $notificationsNode->appendChild($notificationNode);

                $this->appendTextNode($xml, $notificationNode, "type", $notification["type"]);
                $this->appendTextNode($xml, $notificationNode, "title", $notification["title"]);
                $this->appendTextNode($xml, $notificationNode, "message", $notification["message"]);
                $this->appendTextNode($xml, $notificationNode, "receiver", $notification["receiver"]);
                $this->appendTextNode($xml, $notificationNode, "status", $notification["status"]);
                $this->appendTextNode($xml, $notificationNode, "created_at", $notification["created_at"]);
            }

            return $xml;
        }

        private function writeXmlViewPage(
            string $xmlPath,
            string $viewPath,
            string $title,
            string $description,
            string $sourceFileName,
            string $xslFileName
        ): void {
            $xml = new DOMDocument("1.0", "UTF-8");
            $xml->load($xmlPath);
            $escapedXml = htmlspecialchars($xml->saveXML(), ENT_QUOTES, "UTF-8");

            $html = '<!doctype html><html lang="en"><head><meta charset="UTF-8">'
                . '<title>' . htmlspecialchars($title, ENT_QUOTES, "UTF-8") . '</title>'
                . '<style>'
                . 'body{margin:0;font-family:Segoe UI,Tahoma,sans-serif;background:#edf2fb;color:#10203a;}'
                . '.shell{max-width:1180px;margin:32px auto;padding:0 18px 40px;}'
                . '.hero{background:#002b7f;color:#fff;border-bottom:6px solid #f6c400;border-radius:18px 18px 0 0;padding:28px 30px;}'
                . '.hero h1{margin:0 0 8px;font-size:30px;}'
                . '.hero p{margin:0;opacity:.92;line-height:1.6;}'
                . '.panel{background:#fff;border:1px solid #d7e2f4;border-radius:0 0 18px 18px;box-shadow:0 18px 40px rgba(0,43,127,.08);padding:24px 30px 30px;}'
                . '.links{margin:0 0 18px;display:flex;gap:12px;flex-wrap:wrap;}'
                . '.links a{display:inline-block;padding:10px 14px;border-radius:999px;background:#fff7cc;color:#002b7f;text-decoration:none;font-weight:700;border:1px solid #f6c400;}'
                . 'pre{white-space:pre-wrap;word-break:break-word;background:#0f1f3d;color:#f8fafc;border-radius:16px;padding:22px;font-size:13px;line-height:1.55;border:1px solid #1e3a6d;}'
                . '.note{margin-top:16px;color:#5b6472;font-size:13px;}'
                . '</style></head><body><div class="shell"><div class="hero"><h1>'
                . htmlspecialchars($title, ENT_QUOTES, "UTF-8")
                . '</h1><p>' . htmlspecialchars($description, ENT_QUOTES, "UTF-8")
                . '</p></div><div class="panel"><div class="links">'
                . '<a href="' . htmlspecialchars($this->buildExportServeUrl($sourceFileName), ENT_QUOTES, "UTF-8") . '">Open XML Source File</a>'
                . '<a href="' . htmlspecialchars($this->buildExportServeUrl($xslFileName), ENT_QUOTES, "UTF-8") . '">Open XSLT Stylesheet</a>'
                . '</div><pre>' . $escapedXml . '</pre>'
                . '<p class="note">This page shows the raw XML tags exported from the database. Use Generate HTML via XSLT for the formatted report view.</p>'
                . '</div></div></body></html>';

            file_put_contents($viewPath, $html);
        }

        private function appendTextNode($xml, $parent, $name, $value) {
            $node = $xml->createElement($name);
            $text = $xml->createTextNode($value ?? "");
            $node->appendChild($text);
            $parent->appendChild($node);
        }

        private function ensureXsltExtension(): void {
            if (!class_exists("XSLTProcessor")) {
                throw new RuntimeException(
                    "PHP XSLT extension is not enabled. Open C:\\xampp\\php\\php.ini, set extension=xsl, then restart Apache."
                );
            }
        }

        private function formatRole($role) {
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
    }
}
?>
