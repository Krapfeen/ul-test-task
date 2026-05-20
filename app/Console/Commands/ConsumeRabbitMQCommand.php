<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\Providers\MockEmailProvider;
use App\Services\Providers\MockSmsProvider;
use Exception;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

class ConsumeRabbitMQCommand extends Command
{
    protected $signature = 'rabbitmq:consume';
    protected $description = 'Consume messages from RabbitMQ queue';

    public function handle(): void
    {
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest')
        );

        $channel = $connection->channel();

        $channel->queue_declare(
            'notifications_queue',
            false,
            true,
            false,
            false,
            false,
            ['x-max-priority' => ['I', 10]]
        );

        $callback = function (AMQPMessage $msg) {
            $data = json_decode($msg->getBody(), true);
            $notification = Notification::find($data['notification_id']);

            if ($notification->status !== NotificationStatus::QUEUED) {
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
                return;
            }

            $notification->update(['status' => NotificationStatus::SENT]);

            $provider = $data['channel'] === 'sms' ? new MockSmsProvider() : new MockEmailProvider();
            $isTemporaryError = false;

            try {
                $delivered = $provider->send($data['recipient_id'], $data['message']);
                if ($delivered) {
                    $notification->update(['status' => NotificationStatus::DELIVERED]);
                } else {
                    $notification->update(['status' => NotificationStatus::DROPPED]);
                }
            } catch (RuntimeException $e) {
                $isTemporaryError = true;
                $notification->increment('retry_count');
            }

            if ($isTemporaryError && $notification->retry_count < 3) {
                $notification->update(['status' => NotificationStatus::QUEUED]);
                $this->error("Temporary error, retry #{$notification->retry_count}");
                $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'], false, true);
            } elseif ($isTemporaryError && $notification->retry_count >= 3) {
                $notification->update(['status' => NotificationStatus::DROPPED]);
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            } else {
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            }
        };

        $channel->basic_consume('notifications_queue', '', false, false, false, false, $callback);

        $this->info('Worker started. Waiting for messages...');

        while (true) {
            try {
                $channel->wait();
            } catch (Exception $e) {
                $this->error("Error in wait: " . $e->getMessage());
                break;
            }
        }

        $channel->close();
        $connection->close();
    }
}
