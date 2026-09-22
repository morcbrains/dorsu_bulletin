<?php
require_once __DIR__ . "/../config/rabbitmq.php";

use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection(
    RABBITMQ_HOST,
    RABBITMQ_PORT,
    RABBITMQ_USER,
    RABBITMQ_PASSWORD,
    RABBITMQ_VHOST
);

$channel = $connection->channel();

$channel->queue_declare(
    RABBITMQ_NOTIFICATION_QUEUE,
    false,
    true,
    false,
    false
);

echo "DOrSU Bulletin RabbitMQ Worker Started...\n";
echo "Waiting for notification queue messages...\n\n";

$callback = function ($message) {
    $data = json_decode($message->body, true);

    if ($data) {
        echo "New Queue Message Received\n";
        echo "Type: " . $data["type"] . "\n";
        echo "Title: " . $data["title"] . "\n";
        echo "Message: " . $data["message"] . "\n";
        echo "Target Role: " . $data["target_role"] . "\n";
        echo "Created By: " . $data["created_by"] . "\n";
        echo "Created At: " . $data["created_at"] . "\n";
        echo "-----------------------------------\n\n";
    } else {
        echo "Invalid queue message received.\n\n";
    }

    $message->ack();
};

$channel->basic_qos(null, 1, null);

$channel->basic_consume(
    RABBITMQ_NOTIFICATION_QUEUE,
    "",
    false,
    false,
    false,
    false,
    $callback
);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>