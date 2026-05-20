<?php

namespace App\Services\Producers;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQProducer
{
    public function publish(array $data, int $priority): void
    {
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest')
        );

        $channel = $connection->channel();

        $channel->queue_declare(
            'notifications_queue', false, true, false, false, false, [
                'x-max-priority' => ['I', 10]
            ]
        );

        $msg = new AMQPMessage(json_encode($data), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'priority' => $priority,
        ]);

        $channel->basic_publish($msg, '', 'notifications_queue');

        $channel->close();
        $connection->close();
    }
}
