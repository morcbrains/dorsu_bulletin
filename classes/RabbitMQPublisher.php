<?php
require_once __DIR__ . "/../config/rabbitmq.php";

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

if (!class_exists("RabbitMQPublisher")) {
    class RabbitMQPublisher {
        private $connection;
        private $channel;
        private $connected = false;

        public function __construct() {
            try {
                $this->connection = new AMQPStreamConnection(
                    RABBITMQ_HOST,
                    RABBITMQ_PORT,
                    RABBITMQ_USER,
                    RABBITMQ_PASSWORD,
                    RABBITMQ_VHOST
                );

                $this->channel = $this->connection->channel();
                $this->connected = true;
            } catch (Exception $e) {
                $this->connected = false;
            }
        }

        public function isConnected() {
            return $this->connected;
        }

        public function publishNotification($type, $title, $message, $targetRole = "all", $createdBy = "") {
            if (!$this->connected) {
                return false;
            }

            $payload = [
                "type" => $type,
                "title" => $title,
                "message" => $message,
                "target_role" => $targetRole,
                "created_by" => $createdBy,
                "created_at" => date("Y-m-d H:i:s")
            ];

            try {
                $this->channel->queue_declare(
                    RABBITMQ_NOTIFICATION_QUEUE,
                    false,
                    true,
                    false,
                    false
                );

                $rabbitMessage = new AMQPMessage(
                    json_encode($payload),
                    [
                        "delivery_mode" => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                        "content_type" => "application/json"
                    ]
                );

                $this->channel->basic_publish($rabbitMessage, "", RABBITMQ_NOTIFICATION_QUEUE);

                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        public function getPrivateMessageQueueName($receiverId) {
            return "dorsu_private_messages_user_" . intval($receiverId);
        }

        public function publishPrivateMessage($receiverId, $payload) {
            if (!$this->connected) {
                return false;
            }

            $receiverId = intval($receiverId);

            if ($receiverId <= 0) {
                return false;
            }

            $queueName = $this->getPrivateMessageQueueName($receiverId);

            if (!is_array($payload)) {
                return false;
            }

            if (!isset($payload["queued_at"])) {
                $payload["queued_at"] = date("Y-m-d H:i:s");
            }

            if (!isset($payload["queue_name"])) {
                $payload["queue_name"] = $queueName;
            }

            try {
                $this->channel->queue_declare(
                    $queueName,
                    false,
                    true,
                    false,
                    false
                );

                $rabbitMessage = new AMQPMessage(
                    json_encode($payload),
                    [
                        "delivery_mode" => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                        "content_type" => "application/json"
                    ]
                );

                $this->channel->basic_publish($rabbitMessage, "", $queueName);

                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        public function close() {
            try {
                if ($this->channel) {
                    $this->channel->close();
                }

                if ($this->connection) {
                    $this->connection->close();
                }
            } catch (Exception $e) {
                return false;
            }

            return true;
        }
    }
}
?>