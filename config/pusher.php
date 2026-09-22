<?php
require_once __DIR__ . "/../vendor/autoload.php";

define("PUSHER_APP_ID", "2155802");
define("PUSHER_APP_KEY", "1d5f01074c2e687fb414");
define("PUSHER_APP_SECRET", "065e8c990e3921d3ccc1");
define("PUSHER_APP_CLUSTER", "ap1");

function getPusherInstance() {
    return new Pusher\Pusher(
        PUSHER_APP_KEY,
        PUSHER_APP_SECRET,
        PUSHER_APP_ID,
        [
            "cluster" => PUSHER_APP_CLUSTER,
            "useTLS" => true
        ]
    );
}
?>