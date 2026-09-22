<?php
if (!class_exists("Event")) {
    class Event {
        private $conn;

        public function __construct($conn) {
            $this->conn = $conn;
        }

        public function createEvent($title, $description, $category, $eventDate, $startTime, $endTime, $venue, $organizer, $targetAudience, $status, $createdBy) {
            $stmt = $this->conn->prepare("
                INSERT INTO university_events (
                    event_title,
                    event_description,
                    event_category,
                    event_date,
                    start_time,
                    end_time,
                    venue,
                    organizer,
                    target_audience,
                    status,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssssssssssi",
                $title,
                $description,
                $category,
                $eventDate,
                $startTime,
                $endTime,
                $venue,
                $organizer,
                $targetAudience,
                $status,
                $createdBy
            );

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function getMyEvents($userId) {
            $stmt = $this->conn->prepare("
                SELECT 
                    id,
                    event_title,
                    event_description,
                    event_category,
                    event_date,
                    start_time,
                    end_time,
                    venue,
                    organizer,
                    target_audience,
                    status,
                    created_at,
                    updated_at
                FROM university_events
                WHERE created_by = ?
                ORDER BY event_date ASC, start_time ASC
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            return $stmt->get_result();
        }

        public function getTotalMyEvents($userId) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM university_events
                WHERE created_by = ?
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

        public function getTotalMyEventsByStatus($userId, $status) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS total
                FROM university_events
                WHERE created_by = ? AND status = ?
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

        public function updateMyEventStatus($eventId, $userId, $status) {
            $stmt = $this->conn->prepare("
                UPDATE university_events
                SET status = ?
                WHERE id = ? AND created_by = ?
            ");

            $stmt->bind_param("sii", $status, $eventId, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function deleteMyEvent($eventId, $userId) {
            $stmt = $this->conn->prepare("
                DELETE FROM university_events
                WHERE id = ? AND created_by = ?
            ");

            $stmt->bind_param("ii", $eventId, $userId);

            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }

        public function getEventsForStudent() {
            return $this->conn->query("
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
                    users.full_name AS created_by_name,
                    users.role AS created_by_role
                FROM university_events
                INNER JOIN users ON university_events.created_by = users.id
                WHERE university_events.status = 'published'
                AND (
                    university_events.target_audience = 'All Users'
                    OR university_events.target_audience = 'All Students'
                    OR university_events.target_audience = 'Students and Faculty'
                )
                ORDER BY university_events.event_date ASC, university_events.start_time ASC
            ");
        }

        public function getEventsForFaculty() {
            return $this->conn->query("
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
                    users.full_name AS created_by_name,
                    users.role AS created_by_role
                FROM university_events
                INNER JOIN users ON university_events.created_by = users.id
                WHERE university_events.status = 'published'
                AND (
                    university_events.target_audience = 'All Users'
                    OR university_events.target_audience = 'All Faculty Members'
                    OR university_events.target_audience = 'Students and Faculty'
                )
                ORDER BY university_events.event_date ASC, university_events.start_time ASC
            ");
        }

        public function getTotalEventsForStudent() {
            $result = $this->conn->query("
                SELECT COUNT(*) AS total
                FROM university_events
                WHERE status = 'published'
                AND (
                    target_audience = 'All Users'
                    OR target_audience = 'All Students'
                    OR target_audience = 'Students and Faculty'
                )
            ");

            if ($result) {
                return $result->fetch_assoc()["total"];
            }

            return 0;
        }

        public function getTotalEventsForFaculty() {
            $result = $this->conn->query("
                SELECT COUNT(*) AS total
                FROM university_events
                WHERE status = 'published'
                AND (
                    target_audience = 'All Users'
                    OR target_audience = 'All Faculty Members'
                    OR target_audience = 'Students and Faculty'
                )
            ");

            if ($result) {
                return $result->fetch_assoc()["total"];
            }

            return 0;
        }
    }
}
?>