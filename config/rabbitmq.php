<?php
require_once __DIR__ . "/../vendor/autoload.php";

define("RABBITMQ_HOST", "localhost");
define("RABBITMQ_PORT", 5672);
define("RABBITMQ_USER", "guest");
define("RABBITMQ_PASSWORD", "guest");
define("RABBITMQ_VHOST", "/");

define("RABBITMQ_NOTIFICATION_QUEUE", "dorsu_notifications");
?>